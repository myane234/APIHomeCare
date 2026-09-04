package main

import (
	"encoding/json"
	"flag"
	"fmt"
	"log"
	"net/http"
	"os"
	"strconv"
)

func main() {
	port := flag.String("port", "8080", "HTTP server port")
	flag.Parse()

	if envPort := os.Getenv("PORT"); envPort != "" {
		*port = envPort
	}

	hub := newHub()
	go hub.run()

	http.HandleFunc("/ws", func(w http.ResponseWriter, r *http.Request) {
		bookingIDStr := r.URL.Query().Get("booking_id")
		userIDStr := r.URL.Query().Get("user_id")
		userType := r.URL.Query().Get("user_type")

		if userType == "" {
			userType = "pasien"
		}

		bookingID, err1 := strconv.ParseUint(bookingIDStr, 10, 64)
		userID, err2 := strconv.ParseUint(userIDStr, 10, 64)

		if err1 != nil || err2 != nil || bookingID == 0 {
			http.Error(w, "Invalid parameters: booking_id and user_id are required", http.StatusBadRequest)
			return
		}

		serveWs(hub, w, r, bookingID, userID, userType)
	})

	http.HandleFunc("/broadcast", func(w http.ResponseWriter, r *http.Request) {
		if r.Method != http.MethodPost {
			http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
			return
		}

		var msg WSMessage
		if err := json.NewDecoder(r.Body).Decode(&msg); err != nil {
			http.Error(w, "Invalid request JSON", http.StatusBadRequest)
			return
		}

		if msg.BookingID == 0 {
			http.Error(w, "booking_id is required", http.StatusBadRequest)
			return
		}

		hub.broadcast <- msg

		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(map[string]interface{}{
			"success": true,
			"message": "Broadcast sent to room",
		})
	})

	http.HandleFunc("/health", func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(map[string]interface{}{
			"status":  "UP",
			"service": "Citra Homecare Go WebSocket Service",
		})
	})

	http.HandleFunc("/rooms", func(w http.ResponseWriter, r *http.Request) {
		hub.mu.RLock()
		defer hub.mu.RUnlock()

		rooms := make([]RoomInfo, 0, len(hub.rooms))
		for bookingID, clients := range hub.rooms {
			rooms = append(rooms, RoomInfo{
				BookingID:   bookingID,
				ClientCount: len(clients),
			})
		}

		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(map[string]interface{}{
			"total_rooms": len(rooms),
			"rooms":       rooms,
		})
	})

	addr := fmt.Sprintf("0.0.0.0:%s", *port)
	log.Printf("==================================================")
	log.Printf("Go WebSocket Service started on %s", addr)
	log.Printf("- WS Endpoint: ws://localhost:%s/ws?booking_id=123&user_id=1&user_type=pasien", *port)
	log.Printf("- REST Broadcast: POST http://localhost:%s/broadcast", *port)
	log.Printf("- Health Check: GET http://localhost:%s/health", *port)
	log.Printf("==================================================")

	if err := http.ListenAndServe(addr, nil); err != nil {
		log.Fatalf("Server failed to start: %v", err)
	}
}
