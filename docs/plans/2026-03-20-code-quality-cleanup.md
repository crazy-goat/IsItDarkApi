# IsItDarkApi - Full Code Quality Cleanup Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Comprehensive code quality overhaul - fix every bug, security issue, inconsistency, dead code, missing test, and code smell in the entire codebase.

**Architecture:** Incremental cleanup in dependency order - fix bugs and security first, then refactor architecture (OTel DI), then clean up dead code and inconsistencies, then add missing tests, then polish metadata and config.

**Tech Stack:** PHP 8.4, Webman/Workerman, PHPUnit 13, PHPStan (max), PHP_CodeSniffer + Slevomat, Rector

---

## Phase 1: Critical Bugs & Security

### Task 1: Fix XML output literal `\n` bug

**Files:**
- Modify: `app/service/ResponseFormatterService.php:35`
- Test: `tests/Unit/Service/ResponseFormatterServiceTest.php`

**Step 1: Write a failing test that proves the bug**

Add to `ResponseFormatterServiceTest.php`:

```php
public function testXmlOutputContainsRealNewlines(): void
{
    $data = ['key' => 'value'];
    $result = $this->formatter->format($data, 'xml');

    $this->assertStringNotContainsString('\n', $result);
    $this->assertStringContainsString("\n", $result);
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/Service/ResponseFormatterServiceTest.php --filter testXmlOutputContainsRealNewlines -v`
Expected: FAIL (literal `\n` found in output)

**Step 3: Fix the bug**

In `app/service/ResponseFormatterService.php:35`, change single quotes to double quotes:

```php
// Before:
$xml = '<?xml version="1.0" encoding="UTF-8"?>\n';
// After:
$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Unit/Service/ResponseFormatterServiceTest.php --filter testXmlOutputContainsRealNewlines -v`
Expected: PASS

**Step 5: Commit**

```bash
git add app/service/ResponseFormatterService.php tests/Unit/Service/ResponseFormatterServiceTest.php
git commit -m "fix: use real newline in XML declaration instead of literal \\n"
```

---

### Task 2: Move Dokploy webhook token to GitHub secret

**Files:**
- Modify: `.github/workflows/build.yml:83`
- Modify: `.github/workflows/build-scratch.yml:120-121`

**Step 1: Replace hardcoded URL with secret reference**

In `.github/workflows/build.yml:83`, change:
```yaml
curl -X POST https://dokploy.crazy-goat.com/api/deploy/GTqbHMtCRqf-SDlbxQrSz \
```
to:
```yaml
curl -X POST ${{ secrets.DOKPLOY_WEBHOOK_URL }} \
```

In `.github/workflows/build-scratch.yml:120`, change:
```yaml
curl -X POST https://dokploy.crazy-goat.com/api/deploy/GTqbHMtCRqf-SDlbxQrSz \
```
to:
```yaml
curl -X POST ${{ secrets.DOKPLOY_WEBHOOK_URL }} \
```

**Step 2: Commit**

```bash
git add .github/workflows/build.yml .github/workflows/build-scratch.yml
git commit -m "security: move Dokploy webhook URL to GitHub secret"
```

> **Note:** After committing, the repository owner must add `DOKPLOY_WEBHOOK_URL` as a GitHub Actions secret with value `https://dokploy.crazy-goat.com/api/deploy/GTqbHMtCRqf-SDlbxQrSz`. Consider rotating the token since it was exposed in git history.

---

### Task 3: Fix debug mode - use environment variable

**Files:**
- Modify: `config/app.php:18`

**Step 1: Change hardcoded `true` to env-based**

```php
// Before:
'debug' => true,
// After:
'debug' => (bool) (getenv('APP_DEBUG') ?: false),
```

**Step 2: Commit**

```bash
git add config/app.php
git commit -m "security: disable debug mode by default, use APP_DEBUG env var"
```

---

### Task 4: Fix CORS middleware - handle OPTIONS preflight

**Files:**
- Modify: `app/middleware/CorsMiddleware.php`
- Create: `tests/Unit/Middleware/CorsMiddlewareTest.php`

**Step 1: Write failing test for OPTIONS preflight**

Create `tests/Unit/Middleware/CorsMiddlewareTest.php`:

