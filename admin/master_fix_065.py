import json
import re
import os
import shutil
import sys

print("=== STARTING MASTER FIX FOR EST-202609-065 & COMPLEX 105x170 ===")

# ==============================================================================
# PART 1: PATCH turnkey_calculator.php
# ==============================================================================
calc_path = "/var/www/gcm.enterprises/admin/includes/turnkey_calculator.php"
with open(calc_path, "r", encoding="utf-8") as f:
    calc_code = f.read()

# 1A. Update getTurnkeyDefaultGates to include multisport_complex_105x170
gates_func_target = "function getTurnkeyDefaultGates($L, $W, $H, $gateCount = 1, $sportType = 'box_cricket') {\n        $L = (float)$L ?: 90;\n        $W = (float)$W ?: 60;\n        $H = (float)$H ?: 25;\n        $gateCount = (int)$gateCount;\n        if ($gateCount <= 0) return [];\n        $gates = [];"

gates_105x170_code = """function getTurnkeyDefaultGates($L, $W, $H, $gateCount = 1, $sportType = 'box_cricket') {
        $L = (float)$L ?: 90;
        $W = (float)$W ?: 60;
        $H = (float)$H ?: 25;
        $gateCount = (int)$gateCount;
        if ($gateCount <= 0) return [];

        if ($sportType === 'multisport_complex_105x170' || ($L == 170 && $W == 105)) {
            return [
                [
                    'id'          => 'gate_1',
                    'name'        => 'Main Campus Entrance Gate (Left Pathway)',
                    'wall'        => 'front_width',
                    'x_ft'        => 0,
                    'z_ft'        => -45.0,
                    'width_ft'    => 12,
                    'height_ft'   => 8,
                    'type'        => 'main_gate',
                    'color'       => '#f97316',
                    'position_ft' => 7.5
                ],
                [
                    'id'          => 'gate_2',
                    'name'        => 'Indoor Sports Arena Main Double Door',
                    'wall'        => 'transverse_indoor_facade',
                    'x_ft'        => 68,
                    'z_ft'        => -45.0,
                    'width_ft'    => 6,
                    'height_ft'   => 8,
                    'type'        => 'main_door',
                    'color'       => '#0284c7',
                    'position_ft' => 68
                ],
                [
                    'id'          => 'gate_3',
                    'name'        => 'Outdoor Cricket Arena Player Door',
                    'wall'        => 'cricket_pathway_partition',
                    'x_ft'        => 43,
                    'z_ft'        => -37.5,
                    'width_ft'    => 4,
                    'height_ft'   => 7,
                    'type'        => 'player_door',
                    'color'       => '#22c55e',
                    'position_ft' => 43
                ],
                [
                    'id'          => 'gate_4',
                    'name'        => 'Indoor Hall Emergency Exit Door',
                    'wall'        => 'back_width',
                    'x_ft'        => 170,
                    'z_ft'        => 0.0,
                    'width_ft'    => 4,
                    'height_ft'   => 7,
                    'type'        => 'emergency_door',
                    'color'       => '#eab308',
                    'position_ft' => 52.5
                ]
            ];
        }

        $gates = [];"""

if gates_func_target in calc_code:
    calc_code = calc_code.replace(gates_func_target, gates_105x170_code, 1)
    print("[PHP] getTurnkeyDefaultGates patched for 105x170.")
elif "if ($sportType === 'multisport_complex_105x170'" in calc_code:
    print("[PHP] getTurnkeyDefaultGates already patched.")
else:
    print("[PHP WARNING] gates_func_target not found!")

# 1B. Side GI Sheets in Section 5 (after 5C Gutters)
gutter_target = """            $docItems[] = [
                'description' => "Heavy-Duty Eaves Gutters & Rainwater Downpipes System\\n0.60mm Galvalume profile rainwater drainage gutters running on both eaves lengths with 110mm PVC storm downpipes for zero water spill.",
                'hsn'         => '7308',
                'qty'         => $specs['gutter_rft'],
                'unit'        => 'RFT',
                'rate'        => $gutterRate,
                'amount'      => $gutterAmt,
                'specs'       => [
                    "Gutters: " . (int)$specs['gutter_rft'] . " RFT along both 185 FT eaves boundaries",
                    "Downpipes: " . (int)$specs['downpipe_rft'] . " RFT of 110mm heavy PVC downpipes with steel brackets"
                ],
                'gst_rate'    => 18.0,
            ];
        } elseif ($specs['has_roof'] && !empty($specs['top_net_sft']) && $specs['top_net_sft'] > 0) {"""

side_sheets_code = """            $docItems[] = [
                'description' => "Heavy-Duty Eaves Gutters & Rainwater Downpipes System\\n0.60mm Galvalume profile rainwater drainage gutters running on both eaves lengths with 110mm PVC storm downpipes for zero water spill.",
                'hsn'         => '7308',
                'qty'         => $specs['gutter_rft'],
                'unit'        => 'RFT',
                'rate'        => $gutterRate,
                'amount'      => $gutterAmt,
                'specs'       => [
                    "Gutters: " . (int)$specs['gutter_rft'] . " RFT along eaves boundaries",
                    "Downpipes: " . (int)$specs['downpipe_rft'] . " RFT of 110mm heavy PVC downpipes with steel brackets"
                ],
                'gst_rate'    => 18.0,
            ];

            // 5D. 0.50mm Pre-Painted Galvalume (PPGL) Profile Side Wall Cladding Sheets (All 4 Sides)
            if ($sportType === 'multisport_complex_105x170' || !empty($specs['wall_sheet_sft']) || !empty($layers['side_sheets'])) {
                $wallSheetSft = !empty($specs['wall_sheet_sft']) ? (float)$specs['wall_sheet_sft'] : 12995.0;
                $wallSheetRate = ($selectedPkg === 'essential') ? 52.0 : (($selectedPkg === 'premium_pro') ? 68.0 : 58.0);
                $wallSheetAmt  = round($wallSheetSft * $wallSheetRate, 2);
                $docItems[] = [
                    'description' => "0.50mm Pre-Painted Galvalume (PPGL) Corrugated Profile Metal Wall Cladding Sheets (All 4 Sides)\\r\\nTCT 0.50mm high-tensile 550 MPa AZ150 zinc-aluminum alloy color-coated corrugated profile side wall sheeting covering all 4 perimeter walls of the Indoor PEB Sports Complex from 4.5 FT masonry plinth wall up to full 35 FT clear eave height, including corner flashings, drip trims & weather fasteners.",
                    'hsn'         => '7210',
                    'qty'         => $wallSheetSft,
                    'unit'        => 'SQFT',
                    'rate'        => $wallSheetRate,
                    'amount'      => $wallSheetAmt,
                    'specs'       => [
                        "Cladding Coverage: " . number_format($wallSheetSft) . " SFT (4 Sides: 2 × 102 FT lengths + 2 × 105 FT widths × 30.5 FT height + gable apexes)",
                        "Material: 0.50mm TCT Pre-Painted Galvalume (AZ150, 550 MPa Yield Strength)",
                        "Flashing & Trims: Corner flashings, eaves apron trims & self-drilling fasteners with vulcanized EPDM washers",
                        "Weather Sealing: Silicon sealant along overlaps for complete wind & water tightness"
                    ],
                    'gst_rate'    => 18.0,
                ];

                // 5E. Secondary Structural Wall Girts / Runners Grid Framework
                $wallGirtRft = round(414 * 6, 2); // 414 RFT perimeter x 6 tiers = 2,484 RFT
                $wallGirtRate = 55.0; // Rs 55/RFT
                $wallGirtAmt = round($wallGirtRft * $wallGirtRate, 2);
                $docItems[] = [
                    'description' => "Structural Secondary Wall Girt & Runner Grid Framework\\r\\nEngineered cold-formed Z-girts / tubular steel horizontal runners mounted @ 5.0 FT c/c across all 4 perimeter walls of the Indoor PEB Sports Complex for corrugated side wall sheet fastening.",
                    'hsn'         => '7308',
                    'qty'         => $wallGirtRft,
                    'unit'        => 'RFT',
                    'rate'        => $wallGirtRate,
                    'amount'      => $wallGirtAmt,
                    'specs'       => [
                        "Quantity: " . number_format($wallGirtRft) . " Running Feet of structural wall girt runners",
                        "Spacing: 5.0 FT vertical center-to-center tiers along all 4 walls",
                        "Coating: Anti-corrosion zinc chromate primer & weather-resistant enamel coat"
                    ],
                    'gst_rate'    => 18.0,
                ];
            }
        } elseif ($specs['has_roof'] && !empty($specs['top_net_sft']) && $specs['top_net_sft'] > 0) {"""

if gutter_target in calc_code:
    calc_code = calc_code.replace(gutter_target, side_sheets_code, 1)
    print("[PHP] Side GI sheets injected after gutters.")
elif "0.50mm Pre-Painted Galvalume (PPGL) Corrugated Profile Metal Wall Cladding Sheets (All 4 Sides)" in calc_code:
    print("[PHP] Side GI sheets already injected.")
else:
    print("[PHP WARNING] gutter_target not found!")

