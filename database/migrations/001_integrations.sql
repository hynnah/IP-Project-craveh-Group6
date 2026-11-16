-- Craveh — Group 6 — Integrative Programming (IT 3206N)
-- Migration 001: schema required by the external API integrations.
--
-- Run ONCE against an existing craveh_group6 database, AFTER craveh_db_structure.sql:
--   phpMyAdmin > select craveh_group6 > SQL tab > paste this file > Go
--
-- This migration is additive. No table is dropped and no existing row is lost.

USE `craveh_group6`;

START TRANSACTION;

-- ─────────────────────────────────────────────────────────────────────────
-- 1. users — Google OAuth sign-in
-- ─────────────────────────────────────────────────────────────────────────
-- An account created through Google never sets a local password, so
-- password_hash has to accept NULL. google_id is the subject ("sub") claim
-- from Google's ID token: it is the stable key that identifies the same
-- person across logins, even if they change their email display name.
ALTER TABLE `users`
  MODIFY `password_hash` varchar(255) DEFAULT NULL,
  ADD COLUMN `google_id` varchar(255) DEFAULT NULL AFTER `role`,
  ADD COLUMN `auth_provider` enum('local','google') NOT NULL DEFAULT 'local' AFTER `google_id`,
  ADD COLUMN `avatar_url` varchar(500) DEFAULT NULL AFTER `auth_provider`,
  ADD UNIQUE KEY `uq_google_id` (`google_id`);

-- ─────────────────────────────────────────────────────────────────────────
-- 2. orders — payment state and the delivery pin
-- ─────────────────────────────────────────────────────────────────────────
-- payment_status is deliberately separate from status. status is where the
-- FOOD is (pending → preparing → delivered); payment_status is where the
-- MONEY is. A GCash order is paid before it is cooked; a COD order is
-- delivered before it is paid. One column could not express both.
ALTER TABLE `orders`
  ADD COLUMN `delivery_lat` decimal(10,7) DEFAULT NULL AFTER `delivery_address`,
  ADD COLUMN `delivery_lng` decimal(10,7) DEFAULT NULL AFTER `delivery_lat`,
  ADD COLUMN `payment_method` enum('cod','gcash') NOT NULL DEFAULT 'cod' AFTER `phone`,
  ADD COLUMN `payment_status` enum('unpaid','pending','paid','failed','refunded') NOT NULL DEFAULT 'unpaid' AFTER `payment_method`,
  ADD COLUMN `payment_reference` varchar(255) DEFAULT NULL AFTER `payment_status`,
  ADD COLUMN `paid_at` timestamp NULL DEFAULT NULL AFTER `payment_reference`,
  ADD KEY `idx_payment_status` (`payment_status`),
  ADD KEY `idx_payment_reference` (`payment_reference`);

-- ─────────────────────────────────────────────────────────────────────────
-- 3. payments — one row per payment attempt
-- ─────────────────────────────────────────────────────────────────────────
-- The order table records the CURRENT payment state. This table records the
-- attempts that produced it, including the failed ones, which is what an
-- audit trail means. provider is kept as a column so a second gateway could
-- be added later without a new table.
CREATE TABLE IF NOT EXISTS `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `provider` varchar(50) NOT NULL DEFAULT 'paymongo',
  `provider_reference` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'PHP',
  `status` enum('pending','paid','failed','expired','refunded') NOT NULL DEFAULT 'pending',
  `checkout_url` varchar(1000) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_provider_reference` (`provider`, `provider_reference`),
  KEY `idx_order_id` (`order_id`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────
-- 4. webhook_events — the idempotency guard
-- ─────────────────────────────────────────────────────────────────────────
-- A payment provider retries a webhook until it receives a 2xx, so the SAME
-- "payment succeeded" event can arrive two or three times. Without a guard,
-- each delivery would run the handler again.
--
-- The UNIQUE key on (provider, event_id) is the guard: the handler inserts
-- the event FIRST, and a duplicate delivery fails on the duplicate key. The
-- handler then returns 200 without touching the order again. That is what
-- makes the endpoint idempotent — the same request twice has the same
-- effect as once.
CREATE TABLE IF NOT EXISTS `webhook_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `provider` varchar(50) NOT NULL DEFAULT 'paymongo',
  `event_id` varchar(255) NOT NULL,
  `event_type` varchar(100) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `received_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_provider_event` (`provider`, `event_id`),
  KEY `idx_event_type` (`event_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;

-- Check:
--   SHOW COLUMNS FROM orders LIKE 'payment%';   -- 4 rows
--   SHOW TABLES LIKE 'payments';                -- 1 row
--   SHOW TABLES LIKE 'webhook_events';          -- 1 row
