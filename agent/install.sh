#!/usr/bin/env bash
#
# WP Server Toolkit Pro — Agent Installer (Ubuntu/Debian)
# Developed by: Saiful Islam (aThemeArt)
#
# Usage:
#   sudo bash install.sh --site-url "https://example.com/wp-json/wp-server-toolkit/v1" \
#                         --registration-token "xxxxxxxx" \
#                         --name "Production" \
#                         [--interval 60]
#
# What this does:
#   1. Exchanges the short-lived registration token for a permanent bearer
#      token via POST /agent/register (HTTPS only).
#   2. Stores the bearer token in /etc/wp-server-toolkit/agent.conf, mode 600,
#      owned by root — never printed to the terminal or logged.
#   3. Installs the collector script and a systemd service + timer that
#      runs it on the configured interval (default 60s).
#
# This script never grants the agent shell-command execution rights from
# WordPress — see wpst-agent.sh for the fixed set of read-only collectors.
#
set -euo pipefail

SITE_URL=""
REG_TOKEN=""
SERVER_NAME=""
INTERVAL=60
INSECURE=0
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

while [[ $# -gt 0 ]]; do
	case "$1" in
		--site-url) SITE_URL="$2"; shift 2 ;;
		--registration-token) REG_TOKEN="$2"; shift 2 ;;
		--name) SERVER_NAME="$2"; shift 2 ;;
		--interval) INTERVAL="$2"; shift 2 ;;
		--insecure) INSECURE=1; shift 1 ;;
		*) echo "Unknown argument: $1" >&2; exit 1 ;;
	esac
done

CURL_TLS_OPTS=()
if [[ "$INSECURE" -eq 1 ]]; then
	CURL_TLS_OPTS+=(--insecure)
	echo "-------------------------------------------------------------------"
	echo "WARNING: --insecure disables TLS certificate verification."
	echo "This is intended ONLY for local development against a self-signed"
	echo "or hostname-mismatched certificate (e.g. Local by Flywheel, Valet,"
	echo "Docker reverse proxies). Never use this against a production site"
	echo "— it removes protection against man-in-the-middle interception of"
	echo "your monitoring credential."
	echo "-------------------------------------------------------------------"
fi

if [[ -z "$SITE_URL" || -z "$REG_TOKEN" ]]; then
	echo "Usage: sudo bash install.sh --site-url <url> --registration-token <token> [--name <name>] [--interval <seconds>]" >&2
	exit 1
fi

if [[ "$SITE_URL" != https://* ]]; then
	echo "Refusing to proceed: --site-url must use HTTPS. Monitoring credentials must never be sent over plain HTTP." >&2
	exit 1
fi

if [[ $EUID -ne 0 ]]; then
	echo "This installer must be run as root (it writes to /etc and /etc/systemd/system)." >&2
	exit 1
fi

command -v curl >/dev/null 2>&1 || { echo "curl is required." >&2; exit 1; }

HOSTNAME_FQDN=$(hostname -f 2>/dev/null || hostname)
AGENT_ID=$(head -c 16 /dev/urandom | od -An -tx1 | tr -d ' \n')

echo "Registering agent with ${SITE_URL}..."

RESPONSE=$(curl --silent --show-error --fail --max-time 15 "${CURL_TLS_OPTS[@]}" \
	--header "Content-Type: application/json" \
	--request POST \
	--data "{\"registration_token\":\"${REG_TOKEN}\",\"hostname\":\"${HOSTNAME_FQDN}\",\"agent_id\":\"${AGENT_ID}\",\"agent_version\":\"1.0.0\"}" \
	"${SITE_URL}/agent/register") || {
		echo "Registration failed. Possible causes:" >&2
		echo "  - The token is invalid, already used, or expired (tokens expire after 15 minutes)." >&2
		echo "  - TLS certificate verification failed (self-signed/local cert) — retry with --insecure for local testing only." >&2
		echo "  - A security plugin or server rule is blocking /wp-json/ requests — test manually:" >&2
		echo "      curl -k -v -X POST -H 'Content-Type: application/json' -d '{}' '${SITE_URL}/agent/register'" >&2
		exit 1
	}

BEARER=$(echo "$RESPONSE" | grep -oE '"bearer":"[^"]+"' | cut -d'"' -f4)

if [[ -z "$BEARER" ]]; then
	echo "Registration succeeded but no bearer token was returned. Response: $RESPONSE" >&2
	exit 1
fi

echo "Registration successful."

mkdir -p /etc/wp-server-toolkit
cat > /etc/wp-server-toolkit/agent.conf <<-EOF
	# WP Server Toolkit Pro agent configuration
	# This file contains a monitoring credential — keep mode 600, root-owned.
	WPST_SITE_URL="${SITE_URL}"
	WPST_BEARER="${BEARER}"
	WPST_INSECURE="${INSECURE}"
EOF
chmod 600 /etc/wp-server-toolkit/agent.conf
chown root:root /etc/wp-server-toolkit/agent.conf

install -m 750 -o root -g root "${SCRIPT_DIR}/wpst-agent.sh" /usr/local/bin/wpst-agent.sh

# Run as a dedicated unprivileged system user — least privilege, per spec.
if ! id wpst-agent >/dev/null 2>&1; then
	useradd --system --no-create-home --shell /usr/sbin/nologin wpst-agent
fi
# The agent user needs read access to /etc/wp-server-toolkit/agent.conf.
setfacl -m u:wpst-agent:r /etc/wp-server-toolkit/agent.conf 2>/dev/null || chgrp wpst-agent /etc/wp-server-toolkit/agent.conf

cat > /etc/systemd/system/wpst-agent.service <<-EOF
	[Unit]
	Description=WP Server Toolkit Pro monitoring agent (single run)
	After=network-online.target
	Wants=network-online.target

	[Service]
	Type=oneshot
	User=wpst-agent
	Group=wpst-agent
	ExecStart=/usr/local/bin/wpst-agent.sh
	# Hardening: no write access outside what's explicitly needed.
	ProtectSystem=strict
	ProtectHome=true
	PrivateTmp=true
	NoNewPrivileges=true
EOF

cat > /etc/systemd/system/wpst-agent.timer <<-EOF
	[Unit]
	Description=Run WP Server Toolkit Pro agent every ${INTERVAL}s

	[Timer]
	OnBootSec=15
	OnUnitActiveSec=${INTERVAL}s
	AccuracySec=5s

	[Install]
	WantedBy=timers.target
EOF

systemctl daemon-reload
systemctl enable --now wpst-agent.timer

echo ""
echo "✓ WP Server Toolkit Pro agent installed and running (every ${INTERVAL}s)."
echo "  Config:  /etc/wp-server-toolkit/agent.conf (mode 600)"
echo "  Script:  /usr/local/bin/wpst-agent.sh"
echo "  Timer:   systemctl status wpst-agent.timer"
echo "  Logs:    journalctl -u wpst-agent.service"
echo ""
echo "Developed by: Saiful Islam (aThemeArt)"