# 1C. Section 6 Flooring for multisport_complex_105x170
flooring_target = "// Zone C: Open Utility Area (Empty space requires no flooring scope)\n        } elseif ($includeTurf && $flooringProfile['has_turf']) {"
flooring_105x170 = """// Zone C: Open Utility Area (Empty space requires no flooring scope)
        } elseif ($sportType === 'multisport_complex_105x170') {
            // === ZONE 2: OUTDOOR BOX CRICKET GROUND (90 FT × 50 FT = 4,500 SFT) ===
            // 1. Cricket Pitch Turf (Full Roll 540 SFT)
            $docItems[] = [
                'description' => "Zone 2 (Cricket): 15mm Curled Cricket Match Pitch Turf (Full Roll 6.5 FT × 82 FT [2M × 25M] = 540 SFT)\\r\\nDedicated high-density curled wicket turf strip with 3 FT structure safety offset for natural ball bounce.",
                'hsn'         => '5703',
                'qty'         => 540,
                'unit'        => 'SQFT',
                'rate'        => 78.0,
                'amount'      => 42120.0,
                'specs'       => [
                    "Roll Specification: Full Roll (6.5 FT × 82 FT = 540 SFT [2.0M × 25.0M])",
                    "Installed Pitch Dimensions: 6.5 FT × 66 FT = 429 SFT laid at center of 90x50 cricket arena",
                    "End Clearances: 3.0 FT clear setback from frame structures"
                ],
                'gst_rate'    => 5.0,
            ];
            $docItems[] = [
                'description' => "Zone 2 (Cricket): Cricket Pitch Turf Specialized Installation & Laser Seaming\\r\\nSub-base laser leveling, dual-edge high-tensile tape bonding with PU adhesive.",
                'hsn'         => '9954',
                'qty'         => 429,
                'unit'        => 'SQFT',
                'rate'        => 9.0,
                'amount'      => 3861.0,
                'specs'       => [
                    "Installation Scope: Precision center alignment of 429 SFT pitch",
                    "Bonding: Heavy-duty 350 GSM seam joint tape + all-weather PU adhesive"
                ],
                'gst_rate'    => 5.0,
            ];

            // 2. Outfield Sports Artificial Grass (4,071 SFT = 4,500 SFT minus 429 SFT pitch)
            $cricOutfieldRate = (float)($flooringProfile['rates'][$selectedPkg] ?? $flooringProfile['rates']['commercial'] ?? 45.0);
            $cricOutfieldAmt  = round(4071 * $cricOutfieldRate, 2);
            $docItems[] = [
                'description' => "Zone 2 (Cricket): Outfield Sports Flooring / Artificial Grass (40mm Landscape Sports Grass)\\r\\nHigh-density UV-resistant synthetic turf surface providing natural cushioning and traction across the 90 FT × 50 FT Outdoor Box Cricket Arena.",
                'hsn'         => '3918',
                'qty'         => 4071,
                'unit'        => 'SQFT',
                'rate'        => $cricOutfieldRate,
                'amount'      => $cricOutfieldAmt,
                'specs'       => [
                    "Coverage Area: 4,071 SFT outfield (4,500 SFT arena minus 429 SFT pitch)",
                    "Specification: 40mm Landscape Sports Grass",
                    "Pile Height: 40mm Pile Height"
                ],
                'gst_rate'    => 5.0,
            ];
            $docItems[] = [
                'description' => "Zone 2 (Cricket): Sports Grass Precision Laying, Seaming & Bonding\\r\\nPrecision roll-out, geotextile seam jointing, and perimeter adhesive bonding across 4,071 SFT.",
                'hsn'         => '9954',
                'qty'         => 4071,
                'unit'        => 'SQFT',
                'rate'        => 5.0,
                'amount'      => 20355.0,
                'specs'       => [
                    "Laying Area: 4,071 SFT outdoor outfield grass",
                    "Includes seaming tape and high-bonding adhesive application"
                ],
                'gst_rate'    => 5.0,
            ];

            // === ZONE 3: INDOOR SPORTS COMPLEX (11,000 SFT) — THE 6 REGULATION COURTS FLOORING ===
            // 4 BWF BADMINTON COURTS (4,590 SFT)
            $badmintonRate = 95.0; // Rs 95/SFT
            $badmintonAmt  = round(4590 * $badmintonRate, 2);
            $docItems[] = [
                'description' => "Zone 3 (Indoor): 4 BWF-Grade 4.5mm Multi-Layer Synthetic Vinyl Sports Surface (Four Regulation Badminton Courts)\\r\\nHigh-performance embossed anti-slip wear layer with high-density PVC foam backing for maximum tournament shock absorption, resilience, and traction across 4 Full Regulation Badminton Courts (Each 44 FT × 20 FT play court + safety run-offs = 1,147.5 SFT/court).",
                'hsn'         => '3918',
                'qty'         => 4590,
                'unit'        => 'SQFT',
                'rate'        => $badmintonRate,
                'amount'      => $badmintonAmt,
                'specs'       => [
                    "Total Badminton Playing Area: 4,590 SFT (4 BWF Regulation Courts: 44 FT × 20 FT play court + safety run-offs)",
                    "Specification: 4.5mm Multi-Layer PVC Sports Flooring with fiberglass stability layer & embossed wear surface",
                    "Shock Absorption: ≥25% force reduction complying with BWF Tournament Class standards"
                ],
                'gst_rate'    => 18.0,
            ];
            $docItems[] = [
                'description' => "Zone 3 (Indoor): Badminton Synthetic Vinyl Surface Precision Installation, Chemical Cold-Welding Seams & Leveling\\r\\nSpecialized chemical cold-welding joints, all-weather adhesive bonding, and perimeter anchor fastening across 4,590 SFT for 4 Badminton Courts.",
                'hsn'         => '9954',
                'qty'         => 4590,
                'unit'        => 'SQFT',
                'rate'        => 10.0,
                'amount'      => 45900.0,
                'specs'       => ["Precision installation of 4,590 SFT quad-court badminton synthetic sports surface with chemical cold-welded seams"],
                'gst_rate'    => 18.0,
            ];
            $docItems[] = [
                'description' => "Zone 3 (Indoor): Heavy-Duty Cast-Iron Movable Tournament Badminton Posts & BWF Regulation Nets (4 Sets)\\r\\n120 KG weighted cast-iron base movable posts with non-marking transport wheels, grooved brass pulley, ratchet tensioners, and official tournament-grade nylon nets with heavy white PVC headband.",
                'hsn'         => '9506',
                'qty'         => 4,
                'unit'        => 'SET',
                'rate'        => 14000.0,
                'amount'      => 56000.0,
                'specs'       => [
                    "Quantity: 4 Sets (1 Set per Regulation Badminton Court)",
                    "Posts: 120 KG weighted base with transport wheels, regulation 1.55M height",
                    "Nets: Official BWF regulation nylon mesh nets with steel tension cable"
                ],
                'gst_rate'    => 18.0,
            ];
            $docItems[] = [
                'description' => "Zone 3 (Indoor): BWF Official Tournament Court Line Markings (4 Badminton Courts)\\r\\nOfficial BWF line markings (40mm width) applied in durable two-component polyurethane wear-resistant paint for Singles & Doubles play on all 4 Courts.",
                'hsn'         => '9506',
                'qty'         => 4,
                'unit'        => 'SET',
                'rate'        => 6000.0,
                'amount'      => 24000.0,
                'specs'       => [
                    "Court 1 to 4: Full BWF Regulation Singles & Doubles Court Markings",
                    "Marking Paint: Heavy-duty 2-pack PU paint resistant to high player foot friction"
                ],
                'gst_rate'    => 18.0,
            ];

            // 2 REGULATION PICKLEBALL COURTS (1,936 SFT)
            $pickleballRate = 85.0; // Rs 85/SFT
            $pickleballAmt  = round(1936 * $pickleballRate, 2);
            $docItems[] = [
                'description' => "Zone 3 (Indoor): 8-Layer Heavy-Cushion Acrylic Synthetic Sports Flooring System (Two Regulation Pickleball Courts)\\r\\nMulti-layer 100% acrylic latex cushion surfacing system featuring acrylic resurfacer, flexible rubber micro-granule cushion base coats, textured color wear coats, and anti-glare finish for 2 Regulation Pickleball Courts (44 FT × 22 FT each).",
                'hsn'         => '3918',
                'qty'         => 1936,
                'unit'        => 'SQFT',
                'rate'        => $pickleballRate,
                'amount'      => $pickleballAmt,
                'specs'       => [
                    "Total Pickleball Area: 1,936 SFT (2 Regulation Courts: 44 FT × 22 FT each)",
                    "System: 8-Layer Cushion Acrylic (Resurfacer + Cushion Layers + Color Finish)",
                    "Traction: Anti-glare textured finish engineered for pickleball ball bounce & pivot safety"
                ],
                'gst_rate'    => 18.0,
            ];
            $docItems[] = [
                'description' => "Zone 3 (Indoor): Heavy-Duty Tournament Pickleball Posts, Internal Ratchet Tensioners & Regulation Nets (2 Sets)\\r\\n3.0\" heavy-gauge round steel posts with internal brass gear ratchet, post ground sleeves, and official 36\" height heavy nylon pickleball tournament net with center strap.",
                'hsn'         => '9506',
                'qty'         => 2,
                'unit'        => 'SET',
                'rate'        => 15000.0,
                'amount'      => 30000.0,
                'specs'       => [
                    "Quantity: 2 Sets (1 Set per Regulation Pickleball Court)",
                    "Posts: 3.0\" powder-coated steel posts with internal worm-gear winch",
                    "Nets: Heavy-duty 3.0mm braided polyethylene net with vinyl headband and fiberglass dowels"
                ],
                'gst_rate'    => 18.0,
            ];
            $docItems[] = [
                'description' => "Zone 3 (Indoor): Official Regulation Tournament Pickleball Line Markings (2 Courts)\\r\\nTwo-tone regulation court markings including 7 FT Non-Volley Kitchen Zone, centerline, baselines, and sidelines in high-visibility 100% acrylic textured paint.",
                'hsn'         => '9506',
                'qty'         => 2,
                'unit'        => 'SET',
                'rate'        => 5000.0,
                'amount'      => 10000.0,
                'specs'       => [
                    "Courts 1 & 2: Full Regulation Pickleball Markings (44×20 FT + Kitchen 7 FT non-volley zone)",
                    "Color: Crisp white tournament boundary lines with color-contrasted kitchen court"
                ],
                'gst_rate'    => 18.0,
            ];
        } elseif ($includeTurf && $flooringProfile['has_turf']) {"""

