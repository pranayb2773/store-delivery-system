# Store Delivery System

A Laravel 12 REST API for managing store locations, checking delivery coverage, and discovering nearby stores using geolocation.

## Features

- **Authentication** - Token-based API auth via Laravel Sanctum with rate-limited login
- **Store Management** - Admin-only store creation with postcode-based geolocation
- **Delivery Coverage** - Check if any store can deliver to a given UK postcode, with estimated delivery time
- **Nearby Stores** - Find stores within a configurable radius using the haversine formula, with pagination and open-now filtering
- **Postcode Import** - Bulk import UK postcodes from CSV via queued batch jobs or sync mode
- **Caching** - Redis-backed query caching with automatic invalidation on model changes
- **Monitoring** - Laravel Telescope for request/query/job debugging

## Tech Stack

- **PHP** 8.4 / **Laravel** 12
- **MySQL** 8.4 - Primary database
- **Redis** - Cache, queue, and session store
- **Laravel Sanctum** - API token authentication
- **Laravel Horizon** - Queue monitoring dashboard
- **Laravel Telescope** - Application debugging
- **Pest** 4 - Testing framework
- **Docker** via Laravel Sail

## Getting Started

### Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/)

### Installation

```bash
# Clone the repository
git clone <repository-url>
cd store-delivery-system

# Copy environment file
cp .env.example .env

# Install PHP dependencies (no local PHP/Composer required)
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php84-composer:latest \
    composer install --ignore-platform-reqs

# Start Docker containers
./vendor/bin/sail up -d

# Generate application key
./vendor/bin/sail artisan key:generate

# Run database migrations
./vendor/bin/sail artisan migrate

# Import postcodes from CSV (required before seeding)
./vendor/bin/sail artisan import:postcodes --path=postcodes.csv --sync

# Seed the database (optional — requires postcodes)
./vendor/bin/sail artisan db:seed
```

### Running Without Docker (Laravel Herd)

