#!/bin/sh
# Wires up /etc/nginx/ssl/ to the real LE cert if it exists, or generates
# a self-signed placeholder so nginx can start before certbot runs.
# Never writes into certbot's managed /etc/letsencrypt/live/ path, which
# would cause certbot to use the -0001 suffix on first issuance.
set -e

DOMAIN="portal.boldmarkprop.co.za"
LE_CERT="/etc/letsencrypt/live/$DOMAIN/fullchain.pem"
SSL_DIR="/etc/nginx/ssl"

mkdir -p "$SSL_DIR"

if [ -f "$LE_CERT" ]; then
    ln -sf "/etc/letsencrypt/live/$DOMAIN/fullchain.pem" "$SSL_DIR/fullchain.pem"
    ln -sf "/etc/letsencrypt/live/$DOMAIN/privkey.pem"   "$SSL_DIR/privkey.pem"
else
    echo "► No SSL cert found — generating self-signed placeholder for $DOMAIN"
    openssl req -x509 -nodes -newkey rsa:2048 -days 1 \
        -keyout "$SSL_DIR/privkey.pem" \
        -out    "$SSL_DIR/fullchain.pem" \
        -subj   "/CN=$DOMAIN" 2>/dev/null
    echo "  Placeholder ready — certbot will replace this on next nginx restart"
fi

exec "$@"