if flooring_target in calc_code:
    calc_code = calc_code.replace(flooring_target, flooring_105x170, 1)
    print("[PHP] 6 Courts Flooring injected into Section 6.")
elif "4 BWF-Grade 4.5mm Multi-Layer Synthetic Vinyl Sports Surface" in calc_code:
    print("[PHP] 6 Courts Flooring already injected.")
else:
    print("[PHP WARNING] flooring_target not found!")

# 1D. Section 4: Perimeter Netting for 105x170 (Cricket zone only: 6,900 SFT)
old_net_block = """        $sideNetTitle = $isTrainingNet
            ? ("Practice Lane 3-Sided Ball-Stop Netting (" . $sideNetProfile['name'] . ")")
            : ("Perimeter Side & Back Ball-Stop Netting System (" . $sideNetProfile['name'] . ")");
        $sideNetDesc = $isTrainingNet
            ? ($sideNetProfile['full_desc'] . ' enclosing 2 length walls & 1 back batsman wall (1 front width open for bowling). Reinforced with 8mm border ropes.')
            : ($sideNetProfile['full_desc'] . ', reinforced 8mm border ropes, 6mm PVC coated wire rope grid.');"""

netting_105x170 = """        if ($sportType === 'multisport_complex_105x170') {
            $sideNetSft = 6900.0;
            $sideNetAmt = round($sideNetSft * $sideNetUnitRate, 2);
            $sideNetTitle = "Zone 2 (Cricket): Perimeter Ball-Stop Netting System (" . $sideNetProfile['name'] . ")";
            $sideNetDesc  = $sideNetProfile['full_desc'] . " enclosing 3 outer perimeter sides of the 90 FT × 50 FT Outdoor Box Cricket Arena up to 30 FT height, reinforced with 8mm border ropes and wire rope suspension.";
        } else {
        $sideNetTitle = $isTrainingNet
            ? ("Practice Lane 3-Sided Ball-Stop Netting (" . $sideNetProfile['name'] . ")")
            : ("Perimeter Side & Back Ball-Stop Netting System (" . $sideNetProfile['name'] . ")");
        $sideNetDesc = $isTrainingNet
            ? ($sideNetProfile['full_desc'] . ' enclosing 2 length walls & 1 back batsman wall (1 front width open for bowling). Reinforced with 8mm border ropes.')
            : ($sideNetProfile['full_desc'] . ', reinforced 8mm border ropes, 6mm PVC coated wire rope grid.');
        }"""

if old_net_block in calc_code:
    calc_code = calc_code.replace(old_net_block, netting_105x170, 1)
    print("[PHP] Netting logic updated for 105x170.")
elif "Zone 2 (Cricket): Perimeter Ball-Stop Netting System" in calc_code:
    print("[PHP] Netting already updated.")
else:
    print("[PHP WARNING] old_net_block not found!")

# 1E. Section 8 Lighting for 105x170 (16 fixtures)
old_light_block = """        $lightAmt = round($numLights * $lightFixtureRate, 2);
        $docItems[] = [
            'description' => "High-Lumen Sports LED Floodlight Fixtures\\n" . ($isSmallCourt ? "{$numLights} Nos High-Output Sports LED Floodlights (IP66, 140 lm/W) mounted for practice lane lighting." : $pkg['items']['lighting']['desc']),
            'hsn'         => '9405',
            'qty'         => $numLights,
            'unit'        => 'NOS',
            'rate'        => $lightFixtureRate,
            'amount'      => $lightAmt,
            'specs'       => [
                "Fixtures: {$numLights} units mounted for uniform illumination",
                "Illumination: 200+ Lux average designed for high-speed night sports visibility"
            ],
            'gst_rate'    => 18.0,
        ];"""

new_light_block = """        if ($sportType === 'multisport_complex_105x170') {
            $numLights = 16;
            $lightAmt = round($numLights * $lightFixtureRate, 2);
            $docItems[] = [
                'description' => "Commercial Sports Arena LED Lighting System (16 High-Output Fixtures)\\r\\nDual-Zone Illumination: (1) 8 Nos 200W IP66 asymmetric stadium floodlights mounted on perimeter masts for 250+ lux outfield cricket coverage; (2) 8 Nos 150W IP65 low-glare wide-beam optical high-bay LEDs for 300+ lux tournament indoor badminton & pickleball court illumination.",
                'hsn'         => '9405',
                'qty'         => 16,
                'unit'        => 'NOS',
                'rate'        => $lightFixtureRate,
                'amount'      => $lightAmt,
                'specs'       => [
                    "Outdoor Cricket: 8 Nos 200W Stadium LEDs (IP66, 140 lm/W, 250+ lux avg)",
                    "Indoor Complex: 8 Nos 150W Anti-Glare High-Bays (IP65, 300+ lux tournament level)",
                    "Driver & Optics: Meanwell/Philips IP67 surge-protected drivers with 60°/90° asymmetric beam"
                ],
                'gst_rate'    => 18.0,
            ];
        } else {
        $lightAmt = round($numLights * $lightFixtureRate, 2);
        $docItems[] = [
            'description' => "High-Lumen Sports LED Floodlight Fixtures\\n" . ($isSmallCourt ? "{$numLights} Nos High-Output Sports LED Floodlights (IP66, 140 lm/W) mounted for practice lane lighting." : $pkg['items']['lighting']['desc']),
            'hsn'         => '9405',
            'qty'         => $numLights,
            'unit'        => 'NOS',
            'rate'        => $lightFixtureRate,
            'amount'      => $lightAmt,
            'specs'       => [
                "Fixtures: {$numLights} units mounted for uniform illumination",
                "Illumination: 200+ Lux average designed for high-speed night sports visibility"
            ],
            'gst_rate'    => 18.0,
        ];
        }"""

if old_light_block in calc_code:
    calc_code = calc_code.replace(old_light_block, new_light_block, 1)
    print("[PHP] Lighting updated for 105x170.")
elif "Commercial Sports Arena LED Lighting System (16 High-Output Fixtures)" in calc_code:
    print("[PHP] Lighting already updated.")
else:
    print("[PHP WARNING] old_light_block not found!")

# 1F. Section 10 Gates for 105x170 (4 Purpose-Built Gates)
gates_sec_target = """        // 10. Entry Gate & Accessories
        $gateCount = (int)$gateCount;
        if ($gateCount > 0 && $includeGates) {"""

gates_sec_105x170 = """        // 10. Entry Gate & Accessories
        $gateCount = (int)$gateCount;
        if ($gateCount > 0 && $includeGates) {
            if ($sportType === 'multisport_complex_105x170') {
                $gateCount = 4;
                $docItems[] = [
                    'description' => "Heavy-Duty Main Entrance & Dedicated Court Access Doors & Hardware (4 Purpose-Built Gates)\\r\\nComplete campus access package: (1) 12'×8' Heavy-Duty Main Front Sliding/Swing Gate on left 15 FT pathway; (2) 6'×8' Indoor Complex Main Double Door into Badminton & Pickleball arena; (3) 4'×7' Cricket Player Door from pathway; (4) 4'×7' Emergency Exit Door on rear wall. All doors equipped with industrial ball-bearing hinges, mortise cylinder deadbolts & protective padding.",
                    'hsn'         => '7308',
                    'qty'         => 4,
                    'unit'        => 'NOS',
                    'rate'        => 20750.0,
                    'amount'      => 83000.0,
                    'specs'       => [
                        "Main Gate: 12 FT × 8 FT Main Entrance Gate on left front boundary aligned with 15 FT pathway (Zero column collision)",
                        "Indoor Arena Door: 6 FT × 8 FT Commercial Double Door into Badminton & Pickleball lobby from 15 FT pathway",
                        "Cricket Player Door: 4 FT × 7 FT Player Access Door into Box Cricket Ground from 15 FT pathway",
                        "Emergency Exit: 4 FT × 7 FT Steel Emergency Egress Door on rear boundary wall",
                        "Hardware: Industrial cylinder locks, heavy-duty ball-bearing hinges, drop bolts & vinyl-wrapped foam safety padding"
                    ],
                    'gst_rate'    => 18.0,
                ];
            } else {"""

