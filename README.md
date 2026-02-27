<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## OLX Price Tracker

**PHP 8.4 required**

### Set-up Makefile

```
127.0.0.1 olx-price-tracker.test

make setup
```

### Set-up Sail (without Makefile)

```bash
127.0.0.1 olx-price-tracker.test

cp .env.example .env
cp .env.testing.example .env.testing

composer install
chmod +x vendor/bin/sail vendor/laravel/sail/bin/sail
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan migrate --env=testing
```

### DB connection settings

| Setting   | Value              |
| --------- | ------------------ |
| Host      | localhost          |
| Port      | 3307               |
| Database  | olx_price_tracker  |
| Username  | sail               |
| Password  | password           |
