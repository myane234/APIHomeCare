#!/bin/bash

SERVER_IP="192.168.18.12"
SERVER_USER="faruq"
REMOTE_DIR="/home/faruq/citra-ws-service"



ssh $SERVER_USER@$SERVER_IP "mkdir -p $REMOTE_DIR"

rsync -avz --exclude='.git' . $SERVER_USER@$SERVER_IP:$REMOTE_DIR/


ssh $SERVER_USER@$SERVER_IP "cd $REMOTE_DIR && go build -o ws-service main.go client.go hub.go models.go && nohup ./ws-service -port 8080 > ws.log 2>&1 &"

echo "=================================================="
echo "complete!"
echo "   - Server: http://$SERVER_IP:8080"
echo "   - WS URL: ws://$SERVER_IP:8080/ws?booking_id={ID}&user_id={USER_ID}&user_type={nakes|pasien}"
echo "=================================================="
