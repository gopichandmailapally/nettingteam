import re

with open("/var/www/gcm.enterprises/admin/includes/turnkey_calculator.php", "r", encoding="utf-8") as f:
    content = f.read()

# 1. Update default_gates in sport profile to 4
content = re.sub(
    r"(multisport_complex_105x170\s*=>\s*\[(?:(?!\]\s*,).)*?default_gates\s*=>\s*)\d+",
    r"\g<1>4",
    content,
    flags=re.DOTALL
)

# 2. Side GI Sheets in Section 5 (Roof System)
side_gi_sheets_code = """            // 5D. 0.50mm Pre-Painted Galvalume (PPGL) Profile Side Wall Cladding Sheets (All 4 Sides)
            if ($sportType === multisport_complex_105x170 || !empty($specs[wall_sheet_sft]) || !empty($layers[side_sheets])) {
                $wallSheetSft = !empty($specs[wall_sheet_sft]) ? (float)$specs[wall_sheet_sft] : 12995.0; // 4 sides of 105x102 indoor PEB hall from 4.5 FT plinth to 35 FT eave
                $wallSheetRate = ($selectedPkg === essential) ? 52.0 : (($selectedPkg === premium_pro) ? 68.0 : 58.0);
                $wallSheetAmt  = round($wallSheetSft * $wallSheetRate, 2);
                $docItems[] = [
                    description => "0.50mm Pre-Painted Galvalume (PPGL) Corrugated Profile Metal Wall Cladding Sheets (All 4 Sides)\r\nTCT 0.50mm high-tensile 550 MPa AZ150 zinc-aluminum alloy color-coated corrugated profile side wall sheeting covering all 4 perimeter walls of the Indoor PEB Sports Complex from 4.5 FT masonry plinth wall up to full 35 FT clear eave height, including corner flashings, drip trims & weather fasteners.",
                    hsn         => 7210,
                    qty         => $wallSheetSft,
                    unit        => SQFT,
                    rate        => $wallSheetRate,
                    amount      => $wallSheetAmt,
                    specs       => [
                        "Cladding Coverage: " . number_format($wallSheetSft) . " SFT (4 Sides: 2 × 102 FT lengths + 2 × 105 FT widths × 30.5 FT height + gable apexes)",
                        "Material: 0.50mm TCT Pre-Painted Galvalume (AZ150, 550 MPa Yield Strength)",
                        "Flashing & Trims: Corner flashings, eaves apron trims & self-drilling fasteners with vulcanized EPDM washers",
                        "Weather Sealing: Silicon sealant along overlaps for complete wind & water tightness"
                    ],
                    gst_rate    => 18.0,
                ];

                // 5E. Secondary Structural Wall Girts / Runners Grid Framework
                $wallGirtRft = round(414 * 6, 2); // 414 RFT perimeter x 6 tiers = 2,484 RFT
                $wallGirtRate = 55.0; // Rs 55/RFT
                $wallGirtAmt = round($wallGirtRft * $wallGirtRate, 2);
                $docItems[] = [
                    description => "Structural Secondary Wall Girt & Runner Grid Framework\r\nEngineered cold-formed Z-girts / tubular steel horizontal runners mounted @ 5.0 FT c/c across all 4 perimeter walls of the Indoor PEB Sports Complex for corrugated side wall sheet fastening.",
                    hsn         => 7308,
                    qty         => $wallGirtRft,
                    unit        => RFT,
                    rate        => $wallGirtRate,
                    amount      => $wallGirtAmt,
                    specs       => [
                        "Quantity: " . number_format($wallGirtRft) . " Running Feet of structural wall girt runners",
                        "Spacing: 5.0 FT vertical center-to-center tiers along all 4 walls",
                        "Coating: Anti-corrosion zinc chromate primer & weather-resistant enamel coat"
                    ],
                    gst_rate    => 18.0,
                ];
            }
"""

anchor_gutter = "$gutterAmt,\n                specs       => [\n                    \"Gutters: \" . (int)$specs[gutter_rft] . \" RFT along both 185 FT eaves boundaries\",\n                    \"Downpipes: \" . (int)$specs[downpipe_rft] . \" RFT of 110mm heavy PVC downpipes with steel brackets\"\n                ],\n                gst_rate    => 18.0,\n            ];\n        }"

if anchor_gutter in content:
    content = content.replace(anchor_gutter, anchor_gutter[:-1] + "\n" + side_gi_sheets_code + "        }")
    print("Replaced anchor_gutter with side_gi_sheets_code")
