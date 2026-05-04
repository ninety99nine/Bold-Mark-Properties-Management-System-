#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════════
# One-Time EC2 Bootstrap — BoldMark PMS
# ═══════════════════════════════════════════════════════════════════════
# Run ONCE on a fresh Ubuntu 24.04 EC2 instance:
#   sudo bash setup-ec2.sh
#
# Idempotent: re-running is safe — it skips steps that are already done.
#
# What it does:
#   - Updates apt packages
#   - Installs Docker Engine + Compose plugin
#   - Installs AWS CLI v2
#   - Installs certbot (snap)
#   - Creates /opt/boldmark/ deploy directory
#   - Adds 'ubuntu' user to docker group
#   - Configures unattended-upgrades for security patches
#   - Sets up daily DB backup cron job
#
# After this script, you still need to:
#   1. Configure DNS (point portal.boldmarkprop.co.za → this EC2's Elastic IP)
#   2. Obtain Let's Encrypt SSL cert (instructions printed at end)
#   3. Push to main from your local repo — CI/CD will deploy.
#   4. Run /opt/boldmark/docker/scripts/first-deploy.sh after first deploy succeeds.
# ═══════════════════════════════════════════════════════════════════════
set -euo pipefail

DOMAIN="portal.boldmarkprop.co.za"
DEPLOY_USER="ubuntu"
DEPLOY_DIR="/opt/boldmark"
EMAIL="noreply@boldmarkprop.co.za"

if [ "$(id -u)" -ne 0 ]; then
  echo "ERROR: This script must be run as root (use sudo)."
  exit 1
fi

step() { echo ""; echo "━━━ $1 ━━━"; }
ok()   { echo "  ✓ $1"; }
skip() { echo "  ⊘ $1 (already done)"; }

# ── 1. System update + base packages ─────────────────────────────────
step "1. System update + base packages"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get upgrade -y
apt-get install -y \
  ca-certificates curl gnupg lsb-release unzip jq \
  unattended-upgrades htop net-tools
ok "System updated"

# ── 2. Docker Engine + Compose plugin ────────────────────────────────
step "2. Docker Engine + Compose plugin"
if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
  skip "Docker + Compose plugin"
else
  install -m 0755 -d /etc/apt/keyrings
  curl -fsSL https://download.docker.com/linux/ubuntu/gpg \
    | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
  chmod a+r /etc/apt/keyrings/docker.gpg
  echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" \
    > /etc/apt/sources.list.d/docker.list
  apt-get update -y
  apt-get install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
  systemctl enable --now docker
  ok "Docker installed: $(docker --version)"
fi

# Allow ubuntu user to run docker without sudo
if ! id -nG "$DEPLOY_USER" | grep -qw docker; then
  usermod -aG docker "$DEPLOY_USER"
  ok "Added '$DEPLOY_USER' to docker group (re-login or run: newgrp docker)"
else
  skip "User '$DEPLOY_USER' already in docker group"
fi

# ── 3. AWS CLI v2 ────────────────────────────────────────────────────
step "3. AWS CLI v2"
if command -v aws >/dev/null 2>&1; then
  skip "AWS CLI: $(aws --version 2>&1)"
else
  ARCH=$(dpkg --print-architecture)
  case "$ARCH" in
    amd64) AWS_URL="https://awscli.amazonaws.com/awscli-exe-linux-x86_64.zip" ;;
    arm64) AWS_URL="https://awscli.amazonaws.com/awscli-exe-linux-aarch64.zip" ;;
    *) echo "Unsupported arch: $ARCH"; exit 1 ;;
  esac
  curl -fsSL "$AWS_URL" -o /tmp/awscliv2.zip
  unzip -q /tmp/awscliv2.zip -d /tmp/
  /tmp/aws/install
  rm -rf /tmp/aws /tmp/awscliv2.zip
  ok "AWS CLI installed: $(aws --version 2>&1)"
fi

# ── 4. Certbot (via snap, the recommended install) ───────────────────
step "4. Certbot (Let's Encrypt)"
if command -v certbot >/dev/null 2>&1; then
  skip "Certbot: $(certbot --version 2>&1)"
else
  apt-get install -y snapd
  snap install core
  snap refresh core
  snap install --classic certbot
  ln -sf /snap/bin/certbot /usr/bin/certbot
  ok "Certbot installed"
fi

# ── 5. Deploy directory ──────────────────────────────────────────────
step "5. Deploy directory at $DEPLOY_DIR"
mkdir -p "$DEPLOY_DIR"
mkdir -p /var/log/boldmark
chown -R "$DEPLOY_USER:$DEPLOY_USER" "$DEPLOY_DIR" /var/log/boldmark
ok "Created $DEPLOY_DIR (owned by $DEPLOY_USER)"

# ── 6. Unattended security upgrades ──────────────────────────────────
step "6. Unattended security upgrades"
dpkg-reconfigure -f noninteractive unattended-upgrades >/dev/null 2>&1 || true
ok "Unattended-upgrades configured (security patches automatic)"

# ── 7. Daily DB backup cron ──────────────────────────────────────────
step "7. Daily DB backup cron job"
CRON_LINE="0 2 * * * $DEPLOY_USER bash $DEPLOY_DIR/docker/scripts/backup-db.sh >> /var/log/boldmark/backup.log 2>&1"
if grep -qF "$DEPLOY_DIR/docker/scripts/backup-db.sh" /etc/crontab 2>/dev/null; then
  skip "Backup cron already installed"
else
  echo "$CRON_LINE" >> /etc/crontab
  ok "Backup cron installed (runs daily at 02:00 UTC)"
fi

# ── 8. Print summary + next steps ────────────────────────────────────
PUBLIC_IP=$(curl -fsSL -H "X-aws-ec2-metadata-token: $(curl -fsSL -X PUT 'http://169.254.169.254/latest/api/token' -H 'X-aws-ec2-metadata-token-ttl-seconds: 60')" http://169.254.169.254/latest/meta-data/public-ipv4 2>/dev/null || echo "<unknown>")

cat <<EOF

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  ✅ EC2 bootstrap complete!

  Instance public IP : $PUBLIC_IP

  NEXT STEPS:

  1. DNS: Point $DOMAIN → $PUBLIC_IP (A record)
     Wait for propagation:  dig +short $DOMAIN

  2. Get the SSL certificate (one-time, requires DNS to be live):

       sudo docker run --rm \\
         -p 80:80 \\
         -v /etc/letsencrypt:/etc/letsencrypt \\
         certbot/certbot certonly --standalone \\
         -d $DOMAIN \\
         --email $EMAIL \\
         --agree-tos --no-eff-email --non-interactive

  3. Push to main from your local repo — GitHub Actions will deploy.

  4. After the first successful deploy, run:

       sudo bash $DEPLOY_DIR/docker/scripts/first-deploy.sh

  5. Verify:
       https://$DOMAIN          (login page)
       https://$DOMAIN/horizon  (queue dashboard)
       https://$DOMAIN/up       (health check)

  NOTE: Re-login as $DEPLOY_USER (or run 'newgrp docker') so the
        docker group membership takes effect.
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
EOF