generic_gate_end = """            $docItems[] = [
                'description' => "Player Access Gate & Hardware (" . $gateCount . " " . ($gateCount > 1 ? "Gates" : "Gate") . ")\\n" . $gateDescText,
                'hsn'         => '7308',
                'qty'         => $gateCount,
                'unit'        => 'NOS',
                'rate'        => $gateRatePerUnit,
                'amount'      => $gateAmt,
                'specs'       => [
                    "Quantity: " . $gateCount . " Nos. Heavy-duty structural tubular steel access door(s)",
                    "Dimensions: Standard 4ft × 7ft player doors" . ($gateCount >= 3 ? " + 6ft wide equipment access gate" : ""),
                    "Hardware: Cylinder deadbolt lock, industrial ball-bearing hinges, latch & rubber weather-seals",
                    "Safety & Protection: High-density protective foam padding with heavy-duty vinyl wrapping"
                ],
                'gst_rate'    => 18.0,
            ];
        }"""

if gates_sec_target in calc_code and generic_gate_end in calc_code:
    calc_code = calc_code.replace(gates_sec_target, gates_sec_105x170, 1)
    calc_code = calc_code.replace(generic_gate_end, generic_gate_end[:-1] + "    }\n        }", 1)
    print("[PHP] Section 10 Gates logic injected.")
elif "Heavy-Duty Main Entrance & Dedicated Court Access Doors & Hardware (4 Purpose-Built Gates)" in calc_code:
    print("[PHP] Gates logic already injected.")
else:
    print("[PHP WARNING] gates_sec_target or generic_gate_end not found!")

# 1G. Section 11 Equipment & Markings for 105x170
equip_target = """        $docItems[] = [
            'description' => "Court Markings & Sports Fixtures (" . $sportProfile['name'] . ")\\n" . $sportProfile['equipment_desc'],
            'hsn'         => '9506',
            'qty'         => 1,
            'unit'        => 'SET',
            'rate'        => $equipAmt,
            'amount'      => $equipAmt,
            'specs'       => $equipSpecs,
            'gst_rate'    => 18.0,
        ];"""

equip_105x170 = """        if ($sportType === 'multisport_complex_105x170') {
            $docItems[] = [
                'description' => "Zone 2 (Cricket): Regulation Match Wickets, Crease Markings & Column Safety Padding\\r\\n66 FT official pitch crease markings (Bowling, Popping & Return creases at both ends), regulation spring-loaded match wickets, bails, boundary indicators, and high-density vinyl-wrapped column impact safety padding.",
                'hsn'         => '9506',
                'qty'         => 1,
                'unit'        => 'SET',
                'rate'        => 26000.0,
                'amount'      => 26000.0,
                'specs'       => [
                    "Pitch Markings: Official ICC/MCC 66 FT crease dimensions",
                    "Equipment: 2 Sets spring-return wooden match wickets with bails",
                    "Safety Padding: 2\" thick high-density PU foam pads wrapped in heavy PVC tarpaulin for playing zone columns"
                ],
                'gst_rate'    => 18.0,
            ];
        } else {
        $docItems[] = [
            'description' => "Court Markings & Sports Fixtures (" . $sportProfile['name'] . ")\\n" . $sportProfile['equipment_desc'],
            'hsn'         => '9506',
            'qty'         => 1,
            'unit'        => 'SET',
            'rate'        => $equipAmt,
            'amount'      => $equipAmt,
            'specs'       => $equipSpecs,
            'gst_rate'    => 18.0,
        ];
        }"""

if equip_target in calc_code:
    calc_code = calc_code.replace(equip_target, equip_105x170, 1)
    print("[PHP] Section 11 Equipment logic injected.")
elif "Zone 2 (Cricket): Regulation Match Wickets, Crease Markings & Column Safety Padding" in calc_code:
    print("[PHP] Equipment already injected.")
else:
    print("[PHP WARNING] equip_target not found!")

# Write updated PHP back
with open(calc_path, "w", encoding="utf-8") as f:
    f.write(calc_code)
print(f"[PHP] Written to {calc_path}")

# Sync to /admin/turnkey_calculator.php if it exists
admin_calc = "/var/www/gcm.enterprises/admin/turnkey_calculator.php"
if os.path.exists(admin_calc):
    shutil.copy2(calc_path, admin_calc)
    print(f"[PHP] Synced to {admin_calc}")

print("=== PHP PATCH COMPLETED ===")

# ==============================================================================
# PART 2: PATCH turnkey_court_viewer_v5.js
# ==============================================================================
js_path = "/var/www/gcm.enterprises/admin/assets/js/turnkey_court_viewer_v5.js"
with open(js_path, "r", encoding="utf-8") as f:
    js_code = f.read()

# 2A. Ground canvas texture: Single main entrance gate on left (pathway side), remove Gate 2 on right
canvas_gate_target = """        // Gate 1 & Gate 2 Markers
        ctx.fillStyle = '#f97316';
        ctx.fillRect(4, 200, 24, 240);
        ctx.fillRect(4, ch - 440, 24, 240);
        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold 24px Inter, sans-serif';
        ctx.save();
        ctx.translate(22, 320); ctx.rotate(-Math.PI / 2);
        ctx.fillText('GATE 1 (ENTRY)', 0, 0);
        ctx.restore();
        ctx.save();
        ctx.translate(22, ch - 320); ctx.rotate(-Math.PI / 2);
        ctx.fillText('GATE 2 (EXIT)', 0, 0);
        ctx.restore();"""

canvas_gate_replacement = """        // Single Main Campus Entrance Gate on Left (15 FT Pathway Side) — Zero Column Collision
        ctx.fillStyle = '#f97316';
        ctx.fillRect(4, 30, 24, 240); // Aligned with the 15 FT pathway (Y: 0 to 300 px)
        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold 22px Inter, sans-serif';
        ctx.save();
        ctx.translate(22, 150); ctx.rotate(-Math.PI / 2);
        ctx.fillText('MAIN ENTRANCE GATE', 0, 0);
        ctx.restore();"""

if canvas_gate_target in js_code:
    js_code = js_code.replace(canvas_gate_target, canvas_gate_replacement, 1)
    print("[JS] Ground canvas gate updated to single left entrance.")
elif "MAIN ENTRANCE GATE" in js_code and "ctx.fillRect(4, 30, 24, 240)" in js_code:
    print("[JS] Ground canvas gate already updated.")
else:
    print("[JS WARNING] canvas_gate_target not found!")

# 2B. 3D Gate rendering: Add dedicated 105x170 branch with zero column collision
three_gate_target = """      // 4. CRICKET SIDELINE PLAYER DOOR (4 FT × 7 FT)
      var sidelineGate = render3DTubularGate(
        -5.08,  // x = -5.08 (Zone 1, centered between frame 2 and frame 3)
        -halfW, // z = -3.0 (north sideline)
        0.4,    // 4 FT width
        0.7,    // 7 FT height
        false,  // facing along Z
        null,   // No floating signboard badge!
        0xeab308
      );
      scene.add(sidelineGate);
    } else if (showGates && resolvedGates.length > 0) {"""

