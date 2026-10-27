<?php
$file = '/var/www/nettingteam.com/admin/data/documents.json';
$raw = file_get_contents($file);

// Clean any accidental invalid JSON structures
$raw = preg_replace('/"show_stamp":\s*true,\s*\},/', '"show_stamp": true,', $raw);

$docs = json_decode($raw, true);
if (!$docs) {
    echo "JSON DECODE ERROR: " . json_last_error_msg() . "
";
    exit(1);
}

echo "Successfully parsed " . count($docs) . " documents!
";

$specs_23 = [
    'civil' => [
        'Court Area: 100ft x 50ft = 5,000 SFT',
        'Specification: 8mm Fe-500D TMT Steel Rebar Mesh @ 150mm (6") c/c',
        "Foundation: 20 concrete footings (1.5' x 1.5' x 3' deep)"
    ],
    'structural' => [
        "Vertical Columns: 20 Nos (4 corner + 12 length + 4 width) - 🪜 2-Lines Ladder-Type Column (Dual 40mm × 40mm Square Pipe, 2.0mm (14G), 1.5' Rungs)",
        'Column Specification: 2.0mm Wall Thickness (14 Gauge)',
        'Horizontal Tie Pipes: 6 tiers all around (1800 RFT, 90 pipes of 20ft, 14 Gauge)',
        'Bay Spacing: ~14.3ft c/c along length, ~16.7ft c/c along width',
        'Ladder Rungs: 320 Nos welded steps/bracing (2.0mm (14G)) spaced @ 1.5ft c/c for climbing inspection & wind rigidity',
        'Overhead Net Support: High-tension wire rope cable suspension grid (Zero rigid top steel)'
    ],
    'perimeter' => [
        'Perimeter Area: 2 x (100 + 50) x 25ft = 7875 SFT (incl. 5% overlap & skirt)',
        'Net Specification: 2.5mm / 3.0mm Braided HDPE (40mm Square Mesh)',
        'Tension Wire Rope: 2550 RFT galvanized wire with turnbuckles & clamps'
    ],
    'overhead' => [
        'Top Enclosure: 100ft x 50ft = 5400 SFT (incl. 8% camber allowance)',
        'Specification: 1.5mm / 24-Ply UV Stabilized HDPE Braided Net, 35mm / 40mm Square Mesh',
        'Support: Wire Rope Tension Grid (No Top Steel)'
    ],
    'turf' => [
        'Manufactured Roll Size: 2.0 MTR x 25.0 MTR (6.5 FT x 83 FT = ~540 SFT per roll)',
        'Coverage & Rolls: 5000 SFT requiring 10 full rolls laid longitudinally',
        'Jointing: 30cm seaming tape with heavy-duty 2-part polyurethane (PU) adhesive',
        'Type: Non-Infill Multi-Sport Artificial Grass (Zero Infill Required)'
    ],
    'led' => [
        'Fixtures: 8 units mounted on 8 masts',
        'Illumination: 250+ Lux average designed for high-speed night sports visibility'
    ],
    'electrical' => [
        'Complete turnkey electrical wiring, 4.0 sq.mm armoured copper cable and circuit protection'
    ],
    'gate' => [
        'Access control door (4ft x 7ft) with heavy-duty hinges, lock, and mesh panel'
    ],
    'marking' => [
        'Cricket Pitch Pad: 6.5 FT (2.0 MTR) Width x 66 FT Length with regulation bowling & batting creases',
        'Sports Equipment: Match pitch pad, stumps bases, and boundary markers'
    ],
    'coating' => [
        'Weather-resistant outdoor protective coating on all steelwork (80-100 Microns DFT)'
    ],
    'fabrication' => [
        'Skilled manpower for civil, steel fabrication, turfing, and net rigging'
    ],
    'transport' => [
        'Safe transport of heavy pipes, turf rolls, nets, and aggregate to project site'
    ],
    'engineering' => [
        'Engineering supervision, alignment calibration, and final handover certificate'
    ]
];

$specs_22 = [
    'civil' => [
        'Court Area: 100ft x 50ft = 5,000 SFT',
        "Foundation: 20 concrete footings (1.5' x 1.5' x 3' deep)"
    ],
    'structural' => [
        'Vertical Columns: 20 Nos (4 corner + 12 length + 4 width), Clear Height: 25 FT',
        'Horizontal Tie Pipes: 6 tiers all around (1800 RFT, 90 pipes of 20ft length)',
        'Bay Spacing: ~14.29ft c/c along length, ~16.67ft c/c along width',
        'Roof Trusses: 8 Nos spanning 50ft width'
    ],
    'perimeter' => [
        'Perimeter Area: 2 x (100 + 50) x 25ft = 7875 SFT (incl. 5% overlap & skirt)',
        'Tension Wire Rope: 2870 RFT galvanized wire with turnbuckles & clamps'
    ],
    'overhead' => [
        'Top Enclosure: 100ft x 50ft = 5250 SFT (incl. 5% camber allowance)'
    ],
    'turf' => [
        'Manufactured Roll Size: 2.0 MTR x 25.0 MTR (6.5 FT x 83 FT = ~540 SFT per roll)',
        'Coverage & Rolls: 5100 SFT requiring 10 full rolls laid longitudinally',
        'Jointing: 30cm seaming tape with heavy-duty 2-part polyurethane (PU) adhesive'
    ],
    'led' => [
        'Fixtures: 8 units mounted on 6 masts',
        'Illumination designed for high-speed night sports visibility'
    ],
    'electrical' => [
        'Complete turnkey electrical wiring and circuit protection'
    ],
    'gate' => [
        'Access control door with heavy-duty hinges and lock positioned on longitudinal side'
    ],
    'marking' => [
        'Cricket Pitch Pad: 6.5 FT (2.0 MTR) Width x 66 FT Length (standard 1-roll 2M width) with regulation bowling & batting creases',
        'Sports Equipment: Match pitch pad, stumps bases, and boundary markers'
    ],
    'coating' => [
        'Weather-resistant outdoor protective coating on all structural steelwork (20 columns + 1800 RFT tie purlins)'
    ],
    'fabrication' => [
        'Certified manpower for civil, structural steel welding/erection at 25ft elevation, turf seaming, and net rigging'
    ],
    'transport' => [
        'Dedicated transportation and crane unloading of structural steel columns, trusses, turf rolls, and nets'
    ],
    'engineering' => [
        'Project engineer supervision, structural alignment calibration, and final handover certificate'
    ]
];

