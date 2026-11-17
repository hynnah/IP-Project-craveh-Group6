# Craveh — Integrative Programming Final Project

**Group 6 · IT 3206N — Integrative Programming**

Craveh is a food ordering web application. This build extends the storefront
with a service layer: a normalised order pipeline, an external payment
gateway, and federated sign-in — the integration work that the original
static version did not have.

---

## 1. What this project demonstrates

The brief asks for a mini system with at least two API integrations. Craveh
has three, each chosen to exercise a different idea from the course:

| Integration | Concept it demonstrates |
|---|---|
| **GCash payment** (provider-abstracted) | Synchronous vs asynchronous flow, webhooks, **idempotency**, signature verification, failure modes |
| **Google OAuth sign-in** | OAuth 2.0, contracts, cross-system **identity resolution** |
| **Maps / delivery location** | Application-level and presentation-level integration |

The system sits at three of the four integration levels from the course
notes: **data** (one database owning each fact), **application/API** (REST
contracts between the browser, the server, and external providers), and
**business process** (an order that spans the kitchen and the payment
provider, with rules about what happens when a step fails).

---

## 2. Architecture

```
  ┌──────────────┐
  │   Browser    │  HTML + CSS + vanilla JS
  └──────┬───────┘
         │ fetch() — JSON over HTTP, same-origin session cookie
  ┌──────▼───────────────────────────────┐
  │        PHP API layer  (api/)         │
  │  auth · menu · cart · orders · pay   │
  └───┬────────────────────┬─────────────┘
      │                    │
      │ mysqli (prepared)  │ HTTPS
  ┌───▼──────────┐   ┌─────▼──────────────────────┐
  │   MySQL      │   │  External services         │
  │ craveh_group6│   │  · Payment gateway         │
  └──────────────┘   │  · Google Identity         │
                     │  · Maps provider           │
                     └─────┬──────────────────────┘
                           │ webhook (server-to-server, signed)
                     ┌─────▼──────────────────────┐
                     │  api/payment_webhook.php   │
                     └────────────────────────────┘
```

The payment provider is reached through an interface (`api/payments/`), not
called directly. Swapping gateways is a one-line config change — the order
pipeline has no idea who processes money.

### Technology stack

| Layer | Technology | Why |
|---|---|---|
| Frontend | HTML, CSS, vanilla JavaScript | No build step; the integration logic stays visible |
| Backend | PHP 8 | Runs on XAMPP, matches the course environment |
| Database | MySQL / MariaDB | Relational integrity for orders and line items |
| Payments | Provider interface — mock gateway or Xendit Test Mode | Demonstrates loose coupling |
| Identity | Google Identity Services (OAuth 2.0 ID tokens) | Federated sign-in without storing passwords |
| Server | XAMPP (Apache + MySQL) | Local development |

---

## 3. Setup

### Requirements
XAMPP with Apache, MySQL and PHP 8+.

### Steps

1. **Clone into the web root** — PHP must be executed by Apache, so the
   project has to live under `htdocs`:
   ```
   cd C:\xampp\htdocs
   git clone https://github.com/hynnah/IP-Project-craveh-Group6.git
   ```

2. **Start Apache and MySQL** from the XAMPP Control Panel.

3. **Create the database** — phpMyAdmin → **Import** →
   `database/craveh_db_structure.sql`. Creates `craveh_group6` with 7 tables
   and 24 menu items.

4. **Run the migration** — phpMyAdmin → select `craveh_group6` → **Import** →
   `database/migrations/001_integrations.sql`. Adds the payment, OAuth and
   location columns plus the `payments` and `webhook_events` tables.

5. **Create your config** — copy `api/config.example.php` to
   `api/config.php`. The `local` defaults already match a fresh XAMPP
   install, so usually nothing needs editing.

6. **Open the site** at
   `http://localhost/IP-Project-craveh-Group6/menu.html`.

> **Use `localhost`, not VS Code Live Server.** Live Server (port 5500)
> serves static files only — it does not execute PHP, so every API call
> silently fails.

### Verify

```sql
SHOW TABLES;                              -- 9 tables after the migration
SELECT COUNT(*) FROM menu_items;          -- 24
SHOW COLUMNS FROM orders LIKE 'payment%'; -- 4 rows
```

### Optional: Google sign-in

1. Google Cloud Console → new project → **Google Auth Platform**
2. **Audience**: *Internal* (organisation accounts only) or *External*
   (add each tester under Test users)
3. **Clients** → Create client → **Web application**
   - Authorized JavaScript origins: `http://localhost` and `http://localhost:80`
   - Authorized redirect URIs: leave empty — not used by the JavaScript API
