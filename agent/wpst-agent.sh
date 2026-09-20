#!/usr/bin/env bash
#
# WP Server Toolkit Pro — Linux Agent
# Developed by: Saiful Islam (aThemeArt)
#
# Collects CPU/RAM/Disk/Load/Network/Process/PHP-FPM/Nginx/MySQL/Security
# metrics and reports them to the WordPress site's REST API over HTTPS.
#
# SECURITY: this agent only ever POSTs metric data to a fixed, small set
# of endpoints (/agent/heartbeat, /agent/metrics/<domain>). It never
# accepts or executes remote commands of any kind — there is no listener,
# no reverse shell, and no "/execute" style endpoint anywhere in this
# design. Read-only local reads (/proc, systemctl status, df, etc.) only.
#
# Config file (created by install.sh): /etc/wp-server-toolkit/agent.conf
#   WPST_SITE_URL="https://example.com/wp-json/wp-server-toolkit/v1"
#   WPST_BEARER="12.a1b2c3...."
#
set -euo pipefail

CONF_FILE="/etc/wp-server-toolkit/agent.conf"
AGENT_VERSION="1.0.0"

if [[ ! -f "$CONF_FILE" ]]; then
	echo "wpst-agent: config file not found at $CONF_FILE — run install.sh first." >&2
	exit 1
fi

# shellcheck source=/etc/wp-server-toolkit/agent.conf
source "$CONF_FILE"

if [[ -z "${WPST_SITE_URL:-}" || -z "${WPST_BEARER:-}" ]]; then
	echo "wpst-agent: WPST_SITE_URL / WPST_BEARER missing from config." >&2
	exit 1
fi

CURL_OPTS=(--silent --show-error --max-time 15
	--header "Authorization: Bearer ${WPST_BEARER}"
	--header "Content-Type: application/json"
	--header "X-WPST-Timestamp: $(date +%s)")

# Only ever set by install.sh's --insecure flag, for local/dev testing
# against self-signed or hostname-mismatched certificates. Never use in
# production — it disables TLS certificate verification.
if [[ "${WPST_INSECURE:-0}" -eq 1 ]]; then
	CURL_OPTS+=(--insecure)
fi

post_json() {
	local path="$1" json="$2"
	curl "${CURL_OPTS[@]}" --request POST --data "$json" "${WPST_SITE_URL}${path}" >/dev/null || {
		echo "wpst-agent: failed to POST ${path}" >&2
	}
}

json_escape() {
	# Minimal escaper for the plain strings we emit (no embedded quotes expected).
	printf '%s' "$1" | sed 's/\\/\\\\/g; s/"/\\"/g'
}

# ---------------------------------------------------------------------------
# Collectors
# ---------------------------------------------------------------------------

collect_system() {
	local cores load1 load5 load15 uptime_s hostname
	cores=$(nproc 2>/dev/null || echo 1)
	read -r load1 load5 load15 _ < /proc/loadavg
	uptime_s=$(awk '{print int($1)}' /proc/uptime)
	hostname=$(hostname -f 2>/dev/null || hostname)

	# CPU % sampled over ~1 second using /proc/stat deltas.
	local cpu1 cpu2 idle1 idle2 total1 total2 cpu_percent
	read -r _ u1 n1 s1 i1 io1 irq1 sirq1 _ < /proc/stat
	total1=$((u1+n1+s1+i1+io1+irq1+sirq1)); idle1=$i1
	sleep 1
	read -r _ u2 n2 s2 i2 io2 irq2 sirq2 _ < /proc/stat
	total2=$((u2+n2+s2+i2+io2+irq2+sirq2)); idle2=$i2
	local totald=$((total2-total1)) idled=$((idle2-idle1))
	if [[ $totald -gt 0 ]]; then
		cpu_percent=$(awk -v t="$totald" -v i="$idled" 'BEGIN{printf "%.1f", (1-(i/t))*100}')
	else
		cpu_percent=0
	fi

	cat <<-EOF
	{"hostname":"$(json_escape "$hostname")","cpu_percent":${cpu_percent},"cpu_cores":${cores},"load1":${load1},"load5":${load5},"load15":${load15},"uptime_seconds":${uptime_s}}
	EOF
}

collect_memory() {
	local total used free available cached swap_total swap_used
	total=$(awk '/MemTotal/{print $2}' /proc/meminfo)
	free=$(awk '/MemFree/{print $2}' /proc/meminfo)
	available=$(awk '/MemAvailable/{print $2}' /proc/meminfo)
	cached=$(awk '/^Cached/{print $2}' /proc/meminfo)
	swap_total=$(awk '/SwapTotal/{print $2}' /proc/meminfo)
	local swap_free
	swap_free=$(awk '/SwapFree/{print $2}' /proc/meminfo)
	swap_used=$((swap_total - swap_free))
	used=$((total - available))
	local ram_percent
	ram_percent=$(awk -v u="$used" -v t="$total" 'BEGIN{ if (t>0) printf "%.1f", (u/t)*100; else print 0 }')

	cat <<-EOF
	{"total_kb":${total},"used_kb":${used},"free_kb":${free},"available_kb":${available},"cached_kb":${cached},"swap_total_kb":${swap_total},"swap_used_kb":${swap_used},"ram_percent":${ram_percent}}
	EOF
}

