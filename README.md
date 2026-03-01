<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## OLX Price Tracker 
**PHP 8.4 required**

### Task

1. Provide an HTTP endpoint for price change subscription. Input: listing URL, email for notifications.
2. After successful subscription, track listing price and send email notifications on changes.
3. If multiple users subscribe to the same listing, avoid redundant price checks.
4. Full service running in Docker container.
5. Tests with 70%+ coverage.
6. Email confirmation for users.

### Set-up Makefile

```bash
127.0.0.1 olx-price-tracker.test (add to hosts file)

make setup
```

Then run in 2 separate terminals:

```bash
make queue
make schedule
```

Set your SMTP credentials in `.env`:
1. `MAIL_USERNAME=your_email@gmail.com`
2. `MAIL_PASSWORD="xxxx xxxx xxxx xxxx"`

### Set-up Sail (without Makefile)
```bash
127.0.0.1 olx-price-tracker.test (add to hosts file)

cp .env.example .env
cp .env.testing.example .env.testing

composer install
chmod +x vendor/bin/sail vendor/laravel/sail/bin/sail
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan migrate --env=testing
./vendor/bin/sail exec -u root laravel.test php artisan vendor:publish --provider="Dedoc\Scramble\ScrambleServiceProvider" --tag="scramble-config" --force
```

Then run in 2 separate terminals:

```bash
./vendor/bin/sail artisan queue:work
./vendor/bin/sail artisan schedule:work
```

Set your SMTP credentials in `.env`:
1. `MAIL_USERNAME=your_email@gmail.com`
2. `MAIL_PASSWORD="xxxx xxxx xxxx xxxx"`

### API Documentation (Swagger)
- http://olx-price-tracker.test/docs/api

### DB connection settings

| Setting   | Value              |
| --------- | ------------------ |
| Host      | localhost          |
| Port      | 3307               |
| Database  | olx_price_tracker  |
| Username  | sail               |
| Password  | password           |

### Test Coverage Overview
<img width="642" height="335" alt="image" src="https://github.com/user-attachments/assets/68b62060-d405-48a0-bffd-08a0ab6950fd" />

## How does it work?

**Core idea:** Parse the listing URL once to get the ad ID. From then on, only call the OLX payment API to check for price changes.

### Part 1 – Subscription

<img width="2126" height="4489" alt="image" src="https://github.com/user-attachments/assets/102548af-4835-4550-a68f-f3bdf7c277d8" />


The client sends `POST /api/price-subscriptions` with the listing URL and a Bearer token. Middleware checks auth and email verification. The URL is normalized and validated. If the listing is already in the DB, we don't fetch from OLX: we add the user as a subscriber and return data from the DB. Otherwise we: fetch the page (expect 200) -> parse HTML for the ad ID -> call the OLX payment API -> create or update the TrackedAd and subscription -> return the response.

**ID parsing:**
- `ad-id=` in links/URL params
- `application/ld+json` scripts with Product schema and `sku`
-  visible "ID: …" label via regex

**OLX payment API:**
{OLX_PAYMENT_BASE_URL}/payment/ad/{adId}/buyer/

This endpoint is not auth-protected, so I used it.

**Email:** A confirmation email is sent only for new subscriptions (when this user has not been subscribed to this listing before). For existing subscriptions, no email is sent.

### Part 2 – Price check command

<img width="3154" height="6076" alt="image" src="https://github.com/user-attachments/assets/8acff488-b97a-4da9-876c-3d43ecec2725" />

Cron runs `ads:check-olx-prices` every 5 minutes. Only ads with subscribers are processed. For each ad, the listing page HTTP status is checked first.

**Listing page status -> internal status:**

| HTTP | Status     |
|------|------------|
| 200  | ACTIVE     |
| 404  | NON_PUBLIC |
| 410  | INACTIVE   |
| other / error | UNAVAILABLE |

**Payment API:** called only when status is **200** (ACTIVE). For 404, 410, or UNAVAILABLE, the payment API is not used.

**Status change (ACTIVE -> other):**
- If it was ACTIVE before and the status changed -> email: `non-public`, `inactive`, or `unavailable`.
- If it was not ACTIVE before (e.g. inactive and still inactive) -> no email.

**When status is ACTIVE:**
1. Call payment API.
2. If fetch fails -> log and skip (no email).
3. If price did not change:
    - **Reactivated** (was inactive / non-public / unavailable, became ACTIVE) -> email: `listing reactivated`.
    - Not reactivated -> no email.
4. If price changed:
    - **Reactivated** -> email: `reactivated + price change`.
    - Not reactivated -> email: `price changed`.

**Email summary:**
- **Send:** non-public, inactive, unavailable, listing reactivated, reactivated + price change, price changed.
- **Do not send:** status unchanged; was not ACTIVE and stayed not ACTIVE; payment API fetch failed; ACTIVE with unchanged price and not reactivated.
