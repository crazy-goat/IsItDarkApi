# IsItDarkApi — AGENTS.md

## Serwer lokalny

Lokalny serwer Webman działa na `http://localhost:8787/`.

### Start / stop / restart

```bash
php start.php start    # uruchom serwer (w tle: php start.php start > /dev/null 2>&1 &)
php start.php stop     # zatrzymaj serwer
php start.php restart  # restart serwera
```

### WAŻNE: cache HTML

`IndexController` cachuje HTML w statycznej zmiennej `self::$cachedHtml` — po każdej zmianie pliku `resources/views/index.html` **wymagany jest restart serwera**.

Jeśli `php start.php stop` nie działa (stare procesy z innej sesji), zabij wszystkie workery ręcznie:

```bash
ps aux | grep -E "WorkerMan.*8787" | grep -v grep | awk '{print $2}' | xargs kill -9
```

Następnie uruchom ponownie:

```bash
php start.php start > /dev/null 2>&1 &
sleep 4 && curl -s -o /dev/null -w "%{http_code}" http://localhost:8787/
```

## TelemetryMiddleware

Wymaga dependency injection — lokalnie należy wyłączyć w `config/middleware.php`.  
**NIE commitować tej zmiany.**

## Testy

```bash
php bin/phpunit
```

62 testy PHP — wszystkie powinny przechodzić przed commitem.

## Branch / PR

- Branch: `fix/map-marker-position`
- PR: https://github.com/crazy-goat/IsItDarkApi/pull/23
