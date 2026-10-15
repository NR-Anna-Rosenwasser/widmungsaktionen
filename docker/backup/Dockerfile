# Nightly Postgres dump and uploads archive, optional off-site copy with rclone.
FROM postgres:17-alpine
RUN apk add --no-cache rclone tzdata
COPY backup.sh /usr/local/bin/backup
COPY entrypoint.sh /usr/local/bin/backup-entrypoint
RUN chmod +x /usr/local/bin/backup /usr/local/bin/backup-entrypoint
ENTRYPOINT ["backup-entrypoint"]
