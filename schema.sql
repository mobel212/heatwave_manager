-- ============================================================
-- Heat Stress Manager – Database Schema
-- Database: heatwave_manager
-- Run this file once to initialise all tables.
-- ============================================================

CREATE DATABASE IF NOT EXISTS `heatwave_manager`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `heatwave_manager`;

-- ----------------------------------------------------------
-- workers
-- Stores every registered worker.
-- telegram_chat_id: obtained when the worker sends /start
--                   to the bot (stored by the webhook).
-- last_checkin: updated whenever the worker replies "OK".
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `workers` (
  `id`               INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `name`             VARCHAR(120)     NOT NULL,
  `phone`            VARCHAR(30)      NOT NULL DEFAULT '',
  `telegram_chat_id` VARCHAR(50)      NOT NULL DEFAULT '',
  `last_checkin`     DATETIME                  DEFAULT NULL,
  `created_at`       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_telegram_chat_id` (`telegram_chat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------
-- alerts
-- One row per "Send Alert" click by the manager.
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `alerts` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `sent_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `risk_level`  ENUM('Green','Yellow','Red') NOT NULL DEFAULT 'Green',
  `message`     TEXT          NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------
-- replies
-- Raw inbound messages from Telegram users.
-- Stored by telegram_webhook.php for auditing purposes.
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `replies` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `chat_id`     VARCHAR(50)   NOT NULL DEFAULT '',
  `message`     TEXT          NOT NULL,
  `received_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
