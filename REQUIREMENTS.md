# IsItDarkApi - Wymagania

## Stack technologiczny
- **Backend**: PHP + Webman (https://github.com/walkor/webman)
- **Astronomia**: crazy-goat/is-it-dark (https://github.com/crazy-goat/IsItDark)
- **Frontend**: One Page HTML, Pure JS, bez CSS framework
- **Mapa**: Custom SVG (własna implementacja)

---

## Funkcjonalność API

### Endpoint
```
GET /api/v1/is-dark
```

### Parametry wejściowe (query string)
| Parametr | Typ     | Wymagany | Domyślnie | Opis                               |
|----------|---------|----------|-----------|------------------------------------|
| lat      | float   | Tak      | -         | Szerokość geograficzna (-90..90)   |
| lng      | float   | Tak      | -         | Długość geograficzna (-180..180)   |
| detailed | boolean | Nie      | false     | Szczegółowe dane astronomiczne     |

### Format odpowiedzi
Wybierany przez header HTTP: `Accept: application/json` (lub `application/xml`, `application/x-yaml`)

### Response body - Simple (przykład JSON)
```json
{
  "is_dark": true,
  "sunrise": "2024-01-15T06:42:18Z",
  "sunset": "2024-01-15T19:32:42Z"
}
```

### Response body - Detailed (przykład JSON, `?detailed=true`)
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

### Response body (przykład XML)
```xml
<?xml version="1.0" encoding="UTF-8"?>
<response>
  <is_dark>true</is_dark>
  <sunrise>2024-01-15T06:42:18Z</sunrise>
  <sunset>2024-01-15T19:32:42Z</sunset>
</response>
```

### Response body (przykład YAML)
```yaml
is_dark: true
sunrise: '2024-01-15T06:42:18Z'
sunset: '2024-01-15T19:32:42Z'
```

### Headers odpowiedzi
- `Expires` - timestamp UTC do którego wynik jest aktualny (następny wschód/zachód słońca)
- `Cache-Control` - odpowiedni dla czasu ważności

### Biblioteka astronomiczna
- **crazy-goat/is-it-dark** - kompleksowa biblioteka astronomiczna
  - Wschód/zachód słońca
  - Fazy zmierzchu (cywilny, żeglarski, astronomiczny)
  - Długość dnia/nocy
  - Warunki polarne (dzień polarny, noc polarna)
  - Algorytm Meeusa (dokładność ±1 minuta)

---

## Frontend (One Page)

### Funkcjonalności wprowadzania lokalizacji
1. **Ręczne współrzędne** - inputy na lat/lng
2. **Geolokacja przeglądarki** - przycisk "użyj mojej lokalizacji" (HTML5 Geolocation API)
3. **Wyszukiwanie miasta** - input z nazwą miasta → geokodowanie do współrzędnych
4. **Mapa** - Leaflet.js + OpenStreetMap, klikalna do wyboru lokalizacji

### Wyświetlanie wyniku
- Tekst: "YES" (jest ciemno) lub "NO" (jest jasno)
- Dodatkowo: godziny wschodu i zachodu słońca

### UX
- Wynik aktualizuje się przy każdej zmianie lokalizacji
- Brak wyboru daty/czasu - zawsze sprawdzany jest "teraz"

---

## Formaty serializacji (hardcoded, bez bibliotek)
- JSON - ręcznie składany
- XML - ręcznie składany
- YAML - ręcznie składany

---

## Hosting
- VPS (np. Hetzner, DigitalOcean)
- Cloudflare jako proxy + cache (DNS + CDN)
- **Dokploy** - PaaS do zarządzania deploymentem (GUI + auto-deploy)

---

## Deployment (Dokploy)

### Konfiguracja
- Dokploy zainstalowany na VPS (`curl -sSL https://dokploy.com/install.sh | sh`)
- Aplikacja dodana w GUI Dokploy
- Połączenie z GitHub repo (auto-deploy na push do main)
- Traefik jako reverse proxy (SSL automatycznie)

### Środowisko
- Docker container
- Port: 8787 (Webman)
- Traefik routing: `isitdarkapi.example.com` → kontener

### Auto-aktualizacje
- Push do `main` → GitHub Actions buduje obraz → Dokploy pobiera i deployuje
- Lub: Dokploy buduje bezpośrednio z Dockerfile w repo

---

## Deployment (Docker)

### Build PHP (static-php-cli)
- Plik konfiguracji: `craft.yml` (w root)
- GitHub Actions buduje statyczny `php` przy użyciu https://github.com/crazywhalecc/static-php-cli
- Binarka publikowana jako release artifact

### Docker Image
- **Multi-stage build**:
  - Stage 1: Pobiera pre-built `php` z GitHub Releases
  - Stage 2: `scratch` + skopiowany `php` + kod aplikacji
- **Registry**: GitHub Container Registry (`ghcr.io/crazy-goat/isitdarkapi`)
- **Tagi**: `latest`, `v1.0.0`, etc.

### Uruchomienie
```bash
docker run -p 8787:8787 ghcr.io/crazy-goat/isitdarkapi:latest
```

---

## Struktura projektu

### Kontrolery
- `app/controller/Api/V1/IsDarkController.php` - endpoint API
- `app/controller/IndexController.php` - frontend (zwraca HTML)

### Middleware
- `app/middleware/ApiErrorMiddleware.php` - obsługa błędów dla API (formatowanie do JSON/XML/YAML)
- `app/middleware/CorsMiddleware.php` - CORS dla publicznego API (`*`)

### Serwisy
- `app/service/HtmlCacheService.php` - wczytuje HTML przy starcie, cache na stałe
- `app/service/SunCalcService.php` - obliczenia astronomiczne (używa crazy-goat/is-it-dark)
- `app/service/ResponseFormatterService.php` - formatowanie odpowiedzi (JSON/XML/YAML hardcoded)

### Testy
- `tests/Unit/` - testy jednostkowe serwisów
- `tests/Feature/` - testy HTTP API

### Widoki
- `resources/views/index.html` - plik HTML frontendu

---

## Szczegóły techniczne

### HTTP Status Codes
- `200 OK` - sukces
- `400 Bad Request` - brakujące parametry lat/lng
- `422 Unprocessable Entity` - nieprawidłowe wartości lat/lng (poza zakresami)

### Walidacja parametrów
- `lat`: -90 do 90
- `lng`: -180 do 180
- Precyzja: 2 miejsca po przecinku (zaokrąglane, ~1 km precyzji)

### Cache HTML
- Wczytywany przy starcie aplikacji przez `HtmlCacheService`
- Cache na stałe (do restartu)
- Wstrzykiwany przez dependency injection

### CORS
- Włączone dla wszystkich domen (`*`)
- Tylko dla ścieżki `/api/*`

### Metody HTTP
- Endpoint API: tylko `GET`
- Frontend: `GET` (zwraca HTML)

---

## Dane miast (geokodowanie)

### Źródło danych
- GeoNames.org (pobierane przez API)

### Generator
- Skrypt: `tools/cities-generator.php`
- Pobiera top 100 miast dla każdego kraju (wg populacji)
- Generuje plik: `app/Config/cities.php`
- Plik zawiera stałą PHP z tablicą miast (nazwa, lat, lng, kraj)

### Użycie
- Miasta wczytywane raz przy starcie aplikacji
- Wyszukiwanie po nazwie (case-insensitive, partial match)
- Autocomplete w frontendzie

---

## Mapa (SVG)

### Źródło danych
- **Natural Earth** (https://www.naturalearthdata.com/) - darmowe dane geograficzne

### Generator
- Skrypt: `tools/map-generator.php`
- Pobiera dane Natural Earth (shapefile)
- Konwertuje do SVG (uproszczona geometria)
- Generuje plik: `public/map/world.svg`

### Funkcjonalność
- Prosta mapa świata w SVG
- Zoom: przyciski +/- (skalowanie SVG)
- Kliknięcie = pobranie współrzędnych lat/lng
- Hostowana lokalnie (brak zewnętrznych zapytań)