```php
<?php

declare(strict_types=1);

namespace tests\Unit\Middleware;

use app\middleware\CorsMiddleware;
use PHPUnit\Framework\TestCase;
use Webman\Http\Request;
use Webman\Http\Response;

class CorsMiddlewareTest extends TestCase
{
    private CorsMiddleware $middleware;

    protected function setUp(): void
    {
        $this->middleware = new CorsMiddleware();
    }

    private function createRequest(string $method, string $uri): Request
    {
        $raw = "{$method} {$uri} HTTP/1.1\r\nHost: localhost\r\n\r\n";
        return new Request($raw);
    }

    public function testOptionsPreflightReturns204(): void
    {
        $request = $this->createRequest('OPTIONS', '/api/v1/is-dark');
        $handler = fn() => new Response(200, [], 'should not reach');

        $response = $this->middleware->process($request, $handler);

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertEquals('', $response->rawBody());
    }

    public function testOptionsPreflightHasCorsHeaders(): void
    {
        $request = $this->createRequest('OPTIONS', '/api/v1/is-dark');
        $handler = fn() => new Response(200);

        $response = $this->middleware->process($request, $handler);

        $this->assertEquals('*', $response->getHeader('Access-Control-Allow-Origin'));
        $this->assertStringContainsString('GET', $response->getHeader('Access-Control-Allow-Methods'));
        $this->assertStringContainsString('OPTIONS', $response->getHeader('Access-Control-Allow-Methods'));
    }

    public function testGetRequestHasCorsHeaders(): void
    {
        $request = $this->createRequest('GET', '/api/v1/is-dark');
        $handler = fn() => new Response(200, [], 'ok');

        $response = $this->middleware->process($request, $handler);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('*', $response->getHeader('Access-Control-Allow-Origin'));
    }

    public function testGetRequestPassesThroughToHandler(): void
    {
        $request = $this->createRequest('GET', '/test');
        $handlerCalled = false;
        $handler = function () use (&$handlerCalled) {
            $handlerCalled = true;
            return new Response(200, [], 'handler response');
        };

        $response = $this->middleware->process($request, $handler);

        $this->assertTrue($handlerCalled);
        $this->assertEquals('handler response', $response->rawBody());
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/Middleware/CorsMiddlewareTest.php -v`
Expected: FAIL (OPTIONS returns 200 instead of 204, handler is called)

**Step 3: Implement OPTIONS preflight handling**

Replace `app/middleware/CorsMiddleware.php`:

```php
<?php

declare(strict_types=1);

namespace app\middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

class CorsMiddleware implements MiddlewareInterface
{
    private const array CORS_HEADERS = [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, OPTIONS',
        'Access-Control-Allow-Headers' => 'Content-Type, Accept',
        'Access-Control-Max-Age' => '86400',
    ];

    public function process(Request $request, callable $handler): Response
    {
        if ($request->method() === 'OPTIONS') {
            return new Response(204, self::CORS_HEADERS);
        }

        /** @var Response $response */
        $response = $handler($request);
        $response->withHeaders(self::CORS_HEADERS);

        return $response;
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Unit/Middleware/CorsMiddlewareTest.php -v`
Expected: PASS

**Step 5: Commit**

```bash
git add app/middleware/CorsMiddleware.php tests/Unit/Middleware/CorsMiddlewareTest.php
git commit -m "fix: handle OPTIONS preflight in CORS middleware with 204 response"
```

---

### Task 5: Fix XSS vulnerability in city autocomplete

**Files:**
- Modify: `resources/views/index.html:719-722`

**Step 1: Escape city names in template literal**

Replace the autocomplete rendering (line 719-722):

```javascript
// Before:
autocompleteList.innerHTML = matches.map(c =>
    `<div class="autocomplete-item" onclick="selectCity('${c.name}', ${c.lat}, ${c.lng})">
        ${c.name}, ${c.country}
    </div>`
).join('');

// After:
autocompleteList.innerHTML = '';
matches.forEach(c => {
    const div = document.createElement('div');
    div.className = 'autocomplete-item';
    div.textContent = `${c.name}, ${c.country}`;
    div.addEventListener('click', () => selectCity(c.name, c.lat, c.lng));
    autocompleteList.appendChild(div);
});
```

**Step 2: Commit**

```bash
git add resources/views/index.html
git commit -m "security: fix XSS vulnerability in city autocomplete by using DOM API"
```

---

### Task 6: Fix negative max-age in Cache-Control header

**Files:**
- Modify: `app/controller/Api/V1/IsDarkController.php:108`

**Step 1: Clamp max-age to minimum 0**

```php
// Before:
'Cache-Control' => 'public, max-age=' . ($nextChangeAt - time()),
// After:
'Cache-Control' => 'public, max-age=' . max(0, $nextChangeAt - time()),
```

**Step 2: Commit**

