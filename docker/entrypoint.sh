#!/bin/sh
# What2Bring container entrypoint.
#  - Normal case: runs as www-data (image default) and checks that /data is writable.
#  - Optional: started as root (user: "0:0" + cap_add CHOWN, SETUID, SETGID) it fixes
#    the ownership of a bind-mounted data directory and then drops to www-data.
#  - While the app is not configured, prints the installer's setup token to the log.
set -eu

DATA_DIR="${W2B_DATA_DIR:-/data}"

if [ "$(id -u)" = "0" ]; then
    mkdir -p "$DATA_DIR"
    if [ "$(stat -c %u "$DATA_DIR")" != "$(id -u www-data)" ]; then
        echo "w2b-entrypoint: fixing ownership of $DATA_DIR for www-data"
        chown -R www-data:www-data "$DATA_DIR"
    fi
    exec setpriv --reuid=www-data --regid=www-data --init-groups "$0" "$@"
fi

probe="$DATA_DIR/.w2b-write-test.$$"
if ! ( : > "$probe" ) 2>/dev/null; then
    cat >&2 <<MSG
========================================================================
  What2Bring: the data directory $DATA_DIR is not writable for
  uid $(id -u) (www-data). The SQLite database cannot be created.

  With a bind mount (e.g. ./data:/data) run once on the host:
      sudo chown -R 33:33 ./data
  or use the named volume from the bundled docker-compose.yml.
========================================================================
MSG
    exit 1
fi
rm -f "$probe"

php /var/www/html/bin/setup-token.php | grep -v 'already configured' || true

exec "$@"
