package main

import (
	"encoding/json"
	"log"
	"sync"
	"time"
)

type Hub struct {
	rooms    map[uint64]map[*Client]bool
	metadata map[uint64]*RoomInfo

	broadcast chan WSMessage

	register chan *ClientSubscription

	unregister chan *ClientSubscription

	mu sync.RWMutex
}

type ClientSubscription struct {
	client    *Client
	bookingID uint64
}

func newHub() *Hub {
	return &Hub{
		rooms:      make(map[uint64]map[*Client]bool),
		metadata:   make(map[uint64]*RoomInfo),
		broadcast:  make(chan WSMessage, 256),
		register:   make(chan *ClientSubscription),
		unregister: make(chan *ClientSubscription),
	}
}

func (h *Hub) upsertRoom(info RoomInfo) RoomInfo {
	h.mu.Lock()
	defer h.mu.Unlock()

	if existing, ok := h.metadata[info.BookingID]; ok {
		if info.Patient != nil {
			existing.Patient = info.Patient
		}
		if info.Nakes != nil {
			existing.Nakes = info.Nakes
		}
		if clients, ok := h.rooms[info.BookingID]; ok {
			existing.ClientCount = len(clients)
		}
		return *existing
	}

	if info.CreatedAt.IsZero() {
		info.CreatedAt = time.Now().UTC()
	}
	if info.ClientCount == 0 {
		info.ClientCount = len(h.rooms[info.BookingID])
	}
	h.metadata[info.BookingID] = &info
	return info
}

func (h *Hub) roomList() []RoomInfo {
	h.mu.RLock()
	defer h.mu.RUnlock()

	rooms := make([]RoomInfo, 0, len(h.metadata))
	for bookingID, info := range h.metadata {
		room := *info
		room.BookingID = bookingID
		room.ClientCount = len(h.rooms[bookingID])
		rooms = append(rooms, room)
	}
	return rooms
}

func (h *Hub) closeRoom(bookingID uint64) bool {
	h.mu.Lock()
	defer h.mu.Unlock()

	if _, exists := h.metadata[bookingID]; !exists {
		return false
	}

	if clients, ok := h.rooms[bookingID]; ok {
		for client := range clients {
			close(client.send)
		}
		delete(h.rooms, bookingID)
	}
	delete(h.metadata, bookingID)

	return true
}

func (h *Hub) run() {
	for {
		select {
		case sub := <-h.register:
			h.mu.Lock()
			if h.rooms[sub.bookingID] == nil {
				h.rooms[sub.bookingID] = make(map[*Client]bool)
			}
			h.rooms[sub.bookingID][sub.client] = true
			if info, ok := h.metadata[sub.bookingID]; ok {
				info.ClientCount = len(h.rooms[sub.bookingID])
			}
			h.mu.Unlock()
			log.Printf("[Hub] Client %d joined room booking:%d (Total clients in room: %d)",
				sub.client.userID, sub.bookingID, len(h.rooms[sub.bookingID]))

		case sub := <-h.unregister:
			h.mu.Lock()
			if clients, ok := h.rooms[sub.bookingID]; ok {
				if _, ok := clients[sub.client]; ok {
					delete(clients, sub.client)
					close(sub.client.send)
					if len(clients) == 0 {
						delete(h.rooms, sub.bookingID)
					}
					if info, ok := h.metadata[sub.bookingID]; ok {
						info.ClientCount = len(clients)
					}
					log.Printf("[Hub] Client %d left room booking:%d", sub.client.userID, sub.bookingID)
				}
			}
			h.mu.Unlock()

		case msg := <-h.broadcast:
			h.mu.RLock()
			clients, ok := h.rooms[msg.BookingID]
			if ok {
				bytes, err := json.Marshal(msg)
				if err == nil {
					for client := range clients {
						select {
						case client.send <- bytes:
						default:
							close(client.send)
							delete(clients, client)
						}
					}
				}
			}
			h.mu.RUnlock()
		}
	}
}