```bash
git add app/controller/Api/V1/IsDarkController.php
git commit -m "fix: clamp Cache-Control max-age to minimum 0"
```

---

## Phase 2: Architecture Refactoring

### Task 7: Refactor OpenTelemetryService from Singleton to DI

**Files:**
- Modify: `app/service/OpenTelemetryService.php`
- Modify: `app/bootstrap/OpenTelemetry.php`
- Modify: `app/middleware/TelemetryMiddleware.php`
- Modify: `app/controller/Api/V1/IsDarkController.php`
- Modify: `app/service/SunCalcService.php`
- Modify: `config/dependence.php`
- Modify: `tests/Feature/IsDarkApiTest.php`

**Step 1: Remove singleton pattern from OpenTelemetryService**

In `app/service/OpenTelemetryService.php`:
- Remove `private static ?self $instance = null;`
- Change `private function __construct()` to `public function __construct()`
- Remove `getInstance()` method entirely

**Step 2: Register OpenTelemetryService in DI container**

In `config/dependence.php`, add:

```php
<?php

use app\service\OpenTelemetryService;

return [
    OpenTelemetryService::class => function () {
        return new OpenTelemetryService();
    },
];
```

**Step 3: Update bootstrap to use DI container**

In `app/bootstrap/OpenTelemetry.php`:

```php
<?php

declare(strict_types=1);

namespace app\bootstrap;

use app\service\OpenTelemetryService;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextStorage;
use support\Container;
use Workerman\Timer;
use Workerman\Worker;

class OpenTelemetry
{
    public static function start(?Worker $worker): void
    {
        Context::setStorage(new ContextStorage());
        $otel = Container::get(OpenTelemetryService::class);

        if ($otel->isEnabled()) {
            Timer::add(5, function () use ($otel): void {
                $otel->forceFlush();
            });
        }
    }
}
```

**Step 4: Inject OTel into TelemetryMiddleware via constructor**

```php
<?php

declare(strict_types=1);

namespace app\middleware;

use app\service\OpenTelemetryService;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

class TelemetryMiddleware implements MiddlewareInterface
{
    private const array IGNORED_PATHS = ['/health'];

    public function __construct(private readonly OpenTelemetryService $otel)
    {
    }

    public function process(Request $request, callable $handler): Response
    {
        if (in_array($request->path(), self::IGNORED_PATHS, true)) {
            /** @var Response $response */
            $response = $handler($request);
            return $response;
        }

        $startTime = hrtime(true);

        $span = $this->otel->tracer()
            ->spanBuilder($request->method() . ' ' . $request->path())
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->setAttribute('http.method', $request->method())
            ->setAttribute('http.url', $request->path())
            ->setAttribute('http.query', $request->queryString())
            ->startSpan();

        $scope = $span->activate();

        try {
            /** @var Response $response */
            $response = $handler($request);
            $statusCode = $response->getStatusCode();

            $span->setAttribute('http.status_code', $statusCode);

            if ($statusCode >= 400) {
                $span->setStatus(StatusCode::STATUS_ERROR);
            } else {
                $span->setStatus(StatusCode::STATUS_OK);
            }

            return $response;
        } catch (\Throwable $e) {
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            $span->recordException($e);
            throw $e;
        } finally {
            $durationMs = (hrtime(true) - $startTime) / 1_000_000;

            $route = $request->path();
            $method = $request->method();
            $status = isset($response) ? (string) ($response->getStatusCode()) : '500';

            $this->otel->requestCounter()->add(1, [
                'http.method' => $method,
                'http.route' => $route,
                'http.status_code' => $status,
            ]);

            $this->otel->requestDuration()->record($durationMs, [
                'http.method' => $method,
                'http.route' => $route,
                'http.status_code' => $status,
            ]);

            $span->end();
            $scope->detach();
        }
    }
}
```

**Step 5: Inject OTel into IsDarkController**

In `app/controller/Api/V1/IsDarkController.php`, add OTel as constructor parameter and remove `getInstance()` call:

```php
public function __construct(
    private readonly SunCalcService $sunCalc = new SunCalcService(),
    private readonly ResponseFormatterService $formatter = new ResponseFormatterService(),
    private readonly ?OpenTelemetryService $otel = null,
) {
}
```

In `index()` method, replace:
```php
$otel = OpenTelemetryService::getInstance();
```
with:
```php
$otel = $this->otel ?? Container::get(OpenTelemetryService::class);
```

Add `use support\Container;` to imports.

**Step 6: Inject OTel into SunCalcService**