three_gate_replacement = """      // 4. CRICKET SIDELINE PLAYER DOOR (4 FT × 7 FT)
      var sidelineGate = render3DTubularGate(
        -5.08,  // x = -5.08 (Zone 1, centered between frame 2 and frame 3)
        -halfW, // z = -3.0 (north sideline)
        0.4,    // 4 FT width
        0.7,    // 7 FT height
        false,  // facing along Z
        null,   // No floating signboard badge!
        0xeab308
      );
      scene.add(sidelineGate);
    } else if (sport === 'multisport_complex_105x170' || (L_ft === 170 && W_ft === 105)) {
      if (showGates) {
        // 1. MAIN ENTRANCE GATE (12 FT × 8 FT) on Left Pathway (Front Width Wall at X = -halfL)
        // Zero column collision: corner column is at z = -halfW (-5.25), gate is centered at z = -4.5 (spanning -5.1 to -3.9)
        var gateMain = render3DTubularGate(
          -halfL, // -8.5
          -4.5,   // centered on 15 FT pathway
          1.2,    // 12 FT width
          0.8,    // 8 FT height
          true,   // isFacingZ = true (across width in YZ plane)
          null,
          0xf97316
        );
        scene.add(gateMain);

        // 2. INDOOR ARENA MAIN DOUBLE DOOR (6 FT × 8 FT) from 15 FT Pathway (Transverse Facade at X = indoorStartRelX)
        // Zero column collision: corner column is at z = -halfW (-5.25), door is centered at z = -4.5 (spanning -4.8 to -4.2)
        var gateIndoor = render3DTubularGate(
          indoorStartRelX, // -1.7 (68 FT)
          -4.5,            // centered on 15 FT pathway
          0.6,             // 6 FT width
          0.8,             // 8 FT height
          true,            // isFacingZ = true (transverse wall in YZ plane)
          null,
          0x0284c7
        );
        scene.add(gateIndoor);

        // 3. CRICKET PLAYER DOOR (4 FT × 7 FT) from Pathway into Outdoor Cricket
        // Located on the netting line between pathway and cricket ground at z = -3.75 (-halfW + 1.5)
        // Positioned at cricMidX = -4.2 (X = 43 FT) — exactly mid-bay, completely clear of any columns
        var gateCricket = render3DTubularGate(
          -4.2,   // X = 43 FT
          -3.75,  // Z boundary between pathway and cricket
          0.4,    // 4 FT width
          0.7,    // 7 FT height
          false,  // isFacingZ = false (along length in XY plane)
          null,
          0x22c55e
        );
        scene.add(gateCricket);

        // 4. EMERGENCY EXIT DOOR (4 FT × 7 FT) on Rear Wall (X = +halfL)
        // Zero column collision: centered at z = 0.0, clear of portal columns
        var gateExit = render3DTubularGate(
          halfL, // +8.5 (170 FT)
          0.0,   // centered at Z = 0
          0.4,   // 4 FT width
          0.7,   // 7 FT height
          true,  // isFacingZ = true (rear width wall in YZ plane)
          null,
          0xeab308
        );
        scene.add(gateExit);
      }
    } else if (showGates && resolvedGates.length > 0) {"""

if three_gate_target in js_code:
    js_code = js_code.replace(three_gate_target, three_gate_replacement, 1)
    print("[JS] 3D dedicated gate branch added for 105x170.")
elif "gateMain = render3DTubularGate(" in js_code:
    print("[JS] 3D dedicated gate branch already exists.")
else:
    print("[JS WARNING] three_gate_target not found!")

# 2C. 2D Floor Plan: Replace Gate 1 & 2 with single left entrance, add indoor entrance, cricket door, emergency door
floorplan_gate_target = """    // Gate 1 & Gate 2 Markers at Front
    var gH_px = 20 * scale;
    svg.push('<rect x="' + (cx - 8) + '" y="' + (cy + 15 * scale) + '" width="12" height="' + gH_px + '" fill="#f97316" stroke="#c2410c" stroke-width="2"/>');
    svg.push('<text x="' + (cx - 14) + '" y="' + (cy + 25 * scale) + '" transform="rotate(-90 ' + (cx - 14) + ' ' + (cy + 25 * scale) + ')" text-anchor="middle" font-size="10" font-weight="900" fill="#c2410c">GATE 1 (ENTRY)</text>');

    svg.push('<rect x="' + (cx - 8) + '" y="' + (cy + 70 * scale) + '" width="12" height="' + gH_px + '" fill="#f97316" stroke="#c2410c" stroke-width="2"/>');
    svg.push('<text x="' + (cx - 14) + '" y="' + (cy + 80 * scale) + '" transform="rotate(-90 ' + (cx - 14) + ' ' + (cy + 80 * scale) + ')" text-anchor="middle" font-size="10" font-weight="900" fill="#c2410c">GATE 2 (EXIT)</text>');"""

floorplan_gate_replacement = """    // Single Main Campus Entrance Gate on Left Side (Aligned with 15 FT Pathway) — Zero Column Collision
    var gH_px = 12 * scale; // 12 FT gate
    svg.push('<rect x="' + (cx - 8) + '" y="' + (cy + 1.5 * scale) + '" width="12" height="' + gH_px + '" fill="#f97316" stroke="#c2410c" stroke-width="2"/>');
    svg.push('<text x="' + (cx - 14) + '" y="' + (cy + 7.5 * scale) + '" transform="rotate(-90 ' + (cx - 14) + ' ' + (cy + 7.5 * scale) + ')" text-anchor="middle" font-size="10" font-weight="900" fill="#c2410c">MAIN ENTRANCE GATE (12×8 FT)</text>');

    // Indoor Arena Main Access Door (from 15 FT Pathway at X = indoorX)
    var indDoorH = 6 * scale;
    svg.push('<rect x="' + (indoorX - 6) + '" y="' + (cy + 4.5 * scale) + '" width="12" height="' + indDoorH + '" fill="#0284c7" stroke="#0369a1" stroke-width="2"/>');
    svg.push('<text x="' + (indoorX - 10) + '" y="' + (cy + 7.5 * scale) + '" transform="rotate(-90 ' + (indoorX - 10) + ' ' + (cy + 7.5 * scale) + ')" text-anchor="middle" font-size="9" font-weight="900" fill="#0284c7">INDOOR ARENA ENTRY DOOR (6×8 FT)</text>');

    // Cricket Player Door (From Pathway into Outdoor Cricket Ground)
    svg.push('<rect x="' + (outdoorX + 23 * scale) + '" y="' + (cricY - 4) + '" width="' + (4 * scale) + '" height="8" fill="#22c55e" stroke="#15803d" stroke-width="1.5"/>');
    svg.push('<text x="' + (outdoorX + 25 * scale) + '" y="' + (cricY - 8) + '" text-anchor="middle" font-size="9" font-weight="900" fill="#15803d">CRICKET PLAYER DOOR (4×7 FT)</text>');

    // Emergency Exit Door on Rear Wall
    svg.push('<rect x="' + (indoorX + indoorW_px - 4) + '" y="' + (cy + campusH_px / 2 - 2 * scale) + '" width="8" height="' + (4 * scale) + '" fill="#eab308" stroke="#ca8a04" stroke-width="1.5"/>');
    svg.push('<text x="' + (indoorX + indoorW_px + 12) + '" y="' + (cy + campusH_px / 2) + '" transform="rotate(90 ' + (indoorX + indoorW_px + 12) + ' ' + (cy + campusH_px / 2) + ')" text-anchor="middle" font-size="9" font-weight="900" fill="#ca8a04">EMERGENCY EXIT (4×7 FT)</text>');"""

if floorplan_gate_target in js_code:
    js_code = js_code.replace(floorplan_gate_target, floorplan_gate_replacement, 1)
    print("[JS] 2D floor plan gates updated.")
elif "INDOOR ARENA ENTRY DOOR (6×8 FT)" in js_code:
    print("[JS] 2D floor plan gates already updated.")
else:
    print("[JS WARNING] floorplan_gate_target not found!")

# 2D. Side Elevation: Replace GATE 1 • 2 with MAIN ENTRANCE GATE
side_elev_target = 'svg.push(\'<text x="\' + cx + \'" y="\' + (groundY - 12 * scale - 6) + \'" font-size="9.5" font-weight="bold" fill="#c2410c">GATE 1 • 2</text>\');'
side_elev_replacement = 'svg.push(\'<text x="\' + cx + \'" y="\' + (groundY - 12 * scale - 6) + \'" font-size="9.5" font-weight="bold" fill="#c2410c">MAIN ENTRANCE GATE (12×8 FT)</text>\');'

if side_elev_target in js_code:
    js_code = js_code.replace(side_elev_target, side_elev_replacement, 1)
    print("[JS] Side elevation gate label updated.")
elif "MAIN ENTRANCE GATE (12×8 FT)" in js_code:
    print("[JS] Side elevation gate label already updated.")
else:
    print("[JS WARNING] side_elev_target not found!")

# Write updated JS back
with open(js_path, "w", encoding="utf-8") as f:
    f.write(js_code)
print(f"[JS] Written to {js_path}")

# Sync to /admin/assets/js/turnkey_court_viewer.js and /admin/turnkey_court_viewer.js
sync_targets = [
    "/var/www/gcm.enterprises/admin/assets/js/turnkey_court_viewer.js",
    "/var/www/gcm.enterprises/admin/turnkey_court_viewer.js"
]
for st in sync_targets:
    if os.path.exists(st):
        shutil.copy2(js_path, st)
        print(f"[JS] Synced to {st}")

print("=== JS PATCH COMPLETED ===")

# ==============================================================================
# PART 3: UPDATE EST-202609-065 IN documents.json
# ==============================================================================
doc_path = "/var/www/gcm.enterprises/admin/data/documents.json"
with open(doc_path, "r", encoding="utf-8") as f:
    docs = json.load(f)

doc_idx = None
for i, d in enumerate(docs):
    if d.get("id") == "EST-202609-065":
        doc_idx = i
        break

if doc_idx is None:
    print("[DOC ERROR] EST-202609-065 not found in documents.json!")
    sys.exit(1)

doc = docs[doc_idx]
print(f"[DOC] Found EST-202609-065 at index {doc_idx}")

