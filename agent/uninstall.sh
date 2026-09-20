#!/usr/bin/env bash
#
# WP Server Toolkit Pro — Agent Uninstaller
# Developed by: Saiful Islam (aThemeArt)
#
# Usage:
#   sudo bash uninstall.sh            # stops/removes the agent, keeps nothing sensitive behind
#
set -euo pipefail

if [[ $EUID -ne 0 ]]; then
	echo "This uninstaller must be run as root." >&2
	exit 1
fi

echo "Stopping and disabling WP Server Toolkit Pro agent..."
systemctl disable --now wpst-agent.timer 2>/dev/null || true
systemctl stop wpst-agent.service 2>/dev/null || true

rm -f /etc/systemd/system/wpst-agent.service
rm -f /etc/systemd/system/wpst-agent.timer
systemctl daemon-reload

rm -f /usr/local/bin/wpst-agent.sh

echo "Removing configuration (including the stored monitoring credential)..."
rm -rf /etc/wp-server-toolkit

if id wpst-agent >/dev/null 2>&1; then
	userdel wpst-agent 2>/dev/null || true
fi

echo ""
echo "✓ WP Server Toolkit Pro agent has been fully removed from this server."
echo "  Remember to also remove the corresponding server entry from the"
echo "  WP Server Toolkit → Servers screen in your WordPress dashboard."
