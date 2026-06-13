#!/bin/bash
# start.sh — Tally Business Manager startup for Replit

MYSQL_DIR="/home/runner/mysql-data"
MYSQL_SOCK="/tmp/mysql.sock"
MYSQL_LOG="/tmp/mysql-error.log"
DB_NAME="InventaireLiontech_db"
SHARE_DIR="/nix/store/a4jsa8kjdn3wlccj2wkvhxqza38rpxzf-mariadb-server-10.11.13/share/mysql"
MARIADB_VER="10.11"

# ── 1. Create MySQL config file ─────────────────────────────────
mkdir -p /tmp/mysql-run
cat > /tmp/my.cnf << EOF
[mysqld]
datadir=${MYSQL_DIR}
socket=${MYSQL_SOCK}
skip-networking
log-error=${MYSQL_LOG}
innodb_use_native_aio=OFF
innodb_buffer_pool_size=32M
key_buffer_size=8M
max_connections=10
performance_schema=OFF
pid-file=/tmp/mysql-run/mysql.pid
lc-messages-dir=${SHARE_DIR}
EOF

# ── 2. Initialize MariaDB 10.11 data dir if needed ──────────────
VERSION_FILE="${MYSQL_DIR}/.mariadb_version"
if [ ! -f "${VERSION_FILE}" ] || [ "$(cat ${VERSION_FILE} 2>/dev/null)" != "${MARIADB_VER}" ]; then
  echo "[Tally] Initializing MariaDB ${MARIADB_VER} data directory..."
  rm -rf "${MYSQL_DIR}"
  mkdir -p "${MYSQL_DIR}"
  mariadb-install-db \
    --datadir="${MYSQL_DIR}" \
    --auth-root-authentication-method=normal \
    --skip-test-db \
    --basedir=/nix/store/a4jsa8kjdn3wlccj2wkvhxqza38rpxzf-mariadb-server-10.11.13 \
    2>&1 | tail -5
  INIT_EXIT=$?
  if [ $INIT_EXIT -eq 0 ]; then
    echo "${MARIADB_VER}" > "${VERSION_FILE}"
    echo "[Tally] Data directory initialized successfully."
  else
    echo "[Tally] WARNING: mariadb-install-db exited with code ${INIT_EXIT}"
    echo "[Tally] Attempting bootstrap initialization..."
    # Bootstrap fallback
    cat "${SHARE_DIR}/mysql_system_tables.sql" \
        "${SHARE_DIR}/mysql_system_tables_data.sql" | \
    mariadbd --defaults-file=/tmp/my.cnf --bootstrap 2>&1 | tail -5
    BOOT_EXIT=$?
    if [ $BOOT_EXIT -eq 0 ]; then
      echo "${MARIADB_VER}" > "${VERSION_FILE}"
      echo "[Tally] Bootstrap initialization done."
    else
      echo "[Tally] WARNING: Bootstrap also failed (exit ${BOOT_EXIT}). MySQL may not work."
    fi
  fi
fi

# ── 3. Kill stale mysqld and socket ─────────────────────────────
if [ -f "/tmp/mysql-run/mysql.pid" ]; then
  OLD_PID=$(cat /tmp/mysql-run/mysql.pid 2>/dev/null)
  kill "$OLD_PID" 2>/dev/null; sleep 1
fi
rm -f "${MYSQL_SOCK}" /tmp/mysql-run/mysql.pid

# ── 4. Start MariaDB ─────────────────────────────────────────────
echo "[Tally] Starting MariaDB..."
mariadbd --defaults-file=/tmp/my.cnf &
MYSQL_PID=$!

# Wait up to 25s for socket
WAITED=0
while [ $WAITED -lt 50 ]; do
  if [ -S "${MYSQL_SOCK}" ]; then
    echo "[Tally] MariaDB ready (pid=${MYSQL_PID})."
    break
  fi
  sleep 0.5
  WAITED=$((WAITED+1))
done

if [ ! -S "${MYSQL_SOCK}" ]; then
  echo "[Tally] WARNING: MariaDB did not start after 25s."
  echo "[Tally] Error log: ${MYSQL_LOG}"
  tail -5 "${MYSQL_LOG}" 2>/dev/null || true
fi

# ── 5. Import schema once ────────────────────────────────────────
if [ -S "${MYSQL_SOCK}" ]; then
  DB_EXISTS=$(mariadb --socket="${MYSQL_SOCK}" -u root \
    -e "SHOW DATABASES LIKE '${DB_NAME}';" 2>/dev/null | grep "${DB_NAME}" || true)
  if [ -z "${DB_EXISTS}" ]; then
    echo "[Tally] Importing database schema..."
    mariadb --socket="${MYSQL_SOCK}" -u root \
      < /home/runner/workspace/db-config.sql 2>&1 | grep -v "^$" | head -10
    echo "[Tally] Schema imported."
  else
    echo "[Tally] Database '${DB_NAME}' exists."
  fi
fi

# ── 6. Start PHP server ──────────────────────────────────────────
echo "[Tally] Starting PHP on :5000..."
exec php -S 0.0.0.0:5000 -t /home/runner/workspace