If using [Laravel Herd](https://herd.laravel.com), update your `.env` to use SQLite or a local MySQL instance and set `CACHE_STORE`, `QUEUE_CONNECTION`, and `SESSION_DRIVER` as needed.

## Database Schema

```
┌───────────────────────────────┐       ┌───────────────────────────────────┐
│            users              │       │     personal_access_tokens        │
├───────────────────────────────┤       ├───────────────────────────────────┤
│ id             BIGINT PK      │◄──────│ tokenable_id   BIGINT FK         │
│ name           VARCHAR        │       │ tokenable_type VARCHAR            │
│ email          VARCHAR UQ     │       │ id             BIGINT PK          │
│ email_verified_at TIMESTAMP   │       │ name           TEXT                │
│ role           TINYINT [1,2]  │       │ token          VARCHAR(64) UQ     │
│ password       VARCHAR        │       │ abilities      TEXT                │
│ remember_token VARCHAR        │       │ last_used_at   TIMESTAMP           │
│ created_at     TIMESTAMP      │       │ expires_at     TIMESTAMP           │
│ updated_at     TIMESTAMP      │       │ created_at     TIMESTAMP           │
└───────────────────────────────┘       │ updated_at     TIMESTAMP           │
                                        └───────────────────────────────────┘
┌───────────────────────────────┐
│           stores              │       ┌───────────────────────────────────┐
├───────────────────────────────┤       │          postcodes                │
│ id             BIGINT PK      │       ├───────────────────────────────────┤
│ name           VARCHAR        │       │ id             BIGINT PK          │
│ address_line1  VARCHAR        │       │ postcode       VARCHAR UQ         │
│ city           VARCHAR        │       │ latitude       DECIMAL(11,7)      │
│ postcode       VARCHAR   IDX  │       │ longitude      DECIMAL(11,7)      │
│ latitude       DECIMAL(11,7)  │       │ created_at     TIMESTAMP           │
│ longitude      DECIMAL(11,7)  │       │ updated_at     TIMESTAMP           │
│ delivery_radius_km DEC(6,2)   │       └───────────────────────────────────┘
│ is_active      BOOLEAN   IDX  │
│ opening_hours  JSON           │       ┌───────────────────────────────────┐
│ created_at     TIMESTAMP      │       │          sessions                 │
│ updated_at     TIMESTAMP      │       ├───────────────────────────────────┤
├───────────────────────────────┤       │ id             VARCHAR PK         │
│ UQ (name, postcode)           │       │ user_id        BIGINT FK ──►users │
│ IDX (latitude, longitude)     │       │ ip_address     VARCHAR(45)        │
└───────────────────────────────┘       │ user_agent     TEXT                │
                                        │ payload        LONGTEXT            │
                                        │ last_activity  INTEGER IDX         │
                                        └───────────────────────────────────┘
```

**Relationships:**
- `personal_access_tokens.tokenable` → polymorphic to `users` (Sanctum auth tokens)
- `sessions.user_id` → `users.id`
- `stores.postcode` references postcodes logically (no FK constraint — coordinates are copied at creation)

## API Endpoints

All API routes return JSON responses. Authenticated routes require a `Bearer` token in the `Authorization` header.

### Authentication

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/api/login` | No | Login and receive an API token |
| POST | `/api/logout` | Yes | Revoke the current token |

### Stores

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/api/stores` | Yes (Admin) | Create a new store |
| GET | `/api/stores/can-deliver` | Yes | Check delivery coverage for a postcode |
| GET | `/api/stores/nearby` | Yes | Find nearby stores by postcode |

### Example Requests

**Login:**
```bash
curl -X POST /api/login \
  -H "Content-Type: application/json" \
  -d '{"email": "user@example.com", "password": "password"}'
```

**Create Store (Admin):**
```bash
curl -X POST /api/stores \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer {token}" \
  -d '{
    "name": "London Bridge Store",
    "address_line1": "1 London Bridge St",
    "city": "London",
    "postcode": "SE1 9GF",
    "delivery_radius_km": 8,
    "is_active": true,
    "opening_hours": {
      "monday": {"open": "09:00", "close": "21:00"},
      "tuesday": {"open": "09:00", "close": "21:00"},
      "wednesday": {"open": "09:00", "close": "21:00"},
      "thursday": {"open": "09:00", "close": "21:00"},
      "friday": {"open": "09:00", "close": "22:00"},
      "saturday": {"open": "10:00", "close": "22:00"},
      "sunday": {"open": "10:00", "close": "18:00"}
    }
  }'
```

**Check Delivery Coverage:**
```bash
curl "/api/stores/can-deliver?postcode=SW1A+1AA" \
  -H "Authorization: Bearer {token}"
```

**Find Nearby Stores:**
```bash
curl "/api/stores/nearby?postcode=SW1A+1AA&radius=5&open_now=true" \
  -H "Authorization: Bearer {token}"
```

## Importing Postcodes

Import UK postcodes from a CSV file containing postcode, latitude, and longitude columns:

```bash
# Async (via queue) - recommended for large files
./vendor/bin/sail artisan import:postcodes --path=postcodes.csv

# Synchronous
./vendor/bin/sail artisan import:postcodes --path=postcodes.csv --sync

# CSV without header row
./vendor/bin/sail artisan import:postcodes --path=postcodes.csv --no-header
```

Start a queue worker to process batched imports:

```bash
./vendor/bin/sail artisan queue:work
```

## Configuration

Delivery estimation settings in `.env`:

| Variable | Default | Description |
|----------|---------|-------------|
| `DELIVERY_BASE_PREPARATION_MINUTES` | 15 | Base preparation time added to every delivery estimate |
| `DELIVERY_AVERAGE_SPEED_KMH` | 30 | Average delivery speed used for time calculation |

## Testing

```bash
# Run all tests
./vendor/bin/sail artisan test

# Run with compact output
./vendor/bin/sail artisan test --compact

# Filter specific tests
./vendor/bin/sail artisan test --filter=CanDeliverTest
```

## Code Style

This project uses [Laravel Pint](https://laravel.com/docs/pint) for code formatting:

```bash
vendor/bin/pint
```

## Docker Services

| Service | Image | Port |
|---------|-------|------|
| App | PHP 8.4 (Sail) | 8080 |
| MySQL | mysql:8.4 | 3306 |
| Redis | redis:alpine | 6379 |

```bash
# Start containers
./vendor/bin/sail up -d

# Stop containers
./vendor/bin/sail down
```

## Project Structure

```
app/
├── Actions/            # Single-responsibility business logic
├── Console/Commands/   # Artisan commands (import:postcodes)
├── Enums/              # UserRole enum
├── Http/
│   ├── Controllers/    # Auth & Store controllers
│   ├── Middleware/      # ForceJsonResponse for API routes
│   └── Requests/       # Form request validation
├── Jobs/               # ProcessPostcodeBatch queue job
├── Models/             # User, Store, Postcode
├── Policies/           # StorePolicy (admin authorization)
├── Resources/          # API resource transformers
└── Services/           # GeoLocationService (haversine queries)
config/
└── delivery.php        # Delivery estimation config
```