4. Add the client ID to `api/config.php`:
   ```php
   $google_config = ['client_id' => 'YOUR_ID.apps.googleusercontent.com'];
   ```

Without this the Google button simply does not render.

### Optional: a real payment gateway

`api/config.php` ships with `'provider' => 'mock'`, which needs no account.
To use Xendit Test Mode, set `'provider' => 'xendit'`, add your test secret
key and webhook token, and point the Xendit dashboard's webhook URL at
`/api/payment_webhook.php` (via ngrok for local testing).

---

## 4. API endpoints

### Authentication

| Method | Endpoint | Description | Auth required |
|---|---|---|---|
| GET | `/api/session.php` | Current user and cart | No |
| GET | `/api/auth_config.php` | Public front-end settings (Google client ID) | No |
| POST | `/api/signup.php` | Create an account | No |
| POST | `/api/login.php` | Log in with email and password | No |
| POST | `/api/google_login.php` | Verify a Google ID token, create or link the account | No |
| POST | `/api/logout.php` | End the session, saving the cart | Yes |

### Menu and cart

| Method | Endpoint | Description | Auth required |
|---|---|---|---|
| GET | `/api/menu_items.php` | Available menu items | No |
| POST | `/api/cart.php` | Save the cart to the session | No |

### Orders

| Method | Endpoint | Description | Auth required |
|---|---|---|---|
| POST | `/api/save_order.php` | Place an order (writes `orders` + `order_items` in one transaction) | Yes |
| GET | `/api/history.php` | The signed-in user's orders with line items | Yes |

### Payments

| Method | Endpoint | Description | Auth required |
|---|---|---|---|
| POST | `/api/create_payment.php` | Start or resume a gateway checkout | Yes |
| POST | `/api/payment_webhook.php` | Gateway callback — the only endpoint that marks an order paid | Signature |
| GET / POST | `/api/mock_pay.php` | Simulator only: the provider's side of the conversation | Reference |

Every endpoint returns JSON. Errors use the shape
`{"success": false, "error": "..."}` with a matching HTTP status.

---

## 5. Design decisions worth defending

**`status` and `payment_status` are separate columns.** `status` is where the
*food* is; `payment_status` is where the *money* is. A GCash order is paid
before it is cooked; a cash-on-delivery order is delivered before it is paid.
One column could not express both.

**Only the webhook marks an order paid.** The customer's browser returning
from the gateway proves only that the browser returned — anyone can type the
return URL. The signed server-to-server callback is the source of truth.

**The webhook is idempotent.** A provider retries until it receives a 2xx, so
the same event can arrive several times. `webhook_events` has a UNIQUE key on
`(provider, event_id)`; the handler inserts the event and applies it inside
one transaction, so a duplicate is rejected by the key, and a failure rolls
back *both* — leaving the next retry free to succeed.

**Signatures are compared with `hash_equals`.** A normal `===` returns as
soon as it finds a differing byte, and how long it took leaks how much of the
signature was correct.

**Google sign-in matches on `sub` before email.** A Google account's email
address can change; `sub` is the only identifier Google promises is stable.
Matching on email alone would also mean an existing local account gets a
second row instead of being linked — the duplicate-identity failure.

**Prices are read from the database, never from the browser.** The cart sends
item ids and quantities; the server looks up the current name and price.
Totals are summed in centavos so repeated float addition cannot drift.

---

## 6. Project structure

```
IP-Project-craveh-Group6/
├── menu.html, checkout.html, history.html, account.html
├── mock-gcash.html              # simulated gateway checkout page
├── style.css
├── assets/js/
│   ├── common.js                # API helper, cart, session, nav
│   ├── menu.js, checkout.js, history.js, account.js
│   └── mock-gcash.js
├── api/
│   ├── bootstrap.php            # shared setup for every endpoint
│   ├── config.example.php       # copy to config.php (gitignored)
│   ├── session.php, signup.php, login.php, logout.php
│   ├── google_login.php, auth_config.php
│   ├── menu_items.php, cart.php
│   ├── save_order.php, history.php
│   ├── create_payment.php, payment_webhook.php, mock_pay.php
│   └── payments/
│       ├── gateway.php          # the PaymentGateway contract + factory
│       ├── mock_gateway.php
│       └── xendit_gateway.php
└── database/
    ├── craveh_db_structure.sql  # base schema + menu data
    ├── migrations/001_integrations.sql
    └── README.md
```

---

## 7. Known gaps

- No admin panel in this repository — order status is changed directly in the
  database for now.
- Three menu items reference `uploads/menu/…`, which is not committed, so
  their images are broken.
- The `analytics` table exists but nothing writes to it yet.
- Maps / delivery location is not yet implemented; the `orders` table already
  has `delivery_lat` and `delivery_lng` columns ready for it.