# Define the exact 28 BOQ items
new_items = [
    # 1. Civil
    {
        "description": "Civil Ground Preparation & Base Slab (4\" M20 RCC Laser Screed Surface)\r\nSub-grade excavation, 150mm compacted WMM sub-base, 500-gauge vapor barrier, and 100mm laser-screed M20 RCC floor slab with vibratory compaction, perimeter expansion joints, and steel trowel smooth mirror finish across 17,850 SFT campus area.",
        "hsn": "9954",
        "qty": 17850,
        "unit": "SQFT",
        "rate": 70.0,
        "amount": 1249500.0,
        "specs": [
            "Total Footprint: 170 FT × 105 FT = 17,850 SFT",
            "Specification: 4\" (100mm) M20 Design Mix RCC with 8mm reinforcement mesh @ 150mm c/c",
            "Finish: Laser-screeded FM2 flat floor tolerance with power-trowel burnished surface"
        ],
        "gst_rate": 18.0
    },
    {
        "description": "Perimeter Drainage System\r\nEngineered perimeter storm-water RCC collection drain (250mm × 300mm) running along 550 RFT perimeter with heavy-duty galvanized steel grating covers and silt traps for zero water-logging.",
        "hsn": "9954",
        "qty": 550,
        "unit": "RFT",
        "rate": 85.0,
        "amount": 46750.0,
        "specs": [
            "Quantity: 550 Running Feet perimeter drainage channel",
            "Covers: Hot-dip galvanized steel trench gratings",
            "Gradient: 1:200 self-cleansing slope towards storm-water discharge pits"
        ],
        "gst_rate": 18.0
    },
    # 2. Structural
    {
        "description": "Structural MS/GI Columns & Horizontal Tier Framework (36 Ladder Columns)\r\nHeavy-duty engineered dual-upright 2-line ladder columns (40mm × 40mm square hollow section @ 2.0mm 14G wall thickness) with welded climbing rungs @ 1.5 FT c/c, base plate assemblies, M16 anchor bolts, and 6 horizontal peripheral bracing tiers across 35 FT vertical clear height.",
        "hsn": "7308",
        "qty": 36,
        "unit": "NOS",
        "rate": 33056.67,
        "amount": 1190040.12,
        "specs": [
            "Total Columns: 36 Nos Two-Line Ladder Type Columns (35 FT Clear Height)",
            "Member Specs: 40×40mm SHS (2.0mm 14G) uprights with welded ladder rungs @ 1.5 FT c/c",
            "Horizontal Ties: 6 Continuous Peripheral Tiers (3,300 RFT structural framing pipes)",
            "Base Foundations: 36 Nos 3.5 FT deep M20 concrete foundation pedestals with anchor bolts"
        ],
        "gst_rate": 18.0
    },
    # 3. Netting (Zone 2 Cricket)
    {
        "description": "Zone 2 (Cricket): Perimeter Ball-Stop Netting System (2.5mm UV-Stabilized Braided HDPE, 45mm Mesh)\r\nHigh-tenacity UV-stabilized 2.5mm braided virgin HDPE sports netting (45mm square mesh, 180 KG knot breaking strength) enclosing 3 outer perimeter sides of the 90 FT × 50 FT Outdoor Box Cricket Arena up to 30 FT height, reinforced with 8mm border ropes and wire rope suspension.",
        "hsn": "5608",
        "qty": 6900,
        "unit": "SQFT",
        "rate": 13.5,
        "amount": 93150.0,
        "specs": [
            "Enclosed Net Area: 230 RFT × 30 FT H = 6,900 SFT (3 outer perimeter sides of 90×50 cricket arena)",
            "Net Specification: 2.5mm UV-Stabilized High-Tenacity Braided HDPE (45mm Square Mesh)",
            "Tension Wire Rope: 6mm galvanized steel wire ropes with turnbuckles & wire clips"
        ],
        "gst_rate": 5.0
    },
    # 4. Roof & Side Cladding
    {
        "description": "Structural Secondary Roof Purlin Grid Framework\r\nEngineered cold-formed Z-purlins / RHS tubular purlins spaced @ 4.5 FT c/c across pitched portal rafters for corrugated metal sheet roof fastening.",
        "hsn": "7308",
        "qty": 4462.5,
        "unit": "RFT",
        "rate": 65.0,
        "amount": 290062.5,
        "specs": [
            "Quantity: 4,462.5 Running Feet of structural purlins",
            "Spacing: 4.5 FT center-to-center across pitched portal frames",
            "Coating: Anti-corrosion zinc chromate primer & weather-resistant enamel coat"
        ],
        "gst_rate": 18.0
    },
    {
        "description": "0.50mm Pre-Painted Galvalume (PPGL) Corrugated Profile Roof Sheets (100% Rainproof)\r\nTCT 0.50mm high-tensile 550 MPa AZ150 zinc-aluminum alloy color-coated corrugated profile sheet roofing covering the 11,000 SFT Indoor Sports Arena, 100% all-weather rainproof & hail-resistant covering including ridge caps, EPDM seal washers & self-drilling fasteners.",
        "hsn": "7210",
        "qty": 20527.5,
        "unit": "SQFT",
        "rate": 58.0,
        "amount": 1190595.0,
        "specs": [
            "Roof Surface Area: 20,527.5 SFT (including pitch slope & side corrugation overlap)",
            "Material: 0.50mm TCT Pre-Painted Galvalume (AZ150, 550 MPa Yield Strength)",
            "Crown Ridge Cap: 102 RFT 0.50mm matching color-coated ridge cap",
            "Fasteners: Class 3 self-drilling hex fasteners with vulcanized EPDM washers"
        ],
        "gst_rate": 18.0
    },
    {
        "description": "Heavy-Duty Eaves Gutters & Rainwater Downpipes System\r\n0.60mm Galvalume profile rainwater drainage gutters running on both eaves lengths with 110mm PVC storm downpipes for zero water spill.",
        "hsn": "7308",
        "qty": 340,
        "unit": "RFT",
        "rate": 120.0,
        "amount": 40800.0,
        "specs": [
            "Gutters: 340 RFT along both eaves boundaries",
            "Downpipes: 110mm heavy PVC downpipes with steel clamping brackets"
        ],
        "gst_rate": 18.0
    },
    {
        "description": "0.50mm Pre-Painted Galvalume (PPGL) Profile Side Wall Cladding Sheets (All 4 Sides)\r\nTCT 0.50mm high-tensile 550 MPa AZ150 zinc-aluminum alloy color-coated corrugated profile side wall sheeting covering all 4 perimeter walls of the Indoor PEB Sports Complex from 4.5 FT masonry plinth wall up to full 35 FT clear eave height, including corner flashings, drip trims & weather fasteners.",
        "hsn": "7210",
        "qty": 12995,
        "unit": "SQFT",
        "rate": 58.0,
        "amount": 753710.0,
        "specs": [
            "Cladding Coverage: 12,995 SFT (4 Sides: 2 × 102 FT lengths + 2 × 105 FT widths × 30.5 FT height + gable apexes)",
            "Material: 0.50mm TCT Pre-Painted Galvalume (AZ150, 550 MPa Yield Strength)",
            "Flashing & Trims: Corner flashings, eaves apron trims & self-drilling fasteners with vulcanized EPDM washers",
            "Weather Sealing: Silicon sealant along overlaps for complete wind & water tightness"
        ],
        "gst_rate": 18.0
    },
    {
        "description": "Structural Secondary Wall Girt & Runner Grid Framework\r\nEngineered cold-formed Z-girts / tubular steel horizontal runners mounted @ 5.0 FT c/c across all 4 perimeter walls of the Indoor PEB Sports Complex for corrugated side wall sheet fastening.",
        "hsn": "7308",
        "qty": 2484,
        "unit": "RFT",
        "rate": 55.0,
        "amount": 136620.0,
        "specs": [
            "Quantity: 2,484 Running Feet of structural wall girt runners",
            "Spacing: 5.0 FT vertical center-to-center tiers along all 4 walls",
            "Coating: Anti-corrosion zinc chromate primer & weather-resistant enamel coat"
        ],
        "gst_rate": 18.0
    },
    # 5. Flooring - Zone 2 Cricket
    {
        "description": "Zone 2 (Cricket): 15mm Curled Cricket Match Pitch Turf (Full Roll 6.5 FT × 82 FT [2M × 25M] = 540 SFT)\r\nDedicated high-density curled wicket turf strip with 3 FT structure safety offset for natural ball bounce.",
        "hsn": "5703",
        "qty": 540,
        "unit": "SQFT",
        "rate": 78.0,
        "amount": 42120.0,
        "specs": [
            "Roll Specification: Full Roll (6.5 FT × 82 FT = 540 SFT [2.0M × 25.0M])",
            "Installed Pitch Dimensions: 6.5 FT × 66 FT = 429 SFT laid at center of 90x50 cricket arena",
            "End Clearances: 3.0 FT clear setback from frame structures"
        ],
        "gst_rate": 5.0
    },
    {
        "description": "Zone 2 (Cricket): Cricket Pitch Turf Specialized Installation & Laser Seaming\r\nSub-base laser leveling, dual-edge high-tensile tape bonding with PU adhesive.",
        "hsn": "9954",
        "qty": 429,
        "unit": "SQFT",
        "rate": 9.0,
        "amount": 3861.0,
        "specs": [
            "Installation Scope: Precision center alignment of 429 SFT pitch",
            "Bonding: Heavy-duty 350 GSM seam joint tape + all-weather PU adhesive"
        ],
        "gst_rate": 5.0
    },
    {
        "description": "Zone 2 (Cricket): Outfield Sports Flooring / Artificial Grass (40mm Landscape Sports Grass)\r\nHigh-density UV-resistant synthetic turf surface providing natural cushioning and traction across the 90 FT × 50 FT Outdoor Box Cricket Arena.",
        "hsn": "3918",
        "qty": 4071,
        "unit": "SQFT",
        "rate": 45.0,
        "amount": 183195.0,
        "specs": [
            "Coverage Area: 4,071 SFT outfield (4,500 SFT arena minus 429 SFT pitch)",
            "Specification: 40mm Landscape Sports Grass",
            "Pile Height: 40mm Pile Height"
        ],
        "gst_rate": 5.0
    },
    {
        "description": "Zone 2 (Cricket): Sports Grass Precision Laying, Seaming & Bonding\r\nPrecision roll-out, geotextile seam jointing, and perimeter adhesive bonding across 4,071 SFT.",
        "hsn": "9954",
        "qty": 4071,
        "unit": "SQFT",
        "rate": 5.0,
        "amount": 20355.0,
        "specs": [
            "Laying Area: 4,071 SFT outdoor outfield grass",
            "Includes seaming tape and high-bonding adhesive application"
        ],
        "gst_rate": 5.0
    },
    # 6. Flooring - Zone 3 Indoor Badminton (4 Courts)
    {
        "description": "Zone 3 (Indoor): 4 BWF-Grade 4.5mm Multi-Layer Synthetic Vinyl Sports Surface (Four Regulation Badminton Courts)\r\nHigh-performance embossed anti-slip wear layer with high-density PVC foam backing for maximum tournament shock absorption, resilience, and traction across 4 Full Regulation Badminton Courts (Each 44 FT × 20 FT play court + safety run-offs = 1,147.5 SFT/court).",
        "hsn": "3918",
        "qty": 4590,
        "unit": "SQFT",
        "rate": 95.0,
        "amount": 436050.0,
        "specs": [
            "Total Badminton Playing Area: 4,590 SFT (4 BWF Regulation Courts: 44 FT × 20 FT play court + safety run-offs)",
            "Specification: 4.5mm Multi-Layer PVC Sports Flooring with fiberglass stability layer & embossed wear surface",
            "Shock Absorption: ≥25% force reduction complying with BWF Tournament Class standards"
        ],
        "gst_rate": 18.0
    },
    {
        "description": "Zone 3 (Indoor): Badminton Synthetic Vinyl Surface Precision Installation, Chemical Cold-Welding Seams & Leveling\r\nSpecialized chemical cold-welding joints, all-weather adhesive bonding, and perimeter anchor fastening across 4,590 SFT for 4 Badminton Courts.",
        "hsn": "9954",
        "qty": 4590,
        "unit": "SQFT",
        "rate": 10.0,
        "amount": 45900.0,
        "specs": [
            "Precision installation of 4,590 SFT quad-court badminton synthetic sports surface with chemical cold-welded seams"
        ],
        "gst_rate": 18.0
    },
    {
        "description": "Zone 3 (Indoor): Heavy-Duty Cast-Iron Movable Tournament Badminton Posts & BWF Regulation Nets (4 Sets)\r\n120 KG weighted cast-iron base movable posts with non-marking transport wheels, grooved brass pulley, ratchet tensioners, and official tournament-grade nylon nets with heavy white PVC headband.",
        "hsn": "9506",
        "qty": 4,
        "unit": "SET",
        "rate": 14000.0,
        "amount": 56000.0,
        "specs": [
            "Quantity: 4 Sets (1 Set per Regulation Badminton Court)",
            "Posts: 120 KG weighted base with transport wheels, regulation 1.55M height",
            "Nets: Official BWF regulation nylon mesh nets with steel tension cable"
        ],
        "gst_rate": 18.0
    },
    {
        "description": "Zone 3 (Indoor): BWF Official Tournament Court Line Markings (4 Badminton Courts)\r\nOfficial BWF line markings (40mm width) applied in durable two-component polyurethane wear-resistant paint for Singles & Doubles play on all 4 Courts.",
        "hsn": "9506",
        "qty": 4,
        "unit": "SET",
        "rate": 6000.0,
        "amount": 24000.0,
        "specs": [
            "Court 1 to 4: Full BWF Regulation Singles & Doubles Court Markings",
            "Marking Paint: Heavy-duty 2-pack PU paint resistant to high player foot friction"
        ],
        "gst_rate": 18.0
    },
    # 7. Flooring - Zone 3 Indoor Pickleball (2 Courts)
    {
        "description": "Zone 3 (Indoor): 8-Layer Heavy-Cushion Acrylic Synthetic Sports Flooring System (Two Regulation Pickleball Courts)\r\nMulti-layer 100% acrylic latex cushion surfacing system featuring acrylic resurfacer, flexible rubber micro-granule cushion base coats, textured color wear coats, and anti-glare finish for 2 Regulation Pickleball Courts (44 FT × 22 FT each).",
        "hsn": "3918",
        "qty": 1936,
        "unit": "SQFT",
        "rate": 85.0,
        "amount": 164560.0,
        "specs": [
            "Total Pickleball Area: 1,936 SFT (2 Regulation Courts: 44 FT × 22 FT each)",
            "System: 8-Layer Cushion Acrylic (Resurfacer + Cushion Layers + Color Finish)",
            "Traction: Anti-glare textured finish engineered for pickleball ball bounce & pivot safety"
        ],
        "gst_rate": 18.0
    },
    {
        "description": "Zone 3 (Indoor): Heavy-Duty Tournament Pickleball Posts, Internal Ratchet Tensioners & Regulation Nets (2 Sets)\r\n3.0\" heavy-gauge round steel posts with internal brass gear ratchet, post ground sleeves, and official 36\" height heavy nylon pickleball tournament net with center strap.",
        "hsn": "9506",
        "qty": 2,
        "unit": "SET",
        "rate": 15000.0,
        "amount": 30000.0,
        "specs": [
            "Quantity: 2 Sets (1 Set per Regulation Pickleball Court)",
            "Posts: 3.0\" powder-coated steel posts with internal worm-gear winch",
            "Nets: Heavy-duty 3.0mm braided polyethylene net with vinyl headband and fiberglass dowels"
        ],
        "gst_rate": 18.0
    },
    {
        "description": "Zone 3 (Indoor): Official Regulation Tournament Pickleball Line Markings (2 Courts)\r\nTwo-tone regulation court markings including 7 FT Non-Volley Kitchen Zone, centerline, baselines, and sidelines in high-visibility 100% acrylic textured paint.",
        "hsn": "9506",
        "qty": 2,
        "unit": "SET",
        "rate": 5000.0,
        "amount": 10000.0,
        "specs": [
            "Courts 1 & 2: Full Regulation Pickleball Markings (44×20 FT + Kitchen 7 FT non-volley zone)",
            "Color: Crisp white tournament boundary lines with color-contrasted kitchen court"
        ],
        "gst_rate": 18.0
    },
    # 8. Lighting & Electrical
    {
        "description": "Commercial Sports Arena LED Lighting System (16 High-Output Fixtures)\r\nDual-Zone Illumination: (1) 8 Nos 200W IP66 asymmetric stadium floodlights mounted on perimeter masts for 250+ lux outfield cricket coverage; (2) 8 Nos 150W IP65 low-glare wide-beam optical high-bay LEDs for 300+ lux tournament indoor badminton & pickleball court illumination.",
        "hsn": "9405",
        "qty": 16,
        "unit": "NOS",
        "rate": 9500.0,
        "amount": 152000.0,
        "specs": [
            "Outdoor Cricket: 8 Nos 200W Stadium LEDs (IP66, 140 lm/W, 250+ lux avg)",
            "Indoor Complex: 8 Nos 150W Anti-Glare High-Bays (IP65, 300+ lux tournament level)",
            "Driver & Optics: Meanwell/Philips IP67 surge-protected drivers with 60°/90° asymmetric beam"
        ],
        "gst_rate": 18.0
    },
    {
        "description": "Electrical Cabling, Control Panel & Earthing System\r\nHeavy-duty 4-core copper armored cable runs, Schneider/Havells MCB sub-distribution panel with timer relays, individual court switching, and 2 Nos copper plate chemical earthing pits for surge protection.",
        "hsn": "8537",
        "qty": 1,
        "unit": "LOT",
        "rate": 55000.0,
        "amount": 55000.0,
        "specs": [
            "Main Control Panel: 63A 4-Pole MCB distribution board with timer contactors",
            "Cables: Polycab/Havells 4-core 6 sq.mm armored copper subterranean cabling",
            "Earthing: 2 chemical pipe electrodes achieving < 2.0 Ohm earth resistance"
        ],
        "gst_rate": 18.0
    },
    # 9. Gates (4 Purpose-Built Gates)
    {
        "description": "Heavy-Duty Main Entrance & Dedicated Court Access Doors & Hardware (4 Purpose-Built Gates)\r\nComplete campus access package: (1) 12'×8' Heavy-Duty Main Front Sliding/Swing Gate on left 15 FT pathway; (2) 6'×8' Indoor Complex Main Double Door into Badminton & Pickleball arena; (3) 4'×7' Cricket Player Door from pathway; (4) 4'×7' Emergency Exit Door on rear wall. All doors equipped with industrial ball-bearing hinges, mortise cylinder deadbolts & protective padding.",
        "hsn": "7308",
        "qty": 4,
        "unit": "NOS",
        "rate": 20750.0,
        "amount": 83000.0,
        "specs": [
            "Main Gate: 12 FT × 8 FT Main Entrance Gate on left front boundary aligned with 15 FT pathway (Zero column collision)",
            "Indoor Arena Door: 6 FT × 8 FT Commercial Double Door into Badminton & Pickleball lobby from 15 FT pathway",
            "Cricket Player Door: 4 FT × 7 FT Player Access Door into Box Cricket Ground from 15 FT pathway",
            "Emergency Exit: 4 FT × 7 FT Steel Emergency Egress Door on rear boundary wall",
            "Hardware: Industrial cylinder locks, heavy-duty ball-bearing hinges, drop bolts & vinyl-wrapped foam safety padding"
        ],
        "gst_rate": 18.0
    },
    # 10. Cricket Equipment
    {
        "description": "Zone 2 (Cricket): Regulation Match Wickets, Crease Markings & Column Safety Padding\r\n66 FT official pitch crease markings (Bowling, Popping & Return creases at both ends), regulation spring-loaded match wickets, bails, boundary indicators, and high-density vinyl-wrapped column impact safety padding.",
        "hsn": "9506",
        "qty": 1,
        "unit": "SET",
        "rate": 26000.0,
        "amount": 26000.0,
        "specs": [
            "Pitch Markings: Official ICC/MCC 66 FT crease dimensions",
            "Equipment: 2 Sets spring-return wooden match wickets with bails",
            "Safety Padding: 2\" thick high-density PU foam pads wrapped in heavy PVC tarpaulin for playing zone columns"
        ],
        "gst_rate": 18.0
    },
    # 11. Services
    {
        "description": "Anti-Corrosion Primer & Weather-Shield Coating\r\nTwo coats of high-build red oxide zinc phosphate anti-corrosion primer followed by two finish coats of heavy-duty industrial synthetic enamel paint on all structural columns, purlins, wall girts, and trusses.",
        "hsn": "9954",
        "qty": 1,
        "unit": "LOT",
        "rate": 22000.0,
        "amount": 22000.0,
        "specs": [
            "Surface Prep: Manual wire brushing and chemical degreasing (SSPC-SP2 standard)",
            "Primer: 2 coats zinc chromate epoxy primer (60 microns DFT)",
            "Finish: 2 coats polyurethane enamel topcoat (50 microns DFT)"
        ],
        "gst_rate": 18.0
    },
    {
        "description": "Certified Turnkey Erection & Installation\r\nTurnkey assembly, alignment, structural portal hoisting, purlin & girt bolting, sheet screw fixing, tension rigging, turf installation, and commissioning by certified engineers.",
        "hsn": "9954",
        "qty": 1,
        "unit": "LOT",
        "rate": 45000.0,
        "amount": 45000.0,
        "specs": [
            "Installation Scope: Complete mechanical & structural assembly across 17,850 SFT campus",
            "Safety: Strict IS 456 / IS 800 compliance, safety harnesses, and crane rigging operations",
            "Quality: Laser line level checks and tension pull tests prior to project sign-off"
        ],
        "gst_rate": 18.0
    },
    {
        "description": "Freight, Material Transportation & Staging\r\nHeavy transport freight, transit insurance, multi-axle vehicle transportation, and site staging for structural steel, galvalume sheets, turf rolls, and sports flooring materials.",
        "hsn": "9965",
        "qty": 1,
        "unit": "LOT",
        "rate": 25000.0,
        "amount": 25000.0,
        "specs": [
            "Logistics: Direct freight delivery to site with transit insurance coverage",
            "Handling: Mechanized unloading, site material staging, and weather-protected storage"
        ],
        "gst_rate": 18.0
    },
    {
        "description": "Project Engineering Supervision & Quality Audits\r\nDedicated structural engineer supervision, laser calibration surveys, foundation testing, material batch verification, and official turnkey completion certification.",
        "hsn": "9983",
        "qty": 1,
        "unit": "LOT",
        "rate": 18000.0,
        "amount": 18000.0,
        "specs": [
            "Project Management: Full-time site engineer deployment throughout erection phase",
            "Audits: Foundation slump tests, torque checks on anchor bolts, seam shear checks",
            "Documentation: Turnkey handover dossiers, warranty certificates & maintenance manuals"
        ],
        "gst_rate": 18.0
    }
]

