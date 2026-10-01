#!/bin/sh
set -eu

if [ -n "${DB_SSL_CA:-}" ]; then
    if [ -f "$DB_SSL_CA" ] && [ -r "$DB_SSL_CA" ]; then
        install -d -o root -g www-data -m 0750 /var/run/lavalust
        install -o www-data -g www-data -m 0440 "$DB_SSL_CA" /var/run/lavalust/aiven-ca.pem
        export DB_SSL_CA=/var/run/lavalust/aiven-ca.pem
        echo "Installed MySQL CA for PHP at $DB_SSL_CA"
    else
        echo "DB_SSL_CA source is missing or unreadable at container startup: $DB_SSL_CA" >&2
    fi
fi

exec /usr/local/bin/docker-php-entrypoint "$@"