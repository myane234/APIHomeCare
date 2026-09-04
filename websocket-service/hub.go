package main

import (
	"encoding/json"
	"log"
	"sync"
)

type Hub struct {
	rooms map[uint64]map[*Client]bool

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
		broadcast:  make(chan WSMessage, 256),
		register:   make(chan *ClientSubscription),
		unregister: make(chan *ClientSubscription),
	}
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