else:
    print("anchor_gutter NOT found, trying CRLF...")
    anchor_gutter_crlf = anchor_gutter.replace("\n", "\r\n")
    if anchor_gutter_crlf in content:
        content = content.replace(anchor_gutter_crlf, anchor_gutter_crlf[:-1] + "\r\n" + side_gi_sheets_code.replace("\n", "\r\n") + "        }")
        print("Replaced anchor_gutter_crlf with side_gi_sheets_code")
    else:
        print("ERROR: Could not locate gutter anchor!")

# 3. Section 6 Flooring for multisport_complex_105x170
flooring_105x170_code = """        } elseif ($sportType === multisport_complex_105x170) {
            // === ZONE 2: OUTDOOR BOX CRICKET GROUND (90 FT × 50 FT = 4,500 SFT) ===
            // 1. Cricket Pitch Turf (Full Roll 540 SFT)
            $docItems[] = [
                description => "Zone 2 (Cricket): 15mm Curled Cricket Match Pitch Turf (Full Roll 6.5 FT × 82 FT [2M × 25M] = 540 SFT)\r\nDedicated high-density curled wicket turf strip with 3 FT structure safety offset for natural ball bounce.",
                hsn         => 5703,
                qty         => 540,
                unit        => SQFT,
                rate        => 78.0,
                amount      => 42120.0,
                specs       => [
                    "Roll Specification: Full Roll (6.5 FT × 82 FT = 540 SFT [2.0M × 25.0M])",
                    "Installed Pitch Dimensions: 6.5 FT × 66 FT = 429 SFT laid at center of 90x50 cricket arena",
                    "End Clearances: 3.0 FT clear setback from frame structures"
                ],
                gst_rate    => 5.0,
            ];
            $docItems[] = [
                description => "Zone 2 (Cricket): Cricket Pitch Turf Specialized Installation & Laser Seaming\r\nSub-base laser leveling, dual-edge high-tensile tape bonding with PU adhesive.",
                hsn         => 9954,
                qty         => 429,
                unit        => SQFT,
                rate        => 9.0,
                amount      => 3861.0,
                specs       => [
                    "Installation Scope: Precision center alignment of 429 SFT pitch",
                    "Bonding: Heavy-duty 350 GSM seam joint tape + all-weather PU adhesive"
                ],
                gst_rate    => 5.0,
            ];

            // 2. Outfield Sports Artificial Grass (4,071 SFT = 4,500 SFT minus 429 SFT pitch)
            $cricOutfieldRate = (float)($flooringProfile[rates][$selectedPkg] ?? $flooringProfile[rates][commercial] ?? 45.0);
            $cricOutfieldAmt  = round(4071 * $cricOutfieldRate, 2);
            $docItems[] = [
                description => "Zone 2 (Cricket): Outfield Sports Flooring / Artificial Grass (40mm Landscape Sports Grass)\r\nHigh-density UV-resistant synthetic turf surface providing natural cushioning and traction across the 90 FT × 50 FT Outdoor Box Cricket Arena.",
                hsn         => 3918,
                qty         => 4071,
                unit        => SQFT,
                rate        => $cricOutfieldRate,
                amount      => $cricOutfieldAmt,
                specs       => [
                    "Coverage Area: 4,071 SFT outfield (4,500 SFT arena minus 429 SFT pitch)",
                    "Specification: 40mm Landscape Sports Grass",
                    "Pile Height: 40mm Pile Height"
                ],
                gst_rate    => 5.0,
            ];
            $docItems[] = [
                description => "Zone 2 (Cricket): Sports Grass Precision Laying, Seaming & Bonding\r\nPrecision roll-out, geotextile seam jointing, and perimeter adhesive bonding across 4,071 SFT.",
                hsn         => 9954,
                qty         => 4071,
                unit        => SQFT,
                rate        => 5.0,
                amount      => 20355.0,
                specs       => [
                    "Laying Area: 4,071 SFT outdoor outfield grass",
                    "Includes seaming tape and high-bonding adhesive application"
                ],
                gst_rate    => 5.0,
            ];

            // === ZONE 3: INDOOR SPORTS COMPLEX (11,000 SFT) — THE 6 REGULATION COURTS FLOORING ===
            // 4 BWF BADMINTON COURTS (4,590 SFT)
            $badmintonRate = 95.0; // Rs 95/SFT
            $badmintonAmt  = round(4590 * $badmintonRate, 2);
            $docItems[] = [
                description => "Zone 3 (Indoor): 4 BWF-Grade 4.5mm Multi-Layer Synthetic Vinyl Sports Surface (Four Regulation Badminton Courts)\r\nHigh-performance embossed anti-slip wear layer with high-density PVC foam backing for maximum tournament shock absorption, resilience, and traction across 4 Full Regulation Badminton Courts (Each 44 FT × 20 FT play court + safety run-offs = 1,147.5 SFT/court).",
                hsn         => 3918,
                qty         => 4590,
                unit        => SQFT,
                rate        => $badmintonRate,
                amount      => $badmintonAmt,
                specs       => [
                    "Total Badminton Playing Area: 4,590 SFT (4 BWF Regulation Courts: 44 FT × 20 FT play court + safety run-offs)",
                    "Specification: 4.5mm Multi-Layer PVC Sports Flooring with fiberglass stability layer & embossed wear surface",
                    "Shock Absorption: ≥25% force reduction complying with BWF Tournament Class standards"
                ],
                gst_rate    => 18.0,
            ];
            $docItems[] = [
                description => "Zone 3 (Indoor): Badminton Synthetic Vinyl Surface Precision Installation, Chemical Cold-Welding Seams & Leveling\r\nSpecialized chemical cold-welding joints, all-weather adhesive bonding, and perimeter anchor fastening across 4,590 SFT for 4 Badminton Courts.",
                hsn         => 9954,
                qty         => 4590,
                unit        => SQFT,
                rate        => 10.0,
                amount      => 45900.0,
                specs       => ["Precision installation of 4,590 SFT quad-court badminton synthetic sports surface with chemical cold-welded seams"],
                gst_rate    => 18.0,
            ];
            $docItems[] = [
                description => "Zone 3 (Indoor): Heavy-Duty Cast-Iron Movable Tournament Badminton Posts & BWF Regulation Nets (4 Sets)\r\n120 KG weighted cast-iron base movable posts with non-marking transport wheels, grooved brass pulley, ratchet tensioners, and official tournament-grade nylon nets with heavy white PVC headband.",
                hsn         => 9506,
                qty         => 4,
                unit        => SET,
                rate        => 14000.0,
                amount      => 56000.0,
                specs       => [
                    "Quantity: 4 Sets (1 Set per Regulation Badminton Court)",
                    "Posts: 120 KG weighted base with transport wheels, regulation 1.55M height",
                    "Nets: Official BWF regulation nylon mesh nets with steel tension cable"
                ],
                gst_rate    => 18.0,
            ];
            $docItems[] = [
                description => "Zone 3 (Indoor): BWF Official Tournament Court Line Markings (4 Badminton Courts)\r\nOfficial BWF line markings (40mm width) applied in durable two-component polyurethane wear-resistant paint for Singles & Doubles play on all 4 Courts.",
                hsn         => 9506,
                qty         => 4,
                unit        => SET,
                rate        => 6000.0,
                amount      => 24000.0,
                specs       => [
                    "Court 1 to 4: Full BWF Regulation Singles & Doubles Court Markings",
                    "Marking Paint: Heavy-duty 2-pack PU paint resistant to high player foot friction"
                ],
                gst_rate    => 18.0,
            ];

            // 2 REGULATION PICKLEBALL COURTS (1,936 SFT)
            $pickleballRate = 85.0; // Rs 85/SFT
            $pickleballAmt  = round(1936 * $pickleballRate, 2);
            $docItems[] = [
                description => "Zone 3 (Indoor): 8-Layer Heavy-Cushion Acrylic Synthetic Sports Flooring System (Two Regulation Pickleball Courts)\r\nMulti-layer 100% acrylic latex cushion surfacing system featuring acrylic resurfacer, flexible rubber micro-granule cushion base coats, textured color wear coats, and anti-glare finish for 2 Regulation Pickleball Courts (44 FT × 22 FT each).",
                hsn         => 3918,
                qty         => 1936,
                unit        => SQFT,
                rate        => $pickleballRate,
                amount      => $pickleballAmt,
                specs       => [
                    "Total Pickleball Area: 1,936 SFT (2 Regulation Courts: 44 FT × 22 FT each)",
                    "System: 8-Layer Cushion Acrylic (Resurfacer + Cushion Layers + Color Finish)",
                    "Traction: Anti-glare textured finish engineered for pickleball ball bounce & pivot safety"
                ],
                gst_rate    => 18.0,
            ];
            $docItems[] = [
                description => "Zone 3 (Indoor): Heavy-Duty Tournament Pickleball Posts, Internal Ratchet Tensioners & Regulation Nets (2 Sets)\r\n3.0\" heavy-gauge round steel posts with internal brass gear ratchet, post ground sleeves, and official 36\" height heavy nylon pickleball tournament net with center strap.",
                hsn         => 9506,
                qty         => 2,
                unit        => SET,
                rate        => 15000.0,
                amount      => 30000.0,
                specs       => [
                    "Quantity: 2 Sets (1 Set per Regulation Pickleball Court)",
                    "Posts: 3.0\" powder-coated steel posts with internal worm-gear winch",
                    "Nets: Heavy-duty 3.0mm braided polyethylene net with vinyl headband and fiberglass dowels"
                ],
                gst_rate    => 18.0,
            ];
            $docItems[] = [
                description => "Zone 3 (Indoor): Official Regulation Tournament Pickleball Line Markings (2 Courts)\r\nTwo-tone regulation court markings including 7 FT Non-Volley Kitchen Zone, centerline, baselines, and sidelines in high-visibility 100% acrylic textured paint.",
                hsn         => 9506,
                qty         => 2,
                unit        => SET,
                rate        => 5000.0,
                amount      => 10000.0,
                specs       => [
                    "Courts 1 & 2: Full Regulation Pickleball Markings (44×20 FT + Kitchen 7 FT non-volley zone)",
                    "Color: Crisp white tournament boundary lines with color-contrasted kitchen court"
                ],
                gst_rate    => 18.0,
            ];
"""

