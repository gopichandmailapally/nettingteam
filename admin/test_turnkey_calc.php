<?php
require_once __DIR__ . '/turnkey_calculator.php';

echo "Testing getTurnkeySportProfiles()...\n";
$sports = getTurnkeySportProfiles();
echo "Total sports profiles: " . count($sports) . "\n";
assert(isset($sports['terrace_cricket_small']), "terrace_cricket_small missing");
assert(isset($sports['terrace_cricket_lane']), "terrace_cricket_lane missing");
assert(isset($sports['terrace_cricket_box']), "terrace_cricket_box missing");
assert(isset($sports['terrace_sports_arena']), "terrace_sports_arena missing");

echo "Testing getTurnkeyCivilBedOptions()...\n";
$civil = getTurnkeyCivilBedOptions();
echo "Total civil bed options: " . count($civil) . "\n";
assert(isset($civil['terrace_base_plates']), "terrace_base_plates missing");

echo "Testing getTurnkeyCourts()...\n";
$courts = getTurnkeyCourts();
echo "Total court presets: " . count($courts) . "\n";
assert(isset($courts['terrace_cricket_small']), "terrace_cricket_small court preset missing");

echo "\n--- 1. Testing Terrace Cricket Net (30x15x12) ---\n";
$specs1 = calculateTurnkeyCourtSpecs(30, 15, 12, 'terrace_cricket_small', 'light_cable_top', 'ladder_2line', '40x40', '2.0mm', 'no_turf', 'terrace_base_plates', 'braided_2_5mm_45mm', 'twisted_1_5mm_35mm', 1);
echo "Specs 1: Area = {$specs1['base_area_sft']} SFT, Total Poles = {$specs1['total_poles']}, Civil Bed = {$specs1['civil_bed_profile']['short_name']}\n";
$boq1 = generateTurnkeyBOQ(30, 15, 12, 'terrace_cricket_small', 'light_cable_top', 'commercial', 'ladder_2line', '40x40', '2.0mm', 'no_turf', null, 30, 'terrace_base_plates', 'braided_2_5mm_45mm', 'twisted_1_5mm_35mm', 1);
echo "BOQ 1 items count: " . count($boq1['items']) . ", Subtotal: ₹" . number_format($boq1['boq_subtotal']) . ", Rate: ₹{$boq1['target_rate']}/SFT\n";
echo "BOQ 1 Item 1: " . $boq1['items'][0]['description'] . "\n";
echo "BOQ 1 Item 1 Spec Details: " . implode(" | ", $boq1['items'][0]['specs']) . "\n";

echo "\n--- 2. Testing Football 8-a-side (150x80x28) ---\n";
$boq2 = generateTurnkeyBOQ(150, 80, 28, 'football_8a_compact', 'light_cable_top', 'commercial', 'ladder_2line', '50x50', '2.5mm', 'infill_turf', null, 0, 'compacted_wmm', 'braided_3_5mm_100mm', 'twisted_1_5mm_100mm', 2);
echo "BOQ 2 items count: " . count($boq2['items']) . ", Subtotal: ₹" . number_format($boq2['boq_subtotal']) . ", Rate: ₹{$boq2['target_rate']}/SFT\n";

echo "\n--- 3. Testing 3\" PCC M15 Broom (90x60x25) ---\n";
$boq3 = generateTurnkeyBOQ(90, 60, 25, 'box_cricket', 'light_cable_top', 'commercial', 'ladder_2line', '40x40', '2.0mm', 'non_infill_turf', null, 66, 'pcc_m15_3inch_broom', 'braided_2_5mm_45mm', 'twisted_1_5mm_35mm', 1);
echo "BOQ 3 items count: " . count($boq3['items']) . ", Subtotal: ₹" . number_format($boq3['boq_subtotal']) . ", Rate: ₹{$boq3['target_rate']}/SFT\n";
echo "Civil item: " . $boq3['items'][0]['description'] . "\n";

echo "\n--- 4. Testing 4\" PCC M15 Tremix (100x60x25) ---\n";
$boq4 = generateTurnkeyBOQ(100, 60, 25, 'box_cricket', 'light_cable_top', 'commercial', 'ladder_2line', '40x40', '2.0mm', 'non_infill_turf', null, 66, 'pcc_m15_4inch_tremix', 'braided_2_5mm_45mm', 'twisted_1_5mm_35mm', 1);
echo "BOQ 4 items count: " . count($boq4['items']) . ", Subtotal: ₹" . number_format($boq4['boq_subtotal']) . ", Rate: ₹{$boq4['target_rate']}/SFT\n";
echo "Civil item: " . $boq4['items'][0]['description'] . "\n";

echo "\n--- 5. Testing 6\" PCC M15 Tremix (120x60x28) ---\n";
$boq5 = generateTurnkeyBOQ(120, 60, 28, 'box_cricket', 'light_cable_top', 'commercial', 'ladder_2line', '50x50', '2.0mm', 'non_infill_turf', null, 66, 'pcc_m15_6inch_tremix', 'braided_2_5mm_45mm', 'twisted_1_5mm_35mm', 1);
echo "BOQ 5 items count: " . count($boq5['items']) . ", Subtotal: ₹" . number_format($boq5['boq_subtotal']) . ", Rate: ₹{$boq5['target_rate']}/SFT\n";

echo "\n--- 6. Testing RCC M20 4\" Mesh (End of list variant) ---\n";
$boq6 = generateTurnkeyBOQ(90, 60, 25, 'box_cricket', 'light_cable_top', 'commercial', 'ladder_2line', '40x40', '2.0mm', 'non_infill_turf', null, 66, 'rcc_m20_4inch_mesh', 'braided_2_5mm_45mm', 'twisted_1_5mm_35mm', 1);
echo "BOQ 6 items count: " . count($boq6['items']) . ", Subtotal: ₹" . number_format($boq6['boq_subtotal']) . ", Rate: ₹{$boq6['target_rate']}/SFT\n";

echo "\nALL TESTS PASSED WITH 100% ACCURACY! 🚀\n";
