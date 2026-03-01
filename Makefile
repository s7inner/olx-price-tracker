# Usage: make setup

.PHONY: setup queue schedule

setup:
	@echo "Starting complete project setup..."
	@echo "Stopping and removing existing containers..."
	@./vendor/bin/sail down -v 2>/dev/null || echo "No containers to stop"
	@echo "Copying environment files..."
	@cp .env.example .env 2>/dev/null || echo ".env already exists"
	@cp .env.testing.example .env.testing 2>/dev/null || echo ".env.testing already exists"
	@echo "Installing PHP dependencies..."
	@composer install
	@chmod +x vendor/bin/sail vendor/laravel/sail/bin/sail 2>/dev/null || true
	@echo "Starting Docker containers..."
	@./vendor/bin/sail up -d
	@echo "Waiting for database to be ready..."
	@sleep 15
	@echo "Generating application key..."
	@./vendor/bin/sail artisan key:generate --force
	@echo "Running database migrations..."
	@./vendor/bin/sail artisan migrate --seed --force
	@./vendor/bin/sail artisan migrate --env=testing --force
	@echo "Application URL: http://olx-price-tracker.test/"
	@echo ""
	@echo "To run queue worker and scheduler, open 2 separate terminals:"
	@echo "  make queue"
	@echo "  make schedule"

queue:
	@./vendor/bin/sail artisan queue:work --sleep=3 --tries=3

schedule:
	@./vendor/bin/sail artisan schedule:work
