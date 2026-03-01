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

Add to hosts file:

```
127.0.0.1 olx-price-tracker.test
```

Run:

```bash
make setup
```

Then run in 2 separate terminals:

```bash
make queue
make schedule
```

### Set-up Sail (without Makefile)

Add to hosts file:

```
127.0.0.1 olx-price-tracker.test
```

Run:

```bash
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


The client sends `POST /api/price-subscriptions` with the listing URL and a Bearer token. Middleware checks auth and email verification. The URL is normalized and validated. If the listing is already in the DB, we don't fetch from OLX: we add the user as a subscriber and return data from the DB. Otherwise we: fetch the page (expect 200) → parse HTML for the ad ID → call the OLX payment API → create or update the TrackedAd and subscription → return the response.

**ID parsing:**
- `ad-id=` in links/URL params
- `application/ld+json` scripts with Product schema and `sku`
-  visible "ID: …" label via regex

**OLX payment API:**
https://ua.production.delivery.olx.tools/payment/ad/{adId}/buyer/

This endpoint is not auth-protected, so I used it.

**Email:** A confirmation email is sent only for new subscriptions (when this user has not been subscribed to this listing before). For existing subscriptions, no email is sent.

### Part 2 – Price check command
