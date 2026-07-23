#!/usr/bin/env bash
# Frontend Vite süreçlerini durdurur.
# Kullanım: cd ~/alatax-hr && bash scripts/ubuntu-frontend-stop.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PIDDIR="$ROOT/logs/pids"

for name in superadmin company portal; do
  pidfile="$PIDDIR/$name.pid"
  if [[ -f "$pidfile" ]]; then
    pid="$(cat "$pidfile" || true)"
    if [[ -n "${pid:-}" ]] && kill -0 "$pid" 2>/dev/null; then
      echo "Stopping $name pid=$pid"
      kill "$pid" 2>/dev/null || true
      sleep 1
      kill -9 "$pid" 2>/dev/null || true
    fi
    rm -f "$pidfile"
  else
    echo "No pidfile for $name"
  fi
done

# Port temizliği (yetim süreçler)
for port in 3001 3002 3003; do
  pids="$(ss -tlnp 2>/dev/null | grep ":$port " | sed -n 's/.*pid=\([0-9]*\).*/\1/p' | sort -u || true)"
  for p in $pids; do
    echo "Killing leftover on :$port pid=$p"
    kill "$p" 2>/dev/null || true
  done
done

echo "Frontend durduruldu."