anchor_flooring = "// Zone C: Open Utility Area (Empty space requires no flooring scope)\n        } elseif ($includeTurf && $flooringProfile[has_turf]) {"
if anchor_flooring in content:
    content = content.replace(anchor_flooring, "// Zone C: Open Utility Area (Empty space requires no flooring scope)\n" + flooring_105x170_code + "        } elseif ($includeTurf && $flooringProfile[has_turf]) {")
    print("Replaced anchor_flooring with flooring_105x170_code")
else:
    print("anchor_flooring NOT found, trying CRLF...")
    anchor_flooring_crlf = anchor_flooring.replace("\n", "\r\n")
    if anchor_flooring_crlf in content:
        content = content.replace(anchor_flooring_crlf, "// Zone C: Open Utility Area (Empty space requires no flooring scope)\r\n" + flooring_105x170_code.replace("\n", "\r\n") + "        } elseif ($includeTurf && $flooringProfile[has_turf]) {")
        print("Replaced anchor_flooring_crlf with flooring_105x170_code")
    else:
        print("ERROR: Could not locate flooring anchor!")

# 4. Section 8 Gates for multisport_complex_105x170
gates_105x170_code = """        if ($includeGates) {
            $gateCount = (int)($specs[gate_count] ?? 1);
            if ($sportType === multisport_complex_105x170) {
                $gateCount = 4;
                $docItems[] = [
                    description => "Heavy-Duty Main Entrance & Dedicated Court Access Doors & Hardware (4 Purpose-Built Gates)\r\nComplete campus access package: (1) 12×8 Heavy-Duty Main Front Sliding/Swing Gate on left 15 FT pathway; (2) 6×8 Indoor Complex Main Double Door into Badminton & Pickleball arena; (3) 4×7 Cricket Player Door from pathway; (4) 4×7 Emergency Exit Door on rear wall. All doors equipped with industrial ball-bearing hinges, mortise cylinder deadbolts & protective padding.",
                    hsn         => 7308,
                    qty         => 4,
                    unit        => NOS,
                    rate        => 20750.0,
                    amount      => 83000.0,
                    specs       => [
                        "Main Gate: 12 FT × 8 FT Main Entrance Gate on left front boundary aligned with 15 FT pathway (Zero column collision)",
                        "Indoor Arena Door: 6 FT × 8 FT Commercial Double Door into Badminton & Pickleball lobby from 15 FT pathway",
                        "Cricket Player Door: 4 FT × 7 FT Player Access Door into Box Cricket Ground from 15 FT pathway",
                        "Emergency Exit: 4 FT × 7 FT Steel Emergency Egress Door on rear boundary wall",
                        "Hardware: Industrial cylinder locks, heavy-duty ball-bearing hinges, drop bolts & vinyl-wrapped foam safety padding"
                    ],
                    gst_rate    => 18.0,
                ];
            } else {"""

anchor_gates = "        if ($includeGates) {\n            $gateCount = (int)($specs[gate_count] ?? 1);"
if anchor_gates in content:
    content = content.replace(anchor_gates, gates_105x170_code)
    # also need to close the else
    print("Replaced anchor_gates with gates_105x170_code")
else:
    anchor_gates_crlf = anchor_gates.replace("\n", "\r\n")
    if anchor_gates_crlf in content:
        content = content.replace(anchor_gates_crlf, gates_105x170_code.replace("\n", "\r\n"))
        print("Replaced anchor_gates_crlf with gates_105x170_code")
    else:
        print("ERROR: Could not locate gates anchor!")

# Write test file
with open("/var/www/gcm.enterprises/admin/includes/turnkey_calculator_test.php", "w", encoding="utf-8") as f:
    f.write(content)

print("Test file written successfully.")
