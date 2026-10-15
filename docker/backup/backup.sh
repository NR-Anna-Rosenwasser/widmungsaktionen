#!/bin/sh
# Daily database dump + uploads archive. Keeps BACKUP_KEEP_DAILY days and
# BACKUP_KEEP_WEEKLY weekly copies (Sundays). With RCLONE_REMOTE set, the
# backup folder is synced off-site (rclone.conf mounted at /config).
set -eu
[ -f /etc/backup.env ] && . /etc/backup.env

DIR=/backups
STAMP=$(date +%Y-%m-%d_%H%M)
mkdir -p "$DIR/daily" "$DIR/weekly"

echo "[backup] $STAMP: dumping database $PGDATABASE"
pg_dump --format=custom --no-owner "$PGDATABASE" > "$DIR/daily/.db_$STAMP.dump"
mv "$DIR/daily/.db_$STAMP.dump" "$DIR/daily/db_$STAMP.dump"

if [ -d /uploads ]; then
    tar -czf "$DIR/daily/.uploads_$STAMP.tar.gz" -C /uploads .
    mv "$DIR/daily/.uploads_$STAMP.tar.gz" "$DIR/daily/uploads_$STAMP.tar.gz"
fi

if [ "$(date +%u)" = "7" ]; then
    cp "$DIR/daily/db_$STAMP.dump" "$DIR/weekly/"
    [ -f "$DIR/daily/uploads_$STAMP.tar.gz" ] && cp "$DIR/daily/uploads_$STAMP.tar.gz" "$DIR/weekly/"
fi

find "$DIR/daily" -type f -mtime +"${BACKUP_KEEP_DAILY:-14}" -delete
find "$DIR/weekly" -type f -mtime +"$(( ${BACKUP_KEEP_WEEKLY:-8} * 7 ))" -delete

if [ -n "${RCLONE_REMOTE:-}" ]; then
    echo "[backup] syncing to $RCLONE_REMOTE"
    rclone sync "$DIR" "$RCLONE_REMOTE" --config /config/rclone.conf
fi

echo "[backup] done: $(du -sh "$DIR" | cut -f1) in $DIR"
