<?php
/**
 * Generate equirectangular SVG world map from Natural Earth GeoJSON
 */

$width = 950;
$height = 500;
$geojsonFile = '/tmp/countries.geojson';
$outputFile = __DIR__ . '/../public/map/world-equirect.svg';

$data = json_decode(file_get_contents($geojsonFile), true);

function lngToX(float $lng, int $width): float {
    return ($lng + 180) / 360 * $width;
}

function latToY(float $lat, int $height): float {
    return (90 - $lat) / 180 * $height;
}

function polygonToPath(array $ring, int $width, int $height): string {
    $parts = [];
    foreach ($ring as $i => $coord) {
        $x = round(lngToX($coord[0], $width), 2);
        $y = round(latToY($coord[1], $height), 2);
        $parts[] = ($i === 0 ? 'M' : 'L') . $x . ',' . $y;
    }
    return implode(' ', $parts) . ' Z';
}

function geometryToPath(array $geometry, int $width, int $height): string {
    $paths = [];
    if ($geometry['type'] === 'Polygon') {
        foreach ($geometry['coordinates'] as $ring) {
            $paths[] = polygonToPath($ring, $width, $height);
        }
    } elseif ($geometry['type'] === 'MultiPolygon') {
        foreach ($geometry['coordinates'] as $polygon) {
            foreach ($polygon as $ring) {
                $paths[] = polygonToPath($ring, $width, $height);
            }
        }
    }
    return implode(' ', $paths);
}

$svg = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$svg .= '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $width . ' ' . $height . '" width="' . $width . '" height="' . $height . '">' . "\n";
$svg .= '  <rect width="' . $width . '" height="' . $height . '" fill="#0a1628"/>' . "\n";
$svg .= '  <g fill="#2a3f5f" stroke="#4a6fa5" stroke-width="0.3">' . "\n";

foreach ($data['features'] as $feature) {
    $name = $feature['properties']['NAME'] ?? '';
    $id = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name));
    $d = geometryToPath($feature['geometry'], $width, $height);
    if ($d) {
        $svg .= '    <path id="' . htmlspecialchars($id) . '" d="' . $d . '"/>' . "\n";
    }
}

$svg .= '  </g>' . "\n";
$svg .= '</svg>' . "\n";

file_put_contents($outputFile, $svg);
echo "Generated: $outputFile (" . round(filesize($outputFile)/1024) . " KB)\n";
