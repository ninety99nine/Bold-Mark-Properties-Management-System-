#!/bin/sh
# Generates a self-signed placeholder cert if no Let's Encrypt cert exists yet,
# so nginx can start on first deploy before certbot runs.
set -e

DOMAIN="portal.boldmarkprop.co.za"
CERT_DIR="/etc/letsencrypt/live/$DOMAIN"

if [ ! -f "$CERT_DIR/fullchain.pem" ]; then
    echo "► No SSL cert found — generating self-signed placeholder for $DOMAIN"
    mkdir -p "$CERT_DIR"
    openssl req -x509 -nodes -newkey rsa:2048 -days 1 \
        -keyout "$CERT_DIR/privkey.pem" \
        -out  "$CERT_DIR/fullchain.pem" \
        -subj "/CN=$DOMAIN" 2>/dev/null
    echo "  Placeholder ready — certbot will replace this with a real cert"
fi

exec "$@"
