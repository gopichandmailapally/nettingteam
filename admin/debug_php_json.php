<?php
$str = file_get_contents("/var/www/nettingteam.com/admin/data/documents.json");
echo "String length: " . strlen($str) . "\n";
// Let us test decoding line by line or character by character
$arr = json_decode($str, true);
if ($arr === null) {
    echo "Overall error: " . json_last_error_msg() . "\n";
    // Binary search for error
    $low = 0; $high = strlen($str);
    while ($high - $low > 50) {
        $mid = intval(($low + $high) / 2);
        // Find nearest closing brace
        $pos = strpos($str, "}", $mid);
        if ($pos === false) break;
        $sub = substr($str, 0, $pos + 1) . "]";
        $test = json_decode($sub, true);
        if ($test !== null) {
            $low = $pos;
        } else {
            // Check if it failed with syntax error or unexpected end
            $high = $pos;
        }
    }
    echo "Problem area around byte $low - $high:\n";
    echo substr($str, max(0, $low - 100), 300) . "\n";
} else {
    echo "SUCCESS: " . count($arr) . " items\n";
}
