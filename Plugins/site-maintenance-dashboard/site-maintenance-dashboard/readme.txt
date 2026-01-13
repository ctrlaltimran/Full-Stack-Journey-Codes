=== Site Maintenance Dashboard (Client + Admin) ===
Contributors: you
Tags: maintenance, client dashboard, time tracking
Requires at least: 6.0
Tested up to: 6.5
Stable tag: 0.1.0
License: GPLv2 or later

A lightweight maintenance-package dashboard for clients with time tracking, work logs, and monthly reports, plus an admin panel to manage clients and plans.

== Setup ==
1) Install and activate the plugin.
2) Create 3 pages:
   - Client Signup: add shortcode [smd_signup]
   - Client Login: add shortcode [smd_login]
   - Client Dashboard: add shortcode [smd_dashboard]
3) Go to WP Admin → Maintenance → Settings and select the Dashboard page (for login redirects).

== Admin usage ==
- WP Admin → Maintenance → Clients
  - Create clients (auto-creates user + assigns Maintenance Client role)
  - Start/Stop timers per client (stop creates a log entry)
- WP Admin → Maintenance → Plans
  - Create plans with monthly hours
- WP Admin → Maintenance → Reports
  - Generate print-ready report (clients can Print/Save as PDF)
  - CSV export available inside the client dashboard

== Notes ==
- Automated tasks should be added via Logs (future), currently supported via AJAX endpoint.
- This is an MVP. Next improvements: tickets, approvals, email notifications, and PDF generation library.