# Calculate exact totals
subtotal = 0.0
taxable_5 = 0.0
taxable_18 = 0.0

for it in new_items:
    amt = round(it["qty"] * it["rate"], 2)
    it["amount"] = amt
    subtotal += amt
    if it["gst_rate"] == 5.0:
        taxable_5 += amt
    else:
        taxable_18 += amt

subtotal = round(subtotal, 2)
taxable_5 = round(taxable_5, 2)
taxable_18 = round(taxable_18, 2)
gst_5 = round(taxable_5 * 0.05, 2)
gst_18 = round(taxable_18 * 0.18, 2)
total_tax = round(gst_5 + gst_18, 2)
grand_total = round(subtotal + total_tax, 2)

print(f"[DOC MATH] Subtotal:    Rs. {subtotal:,.2f}")
print(f"[DOC MATH] Taxable 5%:  Rs. {taxable_5:,.2f} -> GST 5%:  Rs. {gst_5:,.2f}")
print(f"[DOC MATH] Taxable 18%: Rs. {taxable_18:,.2f} -> GST 18%: Rs. {gst_18:,.2f}")
print(f"[DOC MATH] Grand Total: Rs. {grand_total:,.2f}")

# Update document fields
doc["items"] = new_items
doc["subtotal"] = subtotal
doc["taxable_amount"] = subtotal
doc["discount_percent"] = 0
doc["discount_amount"] = 0
doc["gst_type"] = "igst"
doc["gst_rate"] = 18.0
doc["igst"] = total_tax
doc["cgst"] = 0
doc["sgst"] = 0
doc["tax_breakdown"] = {
    "gst_5": {
        "rate": 5.0,
        "taxable": taxable_5,
        "amount": gst_5
    },
    "gst_18": {
        "rate": 18.0,
        "taxable": taxable_18,
        "amount": gst_18
    }
}
doc["total_tax"] = total_tax
doc["grand_total"] = grand_total
doc["amount_in_words"] = "Rupees Sixty Lakh Ninety-Four Thousand Six Hundred Eighteen and Fourteen Paise Only"