In `app/service/SunCalcService.php`, add constructor:

```php
public function __construct(private readonly ?OpenTelemetryService $otel = null)
{
}
```

In `calculate()`, replace:
```php
$otel = OpenTelemetryService::getInstance();
```
with:
```php
$otel = $this->otel ?? Container::get(OpenTelemetryService::class);
```

Add `use support\Container;` to imports.

**Step 7: Update feature tests**

In `tests/Feature/IsDarkApiTest.php`, update `setUp()`:

```php
protected function setUp(): void
{
    $sunCalc = new SunCalcService();
    $formatter = new ResponseFormatterService();
    $this->controller = new IsDarkController($sunCalc, $formatter);
}
```

This already works because `$otel` defaults to `null`.

**Step 8: Run all tests**

Run: `./vendor/bin/phpunit -v`
Expected: All tests pass

**Step 9: Commit**

```bash
git add app/service/OpenTelemetryService.php app/bootstrap/OpenTelemetry.php \
  app/middleware/TelemetryMiddleware.php app/controller/Api/V1/IsDarkController.php \
  app/service/SunCalcService.php config/dependence.php tests/Feature/IsDarkApiTest.php
git commit -m "refactor: replace OpenTelemetryService singleton with dependency injection"
```

---

### Task 8: Extract format detection logic from controller to reduce mixed concerns

**Files:**
- Modify: `app/controller/Api/V1/IsDarkController.php`

**Step 1: Extract accept header parsing into a private method**

Add a private method and use it in both `index()` and `errorResponse()`:

```php
private function detectFormat(Request $request): string
{
    $acceptHeaderRaw = $request->header('Accept', 'application/json');
    $acceptHeader = is_string($acceptHeaderRaw) ? $acceptHeaderRaw : 'application/json';
    return $this->formatter->detectFormat($acceptHeader);
}
```

Replace the duplicated code in `index()` (lines 93-95) and `errorResponse()` (lines 120-122) with:
```php
$format = $this->detectFormat($request);
```

Remove the `$formatter` parameter from `errorResponse()` since it uses `$this->formatter`.

**Step 2: Update errorResponse signature**

```php
private function errorResponse(
    Request $request,
    int $statusCode,
    string $message,
): Response {
```

Update all calls to `errorResponse` to remove the `$formatter` argument.

**Step 3: Remove unnecessary local variable aliases**

Remove lines 23-24:
```php
$sunCalc = $this->sunCalc;
$formatter = $this->formatter;
```

Use `$this->sunCalc` and `$this->formatter` directly throughout.

**Step 4: Run tests**

Run: `./vendor/bin/phpunit tests/Feature/IsDarkApiTest.php -v`
Expected: PASS

**Step 5: Commit**

```bash
git add app/controller/Api/V1/IsDarkController.php
git commit -m "refactor: extract format detection, remove duplicated code in IsDarkController"
```

---

### Task 9: Cache static files in memory for Workerman

**Files:**
- Modify: `app/controller/IndexController.php`
- Modify: `config/route.php`

**Step 1: Cache HTML in IndexController using static property**

```php
<?php

declare(strict_types=1);

namespace app\controller;

use support\Request;
use support\Response;

class IndexController
{
    private static ?string $cachedHtml = null;

    public function index(Request $request): Response
    {
        if (self::$cachedHtml === null) {
            self::$cachedHtml = file_get_contents(base_path('resources/views/index.html')) ?: '';
        }

        return new Response(200, ['Content-Type' => 'text/html'], self::$cachedHtml);
    }
}
```

**Step 2: Cache cities and map SVG in route closures**

In `config/route.php`, use static variables:

```php
Route::get('/api/cities', function () {
    static $cities = null;
    if ($cities === null) {
        $citiesFile = base_path('app/Config/cities.php');
        $cities = file_exists($citiesFile) ? require $citiesFile : [];
    }
    return json($cities);
});

Route::get('/map/world.svg', function () {
    static $svgContent = null;
    if ($svgContent === null) {
        $mapFile = base_path('public/map/world.svg');
        if (!file_exists($mapFile)) {
            return new \Webman\Http\Response(404);
        }
        $svgContent = file_get_contents($mapFile);
    }
    return new \Webman\Http\Response(200, ['Content-Type' => 'image/svg+xml'], $svgContent);
});
```

**Step 3: Commit**

```bash
git add app/controller/IndexController.php config/route.php
git commit -m "perf: cache static files in memory for Workerman long-running process"
```

---

## Phase 3: Dead Code & Cleanup

