# IsItDarkApi

Simple API to check if it's dark at any location in the world.

## Features

- 🌍 Check if it's dark anywhere on Earth
- 🗺️ Interactive map with click-to-select
- 🏙️ City search with autocomplete
- 📍 Use your current location
- 📡 REST API with JSON/XML/YAML support
- 🔬 Detailed astronomical data (twilight phases, polar day/night)
- ⚡ Cached responses with automatic expiration
- 🐳 Docker deployment ready

## API Usage

### Endpoint

```
GET /api/v1/is-dark?lat={latitude}&lng={longitude}&detailed={true|false}
```

### Parameters

| Parameter | Type    | Required | Default | Description                              |
|-----------|---------|----------|---------|------------------------------------------|
| lat       | float   | Yes      | -       | Latitude (-90 to 90)                     |
| lng       | float   | Yes      | -       | Longitude (-180 to 180)                  |
| detailed  | boolean | No       | false   | Include detailed astronomical data       |

### Response Formats

Set format via `Accept` header:
- `application/json` (default)
- `application/xml`
- `application/x-yaml`

### Simple Response

```json
{
  "is_dark": true,
  "sunrise": "2024-01-15T06:42:18Z",
  "sunset": "2024-01-15T19:32:42Z"
}
```

### Detailed Response (`?detailed=true`)

```json
{
  "is_dark": true,
  "is_day": false,
  "state": "night",
  "sunrise": "2024-01-15T06:42:18Z",
  "sunset": "2024-01-15T19:32:42Z",
  "solar_noon": "2024-01-15T13:07:18Z",
  "civil_dawn": "2024-01-15T06:08:42Z",
  "civil_dusk": "2024-01-15T20:15:54Z",
  "nautical_dawn": "2024-01-15T05:28:15Z",
  "nautical_dusk": "2024-01-15T20:56:21Z",
  "astronomical_dawn": "2024-01-15T04:48:33Z",
  "astronomical_dusk": "2024-01-15T21:36:03Z",
  "day_length": 44964,
  "night_length": 41436,
  "has_sunrise": true,
  "has_sunset": true,
  "is_polar_day": false,
  "is_polar_night": false,
  "next_change": "sunrise",
  "next_change_at": 1705310502
}
```

### Response Fields

| Field               | Type    | Description                                      |
|---------------------|---------|--------------------------------------------------|
| is_dark             | boolean | Whether it's currently dark (sun below horizon)  |
| is_day              | boolean | Whether it's currently day (sun above horizon)   |
| state               | string  | Current state: day, civil_twilight, nautical_twilight, astronomical_twilight, night |
| sunrise             | string  | Time of sunrise (ISO 8601) or null               |
| sunset              | string  | Time of sunset (ISO 8601) or null                |
| solar_noon          | string  | Time of solar noon (ISO 8601) or null            |
| civil_dawn/dusk     | string  | Start/end of civil twilight (ISO 8601)           |
| nautical_dawn/dusk  | string  | Start/end of nautical twilight (ISO 8601)        |
| astronomical_dawn/dusk | string | Start/end of astronomical twilight (ISO 8601)  |
| day_length          | integer | Length of day in seconds                         |
| night_length        | integer | Length of night in seconds                       |
| has_sunrise         | boolean | Whether sunrise occurs today (false in polar night) |
| has_sunset          | boolean | Whether sunset occurs today (false in polar day) |
| is_polar_day        | boolean | Whether it's polar day (sun never sets)          |
| is_polar_night      | boolean | Whether it's polar night (sun never rises)       |
| next_change         | string  | What happens next: "sunrise" or "sunset"         |
| next_change_at      | integer | Unix timestamp of next change (for cache)        |

### Response Headers

- `Expires` - Timestamp until which the response is valid
- `Cache-Control` - Cache duration

## Installation

### Local Development

```bash
# Clone repository
git clone https://github.com/crazy-goat/IsItDarkApi.git
cd IsItDarkApi

# Install dependencies
composer install

# Generate map
php tools/map-generator.php

# Start server
php start.php start
```

### Docker

```bash
# Build image
docker build -t isitdarkapi .

# Run container
docker run -p 8787:8787 isitdarkapi
```

### Dokploy Deployment

1. Install Dokploy on your VPS:
   ```bash
   curl -sSL https://dokploy.com/install.sh | sh
   ```

2. Add your GitHub repository in Dokploy UI

3. Configure environment variables (if needed)

4. Deploy!

## Development

### Generate Cities Database

```bash
php tools/cities-generator.php
```

Note: Requires GeoNames API username (set in the script).

### Generate Map

```bash
php tools/map-generator.php
```

### Run Tests

```bash
# All tests
composer test

# Unit tests only
composer test-unit

# Feature tests only
composer test-feature

# With coverage
composer test-coverage
```

## Tech Stack

- **Backend**: PHP 8.2 + Webman
- **Astronomy**: crazy-goat/is-it-dark library
- **Frontend**: Vanilla HTML/JS/CSS
- **Map**: Custom SVG
- **Container**: Docker with static PHP binary

## License

MIT
