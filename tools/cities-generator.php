<?php
/**
 * Generator miast z GeoNames
 * Pobiera top 100 miast dla każdego kraju
 */

require_once __DIR__ . '/../vendor/autoload.php';

const GEONAMES_USERNAME = 'demo'; // Użytkownik powinien zmienić na własny
const OUTPUT_FILE = __DIR__ . '/../app/Config/cities.php';

function fetchCitiesForCountry(string $countryCode): array
{
    $url = sprintf(
        'http://api.geonames.org/searchJSON?country=%s&featureClass=P&maxRows=100&orderby=population&username=%s',
        $countryCode,
        GEONAMES_USERNAME
    );
    
    $response = @file_get_contents($url);
    if (!$response) {
        echo "Failed to fetch data for {$countryCode}\n";
        return [];
    }
    
    $data = json_decode($response, true);
    if (!isset($data['geonames'])) {
        return [];
    }
    
    $cities = [];
    foreach ($data['geonames'] as $place) {
        if (isset($place['name'], $place['lat'], $place['lng'])) {
            $cities[] = [
                'name' => $place['name'],
                'lat' => round((float)$place['lat'], 2),
                'lng' => round((float)$place['lng'], 2),
                'country' => $countryCode,
            ];
        }
    }
    
    return $cities;
}

function getCountryCodes(): array
{
    // Top 50 krajów (najwięcej ludności + popularne turystycznie)
    return [
        'CN', 'IN', 'US', 'ID', 'PK', 'BR', 'NG', 'BD', 'RU', 'MX',
        'JP', 'PH', 'EG', 'ET', 'VN', 'CD', 'TR', 'IR', 'DE', 'TH',
        'GB', 'FR', 'IT', 'ZA', 'KR', 'ES', 'AR', 'CA', 'PL', 'UA',
        'AU', 'TW', 'SE', 'BE', 'CZ', 'GR', 'PT', 'HU', 'IL', 'AT',
        'CH', 'SG', 'DK', 'FI', 'NO', 'NZ', 'IE', 'HR', 'BG', 'RS',
    ];
}

echo "Generating cities database...\n";

$allCities = [];
$countryCodes = getCountryCodes();

foreach ($countryCodes as $code) {
    echo "Fetching cities for {$code}...\n";
    $cities = fetchCitiesForCountry($code);
    $allCities = array_merge($allCities, $cities);
    
    // Rate limiting - max 1000 req/hour dla darmowego konta
    sleep(1);
}

echo "Total cities fetched: " . count($allCities) . "\n";

// Sort by name
usort($allCities, fn($a, $b) => strcmp($a['name'], $b['name']));

// Generate PHP file
$content = "<?php\n\n";
$content .= "/**\n";
$content .= " * Auto-generated cities database\n";
$content .= " * Generated: " . date('Y-m-d H:i:s') . "\n";
$content .= " * Source: GeoNames.org\n";
$content .= " */\n\n";
$content .= "return " . var_export($allCities, true) . ";\n";

file_put_contents(OUTPUT_FILE, $content);

echo "Cities saved to: " . OUTPUT_FILE . "\n";
echo "Done!\n";