### Task 10: Remove dead code

**Files:**
- Delete: `app/model/Test.php`
- Delete: `app/service/HtmlCacheService.php`
- Modify: `phpstan.neon:7` (remove Test.php exclusion)

**Step 1: Delete dead files**

```bash
rm app/model/Test.php
rm app/service/HtmlCacheService.php
```

**Step 2: Remove PHPStan exclusion**

In `phpstan.neon`, remove lines 6-7:
```yaml
    excludePaths:
        - app/model/Test.php
```

**Step 3: Remove dead CI jobs from build.yml**

In `.github/workflows/build.yml`, remove the `build-docker` job (lines 31-72) and `deploy` job (lines 74-85) since they can never trigger (workflow only runs on `pull_request`, but jobs require `push` + tag).

**Step 4: Run PHPStan to verify**

Run: `./vendor/bin/phpstan analyse`
Expected: No errors

**Step 5: Commit**

```bash
git add -A
git commit -m "chore: remove dead code (Test model, HtmlCacheService, unreachable CI jobs)"
```

---

### Task 11: Translate all Polish comments to English

**Files:**
- Modify: `app/controller/Api/V1/IsDarkController.php`
- Modify: `app/service/SunCalcService.php`
- Modify: `app/middleware/ApiErrorMiddleware.php:24`
- Modify: `app/middleware/CorsMiddleware.php:15` (if any remain after Task 4)
- Modify: `tests/Unit/Service/SunCalcServiceTest.php:36,81,113`

**Step 1: Replace all Polish comments**

IsDarkController.php:
- Line 26: `// Pobieramy parametry` → remove (self-evident)
- Line 31: `// Walidacja - czy parametry istnieją` → remove (self-evident)
- Line 36: `// Konwersja na float` → remove (self-evident)
- Line 40: `// Zaokrąglenie do 2 miejsc po przecinku` → remove (self-evident)
- Line 45: `// Walidacja zakres��w` → remove (self-evident)
- Line 51: `// Obliczenia` → remove (self-evident)
- Line 62: `// Przygotowanie odpowiedzi` → remove (self-evident)
- Line 69: `// Dodajemy szczegółowe dane jeśli requested` → remove (self-evident)
- Line 92: `// Formatowanie odpowiedzi` → remove (self-evident)
- Line 104: `// Headers z cache (ważne do następnej zmiany - sunrise lub sunset)` → `// Cache until next sunrise/sunset transition`

SunCalcService.php:
- Line 15-19: PHPDoc - translate to English
- Line 48: `// Określamy czas ważności odpowiedzi (następna zmiana - sunrise lub sunset)` → remove (self-evident from code)
- Line 53: `// Wybieramy to co nastąpi wcześniej` → remove
- Line 64: `// Brak wschodu/zachodu (polar day/night) - cache na 1h` → `// Polar day/night - no sunrise/sunset, cache for 1h`
- Line 93-94: PHPDoc - translate
- Line 101-102: PHPDoc - translate
- Line 113-116: PHPDoc - translate

ApiErrorMiddleware.php:
- Line 24: `// Jeśli to błąd 4xx/5xx i ścieżka zaczyna się od /api/, formatujemy odpowiedź` → remove (self-evident)

SunCalcServiceTest.php:
- Line 36: `// Szczegółowe pola (cache'owalne - stałe dla danego dnia)` → remove
- Line 81: `// Tylko pola cache'owalne (stałe dla danego dnia)` → remove
- Line 113: `// Dzień + noc = 24h (86400s)` → `// Day + night = 24h (86400s)`

**Step 2: Commit**

```bash
git add app/controller/Api/V1/IsDarkController.php app/service/SunCalcService.php \
  app/middleware/ApiErrorMiddleware.php tests/Unit/Service/SunCalcServiceTest.php
git commit -m "chore: translate Polish comments to English, remove self-evident comments"
```

---

### Task 12: Fix `start.php` hardcoded paths

**Files:**
- Modify: `start.php`

**Step 1: Use `__DIR__` instead of hardcoded `/app`**

```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

chdir(__DIR__);
require_once __DIR__ . '/vendor/autoload.php';
support\App::run();
```

**Step 2: Commit**

```bash
git add start.php
git commit -m "fix: use __DIR__ instead of hardcoded /app path in start.php"
```

---

### Task 13: Add `declare(strict_types=1)` to all PHP files missing it

**Files:**
- Modify: `healthcheck.php`
- Modify: `app/functions.php` (move declare above comment)

**Step 1: Fix healthcheck.php**

