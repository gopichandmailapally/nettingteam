<?php
$currentFile = '/var/www/nettingteam.com/admin/data/documents.json';
$currentDocs = json_decode(file_get_contents($currentFile), true) ?: [];

$currentInvs = [];
foreach ($currentDocs as $d) {
    $id = $d['id'] ?? '';
    $type = $d['type'] ?? '';
    if (strpos($id, 'INV-') === 0 || in_array($type, ['tax_invoice', 'invoice', 'bill'])) {
        $currentInvs[$id] = $d;
    }
}

echo "=== CURRENT INVOICES/BILLS IN documents.json: " . count($currentInvs) . " ===\n";
foreach ($currentInvs as $id => $d) {
    $cust = $d['customer_name'] ?? ($d['customer']['name'] ?? 'N/A');
    $date = $d['date'] ?? 'N/A';
    $total = $d['grand_total'] ?? 0;
    $type = $d['type'] ?? 'N/A';
    echo "  $id | Type: $type | Date: $date | Cust: $cust | Total: ₹$total\n";
}

$backupFiles = array_merge(
    glob('/var/www/nettingteam.com/admin/data/backups/documents_*.json') ?: [],
    glob('/var/www/nettingteam.com/admin/data/documents.json.bak*') ?: []
);
sort($backupFiles);

$allBackupInvs = [];
echo "\n=== SCANNING " . count($backupFiles) . " BACKUP FILES ===\n";
foreach ($backupFiles as $bf) {
    $content = file_get_contents($bf);
    $bdocs = json_decode($content, true) ?: [];
    $bCount = 0;
    foreach ($bdocs as $d) {
        $id = $d['id'] ?? '';
        $type = $d['type'] ?? '';
        if (strpos($id, 'INV-') === 0 || in_array($type, ['tax_invoice', 'invoice', 'bill'])) {
            $bCount++;
            $allBackupInvs[$id] = ['file' => basename($bf), 'doc' => $d];
        }
    }
    echo "  " . basename($bf) . " : " . count($bdocs) . " total docs, $bCount invoices/bills\n";
}

echo "\n=== ALL DISTINCT INVOICES/BILLS FOUND IN BACKUPS: " . count($allBackupInvs) . " ===\n";
$missingCount = 0;
foreach ($allBackupInvs as $id => $info) {
    $d = $info['doc'];
    $isMissing = !isset($currentInvs[$id]);
    if ($isMissing) {
        $missingCount++;
        $tag = "*** MISSING FROM CURRENT ***";
    } else {
        $tag = "PRESENT";
    }
    $cust = $d['customer_name'] ?? ($d['customer']['name'] ?? 'N/A');
    $date = $d['date'] ?? 'N/A';
    $total = $d['grand_total'] ?? 0;
    $file = $info['file'];
    echo "  $tag | $id | From: $file | Date: $date | Cust: $cust | Total: ₹$total\n";
}

// Also check ALL documents in backups that might be missing completely!
$currentAllIds = array_column($currentDocs, 'id');
$missingAllDocs = [];
foreach ($backupFiles as $bf) {
    $bdocs = json_decode(file_get_contents($bf), true) ?: [];
    foreach ($bdocs as $d) {
        $did = $d['id'] ?? '';
        if ($did && !in_array($did, $currentAllIds) && !isset($missingAllDocs[$did])) {
            $missingAllDocs[$did] = ['file' => basename($bf), 'doc' => $d];
        }
    }
}

echo "\n=== TOTAL MISSING DOCUMENTS OF ANY TYPE: " . count($missingAllDocs) . " ===\n";
foreach ($missingAllDocs as $mid => $minfo) {
    $mdoc = $minfo['doc'];
    $mtype = $mdoc['type'] ?? 'N/A';
    $mcust = $mdoc['customer_name'] ?? ($mdoc['customer']['name'] ?? 'N/A');
    echo "  $mid | Type: $mtype | From: {$minfo['file']} | Cust: $mcust | Date: " . ($mdoc['date'] ?? 'N/A') . "\n";
}
