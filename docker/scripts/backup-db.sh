#!/bin/bash
# ═══════════════════════════════════════════════════════════════════
# Nightly MySQL Backup to S3
# ═══════════════════════════════════════════════════════════════════
# Add to the EC2 ubuntu crontab (crontab -e):
#   0 2 * * * /opt/boldmark/scripts/backup-db.sh >> /var/log/boldmark-backup.log 2>&1
#
# Keeps the last 30 daily backups in S3.
# Restoring: aws s3 cp s3://BUCKET/database/FILE.sql.gz /tmp/ && gunzip /tmp/FILE.sql.gz
# ═══════════════════════════════════════════════════════════════════
set -euo pipefail

DEPLOY_DIR="/opt/boldmark"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="/tmp/boldmark_db_${TIMESTAMP}.sql.gz"
# Use a separate S3 bucket for backups (not the app file-storage bucket)
S3_BUCKET="${BACKUP_S3_BUCKET:-boldmark-db-backups}"
S3_PREFIX="database"
RETAIN_DAYS=30

# Source the .env to get DB credentials
# shellcheck disable=SC1091
source <(grep -E '^(DB_ROOT_PASSWORD|DB_DATABASE)=' "$DEPLOY_DIR/.env" | sed 's/^/export /')

echo "[$(date)] Starting database backup..."

# Dump + compress in one pipe (no uncompressed file on disk)
docker compose -f "$DEPLOY_DIR/docker-compose.yml" exec -T db \
    mysqldump \
        --single-transaction \
        --routines \
        --triggers \
        --add-drop-database \
        -u root \
        -p"${DB_ROOT_PASSWORD}" \
        "${DB_DATABASE}" \
    | gzip > "$BACKUP_FILE"

BACKUP_SIZE=$(du -sh "$BACKUP_FILE" | cut -f1)
echo "[$(date)] Backup created: $BACKUP_FILE ($BACKUP_SIZE)"

# Upload to S3
aws s3 cp "$BACKUP_FILE" "s3://${S3_BUCKET}/${S3_PREFIX}/${TIMESTAMP}.sql.gz" \
    --storage-class STANDARD_IA

echo "[$(date)] Uploaded to s3://${S3_BUCKET}/${S3_PREFIX}/${TIMESTAMP}.sql.gz"

# Remove local temp file
rm -f "$BACKUP_FILE"

# Prune backups older than RETAIN_DAYS
echo "[$(date)] Pruning backups older than ${RETAIN_DAYS} days..."
CUTOFF=$(date -d "-${RETAIN_DAYS} days" +%Y%m%d 2>/dev/null || \
         date -v -${RETAIN_DAYS}d +%Y%m%d)  # macOS fallback

aws s3 ls "s3://${S3_BUCKET}/${S3_PREFIX}/" \
    | awk '{print $4}' \
    | while read -r file; do
        file_date="${file:0:8}"
        if [[ "$file_date" < "$CUTOFF" ]]; then
            aws s3 rm "s3://${S3_BUCKET}/${S3_PREFIX}/$file"
            echo "[$(date)] Deleted old backup: $file"
        fi
      done

echo "[$(date)] Backup complete."