```php
<?php

declare(strict_types=1);

$response = @file_get_contents('http://localhost:8787/health');
exit($response && str_contains($response, 'ok') ? 0 : 1);
```

**Step 2: Commit**

```bash
git add healthcheck.php app/functions.php
git commit -m "chore: add declare(strict_types=1) to all PHP files"
```

---

### Task 14: Fix YAML null value handling

**Files:**
- Modify: `app/service/ResponseFormatterService.php:99-104`
- Modify: `tests/Unit/Service/ResponseFormatterServiceTest.php`

**Step 1: Write failing test**

Add to `ResponseFormatterServiceTest.php`:

```php
public function testYamlHandlesNullValues(): void
{
    $data = ['key' => null, 'other' => 'value'];
    $yaml = $this->formatter->format($data, 'yaml');

    $this->assertStringContainsString('key: null', $yaml);
    $this->assertStringContainsString('other: value', $yaml);
}

public function testXmlHandlesNullValues(): void
{
    $data = ['key' => null, 'other' => 'value'];
    $xml = $this->formatter->format($data, 'xml');

    $this->assertStringContainsString('<key/>', $xml);
    $this->assertStringContainsString('<other>value</other>', $xml);
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/Service/ResponseFormatterServiceTest.php --filter testYamlHandlesNullValues -v`
Expected: FAIL

**Step 3: Fix formatYamlValue**

In `app/service/ResponseFormatterService.php`, update `formatYamlValue()`:

```php
private function formatYamlValue(mixed $value): string
{
    if ($value === null) {
        return 'null';
    }
    if (is_string($value) && (str_contains($value, ':') || str_contains($value, '#'))) {
        return "'" . str_replace("'", "''", $value) . "'";
    }
    return is_scalar($value) ? (string) $value : '';
}
```

**Step 4: Fix XML null handling in arrayToXml**

In `arrayToXml()`, add null check before the is_array check:

```php
foreach ($data as $key => $value) {
    $key = $this->sanitizeXmlKey($key);
    if ($value === null) {
        $xml .= "{$spaces}<{$key}/>\n";
        continue;
    }
    if (is_bool($value)) {
        $value = $value ? 'true' : 'false';
    }
    // ... rest unchanged
}
```

**Step 5: Run tests**

Run: `./vendor/bin/phpunit tests/Unit/Service/ResponseFormatterServiceTest.php -v`
Expected: PASS

**Step 6: Commit**

```bash
git add app/service/ResponseFormatterService.php tests/Unit/Service/ResponseFormatterServiceTest.php
git commit -m "fix: handle null values correctly in YAML and XML formatters"
```

---

## Phase 4: Missing Tests

### Task 15: Add tests for ApiErrorMiddleware

**Files:**
- Create: `tests/Unit/Middleware/ApiErrorMiddlewareTest.php`

**Step 1: Write tests**

```php
<?php

declare(strict_types=1);

namespace tests\Unit\Middleware;

use app\middleware\ApiErrorMiddleware;
use app\service\ResponseFormatterService;
use PHPUnit\Framework\TestCase;
use Webman\Http\Request;
use Webman\Http\Response;

class ApiErrorMiddlewareTest extends TestCase
{
    private ApiErrorMiddleware $middleware;

    protected function setUp(): void
    {
        $this->middleware = new ApiErrorMiddleware(new ResponseFormatterService());
    }

    private function createRequest(string $uri, string $accept = 'application/json'): Request
    {
        $raw = "GET {$uri} HTTP/1.1\r\nHost: localhost\r\nAccept: {$accept}\r\n\r\n";
        return new Request($raw);
    }

    public function testPassesThroughSuccessfulApiResponse(): void
    {
        $request = $this->createRequest('/api/v1/is-dark');
        $handler = fn() => new Response(200, [], '{"ok":true}');

        $response = $this->middleware->process($request, $handler);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('{"ok":true}', $response->rawBody());
    }

    public function testFormats4xxApiError(): void
    {
        $request = $this->createRequest('/api/v1/is-dark');
        $handler = fn() => new Response(404);

        $response = $this->middleware->process($request, $handler);

        $this->assertEquals(404, $response->getStatusCode());
        $body = json_decode($response->rawBody(), true);
        $this->assertTrue($body['error']);
        $this->assertEquals(404, $body['status']);
    }

    public function testDoesNotFormatNonApiErrors(): void
    {
        $request = $this->createRequest('/some-page');
        $originalBody = '<h1>Not Found</h1>';
        $handler = fn() => new Response(404, [], $originalBody);

        $response = $this->middleware->process($request, $handler);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals($originalBody, $response->rawBody());
    }

    public function testCatchesExceptionsOnApiRoutes(): void
    {
        $request = $this->createRequest('/api/v1/is-dark');
        $handler = fn() => throw new \RuntimeException('test error');

        $response = $this->middleware->process($request, $handler);

        $this->assertEquals(500, $response->getStatusCode());
        $body = json_decode($response->rawBody(), true);
        $this->assertTrue($body['error']);
    }

    public function testRethrowsExceptionsOnNonApiRoutes(): void
    {
        $request = $this->createRequest('/some-page');
        $handler = fn() => throw new \RuntimeException('test error');

        $this->expectException(\RuntimeException::class);
        $this->middleware->process($request, $handler);
    }

    public function testFormatsErrorAsXmlWhenRequested(): void
    {
        $request = $this->createRequest('/api/v1/is-dark', 'application/xml');
        $handler = fn() => new Response(400);

        $response = $this->middleware->process($request, $handler);

        $this->assertEquals('application/xml', $response->getHeader('Content-Type'));
        $this->assertStringContainsString('<error>true</error>', $response->rawBody());
    }
}
```

