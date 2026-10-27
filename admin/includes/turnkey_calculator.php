<?php

if (!function_exists('getTurnkeyDefaultGates')) {
    function getTurnkeyDefaultGates($L, $W, $H, $gateCount = 1, $sportType = 'box_cricket') {
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

        $gates = [];
        if ($gateCount >= 1) {
            $gates[] = [
                'id'        => 'gate_1',
                'name'      => 'Sideline Player Door',
                'wall'      => 'left_length',
                'x_ft'      => round($L * 0.82, 1),
                'z_ft'      => -round($W / 2, 1),
                'width_ft'  => 4,
                'height_ft' => 7,
                'type'      => 'player_door',
                'color'     => '#22c55e'
            ];
        }
        if ($gateCount >= 2) {
            $gates[] = [
                'id'        => 'gate_2',
                'name'      => 'Opposite Player Door',
                'wall'      => 'right_length',
                'x_ft'      => round($L * 0.18, 1),
                'z_ft'      => round($W / 2, 1),
                'width_ft'  => 4,
                'height_ft' => 7,
                'type'      => 'player_door',
                'color'     => '#a855f7'
            ];
        }
        if ($gateCount >= 3) {
            $gates[] = [
                'id'        => 'gate_3',
                'name'      => 'Equipment Access Gate',
                'wall'      => 'front_width',
                'x_ft'      => round($L, 1),
                'z_ft'      => 0,
                'width_ft'  => 6,
                'height_ft' => 7,
                'type'      => 'equipment_gate',
                'color'     => '#f97316'
            ];
        }
        if ($gateCount >= 4) {
            $gates[] = [
                'id'        => 'gate_4',
                'name'      => 'Rear Access Gate',
                'wall'      => 'back_width',
                'x_ft'      => 0,
                'z_ft'      => 0,
                'width_ft'  => 4,
                'height_ft' => 7,
                'type'      => 'rear_gate',
                'color'     => '#eab308'
            ];
        }
        return $gates;
    }
}

if (!function_exists('getTurnkeyTierHeights')) {
    function getTurnkeyTierHeights($H) {
        $H = (float)$H;
        if ($H <= 12.0) {
            // 10 FT / 12 FT height: Only bottom runner (0.5 FT) and top perimeter beam (H FT) - no intermediate pipes!
            return [0.5, $H];
        }
        $tierHeights = [0.5, $H];
        $span = $H - 0.5;
        $numBays = max(1, (int)round($span / 7.5));
        $bayH = $span / $numBays;
        for ($i = 1; $i < $numBays; $i++) {
            $tierHeights[] = round(0.5 + $i * $bayH, 1);
        }
        sort($tierHeights);
        return array_values(array_unique($tierHeights));
    }
}

/**
 * Turnkey Sports Court Calculation & Engineering BOQ Engine
 * NettingTeam (nettingteam.com)
 * Complete multi-sport support:
 * - Cricket: Box Cricket, Circular Arena, Oval Ground
 * - Football / Futsal: 5-a-side, 7-a-side, 9-a-side, 11-a-side, Futsal
 * - Racket & Ball Sports: Badminton (Single & Multi), Basketball (Half & Full), Tennis, Pickleball (Single & Multi)
 * - Multi-Sport / Hybrid: Half Cricket + Half Football, Futsal + Basketball
 * Zero hardcoded rates, dynamic BOQ scaling, multi-geometry (Rectangular, Circular, Oval).
 */

if (!function_exists('getTurnkeySportProfiles')) {
    function getTurnkeySportProfiles() {
        return [
            'multisport_4in1_100x60' => [
                'key'              => 'multisport_4in1_100x60',
                'name'             => '4-in-1 Multi-Sport Ground (100×60 / 30 FT H — Cricket + Football + Volleyball + Badminton)',
                'badge'            => '★ 🏏⚽🏐🏸 4-in-1 Multi-Sport (100×60)',
                'category'         => 'multi_sport',
                'shape'            => 'rectangular',
                'default_l'        => 100,
                'default_w'        => 60,
                'default_h'        => 30,
                'default_pitch'    => 41,
                'default_turf'     => 'sports_30mm_non_infill',
                'default_civil'    => 'compacted_wmm',
                'default_side_net' => 'braided_2_5mm_45mm',
                'default_top_net'  => 'open_top',
                'default_gates'    => 1,
                'equipment_desc'   => 'Complete 4-in-1 Multi-Sport Arena: 15mm Curled Cricket Center Pitch, Steel Mini Football Goals (1 Pair), Volleyball Upright Posts & Net, Movable Badminton Posts & Net, and Inlaid Court Line Markings.',
            ],
            'outdoor_multi_sports_120x60' => [
                'key'              => 'outdoor_multi_sports_120x60',
                'name'             => 'Outdoor Multi Sports Court (120×60 / 15 FT H - Badminton + Basketball + 8-Layer Acrylic)',
                'badge'            => '🏸🏀 Badminton + Basketball (120×60)',
                'category'         => 'multi_sport',
                'shape'            => 'rectangular',
                'default_l'        => 120,
                'default_w'        => 60,
                'default_h'        => 15,
                'default_pitch'    => 0,
                'default_turf'     => 'synthetic_acrylic_8layer',
                'default_civil'    => 'asphalt_bitumen',
                'default_side_net' => 'braided_2_5mm_40mm',
                'default_top_net'  => 'open_top',
                'default_gates'    => 1,
                'equipment_desc'   => 'Proposal for Supply and Installation of Outdoor Multi Sports Flooring Amenities (120ft × 60ft): Open Saucer Drain (120 Rft), Asphalt Subbase Construction (7,200 Sqft), 15ft MS Square Pipe Fabrication Fencing (5,400 Sqft), RUSSEA™ 2.5mm / 40mm Green Nylon Netting (5,824 Sqft), 8-Layer Synthetic Acrylic Sports Flooring (7,200 Sqft), Fixed Tennis Poles with Tournament Net (1 Pair), and Fixed Basketball Poles with 20mm Imported Acrylic Board & Olympian Rings (1 Pair).',
            ],
            'multisport_complex_105x170' => [
                'key'              => 'multisport_complex_105x170',
                'name'             => '16,000 SFT Mega Sports Complex (105×170 / 35 FT H - 11,000 SFT Indoor Hall + 4,500 SFT Outdoor Cricket + 1,890 SFT Parking)',
                'badge'            => '🏛️ Mega Sports Campus (105×170)',
                'category'         => 'multi_sport',
                'shape'            => 'rectangular',
                'default_l'        => 170,
                'default_w'        => 105,
                'default_h'        => 35,
                'default_pitch'    => 66,
                'default_turf'     => 'sports_50mm_12000_dtex',
                'default_civil'    => 'compacted_wmm',
                'default_side_net' => 'braided_2_5mm_45mm',
                'default_top_net'  => 'twisted_1_5mm_35mm',
                'default_gates'    => 2,
                'equipment_desc'   => '16,000 SFT Turnkey Sports Campus (170L × 105W × 35H): (1) 11,000 SFT PEB Indoor Sports Hall under 0.50mm PPGI GI Sheet Roof with 50mm Under-Deck Thermal Foil Insulation & 35 FT Clear Apex Height, housing 4 BWF Badminton Courts (4.5mm Vinyl) and 2 Regulation Pickleball Courts (8-Layer Acrylic Cushion) + Admin/Reception/Washrooms Block; (2) 4,500 SFT (90×50 FT) Outdoor Box Cricket Ground with 66×10 FT Concrete Pitch Slab, 15mm Curled Pitch Turf, 50mm Infill Outfield Turf & Russea™ UV Nylon Ball-Stop Netting (OPEN-TOP / NO GI ROOF); (3) 1,890 SFT Front Parking (105×18 FT) & 15 FT Pathway with Dual Main Gates.',
                'zones'            => [
                    'indoor_badminton' => ['name' => 'Indoor Sports Hall (4 Badminton + 2 Pickleball)', 'length' => 102, 'width' => 105, 'area_sft' => 10710, 'flooring' => '4.5mm BWF Vinyl + 8-Layer Acrylic Cushion', 'roof' => '0.50mm PPGI GI Sheet Roof + 50mm Insulation'],
                    'outdoor_cricket'  => ['name' => 'Outdoor Box Cricket Arena (Open-Top)', 'length' => 90, 'width' => 50, 'area_sft' => 4500, 'flooring' => '15mm Curled Pitch + 50mm Outfield Turf', 'roof' => 'Open-Top (No GI Roof)'],
                    'parking_pathway'  => ['name' => 'Front Vehicle Parking & 15 FT Pathway', 'length' => 105, 'width' => 18, 'area_sft' => 2640, 'flooring' => '60mm M30 Heavy-Duty Paver Blocks', 'gates' => 2]
                ]
            ],
'multisport_arena_complex_185x60' => [
                'key'              => 'multisport_arena_complex_185x60',
                'name'             => "Multi-Sport Complex (185×60 / 30 FT H - 100×60 Cricket + 45×60 2 Badminton Courts + 40×60 Remaining Space)",
                'badge'            => '🏟️ Multi-Sport Arena (185×60)',
                'category'         => 'multi_sport',
                'shape'            => 'rectangular',
                'default_l'        => 185,
                'default_w'        => 60,
                'default_h'        => 30,
                'default_pitch'    => 83,
                'default_turf'     => 'sports_50mm_12000_dtex',
                'default_civil'    => 'compacted_wmm',
                'default_side_net' => 'braided_2_5mm_45mm',
                'default_top_net'  => 'twisted_1_5mm_35mm',
                'default_gates'    => 2,
                'equipment_desc'   => 'Complete Multi-Zone Commercial Arena Complex (185L × 60W × 30H = 11,100 SFT): (1) Zone A: 100 FT × 60 FT Box Cricket Ground (6,000 SFT) with 83 FT × 6.5 FT Curled Match Pitch Turf, 5,460 SFT 40mm Landscape Sports Grass (Zero Infill), and 1.5mm Twisted HDPE (40mm Square Mesh) Overhead Top Netting on Wire Cable Grid (Zero Top Steel Rafters / Zero Roof GI Sheets); (2) Zone B: 45 FT × 60 FT Badminton Complex (2,700 SFT) housing Two Regulation Courts of 45×30 FT each with 4.5mm BWF Synthetic Sports Flooring, 2 Sets Heavy Movable Tournament Posts & BWF Nets, PU Line Markings, 100% All-Weather Rainproof 0.50mm PPGL Profile Metal Roof (3,105 SFT) on 4 Gable Trusses, and 0.50mm PPGL Corrugated Metal Wall Cladding on All 4 Sides up to 30 FT Clear Eave Height (6,300 SQFT); (3) Zone C: 40 FT × 60 FT Front Vehicle Parking & Approach (2,400 SFT) with Heavy-Duty 60mm M30 Interlocking Pavers (Zero Metal Structure / Located Outside the 145×60 FT Steel Framework).',
                'zones'            => [
                    'cricket'   => ['name' => 'Cricket Ground Arena', 'length' => 100, 'width' => 60, 'area_sft' => 6000, 'flooring' => '15mm Pitch + 50mm Turf Outfield'],
                    'badminton' => ['name' => 'Badminton Complex (Two 45×30 Courts)', 'length' => 45, 'width' => 60, 'courts' => 2, 'court_w' => 30, 'area_sft' => 2700, 'flooring' => '4.5mm BWF Synthetic Sports Surface', 'dual_layer_shade' => true],
                    'utility'   => ['name' => 'Remaining Utility & Amenity Space', 'length' => 40, 'width' => 60, 'area_sft' => 2400, 'flooring' => 'Covered Utility Base / Pavers'],
                ]
            ],
            'hybrid_cricket_football' => [
                'key'              => 'hybrid_cricket_football',
                'name'             => 'Hybrid Arena (Half Cricket + Half Football 120×60 / 25 FT H)',
                'badge'            => '🏏⚽ Cricket + Football (120×60)',
                'category'         => 'multi_sport',
                'shape'            => 'rectangular',
                'default_l'        => 120,
                'default_w'        => 60,
                'default_h'        => 25,
                'default_pitch'    => 66,
                'default_turf'     => 'sports_50mm_12000_dtex',
                'default_civil'    => 'compacted_wmm',
                'default_side_net' => 'braided_2_5mm_45mm',
                'default_top_net'  => 'twisted_1_5mm_35mm',
                'default_gates'    => 2,
                'equipment_desc'   => 'Dual-Sport Hybrid Arena (120L × 60W = 7,200 SFT): Full-size 50mm Football Arena with removable center cricket batting pitch turf roll (66 FT) and official Futsal goal posts with back nets.',
            ],
            'hybrid_badminton_pickleball' => [
                'key'              => 'hybrid_badminton_pickleball',
                'name'             => 'Racket Sports Hub (2 Badminton + 2 Pickleball Courts 90×45 / 25 FT H)',
                'badge'            => '🏸🥒 Badminton + Pickleball',
                'category'         => 'multi_sport',
                'shape'            => 'rectangular',
                'default_l'        => 90,
                'default_w'        => 45,
                'default_h'        => 25,
                'default_pitch'    => 0,
                'default_turf'     => 'court_acrylic_8layer',
                'default_civil'    => 'pcc_m15_4inch_broom',
                'default_side_net' => 'braided_2_5mm_45mm',
                'default_top_net'  => 'twisted_1_5mm_35mm',
                'default_gates'    => 1,
                'equipment_desc'   => 'Racket Sports Pavilion (90L × 45W = 4,050 SFT): 8-Layer Cushion Acrylic Surface with multi-colored line markings for 2 Badminton Courts and 2 Pickleball Courts, portable tournament posts, and 75% perimeter anti-glare shade net curtains.',
            ],
            'hybrid_tennis_basketball' => [
                'key'              => 'hybrid_tennis_basketball',
                'name'             => 'Hardcourt Complex (Tennis + Basketball 120×60 / 25 FT H)',
                'badge'            => '🎾🏀 Tennis + Basketball',
                'category'         => 'multi_sport',
                'shape'            => 'rectangular',
                'default_l'        => 120,
                'default_w'        => 60,
                'default_h'        => 25,
                'default_pitch'    => 0,
                'default_turf'     => 'court_acrylic_8layer',
                'default_civil'    => 'pcc_m15_4inch_broom',
                'default_side_net' => 'braided_2_5mm_45mm',
                'default_top_net'  => 'open_top',
                'default_gates'    => 2,
                'equipment_desc'   => 'Heavy-Duty Acrylic Hardcourt Complex: Full regulation Tennis Court with centered Basketball Key & Goal Hoops, heavy spring-loaded glass backboards, and 100mm chain link / braided netting surround.',
            ],
            // --- 🏏 Cricket Training Nets (3 Sides + Top Overhead Netting, Open Front for Bowling) ---
            'cricket_training_net_50x10' => [
                'key'              => 'cricket_training_net_50x10',
                'name'             => 'Cricket Training Net (Single Lane 50×10 / 10\'H)',
                'badge'            => '🏏 Training Net (50×10)',
                'category'         => 'cricket_training',
                'shape'            => 'training_net',
                'is_training_net'  => true,
                'default_l'        => 50,
                'default_w'        => 10,
                'default_h'        => 10,
                'default_pitch'    => 44,
                'default_turf'     => 'non_infill_turf',
                'default_civil'    => 'pcc_m15_4inch_broom',
                'default_side_net' => 'braided_2_5mm_45mm',
                'default_top_net'  => 'twisted_1_5mm_35mm',
                'default_gates'    => 0,
                'equipment_desc'   => 'Single Practice Net Lane (50L × 10W × 10H - 3 Sides + Top Enclosed, Open Front for Bowling): 44 FT Cricket Batting Pitch (6.5 FT width, 15mm Curled Turf), Heavy-Duty Batsman End Baffle Net / Archery Backstop Curtain (10 FT x 9 FT, 3.0mm Braided HDPE), official bowling & popping creases, and heavy-duty spring-loaded wicket stumps.',
            ],
            'cricket_training_net_60x50' => [
                'key'              => 'cricket_training_net_60x50',
                'name'             => 'Cricket Training Net Complex (Multi-Lane 60×50 / 12\'H)',
                'badge'            => '🏏 Training Net Complex (60×50)',
                'category'         => 'cricket_training',
                'shape'            => 'training_net',
                'is_training_net'  => true,
                'default_l'        => 60,
                'default_w'        => 50,
                'default_h'        => 12,
                'default_pitch'    => 55,
                'default_turf'     => 'non_infill_turf',
                'default_civil'    => 'pcc_m15_4inch_broom',
                'default_side_net' => 'braided_2_5mm_45mm',
                'default_top_net'  => 'twisted_1_5mm_35mm',
                'default_gates'    => 0,
                'equipment_desc'   => 'Multi-Lane Cricket Practice Facility (60L × 50W × 12H - 3 Sides + Top Enclosed, Open Front for Bowling): Multiple parallel batting lanes with full-length divider netting curtains, 15mm Curled Turf batting pitches, heavy-duty batsman baffle impact backstop sheets, and regulation crease markings.',
            ],
            'cricket_training_net_75x12' => [
                'key'              => 'cricket_training_net_75x12',
                'name'             => 'Cricket Training Net Pro (Match Lane 75×12 / 15\'H)',
                'badge'            => '🏏 Training Net Pro (75×12)',
                'category'         => 'cricket_training',
                'shape'            => 'training_net',
                'is_training_net'  => true,
                'default_l'        => 75,
                'default_w'        => 12,
                'default_h'        => 15,
                'default_pitch'    => 66,
                'default_turf'     => 'non_infill_turf',
                'default_civil'    => 'pcc_m15_4inch_broom',
                'default_side_net' => 'braided_2_5mm_45mm',
                'default_top_net'  => 'twisted_1_5mm_35mm',
                'default_gates'    => 0,
                'equipment_desc'   => 'Professional Full-Length Practice Net Lane (75L × 12W × 15H - 3 Sides + Top Enclosed, Open Front for Bowling): Regulation 66 FT Cricket Match Pitch (6.5 FT width, 15mm Curled Turf), Heavy Batsman End Baffle Net (12 FT x 10 FT, 3.0mm Braided HDPE), official bowling & popping crease markings, and heavy-duty spring-loaded wicket stumps.',
            ],
            'cricket_training_net' => [
                'key'              => 'cricket_training_net',
                'name'             => 'Cricket Training Net (Open Front Bowling Lane)',
                'badge'            => '🏏 Training Net (1 Lane)',
                'category'         => 'cricket_training',
                'shape'            => 'training_net',
                'is_training_net'  => true,
                'default_l'        => 70,
                'default_w'        => 15,
                'default_h'        => 14,
                'default_pitch'    => 66,
                'default_turf'     => 'non_infill_turf',
                'default_civil'    => 'pcc_m15_4inch_broom',
                'default_side_net' => 'braided_2_5mm_45mm',
                'default_top_net'  => 'twisted_1_5mm_35mm',
                'default_gates'    => 0,
                'equipment_desc'   => 'Single Practice Net Lane (3 Sides + Top Enclosed, Open Front for Bowling): Full-length Cricket Batting Pitch (6.5 FT x 66 FT, 15mm Curled Turf), Heavy-Duty Batsman End Baffle Net / Archery Backstop Curtain (12 FT x 10 FT, 3.0mm Braided HDPE) for high-impact ball absorption, official bowling & popping creases, and heavy-duty spring-loaded wicket stumps.',
            ],
            'training_net' => [
                'key'              => 'training_net',
                'name'             => 'Cricket Training Net (Open Front Bowling Lane)',
                'badge'            => '🏏 Training Net (1 Lane)',
                'category'         => 'cricket_training',
                'shape'            => 'training_net',
                'is_training_net'  => true,
                'default_l'        => 70,
                'default_w'        => 15,
                'default_h'        => 14,
                'default_pitch'    => 66,
                'default_turf'     => 'non_infill_turf',
                'default_civil'    => 'pcc_m15_4inch_broom',
                'default_side_net' => 'braided_2_5mm_45mm',
                'default_top_net'  => 'twisted_1_5mm_35mm',
                'default_gates'    => 0,
                'equipment_desc'   => 'Single Practice Net Lane (3 Sides + Top Enclosed, Open Front for Bowling): Full-length Cricket Batting Pitch (6.5 FT x 66 FT, 15mm Curled Turf), Heavy-Duty Batsman End Baffle Net / Archery Backstop Curtain (12 FT x 10 FT, 3.0mm Braided HDPE) for high-impact ball absorption, official bowling & popping creases, and heavy-duty spring-loaded wicket stumps.',
            ],
            'training_net_2lane' => [
                'key'              => 'training_net_2lane',
                'name'             => 'Cricket Training Net (2-Lane Practice Cage)',
                'badge'            => '🏏 Training Net (2 Lanes)',
                'category'         => 'cricket_training',
                'shape'            => 'training_net',
                'is_training_net'  => true,
                'default_l'        => 70,
                'default_w'        => 24,
                'default_h'        => 14,
                'default_pitch'    => 66,
                'default_turf'     => 'non_infill_turf',
                'default_civil'    => 'pcc_m15_4inch_broom',
                'default_side_net' => 'braided_2_5mm_45mm',
                'default_top_net'  => 'twisted_1_5mm_35mm',
                'default_gates'    => 0,
                'equipment_desc'   => 'Double-Lane Cricket Training Cage (3 Sides + Top Enclosed, Open Front for Bowling): 2 Parallel Cricket Batting Pitches (6.5 FT x 66 FT each), Heavy-Duty Central Divider Netting Curtain with tension cable hardware, 2 Sets Heavy Batsman Baffle Impact Nets (12 FT x 10 FT each), and official crease markings.',
            ],
            'training_net_3lane' => [
                'key'              => 'training_net_3lane',
                'name'             => 'Cricket Training Net (3-Lane Academy Complex)',
                'badge'            => '🏏 Training Net (3 Lanes)',
                'category'         => 'cricket_training',
                'shape'            => 'training_net',
                'is_training_net'  => true,
                'default_l'        => 80,
                'default_w'        => 36,
                'default_h'        => 16,
                'default_pitch'    => 66,
                'default_turf'     => 'non_infill_turf',
                'default_civil'    => 'pcc_m15_4inch_broom',
                'default_side_net' => 'braided_2_5mm_45mm',
                'default_top_net'  => 'twisted_1_5mm_35mm',
                'default_gates'    => 0,
                'equipment_desc'   => 'Triple-Lane Cricket Training Academy Cage (3 Sides + Top Enclosed, Open Front for Bowling): 3 Parallel Cricket Batting Pitches (6.5 FT x 66 FT each), 2 Full-Length Heavy Divider Netting Curtains with tension cable rigging, 3 Sets Heavy Batsman Baffle Backstop Sheets (12 FT x 10 FT each), and official crease markings.',
            ],

            'box_cricket' => [
                'key'              => 'box_cricket',
                'name'             => 'Box Cricket Turf',
                'badge'            => '🏏 Box Cricket',
                'category'         => 'cricket',
                'shape'            => 'rectangular',
                'default_l'        => 100,
                'default_w'        => 50,
                'default_h'        => 25,
                'default_pitch'    => 66,
                'default_turf'     => 'non_infill_turf',
                'default_civil'    => 'compacted_wmm',
                'default_side_net' => 'braided_2_5mm_45mm',
                'default_top_net'  => 'twisted_1_5mm_35mm',
                'equipment_desc'   => 'Center Batting Pitch Turf (6.5 FT x 66 FT, 15mm UV monofilament) with permanent white bowling & popping crease markings, boundary lines, and 1 set regulation PVC stumps.',
            ],
            'cricket_circular' => [
                'key'              => 'cricket_circular',
                'name'             => 'Circular Cricket Arena',
                'badge'            => '🏏 Circular Cricket',
                'category'         => 'cricket',
                'shape'            => 'circular',
                'default_l'        => 140,
                'default_w'        => 140,
                'default_h'        => 30,
                'default_pitch'    => 66,
                'default_turf'     => 'non_infill_turf',
                'default_civil'    => 'compacted_wmm',
                'default_side_net' => 'braided_2_5mm_45mm',
                'default_top_net'  => 'twisted_1_5mm_35mm',
                'equipment_desc'   => 'Central Match Pitch (6.5 FT x 66 FT), 360-degree perimeter boundary markings, 30-yard inner circle markers, and heavy perimeter ball-stop netting.',
            ],
            'cricket_oval' => [
                'key'              => 'cricket_oval',
                'name'             => 'Oval Cricket Ground',
                'badge'            => '🏏 Oval Cricket',
                'category'         => 'cricket',
                'shape'            => 'oval',
                'default_l'        => 180,
                'default_w'        => 120,
                'default_h'        => 30,
                'default_pitch'    => 66,
                'default_turf'     => 'non_infill_turf',
                'default_civil'    => 'compacted_wmm',
                'default_side_net' => 'braided_2_5mm_45mm',
                'default_top_net'  => 'twisted_1_5mm_35mm',
                'equipment_desc'   => 'Central Match Pitch (6.5 FT x 66 FT), oval boundary rope inlay, inner circle markers, and multi-pole curved structural enclosure.',
            ],
            'football_5a' => [
                'key'              => 'football_5a',
                'name'             => '5-a-Side Football Turf',
                'badge'            => '⚽ Football 5v5',
                'category'         => 'football',
                'shape'            => 'rectangular',
                'default_l'        => 100,
                'default_w'        => 60,
                'default_h'        => 25,
                'default_pitch'    => 0,
                'default_turf'     => 'infill_turf',
                'default_civil'    => 'compacted_wmm',
                'default_side_net' => 'braided_3_5mm_100mm',
                'default_top_net'  => 'braided_2_5mm_100mm',
                'equipment_desc'   => '1 Pair (2 Nos - Both Opposing Ends) Steel Goal Posts (10 FT wide x 6.5 FT high) with 4.0mm heavy braided HDPE hexagonal nets, ground anchor sleeves, corner flags, and white turf boundary line markings.',
            ],
            'football_6a' => [
                'key'              => 'football_6a',
                'name'             => '6-a-Side Football Turf',
                'badge'            => '⚽ Football 6v6',
                'category'         => 'football',
                'shape'            => 'rectangular',
                'default_l'        => 110,
                'default_w'        => 70,
                'default_h'        => 25,
                'default_pitch'    => 0,
                'default_turf'     => 'infill_turf',
                'default_civil'    => 'compacted_wmm',
                'default_side_net' => 'braided_3_5mm_100mm',
                'default_top_net'  => 'braided_2_5mm_100mm',
                'equipment_desc'   => '1 Pair (2 Nos - Both Opposing Ends) Heavy Steel Goal Posts (12 FT wide x 6.5 FT high) with 4.0mm braided nets, ground sleeves, 6-a-side penalty box, penalty spot, and white boundary line markings.',
            ],
            'football_7a' => [
                'key'              => 'football_7a',
                'name'             => '7-a-Side Football Arena',
                'badge'            => '⚽ Football 7v7',
                'category'         => 'football',
                'shape'            => 'rectangular',
                'default_l'        => 160,
                'default_w'        => 100,
                'default_h'        => 30,
                'default_pitch'    => 0,
                'default_turf'     => 'infill_turf',
                'default_civil'    => 'compacted_wmm',
                'default_side_net' => 'braided_3_5mm_100mm',
                'default_top_net'  => 'braided_2_5mm_100mm',
                'equipment_desc'   => '1 Pair (2 Nos - Both Opposing Ends) Heavy Steel Goal Posts (16 FT wide x 7 FT high) with 4.0mm braided nets, anchor sleeves, official 7-a-side penalty box, penalty spot, center circle, and touchline markings.',
            ],
            'football_8a' => [
                'key'              => 'football_8a',
                'name'             => '8-a-Side Football Arena (Standard 16k SFT)',
                'badge'            => '⚽ Football 8v8 (16k)',
                'category'         => 'football',
                'shape'            => 'rectangular',
                'default_l'        => 160,
                'default_w'        => 100,
                'default_h'        => 30,
                'default_pitch'    => 0,
                'default_turf'     => 'infill_turf',
                'default_civil'    => 'compacted_wmm',
                'default_side_net' => 'braided_3_5mm_100mm',
                'default_top_net'  => 'braided_2_5mm_100mm',
                'equipment_desc'   => '1 Pair (2 Nos - Both Opposing Ends) Regulation 8-a-Side Steel Goal Posts (18 FT wide x 7 FT high) with 4.0mm heavy braided netting, ground anchor sleeves, penalty box, center circle, and penalty spot.',
            ],
            'football_8a_compact' => [
                'key'              => 'football_8a_compact',
                'name'             => '8-a-Side Football Arena (Compact 12k SFT)',
                'badge'            => '⚽ Football 8v8 (12k)',
                'category'         => 'football',
                'shape'            => 'rectangular',
                'default_l'        => 150,
                'default_w'        => 80,
                'default_h'        => 28,
                'default_pitch'    => 0,
                'default_turf'     => 'infill_turf',
                'default_civil'    => 'compacted_wmm',
                'default_side_net' => 'braided_3_5mm_100mm',
                'default_top_net'  => 'braided_2_5mm_100mm',
                'equipment_desc'   => '1 Pair (2 Nos - Both Opposing Ends) 8-a-Side Steel Goal Posts (16–18 FT wide x 7 FT high) with 4.0mm braided nets, ground anchor sleeves, penalty box, penalty spot, and corner arcs.',
            ],
            'football_9a' => [
                'key'              => 'football_9a',
                'name'             => '9-a-Side Football Ground',
                'badge'            => '⚽ Football 9v9',
                'category'         => 'football',
                'shape'            => 'rectangular',
                'default_l'        => 220,
                'default_w'        => 140,
                'default_h'        => 35,
                'default_pitch'    => 0,
                'default_turf'     => 'infill_turf',
                'default_civil'    => 'compacted_wmm',
                'default_side_net' => 'braided_4_0mm_120mm',
                'default_top_net'  => 'open_top',
                'equipment_desc'   => '1 Pair (2 Nos - Both Opposing Ends) Steel Goal Posts (21 FT wide x 7 FT high) with heavy 4.0mm braided netting, ground sleeves, complete 9-a-side pitch markings, penalty box, and corner flags.',
            ],
            'football_10a' => [
                'key'              => 'football_10a',
                'name'             => '10-a-Side Football Ground',
                'badge'            => '⚽ Football 10v10',
                'category'         => 'football',
                'shape'            => 'rectangular',
                'default_l'        => 220,
                'default_w'        => 140,
                'default_h'        => 35,
                'default_pitch'    => 0,
                'default_turf'     => 'infill_turf',
                'default_civil'    => 'compacted_wmm',
                'default_side_net' => 'braided_4_0mm_120mm',
                'default_top_net'  => 'open_top',
                'equipment_desc'   => '1 Pair (2 Nos - Both Opposing Ends) Steel Goal Posts (21 FT wide x 7.5 FT high) with 4.0mm braided nets, ground sleeves, full 10-a-side pitch markings, penalty box, penalty arc, and corner flagpoles.',
            ],
            'football_11a' => [
                'key'              => 'football_11a',
                'name'             => '11-a-Side Full Football Stadium',
                'badge'            => '⚽ Football 11v11',
                'category'         => 'football',
                'shape'            => 'rectangular',
                'default_l'        => 320,
                'default_w'        => 200,
                'default_h'        => 35,
                'default_pitch'    => 0,
                'default_turf'     => 'infill_turf',
                'default_civil'    => 'compacted_wmm',
                'default_side_net' => 'braided_4_0mm_120mm',
                'default_top_net'  => 'open_top',
                'equipment_desc'   => '1 Pair (2 Nos - Both Opposing Ends) Official FIFA Regulation Steel Goal Posts (24 FT wide x 8 FT high x 6 FT deep) with 4.0mm braided nets, ground sleeves, 18-yard box, 6-yard box, center circle, and 4 corner flagpoles.',
            ],
            'futsal' => [
                'key'              => 'futsal',
                'name'             => 'Official Futsal Court',
                'badge'            => '🥅 Futsal Court',
                'category'         => 'football',
                'shape'            => 'rectangular',
                'default_l'        => 130,
                'default_w'        => 75,
                'default_h'        => 25,
                'default_pitch'    => 0,
                'default_turf'     => 'infill_turf',
                'default_civil'    => 'compacted_wmm',
                'default_side_net' => 'braided_3_5mm_100mm',
                'default_top_net'  => 'braided_2_5mm_100mm',
                'equipment_desc'   => '1 Pair (2 Nos - Both Opposing Ends) Official Futsal Goal Posts (3m wide x 2m high / 9.8 FT x 6.5 FT) with 4.0mm braided nets, ground sleeves, 6m penalty arc, 10m second penalty spot, and corner arcs.',
            ],
            'badminton' => [
                'key'              => 'badminton',
                'name'             => 'Badminton Single Court',
                'badge'            => '🏸 Badminton Single',
                'category'         => 'racket',
                'shape'            => 'rectangular',
                'default_l'        => 60,
                'default_w'        => 30,
                'default_h'        => 25,
                'default_pitch'    => 0,
                'default_turf'     => 'bwf_vinyl_mat',
                'default_civil'    => 'concrete_pcc_3inch',
                'default_side_net' => 'braided_2_5mm_45mm',
                'default_top_net'  => 'twisted_1_5mm_35mm',
                'equipment_desc'   => '1 Set Heavy-Duty BWF Regulation Badminton Posts with tension pulley winder, 20 FT tournament-grade 1.5mm double-stitched nylon net with heavy white top headband, and official BWF court lines.',
            ],
            'badminton_multi' => [
                'key'              => 'badminton_multi',
                'name'             => 'Badminton Multi-Court Complex (2-4 Courts)',
                'badge'            => '🏸 Badminton Complex',
                'category'         => 'racket',
                'shape'            => 'rectangular',
                'default_l'        => 80,
                'default_w'        => 60,
                'default_h'        => 28,
                'default_pitch'    => 0,
                'default_turf'     => 'bwf_vinyl_mat',
                'default_civil'    => 'concrete_pcc_3inch',
                'default_side_net' => 'braided_2_5mm_45mm',
                'default_top_net'  => 'twisted_1_5mm_35mm',
                'equipment_desc'   => 'Multi-court setup with BWF Regulation Badminton Posts & tournament nets per court, high-tension court divider nets with quick-release steel cables, and shadowless glare-free LED lighting layout.',
            ],
            'basketball_half' => [
                'key'              => 'basketball_half',
                'name'             => 'Basketball Half Court',
                'badge'            => '🏀 Basketball Half',
                'category'         => 'ball',
                'shape'            => 'rectangular',
                'default_l'        => 47,
                'default_w'        => 50,
                'default_h'        => 25,
                'default_pitch'    => 0,
                'default_turf'     => 'synthetic_acrylic_8layer',
                'default_civil'    => 'concrete_pcc_3inch',
                'default_side_net' => 'braided_2_5mm_45mm',
                'default_top_net'  => 'twisted_1_5mm_35mm',
                'equipment_desc'   => '1 No Heavy-duty In-ground / Surface flange Basketball Goal System with 54"x36-inch Toughened Glass/Acrylic Backboard, Heavy spring breakaway rim, anti-whip net, and standard half-court FIBA/NBA markings (Key, Free Throw Line, 3-Point Arc).',
            ],
            'basketball_full' => [
                'key'              => 'basketball_full',
                'name'             => 'Basketball Full Court (FIBA Standard)',
                'badge'            => '🏀 Basketball Full',
                'category'         => 'ball',
                'shape'            => 'rectangular',
                'default_l'        => 94,
                'default_w'        => 50,
                'default_h'        => 28,
                'default_pitch'    => 0,
                'default_turf'     => 'synthetic_acrylic_8layer',
                'default_civil'    => 'concrete_pcc_3inch',
                'default_side_net' => 'braided_2_5mm_45mm',
                'default_top_net'  => 'twisted_1_5mm_35mm',
                'equipment_desc'   => '2 Nos Heavy-duty In-Ground Tournament Basketball Posts with 72"x42" (12mm) Toughened Glass Backboards, Spring Breakaway Pro Dunk Rims, Protective Pole Padding, and Full FIBA Regulation Court Line Markings.',
            ],
            'tennis' => [
                'key'              => 'tennis',
                'name'             => 'ITF Standard Tennis Court',
                'badge'            => '🎾 Tennis Court',
                'category'         => 'racket',
                'shape'            => 'rectangular',
                'default_l'        => 120,
                'default_w'        => 60,
                'default_h'        => 25,
                'default_pitch'    => 0,
                'default_turf'     => 'synthetic_acrylic_8layer',
                'default_civil'    => 'concrete_pcc_3inch',
                'default_side_net' => 'braided_2_5mm_45mm',
                'default_top_net'  => 'twisted_1_5mm_35mm',
                'equipment_desc'   => '1 Set Heavy 3" Cast Brass Internal Winder Tennis Posts, 42 FT Tournament Double-Top 3.5mm Braided Polyethylene Tennis Net with heavy PVC coated headband, adjustable center strap with ground anchor, and ITF regulation singles & doubles lines.',
            ],
            'pickleball' => [
                'key'              => 'pickleball',
                'name'             => 'Pickleball Single Court',
                'badge'            => '🏓 Pickleball Single',
                'category'         => 'racket',
                'shape'            => 'rectangular',
                'default_l'        => 60,
                'default_w'        => 30,
                'default_h'        => 20,
                'default_pitch'    => 0,
                'default_turf'     => 'synthetic_acrylic_8layer',
                'default_civil'    => 'concrete_pcc_3inch',
                'default_side_net' => 'braided_2_5mm_45mm',
                'default_top_net'  => 'twisted_1_5mm_35mm',
                'equipment_desc'   => '1 Set Heavy-duty Tournament Pickleball Posts with brass tension winder, 22 FT Official USA Pickleball Net (36-inch high at posts, 34" at center), adjustable center strap & anchor, and 2-tone court line markings with 7 FT Non-Volley Zone (Kitchen).',
            ],
            'pickleball_multi' => [
                'key'              => 'pickleball_multi',
                'name'             => 'Pickleball Multi-Court Complex (2-4 Courts)',
                'badge'            => '🏓 Pickleball Complex',
                'category'         => 'racket',
                'shape'            => 'rectangular',
                'default_l'        => 60,
                'default_w'        => 60,
                'default_h'        => 20,
                'default_pitch'    => 0,
                'default_turf'     => 'synthetic_acrylic_8layer',
                'default_civil'    => 'concrete_pcc_3inch',
                'default_side_net' => 'braided_2_5mm_45mm',
                'default_top_net'  => 'twisted_1_5mm_35mm',
                'equipment_desc'   => 'Multi-court Pickleball setup with individual tournament posts, official 22 FT nets, center straps, and heavy-duty divider netting between adjacent courts.',
            ],
            'multi_sport' => [
                'key'              => 'multi_sport',
                'name'             => 'Multi-Sport Arena (Half Cricket + Half Football)',
                'badge'            => '🏏⚽ Cricket + Football',
                'category'         => 'multi',
                'shape'            => 'rectangular',
                'default_l'        => 120,
                'default_w'        => 65,
                'default_h'        => 28,
                'default_pitch'    => 66,
                'default_turf'     => 'non_infill_turf',
                'default_civil'    => 'compacted_wmm',
                'default_side_net' => 'braided_2_5mm_45mm',
                'default_top_net'  => 'twisted_1_5mm_35mm',
                'equipment_desc'   => 'Dual Sports Package: 1 Pair Steel Futsal/5-a-side Goal Posts (10 FT x 6.5 FT) with heavy nets + Central Cricket Batting Pitch (6.5 FT x 66 FT) with bowling creases, multi-color court boundaries for seamless sport transitions.',
            ],
            'multi_futsal_basket' => [
                'key'              => 'multi_futsal_basket',
                'name'             => 'Multi-Sport Arena (Futsal + Basketball)',
                'badge'            => '⚽🏀 Futsal + Basketball',
                'category'         => 'multi',
                'shape'            => 'rectangular',
                'default_l'        => 110,
                'default_w'        => 60,
                'default_h'        => 25,
                'default_pitch'    => 0,
                'default_turf'     => 'pp_modular_tiles',
                'default_civil'    => 'concrete_pcc_3inch',
                'default_side_net' => 'braided_3_5mm_100mm',
                'default_top_net'  => 'braided_2_5mm_100mm',
                'equipment_desc'   => 'Combined Futsal Goalposts (3m x 2m) with integrated overhead Basketball Backboards & breakaway rims mounted directly above goal structures, dual-color inlaid court markings.',
            ],
            // --- 5. 🏢 Terrace & Rooftop Sports Nets ---
            'terrace_cricket_small' => [
                'key'              => 'terrace_cricket_small',
                'name'             => 'Terrace Top Cricket Net (Small 30x15 FT)',
                'badge'            => '🏢 Terrace Net (30x15)',
                'category'         => 'terrace',
                'shape'            => 'rectangular',
                'default_l'        => 30,
                'default_w'        => 15,
                'default_h'        => 12,
                'default_pitch'    => 30,
                'default_turf'     => 'no_turf',
                'default_civil'    => 'terrace_base_plates',
                'default_side_net' => 'braided_2_5mm_45mm',
                'default_top_net'  => 'twisted_1_5mm_35mm',
                'equipment_desc'   => '1 Set Heavy Stumps, Bowling Crease Markings, 360° Ball-Containment Boundary Netting, and Overhead Cable Suspended Roof Net with Base Flange Plates.',
            ],
            'terrace_cricket_lane' => [
                'key'              => 'terrace_cricket_lane',
                'name'             => 'Terrace Cricket Practice Lane (50x20 FT)',
                'badge'            => '🏢 Terrace Lane (50x20)',
                'category'         => 'terrace',
                'shape'            => 'rectangular',
                'default_l'        => 50,
                'default_w'        => 20,
                'default_h'        => 14,
                'default_pitch'    => 50,
                'default_turf'     => 'non_infill_turf',
                'default_civil'    => 'terrace_base_plates',
                'default_side_net' => 'braided_2_5mm_45mm',
                'default_top_net'  => 'twisted_1_5mm_35mm',
                'equipment_desc'   => 'Full Length 50 FT Practice Bowling & Batting Lane with Crease Markings, Target Stumps, High-Impact Side & Overhead Safety Netting, and Base Flange Plate Anchors.',
            ],
            'terrace_cricket_box' => [
                'key'              => 'terrace_cricket_box',
                'name'             => 'Terrace Box Cricket & Multi-Sport (60x30 FT)',
                'badge'            => '🏢 Terrace Box (60x30)',
                'category'         => 'terrace',
                'shape'            => 'rectangular',
                'default_l'        => 60,
                'default_w'        => 30,
                'default_h'        => 16,
                'default_pitch'    => 60,
                'default_turf'     => 'non_infill_turf',
                'default_civil'    => 'terrace_base_plates',
                'default_side_net' => 'braided_2_5mm_45mm',
                'default_top_net'  => 'twisted_1_5mm_35mm',
                'equipment_desc'   => 'Terrace Box Cricket Pitch with Inlaid Markings, Boundary Lines, Full Overhead Containment Netting, and Base Flange Plate Anchors.',
            ],
            'terrace_sports_arena' => [
                'key'              => 'terrace_sports_arena',
                'name'             => 'Commercial Rooftop Multi-Sport Arena (80x40 FT)',
                'badge'            => '🏢 Rooftop Arena (80x40)',
                'category'         => 'terrace',
                'shape'            => 'rectangular',
                'default_l'        => 80,
                'default_w'        => 40,
                'default_h'        => 18,
                'default_pitch'    => 66,
                'default_turf'     => 'non_infill_turf',
                'default_civil'    => 'terrace_base_plates',
                'default_side_net' => 'braided_2_5mm_45mm',
                'default_top_net'  => 'twisted_1_5mm_35mm',
                'equipment_desc'   => 'Commercial Rooftop Arena with 1 Pair (2 Nos) Steel Goalposts (10 FT x 6.5 FT), Cricket Batting Pitch, High-Tension Cable Roof Enclosure, and Heavy Base Plate Mounting.',
            ],
        ];
    }
}

if (!function_exists('resolveTurnkeySportProfile')) {
    function resolveTurnkeySportProfile($sportKey = 'box_cricket') {
        $profiles = getTurnkeySportProfiles();
        $aliases = [
            'cricket'               => 'box_cricket',
            'football'              => 'football_5a',
            'football_8a_12k'       => 'football_8a_compact',
            'football_8a_std'       => 'football_8a',
            'cricket_training_net'  => 'training_net',
            'practice_net'          => 'training_net',
            'batting_cage'          => 'training_net',
            'cricket_practice_net'  => 'training_net',
            'cricket_lane'          => 'training_net',
            'cricket_net'           => 'training_net',
            'training_net_single'   => 'training_net',
            'training_net_double'   => 'training_net_2lane',
            'training_net_triple'   => 'training_net_3lane',
        ];
        $resolved = $aliases[$sportKey] ?? $sportKey;
        return $profiles[$resolved] ?? $profiles['box_cricket'];
    }
}



if (!function_exists('getBadmintonCivilBedProfiles')) {
    function getBadmintonCivilBedProfiles() {
        return [
            'rcc_m20_4inch_laser' => [
                'key'         => 'rcc_m20_4inch_laser',
                'name'        => '4" M20 Laser-Screeded Reinforced Concrete Slab + 8mm TMT Rebar Mesh (Two 45x30 Courts)',
                'short_name'  => '4" M20 RCC Laser Slab',
                'rate'        => 75.0,
                'has_civil'   => true,
                'desc'        => 'Heavy-duty 100mm (4") M20 Grade reinforced concrete slab with 8mm TMT rebar mesh @ 150mm c/c, moisture-barrier vapor sheet, and power-floating mirror finish (VDF/IPS) for direct bonding of BWF-grade badminton vinyl sports mats across Two Regulation Courts (45x30 FT each = 2,700 SFT).',
                'specs'       => [
                    "Zone Area: 45 FT Length x 60 FT Width = 2,700 SFT (Two 45x30 FT Courts)",
                    "Base Specification: 100mm (4\") M20 RCC Slab with 8mm TMT Rebar Mesh @ 150mm c/c",
                    "Surface Finish: Vacuum Dewatered Flooring (VDF) / Mirror-Smooth Power Trowel Finish",
                    "Moisture Barrier: 250 Micron LDPE Vapor Barrier Sheet under slab"
                ]
            ],
            'rcc_m20_5inch_tmt' => [
                'key'         => 'rcc_m20_5inch_tmt',
                'name'        => '5" Heavy M20 Reinforced Concrete Slab + 8mm TMT Rebar Grid (Two 45x30 Courts)',
                'short_name'  => '5" M20 RCC Heavy Slab',
                'rate'        => 105.0,
                'has_civil'   => true,
                'desc'        => 'Heavy commercial 125mm (5") M20 Grade RCC foundation slab with dual 8mm TMT rebar grid, Tremix vacuum dewatering, and monolithic IPS floor hardener for tournament badminton arenas.',
                'specs'       => [
                    "Zone Area: 45 FT Length x 60 FT Width = 2,700 SFT (Two 45x30 FT Courts)",
                    "Base Specification: 125mm (5\") M20 RCC Slab with Dual 8mm TMT Steel Rebar Grid",
                    "Surface Finish: Vacuum Dewatered Tremix Slab with Monolithic Floor Hardener",
                    "Moisture Barrier: 250 Micron LDPE Vapor Barrier Sheet"
                ]
            ],
            'pcc_m15_4inch_smooth' => [
                'key'         => 'pcc_m15_4inch_smooth',
                'name'        => '4" PCC M15 Plain Cement Concrete Smooth Laser-Screeded Bed (Two 45x30 Courts)',
                'short_name'  => '4" PCC M15 Laser Bed',
                'rate'        => 76.0,
                'has_civil'   => true,
                'desc'        => '100mm (4") thick M15 grade plain cement concrete laser screeded to strict ±3mm level tolerance with 1:120 perimeter drainage slope, zero birdbath standard for synthetic sports flooring across 2,700 SFT.',
                'specs'       => [
                    "Zone Area: 45 FT Length x 60 FT Width = 2,700 SFT",
                    "Base Specification: 100mm (4\") PCC M15 Grade Laser-Screeded Bed",
                    "Surface Finish: Laser-guided screed with power trowel smooth finish"
                ]
            ],
            'pcc_m15_3inch_smooth' => [
                'key'         => 'pcc_m15_3inch_smooth',
                'name'        => '3" PCC M15 Plain Cement Concrete Smooth Troweled Bed (Two 45x30 Courts)',
                'short_name'  => '3" PCC M15 Smooth Bed',
                'rate'        => 60.0,
                'has_civil'   => true,
                'desc'        => '75mm (3") plain cement concrete M15 grade leveling bed with smooth power-troweled finish over compacted subgrade for direct mat adhesion across 2,700 SFT.',
                'specs'       => [
                    "Zone Area: 45 FT Length x 60 FT Width = 2,700 SFT",
                    "Base Specification: 75mm (3\") PCC M15 Grade Smooth Bed"
                ]
            ],
            'client_scope' => [
                'key'         => 'client_scope',
                'name'        => 'Existing Concrete Base / Executed by Client Scope (₹0 in BOQ)',
                'short_name'  => 'Client Scope / Existing Slab',
                'rate'        => 0.0,
                'has_civil'   => false,
                'desc'        => 'Badminton court concrete base slab provided and prepared directly by client as per GCM badminton court engineering standards (₹0 in BOQ).',
                'specs'       => [
                    "Scope: Client provides pre-cast / existing level RCC or PCC slab"
                ]
            ]
        ];
    }
}

if (!function_exists('getBadmintonFlooringProfiles')) {
    function getBadmintonFlooringProfiles() {
        return [
            'bwf_vinyl_4_5mm' => [
                'key'         => 'bwf_vinyl_4_5mm',
                'name'        => 'BWF-Grade 4.5mm Multi-Layer Synthetic Sports Surface (Two Badminton Courts 45×30 each)',
                'short_name'  => '4.5mm BWF Vinyl Mats',
                'rate'        => 95.0,
                'thickness'   => '4.5mm',
                'desc'        => 'High-performance embossed wear layer with high-density PVC foam backing for optimal shock absorption, traction, and zero slip. Divided into Two Regulation Courts (Court 1: 45 FT × 30 FT = 1,350 SFT; Court 2: 45 FT × 30 FT = 1,350 SFT).',
                'color'       => '#0284c7'
            ],
            'bwf_vinyl_5_0mm_wood' => [
                'key'         => 'bwf_vinyl_5_0mm_wood',
                'name'        => '5.0mm Wooden Texture BWF Tournament Vinyl Sports Flooring (Two Courts 45×30 each)',
                'short_name'  => '5.0mm Wooden Vinyl',
                'rate'        => 115.0,
                'thickness'   => '5.0mm',
                'desc'        => '5.0mm premium embossed maple wood grain vinyl sports surface with reinforced fiberglass mesh layer for maximum tournament shock absorption across Two Regulation Courts (45×30 FT each).',
                'color'       => '#d97706'
            ],
            'acrylic_cushion_8layer' => [
                'key'         => 'acrylic_cushion_8layer',
                'name'        => '8-Layer Cushion Acrylic Synthetic Badminton Court System (Two Courts 45×30 each)',
                'short_name'  => '8-Layer Cushion Acrylic',
                'rate'        => 85.0,
                'thickness'   => '8-Layer System',
                'desc'        => 'Multi-layer 100% acrylic latex cushion system with rubber granules resurfacer, flexible color coats, and crisp tournament regulation line markings for Two Regulation Courts (45×30 FT each).',
                'color'       => '#059669'
            ],
            'acrylic_pro_10layer' => [
                'key'         => 'acrylic_pro_10layer',
                'name'        => '10-Layer Pro Elite Grand Slam Cushion Acrylic Hardcourt (Two Courts 45×30 each)',
                'short_name'  => '10-Layer Pro Cushion',
                'rate'        => 115.0,
                'thickness'   => '10-Layer System',
                'desc'        => '10-layer heavy-cushion tournament sports surface featuring double SBR rubber micro-cushion coats, ultra-smooth texture coats, and anti-glare finish for Two Regulation Courts (45×30 FT each).',
                'color'       => '#7c3aed'
            ],
            'imported_teak_wood' => [
                'key'         => 'imported_teak_wood',
                'name'        => 'Imported Teak Wood Tongue-and-Groove Sports Flooring System (Two Courts 45×30 each)',
                'short_name'  => 'Teak Wood Sports Floor',
                'rate'        => 245.0,
                'thickness'   => '21mm Teak + Underlay',
                'desc'        => 'FSC-certified kiln-dried first-grade Teak Wood strip flooring with air-cushioned neoprene rubber pads, pine wood runners, and anti-skid polyurethane sports seal across Two Regulation Courts (45×30 FT each).',
                'color'       => '#b45309'
            ]
        ];
    }
}

if (!function_exists('resolveTurnkeyFlooringProfile')) {
    function resolveTurnkeyFlooringProfile($flooringKey = 'non_infill_turf') {
        $catalog = function_exists('getTurfCatalog') ? getTurfCatalog() : (function_exists('defaultTurfCatalog') ? defaultTurfCatalog() : []);
        $profiles = [
            // --- 0. 🌾 Landscape Sports Grass ---
            'landscape_25mm' => [
                'key'         => 'landscape_25mm',
                'name'        => $catalog['landscape_grass']['names']['25mm'] ?? '25mm Landscape Sports Grass',
                'short_name'  => '25mm Landscape Sports Grass',
                'has_turf'    => true,
                'has_infill'  => false,
                'pile_height' => '25mm Pile Height',
                'category'    => 'landscape',
                'turf_subgroup' => 'landscape',
                'rates'       => [
                    'essential'   => (float)($catalog['landscape_grass']['rates']['25mm'] ?? 30.0),
                    'commercial'  => (float)($catalog['landscape_grass']['rates']['25mm'] ?? 30.0),
                    'premium_pro' => (float)($catalog['landscape_grass']['rates']['25mm'] ?? 30.0),
                ],
                'infill_rates'=> ['essential' => 0, 'commercial' => 0, 'premium_pro' => 0],
                'desc'        => 'High-density 25mm all-weather UV-treated synthetic landscape grass with multi-tone thatch yarn for realistic appearance and heavy footfall.',
            ],
            'landscape_30mm' => [
                'key'         => 'landscape_30mm',
                'name'        => $catalog['landscape_grass']['names']['30mm'] ?? '30mm Landscape Sports Grass',
                'short_name'  => '30mm Landscape Sports Grass',
                'has_turf'    => true,
                'has_infill'  => false,
                'pile_height' => '30mm Pile Height',
                'category'    => 'landscape',
                'turf_subgroup' => 'landscape',
                'rates'       => [
                    'essential'   => (float)($catalog['landscape_grass']['rates']['30mm'] ?? 35.0),
                    'commercial'  => (float)($catalog['landscape_grass']['rates']['30mm'] ?? 35.0),
                    'premium_pro' => (float)($catalog['landscape_grass']['rates']['30mm'] ?? 35.0),
                ],
                'infill_rates'=> ['essential' => 0, 'commercial' => 0, 'premium_pro' => 0],
                'desc'        => 'Premium 30mm natural-look multi-tone landscape turf with curled thatch zone for cushioned comfort, drainage perforations, and zero infill requirement.',
            ],
            'landscape_35mm' => [
                'key'         => 'landscape_35mm',
                'name'        => $catalog['landscape_grass']['names']['35mm'] ?? '35mm Landscape Sports Grass',
                'short_name'  => '35mm Landscape Sports Grass',
                'has_turf'    => true,
                'has_infill'  => false,
                'pile_height' => '35mm Pile Height',
                'category'    => 'landscape',
                'turf_subgroup' => 'landscape',
                'rates'       => [
                    'essential'   => (float)($catalog['landscape_grass']['rates']['35mm'] ?? 40.0),
                    'commercial'  => (float)($catalog['landscape_grass']['rates']['35mm'] ?? 40.0),
                    'premium_pro' => (float)($catalog['landscape_grass']['rates']['35mm'] ?? 40.0),
                ],
                'infill_rates'=> ['essential' => 0, 'commercial' => 0, 'premium_pro' => 0],
                'desc'        => 'Luxury 35mm lush landscape grass with dense 4-tone synthetic fibers, high UV stability, and reinforced dual PP+mesh backing.',
            ],
            'landscape_40mm' => [
                'key'         => 'landscape_40mm',
                'name'        => $catalog['landscape_grass']['names']['40mm'] ?? '40mm Landscape Sports Grass',
                'short_name'  => '40mm Landscape Sports Grass',
                'has_turf'    => true,
                'has_infill'  => false,
                'pile_height' => '40mm Pile Height',
                'category'    => 'landscape',
                'turf_subgroup' => 'landscape',
                'rates'       => [
                    'essential'   => (float)($catalog['landscape_grass']['rates']['40mm'] ?? 45.0),
                    'commercial'  => (float)($catalog['landscape_grass']['rates']['40mm'] ?? 45.0),
                    'premium_pro' => (float)($catalog['landscape_grass']['rates']['40mm'] ?? 45.0),
                ],
                'infill_rates'=> ['essential' => 0, 'commercial' => 0, 'premium_pro' => 0],
                'desc'        => 'Super-dense 40mm premium landscape grass providing ultra-soft underfoot cushioning, superior resilience, and vibrant aesthetic appeal.',
            ],

            // --- 1. 🏏 Sports Grasses & DTEX Options ---
            'sports_30mm_non_infill' => [
                'key'         => 'sports_30mm_non_infill',
                'name'        => $catalog['sports_grass']['30mm_non_infill']['name'] ?? '30mm High-Density Non-Infill Sports Grass (No Rubber/Sand)',
                'short_name'  => '30mm Non-Infill Sports Turf',
                'has_turf'    => true,
                'has_infill'  => false,
                'pile_height' => '30mm High Density',
                'category'    => 'turf',
                'turf_subgroup' => 'sports_non_infill',
                'rates'       => [
                    'essential'   => (float)($catalog['sports_grass']['30mm_non_infill']['rate'] ?? 60.0),
                    'commercial'  => (float)($catalog['sports_grass']['30mm_non_infill']['rate'] ?? 60.0),
                    'premium_pro' => (float)($catalog['sports_grass']['30mm_non_infill']['rate'] ?? 60.0),
                ],
                'infill_rates'=> ['essential' => 0, 'commercial' => 0, 'premium_pro' => 0],
                'desc'        => $catalog['sports_grass']['30mm_non_infill']['desc'] ?? 'Heavy-duty 30mm non-infill sports grass designed for high-traction multi-sport and box cricket.',
            ],
            'sports_50mm_12000_dtex' => [
                'key'         => 'sports_50mm_12000_dtex',
                'name'        => $catalog['sports_grass']['50mm_12000_dtex']['name'] ?? '50mm Football / Box Cricket Turf (12,000 DTEX)',
                'short_name'  => '50mm 12k DTEX Turf',
                'has_turf'    => true,
                'has_infill'  => true,
                'pile_height' => '50mm Monofilament',
                'category'    => 'football_turf',
                'turf_subgroup' => 'sports_infill',
                'dtex'        => 12000,
                'rates'       => [
                    'essential'   => (float)($catalog['sports_grass']['50mm_12000_dtex']['rate'] ?? 58.0),
                    'commercial'  => (float)($catalog['sports_grass']['50mm_12000_dtex']['rate'] ?? 58.0),
                    'premium_pro' => (float)($catalog['sports_grass']['50mm_12000_dtex']['rate'] ?? 58.0),
                ],
                'infill_rates'=> ['essential' => 12, 'commercial' => 12, 'premium_pro' => 12],
                'desc'        => $catalog['sports_grass']['50mm_12000_dtex']['desc'] ?? '50mm monofilament football & box cricket sports turf (12,000 DTEX) with silica sand & SBR rubber infill system.',
            ],
            'sports_50mm_14000_dtex' => [
                'key'         => 'sports_50mm_14000_dtex',
                'name'        => $catalog['sports_grass']['50mm_14000_dtex']['name'] ?? '50mm Football / Box Cricket Turf (14,000 DTEX)',
                'short_name'  => '50mm 14k DTEX Turf',
                'has_turf'    => true,
                'has_infill'  => true,
                'pile_height' => '50mm Diamond Spine',
                'category'    => 'football_turf',
                'turf_subgroup' => 'sports_infill',
                'dtex'        => 14000,
                'rates'       => [
                    'essential'   => (float)($catalog['sports_grass']['50mm_14000_dtex']['rate'] ?? 64.0),
                    'commercial'  => (float)($catalog['sports_grass']['50mm_14000_dtex']['rate'] ?? 64.0),
                    'premium_pro' => (float)($catalog['sports_grass']['50mm_14000_dtex']['rate'] ?? 64.0),
                ],
                'infill_rates'=> ['essential' => 12, 'commercial' => 12, 'premium_pro' => 12],
                'desc'        => $catalog['sports_grass']['50mm_14000_dtex']['desc'] ?? '50mm high-resilience diamond monofilament sports turf (14,000 DTEX) with silica sand & SBR rubber infill.',
            ],
            'sports_50mm_16000_dtex' => [
                'key'         => 'sports_50mm_16000_dtex',
                'name'        => $catalog['sports_grass']['50mm_16000_dtex']['name'] ?? '50mm Football / Box Cricket Turf (16,000 DTEX)',
                'short_name'  => '50mm 16k DTEX Turf',
                'has_turf'    => true,
                'has_infill'  => true,
                'pile_height' => '50mm Tournament Grade',
                'category'    => 'football_turf',
                'turf_subgroup' => 'sports_infill',
                'dtex'        => 16000,
                'rates'       => [
                    'essential'   => (float)($catalog['sports_grass']['50mm_16000_dtex']['rate'] ?? 75.0),
                    'commercial'  => (float)($catalog['sports_grass']['50mm_16000_dtex']['rate'] ?? 75.0),
                    'premium_pro' => (float)($catalog['sports_grass']['50mm_16000_dtex']['rate'] ?? 75.0),
                ],
                'infill_rates'=> ['essential' => 12, 'commercial' => 12, 'premium_pro' => 12],
                'desc'        => $catalog['sports_grass']['50mm_16000_dtex']['desc'] ?? '50mm premium tournament-grade monofilament sports turf (16,000 DTEX) with full infill system.',
            ],

            // --- 2. 🏏 Cricket & Multi-Sport Turfs (Non-Infill) ---
            'non_infill_turf' => [
                'key'         => 'non_infill_turf',
                'name'        => '15mm High-Density UV Monofilament Cricket / Multi-Sport Turf (Non-Infill)',
                'short_name'  => '15mm Non-Infill Turf',
                'has_turf'    => true,
                'has_infill'  => false,
                'pile_height' => '15mm Curled Monofilament',
                'category'    => 'cricket_turf',
                'rates'       => ['essential' => 45, 'commercial' => 52, 'premium_pro' => 65],
                'infill_rates'=> ['essential' => 0, 'commercial' => 0, 'premium_pro' => 0],
                'desc'        => 'High-density 15mm curled UV monofilament sports grass (24,000+ stitches/sqm) designed for true cricket ball bounce, high traction, and zero granule displacement.',
            ],
            'turf_cricket_15mm' => [
                'key'         => 'turf_cricket_15mm',
                'name'        => '15mm High-Density UV Monofilament Cricket / Multi-Sport Turf (Non-Infill)',
                'short_name'  => '15mm Cricket Turf',
                'has_turf'    => true,
                'has_infill'  => false,
                'pile_height' => '15mm Monofilament',
                'category'    => 'cricket_turf',
                'rates'       => ['essential' => 45, 'commercial' => 52, 'premium_pro' => 65],
                'infill_rates'=> ['essential' => 0, 'commercial' => 0, 'premium_pro' => 0],
                'desc'        => 'High-density 15mm curled UV monofilament sports grass (24,000+ stitches/sqm) for Box Cricket & Golf Putting with true ball roll.',
            ],
            'turf_cricket_20mm' => [
                'key'         => 'turf_cricket_20mm',
                'name'        => '20mm High-Density All-Weather Multi-Sport Turf (Non-Infill Heavy Duty)',
                'short_name'  => '20mm Multi-Sport Turf',
                'has_turf'    => true,
                'has_infill'  => false,
                'pile_height' => '20mm High Density',
                'category'    => 'cricket_turf',
                'rates'       => ['essential' => 58, 'commercial' => 72, 'premium_pro' => 90],
                'infill_rates'=> ['essential' => 0, 'commercial' => 0, 'premium_pro' => 0],
                'desc'        => '20mm heavy-gauge curled bi-color sports turf (26,000 stitches/sqm) for heavy footfall box cricket, touch rugby, and outdoor training arenas.',
            ],
            'cricket_dual_turf' => [
                'key'         => 'cricket_dual_turf',
                'name'        => '15mm Turf Outfield + 6.5 FT × 66 FT Dedicated Pitch Roll',
                'short_name'  => 'Dual Cricket Turf + Pitch',
                'has_turf'    => true,
                'has_infill'  => false,
                'pile_height' => '15mm Outfield + 20mm Pitch',
                'category'    => 'cricket_turf',
                'rates'       => ['essential' => 60, 'commercial' => 76, 'premium_pro' => 95],
                'infill_rates'=> ['essential' => 0, 'commercial' => 0, 'premium_pro' => 0],
                'desc'        => 'Integrated 15mm outfield sports grass with factory-inlaid high-density 6.5 FT × 66 FT center cricket batting pitch strip in clay-tint or contrast green.',
            ],

            // --- 2. ⚽ Football & Futsal Turfs (Infill & Non-Infill) ---
            'infill_turf' => [
                'key'         => 'infill_turf',
                'name'        => '50mm FIFA Quality Diamond-Spine Football Turf + Silica Sand/SBR Infill',
                'short_name'  => '50mm FIFA Football Turf',
                'has_turf'    => true,
                'has_infill'  => true,
                'pile_height' => '50mm Pile Height',
                'category'    => 'football_turf',
                'rates'       => ['essential' => 58, 'commercial' => 75, 'premium_pro' => 95],
                'infill_rates'=> ['essential' => 15, 'commercial' => 20, 'premium_pro' => 25],
                'desc'        => 'FIFA-Standard 50mm diamond-spine monofilament artificial grass with UV resistance, dual PP + PU backing, graded silica sand (15 kg/sqm) and SBR resilient rubber granules (4 kg/sqm) infill.',
            ],
            'turf_football_40mm' => [
                'key'         => 'turf_football_40mm',
                'name'        => '40mm High-Performance Futsal & 5-a-Side Football Turf + Infill',
                'short_name'  => '40mm Futsal/Football Turf',
                'has_turf'    => true,
                'has_infill'  => true,
                'pile_height' => '40mm Monofilament',
                'category'    => 'football_turf',
                'rates'       => ['essential' => 52, 'commercial' => 68, 'premium_pro' => 86],
                'infill_rates'=> ['essential' => 12, 'commercial' => 16, 'premium_pro' => 20],
                'desc'        => '40mm fast-paced monofilament sports turf with sand/rubber infill, ideal for 5-a-side football and futsal arenas requiring fast ball roll.',
            ],
            'turf_football_50mm_infill' => [
                'key'         => 'turf_football_50mm_infill',
                'name'        => '50mm FIFA Quality Diamond-Spine Football Turf + Silica Sand/SBR Infill',
                'short_name'  => '50mm FIFA Football Turf',
                'has_turf'    => true,
                'has_infill'  => true,
                'pile_height' => '50mm Pile Height',
                'category'    => 'football_turf',
                'rates'       => ['essential' => 58, 'commercial' => 75, 'premium_pro' => 95],
                'infill_rates'=> ['essential' => 15, 'commercial' => 20, 'premium_pro' => 25],
                'desc'        => 'FIFA-Standard 50mm diamond-spine monofilament artificial grass with UV resistance, dual PP + PU backing, graded silica sand and SBR resilient rubber granules.',
            ],
            'turf_football_55mm_fifa' => [
                'key'         => 'turf_football_55mm_fifa',
                'name'        => '55mm FIFA Pro Tournament Grade Football Turf (12,000 Dtex) + Infill',
                'short_name'  => '55mm FIFA Pro Turf',
                'has_turf'    => true,
                'has_infill'  => true,
                'pile_height' => '55mm Pro Tournament',
                'category'    => 'football_turf',
                'rates'       => ['essential' => 68, 'commercial' => 88, 'premium_pro' => 110],
                'infill_rates'=> ['essential' => 18, 'commercial' => 24, 'premium_pro' => 30],
                'desc'        => 'Professional 55mm 12,000 Dtex stem monofilament turf with heavy-duty latex/PU backing, tested for international tournament standards and maximum player slide protection.',
            ],
            'turf_football_non_infill' => [
                'key'         => 'turf_football_non_infill',
                'name'        => '32mm Dense Root-Zone Non-Infill Football Grass (No Rubber Splatter)',
                'short_name'  => '32mm Non-Infill Football',
                'has_turf'    => true,
                'has_infill'  => false,
                'pile_height' => '32mm Non-Infill Dense',
                'category'    => 'football_turf',
                'rates'       => ['essential' => 65, 'commercial' => 82, 'premium_pro' => 105],
                'infill_rates'=> ['essential' => 0, 'commercial' => 0, 'premium_pro' => 0],
                'desc'        => 'Next-generation 32mm dense thatch-backed non-infill football grass. Eliminates black rubber granules, messy infill splash, and maintenance washing.',
            ],

            // --- 3. 🎾 Synthetic Acrylic Hardcourt & Cushion Systems (Tennis, Basketball, Pickleball) ---
            'synthetic_acrylic_3layer' => [
                'key'         => 'synthetic_acrylic_3layer',
                'name'        => '3-Layer Synthetic Acrylic Hardcourt Coating System (ITF Medium)',
                'short_name'  => '3-Layer Acrylic Hardcourt',
                'has_turf'    => true,
                'has_infill'  => false,
                'pile_height' => '3-Layer Hardcourt',
                'category'    => 'acrylic',
                'rates'       => ['essential' => 35, 'commercial' => 45, 'premium_pro' => 58],
                'infill_rates'=> ['essential' => 0, 'commercial' => 0, 'premium_pro' => 0],
                'desc'        => 'Economic 3-layer 100% acrylic surface system (Resurfacer coat + 2 UV color wear coats + regulation polyurethane line markings).',
            ],
            'synthetic_acrylic_5layer' => [
                'key'         => 'synthetic_acrylic_5layer',
                'name'        => '5-Layer Synthetic Acrylic Standard Hardcourt System (FIBA / ITF)',
                'short_name'  => '5-Layer Acrylic Hardcourt',
                'has_turf'    => true,
                'has_infill'  => false,
                'pile_height' => '5-Layer Acrylic',
                'category'    => 'acrylic',
                'rates'       => ['essential' => 45, 'commercial' => 58, 'premium_pro' => 75],
                'infill_rates'=> ['essential' => 0, 'commercial' => 0, 'premium_pro' => 0],
                'desc'        => 'Standard 5-layer acrylic outdoor sports coating system with acrylic primer, heavy silica resurfacer, two pigmented color wear coats, and polyurethane line paint.',
            ],
            'synthetic_acrylic_8layer' => [
                'key'         => 'synthetic_acrylic_8layer',
                'name'        => '8-Layer Synthetic Acrylic ITF/FIBA Cushion Court (Dual Rubber Cushion)',
                'short_name'  => '8-Layer Acrylic Cushion',
                'has_turf'    => true,
                'has_infill'  => false,
                'pile_height' => '8-Layer Cushion System',
                'category'    => 'acrylic',
                'rates'       => ['essential' => 65, 'commercial' => 85, 'premium_pro' => 110],
                'infill_rates'=> ['essential' => 0, 'commercial' => 0, 'premium_pro' => 0],
                'desc'        => 'ITF / FIBA certified 8-layer cushioned acrylic coating system with resurfacer, dual coarse & fine rubberized cushion coats, UV color coats, and anti-glare textured tournament finish.',
            ],
            'synthetic_acrylic_10layer' => [
                'key'         => 'synthetic_acrylic_10layer',
                'name'        => '10-Layer Pro Elite Grand Slam Acrylic Cushion System (Maximum Joint Safety)',
                'short_name'  => '10-Layer Pro Elite Cushion',
                'has_turf'    => true,
                'has_infill'  => false,
                'pile_height' => '10-Layer Pro Cushion',
                'category'    => 'acrylic',
                'rates'       => ['essential' => 88, 'commercial' => 115, 'premium_pro' => 145],
                'infill_rates'=> ['essential' => 0, 'commercial' => 0, 'premium_pro' => 0],
                'desc'        => 'Grand Slam tournament grade 10-layer heavy liquid rubber cushion system delivering 25%+ force reduction for elite tennis, basketball, and multi-sport performance.',
            ],

            // --- 4. 🏸 Badminton & Indoor Vinyl Sports Flooring ---
            'bwf_vinyl_mat' => [
                'key'         => 'bwf_vinyl_mat',
                'name'        => 'BWF Approved 4.5mm Anti-Slip Embossed Vinyl Sports Flooring',
                'short_name'  => '4.5mm BWF Vinyl Mat',
                'has_turf'    => true,
                'has_infill'  => false,
                'pile_height' => '4.5mm Vinyl Mat',
                'category'    => 'vinyl',
                'rates'       => ['essential' => 55, 'commercial' => 70, 'premium_pro' => 90],
                'infill_rates'=> ['essential' => 0, 'commercial' => 0, 'premium_pro' => 0],
                'desc'        => 'BWF certified 4.5mm PVC sports matting with high-density foaming cushion back, embossed anti-slip sand pattern wear layer, and thermal cold-weld joints.',
            ],
            'bwf_vinyl_4_5mm' => [
                'key'         => 'bwf_vinyl_4_5mm',
                'name'        => 'BWF Approved 4.5mm Anti-Slip Embossed Vinyl Sports Flooring',
                'short_name'  => '4.5mm BWF Vinyl Mat',
                'has_turf'    => true,
                'has_infill'  => false,
                'pile_height' => '4.5mm Vinyl Mat',
                'category'    => 'vinyl',
                'rates'       => ['essential' => 55, 'commercial' => 70, 'premium_pro' => 90],
                'infill_rates'=> ['essential' => 0, 'commercial' => 0, 'premium_pro' => 0],
                'desc'        => 'BWF certified 4.5mm PVC sports matting with high-density foaming cushion back, embossed anti-slip sand pattern wear layer, and thermal cold-weld joints.',
            ],
            'bwf_vinyl_5_0mm' => [
                'key'         => 'bwf_vinyl_5_0mm',
                'name'        => 'BWF Tournament Grade 5.0mm Multi-Layer Vinyl Sports Flooring (Fiberglass Layer)',
                'short_name'  => '5.0mm Tournament Vinyl',
                'has_turf'    => true,
                'has_infill'  => false,
                'pile_height' => '5.0mm Tournament Vinyl',
                'category'    => 'vinyl',
                'rates'       => ['essential' => 62, 'commercial' => 80, 'premium_pro' => 102],
                'infill_rates'=> ['essential' => 0, 'commercial' => 0, 'premium_pro' => 0],
                'desc'        => '5.0mm heavy-duty BWF tournament grade vinyl floor with woven fiberglass mesh reinforcement layer for 100% dimensional stability and anti-friction shoe grip.',
            ],
            'bwf_vinyl_7_0mm' => [
                'key'         => 'bwf_vinyl_7_0mm',
                'name'        => '7.0mm Heavy-Duty Multi-Sport PVC Sports Flooring (Indoor Arena Grade)',
                'short_name'  => '7.0mm Multi-Sport Vinyl',
                'has_turf'    => true,
                'has_infill'  => false,
                'pile_height' => '7.0mm Multi-Sport PVC',
                'category'    => 'vinyl',
                'rates'       => ['essential' => 78, 'commercial' => 98, 'premium_pro' => 125],
                'infill_rates'=> ['essential' => 0, 'commercial' => 0, 'premium_pro' => 0],
                'desc'        => '7.0mm extra-thick multi-purpose indoor sports vinyl floor with dual honeycomb foam cushion backing for multi-sport gymnasiums (Badminton, Basketball, Volleyball).',
            ],

            // --- 5. 🏓 Modular Interlocking PP Sports Tiles ---
            'pp_modular_tiles' => [
                'key'         => 'pp_modular_tiles',
                'name'        => '15.8mm UV-Treated Interlocking Outdoor Modular Sports Tiles (Single Tier)',
                'short_name'  => '15.8mm PP Modular Tiles',
                'has_turf'    => true,
                'has_infill'  => false,
                'pile_height' => '15.8mm Interlocking Tile',
                'category'    => 'modular',
                'rates'       => ['essential' => 58, 'commercial' => 75, 'premium_pro' => 95],
                'infill_rates'=> ['essential' => 0, 'commercial' => 0, 'premium_pro' => 0],
                'desc'        => 'High-impact polypropylene (PP) self-draining interlocking sports tiles with anti-slip diamond surface, thermal expansion joints, and high shock absorption.',
            ],
            'pp_modular_tiles_cushion' => [
                'key'         => 'pp_modular_tiles_cushion',
                'name'        => '19mm Double-Tier Elastic Suspended PP Modular Sports Tiles (Shock Cushion)',
                'short_name'  => '19mm Elastic PP Tiles',
                'has_turf'    => true,
                'has_infill'  => false,
                'pile_height' => '19mm Double Tier Tile',
                'category'    => 'modular',
                'rates'       => ['essential' => 68, 'commercial' => 88, 'premium_pro' => 112],
                'infill_rates'=> ['essential' => 0, 'commercial' => 0, 'premium_pro' => 0],
                'desc'        => '19mm dual-tier suspended interlocking PP sports tiles featuring integrated elastomer suspension cushions delivering 35%+ shock absorption for outdoor basketball and pickleball.',
            ],

            // --- 6. 🪵 Indoor Wooden Sports Flooring ---
            'wooden_teak_maple' => [
                'key'         => 'wooden_teak_maple',
                'name'        => '21mm Solid Teak / Maple Wood Sports Flooring + Air-Cushioned Pine Underlay',
                'short_name'  => '21mm Wooden Sports Floor',
                'has_turf'    => true,
                'has_infill'  => false,
                'pile_height' => '21mm Hardwood + Underlay',
                'category'    => 'wooden',
                'rates'       => ['essential' => 175, 'commercial' => 220, 'premium_pro' => 280],
                'infill_rates'=> ['essential' => 0, 'commercial' => 0, 'premium_pro' => 0],
                'desc'        => 'Tongue & groove 21mm Canadian Maple / Teak hardwood sports floor laid on air-cushioned pine sub-runners and 10mm EPDM rubber pads, finished with anti-skid polyurethane sports sealers.',
            ],

            // --- 7. 🚫 Base / Civil Only (No Top Surface) ---
            'no_turf' => [
                'key'         => 'no_turf',
                'name'        => 'Base / Civil Only (No Flooring / Client Scope)',
                'short_name'  => 'No Flooring (Base Only)',
                'has_turf'    => false,
                'has_infill'  => false,
                'pile_height' => 'N/A',
                'category'    => 'none',
                'rates'       => ['essential' => 0, 'commercial' => 0, 'premium_pro' => 0],
                'infill_rates'=> ['essential' => 0, 'commercial' => 0, 'premium_pro' => 0],
                'desc'        => 'Civil foundation & structural enclosure only without surface flooring or turf.',
            ],
        ];

        // Comprehensive Aliases
        $aliases = [
            'turf_cricket_15mm'            => 'non_infill_turf',
            'cricket_turf_15mm_curled'     => 'non_infill_turf',
            'cricket_turf_20mm_hd'         => 'turf_cricket_20mm',
            'cricket_turf_15mm_pitch'      => 'cricket_dual_turf',
            'football_turf_40mm_mono'      => 'turf_football_40mm',
            'football_turf_50mm_fifa'      => 'infill_turf',
            'turf_football_50mm_infill'    => 'infill_turf',
            'football_turf_55mm_fifa_pro'  => 'turf_football_55mm_fifa',
            'football_turf_32mm_non_infill'=> 'turf_football_non_infill',
            'acrylic_3layer_pickleball'    => 'synthetic_acrylic_3layer',
            'acrylic_5layer_fiba_hardcourt'=> 'synthetic_acrylic_5layer',
            'acrylic_8layer_cushion_pro'   => 'synthetic_acrylic_8layer',
            'acrylic_10layer_elite_slam'   => 'synthetic_acrylic_10layer',
            'badminton_vinyl_4_5mm'        => 'bwf_vinyl_mat',
            'vinyl_badminton_4_5mm'        => 'bwf_vinyl_mat',
            'badminton_vinyl_5_0mm_tour'   => 'bwf_vinyl_5_0mm',
            'vinyl_badminton_5_0mm'        => 'bwf_vinyl_5_0mm',
            'vinyl_7_0mm_multisport'       => 'bwf_vinyl_7_0mm',
            'vinyl_multisport_7_0mm'       => 'bwf_vinyl_7_0mm',
            'modular_pp_tiles_15_8mm'      => 'pp_modular_tiles',
            'modular_pp_tiles_19mm_elastic'=> 'pp_modular_tiles_cushion',
            'wooden_teak_hardwood_21mm'    => 'wooden_teak_maple',
            'hardwood_wooden_floor'        => 'wooden_teak_maple',
            'none'                         => 'no_turf',
            'no_flooring'                  => 'no_turf',
            'hardcourt'                    => 'no_turf',
        ];

        $resolvedKey = $aliases[$flooringKey] ?? $flooringKey;
        return $profiles[$resolvedKey] ?? $profiles['non_infill_turf'];
    }
}

if (!function_exists('getTurnkeyFlooringOptions')) {
    function getTurnkeyFlooringOptions() {
        return [
            // Landscape Sports Grass
            'landscape_25mm'            => '🌾 25mm Landscape Sports Grass (₹30/SFT)',
            'landscape_30mm'            => '🌾 30mm Landscape Sports Grass (₹35/SFT)',
            'landscape_35mm'            => '🌾 35mm Landscape Sports Grass (₹40/SFT)',
            'landscape_40mm'            => '🌾 40mm Landscape Sports Grass (₹45/SFT)',
            // Sports Turf Systems
            'sports_30mm_non_infill'    => '🏏 30mm High-Density Non-Infill Sports Turf (₹60/SFT)',
            'sports_50mm_12000_dtex'    => '⚽ 50mm Football / Box Cricket Turf (12,000 DTEX) (₹58/SFT)',
            'sports_50mm_14000_dtex'    => '⚽ 50mm Football / Box Cricket Turf (14,000 DTEX) (₹64/SFT)',
            'sports_50mm_16000_dtex'    => '🏆 50mm Tournament Sports Turf (16,000 DTEX) (₹75/SFT)',
            // Classic Cricket Turfs
            'non_infill_turf'           => '🏏 15mm Curled UV Cricket Turf (Non-Infill) (~₹52/SFT)',
            'turf_cricket_20mm'         => '🏏 20mm High-Density Multi-Sport Turf (Non-Infill) (~₹72/SFT)',
            'cricket_dual_turf'         => '🏏 15mm Outfield + Dedicated Center Pitch Roll (~₹76/SFT)',
            // Acrylic Hardcourt & Others
            'synthetic_acrylic_3layer'  => '🏓 3-Layer Acrylic Hardcourt (Pickleball/Basket) (~₹45/SFT)',
            'synthetic_acrylic_5layer'  => '🏀 5-Layer Synthetic Acrylic Standard Hardcourt (~₹58/SFT)',
            'synthetic_acrylic_8layer'  => '🎾 8-Layer Synthetic Acrylic Cushion Court (~₹85/SFT)',
            'synthetic_acrylic_10layer' => '🏆 10-Layer Pro Elite Grand Slam Cushion Court (~₹115/SFT)',
            'bwf_vinyl_mat'             => '🏸 4.5mm BWF Approved Anti-Slip Vinyl Sports Mat (~₹70/SFT)',
            'bwf_vinyl_5_0mm'           => '🏸 5.0mm BWF Tournament Grade Sports Vinyl (~₹80/SFT)',
            'bwf_vinyl_7_0mm'           => '🏸 7.0mm Multi-Sport Gymnasium Vinyl Floor (~₹98/SFT)',
            'pp_modular_tiles'          => '🏓 15.8mm UV Interlocking Modular Sports Tiles (~₹75/SFT)',
            'pp_modular_tiles_cushion'  => '🏓 19mm Double-Tier Elastic PP Suspended Tiles (~₹88/SFT)',
            'wooden_teak_maple'         => '🪵 21mm Teak/Maple Hardwood Sports Floor (~₹220/SFT)',
            'no_turf'                   => '🚫 No Top Flooring (Civil Sub-Base Only) (₹0 in BOQ)',
        ];
    }
}

if (!function_exists('resolveTurnkeyPoleProfile')) {
    function resolveTurnkeyPoleProfile($poleStyleKey = 'ladder_2line', $pipeSize = '40x40', $pipeGauge = '2.0mm') {
        if ($poleStyleKey === 's3_ladder_truss') {
            $poleType = 'ladder_2line';
            $pipeSize = 'pipe_2_5_round';
            $pipeGauge = '2.5mm';
        } elseif ($poleStyleKey === 's2_heavy_4x4') {
            $poleType = 'single_pipe';
            $pipeSize = '4x4';
            $pipeGauge = '2.5mm';
        } elseif ($poleStyleKey === 's4_heavy_5x5') {
            $poleType = 'single_pipe';
            $pipeSize = '5x5';
            $pipeGauge = '3.0mm';
        } elseif ($poleStyleKey === 's1_standard_3x3') {
            $poleType = 'single_pipe';
            $pipeSize = '3x3';
            $pipeGauge = '2.0mm';
        } elseif (in_array($poleStyleKey, ['ladder_2line', 'ladder_4line', 'single_pipe'])) {
            $poleType = $poleStyleKey;
        } else {
            $poleType = 'ladder_2line';
        }

        if ($poleType === 'ladder_2line') {
            $validSizes = ['32x32', '40x40', '50x50', 'pipe_2_5_round'];
            if (!in_array($pipeSize, $validSizes)) $pipeSize = '40x40';
            if ($pipeSize === 'pipe_2_5_round') {
                $pipeGauge = '2.5mm';
            } else {
                if (!in_array($pipeGauge, ['1.5mm', '2.0mm'])) $pipeGauge = '2.0mm';
            }
        } elseif ($poleType === 'ladder_4line') {
            $validSizes = ['32x32', '40x40', '50x50'];
            if (!in_array($pipeSize, $validSizes)) $pipeSize = '40x40';
            if (!in_array($pipeGauge, ['1.5mm', '2.0mm'])) $pipeGauge = '2.0mm';
        } else {
            $validSizes = ['3x3', '4x4', '5x5'];
            if (!in_array($pipeSize, $validSizes)) $pipeSize = '4x4';
            if ($pipeSize === '3x3') {
                if (!in_array($pipeGauge, ['2.0mm', '2.5mm'])) $pipeGauge = '2.0mm';
            } elseif ($pipeSize === '4x4') {
                if (!in_array($pipeGauge, ['2.0mm', '2.5mm'])) $pipeGauge = '2.5mm';
            } else {
                if (!in_array($pipeGauge, ['2.0mm', '2.5mm', '3.0mm'])) $pipeGauge = '2.5mm';
            }
        }

        $sizeLabels = [
            '32x32'          => '32mm × 32mm Square Pipe',
            '40x40'          => '40mm × 40mm Square Pipe',
            '50x50'          => '50mm × 50mm Square Pipe',
            'pipe_2_5_round' => 'Dual 2.5" OD Round Pipe',
            '3x3'            => '3" × 3" Square Pipe',
            '4x4'            => '4" × 4" Square Pipe',
            '5x5'            => '5" × 5" Square Pipe',
        ];

        $gaugeLabels = [
            '1.5mm' => '1.5mm Wall Thickness (16 Gauge)',
            '2.0mm' => '2.0mm Wall Thickness (14 Gauge)',
            '2.5mm' => '2.5mm Wall Thickness (12 Gauge)',
            '3.0mm' => '3.0mm Wall Thickness (10 Gauge)',
        ];

        $gaugeShort = [
            '1.5mm' => '1.5mm (16G)',
            '2.0mm' => '2.0mm (14G)',
            '2.5mm' => '2.5mm (12G)',
            '3.0mm' => '3.0mm (10G)',
        ];

        $uprightsCount = ($poleType === 'ladder_4line') ? 4 : (($poleType === 'ladder_2line') ? 2 : 1);
        $facesLaced    = ($poleType === 'ladder_4line') ? 4 : (($poleType === 'ladder_2line') ? 1 : 0);

        $rateAdj = 0;
        if ($poleType === 'ladder_4line') {
            $base4 = ($pipeSize === '32x32') ? 25 : (($pipeSize === '40x40') ? 32 : 40);
            $rateAdj = $base4 + (($pipeGauge === '2.0mm') ? 8 : 0);
        } elseif ($poleType === 'ladder_2line') {
            if ($pipeSize === 'pipe_2_5_round') {
                $rateAdj = 20;
            } else {
                $base2 = ($pipeSize === '32x32') ? 12 : (($pipeSize === '40x40') ? 18 : 24);
                $rateAdj = $base2 + (($pipeGauge === '2.0mm') ? 6 : 0);
            }
        } else {
            if ($pipeSize === '3x3') {
                $rateAdj = ($pipeGauge === '2.5mm') ? -5 : -10;
            } elseif ($pipeSize === '4x4') {
                $rateAdj = ($pipeGauge === '2.5mm') ? 0 : -5;
            } else {
                $rateAdj = ($pipeGauge === '3.0mm') ? 25 : (($pipeGauge === '2.5mm') ? 16 : 10);
            }
        }

        if ($poleType === 'ladder_4line') {
            $fullName = "Four-Lines Heavy Box Lattice Column (" . ($sizeLabels[$pipeSize] ?? $pipeSize) . ", " . ($gaugeShort[$pipeGauge] ?? $pipeGauge) . ")";
            $shortName = "4-Line Box Lattice (" . $pipeSize . " @ " . $pipeGauge . ")";
            $desc = "Four-lines square box lattice tower with 4 vertical uprights tied on all 4 faces with horizontal & diagonal ladder steps @ 1.5ft c/c, engineered for maximum cyclonic wind-load resistance.";
        } elseif ($poleType === 'ladder_2line') {
            $fullName = "Two-Lines Ladder Type Column (" . ($sizeLabels[$pipeSize] ?? $pipeSize) . ", " . ($gaugeShort[$pipeGauge] ?? $pipeGauge) . ")";
            $shortName = "2-Line Ladder (" . $pipeSize . " @ " . $pipeGauge . ")";
            $desc = "Two-lines structural ladder column featuring dual uprights with welded ladder rungs @ 1.5ft c/c for climbing access, light fixture maintenance, and high lateral wind resistance.";
        } else {
            $fullName = "Single Heavy Pipe Column (" . ($sizeLabels[$pipeSize] ?? $pipeSize) . ", " . ($gaugeShort[$pipeGauge] ?? $pipeGauge) . ")";
            $shortName = "Single Pipe (" . $pipeSize . " @ " . $pipeGauge . ")";
            $desc = "Heavy single steel section column with base gusset plates and foundation anchor bolts.";
        }

        $uprightFtRate = 560;
        if ($poleType === 'single_pipe') {
            if ($pipeSize === '3x3') $uprightFtRate = ($pipeGauge === '2.5mm') ? 420 : 360;
            elseif ($pipeSize === '4x4') $uprightFtRate = ($pipeGauge === '2.5mm') ? 520 : 450;
            else $uprightFtRate = ($pipeGauge === '3.0mm') ? 780 : (($pipeGauge === '2.5mm') ? 650 : 560);
        } elseif ($poleType === 'ladder_2line') {
            if ($pipeSize === '32x32') $uprightFtRate = ($pipeGauge === '2.0mm') ? 490 : 440;
            elseif ($pipeSize === '40x40') $uprightFtRate = ($pipeGauge === '2.0mm') ? 580 : 520;
            elseif ($pipeSize === '50x50') $uprightFtRate = ($pipeGauge === '2.0mm') ? 720 : 640;
            elseif ($pipeSize === 'pipe_2_5_round') $uprightFtRate = 780;
        } else { // ladder_4line
            if ($pipeSize === '32x32') $uprightFtRate = ($pipeGauge === '2.0mm') ? 920 : 820;
            elseif ($pipeSize === '40x40') $uprightFtRate = ($pipeGauge === '2.0mm') ? 1180 : 1020;
            elseif ($pipeSize === '50x50') $uprightFtRate = ($pipeGauge === '2.0mm') ? 1460 : 1280;
        }

        return [
            'type'                 => $poleType,
            'pole_type'            => $poleType,
            'pipe_size'            => $pipeSize,
            'pipe_gauge'           => $pipeGauge,
            'size_label'           => $sizeLabels[$pipeSize] ?? $pipeSize,
            'gauge_label'          => $gaugeLabels[$pipeGauge] ?? $pipeGauge,
            'gauge_short'          => $gaugeShort[$pipeGauge] ?? $pipeGauge,
            'full_name'            => $fullName,
            'short_name'           => $shortName,
            'desc'                 => $desc,
            'uprights_per_column'  => $uprightsCount,
            'faces_laced'          => $facesLaced,
            'has_ladder'           => ($poleType !== 'single_pipe'),
            'rate_adj'             => $rateAdj,
            'upright_ft_rate'      => $uprightFtRate,
        ];
    }
}

if (!function_exists('resolveTurnkeyCivilBedProfile')) {
    function resolveTurnkeyCivilBedProfile($civilBedType = 'compacted_wmm') {
        $profiles = [
            // --- 0. 🏢 Terrace / Rooftop Base Plate Mounting (No Ground Digging) ---
            'terrace_base_plates' => [
                'key'         => 'terrace_base_plates',
                'name'        => 'Rooftop / Terrace Base Flange Plates (M12/M16 Chemical Anchors - No Soil Digging)',
                'short_name'  => 'Terrace Base Plates (No Digging)',
                'has_civil'   => true,
                'gauge'       => '8mm–10mm Heavy MS/GI Base Flange Plates + M12/M16 Hilti/Fischer Anchor Fasteners & Bitumen Seal Pads',
                'rates'       => ['essential' => 14, 'commercial' => 18, 'premium_pro' => 24],
                'desc'        => 'Engineered steel base flange plates (8mm-10mm thick) with welded gusset stiffeners, anchored directly into existing RCC roof slab / perimeter beam using M12/M16 high-tensile mechanical wedge anchors and epoxy chemical fasteners, sealed with weatherproof bituminous waterproofing pads. Zero concrete casting or ground excavation needed.',
            ],
            'rooftop_base_plates' => [
                'key'         => 'rooftop_base_plates',
                'name'        => 'Rooftop / Terrace Base Flange Plates (M12/M16 Chemical Anchors - No Soil Digging)',
                'short_name'  => 'Terrace Base Plates (No Digging)',
                'has_civil'   => true,
                'gauge'       => '8mm–10mm Heavy MS/GI Base Flange Plates + M12/M16 Hilti/Fischer Anchor Fasteners & Bitumen Seal Pads',
                'rates'       => ['essential' => 14, 'commercial' => 18, 'premium_pro' => 24],
                'desc'        => 'Engineered steel base flange plates (8mm-10mm thick) with welded gusset stiffeners, anchored directly into existing RCC roof slab / perimeter beam using M12/M16 high-tensile mechanical wedge anchors and epoxy chemical fasteners, sealed with weatherproof bituminous waterproofing pads. Zero concrete casting or ground excavation needed.',
            ],

            // --- 1. Compacted WMM / Granular Sub-Bases (For Free Water Drainage Turf) ---
            'compacted_wmm' => [
                'key'         => 'compacted_wmm',
                'name'        => '4" Compacted WMM Base + 1" Stone Dust Layer (Natural Drainage Standard)',
                'short_name'  => '4" Compacted WMM Base',
                'has_civil'   => true,
                'gauge'       => '100mm (4") WMM compacted with 8-10 ton roller + 25mm stone dust / M-sand blinding layer',
                'rates'       => ['essential' => 32, 'commercial' => 40, 'premium_pro' => 50],
                'desc'        => 'Engineered porous sub-base consisting of 100mm (4") compacted Wet Mix Macadam (WMM) compacted to 98% Proctor density, topped with 25mm leveled stone dust screening layer, slope 1:150 for sub-surface rainwater percolation under artificial turf.',
            ],
            'compacted_wmm_4inch' => [
                'key'         => 'compacted_wmm_4inch',
                'name'        => '4" Compacted WMM Base + 1" Stone Dust Layer (Natural Drainage Standard)',
                'short_name'  => '4" Compacted WMM Base',
                'has_civil'   => true,
                'gauge'       => '100mm (4") WMM compacted with 8-10 ton roller + 25mm stone dust / M-sand blinding layer',
                'rates'       => ['essential' => 32, 'commercial' => 40, 'premium_pro' => 50],
                'desc'        => 'Engineered porous sub-base consisting of 100mm (4") compacted Wet Mix Macadam (WMM) compacted to 98% Proctor density, topped with 25mm leveled stone dust screening layer, slope 1:150 for sub-surface rainwater percolation under artificial turf.',
            ],
            'compacted_wmm_6inch' => [
                'key'         => 'compacted_wmm_6inch',
                'name'        => '6" Heavy Compacted WMM / GSB Sub-Base + 1" Stone Dust (Expansive Soils / Large Pitches)',
                'short_name'  => '6" Heavy WMM/GSB Base',
                'has_civil'   => true,
                'gauge'       => '150mm (6") heavy WMM/GSB base compacted in 2 layers + 25mm stone dust leveling',
                'rates'       => ['essential' => 42, 'commercial' => 50, 'premium_pro' => 62],
                'desc'        => '150mm (6") dual-layer heavy Wet Mix Macadam (WMM) or Granular Sub-Base (GSB) compacted to 98% Proctor density, ideal for large football grounds, outdoor multi-arenas, and expansive clay subgrades.',
            ],

            // --- 2. 3-Inch Plain Cement Concrete (M15 Grade - Non-Reinforced) [75mm Depth] ---
            'concrete_pcc_3inch' => [
                'key'         => 'concrete_pcc_3inch',
                'name'        => '3" Plain Cement Concrete (M15 Non-Reinforced) - Broom / Brush Textured Finish',
                'short_name'  => '3" PCC M15 (Broom Finish)',
                'has_civil'   => true,
                'gauge'       => '75mm (3") PCC M15 Grade (1:2:4) over 75mm compacted aggregate base with broom texture',
                'rates'       => ['essential' => 44, 'commercial' => 55, 'premium_pro' => 68],
                'desc'        => '75mm (3") thick M15 grade non-reinforced plain cement concrete slab with transverse broom/brush textured finish providing high mechanical interlock for synthetic turf, modular tiles, and vinyl mat adhesives.',
            ],
            'pcc_m15_3inch_broom' => [
                'key'         => 'pcc_m15_3inch_broom',
                'name'        => '3" Plain Cement Concrete (M15 Non-Reinforced) - Broom / Brush Textured Finish',
                'short_name'  => '3" PCC M15 (Broom Finish)',
                'has_civil'   => true,
                'gauge'       => '75mm (3") PCC M15 Grade (1:2:4) over 75mm compacted aggregate base with broom texture',
                'rates'       => ['essential' => 44, 'commercial' => 55, 'premium_pro' => 68],
                'desc'        => '75mm (3") thick M15 grade non-reinforced plain cement concrete slab with transverse broom/brush textured finish providing high mechanical interlock for synthetic turf, modular tiles, and vinyl mat adhesives.',
            ],
            'pcc_m15_3inch_smooth' => [
                'key'         => 'pcc_m15_3inch_smooth',
                'name'        => '3" Plain Cement Concrete (M15 Non-Reinforced) - Smooth Steel Troweled Finish',
                'short_name'  => '3" PCC M15 (Smooth Finish)',
                'has_civil'   => true,
                'gauge'       => '75mm (3") PCC M15 Grade (1:2:4) with hand/power steel troweled smooth level finish',
                'rates'       => ['essential' => 48, 'commercial' => 60, 'premium_pro' => 74],
                'desc'        => '75mm (3") thick M15 grade plain cement concrete finished with power steel troweling for a uniform smooth surface, engineered for multi-layer synthetic acrylic hardcourts and indoor matting.',
            ],
            'pcc_m15_3inch_tremix' => [
                'key'         => 'pcc_m15_3inch_tremix',
                'name'        => '3" Plain Cement Concrete (M15 Non-Reinforced) - Vacuum Dewatered Tremix Flooring',
                'short_name'  => '3" PCC M15 (Tremix Finish)',
                'has_civil'   => true,
                'gauge'       => '75mm (3") PCC M15 Grade with Tremix vacuum dewatering, screed vibrator, power floater & saw-cut joints',
                'rates'       => ['essential' => 54, 'commercial' => 68, 'premium_pro' => 82],
                'desc'        => '75mm (3") thick M15 plain cement concrete dewatered with vacuum suction mats and finished with power disc floater, groove-cut control joints @ 3m c/c, giving 25% higher compressive surface density without steel reinforcement.',
            ],

            // --- 3. 4-Inch Plain Cement Concrete (M15 Grade - Non-Reinforced) [100mm Depth] ---
            'pcc_m15_4inch_broom' => [
                'key'         => 'pcc_m15_4inch_broom',
                'name'        => '4" Plain Cement Concrete (M15 Non-Reinforced) - Heavy Broom Textured Bed',
                'short_name'  => '4" PCC M15 (Broom Finish)',
                'has_civil'   => true,
                'gauge'       => '100mm (4") PCC M15 Grade (1:2:4) over 100mm compacted GSB with anti-slip broom texture',
                'rates'       => ['essential' => 56, 'commercial' => 70, 'premium_pro' => 86],
                'desc'        => '100mm (4") robust non-reinforced M15 concrete bed with medium-rough broom finish, ideal for commercial box cricket arenas, heavy turf installations, and perimeter ring beams.',
            ],
            'pcc_m15_4inch_smooth' => [
                'key'         => 'pcc_m15_4inch_smooth',
                'name'        => '4" Plain Cement Concrete (M15 Non-Reinforced) - Smooth Laser Screeded Level Bed',
                'short_name'  => '4" PCC M15 (Smooth Laser Bed)',
                'has_civil'   => true,
                'gauge'       => '100mm (4") PCC M15 Grade with laser guide leveling, power trowel smooth finish, slope 1:120',
                'rates'       => ['essential' => 60, 'commercial' => 76, 'premium_pro' => 94],
                'desc'        => '100mm (4") thick M15 grade plain cement concrete laser screeded to strict ±3mm level tolerance with 1:120 perimeter drainage slope, zero birdbath standard for acrylic tennis, basketball, and pickleball.',
            ],
            'pcc_m15_4inch_tremix' => [
                'key'         => 'pcc_m15_4inch_tremix',
                'name'        => '4" Plain Cement Concrete (M15 Non-Reinforced) - Vacuum Dewatered Tremix + Groove Joints',
                'short_name'  => '4" PCC M15 (Tremix Heavy)',
                'has_civil'   => true,
                'gauge'       => '100mm (4") PCC M15 Grade vacuum dewatered, surface vibrated, power floated with 4mm groove cut joints @ 3m c/c',
                'rates'       => ['essential' => 68, 'commercial' => 84, 'premium_pro' => 105],
                'desc'        => '100mm (4") vacuum dewatered Tremix M15 concrete slab with power troweled wear layer, saw-cut contraction joints filled with polyurethane joint sealant, delivering long-term crack-resistant tournament sports flooring.',
            ],
            'pcc_m15_4inch_ips' => [
                'key'         => 'pcc_m15_4inch_ips',
                'name'        => '4" Plain Cement Concrete (M15 Non-Reinforced) + IPS Floor Hardener Polish Finish',
                'short_name'  => '4" PCC M15 (IPS Hardener)',
                'has_civil'   => true,
                'gauge'       => '100mm (4") PCC M15 + 12mm monolithic Indian Patent Stone (IPS) metallic/non-metallic floor hardener polish',
                'rates'       => ['essential' => 72, 'commercial' => 90, 'premium_pro' => 112],
                'desc'        => '100mm (4") M15 plain cement concrete topped with monolithic 12mm high-abrasion IPS hardener power burnished to a smooth abrasion-resistant polish for high-traffic multi-sport and roller skating facilities.',
            ],

            // --- 4. 5-Inch & 6-Inch Heavy M15 Concrete Beds [125mm & 150mm Depth] ---
            'pcc_m15_5inch_tremix' => [
                'key'         => 'pcc_m15_5inch_tremix',
                'name'        => '5" Heavy Plain Cement Concrete (M15 Non-Reinforced) - Tremix Dewatered Slab',
                'short_name'  => '5" PCC M15 (Tremix Heavy Bed)',
                'has_civil'   => true,
                'gauge'       => '125mm (5") PCC M15 Grade vacuum dewatered Tremix slab over 100mm compacted GSB',
                'rates'       => ['essential' => 78, 'commercial' => 98, 'premium_pro' => 122],
                'desc'        => '125mm (5") heavy non-reinforced M15 concrete foundation slab laid with industrial Tremix dewatering for heavy commercial multi-court arenas and soft subsoil conditions.',
            ],
            'pcc_m15_6inch_tremix' => [
                'key'         => 'pcc_m15_6inch_tremix',
                'name'        => '6" Extra-Heavy Plain Cement Concrete (M15 Non-Reinforced) - Tremix High-Load Bed',
                'short_name'  => '6" PCC M15 (Tremix 6" Bed)',
                'has_civil'   => true,
                'gauge'       => '150mm (6") PCC M15 Grade vacuum dewatered Tremix slab over 150mm compacted aggregate sub-base',
                'rates'       => ['essential' => 92, 'commercial' => 116, 'premium_pro' => 145],
                'desc'        => '150mm (6") extra-thick monolithic M15 plain cement concrete slab with vacuum dewatering and saw-cut relief joints, engineered for black cotton soil, high water table, or multi-purpose tournament complexes.',
            ],

            // --- 5. Asphalt / Bitumen Base ---
            'asphalt_bitumen' => [
                'key'         => 'asphalt_bitumen',
                'name'        => 'Dual-Layer Machine-Laid Asphalt Base (ITF Tennis & Track Standard)',
                'short_name'  => 'Machine-Laid Asphalt Base',
                'has_civil'   => true,
                'gauge'       => '40mm Dense Bituminous Macadam (DBM) + 25mm Bituminous Concrete (BC) over 150mm WMM',
                'rates'       => ['essential' => 65, 'commercial' => 82, 'premium_pro' => 105],
                'desc'        => 'Machine-laid asphalt base complying with international tennis/multi-sport federation standards with zero birdbaths, high flexural resilience, and laser-grade slope.',
            ],

            // --- 6. Natural Stabilized Earth ---
            'laser_graded_earth' => [
                'key'         => 'laser_graded_earth',
                'name'        => 'Laser-Graded Stabilized Natural Earth Base (Economic / Rural Standard)',
                'short_name'  => 'Laser-Graded Earth Base',
                'has_civil'   => true,
                'gauge'       => 'Compacted sub-grade with lime/cement stabilization + stone dust capping',
                'rates'       => ['essential' => 18, 'commercial' => 24, 'premium_pro' => 30],
                'desc'        => 'Laser-guided grading and mechanical vibratory roller compaction of native soil with stone dust screening for economic open grounds.',
            ],

            // --- 7. 🏗️ REINFORCED CONCRETE (RCC - Steel Reinforced) - Placed at LAST as Highest Rate Variants ---
            'concrete_rcc_4inch' => [
                'key'         => 'concrete_rcc_4inch',
                'name'        => '4" Reinforced Concrete Slab (RCC M20) with 6mm Welded Wire Mesh Reinforcement',
                'short_name'  => '4" RCC M20 (6mm Wire Mesh)',
                'has_civil'   => true,
                'gauge'       => '100mm (4") RCC M20 Grade with 6mm welded steel wire fabric mesh @ 150mm c/c + power troweled finish',
                'rates'       => ['essential' => 88, 'commercial' => 110, 'premium_pro' => 138],
                'desc'        => '100mm (4") structural reinforced cement concrete slab (M20 grade) with 6mm welded wire mesh reinforcement, ideal for rooftop sports installations, filled ground, or weak subsoils requiring structural tensile strength.',
            ],
            'rcc_m20_4inch_mesh' => [
                'key'         => 'rcc_m20_4inch_mesh',
                'name'        => '4" Reinforced Concrete Slab (RCC M20) with 6mm Welded Wire Mesh Reinforcement',
                'short_name'  => '4" RCC M20 (6mm Wire Mesh)',
                'has_civil'   => true,
                'gauge'       => '100mm (4") RCC M20 Grade with 6mm welded steel wire fabric mesh @ 150mm c/c + power troweled finish',
                'rates'       => ['essential' => 88, 'commercial' => 110, 'premium_pro' => 138],
                'desc'        => '100mm (4") structural reinforced cement concrete slab (M20 grade) with 6mm welded wire mesh reinforcement, ideal for rooftop sports installations, filled ground, or weak subsoils requiring structural tensile strength.',
            ],
            'rcc_m20_5inch_tmt' => [
                'key'         => 'rcc_m20_5inch_tmt',
                'name'        => '5" Reinforced Concrete Slab (RCC M20) with 8mm Fe500D TMT Rebar Grid @ 150mm c/c',
                'short_name'  => '5" RCC M20 (8mm TMT Rebars)',
                'has_civil'   => true,
                'gauge'       => '125mm (5") RCC M20 Grade with 8mm Fe500D TMT rebar grid @ 150mm c/c both ways + Tremix vacuum dewatered',
                'rates'       => ['essential' => 105, 'commercial' => 132, 'premium_pro' => 165],
                'desc'        => '125mm (5") heavy structural reinforced concrete slab with 8mm TMT steel rebar cage and vacuum dewatered Tremix finish, engineered for rooftop commercial stadiums, elevated slabs, and extreme load bearing.',
            ],
            'rcc_m25_6inch_tmt' => [
                'key'         => 'rcc_m25_6inch_tmt',
                'name'        => '6" Heavy Reinforced Concrete Slab (RCC M25) with Dual 8mm/10mm TMT Steel Reinforcement Cage',
                'short_name'  => '6" RCC M25 (Dual TMT Cage)',
                'has_civil'   => true,
                'gauge'       => '150mm (6") RCC M25 Grade with top & bottom 8mm/10mm TMT rebar mat + Tremix finish',
                'rates'       => ['essential' => 128, 'commercial' => 160, 'premium_pro' => 200],
                'desc'        => '150mm (6") heavy-duty M25 grade reinforced cement concrete slab with dual-layer TMT steel reinforcement cage, designed for elevated multi-tier commercial sports complexes and heavy machinery access.',
            ],

            // --- 8. Client Scope (Zero Cost) ---
            'none' => [
                'key'         => 'none',
                'name'        => 'None / Existing Floor (₹0 in BOQ)',
                'short_name'  => 'None / Existing Floor (₹0)',
                'has_civil'   => false,
                'gauge'       => 'Client scope / existing concrete or terrace slab (zero civil cost in GCM BOQ)',
                'rates'       => ['essential' => 0, 'commercial' => 0, 'premium_pro' => 0],
                'desc'        => 'Civil sub-base and floor slab provided and prepared directly by client or existing floor. Zero civil excavation, base preparation, or concrete casting charges.',
            ],
            'client_scope' => [
                'key'         => 'client_scope',
                'name'        => 'Civil Bed Executed by Client (Zero Cost in GCM BOQ)',
                'short_name'  => 'Civil Bed by Client Scope',
                'has_civil'   => false,
                'gauge'       => 'Civil ground preparation, sub-base compaction, or concrete bed by client scope',
                'rates'       => ['essential' => 0, 'commercial' => 0, 'premium_pro' => 0],
                'desc'        => 'Civil sub-base and floor slab provided and prepared directly by client as per GCM engineering technical drawing specifications.',
            ],
        ];

        return $profiles[$civilBedType] ?? $profiles['compacted_wmm'];
    }
}

if (!function_exists('getTurnkeyCivilBedOptions')) {
    function getTurnkeyCivilBedOptions() {
        return [
            'terrace_base_plates'   => '🏢 Terrace / Rooftop Base Plates (M12/M16 Chemical Anchors) (~₹18/SFT) [No Digging]',
            'compacted_wmm'         => '🚜 4" Compacted WMM Base + 1" Stone Dust (~₹40/SFT) - Ideal for Turf Drainage',
            'compacted_wmm_6inch'   => '🚜 6" Heavy Compacted WMM / GSB Base (~₹50/SFT) - Heavy Drainage / Expansive Soil',
            'pcc_m15_3inch_broom'   => '🧱 3" PCC M15 Broom Finish (~₹55/SFT) - For Turf / Tiles / Vinyl Adhesion',
            'pcc_m15_3inch_smooth'  => '🧱 3" PCC M15 Smooth Troweled (~₹60/SFT) - For Acrylic / Indoor Surfaces',
            'pcc_m15_3inch_tremix'  => '🧱 3" PCC M15 Vacuum Tremix (~₹68/SFT) - Zero Dusting / Dense Bed',
            'pcc_m15_4inch_broom'   => '🧱 4" PCC M15 Broom Textured (~₹70/SFT) - Robust Turf / Box Cricket Bed',
            'pcc_m15_4inch_smooth'  => '🧱 4" PCC M15 Smooth Laser Screeded (~₹76/SFT) - ITF Acrylic Hardcourts',
            'pcc_m15_4inch_tremix'  => '🧱 4" PCC M15 Vacuum Dewatered Tremix (~₹84/SFT) - Commercial Sports Standard',
            'pcc_m15_4inch_ips'     => '🧱 4" PCC M15 + IPS Floor Hardener (~₹90/SFT) - Monolithic Wear Finish',
            'pcc_m15_5inch_tremix'  => '🧱 5" PCC M15 Heavy Tremix Slab (~₹98/SFT) - Multi-Court Commercial Arenas',
            'pcc_m15_6inch_tremix'  => '🧱 6" PCC M15 Extra-Heavy Tremix Bed (~₹116/SFT) - Black Cotton Soil / Heavy Load',
            'asphalt_bitumen'       => '🛣️ Dual-Layer Machine-Laid Asphalt Base (~₹82/SFT) - ITF Tennis Standard',
            'laser_graded_earth'    => '🌱 Laser-Graded Stabilized Earth Base (~₹24/SFT) - Rural/Economic Turf Base',
            'rcc_m20_4inch_mesh'    => '🏗️ 4" RCC M20 + 6mm Wire Mesh (~₹110/SFT) [Reinforced - Rooftops/Weak Soil]',
            'rcc_m20_5inch_tmt'     => '🏗️ 5" RCC M20 + 8mm TMT Rebar Grid (~₹132/SFT) [Reinforced - Heavy Deck]',
            'rcc_m25_6inch_tmt'     => '🏗️ 6" RCC M25 + Dual TMT Steel Cage (~₹160/SFT) [Reinforced - Commercial Stadium]',
            'client_scope'          => '🤝 Civil Bed by Client Scope (₹0 in BOQ)',
        ];
    }
}

if (!function_exists('getTurnkeyPoleStyles')) {
    function getTurnkeyPoleStyles() {
        return [
            'ladder_2line' => [
                'key'           => 'ladder_2line',
                'name'          => 'Two-Lines Ladder Type Column',
                'badge'         => '🪜 2-Line Ladder (Recommended)',
                'desc'          => 'Dual vertical uprights welded with ladder steps @ 1.5ft c/c for climbing access & wind rigidity.',
                'popular'       => true,
                'default_size'  => '40x40',
                'default_gauge' => '2.0mm',
            ],
            'ladder_4line' => [
                'key'           => 'ladder_4line',
                'name'          => 'Four-Lines Heavy Box Lattice Column',
                'badge'         => '🏰 4-Line Box Lattice',
                'desc'          => 'Heavy 4-upright box lattice tower for high wind cyclonic zones and 30-40ft heights.',
                'popular'       => false,
                'default_size'  => '40x40',
                'default_gauge' => '2.0mm',
            ],
            'single_pipe' => [
                'key'           => 'single_pipe',
                'name'          => 'Single Heavy Pipe Column',
                'badge'         => '🏛️ Single Heavy Pipe',
                'desc'          => 'Heavy 3"x3", 4"x4", or 5"x5" single square steel column.',
                'popular'       => false,
                'default_size'  => '4x4',
                'default_gauge' => '2.5mm',
            ],
        ];
    }
}

if (!function_exists('getTurnkeyCourts')) {
    function getTurnkeyCourts() {
        return [
            'box_cricket_std' => [
                'key'           => 'box_cricket_std',
                'name'          => 'Box Cricket Court (Standard 100x50)',
                'badge'         => '🏏 Box Cricket 100x50',
                'default_l'     => 100,
                'default_w'     => 50,
                'default_h'     => 25,
                'sport'         => 'box_cricket',
                'roof'          => 'light_cable_top',
                'desc'          => '100 x 50 FT standard tournament-grade box cricket arena with 25 FT clear overhead height.',
                'popular'       => true,
            ],
            'box_cricket_large' => [
                'key'           => 'box_cricket_large',
                'name'          => 'Box Cricket Arena (Large 140x70)',
                'badge'         => '🏏 Box Cricket 140x70',
                'default_l'     => 140,
                'default_w'     => 70,
                'default_h'     => 30,
                'sport'         => 'box_cricket',
                'roof'          => 'light_cable_top',
                'desc'          => '140 x 70 FT large premium commercial box cricket arena with 30 FT clear height.',
                'popular'       => true,
            ],
            'cricket_circular' => [
                'key'           => 'cricket_circular',
                'name'          => 'Circular Cricket Ground (140 FT Dia)',
                'badge'         => "🏏 Circular Cricket 140 FT",
                'default_l'     => 140,
                'default_w'     => 140,
                'default_h'     => 30,
                'sport'         => 'cricket_circular',
                'roof'          => 'light_cable_top',
                'desc'          => '140 FT Diameter circular cricket arena with 360-degree perimeter netting and central match pitch.',
                'popular'       => true,
            ],
            'cricket_oval' => [
                'key'           => 'cricket_oval',
                'name'          => 'Oval Cricket Ground (180x120 FT)',
                'badge'         => '🏏 Oval Cricket 180x120',
                'default_l'     => 180,
                'default_w'     => 120,
                'default_h'     => 30,
                'sport'         => 'cricket_oval',
                'roof'          => 'open_top',
                'desc'          => '180 x 120 FT oval cricket ground with boundary rope, center pitch, and high perimeter netting.',
                'popular'       => true,
            ],
            'football_5a_side' => [
                'key'           => 'football_5a_side',
                'name'          => '5-a-Side Football Turf (100x60 FT / 6k SFT)',
                'badge'         => '⚽ Football 5v5 (100x60)',
                'default_l'     => 100,
                'default_w'     => 60,
                'default_h'     => 25,
                'sport'         => 'football_5a',
                'roof'          => 'light_cable_top',
                'desc'          => '100 x 60 FT 5-a-side football turf with 50mm FIFA turf, goalposts, and 100mm heavy netting.',
                'popular'       => true,
            ],
            'football_6a_side' => [
                'key'           => 'football_6a_side',
                'name'          => '6-a-Side Football Arena (110x70 FT / 7.7k SFT)',
                'badge'         => '⚽ Football 6v6 (110x70)',
                'default_l'     => 110,
                'default_w'     => 70,
                'default_h'     => 25,
                'sport'         => 'football_6a',
                'roof'          => 'light_cable_top',
                'desc'          => '110 x 70 FT (7,700 SFT) 6-a-side football arena with 12x6.5 goalposts and 100mm braided netting.',
                'popular'       => true,
            ],
            'football_7a_side' => [
                'key'           => 'football_7a_side',
                'name'          => '7-a-Side Football Arena (160x100 FT / 16k SFT)',
                'badge'         => '⚽ Football 7v7 (160x100)',
                'default_l'     => 160,
                'default_w'     => 100,
                'default_h'     => 30,
                'sport'         => 'football_7a',
                'roof'          => 'light_cable_top',
                'desc'          => '160 x 100 FT 7-a-side football arena with heavy 16x7 goalposts and 100mm braided netting.',
                'popular'       => true,
            ],
            'football_8a_compact' => [
                'key'           => 'football_8a_compact',
                'name'          => '8-a-Side Compact Football Turf (150x80 FT / 12k SFT)',
                'badge'         => '⚽ Football 8v8 (12,000 SFT)',
                'default_l'     => 150,
                'default_w'     => 80,
                'default_h'     => 28,
                'sport'         => 'football_8a_compact',
                'roof'          => 'light_cable_top',
                'desc'          => '150 x 80 FT (12,000 SFT) compact 8-a-side high-yield commercial sports arena with goalposts and heavy netting.',
                'popular'       => true,
            ],
            'football_8a_standard' => [
                'key'           => 'football_8a_standard',
                'name'          => '8-a-Side Standard Football Arena (160x100 FT / 16k SFT)',
                'badge'         => '⚽ Football 8v8 (16,000 SFT)',
                'default_l'     => 160,
                'default_w'     => 100,
                'default_h'     => 30,
                'sport'         => 'football_8a',
                'roof'          => 'light_cable_top',
                'desc'          => '160 x 100 FT (16,000 SFT) full regulation 8-a-side sports arena with 18x7 goalposts and 100mm netting.',
                'popular'       => true,
            ],
            'football_9a_side' => [
                'key'           => 'football_9a_side',
                'name'          => '9-a-Side Football Ground (220x140 FT / 30.8k SFT)',
                'badge'         => '⚽ Football 9v9 (220x140)',
                'default_l'     => 220,
                'default_w'     => 140,
                'default_h'     => 35,
                'sport'         => 'football_9a',
                'roof'          => 'open_top',
                'desc'          => '220 x 140 FT 9-a-side football ground with 21x7 goalposts and high containment netting.',
                'popular'       => false,
            ],
            'football_10a_side' => [
                'key'           => 'football_10a_side',
                'name'          => '10-a-Side Football Ground (220x140 FT / 30.8k SFT)',
                'badge'         => '⚽ Football 10v10 (220x140)',
                'default_l'     => 220,
                'default_w'     => 140,
                'default_h'     => 35,
                'sport'         => 'football_10a',
                'roof'          => 'open_top',
                'desc'          => '220 x 140 FT (30,800 SFT) 10-a-side football stadium with 21x7.5 goalposts and heavy perimeter ball-containment netting.',
                'popular'       => false,
            ],
            'football_11a_side' => [
                'key'           => 'football_11a_side',
                'name'          => '11-a-Side Full Football Stadium (320x200 FT)',
                'badge'         => '⚽ Football 11v11 (320x200)',
                'default_l'     => 320,
                'default_w'     => 200,
                'default_h'     => 35,
                'sport'         => 'football_11a',
                'roof'          => 'open_top',
                'desc'          => '320 x 200 FT FIFA regulation 11-a-side pitch with official 24x8 goalposts and high ball-stop nets.',
                'popular'       => false,
            ],
            'futsal_court' => [
                'key'           => 'futsal_court',
                'name'          => 'Official Futsal Arena (130x75 FT)',
                'badge'         => '🥅 Futsal 130x75',
                'default_l'     => 130,
                'default_w'     => 75,
                'default_h'     => 25,
                'sport'         => 'futsal',
                'roof'          => 'light_cable_top',
                'desc'          => '130 x 75 FT official futsal arena with 3m x 2m goalposts and professional court line markings.',
                'popular'       => true,
            ],
            'badminton_single' => [
                'key'           => 'badminton_single',
                'name'          => 'Badminton Single Court (60x30 FT)',
                'badge'         => '🏸 Badminton Single (60x30)',
                'default_l'     => 60,
                'default_w'     => 30,
                'default_h'     => 25,
                'sport'         => 'badminton',
                'roof'          => 'light_cable_top',
                'desc'          => '60 x 30 FT enclosed badminton court with BWF vinyl mat, regulation posts & tournament net.',
                'popular'       => true,
            ],
            'badminton_multi_2' => [
                'key'           => 'badminton_multi_2',
                'name'          => 'Badminton 2-Court Complex (60x60 FT)',
                'badge'         => '🏸 Badminton 2-Court (60x60)',
                'default_l'     => 60,
                'default_w'     => 60,
                'default_h'     => 28,
                'sport'         => 'badminton_multi',
                'roof'          => 'light_cable_top',
                'desc'          => '60 x 60 FT dual-court badminton complex with court divider nets and glare-free lighting.',
                'popular'       => true,
            ],
            'basketball_half' => [
                'key'           => 'basketball_half',
                'name'          => 'Basketball Half Court (47x50 FT)',
                'badge'         => '🏀 Basketball Half (47x50)',
                'default_l'     => 47,
                'default_w'     => 50,
                'default_h'     => 25,
                'sport'         => 'basketball_half',
                'roof'          => 'light_cable_top',
                'desc'          => '47 x 50 FT half-court basketball with 8-layer acrylic cushion, glass backboard & pro breakaway rim.',
                'popular'       => true,
            ],
            'basketball_full' => [
                'key'           => 'basketball_full',
                'name'          => 'Basketball Full Court (94x50 FT)',
                'badge'         => '🏀 Basketball Full (94x50)',
                'default_l'     => 94,
                'default_w'     => 50,
                'default_h'     => 28,
                'sport'         => 'basketball_full',
                'roof'          => 'light_cable_top',
                'desc'          => '94 x 50 FT official FIBA regulation basketball court with dual glass backboards & full line markings.',
                'popular'       => true,
            ],
            'tennis_court' => [
                'key'           => 'tennis_court',
                'name'          => 'ITF Standard Tennis Court (120x60 FT)',
                'badge'         => '🎾 Tennis Court (120x60)',
                'default_l'     => 120,
                'default_w'     => 60,
                'default_h'     => 25,
                'sport'         => 'tennis',
                'roof'          => 'light_cable_top',
                'desc'          => '120 x 60 FT standard tennis court footprint with 8-layer acrylic cushion, brass winder posts & net.',
                'popular'       => true,
            ],
            'pickleball_single' => [
                'key'           => 'pickleball_single',
                'name'          => 'Pickleball Single Court (60x30 FT)',
                'badge'         => '🏓 Pickleball Single (60x30)',
                'default_l'     => 60,
                'default_w'     => 30,
                'default_h'     => 20,
                'sport'         => 'pickleball',
                'roof'          => 'light_cable_top',
                'desc'          => '60 x 30 FT dedicated pickleball court with acrylic cushion surface, regulation 22ft net & kitchen lines.',
                'popular'       => true,
            ],
            'pickleball_multi_2' => [
                'key'           => 'pickleball_multi_2',
                'name'          => 'Pickleball 2-Court Complex (60x60 FT)',
                'badge'         => '🏓 Pickleball 2-Court (60x60)',
                'default_l'     => 60,
                'default_w'     => 60,
                'default_h'     => 20,
                'sport'         => 'pickleball_multi',
                'roof'          => 'light_cable_top',
                'desc'          => '60 x 60 FT dual-court pickleball facility with center divider net and tournament posts.',
                'popular'       => true,
            ],
            'multi_cricket_football' => [
                'key'           => 'multi_cricket_football',
                'name'          => 'Multi-Sport (Half Cricket + Half Football)',
                'badge'         => '🏏⚽ Cricket + Football (120x65)',
                'default_l'     => 120,
                'default_w'     => 65,
                'default_h'     => 28,
                'sport'         => 'multi_sport',
                'roof'          => 'light_cable_top',
                'desc'          => '120 x 65 FT multi-sport facility with central cricket pitch and 1 pair futsal/football goalposts.',
                'popular'       => true,
            ],
            'multi_futsal_basket' => [
                'key'           => 'multi_futsal_basket',
                'name'          => 'Multi-Sport (Futsal + Basketball 110x60)',
                'badge'         => '⚽🏀 Futsal + Basketball (110x60)',
                'default_l'     => 110,
                'default_w'     => 60,
                'default_h'     => 25,
                'sport'         => 'multi_futsal_basket',
                'roof'          => 'light_cable_top',
                'desc'          => '110 x 60 FT multi-purpose court with modular tiles, integrated futsal goal & basketball hoop combos.',
                'popular'       => true,
            ],
            'terrace_cricket_small' => [
                'key'           => 'terrace_cricket_small',
                'name'          => 'Terrace Cricket Net (Small 30x15 FT / 12\'H)',
                'badge'         => '🏢 Terrace Net 30x15 (12\'H)',
                'default_l'     => 30,
                'default_w'     => 15,
                'default_h'     => 12,
                'sport'         => 'terrace_cricket_small',
                'roof'          => 'light_cable_top',
                'desc'          => '30 x 15 FT compact terrace cricket net with 12 FT height, base flange plates, and full overhead enclosure.',
                'popular'       => true,
            ],
            'terrace_cricket_lane' => [
                'key'           => 'terrace_cricket_lane',
                'name'          => 'Terrace Cricket Practice Lane (50x20 FT / 14\'H)',
                'badge'         => '🏢 Terrace Lane 50x20 (14\'H)',
                'default_l'     => 50,
                'default_w'     => 20,
                'default_h'     => 14,
                'sport'         => 'terrace_cricket_lane',
                'roof'          => 'light_cable_top',
                'desc'          => '50 x 20 FT terrace cricket practice lane with 14 FT height and overhead containment.',
                'popular'       => true,
            ],
            'terrace_cricket_box' => [
                'key'           => 'terrace_cricket_box',
                'name'          => 'Terrace Box Cricket & Multi-Sport (60x30 FT / 16\'H)',
                'badge'         => '🏢 Terrace Box 60x30 (16\'H)',
                'default_l'     => 60,
                'default_w'     => 30,
                'default_h'     => 16,
                'sport'         => 'terrace_cricket_box',
                'roof'          => 'light_cable_top',
                'desc'          => '60 x 30 FT terrace box cricket arena with 16 FT height and base plate anchoring.',
                'popular'       => true,
            ],
            'terrace_sports_arena' => [
                'key'           => 'terrace_sports_arena',
                'name'          => 'Rooftop Multi-Sport Arena (80x40 FT / 18\'H)',
                'badge'         => '🏢 Rooftop Arena 80x40 (18\'H)',
                'default_l'     => 80,
                'default_w'     => 40,
                'default_h'     => 18,
                'sport'         => 'terrace_sports_arena',
                'roof'          => 'light_cable_top',
                'desc'          => '80 x 40 FT rooftop multi-sport complex with 18 FT height, dual goalposts & cricket pitch.',
                'popular'       => true,
            ],
            'custom' => [
                'key'           => 'custom',
                'name'          => 'Custom Engineered Court',
                'badge'         => '📐 Custom Sized',
                'default_l'     => 90,
                'default_w'     => 60,
                'default_h'     => 25,
                'sport'         => 'box_cricket',
                'roof'          => 'light_cable_top',
                'desc'          => 'Fully custom dimensions tailored to your site boundaries.',
                'popular'       => false,
            ],
        ];
    }
}

if (!function_exists('resolveTurnkeySideNetProfile')) {
    function resolveTurnkeySideNetProfile($key = 'braided_2_5mm_45mm') {
        $profiles = [
            'braided_2_5mm_45mm' => [
                'key'        => 'braided_2_5mm_45mm',
                'name'       => '2.5mm Braided HDPE (45mm Mesh - Recommended Standard)',
                'twine'      => '2.5mm UV-Stabilized High-Tenacity Braided HDPE',
                'mesh'       => '45mm Square Mesh',
                'full_desc'  => '2.5mm UV-stabilized high-tenacity HDPE braided netting (45mm square mesh) with heavy 8mm reinforced perimeter border ropes',
                'rate_adj'   => 0,
                'net_rate'   => 13.50,
            ],
            'braided_2_5mm_40mm' => [
                'key'        => 'braided_2_5mm_40mm',
                'name'       => '2.5mm Braided HDPE (40mm Mesh - Tournament)',
                'twine'      => '2.5mm UV-Stabilized High-Tenacity Braided HDPE',
                'mesh'       => '40mm Square Mesh',
                'full_desc'  => '2.5mm UV-stabilized high-tenacity HDPE braided netting (40mm square mesh) with 8mm reinforced borders for heavy cricket shot containment',
                'rate_adj'   => 1,
                'net_rate'   => 15.00,
            ],
            'braided_2_5mm_35mm' => [
                'key'        => 'braided_2_5mm_35mm',
                'name'       => '2.5mm Braided HDPE (35mm Mesh - Compact Impact)',
                'twine'      => '2.5mm UV-Stabilized High-Tenacity Braided HDPE',
                'mesh'       => '35mm Square Mesh',
                'full_desc'  => '2.5mm dense braided HDPE netting (35mm square mesh) with 8mm border rope for tight containment and zero ball bounce-through',
                'rate_adj'   => 2,
                'net_rate'   => 16.50,
            ],
            'braided_2_5mm_50mm' => [
                'key'        => 'braided_2_5mm_50mm',
                'name'       => '2.5mm Braided HDPE (50mm Mesh - Multi-Sport)',
                'twine'      => '2.5mm UV-Stabilized Braided HDPE',
                'mesh'       => '50mm Square Mesh',
                'full_desc'  => '2.5mm UV-treated braided HDPE netting (50mm square mesh) with reinforced borders',
                'rate_adj'   => -1,
                'net_rate'   => 12.50,
            ],
            'braided_2_0mm_45mm' => [
                'key'        => 'braided_2_0mm_45mm',
                'name'       => '2.0mm Braided HDPE (45mm Mesh)',
                'twine'      => '2.0mm UV-Stabilized Braided HDPE',
                'mesh'       => '45mm Square Mesh',
                'full_desc'  => '2.0mm UV-treated braided HDPE netting (45mm square mesh)',
                'rate_adj'   => -2,
                'net_rate'   => 11.50,
            ],
            'braided_2_0mm_40mm' => [
                'key'        => 'braided_2_0mm_40mm',
                'name'       => '2.0mm Braided HDPE (40mm Mesh)',
                'twine'      => '2.0mm UV-Stabilized Braided HDPE',
                'mesh'       => '40mm Square Mesh',
                'full_desc'  => '2.0mm UV-treated braided HDPE netting (40mm square mesh)',
                'rate_adj'   => -1,
                'net_rate'   => 12.50,
            ],
            'braided_2_0mm_50mm' => [
                'key'        => 'braided_2_0mm_50mm',
                'name'       => '2.0mm Braided HDPE (50mm Mesh)',
                'twine'      => '2.0mm UV-Stabilized Braided HDPE',
                'mesh'       => '50mm Square Mesh',
                'full_desc'  => '2.0mm UV-treated braided HDPE netting (50mm square mesh)',
                'rate_adj'   => -3,
                'net_rate'   => 10.50,
            ],
            'braided_3_0mm_50mm' => [
                'key'        => 'braided_3_0mm_50mm',
                'name'       => '3.0mm Heavy Braided HDPE (50mm Mesh)',
                'twine'      => '3.0mm Heavy-Duty Braided HDPE',
                'mesh'       => '50mm Square Mesh',
                'full_desc'  => '3.0mm extra heavy-duty UV-stabilized braided HDPE netting (50mm square mesh) for high impact resistance',
                'rate_adj'   => 4,
                'net_rate'   => 16.50,
            ],
            'braided_3_0mm_45mm' => [
                'key'        => 'braided_3_0mm_45mm',
                'name'       => '3.0mm Heavy Braided HDPE (45mm Mesh)',
                'twine'      => '3.0mm Heavy-Duty Braided HDPE',
                'mesh'       => '45mm Square Mesh',
                'full_desc'  => '3.0mm heavy-duty UV-stabilized braided HDPE netting (45mm square mesh)',
                'rate_adj'   => 5,
                'net_rate'   => 18.00,
            ],
            'braided_3_0mm_40mm' => [
                'key'        => 'braided_3_0mm_40mm',
                'name'       => '3.0mm Heavy Braided HDPE (40mm Mesh)',
                'twine'      => '3.0mm Heavy-Duty Braided HDPE',
                'mesh'       => '40mm Square Mesh',
                'full_desc'  => '3.0mm ultra heavy-duty braided HDPE netting (40mm square mesh)',
                'rate_adj'   => 6,
                'net_rate'   => 19.50,
            ],
            'braided_3_5mm_100mm' => [
                'key'        => 'braided_3_5mm_100mm',
                'name'       => '3.5mm Heavy Braided HDPE (100mm Football Mesh)',
                'twine'      => '3.5mm High-Impact Braided HDPE',
                'mesh'       => '100mm Square Mesh',
                'full_desc'  => '3.5mm UV-stabilized high-impact braided HDPE football ball-stop netting (100mm square mesh)',
                'rate_adj'   => 2,
                'net_rate'   => 14.00,
            ],
            'braided_4_0mm_120mm' => [
                'key'        => 'braided_4_0mm_120mm',
                'name'       => '4.0mm High-Impact Braided HDPE (120mm Football Mesh)',
                'twine'      => '4.0mm Heavy Stadium Braided HDPE',
                'mesh'       => '120mm Square Mesh',
                'full_desc'  => '4.0mm heavy stadium-grade braided HDPE containment netting (120mm square mesh) for 7v7, 9v9 and 11v11 pitches',
                'rate_adj'   => 5,
                'net_rate'   => 16.50,
            ],
            'dual_cricket_football' => [
                'key'        => 'dual_cricket_football',
                'name'       => "Multi-Sport Dual Net (40mm Bottom 10 FT + 100mm Top)",
                'twine'      => 'Dual-Zone 2.5mm / 3.5mm Braided HDPE',
                'mesh'       => '40mm Lower + 100mm Upper Mesh',
                'full_desc'  => 'Dual-zone combination netting: 40mm mesh on lower 10 FT for cricket impact + 100mm mesh on upper section for football air-flow',
                'rate_adj'   => 3,
                'net_rate'   => 14.50,
            ],
            'twisted_1_5mm_35mm' => [
                'key'        => 'twisted_1_5mm_35mm',
                'name'       => '1.5mm Twisted HDPE (35mm Mesh)',
                'twine'      => '1.5mm UV-Stabilized High-Tenacity Twisted HDPE',
                'mesh'       => '35mm Square Mesh',
                'full_desc'  => '1.5mm UV-treated twisted HDPE netting (35mm square mesh) for economic containment',
                'rate_adj'   => -4,
                'net_rate'   => 8.00,
            ],
            'twisted_1_5mm_40mm' => [
                'key'        => 'twisted_1_5mm_40mm',
                'name'       => '1.5mm Twisted HDPE (40mm Mesh)',
                'twine'      => '1.5mm UV-Stabilized High-Tenacity Twisted HDPE',
                'mesh'       => '40mm Square Mesh',
                'full_desc'  => '1.5mm UV-treated twisted HDPE netting (40mm square mesh) for economic containment',
                'rate_adj'   => -5,
                'net_rate'   => 7.50,
            ],
            'twisted_1_0mm_30mm' => [
                'key'        => 'twisted_1_0mm_30mm',
                'name'       => '1.0mm Twisted HDPE (30mm Mesh)',
                'twine'      => '1.0mm UV-Treated Ultra-Light Twisted HDPE',
                'mesh'       => '30mm Square Mesh',
                'full_desc'  => '1.0mm ultra-lightweight twisted HDPE netting (30mm mesh)',
                'rate_adj'   => -6,
                'net_rate'   => 6.50,
            ],
            'twisted_2_0mm_45mm' => [
                'key'        => 'twisted_2_0mm_45mm',
                'name'       => '2.0mm Twisted HDPE (45mm Mesh)',
                'twine'      => '2.0mm UV-Stabilized Twisted HDPE',
                'mesh'       => '45mm Square Mesh',
                'full_desc'  => '2.0mm twisted HDPE netting (45mm square mesh)',
                'rate_adj'   => -2,
                'net_rate'   => 9.50,
            ],
        ];

        // Fallback mappings
        if ($key === '45mm') $key = 'braided_2_5mm_45mm';
        elseif ($key === '40mm') $key = 'braided_2_5mm_40mm';
        elseif ($key === '35mm') $key = 'braided_2_5mm_35mm';
        elseif ($key === '50mm') $key = 'braided_2_5mm_50mm';
        elseif ($key === '100mm') $key = 'braided_3_5mm_100mm';
        elseif ($key === '120mm') $key = 'braided_4_0mm_120mm';

        return $profiles[$key] ?? $profiles['braided_2_5mm_45mm'];
    }
}

if (!function_exists('resolveTurnkeyTopNetProfile')) {
    function resolveTurnkeyTopNetProfile($key = 'twisted_1_5mm_35mm', $sideNetProfile = null, $roofType = 'light_cable_top') {
        if ($roofType === 'open_top' || $key === 'open_top') {
            return [
                'key'        => 'open_top',
                'name'       => 'Open Top (No Overhead Netting)',
                'twine'      => 'N/A',
                'mesh'       => 'N/A',
                'full_desc'  => 'Open top arena without overhead net',
                'is_light'   => false,
                'rate_adj'   => 0,
                'net_rate'   => 0,
            ];
        }

        // Uniform All Sides / Same as Side Net Option
        if ($key === 'same_as_side' || $key === 'all_sides_same') {
            $sideProfile = $sideNetProfile ?? resolveTurnkeySideNetProfile('braided_2_5mm_45mm');
            return [
                'key'        => 'same_as_side',
                'name'       => 'Same as Side Net (' . $sideProfile['name'] . ')',
                'twine'      => $sideProfile['twine'],
                'mesh'       => $sideProfile['mesh'],
                'full_desc'  => 'Uniform 2.5mm Braided Net (All Sides & Overhead Top): ' . $sideProfile['twine'] . ' (' . $sideProfile['mesh'] . ') with reinforced 8mm border ropes',
                'is_light'   => false,
                'rate_adj'   => ($sideProfile['rate_adj'] ?? 0) + 4,
                'net_rate'   => $sideProfile['net_rate'] ?? 13.50,
            ];
        }

        $profiles = [
            'braided_2_5mm_45mm' => [
                'key'        => 'braided_2_5mm_45mm',
                'name'       => '2.5mm Braided HDPE (45mm Mesh - Heavy Duty Overhead)',
                'twine'      => '2.5mm UV-Stabilized High-Tenacity Braided HDPE',
                'mesh'       => '45mm Square Mesh',
                'full_desc'  => '2.5mm UV-stabilized high-tenacity braided HDPE top netting (45mm mesh) mounted with reinforced perimeter ropes and cross tension grid',
                'is_light'   => false,
                'rate_adj'   => 4,
                'net_rate'   => 13.50,
            ],
            'braided_2_5mm_40mm' => [
                'key'        => 'braided_2_5mm_40mm',
                'name'       => '2.5mm Braided HDPE (40mm Mesh - Heavy Duty Overhead)',
                'twine'      => '2.5mm UV-Stabilized High-Tenacity Braided HDPE',
                'mesh'       => '40mm Square Mesh',
                'full_desc'  => '2.5mm heavy braided HDPE top netting (40mm square mesh) for heavy shot deflection and zero sag',
                'is_light'   => false,
                'rate_adj'   => 5,
                'net_rate'   => 15.00,
            ],
            'braided_2_5mm_35mm' => [
                'key'        => 'braided_2_5mm_35mm',
                'name'       => '2.5mm Braided HDPE (35mm Mesh - Heavy Duty Overhead)',
                'twine'      => '2.5mm UV-Stabilized High-Tenacity Braided HDPE',
                'mesh'       => '35mm Square Mesh',
                'full_desc'  => '2.5mm dense braided HDPE overhead netting (35mm square mesh) with heavy border ropes',
                'is_light'   => false,
                'rate_adj'   => 6,
                'net_rate'   => 16.50,
            ],
            'braided_2_5mm_50mm' => [
                'key'        => 'braided_2_5mm_50mm',
                'name'       => '2.5mm Braided HDPE (50mm Mesh - Multi-Sport Top)',
                'twine'      => '2.5mm UV-Stabilized Braided HDPE',
                'mesh'       => '50mm Square Mesh',
                'full_desc'  => '2.5mm UV-treated braided HDPE top netting (50mm square mesh)',
                'is_light'   => false,
                'rate_adj'   => 3,
                'net_rate'   => 12.50,
            ],
            'braided_2_0mm_45mm' => [
                'key'        => 'braided_2_0mm_45mm',
                'name'       => '2.0mm Braided HDPE (45mm Mesh)',
                'twine'      => '2.0mm UV-Stabilized Braided HDPE',
                'mesh'       => '45mm Square Mesh',
                'full_desc'  => '2.0mm UV-stabilized braided HDPE top netting (45mm square mesh) with reinforced borders',
                'is_light'   => false,
                'rate_adj'   => 3,
                'net_rate'   => 11.50,
            ],
            'braided_2_0mm_40mm' => [
                'key'        => 'braided_2_0mm_40mm',
                'name'       => '2.0mm Braided HDPE (40mm Mesh)',
                'twine'      => '2.0mm UV-Stabilized Braided HDPE',
                'mesh'       => '40mm Square Mesh',
                'full_desc'  => '2.0mm UV-stabilized braided HDPE top netting (40mm square mesh)',
                'is_light'   => false,
                'rate_adj'   => 4,
                'net_rate'   => 12.50,
            ],
            'braided_2_0mm_50mm' => [
                'key'        => 'braided_2_0mm_50mm',
                'name'       => '2.0mm Braided HDPE (50mm Mesh)',
                'twine'      => '2.0mm UV-Stabilized Braided HDPE',
                'mesh'       => '50mm Square Mesh',
                'full_desc'  => '2.0mm UV-stabilized braided HDPE top netting (50mm square mesh)',
                'is_light'   => false,
                'rate_adj'   => 2,
                'net_rate'   => 10.50,
            ],
            'braided_2_5mm_100mm' => [
                'key'        => 'braided_2_5mm_100mm',
                'name'       => '2.5mm Braided HDPE (100mm Football Roof Mesh)',
                'twine'      => '2.5mm High-Tenacity Braided HDPE',
                'mesh'       => '100mm Square Mesh',
                'full_desc'  => '2.5mm UV-stabilized braided HDPE overhead roof netting (100mm mesh) for football arenas',
                'is_light'   => true,
                'rate_adj'   => 1,
                'net_rate'   => 11.00,
            ],
            'twisted_1_5mm_35mm' => [
                'key'        => 'twisted_1_5mm_35mm',
                'name'       => '1.5mm Twisted HDPE (35mm Mesh / 24-Ply - Recommended Light)',
                'twine'      => '1.5mm UV-Treated Lightweight High-Tenacity Twisted HDPE',
                'mesh'       => '35mm Square Mesh',
                'full_desc'  => '1.5mm lightweight twisted HDPE top netting (35mm mesh) designed for minimal sag on wire cable grids',
                'is_light'   => true,
                'rate_adj'   => 0,
                'net_rate'   => 8.00,
            ],
            'twisted_1_5mm_30mm' => [
                'key'        => 'twisted_1_5mm_30mm',
                'name'       => '1.5mm Twisted HDPE (30mm Mesh / 24-Ply)',
                'twine'      => '1.5mm UV-Treated Lightweight Twisted HDPE',
                'mesh'       => '30mm Square Mesh',
                'full_desc'  => '1.5mm lightweight twisted HDPE top netting (30mm square mesh)',
                'is_light'   => true,
                'rate_adj'   => 1,
                'net_rate'   => 8.50,
            ],
            'twisted_1_5mm_40mm' => [
                'key'        => 'twisted_1_5mm_40mm',
                'name'       => '1.5mm Twisted HDPE (40mm Mesh / 24-Ply)',
                'twine'      => '1.5mm UV-Treated Lightweight Twisted HDPE',
                'mesh'       => '40mm Square Mesh',
                'name'       => '1.0mm Twisted HDPE (30mm Mesh - Ultra Light)',
                'twine'      => '1.0mm UV-Treated Ultra-Light Twisted HDPE',
                'mesh'       => '30mm Square Mesh',
                'full_desc'  => '1.0mm ultra-lightweight twisted HDPE top netting (30mm square mesh)',
                'is_light'   => true,
                'rate_adj'   => -2,
            ],
        ];

        if ($key === '100mm') $key = 'braided_2_5mm_100mm';
        elseif ($key === '45mm') $key = 'braided_2_5mm_45mm';
        elseif ($key === '40mm') $key = 'braided_2_5mm_40mm';
        elseif ($key === '35mm') $key = 'braided_2_5mm_35mm';
        elseif ($key === '50mm') $key = 'braided_2_5mm_50mm';
        elseif ($key === '30mm') $key = 'twisted_1_5mm_30mm';

        return $profiles[$key] ?? $profiles['twisted_1_5mm_35mm'];
    }
}

if (!function_exists('calculateTurnkeyCourtSpecs')) {
    function calculateTurnkeyCourtSpecs($L, $W, $H, $sportType = 'box_cricket', $roofType = 'light_cable_top', $poleStyle = 'ladder_2line', $pipeSize = '40x40', $pipeGauge = '2.0mm', $turfType = 'non_infill_turf', $civilBedType = 'compacted_wmm', $sideNetKey = 'braided_2_5mm_45mm', $topNetKey = 'twisted_1_5mm_35mm', $gateCount = 1, $customPitchLength = null, $openSides = []) {
        $L = max(15, (float)$L);
        $W = max(6, (float)$W);
        $H = max(8, (float)$H);

        $sportProfile = resolveTurnkeySportProfile($sportType);
        $shape = $sportProfile['shape'] ?? 'rectangular';
        $isTrainingNet = ($shape === 'training_net' || strpos($sportType, 'training_net') !== false || $sportType === 'cricket_training_net' || $roofType === 'training_net_open_front');

        // Normalize open sides (allowing removal of any side: front, back, left, right, top)
        $openSides = is_array($openSides) ? $openSides : (json_decode((string)$openSides, true) ?: []);
        $openFront = isset($openSides['front']) ? !empty($openSides['front']) : $isTrainingNet;
        $openBack  = !empty($openSides['back']);
        $openLeft  = !empty($openSides['left']);
        $openRight = !empty($openSides['right']);
        $openTop   = isset($openSides['top']) ? !empty($openSides['top']) : ($roofType === 'open_top');
        $openSidesNormalized = [
            'front' => $openFront,
            'back'  => $openBack,
            'left'  => $openLeft,
            'right' => $openRight,
            'top'   => $openTop,
        ];

        // For training nets with open front, default gates to 0 if not explicitly requested
        if ($isTrainingNet && $openFront && $gateCount === 1) {
            $gateCount = 0;
        }

        // Area and Perimeter calculations based on shape
        if ($isTrainingNet || $shape === 'training_net') {
            $baseAreaSft = round($L * $W, 2);
            $enclosedPerimeter = ($openLeft ? 0 : $L) + ($openRight ? 0 : $L) + ($openBack ? 0 : $W) + ($openFront ? 0 : $W);
            $perimeterRft = round($enclosedPerimeter, 2);
            $lengthBays = max(2, (int)round($L / 15.0));
            $widthBays  = max(1, (int)round($W / 15.0));
            $bayLengthFt = round($L / $lengthBays, 1);
            $bayWidthFt  = round($W / $widthBays, 1);
            $cornerPoles = 4; // 4 corner columns
            $intermediateLengthPoles = ($openLeft ? 0 : max(0, $lengthBays - 1)) + ($openRight ? 0 : max(0, $lengthBays - 1));
            $intermediateWidthPoles  = ($openBack ? 0 : max(0, $widthBays - 1)) + ($openFront ? 0 : max(0, $widthBays - 1));
            $totalPoles = $cornerPoles + $intermediateLengthPoles + $intermediateWidthPoles;
        } elseif ($shape === 'circular') {
            $diameter = $L;
            $radius = $diameter / 2.0;
            $baseAreaSft = round(M_PI * $radius * $radius, 2);
            $perimeterRft = round(M_PI * $diameter, 2);
            $lengthBays = max(8, (int)round($perimeterRft / 15.0));
            $widthBays  = $lengthBays;
            $bayLengthFt = round($perimeterRft / $lengthBays, 1);
            $bayWidthFt  = $bayLengthFt;
            $totalPoles = $lengthBays;
            $cornerPoles = 0;
            $intermediateLengthPoles = $totalPoles;
            $intermediateWidthPoles  = 0;
        } elseif ($shape === 'oval') {
            $a = $L / 2.0; // semi-major
            $b = $W / 2.0; // semi-minor
            $baseAreaSft = round(M_PI * $a * $b, 2);
            // Ramanujan ellipse perimeter approximation
            $perimeterRft = round(M_PI * (3 * ($a + $b) - sqrt((3 * $a + $b) * ($a + 3 * $b))), 2);
            $lengthBays = max(10, (int)round($perimeterRft / 15.0));
            $widthBays  = $lengthBays;
            $bayLengthFt = round($perimeterRft / $lengthBays, 1);
            $bayWidthFt  = $bayLengthFt;
            $totalPoles = $lengthBays;
            $cornerPoles = 0;
            $intermediateLengthPoles = $totalPoles;
            $intermediateWidthPoles  = 0;
        } else {
            // Standard Rectangular (also handles custom removal of any side)
            $baseAreaSft = round($L * $W, 2);
            $enclosedPerimeter = ($openLeft ? 0 : $L) + ($openRight ? 0 : $L) + ($openBack ? 0 : $W) + ($openFront ? 0 : $W);
            $perimeterRft = round($enclosedPerimeter, 2);
            $lengthBays = max(2, (int)round($L / 15.0));
            $widthBays  = max(2, (int)round($W / 15.0));
            $bayLengthFt = round($L / $lengthBays, 1);
            $bayWidthFt  = round($W / $widthBays, 1);
            $cornerPoles = 4;
            $intermediateLengthPoles = ($openLeft ? 0 : max(0, $lengthBays - 1)) + ($openRight ? 0 : max(0, $lengthBays - 1));
            $intermediateWidthPoles  = ($openBack ? 0 : max(0, $widthBays - 1)) + ($openFront ? 0 : max(0, $widthBays - 1));
            $totalPoles = $cornerPoles + $intermediateLengthPoles + $intermediateWidthPoles;
        }

        $lengthSpans = ['count' => $lengthBays, 'bay_width' => $bayLengthFt];
        $widthSpans  = ['count' => $widthBays,  'bay_width' => $bayWidthFt];
        $foundationPits = $totalPoles;

        // Cricket Pitch & Central Strip Calculation
        $catalog = function_exists('getTurfCatalog') ? getTurfCatalog() : (function_exists('defaultTurfCatalog') ? defaultTurfCatalog() : []);
        $pitchCatalog = $catalog['cricket_pitch'] ?? [
            'full_roll_sft' => 540, 'half_roll_sft' => 270, 'rate_per_sft' => 78.0,
            'install_rate_per_sft' => 9.0, 'boundary_offset_ft' => 3.0, 'half_roll_cutoff_ft' => 48.0, 'width_ft' => 6.5
        ];

        $hasCustomPitch = ($customPitchLength !== null && (float)$customPitchLength > 0);
        $isNonCricketCourt = ($sportType === 'outdoor_multi_sports_120x60' || strpos($sportType, 'badminton') !== false || strpos($sportType, 'basketball') !== false || strpos($sportType, 'tennis') !== false || strpos($sportType, 'pickleball') !== false || strpos($flooringProfile['category'] ?? '', 'acrylic') !== false);
        if ($isNonCricketCourt) {
            $isCricket = false;
        } else {
            $isCricket = (strpos($sportType, 'cricket') !== false || strpos($sportType, 'terrace') !== false || ($sportType === 'multi_sport') || ($sportType === 'multisport_4in1_100x60') || ($sportType === 'multisport_arena_complex_185x60') || ($sportType === 'multisport_complex_105x170') || $isTrainingNet || $turfType === 'cricket_dual_turf' || $turfType === 'non_infill_turf' || $turfType === 'turf_cricket_20mm');
        }
        $boundaryOffset = (float)($pitchCatalog['boundary_offset_ft'] ?? 3.0);
        $halfRollCutoff = (float)($pitchCatalog['half_roll_cutoff_ft'] ?? 48.0);
        $pitchWidth     = (float)($pitchCatalog['width_ft'] ?? 6.5);

        // Check if pitch layer is enabled (layers['pitch'] !== false)
        // If custom pitch length is explicitly provided (> 0) or if it's cricket/terrace/multi-sport:
        $pitchExplicitlyDisabled = (($layers['pitch'] ?? true) === false || ($customPitchLength !== null && (float)$customPitchLength <= 0));
        $pitchEnabled = !$pitchExplicitlyDisabled && ($hasCustomPitch || $isCricket);

        if ($pitchEnabled) {
            $hasPitch = true;
            if ($hasCustomPitch) {
                $pitchLength = min((float)$customPitchLength, max(10.0, $L - (2 * $boundaryOffset)));
            } else {
                if ($L < $halfRollCutoff) {
                    $pitchLength = round(min(41.5, max(10.0, $L - (2 * $boundaryOffset))), 1);
                } else {
                    $pitchLength = round(min(83.0, max(20.0, $L - (2 * $boundaryOffset))), 1);
                }
            }

            // Determine roll type and material SFT:
            // If pitch length <= 42.5 FT or court < 48 FT, bill Half Roll (270 SFT)
            if ($pitchLength <= 41.5 || $L < $halfRollCutoff) {
                $pitchRollType   = 'half_roll';
                $pitchMaterialSft = (float)($pitchCatalog['half_roll_sft'] ?? 270.0);
            } else {
                $pitchRollType   = 'full_roll';
                $pitchMaterialSft = (float)($pitchCatalog['full_roll_sft'] ?? 540.0);
            }
            $pitchAreaSft = round($pitchLength * $pitchWidth, 2);
        } else {
            $hasPitch        = false;
            $pitchRollType   = 'none';
            $pitchMaterialSft = 0.0;
            $pitchLength     = 0.0;
            $pitchAreaSft    = 0.0;
        }

        // Horizontal Tiers: Top line @ H, 2nd line @ H - 6 FT, subsequent spaced >= 8 FT down
        $tierHeights = getTurnkeyTierHeights($H);
        $tierCount = count($tierHeights);
        $tierSpacing = ($tierCount > 1) ? round($H / ($tierCount - 1), 1) : $H;

        // Normalize roof type
        $hasRoof = ($roofType !== 'open_top' && $roofType !== 'open');

        $tiers = [];
        for ($i = 0; $i < $tierCount; $i++) {
            $hVal = $tierHeights[$i];
            $tiers[] = [
                'tier_index' => $i + 1,
                'height_ft'  => $hVal,
                'pipe_spec'  => ($i === 0 || $i === $tierCount - 1) ? '2"x2" Square Pipe (14G)' : '1.5"x1.5" Square Pipe (14G)',
            ];
        }

        // Calculate horizontal tie pipes strictly on enclosed sides
        $enclosedPurlinRft = 0.0;
        if (empty($openSidesNormalized['left']))  $enclosedPurlinRft += $L;
        if (empty($openSidesNormalized['right'])) $enclosedPurlinRft += $L;
        if (empty($openSidesNormalized['back']))  $enclosedPurlinRft += $W;
        if (empty($openSidesNormalized['front'])) $enclosedPurlinRft += $W;

        $horizontalPipeRft = round($enclosedPurlinRft * $tierCount, 2);
        // If front is open, but top roof is enclosed, add 1 top portal header tie beam across width at height H:
        if (!empty($openSidesNormalized['front']) && $hasRoof && empty($openSidesNormalized['top'])) {
            $horizontalPipeRft += $W;
        }
        $pipe20ftCount     = (int)ceil($horizontalPipeRft / 20.0);

        // Resolve Pole Profile
        $poleProfile = resolveTurnkeyPoleProfile($poleStyle, $pipeSize, $pipeGauge);

        $rungsPerPole = ($poleProfile['has_ladder']) ? max(0, (int)floor($H / 1.5)) * $poleProfile['faces_laced'] : 0;
        $totalLadderRungs   = $totalPoles * $rungsPerPole;
        $ladderRungPipeRft  = round($totalLadderRungs * 1.5, 1);
        $verticalPipeCount  = $totalPoles * $poleProfile['uprights_per_column'];
        $verticalPipeRft    = round($verticalPipeCount * $H, 2);

        // Normalize roof type (including metal sheet proof roofing)
        $isMetalRoof = in_array($roofType, ['metal_sheet_roof', 'gi_roof_skylight', 'puf_panel_roof', 'galvalume_metal_sheet_roof', 'sheet_roof', 'peb_roof', 'gi_sheet_roof']) || (strpos($roofType, 'sheet') !== false) || (strpos($roofType, 'metal') !== false) || (strpos($roofType, 'panel') !== false);
        if ($isMetalRoof) {
            $roofType = 'metal_sheet_roof';
        } elseif ($roofType === 'top_net' || $roofType === 'closed') {
            $roofType = 'truss_top';
        } elseif ($roofType === 'open') {
            $roofType = 'open_top';
        }

        $hasRoof = ($roofType !== 'open_top');
        $isTrussType = ($roofType === 'truss_top' || $isMetalRoof);
        $trussesCount = $isTrussType ? ($lengthBays + 1) : 0;
        $trussRft     = $isTrussType ? round($trussesCount * $W * 1.18, 2) : 0;
        $crossPipesCount = ($roofType === 'pipe_top') ? ($lengthBays + 1) : 0;
        $crossPipeRft    = ($roofType === 'pipe_top') ? round($crossPipesCount * $W, 2) : 0;

        // Roof Sheeting & Purlin Engineering (for metal_sheet_roof)
        if ($isMetalRoof) {
            // Purlins spaced @ 4.5 FT c/c across width
            $purlinLines = (int)ceil($W / 4.5) + 1;
            $roofPurlinRft = round($purlinLines * $L * 1.05, 2);
            // Metal Sheeting Area: Base area + 15% allowance for roof pitch & corrugated side overlaps
            $metalSheetSft = round($baseAreaSft * 1.15, 2);
            $ridgeCapRft   = round($L, 2);
            $gutterRft     = round($L * 2, 2); // Eaves gutters on both sides
            $downpipeRft   = round($H * 6, 2); // Downpipes every 60 FT
        } else {
            $roofPurlinRft = 0.0;
            $metalSheetSft = 0.0;
            $ridgeCapRft   = 0.0;
            $gutterRft     = 0.0;
            $downpipeRft   = 0.0;
        }

        // Netting Surface Areas (Supporting custom removal of any side)
        $side1NetSft     = !empty($openSidesNormalized['left'])  ? 0.0 : round($L * $H * 1.05, 2);
        $side2NetSft     = !empty($openSidesNormalized['right']) ? 0.0 : round($L * $H * 1.05, 2);
        $backNetSft      = !empty($openSidesNormalized['back'])  ? 0.0 : round($W * $H * 1.05, 2);
        $frontNetSft     = !empty($openSidesNormalized['front']) ? 0.0 : round($W * $H * 1.05, 2);
        $perimeterNetSft = round($side1NetSft + $side2NetSft + $backNetSft + $frontNetSft, 2);
        $sideNetSft      = round($side1NetSft + $side2NetSft, 2);
        // If metal sheet roof, top net is not needed because corrugated metal sheet covers the top!
        $topNetSft       = ($hasRoof && !$isMetalRoof && empty($openSidesNormalized['top'])) ? round($baseAreaSft * 1.08, 2) : 0.0;
        $totalNetSft     = round($perimeterNetSft + $topNetSft, 2);

        // Dual-Layer Shade Netting (Windbreak & Anti-Glare Shield)
        $hasShadeNet = !empty($layers['shade_net']) || (strpos($sportType, 'badminton') !== false) || (strpos($sportType, 'multisport_arena') !== false);
        $shadeNetHeight = !empty($layers['shade_net_height']) ? (float)$layers['shade_net_height'] : $H; // default full height
        // For multisport arena with badminton zone (45x60 badminton zone with two 45x30 courts inside 185x60 arena):
        if ($sportType === 'multisport_arena_complex_185x60') {
            // All 4 perimeter sides of 45x60 badminton zone: (45 FT + 45 FT + 60 FT + 60 FT) = 210 RFT
            $shadeNetPerimeterRft = (45 + 45 + 60 + 60); // 210 RFT
            $shadeNetSft = round($shadeNetPerimeterRft * $shadeNetHeight * 1.00, 2); // 210 RFT x 30 FT = 6,300 SFT exact
        } else {
            $shadeNetSft = $hasShadeNet ? round($perimeterRft * $shadeNetHeight * 1.05, 2) : 0.0;
        }

        $sideNetProfile = resolveTurnkeySideNetProfile($sideNetKey);
        $topNetProfile  = resolveTurnkeyTopNetProfile($topNetKey, $sideNetProfile, $roofType);

        if ($roofType === 'open_top') {
            $topNetSpec = [
                'name'       => 'Open Top (No Overhead Netting)',
                'twine'      => 'N/A',
                'mesh'       => 'N/A',
                'desc'       => 'Open-top arena without overhead roof netting',
                'support'    => 'Open Top (Side & Back Nets Only)',
            ];
        } else {
            $supportLabel = ($roofType === 'truss_top') ? 'Triangular Lattice Structural Trusses' : (($roofType === 'pipe_top') ? '2.0" / 2.5" Horizontal Cross Tie Pipes' : 'Wire Rope Tension Grid (No Top Steel)');
            $topNetSpec = [
                'name'       => 'Overhead Roof Netting (' . $topNetProfile['name'] . ')',
                'twine'      => $topNetProfile['twine'],
                'mesh'       => $topNetProfile['mesh'],
                'desc'       => $topNetProfile['full_desc'] . ' supported on ' . strtolower($supportLabel),
                'support'    => $supportLabel,
                'rate_adj'   => (float)($topNetProfile['rate_adj'] ?? 0),
                'net_rate'   => (float)($topNetProfile['net_rate'] ?? ($sideNetProfile['net_rate'] ?? 13.50)),
            ];
        }

        $wireRopeRft = round(($perimeterRft * ($tierCount + 1)) + ($hasRoof ? ($L * 3 + $W * 3) : 0), 2);

        // Flooring & Turf Profile
        $flooringProfile = resolveTurnkeyFlooringProfile($turfType);
        $hasTurf = $flooringProfile['has_turf'];
        $hasInfill = $flooringProfile['has_infill'];
        $turfAreaSft = $hasTurf ? $baseAreaSft : 0;

        // Civil Bed Profile
        $civilBedProfile = resolveTurnkeyCivilBedProfile($civilBedType);

        // Lighting Masts & Fixtures
        $lightMasts = ($totalPoles >= 16) ? 8 : 4;
        $lightsCount = [
            'essential'   => 6,
            'commercial'  => 8,
            'premium_pro' => 12,
        ];

        return [
            'sport_key'                  => $sportType,
            'sport_profile'              => $sportProfile,
            'shape'                      => $shape,
            'is_training_net'            => $isTrainingNet,
            'is_open_front'              => $openFront,
            'open_sides'                 => $openSidesNormalized,
            'gate_count'                 => $gateCount,
            'civil_bed_type'             => $civilBedType,
            'civil_bed_profile'          => $civilBedProfile,
            'flooring_type'              => $turfType,
            'flooring_profile'           => $flooringProfile,
            'length_ft'                  => $L,
            'width_ft'                   => $W,
            'height_ft'                  => $H,
            'base_area_sft'              => $baseAreaSft,
            'court_sft'                  => $baseAreaSft,
            'perimeter_rft'              => $perimeterRft,
            'sport_type'                 => $sportType,
            'roof_type'                  => $roofType,
            'has_roof'                   => $hasRoof,
            'is_cricket'                 => $isCricket,
            'has_pitch'                  => $hasPitch,
            'pitch_roll_type'            => $pitchRollType,
            'pitch_material_sft'         => $pitchMaterialSft,
            'pitch_length_ft'            => $pitchLength,
            'pitch_width_ft'             => $pitchWidth,
            'pitch_area_sft'             => $pitchAreaSft,
            'outfield_area_sft'          => max(0.0, round($baseAreaSft - $pitchAreaSft, 2)),
            'is_metal_roof'              => $isMetalRoof,
            'metal_sheet_sft'            => $metalSheetSft,
            'roof_purlin_rft'            => $roofPurlinRft,
            'ridge_cap_rft'              => $ridgeCapRft,
            'gutter_rft'                 => $gutterRft,
            'downpipe_rft'               => $downpipeRft,
            'has_shade_net'              => $hasShadeNet,
            'shade_net_sft'              => $shadeNetSft,
            'shade_net_height_ft'        => $shadeNetHeight,
            'trusses_count'              => $trussesCount,
            'truss_rft'                  => $trussRft,
            'cross_pipes_count'          => $crossPipesCount,
            'cross_pipe_rft'             => $crossPipeRft,
            'pole_style'                 => $poleStyle,
            'pole_profile'               => $poleProfile,
            'pipe_size'                  => $pipeSize,
            'pipe_gauge'                 => $pipeGauge,
            'length_bays'                => $lengthBays,
            'width_bays'                 => $widthBays,
            'length_spans'               => $lengthSpans,
            'width_spans'                => $widthSpans,
            'total_poles'                => $totalPoles,
            'corner_poles'               => $cornerPoles,
            'intermediate_length_poles'  => $intermediateLengthPoles,
            'intermediate_width_poles'   => $intermediateWidthPoles,
            'foundation_pits'            => $foundationPits,
            'tier_count'                 => $tierCount,
            'tiers'                      => $tiers,
            'tier_spacing'               => $tierSpacing,
            'tier_spacing_ft'            => $tierSpacing,
            'pole_type'                  => $poleProfile['type'],
            'horizontal_pipe_rft'        => $horizontalPipeRft,
            'pipe_20ft_count'            => $pipe20ftCount,
            'rungs_per_pole'             => $rungsPerPole,
            'total_ladder_rungs'         => $totalLadderRungs,
            'ladder_rung_pipe_rft'       => $ladderRungPipeRft,
            'vertical_pipe_count'        => $verticalPipeCount,
            'vertical_pipe_rft'          => $verticalPipeRft,
            'side_net_key'               => $sideNetKey,
            'side_net_profile'           => $sideNetProfile,
            'top_net_key'                => $topNetKey,
            'top_net_profile'            => $topNetProfile,
            'top_net_spec'               => $topNetSpec,
            'side_net_sft'               => $sideNetSft,
            'back_net_sft'               => $backNetSft,
            'perimeter_net_sft'          => $perimeterNetSft,
            'top_net_sft'                => $topNetSft,
            'total_net_sft'              => $totalNetSft,
            'wire_rope_rft'              => $wireRopeRft,
            'turf_type'                  => $turfType,
            'has_turf'                   => $hasTurf,
            'has_infill'                 => $hasInfill,
            'turf_area_sft'              => $turfAreaSft,
            'gate_count'                 => (int)$gateCount,
            'light_masts'                => $lightMasts,
            'lights_count'               => $lightsCount,
        ];
    }
}

if (!function_exists('getTurnkeyPackageRates')) {
    function getTurnkeyPackageRates($roofType = 'light_cable_top', $poleStyle = 'ladder_2line', $pipeSize = '40x40', $pipeGauge = '2.0mm', $turfType = 'non_infill_turf', $heightFt = 25, $civilBedType = 'compacted_wmm') {
        $poleProfile = resolveTurnkeyPoleProfile($poleStyle, $pipeSize, $pipeGauge);
        $poleAdj = $poleProfile['rate_adj'];
        $civilBedProfile = resolveTurnkeyCivilBedProfile($civilBedType);
        $flooringProfile = resolveTurnkeyFlooringProfile($turfType);

        $heightFt = max(10, (float)$heightFt);
        $heightAdj = ($heightFt - 20) * 1.5;

        // Roof support cost
        if ($roofType === 'truss_top') {
            $roofCost = ['essential' => 20, 'commercial' => 28, 'premium_pro' => 36];
        } elseif ($roofType === 'pipe_top') {
            $roofCost = ['essential' => 12, 'commercial' => 16, 'premium_pro' => 22];
        } elseif ($roofType === 'light_cable_top') {
            $roofCost = ['essential' => 6,  'commercial' => 8,  'premium_pro' => 11];
        } else {
            $roofCost = ['essential' => 0,  'commercial' => 0,  'premium_pro' => 0];
        }

        $hasCivil = !empty($civilBedProfile['has_civil']);
        $drainCost = $hasCivil ? ['essential' => 10, 'commercial' => 15, 'premium_pro' => 20] : ['essential' => 0, 'commercial' => 0, 'premium_pro' => 0];

        $flooringRates = $flooringProfile['rates'];
        $infillRates   = $flooringProfile['infill_rates'];

        $packages = [
            'essential' => [
                'key'          => 'essential',
                'label'        => 'Essential Package',
                'badge'        => '🟢 Essential',
                'color'        => '#10b981',
                'warranty'     => '1 Year Warranty',
                'items' => [
                    'civil'         => ['rate' => $civilBedProfile['rates']['essential'], 'desc' => $civilBedProfile['desc']],
                    'drainage'      => ['rate' => $drainCost['essential'], 'desc' => $hasCivil ? 'Perimeter Trench Drain: French gravel trench along court boundary for surface rainwater runoff.' : 'Perimeter Drainage: Not required for existing floor / terrace (₹0).'],
                    'structure'     => ['rate' => round(50 + $poleAdj + ($heightAdj * 0.5), 2), 'desc' => 'Structural Framework: ' . $poleProfile['full_name'] . ', standard MS/GI horizontal ties, anti-corrosion red oxide primer + enamel finish.'],
                    'side_net'      => ['rate' => round(20 + ($heightAdj * 0.3), 2), 'desc' => 'Perimeter Ball-Stop Netting: UV treated HDPE netting with 6mm border rope and steel cable tension grid.'],
                    'top_net'       => ['rate' => $roofCost['essential'], 'desc' => ($roofType === 'open_top' ? 'Open Top: No roof net' : 'Lightweight UV-treated HDPE top net suspended on steel wire ropes.')],
                    'turf'          => ['rate' => $flooringRates['essential'], 'desc' => $flooringProfile['desc']],
                    'infill'        => ['rate' => $infillRates['essential'], 'desc' => 'Graded silica sand and rubber infill distribution.'],
                    'lighting'      => ['rate' => 18, 'desc' => 'Sports LED Lighting: 6 Nos 150W/200W Stadium LED floodlights (180+ lux illumination).'],
                    'electrical'    => ['rate' => 6,  'desc' => 'Electrical Cabling & DB: Copper wiring, MCB distribution board, and earthing.'],
                    'gates'         => ['rate' => 5,  'desc' => 'Player Access Gate: 4ft x 7ft steel framed entry door with lock.'],
                    'equipment'     => ['rate' => 4,  'desc' => 'Sports Markings & Equipment: Regulation court line markings and sport accessories.'],
                    'finishing'     => ['rate' => 3,  'desc' => 'Anti-Rust Coating: Epoxy primer and weather-proof enamel coating.'],
                    'installation'  => ['rate' => 6,  'desc' => 'Turnkey Installation: Complete on-site erection, tension rigging, and commissioning.'],
                    'transport'     => ['rate' => 3,  'desc' => 'Transport & Freight: Material logistics and staging.'],
                    'project_mgmt'  => ['rate' => 2,  'desc' => 'Project Supervision: Technical execution and handover.'],
                ]
            ],
            'commercial' => [
                'key'          => 'commercial',
                'label'        => 'Commercial Standard Package',
                'badge'        => '🔵 Commercial (Best Value)',
                'color'        => '#2563eb',
                'warranty'     => '2 Years Extended Warranty',
                'items' => [
                    'civil'         => ['rate' => $civilBedProfile['rates']['commercial'], 'desc' => $civilBedProfile['desc']],
                    'drainage'      => ['rate' => $drainCost['commercial'], 'desc' => $hasCivil ? 'Engineered Perimeter Drainage: Perforated PVC sub-surface drain pipe in aggregate trench with catch pits.' : 'Perimeter Drainage: Not required for existing floor / terrace (₹0).'],
                    'structure'     => ['rate' => round(60 + $poleAdj + ($heightAdj * 0.6), 2), 'desc' => 'Heavy-Duty Framework: ' . $poleProfile['full_name'] . ', 2" x 2" horizontal tie purlins, zinc chromate epoxy primer + double PU finish.'],
                    'side_net'      => ['rate' => round(25 + ($heightAdj * 0.4), 2), 'desc' => 'Commercial Side & Back Netting: UV Stabilized HDPE Braided Netting, reinforced 8mm border ropes, 6mm PVC coated wire rope grid.'],
                    'top_net'       => ['rate' => $roofCost['commercial'], 'desc' => ($roofType === 'open_top' ? 'Open Top: No roof net' : 'Overhead High-Tenacity Roof Netting suspended on wire rope grid.')],
                    'turf'          => ['rate' => $flooringRates['commercial'], 'desc' => $flooringProfile['desc']],
                    'infill'        => ['rate' => $infillRates['commercial'], 'desc' => 'Commercial Infill System: Graded silica sand (15 kg/sqm) + SBR resilient rubber granules (4 kg/sqm).'],
                    'lighting'      => ['rate' => 22, 'desc' => 'Commercial Stadium Lighting: 8 Nos 200W High-Output Stadium LED Floodlights (IP66, 140 lm/W, 250+ lux avg illumination).'],
                    'electrical'    => ['rate' => 8,  'desc' => 'Industrial Electrical System: Heavy-duty 4-core copper armored cable, industrial weather-proof DB, digital auto-timer controller, chemical earthing pit.'],
                    'gates'         => ['rate' => 7,  'desc' => 'Heavy-Duty Entry Gate: 4ft x 7ft heavy-duty MS/GI access door with cylinder lock, safety foam padding, and weather-proof hinges.'],
                    'equipment'     => ['rate' => 5,  'desc' => 'Professional Markings & Accessories: Regulation court line markings and tournament sports equipment.'],
                    'finishing'     => ['rate' => 4,  'desc' => 'Anti-Corrosion Treatment: 2 coats epoxy zinc chromate primer + 2 coats heavy-duty weather-shield PU finish.'],
                    'installation'  => ['rate' => 8,  'desc' => 'Professional Installation: Laser-guided leveling, certified structural welding, specialized turf seaming, and tension rigging.'],
                    'transport'     => ['rate' => 3,  'desc' => 'Dedicated Transportation: Dedicated vehicle freight, crane unloading, and site staging.'],
                    'project_mgmt'  => ['rate' => 3,  'desc' => 'Project Management: Dedicated project engineer supervision, quality inspection, and test handover.'],
                ]
            ],
            'premium_pro' => [
                'key'          => 'premium_pro',
                'label'        => 'Premium Pro Club Package',
                'badge'        => '🔴 Premium Pro',
                'color'        => '#7c3aed',
                'warranty'     => '3–5 Years Comprehensive Warranty',
                'items' => [
                    'civil'         => ['rate' => $civilBedProfile['rates']['premium_pro'], 'desc' => $civilBedProfile['desc']],
                    'drainage'      => ['rate' => $drainCost['premium_pro'], 'desc' => $hasCivil ? 'Premium Subsurface French Drain: Full French drain grid + concrete perimeter storm water channel with heavy-duty GI grating.' : 'Perimeter Drainage: Not required for existing floor / terrace (₹0).'],
                    'structure'     => ['rate' => round(70 + ($poleAdj * 1.15) + ($heightAdj * 0.7), 2), 'desc' => 'Engineered Structural Framework: ' . $poleProfile['full_name'] . ', heavy-duty roof support, hot-dip galvanized / 2-pack aliphatic PU finish.'],
                    'side_net'      => ['rate' => round(30 + ($heightAdj * 0.5), 2), 'desc' => 'Russea™ Premium HDPE Braided Net: Russea™ High-Tenacity Braided Sports Netting, maximum UV stabilization, impact-resistant square mesh, double-stitched borders.'],
                    'top_net'       => ['rate' => $roofCost['premium_pro'], 'desc' => ($roofType === 'open_top' ? 'Open Top: No roof net' : 'Russea™ Premium High-Tenacity Overhead Roof Netting with stainless steel hardware.')],
                    'turf'          => ['rate' => $flooringRates['premium_pro'], 'desc' => $flooringProfile['desc']],
                    'infill'        => ['rate' => $infillRates['premium_pro'], 'desc' => 'Premium Infill System: High-purity rounded silica sand + virgin SBR / EPDM colored rubber granules.'],
                    'lighting'      => ['rate' => 25, 'desc' => 'Professional Sports LED System: 10–12 Nos 300W Professional Asymmetric Sports Floodlights (IP67, 150 lm/W, 350+ lux broadcast uniformity, anti-glare).'],
                    'electrical'    => ['rate' => 10, 'desc' => 'Schneider/Siemens Electrical Automation: High-grade armored copper cabling, IP65 digital distribution panel, smart timer/app automation, dual chemical earthings.'],
                    'gates'         => ['rate' => 8,  'desc' => 'Premium Double Entry Gates: 4ft x 7ft player entry + 8ft wide maintenance gate with hydraulic door closures and safety corner crash pads.'],
                    'equipment'     => ['rate' => 6,  'desc' => 'Tournament Grade Equipment: Full regulation tournament markings and heavy-gauge sports equipment.'],
                    'finishing'     => ['rate' => 5,  'desc' => 'Premium Protective Coating: Heavy hot-dip galvanizing or Epoxy zinc-rich primer + 2 coats high-gloss aliphatic PU coating.'],
                    'installation'  => ['rate' => 8,  'desc' => 'Master Sports Engineering Installation: Certified turnkey team, electronic laser surface verification, ultrasonic weld testing, tension rigging.'],
                    'transport'     => ['rate' => 2,  'desc' => 'Comprehensive Logistics: Dedicated heavy freight, crane unloading, and insurance in transit.'],
                    'project_mgmt'  => ['rate' => 4,  'desc' => 'Executive Project Management: Senior project manager on-site, lux level certification, ball bounce calibration, warranty documentation.'],
                ]
            ],
        ];

        foreach ($packages as $k => &$pkg) {
            $pkg['target_rate'] = round(array_sum(array_column($pkg['items'], 'rate')), 2);
        }
        unset($pkg);

        return $packages;
    }
}

if (!function_exists('getTurnkeyScaledPackages')) {
    function getTurnkeyScaledPackages($specs, $boqSubtotal = null, $selectedPkg = 'commercial', $civilBedType = null, $boqGrandTotal = null) {
        $baseArea = (float)($specs['base_area_sft'] ?? (($specs['length'] ?? $specs['length_ft'] ?? 185) * ($specs['width'] ?? $specs['width_ft'] ?? 60)));
        if ($baseArea <= 0) $baseArea = 11100.0;
        
        $civilBed = $civilBedType ?? ($specs['civil_bed_type'] ?? 'compacted_wmm');
        $roofType = $specs['roof_type'] ?? 'metal_sheet_roof';
        $poleStyle = $specs['pole_style'] ?? 'ladder_2line';
        $pipeSize = $specs['pipe_size'] ?? '40x40';
        $pipeGauge = $specs['pipe_gauge'] ?? '2.0mm';
        $turfType = $specs['turf_type'] ?? 'landscape_40mm';
        $heightFt = (float)($specs['height_ft'] ?? $specs['height'] ?? 30);

        $packages = getTurnkeyPackageRates($roofType, $poleStyle, $pipeSize, $pipeGauge, $turfType, $heightFt, $civilBed);

        $out = [];
        $selectedTargetRate = $packages[$selectedPkg]['target_rate'] ?? 300;
        $actualRateExcl = ($boqSubtotal !== null && $baseArea > 0) ? ((float)$boqSubtotal / $baseArea) : $selectedTargetRate;
        
        // Effective blended GST ratio (never allow 0 or null)
        $gstRatio = 1.160308; // Default ~16.03% blended GST
        if ($boqSubtotal !== null && (float)$boqSubtotal > 0 && $boqGrandTotal !== null && (float)$boqGrandTotal > 0) {
            $gstRatio = (float)$boqGrandTotal / (float)$boqSubtotal;
        }

        foreach ($packages as $key => $pkg) {
            $ratio = ($selectedTargetRate > 0) ? ($pkg['target_rate'] / $selectedTargetRate) : 1.0;
            
            if ($key === $selectedPkg && $boqSubtotal !== null) {
                $effRateExcl = round($actualRateExcl, 2);
                $effBudgetExcl = round((float)$boqSubtotal, 2);
                $effBudgetIncl = ($boqGrandTotal !== null && (float)$boqGrandTotal > 0) ? round((float)$boqGrandTotal, 2) : round($effBudgetExcl * $gstRatio, 2);
                $effRateIncl = round($effBudgetIncl / $baseArea, 2);
            } else {
                $effRateExcl = round($actualRateExcl * $ratio, 2);
                $effBudgetExcl = round($effRateExcl * $baseArea, 2);
                $effBudgetIncl = round($effBudgetExcl * $gstRatio, 2);
                $effRateIncl = round($effBudgetIncl / $baseArea, 2);
            }

            $out[$key] = [
                'key'              => $key,
                'label'            => $pkg['label'],
                'badge'            => $pkg['badge'],
                'color'            => $pkg['color'],
                'warranty'         => $pkg['warranty'],
                'target_rate'      => $pkg['target_rate'],
                'effective_rate'   => $effRateExcl,
                'total_amount'     => $effBudgetExcl,
                'rate_excl_gst'    => $effRateExcl,
                'budget_excl_gst'  => $effBudgetExcl,
                'rate_incl_gst'    => $effRateIncl,
                'budget_incl_gst'  => $effBudgetIncl,
                'gst_amount'       => round($effBudgetIncl - $effBudgetExcl, 2),
                'is_selected'      => ($key === $selectedPkg),
                'items'            => $pkg['items'],
                'rate'             => $effRateExcl,
                'budget'           => $effBudgetExcl,
            ];
        }

        return $out;
    }
}

if (!function_exists('getMultiZoneTurnkeyBreakdown')) {
    function getMultiZoneTurnkeyBreakdown($doc, $specs = null) {
        $items = $doc['items'] ?? [];
        $cfg = $doc['turnkey_config'] ?? [];
        $sportType = $cfg['sport_type'] ?? ($specs['sport_type'] ?? '');
        $L = (float)($cfg['length'] ?? ($specs['length_ft'] ?? 185));
        $W = (float)($cfg['width'] ?? ($specs['width_ft'] ?? 60));
        $totalArea = max(1, $L * $W);

        $hasZoneItems = false;
        foreach ($items as $it) {
            $desc = $it['description'] ?? '';
            if (strpos($desc, 'Zone A:') !== false || strpos($desc, 'Zone B:') !== false || strpos($desc, 'Zone C:') !== false) {
                $hasZoneItems = true;
                break;
            }
        }

        $isMultiZone = ($sportType === 'multisport_arena_complex_185x60' || $sportType === 'multisport_complex_105x170' || $hasZoneItems || !empty($cfg['is_multi_zone']));
        if (!$isMultiZone) {
            return ['is_multi_zone' => false];
        }

        // Categorize items
        $zoneADirect = ['tax' => 0.0, 'gst' => 0.0, 'tot' => 0.0];
        $zoneBDirect = ['tax' => 0.0, 'gst' => 0.0, 'tot' => 0.0];
        $zoneCDirect = ['tax' => 0.0, 'gst' => 0.0, 'tot' => 0.0];
        $shared = ['tax' => 0.0, 'gst' => 0.0, 'tot' => 0.0];

        foreach ($items as $it) {
            $amt = (float)($it['amount'] ?? 0);
            $gstRate = (float)($it['gst_rate'] ?? 18);
            $gstAmt = $amt * ($gstRate / 100.0);
            $totAmt = $amt + $gstAmt;
            $desc = $it['description'] ?? '';

            if (strpos($desc, 'Zone A:') !== false) {
                $zoneADirect['tax'] += $amt;
                $zoneADirect['gst'] += $gstAmt;
                $zoneADirect['tot'] += $totAmt;
            } elseif (strpos($desc, 'Zone B:') !== false) {
                $zoneBDirect['tax'] += $amt;
                $zoneBDirect['gst'] += $gstAmt;
                $zoneBDirect['tot'] += $totAmt;
            } elseif (strpos($desc, 'Zone C:') !== false) {
                $zoneCDirect['tax'] += $amt;
                $zoneCDirect['gst'] += $gstAmt;
                $zoneCDirect['tot'] += $totAmt;
            } else {
                $shared['tax'] += $amt;
                $shared['gst'] += $gstAmt;
                $shared['tot'] += $totAmt;
            }
        }

        // Dedicated breakdown for 105x170 16,000 SFT Sports Campus
        if ($sportType === 'multisport_complex_105x170') {
            $zoneIndoorArea  = 10710.0; // 105x102 FT Indoor Hall (4 Badminton + 2 Pickleball under GI Roof)
            $zoneCricketArea = 4500.0;  // 90x50 FT Outdoor Box Cricket (Open-Top)
            $zoneCircArea    = 2640.0;  // 105x18 Parking + 15x50 Pathway

            $indoorItemsTax = 0.0; $indoorItemsGst = 0.0;
            $cricketItemsTax = 0.0; $cricketItemsGst = 0.0;
            $sharedItemsTax = 0.0; $sharedItemsGst = 0.0;

            foreach ($items as $it) {
                $amt = (float)($it['amount'] ?? 0);
                $gRate = (float)($it['gst_rate'] ?? 18);
                $gAmt = $amt * ($gRate / 100.0);
                $desc = $it['description'] ?? '';

                if (strpos($desc, 'Zone A:') !== false) {
                    $indoorItemsTax += $amt; $indoorItemsGst += $gAmt;
                } elseif (strpos($desc, 'Zone B:') !== false) {
                    $cricketItemsTax += $amt; $cricketItemsGst += $gAmt;
                } else {
                    $sharedItemsTax += $amt; $sharedItemsGst += $gAmt;
                }
            }

            $totalDocTax = (float)($doc['subtotal'] ?? ($indoorItemsTax + $cricketItemsTax + $sharedItemsTax));
            $totalDocTot = (float)($doc['grand_total'] ?? $doc['total'] ?? round($totalDocTax * 1.18, 2));
            $totalDocGst = $totalDocTot - $totalDocTax;

            $aTax = $indoorItemsTax + ($sharedItemsTax * 0.70);
            $aGst = $indoorItemsGst + ($sharedItemsGst * 0.70);
            $aTot = $aTax + $aGst;

            $bTax = $cricketItemsTax + ($sharedItemsTax * 0.20);
            $bGst = $cricketItemsGst + ($sharedItemsGst * 0.20);
            $bTot = $bTax + $bGst;

            $cTax = $sharedItemsTax * 0.10;
            $cGst = $sharedItemsGst * 0.10;
            $cTot = $cTax + $cGst;

            return [
                'is_multi_zone'    => true,
                'total_area_sft'   => 17850.0,
                'total_subtotal'   => $totalDocTax,
                'total_rate_excl'  => round($totalDocTax / 17850.0, 2),
                'total_gst'        => $totalDocGst,
                'total_grand'      => $totalDocTot,
                'total_rate_incl'  => round($totalDocTot / 17850.0, 2),
                'zones' => [
                    [
                        'id'               => 'zone_indoor',
                        'name'             => 'Zone 1: Indoor Sports Hall (105 FT × 102 FT = 10,710 SFT)',
                        'short_name'       => 'Zone 1: Indoor Hall (Under GI Roof)',
                        'sport'            => '4 BWF Badminton Courts + 2 Pickleball Courts + Admin Block (35 FT Height)',
                        'dim'              => '105 × 102 FT',
                        'length_ft'        => 102,
                        'width_ft'         => 105,
                        'area_sft'         => 10710,
                        'share_pct'        => 60.0,
                        'subtotal_excl_gst'=> round($aTax, 2),
                        'rate_excl_gst'    => round($aTax / 10710.0, 2),
                        'gst_amount'       => round($aGst, 2),
                        'total_incl_gst'   => round($aTot, 2),
                        'rate_incl_gst'    => round($aTot / 10710.0, 2),
                        'color'            => '#1e3a8a',
                        'bg_light'         => '#eff6ff',
                        'border'           => '#93c5fd',
                        'icon'             => 'fa-warehouse',
                        'inclusions'       => '100% Weatherproof 0.50mm PPGI Roof + 50mm Thermal Under-Deck Foil Insulation + 10 Turbo Vents + 10 FT Solid Perimeter Brick Walls + 4 BWF 4.5mm Vinyl Badminton Courts + 2 8-Layer Acrylic Cushion Pickleball Courts + 24× 150W Anti-Glare Sports LEDs + Restrooms & Office'
                    ],
                    [
                        'id'               => 'zone_cricket',
                        'name'             => 'Zone 2: Outdoor Box Cricket Arena (90 FT × 50 FT = 4,500 SFT)',
                        'short_name'       => 'Zone 2: Outdoor Box Cricket (Open Top)',
                        'sport'            => 'Regulation Box Cricket Ground (Open-Top / No GI Roof)',
                        'dim'              => '90 × 50 FT',
                        'length_ft'        => 90,
                        'width_ft'         => 50,
                        'area_sft'         => 4500,
                        'share_pct'        => 25.2,
                        'subtotal_excl_gst'=> round($bTax, 2),
                        'rate_excl_gst'    => round($bTax / 4500.0, 2),
                        'gst_amount'       => round($bGst, 2),
                        'total_incl_gst'   => round($bTot, 2),
                        'rate_incl_gst'    => round($bTot / 4500.0, 2),
                        'color'            => '#16a34a',
                        'bg_light'         => '#f0fdf4',
                        'border'           => '#86efac',
                        'icon'             => 'fa-baseball-bat-ball',
                        'inclusions'       => '72×72mm MS Superstructure (30 FT High) + 66×10 FT Concrete Pitch Slab + 15mm Curled Match Pitch Turf + 50mm Outfield Turf + Russea 45mm Braided Netting + 8× 200W Stadium LEDs (OPEN-TOP / NO GI SHEET ROOF)'
                    ],
                    [
                        'id'               => 'zone_circ',
                        'name'             => 'Zone 3: Front Parking & 15 FT Pathway (2,640 SFT Total)',
                        'short_name'       => 'Zone 3: Parking & Pathway',
                        'sport'            => '105×18 FT Parking + 15×50 FT Pathway with Dual Main Gates',
                        'dim'              => '105 × 18 FT + 15 × 50 FT',
                        'length_ft'        => 105,
                        'width_ft'         => 18,
                        'area_sft'         => 2640,
                        'share_pct'        => 14.8,
                        'subtotal_excl_gst'=> round($cTax, 2),
                        'rate_excl_gst'    => round($cTax / 2640.0, 2),
                        'gst_amount'       => round($cGst, 2),
                        'total_incl_gst'   => round($cTot, 2),
                        'rate_incl_gst'    => round($cTot / 2640.0, 2),
                        'color'            => '#d97706',
                        'bg_light'         => '#fffbeb',
                        'border'           => '#fde68a',
                        'icon'             => 'fa-square-parking',
                        'inclusions'       => '60mm Heavy-Duty M30 Concrete Paver Blocks + Gate 1 & Gate 2 Dual Main Vehicle Entrance/Exit Gates + 15 FT Clean Pathway leading to Indoor Arena'
                    ]
                ]
            ];
        }

        // Zone dimensions & areas
        $zoneAArea = 6000.0; // 100x60
        $zoneBArea = 2700.0; // 45x60 (Two 45x30 courts)
        $zoneCArea = 2400.0; // 40x60
        if ($totalArea != 11100.0) {
            $zoneAArea = round($totalArea * 0.54054, 2);
            $zoneBArea = round($totalArea * 0.24324, 2);
            $zoneCArea = round($totalArea - $zoneAArea - $zoneBArea, 2);
        }

        $hasZoneCItems = ($zoneCDirect['tax'] > 0);

        if ($hasZoneCItems) {
            // Full 185x60 Covered Arena with Zone C Amenities & Interlocking Paver Floor
            $aRatio = ($totalArea > 0) ? ($zoneAArea / $totalArea) : 0.54054; // 54.1%
            $bRatio = ($totalArea > 0) ? ($zoneBArea / $totalArea) : 0.24324; // 24.3%
            $cRatio = ($totalArea > 0) ? ($zoneCArea / $totalArea) : 0.21622; // 21.6%

            $aTax = $zoneADirect['tax'] + ($shared['tax'] * $aRatio);
            $aTot = $zoneADirect['tot'] + ($shared['tot'] * $aRatio);
            $aGst = $aTot - $aTax;

            $bTax = $zoneBDirect['tax'] + ($shared['tax'] * $bRatio);
            $bTot = $zoneBDirect['tot'] + ($shared['tot'] * $bRatio);
            $bGst = $bTot - $bTax;

            $cTax = $zoneCDirect['tax'] + ($shared['tax'] * $cRatio);
            $cTot = $zoneCDirect['tot'] + ($shared['tot'] * $cRatio);
            $cGst = $cTot - $cTax;

            $totalDocTax = (float)($doc['subtotal'] ?? ($aTax + $bTax + $cTax));
            $totalDocTot = (float)($doc['grand_total'] ?? $doc['total'] ?? ($aTot + $bTot + $cTot));
            $totalDocGst = $totalDocTot - $totalDocTax;

            return [
                'is_multi_zone'    => true,
                'total_area_sft'   => $totalArea,
                'total_subtotal'   => $totalDocTax,
                'total_rate_excl'  => round($totalDocTax / $totalArea, 2),
                'total_gst'        => $totalDocGst,
                'total_grand'      => $totalDocTot,
                'total_rate_incl'  => round($totalDocTot / $totalArea, 2),
                'zones' => [
                    [
                        'id'               => 'zone_a',
                        'name'             => 'Zone 1: Box Cricket Ground (100 FT × 60 FT)',
                        'short_name'       => 'Zone 1: Box Cricket Ground',
                        'sport'            => 'Box Cricket & Practice Net Arena',
                        'dim'              => '100 × 60 FT',
                        'length_ft'        => 100,
                        'width_ft'         => 60,
                        'area_sft'         => $zoneAArea,
                        'share_pct'        => round($aRatio * 100, 1),
                        'subtotal_excl_gst'=> round($aTax, 2),
                        'rate_excl_gst'    => round($aTax / max(1, $zoneAArea), 2),
                        'gst_amount'       => round($aGst, 2),
                        'total_incl_gst'   => round($aTot, 2),
                        'rate_incl_gst'    => round($aTot / max(1, $zoneAArea), 2),
                        'color'            => '#16a34a',
                        'bg_light'         => '#f0fdf4',
                        'border'           => '#86efac',
                        'icon'             => 'fa-baseball-bat-ball',
                        'inclusions'       => '4" PCC M15 Concrete Bed (Broom Textured) + 83×6.5 FT Curled Pitch Turf (540 SFT) + 5,460 SFT 40mm Landscape Grass (Zero Infill) + 2-Line Ladder Steel Columns + 0.50mm Galvalume Roof + 2.5mm Braided Netting + 4 Stadium LEDs + Sideline Gate'
                    ],
                    [
                        'id'               => 'zone_b',
                        'name'             => 'Zone 2: Badminton Complex (45 FT × 60 FT - 2 Courts)',
                        'short_name'       => 'Zone 2: Badminton Complex (2 Courts)',
                        'sport'            => 'Dual Regulation Tournament Badminton Courts',
                        'dim'              => '45 × 60 FT (Two 45×30 Courts)',
                        'length_ft'        => 45,
                        'width_ft'         => 60,
                        'area_sft'         => $zoneBArea,
                        'share_pct'        => round($bRatio * 100, 1),
                        'subtotal_excl_gst'=> round($bTax, 2),
                        'rate_excl_gst'    => round($bTax / max(1, $zoneBArea), 2),
                        'gst_amount'       => round($bGst, 2),
                        'total_incl_gst'   => round($bTot, 2),
                        'rate_incl_gst'    => round($bTot / max(1, $zoneBArea), 2),
                        'per_court_area'   => 1350,
                        'per_court_subtotal'=> round($bTax / 2, 2),
                        'per_court_rate_excl'=> round($bTax / max(1, $zoneBArea), 2),
                        'per_court_total'  => round($bTot / 2, 2),
                        'per_court_rate_incl'=> round($bTot / max(1, $zoneBArea), 2),
                        'color'            => '#0284c7',
                        'bg_light'         => '#f0f9ff',
                        'border'           => '#7dd3fc',
                        'icon'             => 'fa-table-tennis-paddle-ball',
                        'inclusions'       => '4" M20 Laser-Screeded Concrete Slab + 4.5 FT Solid Masonry Perimeter Base Wall (210 RFT) + 4.5mm BWF Synthetic Flooring (2 Courts) + 2 Sets Movable Posts & BWF Nets + PU Line Markings + Full 30 FT 75% Windbreak/Anti-Glare Nets (6,300 SFT) + 4 Stadium LEDs + Inter-Zone Gate'
                    ],
                    [
                        'id'               => 'zone_c',
                        'name'             => 'Zone 3: Front Amenities Suite & Approach (40 FT × 60 FT)',
                        'short_name'       => 'Zone 3: Amenities Suite & Approach',
                        'sport'            => 'Covered Reception, Washrooms, Player Lounge & Approach',
                        'dim'              => '40 × 60 FT',
                        'length_ft'        => 40,
                        'width_ft'         => 60,
                        'area_sft'         => $zoneCArea,
                        'share_pct'        => round($cRatio * 100, 1),
                        'subtotal_excl_gst'=> round($cTax, 2),
                        'rate_excl_gst'    => round($cTax / max(1, $zoneCArea), 2),
                        'gst_amount'       => round($cGst, 2),
                        'total_incl_gst'   => round($cTot, 2),
                        'rate_incl_gst'    => round($cTot / max(1, $zoneCArea), 2),
                        'color'            => '#d97706',
                        'bg_light'         => '#fffbeb',
                        'border'           => '#fde68a',
                        'icon'             => 'fa-couch',
                        'inclusions'       => 'Heavy-Duty 60mm M30 Concrete Paver Blocks (2,400 SFT) + Commercial Restrooms (Gents & Ladies) + Executive Management Office Suite & Reception Lobby + Player Lounge & Kit Lockers + Main Public Access Gate + 100% Galvalume Roof Coverage'
                    ]
                ]
            ];
        }

        // Active Sports Complex Execution Area (Zone 1 + Zone 2 = 6,000 + 2,700 = 8,700 SFT) Fallback for older legacy quotes
        $sportsWorkArea = $zoneAArea + $zoneBArea; // 8,700 SFT
        $aRatio = ($sportsWorkArea > 0) ? ($zoneAArea / $sportsWorkArea) : 0.689655; // 68.9655%
        $bRatio = ($sportsWorkArea > 0) ? ($zoneBArea / $sportsWorkArea) : 0.310345; // 31.0345%
        $cRatio = 0.0; // 0% - Zero contractor work / ₹0.00 charged

        // Fully apportioned totals (100% of execution works allocated between Zone 1 Cricket & Zone 2 Badminton)
        $aTax = $zoneADirect['tax'] + ($shared['tax'] * $aRatio);
        $aGst = $zoneADirect['gst'] + ($shared['gst'] * $aRatio);
        $aTot = $zoneADirect['tot'] + ($shared['tot'] * $aRatio);

        $bTax = $zoneBDirect['tax'] + ($shared['tax'] * $bRatio);
        $bGst = $zoneBDirect['gst'] + ($shared['gst'] * $bRatio);
        $bTot = $zoneBDirect['tot'] + ($shared['tot'] * $bRatio);

        // Zone 3 is explicitly ₹0.00 (Zero Work Required / Client Scope)
        $cTax = 0.0;
        $cGst = 0.0;
        $cTot = 0.0;

        $totalDocTax = (float)($doc['subtotal'] ?? ($aTax + $bTax + $cTax));
        $totalDocTot = (float)($doc['total'] ?? ($aTot + $bTot + $cTot));
        $totalDocGst = $totalDocTot - $totalDocTax;

        $hasCricketOverheadNet = false;
        $hasBadmintonWallCladding = false;
        $cricketBedLabel = '4" Compacted WMM Base';
        foreach ($items as $it) {
            $desc = $it['description'] ?? '';
            if (strpos($desc, 'Overhead Top') !== false || (strpos($desc, '1.5mm') !== false && strpos($desc, 'Zone A') !== false)) {
                $hasCricketOverheadNet = true;
            }
            if (strpos($desc, 'Wall Cladding') !== false) {
                $hasBadmintonWallCladding = true;
            }
            if (strpos($desc, 'PCC M15') !== false || strpos($desc, '4" PCC') !== false) {
                $cricketBedLabel = '4" PCC M15 Concrete Bed';
            }
        }
        $cricketRoofDesc = $hasCricketOverheadNet ? '1.5mm 40mm Overhead Mesh (No Top Steel / Cable Grid)' : '0.50mm Galvalume Roof';
        $badmintonWallDesc = $hasBadmintonWallCladding ? '0.50mm Galvalume Roof (3,105 SFT) + 0.50mm PPGL Wall Cladding All 4 Sides (6,300 SFT)' : 'Full 30 FT 75% Windbreak/Anti-Glare Nets (6,300 SFT)';

        return [
            'is_multi_zone'    => true,
            'total_area_sft'   => $totalArea,
            'total_subtotal'   => $totalDocTax,
            'total_rate_excl'  => round($totalDocTax / $totalArea, 2),
            'total_gst'        => $totalDocGst,
            'total_grand'      => $totalDocTot,
            'total_rate_incl'  => round($totalDocTot / $totalArea, 2),
            'zones' => [
                [
                    'id'               => 'zone_a',
                    'name'             => 'Zone 1: Box Cricket Ground (100 FT × 60 FT)',
                    'short_name'       => 'Zone 1: Box Cricket Ground',
                    'sport'            => 'Box Cricket & Practice Net Arena',
                    'dim'              => '100 × 60 FT',
                    'length_ft'        => 100,
                    'width_ft'         => 60,
                    'area_sft'         => $zoneAArea,
                    'share_pct'        => round($aRatio * 100, 1),
                    'subtotal_excl_gst'=> round($aTax, 2),
                    'rate_excl_gst'    => round($aTax / max(1, $zoneAArea), 2),
                    'gst_amount'       => round($aGst, 2),
                    'total_incl_gst'   => round($aTot, 2),
                    'rate_incl_gst'    => round($aTot / max(1, $zoneAArea), 2),
                    'color'            => '#16a34a',
                    'bg_light'         => '#f0fdf4',
                    'border'           => '#86efac',
                    'icon'             => 'fa-baseball-bat-ball',
                    'inclusions'       => $cricketBedLabel . ' + 83×6.5 FT Curled Pitch Turf (540 SFT) + 5,460 SFT 40mm Landscape Grass (Zero Infill) + 2-Line Ladder Steel Columns + ' . $cricketRoofDesc . ' + 2.5mm Braided Netting + 4 Stadium LEDs + Sideline Gate'
                ],
                [
                    'id'               => 'zone_b',
                    'name'             => 'Zone 2: Badminton Complex (45 FT × 60 FT - 2 Courts)',
                    'short_name'       => 'Zone 2: Badminton Complex (2 Courts)',
                    'sport'            => 'Dual Regulation Tournament Badminton Courts',
                    'dim'              => '45 × 60 FT (Two 45×30 Courts)',
                    'length_ft'        => 45,
                    'width_ft'         => 60,
                    'area_sft'         => $zoneBArea,
                    'share_pct'        => round($bRatio * 100, 1),
                    'subtotal_excl_gst'=> round($bTax, 2),
                    'rate_excl_gst'    => round($bTax / max(1, $zoneBArea), 2),
                    'gst_amount'       => round($bGst, 2),
                    'total_incl_gst'   => round($bTot, 2),
                    'rate_incl_gst'    => round($bTot / max(1, $zoneBArea), 2),
                    'per_court_area'   => 1350,
                    'per_court_subtotal'=> round($bTax / 2, 2),
                    'per_court_rate_excl'=> round($bTax / max(1, $zoneBArea), 2),
                    'per_court_total'  => round($bTot / 2, 2),
                    'per_court_rate_incl'=> round($bTot / max(1, $zoneBArea), 2),
                    'color'            => '#0284c7',
                    'bg_light'         => '#f0f9ff',
                    'border'           => '#7dd3fc',
                    'icon'             => 'fa-table-tennis-paddle-ball',
                    'inclusions'       => '4" M20 Laser-Screeded Concrete Slab + 4.5mm BWF Flooring Mats (2 Courts) + 2 Sets Movable Posts & BWF Nets + PU Line Markings + ' . $badmintonWallDesc . ' + 4 Stadium LEDs + Inter-Zone Gate'
                ],
                [
                    'id'               => 'zone_c',
                    'name'             => 'Zone 3: Open Reception, Lounge & Utility Area (40 FT × 60 FT)',
                    'short_name'       => 'Zone 3: Reception & Utility (Client Scope)',
                    'sport'            => 'Existing / Open Player Reception, Lounge & Parking (Zero Contractor Work Required)',
                    'dim'              => '40 × 60 FT',
                    'length_ft'        => 40,
                    'width_ft'         => 60,
                    'area_sft'         => $zoneCArea,
                    'share_pct'        => 0.0,
                    'subtotal_excl_gst'=> 0.0,
                    'rate_excl_gst'    => 0.0,
                    'gst_amount'       => 0.0,
                    'total_incl_gst'   => 0.0,
                    'rate_incl_gst'    => 0.0,
                    'color'            => '#64748b',
                    'bg_light'         => '#f8fafc',
                    'border'           => '#cbd5e1',
                    'icon'             => 'fa-ban',
                    'inclusions'       => '🚫 CLIENT SCOPE / ZERO WORK REQUIRED: Existing arena space — No structural steel, no civil bed slab, no sports flooring, no roof sheets, and no electrical fixtures charged in this turnkey quotation (₹0.00 / Zero Cost).'
                ]
            ]
        ];
    }
}

if (!function_exists('getTurnkeyDetailedSpecRows')) {
    function getTurnkeyDetailedSpecRows($specs, $selectedPkg = 'commercial') {
        $sportProfile = $specs['sport_profile'];
        $poleProfile = $specs['pole_profile'];
        $civilBedProfile = $specs['civil_bed_profile'];
        $flooringProfile = $specs['flooring_profile'];
        $sideNetProfile = $specs['side_net_profile'];
        $topNetSpec = $specs['top_net_spec'];

        $L = $specs['length_ft'];
        $W = $specs['width_ft'];
        $H = $specs['height_ft'];
        $baseArea = $specs['base_area_sft'];
        $perimeterRft = $specs['perimeter_rft'];

        $isTerraceMount = ($civilBedProfile['key'] === 'terrace_base_plates' || $civilBedProfile['key'] === 'rooftop_base_plates');
        $foundTitle     = $isTerraceMount ? 'Terrace Base Plate Mount' : 'Foundation Footings';
        $foundComponent = $isTerraceMount ? 'Rooftop Slab Anchor Plates' : 'Structural Column Foundations';
        $foundSpec      = $isTerraceMount ? 'Heavy-duty 8mm–10mm base flange plates anchored into RCC roof slab with M12/M16 chemical & wedge bolts + bitumen pads' : 'M20 grade concrete block foundations with high-tensile anchor bolts';
        $foundGauge     = $isTerraceMount ? '8mm–10mm MS/GI Flange Plates + M12/M16 Anchors' : '1.5 FT x 1.5 FT x 3.0 FT Depth';
        $foundQty       = $isTerraceMount ? ($specs['total_poles'] . ' Base Plates') : ($specs['total_poles'] . ' Footings');
        $foundStandard  = $isTerraceMount ? 'IS 800 & Hilti/Fischer ETA' : 'IS 456 M20 Concrete';

        $rows = [
            [
                'sl'        => '01',
                'cat'       => 'Civil Sub-Base',
                'item'      => $isTerraceMount ? 'Terrace Base Anchors' : 'Civil Ground Bed',
                'component' => $isTerraceMount ? 'Rooftop Flange Mounting' : 'Civil Sub-Base & Ground Slab',
                'spec'      => $civilBedProfile['name'],
                'gauge'     => $civilBedProfile['gauge'],
                'gaps'      => '1:150 dual-slope cross gradient',
                'qty'       => number_format($baseArea) . ' SFT',
                'standard'  => 'IS 456 & IS 1786',
            ],
            [
                'sl'        => '02',
                'cat'       => 'Civil Sub-Base',
                'item'      => $foundTitle,
                'component' => $foundComponent,
                'spec'      => $foundSpec,
                'gauge'     => $foundGauge,
                'gaps'      => '~' . $specs['length_spans']['bay_width'] . ' FT c/c intervals',
                'qty'       => $foundQty,
                'standard'  => $foundStandard,
            ],
            [
                'sl'        => '03',
                'cat'       => 'Structural Engineering',
                'item'      => 'Perimeter Columns',
                'component' => 'Main Structural Columns',
                'spec'      => $poleProfile['full_name'],
                'gauge'     => $poleProfile['gauge_label'],
                'gaps'      => '~' . $specs['length_spans']['bay_width'] . ' FT bay spans',
                'qty'       => $specs['total_poles'] . ' Nos (' . (int)$H . ' FT Ht)',
                'standard'  => 'IS 4923 YSt-210',
            ],
            [
                'sl'        => '04',
                'cat'       => 'Structural Engineering',
                'item'      => 'Horizontal Tie Purlins',
                'component' => 'Horizontal Steel Tie Tiers',
                'spec'      => '2" x 2" & 1.5" MS/GI continuous horizontal tie pipes',
                'gauge'     => '2.0mm / 14 Gauge (IS 1239)',
                'gaps'      => '~' . $specs['tier_spacing'] . ' FT vertical c/c tiers',
                'qty'       => $specs['tier_count'] . ' Tiers (' . $specs['horizontal_pipe_rft'] . ' RFT)',
                'standard'  => 'IS 1239 Class-B',
            ],
        ];

        $sl = 5;
        if ($poleProfile['has_ladder']) {
            $rows[] = [
                'sl'        => sprintf('%02d', $sl++),
                'cat'       => 'Structural Engineering',
                'item'      => 'Ladder Steps',
                'component' => 'Column Ladder Rungs & Lattice',
                'spec'      => 'Welded tubular ladder climbing rungs & wind lacing',
                'gauge'     => $poleProfile['pipe_size'] . ' @ ' . $poleProfile['pipe_gauge'],
                'gaps'      => '1.5 FT c/c step spacing',
                'qty'       => $specs['total_ladder_rungs'] . ' Steps',
                'standard'  => 'IS 4923 Structural',
            ];
        }

        if ($specs['roof_type'] === 'truss_top') {
            $rows[] = [
                'sl'        => sprintf('%02d', $sl++),
                'cat'       => 'Roof Structure',
                'item'      => 'Roof Trusses',
                'component' => 'Overhead Triangular Roof Trusses',
                'spec'      => 'Engineered triangular steel lattice roof trusses spanning ' . (int)$W . ' FT',
                'gauge'     => 'Dual 2" Top & Bottom Chords',
                'gaps'      => '~' . $specs['length_spans']['bay_width'] . ' FT truss spacing',
                'qty'       => $specs['trusses_count'] . ' Trusses (' . number_format($specs['truss_rft']) . ' RFT)',
                'standard'  => 'IS 800 Steel Design',
            ];
        } elseif ($specs['roof_type'] === 'pipe_top') {
            $rows[] = [
                'sl'        => sprintf('%02d', $sl++),
                'cat'       => 'Roof Structure',
                'item'      => 'Cross Pipes',
                'component' => 'Horizontal Cross Tie Pipes',
                'spec'      => '2.0" / 2.5" structural cross pipes across width span',
                'gauge'     => '2.0mm / 14 Gauge Heavy Steel',
                'gaps'      => '~' . $specs['length_spans']['bay_width'] . ' FT c/c spacing',
                'qty'       => $specs['cross_pipes_count'] . ' Pipes',
                'standard'  => 'IS 1239 Structural',
            ];
        } elseif ($specs['roof_type'] === 'light_cable_top') {
            $rows[] = [
                'sl'        => sprintf('%02d', $sl++),
                'cat'       => 'Roof Structure',
                'item'      => 'Cable Grid',
                'component' => 'Cable Suspension Tension Grid',
                'spec'      => 'High-tension galvanized steel wire rope grid (Zero top steel)',
                'gauge'     => '6mm / 8mm PVC Coated GI Wire',
                'gaps'      => '15 FT x 15 FT tension grid',
                'qty'       => number_format($specs['top_net_sft']) . ' SFT Grid',
                'standard'  => 'IS 2266 Wire Rope',
            ];
        }

        $rows[] = [
            'sl'        => sprintf('%02d', $sl++),
            'cat'       => 'Sports Netting',
            'item'      => 'Perimeter Netting',
            'component' => 'Perimeter Ball-Stop Netting',
            'spec'      => $sideNetProfile['full_desc'],
            'gauge'     => $sideNetProfile['twine'],
            'gaps'      => $sideNetProfile['mesh'] . ' square',
            'qty'       => number_format($specs['perimeter_net_sft']) . ' SFT',
            'standard'  => 'Russea™ UV IS 5608',
        ];

        if ($specs['has_roof']) {
            $rows[] = [
                'sl'        => sprintf('%02d', $sl++),
                'cat'       => 'Sports Netting',
                'item'      => 'Overhead Netting',
                'component' => 'Overhead Roof Enclosure Netting',
                'spec'      => $topNetSpec['name'] . ' - ' . $topNetSpec['desc'],
                'gauge'     => $topNetSpec['twine'],
                'gaps'      => $topNetSpec['mesh'],
                'qty'       => number_format($specs['top_net_sft']) . ' SFT',
                'standard'  => 'Russea™ Certified',
            ];
        }

        if ($flooringProfile['has_turf']) {
            $rows[] = [
                'sl'        => sprintf('%02d', $sl++),
                'cat'       => 'Surface & Flooring',
                'item'      => 'Playing Surface',
                'component' => 'Sports Playing Surface / Flooring',
                'spec'      => $flooringProfile['name'],
                'gauge'     => $flooringProfile['pile_height'],
                'gaps'      => 'Seamless 30cm PU jointing',
                'qty'       => number_format($specs['turf_area_sft']) . ' SFT',
                'standard'  => 'FIFA / ITF Certified',
            ];
        }

        if (($specs['sport_type'] ?? '') === 'outdoor_multi_sports_120x60') {
            $rows[] = [
                'sl'        => sprintf('%02d', $sl++),
                'cat'       => 'Court Amenities',
                'item'      => 'Basketball Equipment',
                'component' => 'Fixed 6" MS Basketball Poles (1 Pair)',
                'spec'      => '6\" (150mm) heavy MS tubular steel with 1.2m extension overhang, 20mm imported acrylic backboards (180×105cm), Olympian breakaway rings & all-weather nets (~200 kg/pair)',
                'gauge'     => '6" MS / 20mm Acrylic',
                'gaps'      => 'Both court baselines',
                'qty'       => '1 Pair (2 Nos.)',
                'standard'  => 'FIBA Regulation Standard',
            ];
            $rows[] = [
                'sl'        => sprintf('%02d', $sl++),
                'cat'       => 'Court Amenities',
                'item'      => 'Badminton / Tennis Net',
                'component' => 'Fixed Posts & Tournament Net (1 Pair)',
                'spec'      => '80×80mm heavy MS square pipe with internal tension ratchet system & branded professional quality regulation net',
                'gauge'     => '80×80mm MS / 2.5mm Net',
                'gaps'      => 'Center court span',
                'qty'       => '1 Pair',
                'standard'  => 'BWF / ITF Regulation',
            ];
            $rows[] = [
                'sl'        => sprintf('%02d', $sl++),
                'cat'       => 'Civil & Drainage',
                'item'      => 'Saucer Drain',
                'component' => 'Perimeter Open Saucer Drain',
                'spec'      => 'Open saucer drain of width (6"×24" or 9"×24") matching court gradient to channelize surface water to corner',
                'gauge'     => 'M20 Concrete Pre-Cast',
                'gaps'      => 'Along perimeter edge',
                'qty'       => '120 Rft',
                'standard'  => 'Engineered Drainage',
            ];
        }

        if (!empty($specs['is_cricket']) && !empty($specs['pitch_length_ft']) && $specs['pitch_length_ft'] > 0) {
            $rows[] = [
                'sl'        => sprintf('%02d', $sl++),
                'cat'       => 'Surface & Flooring',
                'item'      => 'Cricket Pitch Strip',
                'component' => 'Dedicated Central Cricket Wicket Strip',
                'spec'      => $specs['pitch_length_ft'] . ' FT × 6.5 FT (' . number_format($specs['pitch_area_sft']) . ' SFT) High-Density Wicket Turf with Reinforced Seaming Tape & PU Adhesive',
                'gauge'     => '15mm / 20mm Wicket Grade Yarn',
                'gaps'      => 'Dual-tone pitch border inlays',
                'qty'       => number_format($specs['pitch_area_sft']) . ' SFT',
                'standard'  => 'BCCI / MCC Standard (22 Yards Regulation)',
            ];
        }

        $numLights = $specs['lights_count'][$selectedPkg] ?? 8;
        $rows[] = [
            'sl'        => sprintf('%02d', $sl++),
            'cat'       => 'Lighting & Electrical',
            'item'      => 'Stadium Lights',
            'component' => 'Stadium LED Floodlight System',
            'spec'      => 'High-output asymmetric optics IP66/IP67 stadium LED floodlights',
            'gauge'     => ($selectedPkg === 'premium_pro' ? '300W Pro LEDs' : '200W Stadium LEDs'),
            'gaps'      => 'Mounted on ' . $specs['light_masts'] . ' corner/side masts',
            'qty'       => $numLights . ' Fixtures',
            'standard'  => '250-350+ Lux Avg',
        ];

        $rows[] = [
            'sl'        => sprintf('%02d', $sl++),
            'cat'       => 'Accessories',
            'item'      => 'Court Equipment',
            'component' => 'Court Markings & Sports Fixtures',
            'spec'      => $sportProfile['equipment_desc'],
            'gauge'     => 'Regulation Tournament Standard',
            'gaps'      => 'Sport-specific standard dimensions',
            'qty'       => '1 Complete Setup',
            'standard'  => 'Official Federation',
        ];

        return $rows;
    }
}

if (!function_exists('generateTurnkeyScopeItems')) {
    function generateTurnkeyScopeItems($specs, $selectedPkg = 'commercial') {
        $rows = getTurnkeyDetailedSpecRows($specs, $selectedPkg);
        $scopeItems = [];

        foreach ($rows as $r) {
            $scopeItems[] = [
                'title'       => $r['cat'] . ': ' . $r['item'],
                'description' => $r['spec'],
                'note'        => '',
            ];
        }

        return $scopeItems;
    }
}

if (!function_exists('generateTurnkeyBOQ')) {
    function generateTurnkeyBOQ($L, $W, $H, $sportType = 'box_cricket', $roofType = 'light_cable_top', $selectedPkg = 'commercial', $poleStyle = 'ladder_2line', $pipeSize = '40x40', $pipeGauge = '2.0mm', $turfType = 'non_infill_turf', $customRate = null, $customPitchLength = null, $civilBedType = 'compacted_wmm', $sideNetKey = 'braided_2_5mm_45mm', $topNetKey = 'twisted_1_5mm_35mm', $gateCount = 1, $openSides = [], $layers = [], $badmintonFloorType = 'bwf_vinyl_4_5mm', $badmintonBedType = 'rcc_m20_4inch_laser', $equipment = []) {
        $specs = calculateTurnkeyCourtSpecs($L, $W, $H, $sportType, $roofType, $poleStyle, $pipeSize, $pipeGauge, $turfType, $civilBedType, $sideNetKey, $topNetKey, $gateCount, $customPitchLength, $openSides);
        $gateCount = $specs['gate_count'];
        $allPackages = getTurnkeyPackageRates($roofType, $poleStyle, $pipeSize, $pipeGauge, $turfType, $H, $civilBedType);
        $pkg = $allPackages[$selectedPkg] ?? $allPackages['commercial'];

        $baseArea = $specs['base_area_sft'];
        $perimeterRft = $specs['perimeter_rft'];
        $totalPoles = max(1, $specs['total_poles']);

        $poleProfile = $specs['pole_profile'];
        $sportProfile = $specs['sport_profile'];
        $flooringProfile = $specs['flooring_profile'];
        $civilBedProfile = $specs['civil_bed_profile'];

        $docItems = [];
        // --- OUTDOOR MULTI SPORTS FLOORING AMENITIES (120ft x 60ft) MULTI-PACKAGE PROPOSAL ---
        if ($sportType === 'outdoor_multi_sports_120x60') {
            if ($selectedPkg === 'essential') {
                $effectiveTargetRate = 266.58;
                $docItems = [
                    [
                        'category'    => 'Drainage',
                        'item_title'  => 'Construction of open saucer drain',
                        'description' => "Drainage\nConstruction of open saucer drain\nOpen saucer drain of width (6 inches x 24 inches). Standard grade gradient channel drain to corner water discharge point.",
                        'hsn'         => '9954',
                        'qty'         => 120,
                        'unit'        => 'Rft',
                        'rate'        => 400.00,
                        'amount'      => 48000.00,
                        'specs'       => [
                            "Open saucer drain width: (6 inches x 24 inches)",
                            "The height of drain made to match the court gradient",
                            "Channelizes water till one corner of the court",
                            "Onward drainage connection to be provided by client"
                        ],
                        'gst_rate'    => 18.0
                    ],
                    [
                        'category'    => 'Sub base',
                        'item_title'  => 'Asphalt Subbase Construction',
                        'description' => "Sub base\nAsphalt Subbase Construction:\n*Compacted WMM Base (100mm) using 40-60mm Stone\n*Cleaning & Tack/Primer Coat\n*Bituminous Macadam (BM) Layer up to 30mm\n*Asphalt Concrete (AC) wearing course up to 20mm\n*Seal coat surface finishing for synthetic sports installation.",
                        'hsn'         => '9954',
                        'qty'         => 7200,
                        'unit'        => 'Sqft',
                        'rate'        => 85.00,
                        'amount'      => 612000.00,
                        'specs'       => [
                            "100mm Compacted WMM using Stone Size 40-60mm",
                            "Wire brushing cleaning and Tack/Primer coat",
                            "Bituminous Layer up to 30mm thickness",
                            "Compacted AC wearing course up to 20mm",
                            "Bituminous seal coat for synthetic court installation"
                        ],
                        'gst_rate'    => 18.0
                    ],
                    [
                        'category'    => 'Fabrication',
                        'item_title'  => 'Fabrication of 15 feet height Fencing',
                        'description' => "Fabrication\nFabrication of 15 feet height Fencing\nFencing Height: 15 ft overall height.\nVertical Posts: 60 × 60 mm MS square pipes with 2.5 mm wall thickness.\nHorizontal Members: 40 × 40 mm MS square pipes.\nPost Spacing: 10 ft centre-to-centre with 3 horizontal support tiers.\nFinish: Primer and Enamel Paint in Bus Green colour.",
                        'hsn'         => '9954',
                        'qty'         => 5400,
                        'unit'        => 'Sqft',
                        'rate'        => 85.00,
                        'amount'      => 459000.00,
                        'specs'       => [
                            "Fencing Height: 15 ft overall height (5,400 Sqft perimeter)",
                            "Vertical Posts: 60 × 60 mm MS square pipes (2.5mm wall)",
                            "Horizontal Members: 40 × 40 mm MS square pipes",
                            "10 ft bay spacing with 3 tiers of horizontal pipe supports",
                            "Corrosion-resistant primer and Bus Green enamel paint"
                        ],
                        'gst_rate'    => 18.0
                    ],
                    [
                        'category'    => 'Nylon Net',
                        'item_title'  => 'Nylon Netting',
                        'description' => "Nylon Net\nRUSSEA™ High-Tenacity Green Net 2.0mm thick - 45mm box size. UV-stabilized weather-resistant perimeter containment net installed on all four sides.",
                        'hsn'         => '5608',
                        'qty'         => 5824,
                        'unit'        => 'Sqft',
                        'rate'        => 18.00,
                        'amount'      => 104832.00,
                        'specs'       => [
                            "RUSSEA™ High-Tenacity Green Net 2.0mm thick - 45mm mesh",
                            "Four-side perimeter enclosure netting",
                            "High-tensile wire rope tensioning perimeter framework",
                            "UV-stabilized all-weather impact containment"
                        ],
                        'gst_rate'    => 18.0
                    ],
                    [
                        'category'    => 'Acrylic sports flooring',
                        'item_title'  => 'Acrylic 5-Layer Sports Flooring System',
                        'description' => "Acrylic sports flooring\nAcrylic 5-Layer - Synthetic Sports Flooring System\nAll-weather synthetic acrylic court system for fast play and high traction.\n5 Layer System: (1 layer primer + 1 layer acrylic resurfacer + 2 layers acrylic wear coats + 1 top coat with silica sand & UV inhibitors + Official dual-game line markings for Badminton & Basketball).",
                        'hsn'         => '9954',
                        'qty'         => 7200,
                        'unit'        => 'Sqft',
                        'rate'        => 72.00,
                        'amount'      => 518400.00,
                        'specs'       => [
                            "Court Area: 120 FT × 60 FT = 7,200 Sqft",
                            "5-Layer All-Weather Synthetic Acrylic System",
                            "UV-granules and silica sand non-skid texturing",
                            "Includes official dual-game line markings for Badminton (yellow) & Basketball (white)"
                        ],
                        'gst_rate'    => 18.0
                    ],
                    [
                        'category'    => 'Tennis / Badminton Net & Posts',
                        'item_title'  => 'Tournament Net & MS Posts',
                        'description' => "Tennis / Badminton Net & Posts\nFixed MS Posts with internal tensioning ratchet and regulation tournament net with red weighted protective base sleeves.",
                        'hsn'         => '9506',
                        'qty'         => 1,
                        'unit'        => 'Pair',
                        'rate'        => 38000.00,
                        'amount'      => 38000.00,
                        'specs'       => [
                            "Heavy MS pipe posts with tension ratchet system",
                            "Branded tournament-grade regulation net",
                            "Counterweighted red safety base sleeves for court width span"
                        ],
                        'gst_rate'    => 18.0
                    ],
                    [
                        'category'    => 'Basketball poles',
                        'item_title'  => 'Fixed Basketball Poles (10 FT Rim)',
                        'description' => "Basketball poles\nFixed Heavy MS Basketball Poles\n*Heavy MS pipe with 1.2m extension overhang\n*10 FT (3.05m) regulation rim height with breakaway ring & net\n*15mm Acrylic backboard (180cm x 105cm)\n*Protective red base safety padding sleeve.",
                        'hsn'         => '9506',
                        'qty'         => 1,
                        'unit'        => 'Pair',
                        'rate'        => 139144.00,
                        'amount'      => 139144.00,
                        'specs'       => [
                            "Heavy MS upright pole with 1.2m gooseneck extension",
                            "Exactly 10 FT (3.05m) regulation rim height",
                            "15mm Acrylic Board: 180cm × 105cm",
                            "Olympian breakaway ring and red protective base padding sleeve"
                        ],
                        'gst_rate'    => 18.0
                    ],
                ];
            } elseif ($selectedPkg === 'premium_pro') {
                $effectiveTargetRate = 360.49;
                $docItems = [
                    [
                        'category'    => 'Drainage',
                        'item_title'  => 'Construction of Heavy Saucer Drain',
                        'description' => "Drainage\nConstruction of Heavy Pre-Cast Saucer Drain\nOpen saucer drain of width (9 inches x 24 inches) with reinforced concrete gradient bed and heavy polymer grates at discharge corners.",
                        'hsn'         => '9954',
                        'qty'         => 120,
                        'unit'        => 'Rft',
                        'rate'        => 500.00,
                        'amount'      => 60000.00,
                        'specs'       => [
                            "Heavy-duty pre-cast saucer drain: (9 inches x 24 inches)",
                            "High-flow gradient profiling to storm-water collection corners",
                            "Reinforced corner junction box with debris collector",
                            "Onward storm-water connection provided by client"
                        ],
                        'gst_rate'    => 18.0
                    ],
                    [
                        'category'    => 'Sub base',
                        'item_title'  => 'Heavy-Duty Asphalt Subbase Construction',
                        'description' => "Sub base\nHeavy-Duty Asphalt Subbase Construction:\n*125mm Compacted WMM using Stone Size 40-60mm\n*Wire Brushing & High-Tack Primer Coat (3.5kg/10 Sqm)\n*Bituminous Macadam (BM) Layer of 50mm compacted thickness (3.5% binder)\n*Dense Asphalt Concrete (AC) wearing course of 30mm with 6mm aggregate\n*Heavy seal coat layer with high-grade bitumen for cushion acrylic flooring.",
                        'hsn'         => '9954',
                        'qty'         => 7200,
                        'unit'        => 'Sqft',
                        'rate'        => 108.00,
                        'amount'      => 777600.00,
                        'specs'       => [
                            "125mm Heavy Compacted WMM Base",
                            "Tack Coat (up to 3.5kg per 10 Sq. Meter)",
                            "Heavy BM Layer of 50mm compacted thickness",
                            "High-density AC wearing course of 30mm",
                            "Premium bitumen seal coat for cushion acrylic installation"
                        ],
                        'gst_rate'    => 18.0
                    ],
                    [
                        'category'    => 'Fabrication',
                        'item_title'  => 'Heavy Structural 15 Feet Fencing',
                        'description' => "Fabrication\nHeavy Structural 15 Feet Fencing\nFencing Height: 15 ft overall height.\nVertical Posts: 80 × 80 mm MS square pipes with 3.2 mm heavy wall thickness.\nHorizontal Members: 60 × 60 mm MS square pipes with 2.5 mm wall thickness.\nPost Spacing: 10 ft centre-to-centre with 3 horizontal support tiers & diagonal corner bracing.\nFinish: Dual anti-corrosion epoxy primer + Polyurethane (PU) Enamel Paint in Bus Green colour.",
                        'hsn'         => '9954',
                        'qty'         => 5400,
                        'unit'        => 'Sqft',
                        'rate'        => 110.00,
                        'amount'      => 594000.00,
                        'specs'       => [
                            "Fencing Height: 15 ft overall height (5,400 Sqft area)",
                            "Vertical Posts: 80 × 80 mm MS square pipes (3.2mm wall thickness)",
                            "Horizontal Members: 60 × 60 mm MS square pipes (2.5mm wall)",
                            "10 ft bay spacing with 3 tiers & reinforced corner portal braces",
                            "Epoxy primer + UV-resistant Polyurethane (PU) Bus Green finish"
                        ],
                        'gst_rate'    => 18.0
                    ],
                    [
                        'category'    => 'Nylon Net',
                        'item_title'  => 'RUSSEA™ UV-Max Braided Netting',
                        'description' => "Nylon Net\nRUSSEA™ High-Tenacity Braided Green Net 3.0mm thick - 40mm box size. Maximum-durability UV-stabilized impact netting on all 4 sides with heavy 10mm perimeter border ropes and steel tensioners.",
                        'hsn'         => '5608',
                        'qty'         => 5824,
                        'unit'        => 'Sqft',
                        'rate'        => 26.00,
                        'amount'      => 151424.00,
                        'specs'       => [
                            "RUSSEA™ 3.0mm UV-Max High-Tenacity Braided Netting (40mm box size)",
                            "Exceeds Russea™/Tuff specifications for sports impact",
                            "10mm heavy reinforced border ropes and stainless steel rigging clips",
                            "Drum-tight four-side perimeter enclosure"
                        ],
                        'gst_rate'    => 18.0
                    ],
                    [
                        'category'    => 'Acrylic sports flooring',
                        'item_title'  => '10-Layer Cushion Pro Acrylic Sports System',
                        'description' => "Acrylic sports flooring\n10-Layer Cushion Pro Acrylic Sports Flooring System\nTournament-grade shock-absorbing acrylic surface engineered for multi-sport play.\n10 Layer System: (1 primer + 2 resurfacers + 4 liquid rubber cushion shock-absorption layers + 2 acrylic color pre-coats + 1 UV-top coat blended with ceramic granules & silica sand + Official dual-game line markings for Badminton & Basketball).",
                        'hsn'         => '9954',
                        'qty'         => 7200,
                        'unit'        => 'Sqft',
                        'rate'        => 108.00,
                        'amount'      => 777600.00,
                        'specs'       => [
                            "Court Surface Area: 120 FT × 60 FT = 7,200 Sqft",
                            "10-Layer Cushion Pro System with 4 Liquid Rubber Cushion Layers",
                            "High shock-absorption reduces player joint strain & fatigue",
                            "Dual-game line markings for Badminton (yellow) & Basketball (white)"
                        ],
                        'gst_rate'    => 18.0
                    ],
                    [
                        'category'    => 'Tennis / Badminton Net & Posts',
                        'item_title'  => 'Pro Tournament Net & Brass Ratchet Posts',
                        'description' => "Tennis / Badminton Net & Posts\nHeavy 80×80mm MS Posts with precision internal brass ratchet mechanism, counterweighted red safety base sleeves, and official tournament-grade braided net.",
                        'hsn'         => '9506',
                        'qty'         => 1,
                        'unit'        => 'Pair',
                        'rate'        => 55000.00,
                        'amount'      => 55000.00,
                        'specs'       => [
                            "80×80mm heavy MS square pipe with internal brass ratchet system",
                            "Precision tension control with stainless steel wire cable",
                            "Branded BWF/ITF regulation tournament net with quad-stitched headband",
                            "Heavy counterweighted red base sleeves for rigid court-width tensioning"
                        ],
                        'gst_rate'    => 18.0
                    ],
                    [
                        'category'    => 'Basketball poles',
                        'item_title'  => 'Pro Club Basketball Posts (10 FT Rim)',
                        'description' => "Basketball poles\nPro Club Fixed Heavy MS Basketball Posts\n*Heavy 8\" (200mm) MS tubular column with 1.5m extension overhang\n*Exactly 10 FT (3.05m) regulation rim height with Olympian spring breakaway rim\n*25mm Toughened Glass/Imported Acrylic backboard (180cm x 105cm)\n*Vibrant red protective base safety padding sleeve & backboard edge padding.",
                        'hsn'         => '9506',
                        'qty'         => 1,
                        'unit'        => 'Pair',
                        'rate'        => 179904.00,
                        'amount'      => 179904.00,
                        'specs'       => [
                            "Heavy 8\" (200mm) MS main column with 1.5m gooseneck overhang",
                            "Exactly 10 FT (3.05m) regulation rim height",
                            "25mm Toughened Glass / Imported Acrylic backboard (180cm × 105cm)",
                            "Olympian spring breakaway rim, net, and full base + board safety padding"
                        ],
                        'gst_rate'    => 18.0
                    ],
                ];
            } else {
                // Commercial Standard Package (Default proposal standard)
                $effectiveTargetRate = 302.93;
                $docItems = [
                    [
                        'category'    => 'Drainage',
                        'item_title'  => 'Construction of open saucer drain',
                        'description' => "Drainage\nConstruction of open saucer drain\nOpen saucer drain of width (6 inches x 24 inches) or (9 inches x 24 inches). The height of drain will be made to match the side of the court. This drain will channelize water till one corner of the court. From their drainage connection to be provided by client.",
                        'hsn'         => '9954',
                        'qty'         => 120,
                        'unit'        => 'Rft',
                        'rate'        => 450.00,
                        'amount'      => 54000.00,
                        'specs'       => [
                            "Open saucer drain width: (6 inches x 24 inches) or (9 inches x 24 inches)",
                            "The height of drain will be made to match the side of the court",
                            "Channelizes water till one corner of the court",
                            "Drainage connection from corner to be provided by client"
                        ],
                        'gst_rate'    => 18.0
                    ],
                    [
                        'category'    => 'Sub base',
                        'item_title'  => 'Asphalt',
                        'description' => "Sub base\nAsphalt\nAsphalt Subbase Construction:\n*Layer of up to 4\" (100mm) Compacted WMM using Stone Size 40 MM to 60 MM and Compaction of same in One Layer\n*Cleaning of Sub base (Wire Brushing) before starting Asphalting Layers\n*Tack/Primer Coat to be applied before BM & AC Layers (Qty Consumption will have Tack Coat of Up to 3kg Per 10 Sq. Meter)\n*Bituminous Layer of compacted thickness of up to 30 mm using mix of 20 and 10 MM Stone with 80-100/60-70 Grade Bitumen(Subject to availability). Bituminous binder percentage upto3.5%.\n*Compacting thickness of AC of up to 20 mm using 6mm Stone with 80-100/60-70 Grade Bitumen( Subject to availability). Bituminous binder percentage up to 3.5-4%.\n*Final Layer of Seal Coat using Stone Dust with Bitumen to make the surface appropriate for Synthetic Installation\n*If a hot mix plant is being brought to the site, path, location and barrication should be provided by client, keeping in mind the atmospheric and environmental factors.",
                        'hsn'         => '9954',
                        'qty'         => 7200,
                        'unit'        => 'Sqft',
                        'rate'        => 95.00,
                        'amount'      => 684000.00,
                        'specs'       => [
                            "Layer of up to 4\" (100mm) Compacted WMM using Stone Size 40 MM to 60 MM (One Layer)",
                            "Cleaning of Sub base (Wire Brushing) before starting Asphalting Layers",
                            "Tack/Primer Coat applied before BM & AC Layers (up to 3kg per 10 Sq. Meter)",
                            "Bituminous Layer of compacted thickness up to 30 mm (20 & 10 MM Stone, 3.5% binder)",
                            "Compacting thickness of AC up to 20 mm (6mm Stone, 3.5-4% Bituminous binder)",
                            "Final Layer of Seal Coat using Stone Dust with Bitumen for synthetic installation"
                        ],
                        'gst_rate'    => 18.0
                    ],
                    [
                        'category'    => 'Fabrication',
                        'item_title'  => 'Fabrication of 15 feet height',
                        'description' => "Fabrication\nFabrication of 15 feet height\nFencing Height: 15 ft overall height\nVertical Posts: 72 × 72 mm MS square pipes with 3 mm wall thickness.\nHorizontal Members: 50 × 50 mm MS square pipes with 2 mm wall thickness.\nPost Spacing: 10 ft centre-to-centre with Top, Centre & Bottom horizontal supports.\nInstallation: Vertical posts embedded 2 ft in ground or fixed on base plate with anchor fasteners.\nFinish: Primer and Enamel Paint in Bus Green colour.",
                        'hsn'         => '9954',
                        'qty'         => 5400,
                        'unit'        => 'Sqft',
                        'rate'        => 95.00,
                        'amount'      => 513000.00,
                        'specs'       => [
                            "Fencing Height: 15 ft overall height (Total Area: 5,400 Sqft)",
                            "Vertical Posts: 72 × 72 mm MS square pipes with 3 mm wall thickness",
                            "Horizontal Members: 50 × 50 mm MS square pipes with 2 mm wall thickness",
                            "Post Spacing: 10 ft centre-to-centre with Top, Centre & Bottom horizontal supports",
                            "Installation: Vertical posts embedded 2 ft in ground or fixed on base plate with anchor fasteners",
                            "Finish: Primer and Enamel Paint in Bus Green colour"
                        ],
                        'gst_rate'    => 18.0
                    ],
                    [
                        'category'    => 'Nylon Net',
                        'item_title'  => 'Net',
                        'description' => "Nylon Net\nNet\nRUSSEA™ High-Tenacity Green Net 2.5mm thick- 40mm box size (Russea™/Tuff equivalent & exceeding specifications). Netting to be done on all four sides, provided to minimize the ball from escaping outside of the net",
                        'hsn'         => '5608',
                        'qty'         => 5824,
                        'unit'        => 'Sqft',
                        'rate'        => 22.00,
                        'amount'      => 128128.00,
                        'specs'       => [
                            "RUSSEA™ High-Tenacity Green Net 2.5mm thick - 40mm box size",
                            "Russea™/Tuff equivalent & exceeding specifications",
                            "Netting installed on all four sides (5,824 Sqft total area)",
                            "UV-stabilized impact containment netting"
                        ],
                        'gst_rate'    => 18.0
                    ],
                    [
                        'category'    => 'Acrylic sports flooring',
                        'item_title'  => 'Acrylic 8 layer - Synthetic sports flooring',
                        'description' => "Acrylic sports flooring\nAcrylic 8 layer - Synthetic sports flooring\nAcrylic courts give the necessary speed, texture, and vibrant colors. Acrylic Coating Systems are multi-layer latex based paint systems that establish or restore surface texture, protect underlying asphalt systems from the weather and climatic change, enhance overall playability by establishing play speed and ultimately provide the surface with a vibrant color scheme.\n\n8 Layer System :- (1 layer of primer + 1 layer of resurfacer + 3 layers of liquid rubber + 1 layer of resurfacer + 1 layers of Acrylic pre coats + 1 acrylic top coat which is blended with UV granules and Silica sand + Line marking )\n\nProduct Specification\n• High density for improved strength.\n• Environment friendly.",
                        'hsn'         => '9954',
                        'qty'         => 7200,
                        'unit'        => 'Sqft',
                        'rate'        => 85.00,
                        'amount'      => 612000.00,
                        'specs'       => [
                            "Court Surface Area: 120 FT × 60 FT = 7,200 Sqft",
                            "8 Layer System: 1 primer + 1 resurfacer + 3 liquid rubber + 1 resurfacer + 1 acrylic pre-coats + 1 acrylic top coat with UV granules & silica sand + Line marking",
                            "High density formulation for improved strength & play speed control",
                            "Environment friendly latex-based all-weather coating system"
                        ],
                        'gst_rate'    => 18.0
                    ],
                    [
                        'category'    => 'Tennis Poles',
                        'item_title'  => 'Center Tournament Net & Posts (1 Pair)',
                        'description' => "Tennis / Badminton Net & Posts\nFixed MS Posts with internal tension ratchet & professional tournament net\n- Fixed Post Made of 80mm x 80mm heavy MS pipe\n- With special internal ratchet system for tightening of rope\n- Enables perfect tension control of the net\n- With branded professional quality net and red weighted protective base sleeves.",
                        'hsn'         => '9506',
                        'qty'         => 1,
                        'unit'        => 'Pair',
                        'rate'        => 45000.00,
                        'amount'      => 45000.00,
                        'specs'       => [
                            "Fixed Post Made of 80mm x 80mm MS pipe",
                            "Special internal ratchet system for tightening of rope",
                            "Ratchet enables perfect tension control of the net",
                            "Supplied with branded professional tournament quality net & red weighted base sleeves"
                        ],
                        'gst_rate'    => 18.0
                    ],
                    [
                        'category'    => 'Basketball poles',
                        'item_title'  => 'Fixed Basketball Poles with 10 FT Rim (1 Pair)',
                        'description' => "Basketball poles\nFixed Basketball poles\n*Pole will Made of (6\") Pipe\n*equipped with 1.2 m extention.\n*Exactly 10 FT (3.05m) regulation rim height with Olympian breakaway ring\n*Acrylic Board Thickness - 20 MM (Imported Quality), Size of Board - 180cm x 105cm\n*Olympion Ring & Quality Net\n*Spray Painted with red protective base padding sleeve\n*Approximately 200 kgs per pair",
                        'hsn'         => '9506',
                        'qty'         => 1,
                        'unit'        => 'Pair',
                        'rate'        => 145000.00,
                        'amount'      => 145000.00,
                        'specs'       => [
                            "Heavy-duty pole made of 6\" (150mm) MS pipe with 1.2 m extension overhang",
                            "Exactly 10 FT (3.05m) regulation rim height with Olympian spring breakaway ring",
                            "Imported Acrylic Board: 20 MM thickness, Size: 180cm x 105cm",
                            "Industrial spray painted finish with red protective base padding, ~200 kgs per pair"
                        ],
                        'gst_rate'    => 18.0
                    ],
                ];
            }

                    foreach ($docItems as &$it) {
            $desc = $it['description'] ?? '';
            $hsn = function_exists('resolveItemHsn') ? resolveItemHsn($desc, $it['hsn'] ?? '') : ($it['hsn'] ?? '9954');
            $it['hsn'] = $hsn;
            $it['gst_rate'] = function_exists('resolveItemGstRate') ? resolveItemGstRate($desc, $hsn, $it['gst_rate'] ?? null) : (float)($it['gst_rate'] ?? 18.0);
            $it['unit'] = function_exists('normalizeUqcUnit') ? normalizeUqcUnit($it['unit'] ?? 'SQF-SQUARE FEET') : 'SQF-SQUARE FEET';
        }
        unset($it);
        $calculatedSubtotal = array_sum(array_column($docItems, 'amount'));

            $scopeItems = [
                [
                    'title'       => 'Drainage — Construction of Open Saucer Drain (120 Rft)',
                    'description' => "Open saucer drain of width (6 inches x 24 inches) or (9 inches x 24 inches). The height of drain will be made to match the side of the court gradient. This drain will channelize water till one corner of the court.",
                    'note'        => 'From corner of court, onward drainage connection and storm-water discharge line to be provided by client.'
                ],
                [
                    'title'       => 'Sub base — Machine-Laid Asphalt Subbase Construction (7,200 Sqft)',
                    'description' => "Asphalt Subbase Construction:\n• Layer of up to 4\" (100mm) Compacted WMM using Stone Size 40 MM to 60 MM and Compaction in One Layer.\n• Cleaning of Sub base (Wire Brushing) before starting Asphalting Layers.\n• Tack/Primer Coat applied before BM & AC Layers (Qty Consumption up to 3kg Per 10 Sq. Meter).\n• Bituminous Layer of compacted thickness up to 30 mm using mix of 20 and 10 MM Stone with 80-100/60-70 Grade Bitumen (Bituminous binder up to 3.5%).\n• Compacting thickness of AC up to 20 mm using 6mm Stone with 80-100/60-70 Grade Bitumen (Bituminous binder up to 3.5-4%).\n• Final Layer of Seal Coat using Stone Dust with Bitumen to make surface appropriate for synthetic installation.",
                    'note'        => 'If a hot mix plant is brought to the site, approach path, location and barrication should be provided by client, keeping in mind atmospheric & environmental factors.'
                ],
                [
                    'title'       => 'Fabrication — 15 Feet Height Structural Fencing (5,400 Sqft)',
                    'description' => "Fencing Height: 15 ft overall height.\n• Vertical Posts: 72 × 72 mm MS square pipes with 3 mm wall thickness.\n• Horizontal Members: 50 × 50 mm MS square pipes with 2 mm wall thickness.\n• Post Spacing: 10 ft centre-to-centre with Top, Centre & Bottom horizontal supports.\n• Installation: Vertical posts embedded 2 ft in ground or fixed on base plate with anchor fasteners.\n• Finish: High-durability Primer and Enamel Paint in Bus Green colour.",
                    'note'        => 'Structural column alignment and foundation embedment executed by trained fabrication team.'
                ],
                [
                    'title'       => 'Nylon Net — RUSSEA™ High-Tenacity Green Net (5,824 Sqft)',
                    'description' => "RUSSEA™ High-Tenacity Green Net 2.5mm thick - 40mm box size (Russea™/Tuff equivalent & exceeding specifications). Netting to be done on all four sides, provided to minimize the ball from escaping outside of the court.",
                    'note'        => 'Tensioned with perimeter high-tensile wire rope border, fasteners & steel clips for drum-tight aesthetic finish.'
                ],
                [
                    'title'       => 'Acrylic Sports Flooring — Synthetic Sports System (7,200 Sqft)',
                    'description' => "Acrylic courts give the necessary speed, texture, and vibrant colors. Multi-layer latex-based paint system that establishes surface texture, protects underlying asphalt from weather & climatic changes, enhances play speed and provides a vibrant color scheme.\n• Multi-Layer System with primer, resurfacer, liquid rubber cushion layers, acrylic pre-coats, UV-top coat and line markings.\n• Product Specification: High density for improved strength, environment friendly formulation.",
                    'note'        => 'Includes official regulation dual-game line markings for both Badminton (contrasting yellow lines) and Basketball (white lines & red keys) matching site specifications.'
                ],
                [
                    'title'       => 'Center Tournament Net & Posts — Fixed Heavy MS Posts with Ratchet & Tournament Net (1 Pair)',
                    'description' => "• Fixed Post Made of 80mm x 80mm heavy MS pipe with heavy weighted red protective base sleeves.\n• Equipped with special internal ratchet system for precision tightening of rope.\n• The ratchet enables perfect tension control of the net.\n• Supplied with branded professional tournament-grade net suitable for Badminton and Tennis recreational play.",
                    'note'        => 'Heavy counterweighted red base sleeves for rigid tensioning across court width.'
                ],
                [
                    'title'       => 'Basketball Poles — Fixed Heavy MS Poles with 10 FT Rim & 20mm Acrylic Board (1 Pair)',
                    'description' => "• Pole made of heavy 6\" (150mm) MS pipe equipped with 1.2 m extension overhang.\n• Exactly 10 FT (3.05m) regulation rim height with Olympian breakaway ring and all-weather net.\n• Acrylic Board Thickness: 20 MM (Imported Quality), Size of Board: 180cm x 105cm.\n• Industrial spray painted finish with vibrant red protective base safety padding.",
                    'note'        => 'Complete foundation anchoring and counterbalance heavy embedding included in turnkey scope.'
                ],
            ];

            return [
                'specs'            => $specs,
                'selected_package' => $selectedPkg,
                'selected_pkg'     => [
                    'target_rate' => $effectiveTargetRate,
                    'name'        => ($selectedPkg === 'essential' ? 'Essential Quality Package' : ($selectedPkg === 'premium_pro' ? 'Premium Pro Club Package' : 'Commercial Standard Package')),
                ],
                'package_info'     => $pkg,
                'base_area_sft'    => $baseArea,
                'target_rate'      => $effectiveTargetRate,
                'target_total'     => $calculatedSubtotal,
                'boq_subtotal'     => $calculatedSubtotal,
                'items'            => $docItems,
                'doc_items'        => $docItems,
                'packages'         => [
                    'essential'   => [
                        'name'        => 'Essential Quality Package',
                        'target_rate' => 266.58,
                        'total'       => 1919376.00
                    ],
                    'commercial'  => [
                        'name'        => 'Commercial Standard Package',
                        'target_rate' => 302.93,
                        'total'       => 2181128.00
                    ],
                    'premium_pro' => [
                        'name'        => 'Premium Pro Club Package',
                        'target_rate' => 360.49,
                        'total'       => 2595528.00
                    ]
                ],
                'scope_items'      => $scopeItems,
                'spec_rows'        => getTurnkeyDetailedSpecRows($specs, $selectedPkg),
            ];
        }


        // 1. Civil Ground Bed / Terrace Base Plates
                if (is_string($layers)) {
            try { $layers = json_decode($layers, true) ?: []; } catch (\Exception $e) { $layers = []; }
        }
        $includeStructure = ($layers['structure'] ?? true) !== false;
        $includeNetting   = ($layers['netting'] ?? true) !== false;
        $includeLighting  = ($layers['lighting'] ?? true) !== false;
        $includeGates     = ($layers['gates'] ?? true) !== false;
        $includeCivil     = ($layers['civil'] ?? true) !== false && $civilBedProfile['has_civil'];
        $includeTurf      = ($layers['turf'] ?? true) !== false && $flooringProfile['has_turf'];
        $includePitch     = ($layers['pitch'] ?? true) !== false && (($customPitchLength ?? $specs['pitch_length_ft']) > 0);

        if ($includeCivil) {
            if ($sportType === 'multisport_arena_complex_185x60') {
                // Zone A: Cricket 100x60 Civil Sub-Base (Dynamically resolved from $civilBedProfile)
                $zoneACivilRate = (float)($civilBedProfile['rates'][$selectedPkg] ?? $civilBedProfile['rates']['commercial'] ?? 40.0);
                $zoneACivilAmt  = round(6000 * $zoneACivilRate, 2);

                $docItems[] = [
                    'description' => "Zone A: Cricket Ground Civil Sub-Base Bed (" . ($civilBedProfile['short_name'] ?? $civilBedProfile['name']) . ")\n" . ($civilBedProfile['desc'] ?? 'Engineered porous sub-base for cricket turf.'),
                    'hsn'         => '9954',
                    'qty'         => 6000,
                    'unit'        => 'SQFT',
                    'rate'        => $zoneACivilRate,
                    'amount'      => $zoneACivilAmt,
                    'specs'       => [
                        "Zone Area: 100 FT Length x 60 FT Width = 6,000 SFT",
                        "Base Specification: " . ($civilBedProfile['name'] ?? 'Civil Base Bed'),
                        "Foundation: Column Concrete Footings (1.5 FT x 1.5 FT x 3.0 FT Depth)"
                    ],
                    'gst_rate'    => 18.0,
                ];
                // Zone B: Badminton Two Courts Civil Sub-Base (Dynamically using selected $badmintonBedProfile)
                $badmintonBedKey     = $badmintonBedType ?? ($_POST['badminton_bed_type'] ?? ($specs['badminton_bed_type'] ?? 'rcc_m20_4inch_laser'));
                $badmintonBedCatalog = getBadmintonCivilBedProfiles();
                $badmintonBedProfile = $badmintonBedCatalog[$badmintonBedKey] ?? $badmintonBedCatalog['rcc_m20_4inch_laser'];

                if (!empty($badmintonBedProfile['has_civil']) && (float)$badmintonBedProfile['rate'] > 0) {
                    $badmintonBedRate = (float)$badmintonBedProfile['rate'];
                    $badmintonBedAmt  = round(2700 * $badmintonBedRate, 2);

                    $docItems[] = [
                        'description' => "Zone B: " . $badmintonBedProfile['name'] . "\n" . $badmintonBedProfile['desc'],
                        'hsn'         => '9954',
                        'qty'         => 2700,
                        'unit'        => 'SQFT',
                        'rate'        => $badmintonBedRate,
                        'amount'      => $badmintonBedAmt,
                        'specs'       => $badmintonBedProfile['specs'],
                        'gst_rate'    => 18.0,
                    ];
                }
// Zone C: Heavy-Duty 60mm M30 Concrete Interlocking Pavers & GSB Sub-Base Bed
                $docItems[] = [
                    'description' => "Zone C: Heavy-Duty 60mm M30 Concrete Interlocking Pavers & GSB Sub-Base Bed
Engineered granular sub-base preparation with 60mm M30 chamfered paver blocks for front approach, reception lobby & player lounge base.",
                    'hsn'         => '6810',
                    'qty'         => 2400,
                    'unit'        => 'SQFT',
                    'rate'        => 55.0,
                    'amount'      => 132000.0,
                    'specs'       => [
                        "Zone Area: 40 FT Length × 60 FT Width = 2,400 SFT (Front Utility & Amenities Suite Base)",
                        "Base Specification: 150mm compacted Granular Sub-Base (GSB) + 50mm river sand bedding layer",
                        "Flooring: 60mm heavy-duty M30 grade chamfered interlocking paver blocks with sand joint vibro-compaction"
                    ],
                    'gst_rate'    => 18.0,
                ];

                // Zone B: 4.5 FT Solid Masonry / Brick Plinth Wall & Concrete Coping (Badminton Enclosure)
                $docItems[] = [
                    'description' => "Zone B: 4.5 FT Solid Masonry / Brick Plinth Wall & Concrete Coping (Badminton Enclosure)
Full perimeter solid masonry base wall with cement plaster and cast concrete coping band around all 4 sides of the 45×60 FT badminton complex.",
                    'hsn'         => '9954',
                    'qty'         => 210,
                    'unit'        => 'RFT',
                    'rate'        => 495.0,
                    'amount'      => 103950.0,
                    'specs'       => [
                        "Enclosure Extent: All 4 perimeter sides of 45×60 FT Badminton Zone (45 + 45 + 60 + 60 = 210 RFT)",
                        "Wall Specification: 9-inch solid masonry / clay brick wall up to 4.5 FT height with cement plaster & paint finish",
                        "Top Trim: 4-inch cast-in-situ concrete coping band with chamfered drip edges",
                        "Integration: Provides solid rebound protection, draft shielding & anchor base for 25.5 FT upper shade netting"
                    ],
                    'gst_rate'    => 18.0,
                ];
            } else {
                $civilUnitRate = (float)($civilBedProfile['rates'][$selectedPkg] ?? $civilBedProfile['rates']['commercial'] ?? 40);
                $civilAmt = round($baseArea * $civilUnitRate, 2);
                $isTerrace = ($civilBedProfile['key'] === 'terrace_base_plates' || $civilBedProfile['key'] === 'rooftop_base_plates');
                $item1Title = $isTerrace ? ("Civil Base & Column Mounting (" . $civilBedProfile['short_name'] . ")") : ("Civil Ground Preparation & Base Slab (" . $civilBedProfile['short_name'] . ")");
                $item1FoundSpec = $isTerrace
                    ? "Mounting: {$totalPoles} Heavy-duty base flange plates (8mm–10mm) anchored with M12/M16 chemical bolts & bitumen pads (No Ground Digging)"
                    : "Foundation: {$totalPoles} concrete footings (1.5' x 1.5' x 3' deep)";

                $docItems[] = [
                    'description' => $item1Title . "\n" . $civilBedProfile['desc'],
                    'hsn'         => '9954',
                    'qty'         => $baseArea,
                    'unit'        => 'SQFT',
                    'rate'        => $civilUnitRate,
                    'amount'      => $civilAmt,
                    'specs'       => [
                        "Court Area: {$L}ft x {$W}ft = " . number_format($baseArea) . " SFT (" . ucfirst($specs['shape']) . ")",
                        "Specification: " . $civilBedProfile['gauge'],
                        $item1FoundSpec
                    ],
                    'gst_rate'    => 18.0,
                ];
            }
        }

        // 2. Drainage (Only if civil scope is active)
        if ($includeCivil) {
            $drainUnitRate = ($selectedPkg === 'essential') ? 65.0 : (($selectedPkg === 'premium_pro') ? 110.0 : 85.0);
            $drainRft = $specs['perimeter_rft'];
            $drainAmt = round($drainRft * $drainUnitRate, 2);
            $docItems[] = [
                'description' => "Perimeter Drainage System\nEngineered perimeter trench drainage along boundary for surface storm water runoff and subgrade protection.",
                'hsn'         => '9954',
                'qty'         => $drainRft,
                'unit'        => 'RFT',
                'rate'        => $drainUnitRate,
                'amount'      => $drainAmt,
                'specs'       => ["Total Perimeter: {$drainRft} Running Feet"],
                'gst_rate'    => 18.0,
            ];
        }

        // 3. Structural Framework (Columns, Horizontal Ties, Ladder Rungs, Roof Steel)
        if ($includeStructure) {
        $isTrainingNet = !empty($specs['is_training_net']);
        $isSmallCourt = ($baseArea <= 1500) || $isTrainingNet;

        if ($isSmallCourt) {
            $uprightFtRate = min(290.0, (float)($poleProfile['upright_ft_rate'] ?? 300) * 0.65);
            $colBasePlateCost = ($selectedPkg === 'essential') ? 900 : (($selectedPkg === 'premium_pro') ? 1600 : 1200);
            $tiePurlinUnitRate = 55; // ₹55/RFT for practice cages
            $rungsPerPole = 0;
            $colRungCost = 0;
        } else {
            $uprightFtRate = (float)($poleProfile['upright_ft_rate'] ?? 560);
            $colBasePlateCost = ($selectedPkg === 'essential') ? 1600 : (($selectedPkg === 'premium_pro') ? 2800 : 2200);
            $tiePurlinUnitRate = 70;
            $rungsPerPole = ($poleProfile['has_ladder']) ? max(0, (int)floor($H / 1.5)) * $poleProfile['faces_laced'] : 0;
            $colRungCost = $rungsPerPole * 180;
        }
        $colUprightCost = $H * $uprightFtRate;
        
        $tiePurlinTotal = $specs['horizontal_pipe_rft'] * $tiePurlinUnitRate;
        $colTieShare = round($tiePurlinTotal / $totalPoles, 2);
        
        $roofSteelTotal = 0;
        if ($roofType === 'truss_top') {
            $roofSteelTotal = $specs['truss_rft'] * 125;
        } elseif ($roofType === 'pipe_top') {
            $roofSteelTotal = $specs['cross_pipe_rft'] * ($isSmallCourt ? 60 : 80);
        } elseif ($roofType === 'light_cable_top') {
            $roofSteelTotal = $baseArea * ($isSmallCourt ? 4.5 : 6.5);
        }
        $colRoofShare = round($roofSteelTotal / $totalPoles, 2);
        
        $singleColumnRate = round($colUprightCost + $colBasePlateCost + $colTieShare + $colRungCost + $colRoofShare, 2);
        $structAmt = round($totalPoles * $singleColumnRate, 2);
        
        $structSpecs = [
            "Vertical Columns: {$totalPoles} Nos - " . $poleProfile['full_name'] . ($isTrainingNet ? " (2 Length Sides + 1 Rear Wall, Open Front Portal)" : ""),
            "Column Upright Specification: " . $poleProfile['gauge_label'] . " (" . (int)$H . " FT Clear Height)",
            "Horizontal Tie Framework: {$specs['tier_count']} tiers on enclosed sides ({$specs['horizontal_pipe_rft']} RFT, {$specs['pipe_20ft_count']} pipes of 20ft, 14 Gauge)" . ($isTrainingNet ? " + Front Portal Top Cross Tie Beam" : ""),
            "Bay Spacing: ~{$specs['length_spans']['bay_width']}ft c/c along practice lane",
        ];
        if ($poleProfile['has_ladder']) {
            $structSpecs[] = "Ladder Rungs: {$specs['total_ladder_rungs']} Nos welded steps/bracing (" . $poleProfile['gauge_short'] . ") spaced @ 1.5ft c/c for climbing inspection & wind rigidity";
        }
        if ($roofType === 'truss_top') {
            $structSpecs[] = "Roof Trusses: {$specs['trusses_count']} Nos triangular lattice trusses spanning {$W}ft width (" . number_format($specs['truss_rft']) . " RFT)";
        } elseif ($roofType === 'pipe_top') {
            $structSpecs[] = 'Cross Pipes: ' . $specs['cross_pipes_count'] . ' Nos 2.0" / 2.5" horizontal tie cross pipes spanning ' . $W . 'ft width';
        } elseif ($roofType === 'light_cable_top') {
            $structSpecs[] = "Overhead Net Support: High-tension wire rope cable suspension grid (Zero rigid top steel)";
        } else {
            $structSpecs[] = "Open Top configuration (No overhead steel or net)";
        }

        $docItems[] = [
            'description' => "Structural MS/GI Columns & Horizontal Tie Framework\n" . $poleProfile['desc'] . ($isTrainingNet ? " (Engineered for 3-sided practice cage with clear open bowling run-up portal)" : ""),
            'hsn'         => '7308',
            'qty'         => $totalPoles,
            'unit'        => 'NOS',
            'rate'        => $singleColumnRate,
            'amount'      => $structAmt,
            'specs'       => $structSpecs,
            'gst_rate'    => 18.0,
        ];

        }

        // 4. Perimeter Netting
        $sideNetProfile = $specs['side_net_profile'];
        $sideNetSft = $specs['perimeter_net_sft'];
        $sideNetUnitRate = (float)($sideNetProfile['net_rate'] ?? 13.50);
        if ($selectedPkg === 'essential') $sideNetUnitRate = max(8.0, round($sideNetUnitRate * 0.88, 2));
        elseif ($selectedPkg === 'premium_pro') $sideNetUnitRate = round($sideNetUnitRate * 1.15, 2);
        $sideNetAmt = round($sideNetSft * $sideNetUnitRate, 2);

        if ($sportType === 'multisport_complex_105x170') {
            $sideNetSft = 6900.0;
            $sideNetAmt = round($sideNetSft * $sideNetUnitRate, 2);
            $sideNetTitle = "Zone 2 (Cricket): Perimeter Ball-Stop Netting System (" . $sideNetProfile['name'] . ")";
            $sideNetDesc  = $sideNetProfile['full_desc'] . " enclosing 3 outer perimeter sides of the 90 FT × 50 FT Outdoor Box Cricket Arena up to 30 FT height, reinforced with 8mm border ropes and wire rope suspension.";
        } else {
        if ($sportType === 'multisport_complex_105x170') {
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
        }
        }

        $docItems[] = [
            'description' => $sideNetTitle . "\n" . $sideNetDesc,
            'hsn'         => '5608',
            'qty'         => $sideNetSft,
            'unit'        => 'SQFT',
            'rate'        => $sideNetUnitRate,
            'amount'      => $sideNetAmt,
            'specs'       => [
                "Enclosed Net Area: {$perimeterRft} RFT x {$H} FT = {$sideNetSft} SFT (3 sides, incl. overlap)",
                "Net Specification: " . $sideNetProfile['twine'] . " (" . $sideNetProfile['mesh'] . ")",
                "Tension Wire Rope: {$specs['wire_rope_rft']} RFT galvanized wire with turnbuckles & clamps"
            ],
            'gst_rate'    => 5.0,
        ];

        // 4B. Dual-Layer Windbreak & Anti-Glare Shade Netting (Perimeter / Badminton Protection)
        if (!empty($specs['has_shade_net']) && !empty($specs['shade_net_sft']) && $specs['shade_net_sft'] > 0) {
            $shadeRate = ($selectedPkg === 'essential') ? 5.50 : (($selectedPkg === 'premium_pro') ? 8.50 : 6.80);
            $shadeAmt  = round($specs['shade_net_sft'] * $shadeRate, 2);
            $shadeHeight = (int)($specs['shade_net_height_ft'] ?? $H);
            $docItems[] = [
                'description' => "75% High-Density HDPE Dark Green Anti-Glare & Windbreak Shade Netting\nHeavy-duty UV-stabilized 75% shade factor agro/sports fabric mounted as secondary outer barrier on all perimeter frames up to full {$shadeHeight} FT height. Deflects cross-winds for badminton shuttle stability and eliminates sky glare.",
                'hsn'         => '5608',
                'qty'         => $specs['shade_net_sft'],
                'unit'        => 'SQFT',
                'rate'        => $shadeRate,
                'amount'      => $shadeAmt,
                'specs'       => [
                    "Coverage Area: " . number_format($specs['shade_net_sft']) . " SFT (" . ($sportType === 'multisport_arena_complex_185x60' ? "All 4 sides of Badminton Court Zone" : "Perimeter sides") . ")",
                    "Enclosure Height: Full {$shadeHeight} FT Height Enclosure",
                    "Fabric Quality: 120 GSM Virgin HDPE with 75% shade factor & UV anti-aging stabilizer",
                    "Fastening: 4.0mm heavy-duty nylon cable ties @ 1.0 FT c/c & wire rope edge lacing"
                ],
                'gst_rate'    => 5.0,
            ];
        }

        // 5. Roof System (0.50mm Galvalume Metal Sheet Proof Roofing OR Overhead Netting)
        if (!empty($specs['is_metal_roof']) && !empty($specs['metal_sheet_sft'])) {
            // 5A. Secondary Roof Purlins
            $purlinRate = 65.0; // Rs 65/RFT
            $purlinAmt  = round($specs['roof_purlin_rft'] * $purlinRate, 2);
            $docItems[] = [
                'description' => "Structural Secondary Roof Purlin Grid Framework\nEngineered structural cold-formed Z-purlins / structural RHS tubular purlins spaced @ 4.5 FT c/c across width for corrugated metal sheet fastening.",
                'hsn'         => '7308',
                'qty'         => $specs['roof_purlin_rft'],
                'unit'        => 'RFT',
                'rate'        => $purlinRate,
                'amount'      => $purlinAmt,
                'specs'       => [
                    "Quantity: " . number_format($specs['roof_purlin_rft']) . " Running Feet of engineered roof purlins",
                    "Spacing: 4.5 FT center-to-center across width",
                    "Coating: Anti-corrosion zinc chromate primer & weather-resistant enamel coat"
                ],
                'gst_rate'    => 18.0,
            ];

            // 5B. 0.50mm Pre-Painted Galvalume (PPGL) Corrugated Profile Metal Roofing Sheets
            $metalSheetRate = ($selectedPkg === 'essential') ? 52.0 : (($selectedPkg === 'premium_pro') ? 68.0 : 58.0);
            $metalSheetAmt  = round($specs['metal_sheet_sft'] * $metalSheetRate, 2);
            $docItems[] = [
                'description' => "0.50mm Pre-Painted Galvalume (PPGL) Corrugated Profile Metal Roofing Sheets (100% Rainproof)\nTCT 0.50mm high-tensile 550 MPa AZ150 zinc-aluminum alloy coated profile sheet roofing. 100% all-weather rainproof & hail-resistant top covering including ridge caps, EPDM seal washers & self-drilling fasteners.",
                'hsn'         => '7210',
                'qty'         => $specs['metal_sheet_sft'],
                'unit'        => 'SQFT',
                'rate'        => $metalSheetRate,
                'amount'      => $metalSheetAmt,
                'specs'       => [
                    "Roof Surface Area: " . number_format($specs['metal_sheet_sft']) . " SFT (including pitch slope & side corrugation overlap)",
                    "Material: 0.50mm TCT Pre-Painted Galvalume (AZ150, 550 MPa Yield Strength)",
                    "Crown Ridge Cap: " . (int)$specs['ridge_cap_rft'] . " RFT 0.50mm matching color-coated ridge cap",
                    "Fasteners: Class 3 self-drilling hex fasteners with vulcanized EPDM washers"
                ],
                'gst_rate'    => 18.0,
            ];

            // 5C. Eaves Gutters & Downpipes (Rainwater Management)
            $gutterRate = 120.0; // Rs 120/RFT
            $gutterAmt  = round($specs['gutter_rft'] * $gutterRate, 2);
            $docItems[] = [
                'description' => "Heavy-Duty Eaves Gutters & Rainwater Downpipes System\n0.60mm Galvalume profile rainwater drainage gutters running on both eaves lengths with 110mm PVC storm downpipes for zero water spill.",
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
                    'description' => "0.50mm Pre-Painted Galvalume (PPGL) Corrugated Profile Metal Wall Cladding Sheets (All 4 Sides)\r\nTCT 0.50mm high-tensile 550 MPa AZ150 zinc-aluminum alloy color-coated corrugated profile side wall sheeting covering all 4 perimeter walls of the Indoor PEB Sports Complex from 4.5 FT masonry plinth wall up to full 35 FT clear eave height, including corner flashings, drip trims & weather fasteners.",
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
                    'description' => "Structural Secondary Wall Girt & Runner Grid Framework\r\nEngineered cold-formed Z-girts / tubular steel horizontal runners mounted @ 5.0 FT c/c across all 4 perimeter walls of the Indoor PEB Sports Complex for corrugated side wall sheet fastening.",
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
        } elseif ($specs['has_roof'] && !empty($specs['top_net_sft']) && $specs['top_net_sft'] > 0) {
            $topNetSpec = $specs['top_net_spec'];
            $topNetSft = $specs['top_net_sft'];
            $topNetUnitRate = (float)($topNetSpec['net_rate'] ?? ($specs['side_net_profile']['net_rate'] ?? 13.50));
            if ($selectedPkg === 'essential') $topNetUnitRate = max(6.0, round($topNetUnitRate * 0.88, 2));
            elseif ($selectedPkg === 'premium_pro') $topNetUnitRate = round($topNetUnitRate * 1.15, 2);
            $topNetAmt = round($topNetSft * $topNetUnitRate, 2);
            $topNetDesc = ($topNetSpec['name'] ?? 'Overhead Roof Netting') . ': ' . ($topNetSpec['twine'] ?? 'HDPE Net') . ' (' . ($topNetSpec['mesh'] ?? '40mm Mesh') . '), ' . ($topNetSpec['desc'] ?? '');

            $docItems[] = [
                'description' => "Overhead Roof / Top Netting System\n" . $topNetDesc,
                'hsn'         => '5608',
                'qty'         => $topNetSft,
                'unit'        => 'SQFT',
                'rate'        => $topNetUnitRate,
                'amount'      => $topNetAmt,
                'specs'       => [
                    "Top Enclosure Area: {$topNetSft} SFT",
                    "Specification: " . ($topNetSpec['twine'] ?? 'HDPE Net') . ", " . ($topNetSpec['mesh'] ?? 'Mesh'),
                    "Support: " . ($topNetSpec['support'] ?? 'Wire rope suspension'),
                ],
                'gst_rate'    => 5.0,
            ];
        }

        // 6. Surface, Cricket Pitch & Outfield Flooring
        $catalog = function_exists('getTurfCatalog') ? getTurfCatalog() : (function_exists('defaultTurfCatalog') ? defaultTurfCatalog() : []);
        $pitchCatalog = $catalog['cricket_pitch'] ?? [];
        $hasPitch     = !empty($specs['has_pitch']) && !empty($specs['pitch_material_sft']);

        if ($sportType === 'multisport_arena_complex_185x60') {
            // === ZONE A: CRICKET GROUND (100 FT x 60 FT = 6,000 SFT) ===
            // 1. Cricket Pitch Turf Strip (83 FT x 6.5 FT = 540 SFT Full Roll)
            $docItems[] = [
                'description' => "Zone A: 15mm Curled Cricket Match Pitch Turf (Full Roll 6.5 FT × 82 FT [2M × 25M] = 540 SFT)\nDedicated high-density curled wicket turf strip with 3 FT structure safety offset for natural ball bounce.",
                'hsn'         => '5703',
                'qty'         => 540,
                'unit'        => 'SQFT',
                'rate'        => 78.0,
                'amount'      => 42120.0,
                'specs'       => [
                    "Roll Specification: Full Roll (6.5 FT × 82 FT = 540 SFT [2.0M × 25.0M])",
                    "Installed Pitch Dimensions: 6.5 FT × 83 FT laid at center of 100x60 cricket zone",
                    "End Clearances: 3.0 FT clear setback from frame structures"
                ],
                'gst_rate'    => 5.0,
            ];
            $docItems[] = [
                'description' => "Zone A: Cricket Pitch Turf Specialized Installation & Seaming\nSub-base laser leveling, dual-edge high-tensile tape bonding with PU adhesive.",
                'hsn'         => '9954',
                'qty'         => 540,
                'unit'        => 'SQFT',
                'rate'        => 9.0,
                'amount'      => 4860.0,
                'specs'       => [
                    "Installation Scope: Precision center alignment of 540 SFT pitch",
                    "Bonding: Heavy-duty 350 GSM seam joint tape + all-weather PU adhesive"
                ],
                'gst_rate'    => 5.0,
            ];

            // 2. Outfield Turf Grass (5,460 SFT) - Dynamically using $flooringProfile
            $outfieldTurfRate = (float)($flooringProfile['rates'][$selectedPkg] ?? $flooringProfile['rates']['commercial'] ?? 45.0);
            $outfieldTurfAmt  = round(5460 * $outfieldTurfRate, 2);
            $hasInfill        = !empty($flooringProfile['has_infill']);
            $isLandscape      = ($flooringProfile['category'] ?? '') === 'landscape' || (strpos($flooringProfile['key'] ?? '', 'landscape') !== false);

            $outfieldTitle = "Zone A: Cricket Outfield " . ($flooringProfile['name'] ?? 'Sports Artificial Grass');
            if ($isLandscape || !$hasInfill) {
                $outfieldTitle .= " (Zero Infill)";
            }
            $outfieldDesc = $outfieldTitle . "\n" . ($flooringProfile['desc'] ?? 'High-density UV-resistant synthetic turf surface.');

            $docItems[] = [
                'description' => $outfieldDesc,
                'hsn'         => '5703',
                'qty'         => 5460,
                'unit'        => 'SQFT',
                'rate'        => $outfieldTurfRate,
                'amount'      => $outfieldTurfAmt,
                'specs'       => [
                    "Coverage Area: 5,460 SFT outfield (6,000 SFT zone minus 540 SFT pitch)",
                    "Specification: " . ($flooringProfile['pile_height'] ?? '') . " " . ($flooringProfile['name'] ?? ''),
                    "Infill Requirement: " . ($hasInfill ? "Infill Required (Silica Sand & SBR Rubber)" : "Zero Infill (No Silica Sand or Rubber required)")
                ],
                'gst_rate'    => 5.0,
            ];

            if ($hasInfill) {
                $docItems[] = [
                    'description' => "Zone A: Graded High-Purity Silica Sand (Turf Infill)\nKiln-dried round-grain silica sand ballast for blade uprightness and stability.",
                    'hsn'         => '2505',
                    'qty'         => 12050,
                    'unit'        => 'KG',
                    'rate'        => 6.50,
                    'amount'      => 78325.0,
                    'specs'       => ["Application: 2.207 kg/sqft across 5,460 SFT outfield"],
                    'gst_rate'    => 5.0,
                ];
                $docItems[] = [
                    'description' => "Zone A: SBR Resilient Rubber Granules (Turf Infill)\nNon-toxic dust-free vulcanized rubber granules for impact shock absorption and player joint protection.",
                    'hsn'         => '4004',
                    'qty'         => 3369,
                    'unit'        => 'KG',
                    'rate'        => 35.0,
                    'amount'      => 117915.0,
                    'specs'       => ["Application: 0.617 kg/sqft across 5,460 SFT outfield"],
                    'gst_rate'    => 5.0,
                ];
            }

            $docItems[] = [
                'description' => "Zone A: Joint Seaming Tape & Speb 7 PU Adhesive\nHeavy-duty geotextile joint tape and two-component polyurethane bonding compound.",
                'hsn'         => '3506',
                'qty'         => 1,
                'unit'        => 'LTR-LITRES',
                'rate'        => 19840.0,
                'amount'      => 19840.0,
                'specs'       => ["Joint Tape: 210 MTR + PU Adhesive: 55 KG"],
                'gst_rate'    => 18.0,
            ];

            if ($hasInfill) {
                $docItems[] = [
                    'description' => "Zone A: Turf Laying & Infill Mechanical Installation Charges\nPrecision mechanical brushing, silica sand injection, and rubber granule dressing across 5,460 SFT.",
                    'hsn'         => '9954',
                    'qty'         => 5460,
                    'unit'        => 'SQFT',
                    'rate'        => 12.0,
                    'amount'      => 65520.0,
                    'specs'       => ["Mechanical infill spreading & counter-rotational brushing"],
                    'gst_rate'    => 5.0,
                ];
            } else {
                $docItems[] = [
                    'description' => "Zone A: Turf Precision Laying, Seam Jointing & Bonding Installation (Zero Infill)\nPrecision manual roll-out, laser alignment, cold chemical seam bonding, and perimeter anchor fastening across 5,460 SFT (Zero infill application).",
                    'hsn'         => '9954',
                    'qty'         => 5460,
                    'unit'        => 'SQFT',
                    'rate'        => 7.0,
                    'amount'      => 38220.0,
                    'specs'       => ["Manual roll-out, precision seam jointing & perimeter bonding for Zero-Infill turf"],
                    'gst_rate'    => 5.0,
                ];
            }

            // === ZONE B: BADMINTON ARENA COMPLEX (45 FT x 60 FT = 2,700 SFT - TWO 45x30 COURTS) ===
            $badmintonFloorKey = $badmintonFloorType ?? ($_POST['badminton_floor_type'] ?? ($specs['badminton_floor_type'] ?? 'bwf_vinyl_4_5mm'));
            $badmintonCatalog  = getBadmintonFlooringProfiles();
            $badmintonProfile  = $badmintonCatalog[$badmintonFloorKey] ?? $badmintonCatalog['bwf_vinyl_4_5mm'];
            $badmintonRate     = (float)$badmintonProfile['rate'];
            $badmintonAmt      = round(2700 * $badmintonRate, 2);

            $docItems[] = [
                'description' => "Zone B: " . $badmintonProfile['name'] . "\n" . $badmintonProfile['desc'],
                'hsn'         => '3918',
                'qty'         => 2700,
                'unit'        => 'SQFT',
                'rate'        => $badmintonRate,
                'amount'      => $badmintonAmt,
                'specs'       => [
                    "Total Zone Area: 45 FT × 60 FT = 2,700 SFT",
                    "Courts Subdivision: 2 Full Regulation Badminton Courts (Each 45 FT Length × 30 FT Width = 1,350 SFT)",
                    "Specification: " . $badmintonProfile['name'],
                    "Installation: Precision laser alignment, heavy-duty seam bonding & federation regulation line markings"
                ],
                'gst_rate'    => 18.0,
            ];
            $docItems[] = [
                'description' => "Zone B: Badminton Surface Laser Leveling, Cold Vulcanized Bonding & Seaming\nSpecialized cold-welding chemical seams, adhesive perimeter anchoring, and laser plane calibration across 2,700 SFT.",
                'hsn'         => '9954',
                'qty'         => 2700,
                'unit'        => 'SQFT',
                'rate'        => 10.0,
                'amount'      => 27000.0,
                'specs'       => ["Precision installation of 2,700 SFT dual-court badminton sports surface"],
                'gst_rate'    => 18.0,
            ];
            $docItems[] = [
                'description' => "Zone B: Heavy-Duty Cast-Iron Movable Tournament Badminton Posts & BWF Nets (2 Sets)\nWeighted 120 KG cast-iron base movable posts with scratch-proof wheels, grooved brass pulley, ratchet tensioners, and tournament-grade nylon nets with heavy white headband.",
                'hsn'         => '9506',
                'qty'         => 2,
                'unit'        => 'SET',
                'rate'        => 14000.0,
                'amount'      => 28000.0,
                'specs'       => [
                    "Quantity: 2 Sets (1 Set for Court 1 [45x30] + 1 Set for Court 2 [45x30])",
                    "Posts: 120 KG weighted base with transport wheels, regulation 1.55M height",
                    "Nets: Official BWF regulation nylon mesh nets with steel tension cable"
                ],
                'gst_rate'    => 18.0,
            ];
            $docItems[] = [
                'description' => "Zone B: Dual Badminton Court Official Tournament Line Markings\nOfficial BWF line markings (40mm width) applied in durable two-component polyurethane paint for Singles & Doubles play on both 45×30 FT courts.",
                'hsn'         => '9506',
                'qty'         => 2,
                'unit'        => 'SET',
                'rate'        => 6000.0,
                'amount'      => 12000.0,
                'specs'       => [
                    "Court 1 (45x30 FT): Full BWF Singles & Doubles Court Markings",
                    "Court 2 (45x30 FT): Full BWF Singles & Doubles Court Markings"
                ],
                'gst_rate'    => 18.0,
            ];

// === ZONE C: AMENITIES SUITE & RECEPTION LOUNGE (40 FT × 60 FT = 2,400 SFT) ===
            $docItems[] = [
                'description' => "Zone C: Commercial Restrooms & Player Washroom Facility (Gents & Ladies)
Fully equipped sanitary block with ceramic sanitaryware, concealed plumbing, CPVC water supply lines, floor & wall tiling, and heavy-duty PVC panel doors.",
                'hsn'         => '9954',
                'qty'         => 1,
                'unit'        => 'LOT',
                'rate'        => 195000.0,
                'amount'      => 195000.0,
                'specs'       => [
                    "Facility Scope: Twin Restroom Units (Gents & Ladies) inside Zone C Amenities Area",
                    "Sanitary Fixtures: 2 Wall-hung EWCs, 2 wash basins with chrome pillar taps & dual-flush cisterns",
                    "Plumbing & Piping: CPVC water supply lines, PVC drainage stacks & connection to external septic/sewer point",
                    "Tiling & Partitions: Anti-skid ceramic floor tiles and 7 FT glazed wall tiles with waterproof panel doors"
                ],
                'gst_rate'    => 18.0,
            ];
            $docItems[] = [
                'description' => "Zone C: Executive Management Office Suite & Reception Lobby
Managerial administration cabin and visitor reception desk with toughened glass partitions, executive workstation desk, visitor seating, and electrical LED lighting fittings.",
                'hsn'         => '9954',
                'qty'         => 1,
                'unit'        => 'LOT',
                'rate'        => 175000.0,
                'amount'      => 175000.0,
                'specs'       => [
                    "Office Footprint: 20 FT × 15 FT framed executive office & reception counter inside Zone C",
                    "Glazing & Partition: 10mm toughened glass front partition with patch-fitting entrance door",
                    "Furnishings: Managerial desk, visitor chairs, reception greeting counter & equipment billing counter",
                    "Electrical & Lighting: Modular switches, AC power points, LED panel lighting & UPS wiring provision"
                ],
                'gst_rate'    => 18.0,
            ];
            $docItems[] = [
                'description' => "Zone C: Player Lounge, Refreshment Cafeteria & Kit Storage Locker Room
Furnished player lounge waiting zone, refreshment pantry counter, and 24-compartment lockable kit storage lockers for club players.",
                'hsn'         => '9954',
                'qty'         => 1,
                'unit'        => 'LOT',
                'rate'        => 145000.0,
                'amount'      => 145000.0,
                'specs'       => [
                    "Lounge Scope: Dedicated player waiting area, refreshment cafeteria counter & locker zone",
                    "Storage: 24-compartment powder-coated steel player kit lockers with master keys",
                    "Cafeteria Setup: Stainless steel beverage counter, sink, waste disposal and seating benches",
                    "Finishing: Washable emulsion paint finish, overhead LED lighting and display notice board"
                ],
                'gst_rate'    => 18.0,
            ];
        } elseif ($sportType === 'multisport_complex_105x170') {
            // === ZONE 2: OUTDOOR BOX CRICKET GROUND (90 FT × 50 FT = 4,500 SFT) ===
            // 1. Cricket Pitch Turf (Full Roll 540 SFT)
            $docItems[] = [
                'description' => "Zone 2 (Cricket): 15mm Curled Cricket Match Pitch Turf (Full Roll 6.5 FT × 82 FT [2M × 25M] = 540 SFT)\r\nDedicated high-density curled wicket turf strip with 3 FT structure safety offset for natural ball bounce.",
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
                'description' => "Zone 2 (Cricket): Cricket Pitch Turf Specialized Installation & Laser Seaming\r\nSub-base laser leveling, dual-edge high-tensile tape bonding with PU adhesive.",
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
                'description' => "Zone 2 (Cricket): Outfield Sports Flooring / Artificial Grass (40mm Landscape Sports Grass)\r\nHigh-density UV-resistant synthetic turf surface providing natural cushioning and traction across the 90 FT × 50 FT Outdoor Box Cricket Arena.",
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
                'description' => "Zone 2 (Cricket): Sports Grass Precision Laying, Seaming & Bonding\r\nPrecision roll-out, geotextile seam jointing, and perimeter adhesive bonding across 4,071 SFT.",
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
                'description' => "Zone 3 (Indoor): 4 BWF-Grade 4.5mm Multi-Layer Synthetic Vinyl Sports Surface (Four Regulation Badminton Courts)\r\nHigh-performance embossed anti-slip wear layer with high-density PVC foam backing for maximum tournament shock absorption, resilience, and traction across 4 Full Regulation Badminton Courts (Each 44 FT × 20 FT play court + safety run-offs = 1,147.5 SFT/court).",
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
                'description' => "Zone 3 (Indoor): Badminton Synthetic Vinyl Surface Precision Installation, Chemical Cold-Welding Seams & Leveling\r\nSpecialized chemical cold-welding joints, all-weather adhesive bonding, and perimeter anchor fastening across 4,590 SFT for 4 Badminton Courts.",
                'hsn'         => '9954',
                'qty'         => 4590,
                'unit'        => 'SQFT',
                'rate'        => 10.0,
                'amount'      => 45900.0,
                'specs'       => ["Precision installation of 4,590 SFT quad-court badminton synthetic sports surface with chemical cold-welded seams"],
                'gst_rate'    => 18.0,
            ];
            $docItems[] = [
                'description' => "Zone 3 (Indoor): Heavy-Duty Cast-Iron Movable Tournament Badminton Posts & BWF Regulation Nets (4 Sets)\r\n120 KG weighted cast-iron base movable posts with non-marking transport wheels, grooved brass pulley, ratchet tensioners, and official tournament-grade nylon nets with heavy white PVC headband.",
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
                'description' => "Zone 3 (Indoor): BWF Official Tournament Court Line Markings (4 Badminton Courts)\r\nOfficial BWF line markings (40mm width) applied in durable two-component polyurethane wear-resistant paint for Singles & Doubles play on all 4 Courts.",
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
                'description' => "Zone 3 (Indoor): 8-Layer Heavy-Cushion Acrylic Synthetic Sports Flooring System (Two Regulation Pickleball Courts)\r\nMulti-layer 100% acrylic latex cushion surfacing system featuring acrylic resurfacer, flexible rubber micro-granule cushion base coats, textured color wear coats, and anti-glare finish for 2 Regulation Pickleball Courts (44 FT × 22 FT each).",
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
                'description' => "Zone 3 (Indoor): Heavy-Duty Tournament Pickleball Posts, Internal Ratchet Tensioners & Regulation Nets (2 Sets)\r\n3.0-inch heavy-gauge round steel posts with internal brass gear ratchet, post ground sleeves, and official 36-inch height heavy nylon pickleball tournament net with center strap.",
                'hsn'         => '9506',
                'qty'         => 2,
                'unit'        => 'SET',
                'rate'        => 15000.0,
                'amount'      => 30000.0,
                'specs'       => [
                    "Quantity: 2 Sets (1 Set per Regulation Pickleball Court)",
                    "Posts: 3.0-inch powder-coated steel posts with internal worm-gear winch",
                    "Nets: Heavy-duty 3.0mm braided polyethylene net with vinyl headband and fiberglass dowels"
                ],
                'gst_rate'    => 18.0,
            ];
            $docItems[] = [
                'description' => "Zone 3 (Indoor): Official Regulation Tournament Pickleball Line Markings (2 Courts)\r\nTwo-tone regulation court markings including 7 FT Non-Volley Kitchen Zone, centerline, baselines, and sidelines in high-visibility 100% acrylic textured paint.",
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
        } elseif ($includeTurf && $flooringProfile['has_turf']) {
            $courtTotalSft = (float)$specs['court_sft'];
            $pitchMatSft   = $hasPitch ? (float)$specs['pitch_material_sft'] : 0.0;
            $pitchInstArea = $hasPitch ? (float)$specs['pitch_area_sft'] : 0.0;
            $pitchLengthFt = $hasPitch ? (float)$specs['pitch_length_ft'] : 0.0;
            $outfieldSft   = $hasPitch ? max(0.0, round($courtTotalSft - $pitchInstArea, 2)) : $courtTotalSft;

            // 6A. Center Cricket Pitch Turf Strip (if cricket/box cricket pitch enabled)
            if ($hasPitch && $pitchMatSft > 0) {
                $pitchUnitRate    = (float)($pitchCatalog['rate_per_sft'] ?? 78.0);
                $pitchInstallRate = (float)($pitchCatalog['install_rate_per_sft'] ?? 9.0);
                $pitchRollName    = ($specs['pitch_roll_type'] === 'half_roll')
                    ? ($pitchCatalog['half_roll_name'] ?? "15mm Curled Multisport Pitch Turf (Half Roll 6.5' × 41.5' [2M × 12.5M])")
                    : ($pitchCatalog['full_roll_name'] ?? "15mm Curled Multisport Pitch Turf (Full Roll 6.5' × 82' [2M × 25M])");

                // 1. Pitch Material (Billed per standard roll area: 540 SFT or 270 SFT @ ₹78/SFT)
                $docItems[] = [
                    'description' => "{$pitchRollName}\nDedicated high-density curled wicket turf strip with 3 FT structure safety offset.",
                    'hsn'         => '5703',
                    'qty'         => $pitchMatSft,
                    'unit'        => 'SQFT',
                    'rate'        => $pitchUnitRate,
                    'amount'      => round($pitchMatSft * $pitchUnitRate, 2),
                    'specs'       => [
                        "Roll Specification: " . ($specs['pitch_roll_type'] === 'half_roll' ? "Half Roll (6.5 FT × 41.5 FT = 270 SFT [2.0M × 12.5M = 25 SQM])" : "Full Roll (6.5 FT × 82 FT = 540 SFT [2.0M × 25.0M = 50 SQM])"),
                        "Installed Pitch Dimensions: 6.5 FT × {$pitchLengthFt} FT = {$pitchInstArea} SFT laid at center",
                        "End Clearances: 3.0 FT clear setback from front & back frame structures",
                    ],
                    'gst_rate'    => 5.0,
                ];

                // 2. Pitch Installation (Billed strictly on actual installed area @ ₹8-₹10, std ₹9/SFT)
                $docItems[] = [
                    'description' => "Cricket Pitch Turf Specialized Installation & Seaming\nSub-base laser leveling, dual-edge high-tensile tape bonding with PU adhesive.",
                    'hsn'         => '9954',
                    'qty'         => $pitchInstArea,
                    'unit'        => 'SQFT',
                    'rate'        => $pitchInstallRate,
                    'amount'      => round($pitchInstArea * $pitchInstallRate, 2),
                    'specs'       => [
                        "Installation Scope: Precision center alignment of {$pitchInstArea} SFT ({$pitchLengthFt} FT length)",
                        "Bonding: Heavy-duty 350 GSM seam joint tape + all-weather PU adhesive",
                    ],
                    'gst_rate'    => 5.0,
                ];
            }

            // 6B. Outfield Turf Grass Material
            if ($outfieldSft > 0) {
                $outfieldRate = (float)($flooringProfile['rates'][$selectedPkg] ?? $flooringProfile['rates']['commercial'] ?? 35.0);
                $docItems[] = [
                    'description' => "Outfield Sports Flooring / Surface (" . $flooringProfile['short_name'] . ")\n" . $flooringProfile['desc'],
                    'hsn'         => (strpos(strtolower($flooringProfile['category'] ?? ''), 'turf') !== false || strpos(strtolower($flooringProfile['name'] ?? ''), 'turf') !== false || strpos(strtolower($flooringProfile['name'] ?? ''), 'grass') !== false ? '5703' : '3918'),
                    'qty'         => $outfieldSft,
                    'unit'        => 'SQFT',
                    'rate'        => $outfieldRate,
                    'amount'      => round($outfieldSft * $outfieldRate, 2),
                    'specs'       => [
                        "Coverage Area: {$outfieldSft} SFT" . ($hasPitch ? " (Total court {$courtTotalSft} SFT minus {$pitchInstArea} SFT pitch)" : ""),
                        "Material Specification: " . $flooringProfile['name'],
                        "Pile Height: " . ($flooringProfile['pile_height'] ?? 'Standard'),
                    ],
                    'gst_rate'    => 5.0,
                ];

                // 6C. Outfield Installation Charges
                $subgroup = $flooringProfile['turf_subgroup'] ?? '';
                if ($subgroup === 'landscape') {
                    // Sliding scale: <1500 SFT => ₹8, 1501-3000 SFT => ₹6.5, >3000 SFT => ₹5
                    $instTiers = $catalog['landscape_grass']['install_tiers'] ?? [
                        ['min_sft' => 0, 'max_sft' => 1500, 'rate' => 8.0],
                        ['min_sft' => 1501, 'max_sft' => 3000, 'rate' => 6.5],
                        ['min_sft' => 3001, 'max_sft' => 999999, 'rate' => 5.0],
                    ];
                    $outfieldInstallRate = 8.0;
                    foreach ($instTiers as $t) {
                        if ($courtTotalSft >= $t['min_sft'] && $courtTotalSft <= $t['max_sft']) {
                            $outfieldInstallRate = (float)$t['rate'];
                            break;
                        }
                    }
                    $docItems[] = [
                        'description' => "Landscape Sports Grass Installation & Seaming\nSub-base fine dressing, geotextile joint seaming, and adhesive fixation.",
                        'hsn'         => '9954',
                        'qty'         => $outfieldSft,
                        'unit'        => 'SQFT',
                        'rate'        => $outfieldInstallRate,
                        'amount'      => round($outfieldSft * $outfieldInstallRate, 2),
                        'specs'       => [
                            "Laying Area: {$outfieldSft} SFT (Sliding rate based on total court size)",
                            "Includes seaming tape and high-bonding adhesive application",
                        ],
                        'gst_rate'    => 5.0,
                    ];
                } elseif ($subgroup === 'sports_non_infill') {
                    $sportsInstRate = (float)($catalog['sports_grass']['30mm_non_infill']['install_rate'] ?? 9.0);
                    $docItems[] = [
                        'description' => "Sports Turf Laying & Precision Installation Charges\nProfessional roll unrolling, alignment, perimeter stretching, and seam tape bonding.",
                        'hsn'         => '9954',
                        'qty'         => $outfieldSft,
                        'unit'        => 'SQFT',
                        'rate'        => $sportsInstRate,
                        'amount'      => round($outfieldSft * $sportsInstRate, 2),
                        'specs'       => [
                            "Installed Area: {$outfieldSft} SFT high-traction sports surface",
                            "Specialized joint seaming and perimeter clamping",
                        ],
                        'gst_rate'    => 5.0,
                    ];
                } elseif ($flooringProfile['has_infill']) {
                    // 50mm Infill Turf: Detailed breakdown based on EST-202609-031, 032, 033
                    $infillStd = $catalog['infill_standards'] ?? [];

                    // 1. Graded Silica Sand (2.207 KG/SFT @ ₹6.50/KG)
                    $sandRate = (float)($infillStd['silica_sand']['rate_per_kg'] ?? 6.50);
                    $sandQtyKg = round($outfieldSft * (float)($infillStd['silica_sand']['kg_per_sqft'] ?? 2.207));
                    $docItems[] = [
                        'description' => "Graded High-Purity Silica Sand (Turf Infill)\nKiln-dried round-grain silica sand for ballast weight and upright blade support.",
                        'hsn'         => '2505',
                        'qty'         => $sandQtyKg,
                        'unit'        => 'KG',
                        'rate'        => $sandRate,
                        'amount'      => round($sandQtyKg * $sandRate, 2),
                        'specs'       => ["Application: ~23.75 kg/m² (2.207 kg/sqft) across {$outfieldSft} SFT outfield"],
                        'gst_rate'    => 5.0,
                    ];

                    // 2. SBR Resilient Rubber Granules (0.617 KG/SFT @ ₹35.00/KG)
                    $rubberRate = (float)($infillStd['rubber_granules']['rate_per_kg'] ?? 35.00);
                    $rubberQtyKg = round($outfieldSft * (float)($infillStd['rubber_granules']['kg_per_sqft'] ?? 0.617));
                    $docItems[] = [
                        'description' => "SBR Resilient Rubber Granules (Turf Infill)\nNon-toxic dust-free vulcanized rubber granules for impact shock absorption.",
                        'hsn'         => '4004',
                        'qty'         => $rubberQtyKg,
                        'unit'        => 'KGS-KILOGRAMS',
                        'rate'        => $rubberRate,
                        'amount'      => round($rubberQtyKg * $rubberRate, 2),
                        'specs'       => ["Application: ~6.64 kg/m² (0.617 kg/sqft) across {$outfieldSft} SFT outfield"],
                        'gst_rate'    => 18.0,
                    ];

                    // 3. White Joint Seaming Tape (0.03858 MTR/SFT @ ₹45.00/MTR)
                    $tapeRate = (float)($infillStd['joint_tape']['rate_per_mtr'] ?? 45.00);
                    $tapeQtyMtr = max(10, round($outfieldSft * 0.03858));
                    $docItems[] = [
                        'description' => "White High-Tensile Joint Seaming Tape\nHeavy-duty geotextile backing tape for roll jointing and seam reinforcement.",
                        'hsn'         => '5911',
                        'qty'         => $tapeQtyMtr,
                        'unit'        => 'MTR',
                        'rate'        => $tapeRate,
                        'amount'      => round($tapeQtyMtr * $tapeRate, 2),
                        'specs'       => ["Length: {$tapeQtyMtr} Meters for seamless thermal roll bonding"],
                        'gst_rate'    => 5.0,
                    ];

                    // 4. Speb 7 PU Adhesive (0.01003 KG/SFT @ ₹330.00/KG)
                    $glueRate = (float)($infillStd['pu_glue']['rate_per_kg'] ?? 330.00);
                    $glueQtyKg = max(5, round($outfieldSft * 0.01003));
                    $docItems[] = [
                        'description' => "Speb 7 Two-Component Polyurethane Adhesive\nHigh-bond weather-resistant PU glue for artificial turf roll joints.",
                        'hsn'         => '3506',
                        'qty'         => $glueQtyKg,
                        'unit'        => 'LTR-LITRES',
                        'rate'        => $glueRate,
                        'amount'      => round($glueQtyKg * $glueRate, 2),
                        'specs'       => ["Quantity: {$glueQtyKg} KG two-component polyurethane bonding compound"],
                        'gst_rate'    => 18.0,
                    ];

                    // 5. Infill Installation Charges (Standard ₹12.00/SFT)
                    $infillInstallRate = 12.0;
                    $docItems[] = [
                        'description' => "Turf Laying & Infill Mechanical Installation Charges\nPrecision mechanical brushing, silica sand injection, and rubber granule dressing.",
                        'hsn'         => '9954',
                        'qty'         => $outfieldSft,
                        'unit'        => 'SQFT',
                        'rate'        => $infillInstallRate,
                        'amount'      => round($outfieldSft * $infillInstallRate, 2),
                        'specs'       => ["Scope: Mechanical infill brushing & spreading across {$outfieldSft} SFT"],
                        'gst_rate'    => 5.0,
                    ];
                } else {
                    // General sports turf installation fallback
                    $docItems[] = [
                        'description' => "Turf Laying & Installation Charges\nSub-base fine dressing, seam joint tape bonding, and precision perimeter alignment.",
                        'hsn'         => '9954',
                        'qty'         => $outfieldSft,
                        'unit'        => 'SQFT',
                        'rate'        => 8.0,
                        'amount'      => round($outfieldSft * 8.0, 2),
                        'specs'       => ["Area: {$outfieldSft} SFT"],
                        'gst_rate'    => 5.0,
                    ];
                }
            }
        }

        // 8. Lighting
        if ($includeLighting) {
        if ($isSmallCourt) {
            $numLights = ($selectedPkg === 'essential') ? 2 : (($selectedPkg === 'premium_pro') ? 6 : 4);
            $lightFixtureRate = ($selectedPkg === 'essential') ? 4000 : (($selectedPkg === 'premium_pro') ? 6500 : 4500);
        } else {
            $numLights = $specs['lights_count'][$selectedPkg] ?? 8;
            $lightFixtureRate = ($selectedPkg === 'essential') ? 7500 : (($selectedPkg === 'premium_pro') ? 13500 : 9500);
        }
        if ($sportType === 'multisport_complex_105x170') {
            $numLights = 16;
            $lightAmt = round($numLights * $lightFixtureRate, 2);
            $docItems[] = [
                'description' => "Commercial Sports Arena LED Lighting System (16 High-Output Fixtures)\r\nDual-Zone Illumination: (1) 8 Nos 200W IP66 asymmetric stadium floodlights mounted on perimeter masts for 250+ lux outfield cricket coverage; (2) 8 Nos 150W IP65 low-glare wide-beam optical high-bay LEDs for 300+ lux tournament indoor badminton & pickleball court illumination.",
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
        if ($sportType === 'multisport_complex_105x170') {
            $numLights = 16;
            $lightAmt = round($numLights * $lightFixtureRate, 2);
            $docItems[] = [
                'description' => "Commercial Sports Arena LED Lighting System (16 High-Output Fixtures)\r\nDual-Zone Illumination: (1) 8 Nos 200W IP66 asymmetric stadium floodlights mounted on perimeter masts for 250+ lux outfield cricket coverage; (2) 8 Nos 150W IP65 low-glare wide-beam optical high-bay LEDs for 300+ lux tournament indoor badminton & pickleball court illumination.",
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
            'description' => "High-Lumen Sports LED Floodlight Fixtures\n" . ($isSmallCourt ? "{$numLights} Nos High-Output Sports LED Floodlights (IP66, 140 lm/W) mounted for practice lane lighting." : $pkg['items']['lighting']['desc']),
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
        }
        }

        // 9. Electrical Work
        if ($isSmallCourt) {
            $elecAmt = ($selectedPkg === 'essential') ? 7000 : (($selectedPkg === 'premium_pro') ? 15000 : 10000);
        } else {
            $elecAmt = ($selectedPkg === 'essential') ? 25000 : (($selectedPkg === 'premium_pro') ? 50000 : 35000);
        }
        $docItems[] = [
            'description' => "Electrical Cabling, Control Panel & Earthing\n" . $pkg['items']['electrical']['desc'],
            'hsn'         => '8537',
            'qty'         => 1,
            'unit'        => 'LOT',
            'rate'        => $elecAmt,
            'amount'      => $elecAmt,
            'specs'       => ["Complete turnkey electrical wiring, 4.0 sq.mm armoured copper cable and circuit protection"],
            'gst_rate'    => 18.0,
        ];

        }

        // 10. Entry Gate & Accessories
        $gateCount = (int)$gateCount;
        if ($gateCount > 0 && $includeGates) {
            if ($sportType === 'multisport_complex_105x170') {
                $gateCount = 4;
                $docItems[] = [
                    'description' => "Heavy-Duty Main Entrance & Dedicated Complex Access Doors & Hardware (4 Purpose-Built Gates)\r\nComplete campus access package: (1) 12'×8' Heavy-Duty Main Front Sliding/Swing Gate at X = 18 FT (entering the enclosed sports complex & 15 FT pathway from the outside vehicle parking lot); (2) 6'×8' Indoor Complex Main Double Door at X = 68 FT (entering Reception Lobby, Office & Sports Hall from 15 FT pathway); (3) 4'×7' Box Cricket Player Door (entering cricket ground from pathway); (4) 4'×7' Emergency Egress Door on rear perimeter wall. All doors equipped with industrial ball-bearing hinges, mortise cylinder deadbolts & protective padding.",
                    'hsn'         => '7308',
                    'qty'         => 4,
                    'unit'        => 'NOS',
                    'rate'        => 20750.0,
                    'amount'      => 83000.0,
                    'specs'       => [
                        "Main Gate: 12 FT × 8 FT Main Entrance Gate located at X = 18 FT front wall of metal structure, providing grand entry from outside open-air parking into 15 FT pathway",
                        "Indoor Complex Door: 6 FT × 8 FT Commercial Double Door located at X = 68 FT front facade of PEB Indoor Building, providing direct entry into Reception Lobby, Office & Sports Hall",
                        "Cricket Player Door: 4 FT × 7 FT Player Access Door between 15 FT pathway and Outdoor Box Cricket Arena",
                        "Emergency Exit: 4 FT × 7 FT Heavy Steel Fire/Emergency Exit Door on rear boundary wall",
                        "Hardware: Industrial cylinder locks, heavy-duty ball-bearing hinges, drop bolts & vinyl-wrapped foam safety padding"
                    ],
                    'gst_rate'    => 18.0,
                ];
            } else {
            $gateRatePerUnit = ($selectedPkg === 'essential') ? 14000 : (($selectedPkg === 'premium_pro') ? 22000 : 16500);
            $gateAmt = round($gateCount * $gateRatePerUnit, 2);
            $gateDescText = ($gateCount == 1)
                ? "1 No. Standard Player Access Door (4ft × 7ft) with cylinder lock, heavy-duty hinges, safety foam padding & weather-proof finish."
                : (($gateCount == 2)
                    ? "2 Nos. Dual Player Access Doors (4ft × 7ft each, opposing sidelines) with cylinder locks, heavy-duty hinges, safety foam padding & weather-proof finish."
                    : (($gateCount == 3)
                        ? "3 Nos. Access Doors (2 Nos. 4'×7' Player Doors + 1 No. 6'×7' Equipment / Maintenance Gate) with cylinder locks, heavy-duty hinges, crash pads & weather-proof finish."
                        : "$gateCount Nos. Multi-Point Entry & Maintenance Gates (4'×7' & 6'×7') with cylinder locks, heavy-duty hinges & protective padding."));

            $docItems[] = [
                'description' => "Player Access Gate & Hardware (" . $gateCount . " " . ($gateCount > 1 ? "Gates" : "Gate") . ")\n" . $gateDescText,
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
            }
        }

        // 11. Equipment & Markings
        $isCricket = !empty($specs['is_cricket']);
        $pitchLen = (float)($specs['pitch_length_ft'] ?? 0);
        if ($isSmallCourt) {
            $equipAmt   = ($selectedPkg === 'essential') ? 4000  : (($selectedPkg === 'premium_pro') ? 8000 : 6000);
            $equipSpecs = [
                "Sport: " . $sportProfile['name'],
                "Pitch Markings: {$pitchLen} FT Official Batting & Bowling Creases",
                "Training Kit: Target bowling cones & spring-loaded stumps"
            ];
        } elseif ($isTrainingNet) {
            $equipBase = ($selectedPkg === 'essential') ? 11000 : (($selectedPkg === 'premium_pro') ? 20000 : 15000);
            $equipPitchShare = ($selectedPkg === 'essential') ? 4000 : (($selectedPkg === 'premium_pro') ? 7000 : 5500);
            $equipAmt = round($equipBase + ($equipPitchShare * ($pitchLen / 66.0)), 2);
            $equipSpecs = [
                "Sport: " . $sportProfile['name'],
                "Pitch Markings: {$pitchLen} FT Official Batting & Bowling Creases with Return Crease inlays",
                "Training Kit: Spring-return batting stumps with heavy base, target bowling cones, safety backstop target sheet & pitch boundary borders",
            ];
        } elseif ($isCricket && $pitchLen > 0) {
            $equipBase = ($selectedPkg === 'essential') ? 14000 : (($selectedPkg === 'premium_pro') ? 25000 : 19000);
            $equipPitchShare = ($selectedPkg === 'essential') ? 5000 : (($selectedPkg === 'premium_pro') ? 9000 : 7000);
            $equipAmt = round($equipBase + ($equipPitchShare * ($pitchLen / 66.0)), 2);
            $equipSpecs = [
                "Sport: " . $sportProfile['name'],
                "Pitch Markings: {$pitchLen} FT Official Crease Markings (Bowling, Popping & Return Creases at both ends)",
                "Accessories: Regulation spring-loaded match wickets, bails, boundary markers & crease inlays",
            ];
        } else {
            $equipAmt = ($selectedPkg === 'essential') ? 18000 : (($selectedPkg === 'premium_pro') ? 32000 : 24000);
            $equipSpecs = [
                "Sport: " . $sportProfile['name'],
                "Accessories: " . $sportProfile['equipment_desc']
            ];
        }

        if ($sportType === 'multisport_complex_105x170') {
            $docItems[] = [
                'description' => "Zone 2 (Cricket): Regulation Match Wickets, Crease Markings & Column Safety Padding\r\n66 FT official pitch crease markings (Bowling, Popping & Return creases at both ends), regulation spring-loaded match wickets, bails, boundary indicators, and high-density vinyl-wrapped column impact safety padding.",
                'hsn'         => '9506',
                'qty'         => 1,
                'unit'        => 'SET',
                'rate'        => 26000.0,
                'amount'      => 26000.0,
                'specs'       => [
                    "Pitch Markings: Official ICC/MCC 66 FT crease dimensions",
                    "Equipment: 2 Sets spring-return wooden match wickets with bails",
                    "Safety Padding: 2-inch thick high-density PU foam pads wrapped in heavy PVC tarpaulin for playing zone columns"
                ],
                'gst_rate'    => 18.0,
            ];

            // 11B. Zone 3 (Indoor): Dedicated Amenities Suite (Office, Lobby, Restrooms & Showers)
            // Item 1: Internal Brickwork Partitions & Both-Sides Plastering
            $docItems[] = [
                'description' => "Zone 3 (Indoor): Amenities Suite Civil Work — Internal AAC Block / Brickwork Partitions & Plastering\r\nConstruction of 150mm/100mm internal masonry partition walls (up to 10 FT ceiling height) dividing the 2,068 SFT Amenities Suite into: (1) Main Reception Lobby & Visitor Foyer, (2) Administrative Office & Billing Cabin, (3) Gents Changing & Shower Room, (4) Gents Restroom / Water Closets, (5) Ladies Changing & Shower Room, and (6) Ladies Restroom. Includes 1:4 cement mortar plastering with smooth sponge float finish on both sides, curing, bond beams, and primer coating.",
                'hsn'         => '9954',
                'qty'         => 2068,
                'unit'        => 'SQFT',
                'rate'        => 110.0,
                'amount'      => 227480.0,
                'specs'       => [
                    "Floor Footprint: 2,068 SFT (44 FT × 47 FT dedicated North-West amenities block)",
                    "Partitions: 6-inch solid AAC blockwork / masonry with reinforced concrete lintels & bond beams",
                    "Plastering: 15mm cement plaster (1:4 mix) with waterproofing compound on both sides",
                    "Curing & Finish: 14-day water cured and finished with anti-fungal primer coat"
                ],
                'gst_rate'    => 18.0,
            ];

            // Item 2: Commercial Sanitaryware, Plumbing, CP Brass Fittings & Drainage System
            $docItems[] = [
                'description' => "Zone 3 (Indoor): Commercial Sanitaryware, Plumbing, CP Brass Fittings & Drainage System (Gents & Ladies Restrooms & Showers)\r\nComplete commercial plumbing and sanitaryware installation for Gents and Ladies facilities: 4 Nos Wall-Hung / Floor-Mounted EWC Water Closets with dual-flush concealed/slim cisterns, 2 Nos Sensor/Spread Urinal bowls with privacy marble partitions, 4 Nos Countertop / Wall-Hung Ceramic Washbasins with CP brass pillar taps, 4 Nos Overhead Rain Showers with brass mixer diverters & bib taps, heavy-duty CPVC hot/cold water supply manifold, SWR sewage & waste lines with anti-cockroach floor traps, commercial high-flow exhaust ventilation fans, LED vanity mirrors, soap dispensers, and connection to campus perimeter drainage.",
                'hsn'         => '6910',
                'qty'         => 1,
                'unit'        => 'LOT',
                'rate'        => 185000.0,
                'amount'      => 185000.0,
                'specs'       => [
                    "Sanitaryware: 4 EWCs with soft-close seats & dual-flush cisterns (2 Gents + 2 Ladies)",
                    "Urinals & Basins: 2 Urinals with marble dividers + 4 Ceramic basins with brass pillar taps",
                    "Shower Suites: 4 Shower cubicles with overhead rain showerheads & concealed diverter valves",
                    "Piping & Drainage: Schedule 40 CPVC water lines + heavy SWR drainage with gully traps"
                ],
                'gst_rate'    => 18.0,
            ];

            // Item 3: Heavy-Duty Anti-Skid Floor Tiling & Glazed Wall Dado Tiling
            $docItems[] = [
                'description' => "Zone 3 (Indoor): Restrooms & Changing Suites — Heavy-Duty Anti-Skid Vitrified Floor Tiling & Glazed Ceramic Wall Dado\r\nSupply and precision laying of 300×300mm matte-finish anti-skid vitrified ceramic floor tiles with epoxy grouting in all wet areas (Gents & Ladies washrooms and shower cubicles), and 300×450mm digital glazed ceramic wall dado tiles up to 7 FT height with waterproof polymer adhesive, border trims, and corner sealant.",
                'hsn'         => '6907',
                'qty'         => 1250,
                'unit'        => 'SQFT',
                'rate'        => 85.0,
                'amount'      => 106250.0,
                'specs'       => [
                    "Floor Tiling: 300×300mm heavy-duty anti-skid vitrified tiles in all restrooms & shower stalls",
                    "Wall Dado: 300×450mm premium glazed ceramic wall tiles laid up to 7.0 FT height",
                    "Joints & Adhesive: Polymer-modified tile adhesive with anti-fungal epoxy grout filling"
                ],
                'gst_rate'    => 18.0,
            ];

            // Item 4: Office, Reception Lobby & Washroom Interior Doors, Partitions & Electrical Fitout
            $docItems[] = [
                'description' => "Zone 3 (Indoor): Amenities Suite Interior Fitout — Glazed Cabin Partitions, Waterproof Doors & Interior Electricals\r\nSupply and turnkey installation of: (1) Heavy-duty architectural anodized aluminum glazed partitions with 8mm toughened safety glass for Admin Office & Reception Lobby; (2) 6 Nos Solid WPC/FRP waterproof flush doors with mortise cylinder handles and stainless steel hinges for restrooms and changing cubicles; (3) Premium vitrified tile flooring for Lobby & Office (820 SFT); (4) Complete interior electricals including recessed LED panel lighting, ceiling fans, AC provision points, data points, and modular switchgear.",
                'hsn'         => '9954',
                'qty'         => 1,
                'unit'        => 'LOT',
                'rate'        => 165000.0,
                'amount'      => 165000.0,
                'specs'       => [
                    "Office Partitions: Architectural anodized aluminum frame with 8mm toughened safety glass",
                    "Doors: 6 Nos WPC/FRP 100% waterproof doors with mortise cylinder locks & handles",
                    "Lobby Flooring: 600×600mm double-charged vitrified floor tiles for Reception Lobby & Office",
                    "Electricals: Recessed 36W LED panel lights, ceiling fans, AC wiring conduits & modular switches"
                ],
                'gst_rate'    => 18.0,
            ];
        } elseif ($sportType === 'multisport_4in1_100x60') {
            if (is_string($equipment)) {
                try { $equipment = json_decode($equipment, true) ?: []; } catch (\Exception $e) { $equipment = []; }
            }
            $includeMarkings      = ($equipment['multisport_markings'] ?? true) !== false;
            $includeFootball      = ($equipment['football_goals'] ?? true) !== false;
            $includeVolleyball    = ($equipment['volleyball_net'] ?? true) !== false;
            $includeBadminton     = ($equipment['badminton_net'] ?? true) !== false;
            $includeCricketStumps = ($equipment['cricket_pitch'] ?? true) !== false;

            if ($includeMarkings) {
                $docItems[] = [
                    'description' => "Multi-Sport Regulation Inlaid Line Markings (Cricket, Mini Football, Volleyball & Badminton)\r\nMandatory permanent high-contrast multi-sport court line markings inlaid directly into turf: Regulation White lines for Football perimeter, center circle & penalty boxes; Cricket pitch bowling & popping creases; High-contrast Yellow lines for 59 FT × 29.5 FT Volleyball court; High-contrast Cyan lines for 44 FT × 20 FT BWF Badminton court.",
                    'hsn'         => '9506',
                    'qty'         => 1,
                    'unit'        => 'SET',
                    'rate'        => 28000.0,
                    'amount'      => 28000.0,
                    'specs'       => [
                        "Mini Football Markings: Regulation white sidelines, goal lines, halfway line, center circle (24 FT dia), penalty boxes",
                        "Box Cricket Markings: 41 FT pitch bowling, popping & return creases with wicket markers",
                        "Volleyball Markings: 59 FT × 29.5 FT (18m × 9m) yellow perimeter boundary lines & 3m attack lines",
                        "Badminton Markings: 44 FT × 20 FT (13.4m × 6.1m) BWF cyan lines with short service, center & doubles lines"
                    ],
                    'gst_rate'    => 18.0,
                ];
            }
            if ($includeFootball) {
                $docItems[] = [
                    'description' => "Heavy-Duty Steel Mini Football Goal Posts (10 FT × 6.5 FT × 3.5 FT) with Nets - 1 Pair\r\n1 Pair (2 Nos) heavy-duty tubular steel mini football / futsal goal posts (10 FT wide × 6.5 FT high × 3.5 FT runback depth), powder-coated weather-shield finish, 3.5mm UV-treated braided all-weather hexagonal nets, ground anchors & high-density protective safety corner padding.",
                    'hsn'         => '9506',
                    'qty'         => 1,
                    'unit'        => 'PAIR',
                    'rate'        => 35000.0,
                    'amount'      => 35000.0,
                    'specs'       => [
                        "Quantity: 1 Pair (2 Units for dual goal ends: East & West Goal Mouths)",
                        "Dimensions: 10 FT Width × 6.5 FT Height × 3.5 FT Depth (Official Mini Football / Futsal Size)",
                        "Frame: Heavy-gauge 3\" (75mm) round/square tubular steel posts and crossbar with radius corners",
                        "Netting: 3.5mm UV-treated high-tenacity braided nylon hexagonal mesh with net clips",
                        "Safety: High-density protective safety foam corner and upright padding wraps"
                    ],
                    'gst_rate'    => 18.0,
                ];
            }
            if ($includeVolleyball) {
                $docItems[] = [
                    'description' => "Heavy-Duty Volleyball Upright Posts with Sleeves & Competition Net System\r\n1 Pair heavy-duty tubular steel volleyball upright posts (8 FT / 2.43m official height) with recessed ground sleeves/caps, heavy-duty worm-gear tension winch, regulation 32 FT × 3.28 FT nylon net with vinyl top/bottom bands, guide cables, and fiberglass antenna set.",
                    'hsn'         => '9506',
                    'qty'         => 1,
                    'unit'        => 'SET',
                    'rate'        => 18500.0,
                    'amount'      => 18500.0,
                    'specs'       => [
                        "Uprights: Heavy 3\" (76mm) powder-coated MS posts with dual height adjustment (Men 2.43m / Women 2.24m)",
                        "Mounting: Flush recessed ground sleeves with cap covers for quick setup/removal",
                        "Net: Official 32 FT × 3.28 FT (9.5m × 1m) heavy knotted net with steel cable & vinyl headbands",
                        "Accessories: Smooth worm-gear ratchet tensioner, dual antenna set with pocket holders, and post safety pads"
                    ],
                    'gst_rate'    => 18.0,
                ];
            }
            if ($includeBadminton) {
                $docItems[] = [
                    'description' => "Heavy-Duty Movable Badminton Posts with Cast-Iron Base & BWF Regulation Net\r\nHeavy-duty movable tournament badminton posts (5 FT 1 in / 1.55m) with 40kg solid cast-iron counterweight bases, built-in non-marking nylon transport wheels, ratchet tensioner mechanism, and BWF regulation nylon tournament net with reinforced white headband.",
                    'hsn'         => '9506',
                    'qty'         => 1,
                    'unit'        => 'SET',
                    'rate'        => 16500.0,
                    'amount'      => 16500.0,
                    'specs'       => [
                        "Posts: Official 5 FT 1 in (1.55m) heavy tubular steel uprights with groove pulley tops",
                        "Base: Heavy 40kg solid cast-iron wheel-mounted counterweight bases for stability without drilling",
                        "Net: BWF-certified 20 FT × 2.5 FT tournament net with high-tenacity nylon cord & white vinyl headband",
                        "Portability: Heavy-duty non-marking transport wheels for rapid roll-in / roll-out conversion"
                    ],
                    'gst_rate'    => 18.0,
                ];
            }
            if ($includeCricketStumps) {
                $docItems[] = [
                    'description' => "Center Batting Pitch Accessories & PVC Match Stumps\r\nRegulation spring-loaded match wickets, bails, boundary markers & crease inlays for Box Cricket setup.",
                    'hsn'         => '9506',
                    'qty'         => 1,
                    'unit'        => 'SET',
                    'rate'        => 8500.0,
                    'amount'      => 8500.0,
                    'specs'       => [
                        "Accessories: 2 Sets regulation spring-loaded PVC match wickets with heavy rubber bases",
                        "Boundary: High-visibility boundary indicator cones and corner flags"
                    ],
                    'gst_rate'    => 18.0,
                ];
            }
        } else {
            if (is_string($equipment)) {
                try { $equipment = json_decode($equipment, true) ?: []; } catch (\Exception $e) { $equipment = []; }
            }
            $docItems[] = [
                'description' => "Court Markings & Sports Fixtures (" . $sportProfile['name'] . ")\n" . $sportProfile['equipment_desc'],
                'hsn'         => '9506',
                'qty'         => 1,
                'unit'        => 'SET',
                'rate'        => $equipAmt,
                'amount'      => $equipAmt,
                'specs'       => $equipSpecs,
                'gst_rate'    => 18.0,
            ];

            // If user explicitly enabled equipment options on any other court:
            if (!empty($equipment['football_goals'])) {
                $docItems[] = [
                    'description' => "Heavy-Duty Steel Mini Football Goal Posts (10 FT × 6.5 FT × 3.5 FT) with Nets - 1 Pair\r\n1 Pair (2 Nos) heavy-duty tubular steel mini football / futsal goal posts (10 FT wide × 6.5 FT high × 3.5 FT runback depth), powder-coated weather-shield finish, 3.5mm UV-treated braided all-weather hexagonal nets, ground anchors & high-density protective safety corner padding.",
                    'hsn'         => '9506',
                    'qty'         => 1,
                    'unit'        => 'PAIR',
                    'rate'        => 35000.0,
                    'amount'      => 35000.0,
                    'specs'       => [
                        "Quantity: 1 Pair (2 Units for dual goal ends)",
                        "Dimensions: 10 FT Width × 6.5 FT Height × 3.5 FT Depth",
                        "Frame: Heavy-gauge 3\" tubular steel with powder-coating",
                        "Netting: 3.5mm UV-treated hexagonal mesh with clips"
                    ],
                    'gst_rate'    => 18.0,
                ];
            }
            if (!empty($equipment['volleyball_net'])) {
                $docItems[] = [
                    'description' => "Heavy-Duty Volleyball Upright Posts with Sleeves & Competition Net System\r\n1 Pair heavy-duty tubular steel volleyball upright posts (8 FT / 2.43m official height) with recessed ground sleeves/caps, heavy-duty worm-gear tension winch, regulation 32 FT × 3.28 FT nylon net with vinyl top/bottom bands, guide cables, and fiberglass antenna set.",
                    'hsn'         => '9506',
                    'qty'         => 1,
                    'unit'        => 'SET',
                    'rate'        => 18500.0,
                    'amount'      => 18500.0,
                    'specs'       => [
                        "Uprights: Heavy 3\" MS posts with recessed sleeves",
                        "Net: Official 32 FT × 3.28 FT competition net with steel cable",
                        "Accessories: Tension ratchet winch, antennas, and pads"
                    ],
                    'gst_rate'    => 18.0,
                ];
            }
            if (!empty($equipment['badminton_net'])) {
                $docItems[] = [
                    'description' => "Heavy-Duty Movable Badminton Posts with Cast-Iron Base & BWF Regulation Net\r\nHeavy-duty movable tournament badminton posts (5 FT 1 in / 1.55m) with 40kg solid cast-iron counterweight bases, built-in non-marking nylon transport wheels, ratchet tensioner mechanism, and BWF regulation nylon tournament net with reinforced white headband.",
                    'hsn'         => '9506',
                    'qty'         => 1,
                    'unit'        => 'SET',
                    'rate'        => 16500.0,
                    'amount'      => 16500.0,
                    'specs'       => [
                        "Posts: Official 1.55m heavy tubular steel uprights",
                        "Base: Heavy 40kg solid cast-iron wheeled base",
                        "Net: BWF-certified 20 FT tournament net"
                    ],
                    'gst_rate'    => 18.0,
                ];
            }
            if (!empty($equipment['multisport_markings'])) {
                $docItems[] = [
                    'description' => "Multi-Sport Regulation Inlaid Line Markings (Cricket, Mini Football, Volleyball & Badminton)\r\nPermanent high-contrast multi-sport line markings inlaid directly into turf: Regulation White lines for Football; Cricket pitch creases; Yellow lines for Volleyball; Cyan lines for Badminton.",
                    'hsn'         => '9506',
                    'qty'         => 1,
                    'unit'        => 'SET',
                    'rate'        => 28000.0,
                    'amount'      => 28000.0,
                    'specs'       => [
                        "Markings: Inlaid multi-color synthetic turf lines",
                        "Games: Football, Cricket, Volleyball, Badminton"
                    ],
                    'gst_rate'    => 18.0,
                ];
            }
        }

        // 12. Protective Finishing
        $finishAmt = $isSmallCourt
            ? (($selectedPkg === 'essential') ? 5500 : (($selectedPkg === 'premium_pro') ? 10000 : 7500))
            : (($selectedPkg === 'essential') ? 16000 : (($selectedPkg === 'premium_pro') ? 32000 : 22000));
        $docItems[] = [
            'description' => "Anti-Corrosion Primer & Weather-Shield Coating\n" . $pkg['items']['finishing']['desc'],
            'hsn'         => '3208',
            'qty'         => 1,
            'unit'        => 'LOT',
            'rate'        => $finishAmt,
            'amount'      => $finishAmt,
            'specs'       => ["Surface degreasing, epoxy primer + 2 coats weather-shield PU coating"],
            'gst_rate'    => 18.0,
        ];

        // 13. Turnkey Installation
        $installAmt = $isSmallCourt
            ? (($selectedPkg === 'essential') ? 10000 : (($selectedPkg === 'premium_pro') ? 18000 : 14000))
            : (($selectedPkg === 'essential') ? 28000 : (($selectedPkg === 'premium_pro') ? 50000 : 38000));
        $docItems[] = [
            'description' => "Certified Turnkey Erection & Installation\n" . $pkg['items']['installation']['desc'],
            'hsn'         => '9954',
            'qty'         => 1,
            'unit'        => 'LOT',
            'rate'        => $installAmt,
            'amount'      => $installAmt,
            'specs'       => ["Full turnkey labor, certified welding, laser leveling, and commissioning"],
            'gst_rate'    => 18.0,
        ];

        // 14. Logistics & Freight
        $transAmt = $isSmallCourt
            ? (($selectedPkg === 'essential') ? 5000 : (($selectedPkg === 'premium_pro') ? 8500 : 6500))
            : (($selectedPkg === 'essential') ? 14000 : (($selectedPkg === 'premium_pro') ? 22000 : 18000));
        $docItems[] = [
            'description' => "Freight, Material Transportation & Staging\n" . $pkg['items']['transport']['desc'],
            'hsn'         => '9965',
            'qty'         => 1,
            'unit'        => 'LOT',
            'rate'        => $transAmt,
            'amount'      => $transAmt,
            'specs'       => ["Site staging and freight logistics"],
            'gst_rate'    => 18.0,
        ];

        // 15. Project Management
        $pmAmt = $isSmallCourt
            ? (($selectedPkg === 'essential') ? 3000 : (($selectedPkg === 'premium_pro') ? 6000 : 4000))
            : (($selectedPkg === 'essential') ? 10000 : (($selectedPkg === 'premium_pro') ? 22000 : 15000));
        $docItems[] = [
            'description' => "Project Engineering Supervision & Quality Handover\n" . $pkg['items']['project_mgmt']['desc'],
            'hsn'         => '9983',
            'qty'         => 1,
            'unit'        => 'LOT',
            'rate'        => $pmAmt,
            'amount'      => $pmAmt,
            'specs'       => ["Supervision, quality inspection, and testing handover"],
            'gst_rate'    => 18.0,
        ];

        $naturalSubtotal = array_sum(array_column($docItems, 'amount'));

                foreach ($docItems as &$it) {
            $desc = $it['description'] ?? '';
            $hsn = function_exists('resolveItemHsn') ? resolveItemHsn($desc, $it['hsn'] ?? '') : ($it['hsn'] ?? '5608');
            $it['hsn'] = $hsn;
            $it['gst_rate'] = function_exists('resolveItemGstRate') ? resolveItemGstRate($desc, $hsn, $it['gst_rate'] ?? null) : (float)($it['gst_rate'] ?? 18.0);
            $it['unit'] = function_exists('normalizeUqcUnit') ? normalizeUqcUnit($it['unit'] ?? 'SQF-SQUARE FEET') : 'SQF-SQUARE FEET';
        }
        unset($it);
        $naturalSubtotal = array_sum(array_column($docItems, 'amount'));

        // Custom target rate scaling if explicitly supplied
        if ($customRate !== null && (float)$customRate > 0) {
            $customRate = (float)$customRate;
            $targetTotal = round($baseArea * $customRate, 2);
            $scaleFactor = $targetTotal / max(1, $naturalSubtotal);
            foreach ($docItems as &$di) {
                $di['amount'] = round($di['amount'] * $scaleFactor, 2);
                $di['rate']   = round($di['amount'] / max(1, $di['qty']), 2);
            }
            unset($di);
            $calculatedSubtotal = array_sum(array_column($docItems, 'amount'));
            $effectiveTargetRate = $customRate;
        } else {
            $calculatedSubtotal = $naturalSubtotal;
            $effectiveTargetRate = round($calculatedSubtotal / max(1, $baseArea), 2);
        }

        $scaledPackages = getTurnkeyScaledPackages($specs, $calculatedSubtotal, $selectedPkg, $civilBedType);
        $scopeItems = generateTurnkeyScopeItems($specs, $selectedPkg);

        return [
            'specs'                => $specs,
            'selected_package'     => $selectedPkg,
            'selected_pkg'         => [
                'target_rate' => $effectiveTargetRate,
                'name'        => $pkg['name'] ?? ucfirst($selectedPkg),
            ],
            'package_info'         => $pkg,
            'base_area_sft'        => $baseArea,
            'target_rate'          => $effectiveTargetRate,
            'target_total'         => $calculatedSubtotal,
            'boq_subtotal'         => $calculatedSubtotal,
            'items'                => $docItems,
            'doc_items'            => $docItems,
            'packages'             => $scaledPackages,
            'scope_items'          => $scopeItems,
            'spec_rows'            => getTurnkeyDetailedSpecRows($specs, $selectedPkg),
        ];
    }
}
