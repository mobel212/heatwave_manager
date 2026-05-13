<?php

// ── Telegram ─────────────────────────────────────────────────
// Get this from @BotFather on Telegram → /newbot
define('TELEGRAM_BOT_TOKEN', 'YOUR_BOT_TOKEN_HERE');
define('TELEGRAM_API_BASE',  'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN);

// ── Default site location ────────────────────────────────────
define('SITE_LATITUDE',  19.0760);
define('SITE_LONGITUDE', 72.8777);
define('SITE_LABEL',     'Default Site');

// ── CORS ─────────────────────────────────────────────────────
define('ALLOW_ORIGIN', '*');
