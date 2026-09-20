=== WP Server Toolkit Pro ===
Contributors: saifulislamathemeart
Tags: server monitoring, uptime, linux, php-fpm, nginx
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Monitor WordPress, Linux, Nginx, PHP-FPM, MySQL/MariaDB, SSL, uptime, logs and server health from the WordPress dashboard.

== Description ==

WP Server Toolkit Pro is a WordPress plugin, paired with a lightweight optional Linux agent, that brings server and site health into your WordPress dashboard:

* CPU, RAM, swap, disk, load, network and process monitoring (via the Linux agent)
* Nginx, PHP-FPM and MySQL/MariaDB service monitoring
* WordPress health: debug status, WP-Cron, filesystem permissions
* Deduplicated PHP/WordPress error tracking from debug.log
* SSL certificate expiry monitoring
* Multi-server support with tags/groups
* Alerts via Email, Telegram and Webhooks, with deduplication and recovery notices
* Historical metrics with automatic hourly/daily aggregation and configurable retention
* Security findings (WordPress + agent-reported Linux checks) — reported, never "guaranteed"

= Security by design =

The Linux agent exposes only a small set of explicitly defined monitoring endpoints (system, memory, disk, network, processes, php, php-fpm, nginx, mysql, security). **There is no arbitrary shell-execution endpoint of any kind.** The WordPress dashboard can never send a raw command to a server. If service-control features (e.g. "Restart Nginx") are added in a future release, they will be explicit, individually authorized actions — never free-form command input.

Server onboarding uses short-lived (15 minute), single-use registration tokens. No permanent credential is ever typed on the command line; the agent exchanges the registration token for a permanent bearer token during installation and stores it locally with restrictive file permissions.

== Installation ==

1. Upload the `wp-server-toolkit` folder to `/wp-content/plugins/`.
2. Activate the plugin through the "Plugins" screen in WordPress.
3. Go to **WP Server Toolkit → Servers → Add Server** to connect your first Linux server, or use the Overview and WordPress Health screens immediately — those work without any agent installed.

== Requirements ==

* WordPress 6.0+, PHP 8.1+, MySQL 8+/MariaDB
* For remote server monitoring: Ubuntu or Debian with `bash`, `curl`, and `systemd` (Nginx/PHP-FPM/MySQL support detected automatically if present)

== Troubleshooting ==

= curl: SSL: no alternative certificate subject name matches target hostname =

This means the certificate presented by your server doesn't cover the hostname you used — common on local development setups (Local by Flywheel, Valet, Docker reverse proxies) where several sites share one server and `localhost` isn't the site's real local domain. Use the site's actual configured domain (Settings → General → Site Address) instead of `localhost`. For local testing only, `install.sh` also accepts `--insecure` to skip certificate verification — never use this against a production site.

= Registration returns 403 =

Usually the same root cause as above (the request reached the wrong site/vhost), or a security plugin (Wordfence, iThemes Security, Really Simple Security) blocking unauthenticated REST API requests. Test the endpoint directly:

`curl -k -v -X POST -H "Content-Type: application/json" -d "{}" "https://yoursite.tld/wp-json/wp-server-toolkit/v1/agent/register"`

A `400` response means the REST route is reachable and working (you'll fix this once you supply a valid token). A repeated `403` means something in front of WordPress is blocking `/wp-json/` — allowlist the `wp-server-toolkit/v1` namespace there, or flush permalinks via Settings → Permalinks → Save.

== Changelog ==

= 1.0.0 =
* Initial Phase 1 release: core plugin, server registration, secure REST API, Linux agent, CPU/RAM/Disk/Load/Network/Processes, Nginx/PHP-FPM/MySQL, WordPress health, WP-Cron monitoring, filesystem checks, SSL monitoring, email/Telegram/webhook alerts with deduplication, historical metrics with retention, security findings, CSV-ready data model.
* Planned for later releases: uptime history dashboards, PDF/CSV reports, client/agency dashboard, WooCommerce monitoring, backup monitoring, additional Linux distributions (AlmaLinux, Rocky, Fedora), Apache support.

== Credits ==

Developed by: Saiful Islam (aThemeArt)
