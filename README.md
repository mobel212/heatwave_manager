# 🌡️ HeatGuard
### Not automated. Accountable.

> A heat stress management dashboard for site managers in construction, agriculture, and manufacturing — built for the **WeatherWise Hack 2026**.

---

## The problem

Every year, thousands of workers suffer heat stroke on the job. Many die. In most countries, there is still no law that forces a manager to warn workers when temperatures become dangerous — and when accidents happen, there is often no record of who knew what, and when.

At the same time, most field workers don't have a laptop or a reliable data plan. They have a basic smartphone and Telegram.

HeatGuard was built to solve both problems.

---

## What it does

- **Live heat risk** — pulls real-time weather from [Open-Meteo](https://open-meteo.com) (free, no API key needed) and displays a Red / Yellow / Green risk level with a 12-hour forecast
- **Multi-site support** — manage multiple physical locations, each with their own GPS coordinates and group of workers
- **Telegram alerts** — the manager clicks one button to send a heat alert to all workers at a site via Telegram bot (free, works on 2G)
- **Worker check-in** — workers reply "OK" to the bot; the dashboard logs the exact time, creating a timestamped safety record
- **No automation** — alerts are manual by design (see [Why not automated?](#why-not-automated))

---

## Why not automated?

This was the most deliberate decision in the project.

The obvious approach is: *if temperature exceeds 40°C, send the alert automatically.* We chose not to do that.

In most countries, a site manager has a **legal duty of care** toward their workers. When a worker is injured from heat, the first question from a labor inspector is: *"Did the manager know about the conditions, and what did they do?"*

When the manager has to physically click the send button, that click is a legal act. The alert is a document. The worker's "OK" reply is a receipt.

This also protects workers. If a worker feels unwell and needs to stop, the check-in record gives them a formal, timestamped channel — not just walking off the job.

**HeatGuard is not an automation tool. It is a compliance tool.**

---

## Tech stack

| Layer | Technology |
|---|---|
| Backend | PHP 7.4+ with MySQLi and cURL |
| Database | MySQL |
| Frontend | React 18 (CDN), Tailwind CSS (CDN), Babel standalone |
| Weather | [Open-Meteo API](https://open-meteo.com) — free, no key required |
| Messaging | [Telegram Bot API](https://core.telegram.org/bots/api) — free, unlimited |
| Server | Apache (XAMPP locally, InfinityFree or any PHP host for production) |
| Dev tunnel | ngrok (for Telegram webhook during local development) |

No build step, no npm, no Composer. Every dependency loads from a CDN or is a built-in PHP function.

---
## Local setup (XAMPP)

### 1. Place files

Copy the project folder into your Apache document root:

```
htdocs/heatwave-manager/
```

### 2. Create the database

Import `schema.sql` into MySQL:


Or use phpMyAdmin → Import → choose `schema.sql`.

### 3. Configure

```bash
cp backend/config.example.php backend/config.php
```

Open `backend/config.php` and fill in your Telegram bot token (get one free from [@BotFather](https://t.me/BotFather)).  
Update the database credentials in `backend/db.php` if needed.

### 4. Set the Telegram webhook

Start ngrok:

```bash
ngrok http 80
```

Register the webhook (paste in your browser):

```
https://api.telegram.org/botYOUR_TOKEN/setWebhook?url=https://YOUR-NGROK-URL.ngrok-free.app/heatwave-manager/backend/telegram_webhook.php
```

### 5. Open the dashboard

```
http://localhost/heatwave-manager/index.html
```



## License

MIT — free to use, modify, and deploy.
