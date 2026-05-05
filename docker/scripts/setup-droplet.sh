#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════════
# DigitalOcean Droplet First-Time Setup — BoldMark PMS
# Run this ONCE on a fresh Ubuntu 24.04 Droplet (as root) before
# the first GitHub Actions deploy.
# ═══════════════════════════════════════════════════════════════════════
set -euo pipefail

echo "── Installing Docker Engine ─────────────────────────────────────"
apt-get update -qq
apt-get install -y -qq ca-certificates curl gnupg

install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg \
  | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
chmod a+r /etc/apt/keyrings/docker.gpg

echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] \
  https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" \
  | tee /etc/apt/sources.list.d/docker.list > /dev/null

apt-get update -qq
apt-get install -y -qq \
  docker-ce docker-ce-cli containerd.io \
  docker-buildx-plugin docker-compose-plugin

echo "── Configuring Docker ───────────────────────────────────────────"
systemctl enable docker
systemctl start docker

echo "── Creating deploy directory ────────────────────────────────────"
mkdir -p /opt/boldmark
chmod 755 /opt/boldmark

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  ✅ Droplet setup complete!"
echo "  Docker:         $(docker --version)"
echo "  Docker Compose: $(docker compose version)"
echo ""
echo "  Next: push to the phase-2 branch to trigger CI/CD deploy."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