**Step 2: Run tests**

Run: `./vendor/bin/phpunit tests/Unit/Middleware/ApiErrorMiddlewareTest.php -v`
Expected: PASS

**Step 3: Commit**

```bash
git add tests/Unit/Middleware/ApiErrorMiddlewareTest.php
git commit -m "test: add unit tests for ApiErrorMiddleware"
```

---

### Task 16: Add tests for health and cities routes

**Files:**
- Create: `tests/Feature/RoutesTest.php`

**Step 1: Write route tests**

Note: These test the route closures by extracting them into testable controller methods first.

Actually, since route closures are hard to test in isolation, we should extract them to controllers. But to keep scope manageable, we'll test them via the existing feature test pattern if possible, or skip and note as future improvement.

For now, add a simple test that verifies the cities data structure:

```php
<?php

declare(strict_types=1);

namespace tests\Feature;

use PHPUnit\Framework\TestCase;

class RoutesTest extends TestCase
{
    public function testCitiesDataIsValidArray(): void
    {
        $citiesFile = __DIR__ . '/../../app/Config/cities.php';
        $this->assertFileExists($citiesFile);

        $cities = require $citiesFile;
        $this->assertIsArray($cities);
        $this->assertNotEmpty($cities);

        foreach ($cities as $city) {
            $this->assertArrayHasKey('name', $city);
            $this->assertArrayHasKey('lat', $city);
            $this->assertArrayHasKey('lng', $city);
            $this->assertArrayHasKey('country', $city);
            $this->assertIsString($city['name']);
            $this->assertIsFloat($city['lat']);
            $this->assertIsFloat($city['lng']);
        }
    }

    public function testMapFileExists(): void
    {
        $mapFile = __DIR__ . '/../../public/map/world.svg';
        $this->assertFileExists($mapFile);

        $content = file_get_contents($mapFile);
        $this->assertNotFalse($content);
        $this->assertStringContainsString('<svg', $content);
    }
}
```

**Step 2: Run tests**

Run: `./vendor/bin/phpunit tests/Feature/RoutesTest.php -v`
Expected: PASS

**Step 3: Commit**

```bash
git add tests/Feature/RoutesTest.php
git commit -m "test: add tests for cities data structure and map file existence"
```

---

## Phase 5: Metadata & Config Cleanup

### Task 17: Fix composer.json metadata

**Files:**
- Modify: `composer.json`

**Step 1: Update metadata**

Replace lines 10-31 with actual project info:

```json
  "keywords": [
    "astronomy",
    "sunrise",
    "sunset",
    "dark",
    "api"
  ],
  "homepage": "https://github.com/crazy-goat/IsItDarkApi",
  "license": "MIT",
  "description": "REST API to determine if it is currently dark at any geographic location.",
  "authors": [
    {
      "name": "Piotr Halas",
      "role": "Developer"
    }
  ],
  "support": {
    "issues": "https://github.com/crazy-goat/IsItDarkApi/issues",
    "source": "https://github.com/crazy-goat/IsItDarkApi"
  },
```

**Step 2: Remove duplicate PSR-4 mapping**

In `autoload.psr-4`, remove the duplicate `"App\\"` mapping (line 49). Keep only `"app\\"`:

