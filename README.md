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

### Quick Start

Check if it's dark in Warsaw (52.23°N, 21.01°E):

```bash
# Simple request (JSON by default)
curl "http://localhost:8787/api/v1/is-dark?lat=52.23&lng=21.01"

# Detailed astronomical data
curl "http://localhost:8787/api/v1/is-dark?lat=52.23&lng=21.01&detailed=true"

# XML format
curl -H "Accept: application/xml" "http://localhost:8787/api/v1/is-dark?lat=52.23&lng=21.01"

# YAML format
curl -H "Accept: application/x-yaml" "http://localhost:8787/api/v1/is-dark?lat=52.23&lng=21.01"
```

### Code Examples

#### JavaScript (fetch)

```javascript
const response = await fetch(
  'http://localhost:8787/api/v1/is-dark?lat=52.23&lng=21.01'
);
const data = await response.json();
console.log(data.is_dark); // boolean
```

#### JavaScript with detailed data

```javascript
const response = await fetch(
  'http://localhost:8787/api/v1/is-dark?lat=52.23&lng=21.01&detailed=true'
);
const data = await response.json();
console.log(data.state); // "day", "night", "civil_twilight", etc.
```

#### Python

```python
import requests

response = requests.get(
    'http://localhost:8787/api/v1/is-dark',
    params={'lat': 52.23, 'lng': 21.01}
)
data = response.json()
print(data['is_dark'])  # True or False
```

#### Python with XML response

```python
import requests

response = requests.get(
    'http://localhost:8787/api/v1/is-dark',
    params={'lat': 52.23, 'lng': 21.01},
    headers={'Accept': 'application/xml'}
)
print(response.text)  # XML response
```

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

- `Expires` - Timestamp until which the response is valid (equals `next_change_at` from response body)
- `Cache-Control` - Cache duration in seconds

Responses are automatically cached until the next solar event (sunrise or sunset). You can use these headers to avoid unnecessary requests:

```bash
# Check cache headers
curl -I "http://localhost:8787/api/v1/is-dark?lat=52.23&lng=21.01"

# Example response headers:
# Expires: Fri, 20 Mar 2026 18:42:00 GMT
# Cache-Control: public, max-age=3542
```

**JavaScript example using cache:**

```javascript
const response = await fetch(
  'http://localhost:8787/api/v1/is-dark?lat=52.23&lng=21.01&detailed=true'
);
const cacheControl = response.headers.get('Cache-Control'); // "public, max-age=3542"
const data = await response.json();
// data.next_change_at contains Unix timestamp of next solar event
```

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

### Docker (Minimal - FROM scratch)

For a minimal image (~30-50MB vs ~150MB):

```bash
# Pull pre-built image
docker pull ghcr.io/crazy-goat/isitdarkapi:latest-scratch

# Run container
docker run -p 8787:8787 ghcr.io/crazy-goat/isitdarkapi:latest-scratch
```

**Benefits of scratch image:**
- ~70% smaller (30-50MB vs 150MB+)
- No shell, no package manager - minimal attack surface
- Faster deployments
- Only PHP binary + application code

**Building scratch image locally:**

```bash
# Build
docker build -f Dockerfile.scratch -t isitdarkapi:scratch .

# Run
docker run -p 8787:8787 isitdarkapi:scratch

# Check size
docker images isitdarkapi:scratch --format "{{.Size}}"
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
- **Container**: Docker with static PHP binary (scratch image ~30-50MB)

## License

MIT
