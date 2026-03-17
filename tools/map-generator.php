<?php
/**
 * Generator mapy SVG z Natural Earth
 * Tworzy uproszczoną mapę świata w formacie SVG
 */

const OUTPUT_FILE = __DIR__ . '/../public/map/world.svg';

/**
 * Tworzy prostą mapę świata w SVG
 * Używa uproszczonej geometrii (prostokąty dla kontynentów)
 */
function generateSimpleWorldMap(): string
{
    $width = 1000;
    $height = 500;
    
    $svg = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $svg .= '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $width . ' ' . $height . '" width="' . $width . '" height="' . $height . '">' . "\n";
    
    // Background (ocean)
    $svg .= '  <rect width="' . $width . '" height="' . $height . '" fill="#0a1628"/>' . "\n";
    
    // Grid lines
    $svg .= '  <g stroke="#1a2a4a" stroke-width="0.5">' . "\n";
    for ($i = 0; $i <= 360; $i += 30) {
        $x = ($i / 360) * $width;
        $svg .= '    <line x1="' . $x . '" y1="0" x2="' . $x . '" y2="' . $height . '"/>' . "\n";
    }
    for ($i = -90; $i <= 90; $i += 30) {
        $y = (($i + 90) / 180) * $height;
        $svg .= '    <line x1="0" y1="' . $y . '" x2="' . $width . '" y2="' . $y . '"/>' . "\n";
    }
    $svg .= '  </g>' . "\n";
    
    // Continents (simplified as polygons)
    $continents = [
        // North America
        [
            'fill' => '#2a3a5a',
            'points' => [
                [120, 80], [280, 60], [320, 120], [300, 200], 
                [250, 250], [200, 220], [150, 180], [100, 140]
            ]
        ],
        // South America
        [
            'fill' => '#2a3a5a',
            'points' => [
                [260, 280], [320, 270], [340, 350], [300, 450], [260, 420], [240, 350]
            ]
        ],
        // Europe
        [
            'fill' => '#3a4a6a',
            'points' => [
                [460, 100], [520, 90], [560, 120], [540, 160], [480, 150], [450, 130]
            ]
        ],
        // Africa
        [
            'fill' => '#3a4a6a',
            'points' => [
                [480, 180], [560, 170], [600, 250], [580, 380], 
                [520, 400], [460, 350], [450, 250]
            ]
        ],
        // Asia
        [
            'fill' => '#4a5a7a',
            'points' => [
                [560, 80], [750, 60], [880, 100], [900, 200], 
                [850, 280], [750, 300], [650, 250], [580, 180], [560, 120]
            ]
        ],
        // Australia
        [
            'fill' => '#4a5a7a',
            'points' => [
                [780, 350], [880, 340], [920, 400], [880, 450], [800, 440], [760, 400]
            ]
        ],
        // Antarctica
        [
            'fill' => '#5a6a8a',
            'points' => [
                [100, 480], [500, 470], [900, 480], [900, 500], [100, 500]
            ]
        ],
        // Greenland
        [
            'fill' => '#6a7a9a',
            'points' => [
                [340, 40], [420, 30], [440, 80], [400, 100], [350, 90]
            ]
        ],
        // UK & Ireland
        [
            'fill' => '#5a6a8a',
            'points' => [
                [430, 110], [450, 105], [455, 125], [440, 130], [425, 120]
            ]
        ],
        // Japan
        [
            'fill' => '#5a6a8a',
            'points' => [
                [860, 160], [880, 150], [890, 180], [870, 190], [855, 175]
            ]
        ],
        // Indonesia / SE Asia islands
        [
            'fill' => '#4a5a7a',
            'points' => [
                [750, 320], [820, 310], [850, 340], [800, 360], [740, 340]
            ]
        ],
        // New Zealand
        [
            'fill' => '#5a6a8a',
            'points' => [
                [920, 420], [940, 410], [950, 440], [930, 450]
            ]
        ],
    ];
    
    foreach ($continents as $continent) {
        $points = array_map(fn($p) => $p[0] . ',' . $p[1], $continent['points']);
        $svg .= '  <polygon fill="' . $continent['fill'] . '" stroke="#4a5a7a" stroke-width="1" points="' . implode(' ', $points) . '"/>' . "\n";
    }
    
    // Country borders (simplified)
    $svg .= '  <g stroke="#5a6a8a" stroke-width="0.5" fill="none">' . "\n";
    // US-Canada border
    $svg .= '    <polyline points="120,140 200,130 280,125"/>' . "\n";
    // US-Mexico border  
    $svg .= '    <polyline points="150,220 220,215 280,210"/>' . "\n";
    // Europe borders
    $svg .= '    <polyline points="460,130 500,125 540,130"/>' . "\n";
    // Asia borders
    $svg .= '    <polyline points="650,180 700,175 750,180"/>' . "\n";
    $svg .= '  </g>' . "\n";
    
    $svg .= '</svg>';
    
    return $svg;
}

echo "Generating world map...\n";

$svg = generateSimpleWorldMap();

// Ensure directory exists
$dir = dirname(OUTPUT_FILE);
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

file_put_contents(OUTPUT_FILE, $svg);

echo "Map saved to: " . OUTPUT_FILE . "\n";
echo "Done!\n";