foreach ($docs as &$doc) {
    $id = $doc['id'] ?? '';
    if ($id === 'EST-202609-023') {
        foreach ($doc['items'] as &$it) {
            $d = strtolower($it['description'] ?? '');
            if (strpos($d, 'civil') !== false) $it['specs'] = $specs_23['civil'];
            elseif (strpos($d, 'structural') !== false) $it['specs'] = $specs_23['structural'];
            elseif (strpos($d, 'perimeter') !== false || strpos($d, 'side & back') !== false) $it['specs'] = $specs_23['perimeter'];
            elseif (strpos($d, 'overhead') !== false || strpos($d, 'top net') !== false) $it['specs'] = $specs_23['overhead'];
            elseif (strpos($d, 'turf') !== false || strpos($d, 'grass') !== false) $it['specs'] = $specs_23['turf'];
            elseif (strpos($d, 'floodlight') !== false || strpos($d, 'led') !== false) $it['specs'] = $specs_23['led'];
            elseif (strpos($d, 'electrical') !== false || strpos($d, 'cabling') !== false) $it['specs'] = $specs_23['electrical'];
            elseif (strpos($d, 'gate') !== false) $it['specs'] = $specs_23['gate'];
            elseif (strpos($d, 'marking') !== false || strpos($d, 'fixture') !== false) $it['specs'] = $specs_23['marking'];
            elseif (strpos($d, 'coating') !== false || strpos($d, 'corrosion') !== false) $it['specs'] = $specs_23['coating'];
            elseif (strpos($d, 'fabrication') !== false || strpos($d, 'erection') !== false) $it['specs'] = $specs_23['fabrication'];
            elseif (strpos($d, 'transport') !== false || strpos($d, 'freight') !== false) $it['specs'] = $specs_23['transport'];
            elseif (strpos($d, 'engineering') !== false || strpos($d, 'inspection') !== false) $it['specs'] = $specs_23['engineering'];
        }
    }
    if ($id === 'EST-202609-022') {
        foreach ($doc['items'] as &$it) {
            $d = strtolower($it['description'] ?? '');
            if (strpos($d, 'civil') !== false) $it['specs'] = $specs_22['civil'];
            elseif (strpos($d, 'structural') !== false) $it['specs'] = $specs_22['structural'];
            elseif (strpos($d, 'perimeter') !== false || strpos($d, 'side & back') !== false) $it['specs'] = $specs_22['perimeter'];
            elseif (strpos($d, 'overhead') !== false || strpos($d, 'top net') !== false) $it['specs'] = $specs_22['overhead'];
            elseif (strpos($d, 'turf') !== false || strpos($d, 'grass') !== false) $it['specs'] = $specs_22['turf'];
            elseif (strpos($d, 'floodlight') !== false || strpos($d, 'led') !== false) $it['specs'] = $specs_22['led'];
            elseif (strpos($d, 'electrical') !== false || strpos($d, 'cabling') !== false) $it['specs'] = $specs_22['electrical'];
            elseif (strpos($d, 'gate') !== false) $it['specs'] = $specs_22['gate'];
            elseif (strpos($d, 'marking') !== false || strpos($d, 'fixture') !== false) $it['specs'] = $specs_22['marking'];
            elseif (strpos($d, 'coating') !== false || strpos($d, 'corrosion') !== false) $it['specs'] = $specs_22['coating'];
            elseif (strpos($d, 'fabrication') !== false || strpos($d, 'erection') !== false) $it['specs'] = $specs_22['fabrication'];
            elseif (strpos($d, 'transport') !== false || strpos($d, 'freight') !== false) $it['specs'] = $specs_22['transport'];
            elseif (strpos($d, 'engineering') !== false || strpos($d, 'inspection') !== false) $it['specs'] = $specs_22['engineering'];
        }
    }
}
unset($doc);

$out = json_encode($docs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
file_put_contents($file . '.tmp', $out);
rename($file . '.tmp', $file);
chmod($file, 0775);
chown($file, 'www-data');
chgrp($file, 'www-data');

echo "SUCCESSFULLY REPAIRED ALL DOCUMENTS IN DATABASE!
";
