# WP Server Toolkit 

**Monitor WordPress, Linux servers, Nginx, PHP-FPM, MySQL/MariaDB, SSL, uptime, logs, and overall server health — all from your WordPress dashboard.**

Developed by **[Md Saiful Islam (aThemeArt)](https://athemeart.com)**  
Website: [https://athemeart.com](https://athemeart.com)

Donate: [https://athemeart.com/downloads/wp-server-toolkit/](https://athemeart.com/downloads/wp-server-toolkit/)

[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Requires PHP](https://img.shields.io/badge/PHP-8.1%2B-8892BF)](https://www.php.net/)
[![Requires WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759B)](https://wordpress.org/)

---

## About the Plugin

WP Server Toolkit Pro is a WordPress plugin paired with an optional, lightweight Linux agent. It brings real server and site health into the WordPress admin so you can see problems before your users do.

Whether you run a single site or many servers, the goal is simple: **one place to understand how healthy your stack is**.

### What you get

| Area | What is monitored |
|------|-------------------|
| **Linux agent** | CPU, RAM, swap, disk, load, network, processes |
| **Services** | Nginx, PHP-FPM, MySQL/MariaDB status |
| **WordPress** | Debug mode, WP-Cron, filesystem permissions, health checks |
| **Errors** | Deduplicated PHP / WordPress errors from `debug.log` |
| **SSL** | Certificate expiry monitoring |
| **Multi-server** | Register many servers, tag/group them, view from one dashboard |
| **Alerts** | Email, Telegram, and webhooks — with deduplication and recovery notices |
| **History** | Metrics with hourly/daily aggregation and configurable retention |
| **Security** | WordPress + agent-reported Linux findings (reported, never “guaranteed”) |

### Why use it?

- **See problems early** — high CPU, full disks, dying services, expiring SSL, broken cron.
- **No more SSH for basic checks** — status and trends live in the WordPress admin.
- **Multi-server friendly** — one dashboard for production, staging, and client servers.
- **Security-first design** — the agent only reports metrics. There is **no remote shell** and **no arbitrary command execution**.
- **Clean onboarding** — short-lived, single-use registration tokens. No permanent secret typed on the command line.

---

## Security by design

The Linux agent talks only to a small, fixed set of monitoring endpoints:

`system`, `memory`, `disk`, `network`, `processes`, `php`, `php-fpm`, `nginx`, `mysql`, `security`

- There is **no** `/execute` or free-form command endpoint.
- The WordPress dashboard **cannot** send arbitrary shell commands to a server.
- Onboarding uses **15-minute, single-use registration tokens**. The agent exchanges the token for a long-lived bearer credential and stores it locally with restrictive permissions (`mode 600`).

If service-control actions (e.g. “Restart Nginx”) are added later, they will be explicit, individually authorized actions — never free-form input.

---

## Requirements

**WordPress side**

- WordPress 6.0+
- PHP 8.1+
- MySQL 8+ or MariaDB

**Agent side (optional, for remote Linux monitoring)**

- Ubuntu or Debian
- `bash`, `curl`, `systemd`
- Nginx / PHP-FPM / MySQL support is detected automatically when present

---

## Installation

### 1. Install the WordPress plugin

1. Download or clone this repository.
2. Upload the `wp-server-toolkit` folder to `/wp-content/plugins/`.
3. Activate **WP Server Toolkit Pro** under **Plugins** in WordPress.
4. Open **Server Toolkit** in the admin menu.

You can use **Overview** and **WordPress Health** immediately — no agent required.

### 2. Connect a Linux server (optional)

1. Go to **Server Toolkit → Servers → Add Server**.
2. Enter a name (and optional tags) and submit.
3. Copy the install commands shown in the green success box.
4. Run them on the target Ubuntu/Debian server.

**Production (valid TLS certificate):**

```bash
# 1. Download both files
curl -fsSL https://YOUR-SITE.com/wp-content/plugins/wp-server-toolkit/agent/install.sh -o wpst-install.sh
curl -fsSL https://YOUR-SITE.com/wp-content/plugins/wp-server-toolkit/agent/wpst-agent.sh -o wpst-agent.sh

# 2. Install the agent
sudo bash wpst-install.sh \
  --site-url "https://YOUR-SITE.com/wp-json/wp-server-toolkit/v1" \
  --registration-token "YOUR_TOKEN" \
  --name "Production"
```

**Local development / self-signed certificate:**

```bash
# 1. Download both files (ignore SSL for local)
curl -k -fsSL https://localhost/your-site/wp-content/plugins/wp-server-toolkit/agent/install.sh -o wpst-install.sh
curl -k -fsSL https://localhost/your-site/wp-content/plugins/wp-server-toolkit/agent/wpst-agent.sh -o wpst-agent.sh

# 2. Install with --insecure
sudo bash wpst-install.sh \
  --site-url "https://localhost/your-site/wp-json/wp-server-toolkit/v1" \
  --registration-token "YOUR_TOKEN" \
  --name "Local Dev" \
  --insecure
```

> **Never use `--insecure` on a production server.** It disables TLS certificate verification.

After a successful install you should see:

```text
✓ WP Server Toolkit Pro agent installed and running (every 60s).
```

Useful commands:

```bash
systemctl status wpst-agent.timer
journalctl -u wpst-agent.service -n 30
```

---

## How to use

| Screen | Purpose |
|--------|---------|
| **Overview** | Snapshot of all servers + local WordPress health |
| **Servers** | Add/remove servers, see status, last seen, agent version |
| **WordPress** | Local WP health, cron, debug, filesystem |
| **Alerts** | Open/resolved alerts and history |
| **Security** | Security findings from WordPress and the agent |
| **Settings** | Polling interval, retention, thresholds, email / Telegram / webhook |

**Typical workflow**

1. Activate the plugin and check **Overview** / **WordPress**.
2. Add each Linux server you care about.
3. Configure alert channels and thresholds under **Settings**.
4. Watch the dashboard and act on alerts.

---

## Troubleshooting

### SSL: no alternative certificate subject name matches target hostname

Common on local stacks (Local, Valet, Docker, XAMPP) when the certificate does not include `localhost` or the hostname you used.

- Prefer the real local domain from **Settings → General → Site Address**.
- For local testing only, use `-k` when downloading and `--insecure` on `install.sh`.

### Registration returns 403

- Token already used or older than 15 minutes → create a **new** server and use a fresh token.
- Security plugin blocking unauthenticated REST → allowlist the `wp-server-toolkit/v1` namespace, or temporarily disable the blocker and test.
- Database schema incomplete (older installs) → deactivate/reactivate the plugin, or ensure columns `registration_token` and `registration_expires` exist on the servers table.

### Registration returns 404

- Permalinks set to **Plain** often need `/index.php` in the REST URL:

  ```text
  https://example.com/index.php/wp-json/wp-server-toolkit/v1
  ```

- Or go to **Settings → Permalinks**, choose a non-Plain structure, and Save.

### Quick endpoint test

```bash
curl -k -v -X POST \
  -H "Content-Type: application/json" \
  -d '{"registration_token":"TOKEN","hostname":"test","agent_id":"test","agent_version":"1.0.0"}' \
  "https://YOUR-SITE/wp-json/wp-server-toolkit/v1/agent/register"
```

- `201` / success JSON → working.  
- `400` → route reachable (fix body/token).  
- `403` → invalid/used token or blocked.  
- `404` → wrong URL or rewrite rules.

---

## Uninstall

On the Linux host:

```bash
sudo bash /path/to/uninstall.sh
```

Or remove the systemd units and `/etc/wp-server-toolkit/` manually.

In WordPress, deactivate and delete the plugin. Optional full data removal is controlled by the “Delete monitoring data on uninstall” setting.

---

## Contributing

Contributions are welcome and appreciated.

We want this project to stay useful, secure, and easy to run for developers, agencies, and site owners. You can help by:

- Reporting bugs and edge cases (especially other distros, reverse proxies, security plugins)
- Improving docs and install UX
- Adding collectors or health checks (without weakening the “no arbitrary execution” rule)
- Fixing UI/UX in the admin screens
- Adding tests and hardening the REST API / agent

### How to contribute

1. Fork the repository.
2. Create a feature branch (`git checkout -b feature/your-idea`).
3. Make focused, well-described commits.
4. Open a Pull Request with a clear explanation of the change and any risks.

Please keep the security model intact: **no free-form remote command execution**, and treat registration/bearer tokens carefully.

Ideas and discussion are welcome via Issues. If you build something useful on top of this, we would love to hear about it.

---

## Roadmap (high level)

Planned or considered for later releases:

- Richer uptime history dashboards
- PDF / CSV reports
- Agency / multi-client views
- Deeper WooCommerce monitoring
- Backup status monitoring
- Broader Linux support (AlmaLinux, Rocky, Fedora)
- Apache support

Nothing here is a promise of delivery date — contributions that move these forward are especially welcome.

---

## License

This project is licensed under the **GNU General Public License v2.0 or later**.

See [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html) / the `License` header in the plugin for details.

---

## Credits

**Developer:** Md Saiful Islam (aThemeArt)  
**Website:** [https://athemeart.com](https://athemeart.com)

Built with care for people who run WordPress in the real world — and who prefer observability over guesswork.

If this plugin helps you, a star on the repo or a kind word goes a long way. Contributions that make it better for everyone are even better.