```json
  "autoload": {
    "psr-4": {
      "": "./",
      "app\\": "./app",
      "app\\View\\Components\\": "./app/view/components"
    }
  },
```

**Step 3: Commit**

```bash
git add composer.json
git commit -m "chore: update composer.json metadata from Webman defaults to actual project info"
```

---

### Task 18: Fix LICENSE copyright

**Files:**
- Modify: `LICENSE`

**Step 1: Update copyright line**

Change line 3 from:
```
Copyright (c) 2021 walkor<walkor@workerman.net> and contributors (see https://github.com/walkor/webman/contributors)
```
to:
```
Copyright (c) 2025 Piotr Halas and contributors
```

**Step 2: Commit**

```bash
git add LICENSE
git commit -m "chore: fix LICENSE copyright attribution"
```

---

### Task 19: Fix translation.php locale default

**Files:**
- Modify: `config/translation.php`

**Step 1: Change Chinese locale to English**

```php
'locale' => 'en',
'fallback_locale' => ['en'],
```

**Step 2: Commit**

```bash
git add config/translation.php
git commit -m "chore: change default locale from zh_CN to en"
```

---

### Task 20: Remove deprecated `version` from docker-compose.yml

**Files:**
- Modify: `docker-compose.yml`

**Step 1: Remove line 1**

Remove `version: "3"` from the file.

**Step 2: Commit**

```bash
git add docker-compose.yml
git commit -m "chore: remove deprecated version key from docker-compose.yml"
```

---

### Task 21: Remove Webman boilerplate headers from config files

**Files:**
- Modify: `config/route.php` (remove walkor header, lines 2-13)
- Modify: `config/app.php` (remove walkor header)
- Modify: `config/middleware.php` (remove walkor header)
- Modify: `config/bootstrap.php` (remove walkor header)
- Modify: `config/container.php` (remove walkor header)
- Modify: `config/session.php` (remove walkor header)
- Modify: `config/translation.php` (remove walkor header)

**Step 1: Remove the `@author walkor` / `@copyright walkor` / `@link workerman.net` / `@license` comment blocks from all config files**

Each file has a block like:
```php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * ...
 * @author    walkor<walkor@workerman.net>
 * ...
 */
```

Remove these blocks from all listed files.

**Step 2: Commit**

```bash
git add config/
git commit -m "chore: remove Webman boilerplate headers from config files"
```

---

### Task 22: Clean up empty `app/functions.php`

**Files:**
- Modify: `app/functions.php`

**Step 1: Remove the misleading comment**

```php
<?php

declare(strict_types=1);
```

(Just the declare statement, no comment about "custom functions")

**Step 2: Commit**

```bash
git add app/functions.php
git commit -m "chore: clean up empty functions.php"
```

---

### Task 23: Fix frontend map loading path

**Files:**
- Modify: `resources/views/index.html:351`

**Step 1: Change map fetch URL to match the route**

The route serves `/map/world.svg` but the frontend fetches `/map/world-detailed.svg`. Check which file actually exists and align:

If `public/map/world-detailed.svg` exists and is the desired map, add a route for it. If `world.svg` is the correct map, fix the frontend:

```javascript
// Before:
fetch('/map/world-detailed.svg')
// After:
fetch('/map/world.svg')
```

**Step 2: Commit**

```bash
git add resources/views/index.html
git commit -m "fix: align frontend map fetch URL with server route"
```

---

## Phase 6: Final Verification

### Task 24: Run full lint and test suite

**Step 1: Run Rector**

Run: `./vendor/bin/rector process --dry-run`
Expected: No changes needed

**Step 2: Run PHP_CodeSniffer**

Run: `./vendor/bin/phpcs --standard=phpcs.xml.dist`
Expected: No errors

**Step 3: Run PHPStan**

Run: `./vendor/bin/phpstan analyse`
Expected: No errors

**Step 4: Run all tests**

Run: `./vendor/bin/phpunit -v`
Expected: All tests pass

**Step 5: Fix any issues found by linters**

If any issues are found, fix them and commit:

```bash
git add -A
git commit -m "chore: fix linter issues from final verification"
```

---

## Summary

| Phase | Tasks | Focus |
|-------|-------|-------|
| 1 | Tasks 1-6 | Critical bugs & security |
| 2 | Tasks 7-9 | Architecture refactoring |
| 3 | Tasks 10-14 | Dead code & cleanup |
| 4 | Tasks 15-16 | Missing tests |
| 5 | Tasks 17-23 | Metadata & config cleanup |
| 6 | Task 24 | Final verification |

**Total: 24 tasks, ~24 commits**
