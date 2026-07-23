#!/usr/bin/env bash
# ALATAX HR — Frontend Vite'ları Ubuntu'da SSH bağımsız çalıştırır (nohup).
# PC / terminal kapanınca süreçler ayakta kalır.
#
# Kullanım (Ubuntu SSH'da):
#   cd ~/alatax-hr && bash scripts/ubuntu-frontend-start.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
FE="$ROOT/frontend"
LOG="$ROOT/logs/frontend"
PIDDIR="$ROOT/logs/pids"

mkdir -p "$LOG" "$PIDDIR"
cd "$FE"

# Eski Vite süreçlerini temizle (aynı portlar)
stop_one() {
  local name="$1"
  local pidfile="$PIDDIR/$name.pid"
  if [[ -f "$pidfile" ]]; then
    local old
    old="$(cat "$pidfile" || true)"
    if [[ -n "${old:-}" ]] && kill -0 "$old" 2>/dev/null; then
      kill "$old" 2>/dev/null || true
      sleep 1
      kill -9 "$old" 2>/dev/null || true
    fi
    rm -f "$pidfile"
  fi
}

start_one() {
  local filter="$1"
  local name="$2"
  local port="$3"
  stop_one "$name"
  echo "==> starting $name :$port"
  # nohup + disown: SSH kapanınca yaşar
  nohup pnpm --filter "$filter" dev -- --host --port "$port" \
    >"$LOG/$name.log" 2>&1 &
  local pid=$!
  echo "$pid" >"$PIDDIR/$name.pid"
  disown "$pid" 2>/dev/null || true
  echo "    pid=$pid  log=$LOG/$name.log"
}

# Node/pnpm PATH (login shell dışı nohup için)
export PATH="${PATH}:/usr/local/bin:$HOME/.local/bin"

start_one "@alatax/superadmin" superadmin 3001
start_one "@alatax/company" company 3002
start_one "@alatax/portal" portal 3003

sleep 2
echo ""
echo "Durum:"
for name in superadmin company portal; do
  pidfile="$PIDDIR/$name.pid"
  if [[ -f "$pidfile" ]] && kill -0 "$(cat "$pidfile")" 2>/dev/null; then
    echo "  OK  $name (pid $(cat "$pidfile"))"
  else
    echo "  FAIL $name — bkz. $LOG/$name.log"
  fi
done

SUNUCU_IP="$(hostname -I 2>/dev/null | awk '{print $1}')"
echo ""
echo "URL'ler:"
echo "  SuperAdmin  http://${SUNUCU_IP:-SUNUCU_IP}:3001"
echo "  Company     http://${SUNUCU_IP:-SUNUCU_IP}:3002"
echo "  Portal      http://${SUNUCU_IP:-SUNUCU_IP}:3003"
echo ""
echo "Durdurmak: bash scripts/ubuntu-frontend-stop.sh"
