#!/bin/bash
# ============================================================
#  start.sh — LionTech Business Manager startup (Replit)
#  MariaDB 10.11 via --skip-grant-tables + fast init loop
# ============================================================

MYSQL_SOCK=/tmp/mysql.sock
MYSQL_DATA=/home/runner/mysql-data
MYSQL_LOG=/tmp/mysqld.log
DB_SQL=/home/runner/workspace/db-config.sql
INIT_MARKER=$MYSQL_DATA/.lt_initialized

# ── 1. Kill any stale mysqld ────────────────────────────────
pkill -f "mysqld.*$MYSQL_DATA" 2>/dev/null
sleep 1
rm -f "$MYSQL_SOCK" /tmp/mysql.pid

# ── 2. Ensure data directory exists ────────────────────────
mkdir -p "$MYSQL_DATA"

# ── 3. Start MariaDB ────────────────────────────────────────
echo "[LionTech] Starting MariaDB..."
nohup mysqld \
    --datadir="$MYSQL_DATA" \
    --socket="$MYSQL_SOCK" \
    --pid-file=/tmp/mysql.pid \
    --skip-networking \
    --skip-grant-tables \
    --skip-slave-start \
    --skip-log-bin \
    --skip-name-resolve \
    --skip-host-cache \
    --performance-schema=0 \
    --user=runner \
    >"$MYSQL_LOG" 2>&1 &

MYSQL_PID=$!
echo "[LionTech] mysqld PID=$MYSQL_PID"

# ── 4. Wait for socket (up to 30s, check every 250ms) ───────
echo "[LionTech] Waiting for MariaDB socket..."
READY=0
for i in $(seq 1 120); do
    if mysqladmin --socket="$MYSQL_SOCK" -u root ping 2>/dev/null | grep -q alive; then
        READY=1
        echo "[LionTech] MariaDB ready after $((i*250))ms"
        break
    fi
    sleep 0.25
done

if [ $READY -eq 0 ]; then
    echo "[LionTech] ERROR: MariaDB did not respond. Last log:"
    tail -15 "$MYSQL_LOG" 2>/dev/null
    echo "[LionTech] Starting PHP anyway (app will show DB error)..."
    exec php -S 0.0.0.0:5000 -t /home/runner/workspace
fi

# ── 5. Import schema (first boot only) ──────────────────────
if [ ! -f "$INIT_MARKER" ]; then
    echo "[LionTech] First boot — importing database schema..."
    if [ -f "$DB_SQL" ]; then
        mysql --socket="$MYSQL_SOCK" -u root < "$DB_SQL" 2>/tmp/db_init_err.log
        RC=$?
        if [ $RC -eq 0 ]; then
            touch "$INIT_MARKER"
            echo "[LionTech] Schema imported successfully."
        else
            echo "[LionTech] Schema import had errors:"
            cat /tmp/db_init_err.log | head -20
            # Check if critical tables exist anyway
            if mysql --socket="$MYSQL_SOCK" -u root \
               -e "USE InventaireLiontech_db; SELECT 1 FROM users LIMIT 1;" 2>/dev/null; then
                touch "$INIT_MARKER"
                echo "[LionTech] Database usable — marked as initialized."
            fi
        fi
    else
        echo "[LionTech] WARNING: db-config.sql not found at $DB_SQL"
    fi
else
    echo "[LionTech] Database already initialized."
    # Verify connection works
    if mysql --socket="$MYSQL_SOCK" -u root \
       -e "USE InventaireLiontech_db; SELECT COUNT(*) FROM users;" 2>/dev/null; then
        echo "[LionTech] Database connection verified."
    else
        echo "[LionTech] WARNING: Could not verify database. Check mysqld logs."
    fi
fi

# ── 6. Start PHP server ─────────────────────────────────────
echo "[LionTech] Starting PHP server on port 5000..."
exec php -S 0.0.0.0:5000 -t /home/runner/workspace
