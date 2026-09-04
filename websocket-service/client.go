package main

import (
	"encoding/json"
	"log"
	"net/http"
	"time"

	"github.com/gorilla/websocket"
)

const (
	writeWait      = 10 * time.Second
	pongWait       = 60 * time.Second
	pingPeriod     = (pongWait * 9) / 10
	maxMessageSize = 1024 * 8
)

var upgrader = websocket.Upgrader{
	ReadBufferSize:  2048,
	WriteBufferSize: 2048,
	CheckOrigin: func(r *http.Request) bool {
		return true
	},
}

type Client struct {
	hub       *Hub
	conn      *websocket.Conn
	send      chan []byte
	userID    uint64
	userType  string
	bookingID uint64
}

func (c *Client) readPump() {
	defer func() {
		c.hub.unregister <- &ClientSubscription{client: c, bookingID: c.bookingID}
		c.conn.Close()
	}()

	c.conn.SetReadLimit(maxMessageSize)
	c.conn.SetReadDeadline(time.Now().Add(pongWait))
	c.conn.SetPongHandler(func(string) error {
		c.conn.SetReadDeadline(time.Now().Add(pongWait))
		return nil
	})

	for {
		_, messageBytes, err := c.conn.ReadMessage()
		if err != nil {
			if websocket.IsUnexpectedCloseError(err, websocket.CloseGoingAway, websocket.CloseAbnormalClosure) {
				log.Printf("[WS Error] %v", err)
			}
			break
		}

		var msg WSMessage
		if err := json.Unmarshal(messageBytes, &msg); err != nil {
			log.Printf("[WS JSON Error] %v", err)
			continue
		}
		msg.BookingID = c.bookingID
		msg.SenderID = c.userID
		msg.SenderType = c.userType
		msg.Timestamp = time.Now()
		c.hub.broadcast <- msg
	}
}

func (c *Client) writePump() {
	ticker := time.NewTicker(pingPeriod)
	defer func() {
		ticker.Stop()
		c.conn.Close()
	}()

	for {
		select {
		case message, ok := <-c.send:
			c.conn.SetWriteDeadline(time.Now().Add(writeWait))
			if !ok {
				c.conn.WriteMessage(websocket.CloseMessage, []byte{})
				return
			}

			w, err := c.conn.NextWriter(websocket.TextMessage)
			if err != nil {
				return
			}
			w.Write(message)

			n := len(c.send)
			for i := 0; i < n; i++ {
				w.Write([]byte{'\n'})
				w.Write(<-c.send)
			}

			if err := w.Close(); err != nil {
				return
			}
		case <-ticker.C:
			c.conn.SetWriteDeadline(time.Now().Add(writeWait))
			if err := c.conn.WriteMessage(websocket.PingMessage, nil); err != nil {
				return
			}
		}
	}
}

func serveWs(hub *Hub, w http.ResponseWriter, r *http.Request, bookingID uint64, userID uint64, userType string) {
	conn, err := upgrader.Upgrade(w, r, nil)
	if err != nil {
		log.Println("[Upgrade Error]:", err)
		return
	}

	client := &Client{
		hub:       hub,
		conn:      conn,
		send:      make(chan []byte, 256),
		userID:    userID,
		userType:  userType,
		bookingID: bookingID,
	}

	client.hub.register <- &ClientSubscription{client: client, bookingID: bookingID}

	go client.writePump()
	go client.readPump()
}
