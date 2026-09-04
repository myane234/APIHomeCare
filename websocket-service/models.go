package main

import "time"

type MessageType string

const (
	TypeChatMessage    MessageType = "chat_message"
	TypeLocationUpdate MessageType = "location_update"
	TypeJoinRoom       MessageType = "join_room"
	TypeLeaveRoom      MessageType = "leave_room"
	TypePing           MessageType = "ping"
	TypePong           MessageType = "pong"
	TypeError          MessageType = "error"
)

type WSMessage struct {
	Type       MessageType `json:"type"`
	BookingID  uint64      `json:"booking_id"`
	SenderID   uint64      `json:"sender_id"`
	SenderType string      `json:"sender_type"`
	SenderName string      `json:"sender_name,omitempty"`
	Content    string      `json:"content,omitempty"`
	Latitude   float64     `json:"latitude,omitempty"`
	Longitude  float64     `json:"longitude,omitempty"`
	Timestamp  time.Time   `json:"timestamp"`
}

type RoomInfo struct {
	BookingID   uint64       `json:"booking_id"`
	ClientCount int          `json:"client_count"`
	Patient     *Participant `json:"pasien,omitempty"`
	Nakes       *Participant `json:"nakes,omitempty"`
	CreatedAt   time.Time    `json:"created_at"`
}

type Participant struct {
	ID   uint64 `json:"id"`
	Name string `json:"name"`
}