# Update turnkey_config
tk = doc.get("turnkey_config", {})
tk["gate_count"] = 4
tk["gates"] = [
    {
        "id": "gate_1",
        "name": "Main Campus Entrance Gate (Left Pathway)",
        "wall": "front_width",
        "x_ft": 0,
        "z_ft": -45.0,
        "width_ft": 12,
        "height_ft": 8,
        "type": "main_gate",
        "color": "#f97316",
        "position_ft": 7.5
    },
    {
        "id": "gate_2",
        "name": "Indoor Sports Arena Main Double Door",
        "wall": "transverse_indoor_facade",
        "x_ft": 68,
        "z_ft": -45.0,
        "width_ft": 6,
        "height_ft": 8,
        "type": "main_door",
        "color": "#0284c7",
        "position_ft": 68
    },
    {
        "id": "gate_3",
        "name": "Outdoor Cricket Arena Player Door",
        "wall": "cricket_pathway_partition",
        "x_ft": 43,
        "z_ft": -37.5,
        "width_ft": 4,
        "height_ft": 7,
        "type": "player_door",
        "color": "#22c55e",
        "position_ft": 43
    },
    {
        "id": "gate_4",
        "name": "Indoor Hall Emergency Exit Door",
        "wall": "back_width",
        "x_ft": 170,
        "z_ft": 0.0,
        "width_ft": 4,
        "height_ft": 7,
        "type": "emergency_door",
        "color": "#eab308",
        "position_ft": 52.5
    }
]

# Update specs inside turnkey_config
specs = tk.get("specs", {})
specs["wall_sheet_sft"] = 12995
specs["wall_girt_rft"] = 2484
specs["flooring_badminton_sft"] = 4590
specs["flooring_pickleball_sft"] = 1936
specs["turf_sft"] = 4071
specs["pitch_turf_sft"] = 540
specs["side_net_sft"] = 6900
specs["gate_count"] = 4
specs["total_lights"] = 16
tk["specs"] = specs
doc["turnkey_config"] = tk

docs[doc_idx] = doc

# Write updated documents.json back
with open(doc_path, "w", encoding="utf-8") as f:
    json.dump(docs, f, indent=4)
print(f"[DOC] Updated EST-202609-065 saved to {doc_path} successfully.")

print("=== ALL MASTER FIX OPERATIONS COMPLETED SUCCESSFULLY ===")
