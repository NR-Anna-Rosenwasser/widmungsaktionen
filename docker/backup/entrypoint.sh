#!/bin/sh
set -eu

# cron does not pass the environment on: store it for the backup script.
env | grep -E '^(PG|BACKUP_|RCLONE_|TZ=)' | sed 's/^/export /; s/=\(.*\)$/="\1"/' > /etc/backup.env

echo "${BACKUP_SCHEDULE:-30 2 * * *} /usr/local/bin/backup >> /proc/1/fd/1 2>&1" > /etc/crontabs/root
echo "Backups scheduled: ${BACKUP_SCHEDULE:-30 2 * * *} (${TZ:-UTC})"

exec crond -f -l 8