collect_disk() {
	# JSON array of mount points we care about.
	local mounts=("/" "/home" "/var" "/tmp")
	local entries=()
	for m in "${mounts[@]}"; do
		if mountpoint -q "$m" 2>/dev/null || [[ "$m" == "/" ]]; then
			local line
			line=$(df -kP "$m" 2>/dev/null | awk 'NR==2{print $2","$3","$4","$5}') || continue
			[[ -z "$line" ]] && continue
			IFS=',' read -r total used avail pct <<< "$line"
			pct=${pct%\%}
			entries+=("{\"mount\":\"$(json_escape "$m")\",\"total_kb\":${total},\"used_kb\":${used},\"available_kb\":${avail},\"used_percent\":${pct}}")
		fi
	done
	local root_pct
	root_pct=$(df -kP / | awk 'NR==2{gsub("%","",$5); print $5}')
	printf '{"disk_percent":%s,"mounts":[%s]}' "${root_pct:-0}" "$(IFS=,; echo "${entries[*]}")"
}

collect_network() {
	local rx_total=0 tx_total=0 iface_entries=()
	while read -r line; do
		[[ "$line" == *:* ]] || continue
		local iface rx tx
		iface=$(echo "$line" | cut -d: -f1 | xargs)
		[[ "$iface" == "lo" ]] && continue
		read -r rx _ _ _ _ _ _ _ tx _ <<< "$(echo "$line" | cut -d: -f2)"
		rx_total=$((rx_total + rx))
		tx_total=$((tx_total + tx))
		iface_entries+=("{\"interface\":\"$(json_escape "$iface")\",\"rx_bytes\":${rx},\"tx_bytes\":${tx}}")
	done < /proc/net/dev

	local established time_wait
	established=$(ss -tan 2>/dev/null | grep -c ESTAB || echo 0)
	time_wait=$(ss -tan 2>/dev/null | grep -c TIME-WAIT || echo 0)

	printf '{"rx_bytes_total":%s,"tx_bytes_total":%s,"connections_established":%s,"connections_time_wait":%s,"interfaces":[%s]}' \
		"$rx_total" "$tx_total" "$established" "$time_wait" "$(IFS=,; echo "${iface_entries[*]}")"
}

collect_processes() {
	local total zombies
	total=$(ps -e --no-headers | wc -l)
	zombies=$(ps -e --no-headers -o stat | grep -c '^Z' || echo 0)
	printf '{"process_count":%s,"zombie_count":%s}' "$total" "$zombies"
}

service_status() {
	# Returns "true"/"false" for a given systemd unit, tolerant of missing systemd.
	local unit="$1"
	if command -v systemctl >/dev/null 2>&1; then
		systemctl is-active --quiet "$unit" 2>/dev/null && echo true || echo false
	else
		echo false
	fi
}

detect_php_fpm_unit() {
	# Different distros/PHP versions name this differently — discover it.
	systemctl list-units --type=service --all 2>/dev/null \
		| grep -oE 'php[0-9.]*-fpm\.service' \
		| head -n1 || true
}

collect_php_fpm() {
	local unit running
	unit=$(detect_php_fpm_unit)
	if [[ -z "$unit" ]]; then
		printf '{"detected":false,"running":false}'
		return
	fi
	running=$(service_status "$unit")
	printf '{"detected":true,"unit":"%s","running":%s}' "$(json_escape "$unit")" "$running"
}

collect_nginx() {
	local running version
	running=$(service_status "nginx.service")
	version=$(nginx -v 2>&1 | grep -oE '[0-9]+\.[0-9]+\.[0-9]+' || echo "")
	printf '{"running":%s,"version":"%s"}' "$running" "$(json_escape "$version")"
}

collect_mysql() {
	local running unit
	for unit in mysql.service mysqld.service mariadb.service; do
		if systemctl list-units --all 2>/dev/null | grep -q "$unit"; then
			running=$(service_status "$unit")
			printf '{"running":%s,"unit":"%s"}' "$running" "$(json_escape "$unit")"
			return
		fi
	done
	printf '{"detected":false,"running":false}'
}

collect_security() {
	local root_login pass_auth ufw_active fail2ban_active
	if [[ -r /etc/ssh/sshd_config ]]; then
		root_login=$(grep -iE '^\s*PermitRootLogin' /etc/ssh/sshd_config | awk '{print $2}' | tail -n1)
		pass_auth=$(grep -iE '^\s*PasswordAuthentication' /etc/ssh/sshd_config | awk '{print $2}' | tail -n1)
	fi
	ufw_active=$(command -v ufw >/dev/null 2>&1 && ufw status 2>/dev/null | grep -q "Status: active" && echo true || echo false)

	printf '{"ssh_permit_root_login":"%s","ssh_password_authentication":"%s","ufw_active":%s}' \
		"${root_login:-unknown}" "${pass_auth:-unknown}" "$ufw_active"
}

# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

main() {
	post_json "/agent/heartbeat" "{\"agent_version\":\"${AGENT_VERSION}\"}"
	post_json "/agent/metrics/system" "$(collect_system)"
	post_json "/agent/metrics/memory" "$(collect_memory)"
	post_json "/agent/metrics/disk" "$(collect_disk)"
	post_json "/agent/metrics/network" "$(collect_network)"
	post_json "/agent/metrics/processes" "$(collect_processes)"
	post_json "/agent/metrics/php-fpm" "$(collect_php_fpm)"
	post_json "/agent/metrics/nginx" "$(collect_nginx)"
	post_json "/agent/metrics/mysql" "$(collect_mysql)"
	post_json "/agent/metrics/security" "$(collect_security)"
}

main "$@"
