<?php
require_once __DIR__ . '/turnkey_calculator.php';

// ─── Data Access ────────────────────────────────────────────────────────────

define('VAULT_DIR', '/var/www/gcm_vault');

function dataPath($file) {
    return __DIR__ . '/../data/' . $file . '.json';
}

function vaultPath($file) {
    return VAULT_DIR . '/' . $file . '.json';
}

function getDeletedIds() {
    $p = VAULT_DIR . '/deleted_ids.json';
    if (!file_exists($p)) return [];
    $data = json_decode(file_get_contents($p), true);
    return is_array($data) ? $data : [];
}

function markDocumentDeleted($id, $doc = null) {
    $p = VAULT_DIR . '/deleted_ids.json';
    $deleted = getDeletedIds();
    $deleted[$id] = [
        'deleted_at' => date('c'),
        'deleted_by' => $_SESSION['gcm_user'] ?? ($_SESSION['admin_user'] ?? 'admin'),
        'doc_type'   => $doc['type'] ?? '',
        'client'     => $doc['bill_to']['name'] ?? '',
        'total'      => $doc['grand_total'] ?? ($doc['total'] ?? 0)
    ];
    @file_put_contents($p, json_encode($deleted, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    // Also store complete deleted document in trash archive so it is never truly lost
    if ($doc) {
        $trashFile = VAULT_DIR . '/trash_documents.json';
        $trash = [];
        if (file_exists($trashFile)) {
            $trash = json_decode(file_get_contents($trashFile), true) ?: [];
        }
        $trash[] = array_merge($doc, ['_deleted_meta' => $deleted[$id]]);
        @file_put_contents($trashFile, json_encode($trash, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}

function loadData($file, $forceReload = false) {
    static $runtimeCache = [];
    if (!$forceReload && isset($runtimeCache[$file])) {
        return $runtimeCache[$file];
    }
    $path = dataPath($file);
    $localData = [];
    if (file_exists($path)) {
        $content = file_get_contents($path);
        $decoded = json_decode($content, true);
        $localData = is_array($decoded) ? $decoded : [];
    }

    // ── Immortal Vault Self-Healing Engine ──
    if ($file === 'documents') {
        $vPath = vaultPath('documents');
        $vaultData = [];
        if (file_exists($vPath)) {
            $vContent = file_get_contents($vPath);
            $vDecoded = json_decode($vContent, true);
            $vaultData = is_array($vDecoded) ? $vDecoded : [];
        }

        $deletedIds = getDeletedIds();
        $localMap = [];
        foreach ($localData as $d) {
            if (!empty($d['id'])) $localMap[$d['id']] = $d;
        }

        $vaultMap = [];
        foreach ($vaultData as $vd) {
            if (!empty($vd['id'])) $vaultMap[$vd['id']] = $vd;
        }

        $needsLocalRepair = false;
        $needsVaultUpdate = false;

        // 1. Recover any documents present in Vault that disappeared from local without manual deletion
        foreach ($vaultMap as $vId => $vDoc) {
            if (!isset($localMap[$vId])) {
                // If it was NOT deliberately deleted by an admin, it was lost by a git update / file overwrite!
                if (!isset($deletedIds[$vId])) {
                    $localMap[$vId] = $vDoc;
                    $needsLocalRepair = true;
                    error_log("IMMORTAL VAULT AUTO-HEAL: Restored " . $vId . " (" . ($vDoc['bill_to']['name'] ?? '') . ") which was lost during a website update.");
                }
            }
        }

        // 2. Ensure any new local documents are safely mirrored to the Vault
        foreach ($localMap as $lId => $lDoc) {
            if (!isset($vaultMap[$lId])) {
                $vaultMap[$lId] = $lDoc;
                $needsVaultUpdate = true;
            }
        }

        if ($needsLocalRepair || $needsVaultUpdate) {
            // Sort by created_at / date descending
            $merged = array_values($localMap);
            usort($merged, function($a, $b) {
                $ta = strtotime($a['created_at'] ?? $a['date'] ?? '2000-01-01');
                $tb = strtotime($b['created_at'] ?? $b['date'] ?? '2000-01-01');
                return $tb <=> $ta;
            });
            $localData = $merged;

            if ($needsLocalRepair) {
                $json = json_encode($localData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                @file_put_contents($path, $json, LOCK_EX);
                @chmod($path, 0666);
            }
            if ($needsVaultUpdate || $needsLocalRepair) {
                $vJson = json_encode($localData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                @file_put_contents($vPath, $vJson, LOCK_EX);
                @chmod($vPath, 0666);
            }
        }
    } elseif ($file === 'counters') {
        $vPath = vaultPath('counters');
        if (file_exists($vPath)) {
            $vaultCounters = json_decode(file_get_contents($vPath), true) ?: [];
            $dirty = false;
            foreach ($vaultCounters as $k => $v) {
                if (!isset($localData[$k]) || $v > $localData[$k]) {
                    $localData[$k] = $v;
                    $dirty = true;
                }
            }
            foreach ($localData as $k => $v) {
                if (!isset($vaultCounters[$k]) || $v > $vaultCounters[$k]) {
                    $vaultCounters[$k] = $v;
                    $dirty = true;
                }
            }
            if ($dirty) {
                @file_put_contents($path, json_encode($localData, JSON_PRETTY_PRINT), LOCK_EX);
                @file_put_contents($vPath, json_encode($vaultCounters, JSON_PRETTY_PRINT), LOCK_EX);
            }
        }
    } elseif ($file === 'customers') {
        $vPath = vaultPath('customers');
        if (file_exists($vPath)) {
            $vaultCust = json_decode(file_get_contents($vPath), true) ?: [];
            $cMap = [];
            foreach ($localData as $c) {
                if (!empty($c['id'])) $cMap[$c['id']] = $c;
            }
            $dirty = false;
            foreach ($vaultCust as $vc) {
                if (!empty($vc['id']) && !isset($cMap[$vc['id']])) {
                    $cMap[$vc['id']] = $vc;
                    $dirty = true;
                }
            }
            if ($dirty) {
                $localData = array_values($cMap);
                @file_put_contents($path, json_encode($localData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
                @file_put_contents($vPath, json_encode($localData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
            }
        }
    }

    $runtimeCache[$file] = $localData;
    return $localData;
}

function saveData($file, $data) {
    $path = dataPath($file);
    // Automatic safety snapshot backup for critical master data (hourly snapshot)
    if ($file === 'documents' && file_exists($path) && filesize($path) > 1000) {
        $backupDir = __DIR__ . '/../data/backups/auto';
        if (!is_dir($backupDir)) @mkdir($backupDir, 0777, true);
        $backupFile = $backupDir . '/documents_' . date('Ymd_H') . '.json';
        if (!file_exists($backupFile)) {
            @copy($path, $backupFile);
        }
    }
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $res = @file_put_contents($path, $json, LOCK_EX);
    if ($res === false) {
        @chmod($path, 0666);
        $res = @file_put_contents($path, $json, LOCK_EX);
    }
    if ($res !== false) {
        @chmod($path, 0666);
    } else {
        error_log("CRITICAL ERROR: saveData failed for file {$file} at path {$path}");
    }

    // Mirror immediately to Immortal Vault
    if (in_array($file, ['documents', 'counters', 'customers'])) {
        $vPath = vaultPath($file);
        @file_put_contents($vPath, $json, LOCK_EX);
        @chmod($vPath, 0666);

        // Append to immutable write-only journal if documents
        if ($file === 'documents') {
            $journalFile = VAULT_DIR . '/journal.jsonl';
            $entry = json_encode([
                'ts'   => date('c'),
                'user' => $_SESSION['gcm_user'] ?? ($_SESSION['admin_user'] ?? 'system'),
                'count'=> count($data),
                'top_id'=> $data[0]['id'] ?? ''
            ]) . "\n";
            @file_put_contents($journalFile, $entry, FILE_APPEND | LOCK_EX);
        }
    }

    loadData($file, true);
    return $res;
}

function getSettings() {
    $path = dataPath('settings');
    if (!file_exists($path)) return defaultSettings();
    $content = file_get_contents($path);
    $decoded = json_decode($content, true);
    return is_array($decoded) ? $decoded : defaultSettings();
}

function saveSettings($settings) {
    return saveData('settings', $settings);
}

function defaultTurfCatalog() {
    return [
        'cricket_pitch' => [
            'full_roll_sft' => 540,
            'half_roll_sft' => 270,
            'rate_per_sft' => 78.0,
            'install_rate_per_sft' => 9.0,
            'boundary_offset_ft' => 3.0,
            'half_roll_cutoff_ft' => 48.0,
            'width_ft' => 6.5,
            'full_roll_name' => "15mm Curled Multisport Pitch Turf (Full Roll 6.5' × 82' [2M × 25M])",
            'half_roll_name' => "15mm Curled Multisport Pitch Turf (Half Roll 6.5' × 41.5' [2M × 12.5M])",
        ],
        'landscape_grass' => [
            'rates' => [
                '25mm' => 30.0,
                '30mm' => 35.0,
                '35mm' => 40.0,
                '40mm' => 45.0,
            ],
            'names' => [
                '25mm' => '25mm Landscape Sports Grass',
                '30mm' => '30mm Landscape Sports Grass',
                '35mm' => '35mm Landscape Sports Grass',
                '40mm' => '40mm Landscape Sports Grass',
            ],
            'install_tiers' => [
                ['min_sft' => 0, 'max_sft' => 1500, 'rate' => 8.0],
                ['min_sft' => 1501, 'max_sft' => 3000, 'rate' => 6.5],
                ['min_sft' => 3001, 'max_sft' => 999999, 'rate' => 5.0],
            ],
        ],
        'sports_grass' => [
            '30mm_non_infill' => [
                'name' => '30mm High-Density Non-Infill Sports Grass (No Rubber/Sand)',
                'rate' => 60.0,
                'has_infill' => false,
                'install_rate' => 9.0,
                'desc' => 'Heavy-duty 30mm non-infill sports grass designed for high-traction multi-sport and box cricket.',
            ],
            '50mm_12000_dtex' => [
                'name' => '50mm Football / Box Cricket Turf (12,000 DTEX)',
                'rate' => 58.0,
                'has_infill' => true,
                'install_rate' => 12.0,
                'desc' => '50mm monofilament football turf (12,000 DTEX) with silica sand & SBR rubber infill system.',
            ],
            '50mm_14000_dtex' => [
                'name' => '50mm Football / Box Cricket Turf (14,000 DTEX)',
                'rate' => 64.0,
                'has_infill' => true,
                'install_rate' => 12.0,
                'desc' => '50mm high-resilience diamond monofilament sports turf (14,000 DTEX) with silica sand & SBR rubber infill.',
            ],
            '50mm_16000_dtex' => [
                'name' => '50mm Football / Box Cricket Turf (16,000 DTEX)',
                'rate' => 75.0,
                'has_infill' => true,
                'install_rate' => 13.0,
                'desc' => '50mm premium tournament-grade monofilament sports turf (16,000 DTEX) with full infill system.',
            ],
        ],
        'infill_standards' => [
            'silica_sand' => [
                'name' => 'Graded High-Purity Silica Sand (Infill)',
                'kg_per_sqm' => 23.75,
                'kg_per_sqft' => 2.207,
                'rate_per_kg' => 6.50,
            ],
            'rubber_granules' => [
                'name' => 'SBR Resilient Rubber Granules (50kg Bags)',
                'kg_per_sqm' => 6.64,
                'kg_per_sqft' => 0.617,
                'rate_per_kg' => 35.00,
            ],
            'joint_tape' => [
                'name' => 'White High-Tensile Joint Seaming Tape (100m Roll)',
                'mtr_per_sqm' => 0.415,
                'rft_per_sqft' => 0.126,
                'rate_per_mtr' => 45.00,
            ],
            'pu_glue' => [
                'name' => 'Speb 7 Two-Component Polyurethane Adhesive (5L Cans)',
                'kg_per_sqm' => 0.108,
                'kg_per_sqft' => 0.010,
                'rate_per_kg' => 330.00,
            ],
        ],
    ];
}

function getTurfCatalog() {
    $catalog = loadData('turf_catalog');
    if (!empty($catalog) && is_array($catalog)) {
        return $catalog;
    }
    $def = defaultTurfCatalog();
    saveTurfCatalog($def);
    return $def;
}

function saveTurfCatalog($catalog) {
    return saveData('turf_catalog', $catalog);
}

function defaultSettings() {
    return [
        'company' => [
            'name'       => 'NETTINGTEAM',
            'proprietor' => 'Gopichand Mallapally',
            'address'    => 'Plot No.68, behind Mega Function Hall, Pavanpuri Colony, MRR Reddy Nagar, Karmanghat, Hyderabad, Telangana 500079',
            'phone'      => '9912399334',
            'phone2'     => '',
            'gstin'      => '36EBFPM5722R2ZY',
            'email'      => '',
            'state'            => 'Telangana',
            'state_code'       => '36',
            'bank_name'        => '',
            'bank_account'     => '',
            'bank_ifsc'        => '',
            'bank_branch'      => '',
            'bank_account_type'=> 'Current Account',
        ],
        'defaults' => [
            'cgst_rate' => 2.5,
            'sgst_rate' => 2.5,
            'igst_rate' => 5,
            'terms'     => [
                'Transport charges excluded',
                '50% Advance payment required to proceed order',
                'All other charges included',
                'Installation charges are included',
            ],
        ],
        'auth'      => ['username' => 'admin', 'password' => ''],
        'signature' => null,
        'stamp'     => null,
        'logo'      => null,
    ];
}

// ─── Document Number Generator ───────────────────────────────────────────────

function generateDocNumber($type, $targetDate = null) {
    $settings  = getSettings();
    $numbering = $settings['numbering'] ?? [];
    $sep       = $numbering['separator'] ?? '-';
    $padding   = max(1, min(6, (int)($numbering['padding'] ?? 3)));
    $prefixMap = [
        'tax_invoice'      => $numbering['inv_prefix']  ?? 'INV',
        'bill'             => $numbering['bill_prefix'] ?? 'BILL',
        'estimation'       => $numbering['est_prefix']  ?? 'EST',
        'proforma_invoice' => $numbering['pi_prefix']   ?? 'PI',
        'proforma'         => $numbering['pi_prefix']   ?? 'PI',
    ];
    $prefix  = $prefixMap[$type] ?? 'EST';
    $ym      = $targetDate ? date('Ym', strtotime($targetDate)) : date('Ym');
    $key     = $prefix . '_' . $ym;

    // Scan all sources: Active docs, Vault docs, all Backups, and Counters
    $maxSeq = 0;
    $prefixPattern = '/^' . preg_quote($prefix, '/') . preg_quote($sep, '/') . preg_quote($ym, '/') . preg_quote($sep, '/') . '(\\d+)$/';

    // 1. Scan active docs
    $docs = loadData('documents');
    foreach ($docs as $d) {
        if (!empty($d['id']) && preg_match($prefixPattern, $d['id'], $m)) {
            $seq = (int)$m[1];
            if ($seq > $maxSeq) $maxSeq = $seq;
        }
    }

    // 2. Scan vault docs
    $vPath = vaultPath('documents');
    if (file_exists($vPath)) {
        $vDocs = json_decode(file_get_contents($vPath), true) ?: [];
        foreach ($vDocs as $vd) {
            if (!empty($vd['id']) && preg_match($prefixPattern, $vd['id'], $m)) {
                $seq = (int)$m[1];
                if ($seq > $maxSeq) $maxSeq = $seq;
            }
        }
    }

    // 3. Scan auto backup directory for any historical documents
    $autoBackups = glob(__DIR__ . '/../data/backups/auto/*.json');
    if ($autoBackups) {
        foreach (array_slice($autoBackups, -15) as $bf) {
            $bDocs = json_decode(file_get_contents($bf), true) ?: [];
            foreach ($bDocs as $bd) {
                if (!empty($bd['id']) && preg_match($prefixPattern, $bd['id'], $m)) {
                    $seq = (int)$m[1];
                    if ($seq > $maxSeq) $maxSeq = $seq;
                }
            }
        }
    }

    // 4. Check counters file (both active and vault)
    $counters = loadData('counters');
    $vCountersPath = vaultPath('counters');
    $vCounters = file_exists($vCountersPath) ? (json_decode(file_get_contents($vCountersPath), true) ?: []) : [];

    $counterSeq = max((int)($counters[$key] ?? 0), (int)($vCounters[$key] ?? 0));
    if ($counterSeq > $maxSeq) {
        $maxSeq = $counterSeq;
    }

    $nextSeq = $maxSeq + 1;

    // Persist new counter forward in both places
    $counters[$key] = $nextSeq;
    saveData('counters', $counters);

    return $prefix . $sep . $ym . $sep . str_pad($nextSeq, $padding, '0', STR_PAD_LEFT);
}

// ─── Currency & Number Helpers ───────────────────────────────────────────────

function fmtAmount($n) {
    return number_format((float)$n, 2, '.', ',');
}

function fmtInr($n) {
    return '₹' . fmtAmount($n);
}

// ─── Countries & Currencies Catalog ──────────────────────────────────────────

function getCountriesList() {
    return [
        'India' => ['name' => 'India', 'code' => 'IN', 'currency' => 'INR', 'phone_prefix' => '+91', 'tax_label' => 'GSTIN'],
        'United Arab Emirates' => ['name' => 'United Arab Emirates', 'code' => 'AE', 'currency' => 'AED', 'phone_prefix' => '+971', 'tax_label' => 'TRN / VAT NO'],
        'United States' => ['name' => 'United States', 'code' => 'US', 'currency' => 'USD', 'phone_prefix' => '+1', 'tax_label' => 'Tax ID / EIN'],
        'Saudi Arabia' => ['name' => 'Saudi Arabia', 'code' => 'SA', 'currency' => 'SAR', 'phone_prefix' => '+966', 'tax_label' => 'VAT / Tax ID'],
        'United Kingdom' => ['name' => 'United Kingdom', 'code' => 'GB', 'currency' => 'GBP', 'phone_prefix' => '+44', 'tax_label' => 'VAT Reg No'],
        'Singapore' => ['name' => 'Singapore', 'code' => 'SG', 'currency' => 'SGD', 'phone_prefix' => '+65', 'tax_label' => 'UEN / GST Reg No'],
        'Australia' => ['name' => 'Australia', 'code' => 'AU', 'currency' => 'AUD', 'phone_prefix' => '+61', 'tax_label' => 'ABN / ACN'],
        'Canada' => ['name' => 'Canada', 'code' => 'CA', 'currency' => 'CAD', 'phone_prefix' => '+1', 'tax_label' => 'BN / GST/HST No'],
        'Qatar' => ['name' => 'Qatar', 'code' => 'QA', 'currency' => 'QAR', 'phone_prefix' => '+974', 'tax_label' => 'TIN / Tax Card'],
        'Oman' => ['name' => 'Oman', 'code' => 'OM', 'currency' => 'OMR', 'phone_prefix' => '+968', 'tax_label' => 'VAT / Tax Card'],
        'Kuwait' => ['name' => 'Kuwait', 'code' => 'KW', 'currency' => 'KWD', 'phone_prefix' => '+965', 'tax_label' => 'Tax ID / Commercial Reg'],
        'Bahrain' => ['name' => 'Bahrain', 'code' => 'BH', 'currency' => 'BHD', 'phone_prefix' => '+973', 'tax_label' => 'VAT Account No'],
        'Poland' => ['name' => 'Poland', 'code' => 'PL', 'currency' => 'PLN', 'phone_prefix' => '+48', 'tax_label' => 'NIP / VAT'],
        'Germany' => ['name' => 'Germany', 'code' => 'DE', 'currency' => 'EUR', 'phone_prefix' => '+49', 'tax_label' => 'USt-IdNr.'],
        'France' => ['name' => 'France', 'code' => 'FR', 'currency' => 'EUR', 'phone_prefix' => '+33', 'tax_label' => 'N° TVA'],
        'Malaysia' => ['name' => 'Malaysia', 'code' => 'MY', 'currency' => 'MYR', 'phone_prefix' => '+60', 'tax_label' => 'SST / BRN'],
        'South Africa' => ['name' => 'South Africa', 'code' => 'ZA', 'currency' => 'ZAR', 'phone_prefix' => '+27', 'tax_label' => 'VAT No'],
        'New Zealand' => ['name' => 'New Zealand', 'code' => 'NZ', 'currency' => 'NZD', 'phone_prefix' => '+64', 'tax_label' => 'GST No'],
        'Sri Lanka' => ['name' => 'Sri Lanka', 'code' => 'LK', 'currency' => 'LKR', 'phone_prefix' => '+94', 'tax_label' => 'TIN / VAT'],
        'Nepal' => ['name' => 'Nepal', 'code' => 'NP', 'currency' => 'NPR', 'phone_prefix' => '+977', 'tax_label' => 'PAN / VAT'],
        'Bangladesh' => ['name' => 'Bangladesh', 'code' => 'BD', 'currency' => 'BDT', 'phone_prefix' => '+880', 'tax_label' => 'BIN / TIN'],
        'Indonesia' => ['name' => 'Indonesia', 'code' => 'ID', 'currency' => 'IDR', 'phone_prefix' => '+62', 'tax_label' => 'NPWP'],
        'Philippines' => ['name' => 'Philippines', 'code' => 'PH', 'currency' => 'PHP', 'phone_prefix' => '+63', 'tax_label' => 'TIN'],
        'Netherlands' => ['name' => 'Netherlands', 'code' => 'NL', 'currency' => 'EUR', 'phone_prefix' => '+31', 'tax_label' => 'Btw-id'],
        'Switzerland' => ['name' => 'Switzerland', 'code' => 'CH', 'currency' => 'CHF', 'phone_prefix' => '+41', 'tax_label' => 'UID / MWST'],
        'Italy' => ['name' => 'Italy', 'code' => 'IT', 'currency' => 'EUR', 'phone_prefix' => '+39', 'tax_label' => 'Partita IVA'],
        'Spain' => ['name' => 'Spain', 'code' => 'ES', 'currency' => 'EUR', 'phone_prefix' => '+34', 'tax_label' => 'NIF / CIF'],
        'Other / International' => ['name' => 'Other / International', 'code' => 'XX', 'currency' => 'USD', 'phone_prefix' => '+', 'tax_label' => 'TAX ID / TRN']
    ];
}

function getCurrenciesList() {
    return [
        'INR' => ['code' => 'INR', 'symbol' => '₹',    'name' => 'Indian Rupee (INR)',       'major' => 'Rupees',            'minor' => 'Paise',   'scale' => 'indian'],
        'USD' => ['code' => 'USD', 'symbol' => '$',    'name' => 'US Dollar (USD)',          'major' => 'US Dollars',        'minor' => 'Cents',   'scale' => 'intl'],
        'AED' => ['code' => 'AED', 'symbol' => 'AED ', 'name' => 'UAE Dirham (AED)',         'major' => 'UAE Dirhams',       'minor' => 'Fils',    'scale' => 'intl'],
        'EUR' => ['code' => 'EUR', 'symbol' => '€',    'name' => 'Euro (EUR)',               'major' => 'Euros',             'minor' => 'Cents',   'scale' => 'intl'],
        'GBP' => ['code' => 'GBP', 'symbol' => '£',    'name' => 'British Pound (GBP)',      'major' => 'Pounds Sterling',   'minor' => 'Pence',   'scale' => 'intl'],
        'SAR' => ['code' => 'SAR', 'symbol' => 'SAR ', 'name' => 'Saudi Riyal (SAR)',        'major' => 'Saudi Riyals',      'minor' => 'Halalas', 'scale' => 'intl'],
        'AUD' => ['code' => 'AUD', 'symbol' => 'A$',   'name' => 'Australian Dollar (AUD)',  'major' => 'Australian Dollars','minor' => 'Cents',   'scale' => 'intl'],
        'CAD' => ['code' => 'CAD', 'symbol' => 'C$',   'name' => 'Canadian Dollar (CAD)',    'major' => 'Canadian Dollars',  'minor' => 'Cents',   'scale' => 'intl'],
        'SGD' => ['code' => 'SGD', 'symbol' => 'S$',   'name' => 'Singapore Dollar (SGD)',   'major' => 'Singapore Dollars', 'minor' => 'Cents',   'scale' => 'intl'],
        'QAR' => ['code' => 'QAR', 'symbol' => 'QAR ', 'name' => 'Qatari Riyal (QAR)',       'major' => 'Qatari Riyals',     'minor' => 'Dirhams', 'scale' => 'intl'],
        'OMR' => ['code' => 'OMR', 'symbol' => 'OMR ', 'name' => 'Omani Rial (OMR)',         'major' => 'Omani Rials',       'minor' => 'Baisa',   'scale' => 'intl'],
        'KWD' => ['code' => 'KWD', 'symbol' => 'KWD ', 'name' => 'Kuwaiti Dinar (KWD)',      'major' => 'Kuwaiti Dinars',    'minor' => 'Fils',    'scale' => 'intl'],
        'BHD' => ['code' => 'BHD', 'symbol' => 'BHD ', 'name' => 'Bahraini Dinar (BHD)',     'major' => 'Bahraini Dinars',   'minor' => 'Fils',    'scale' => 'intl'],
        'NZD' => ['code' => 'NZD', 'symbol' => 'NZ$',  'name' => 'New Zealand Dollar (NZD)', 'major' => 'New Zealand Dollars','minor' => 'Cents',   'scale' => 'intl'],
        'ZAR' => ['code' => 'ZAR', 'symbol' => 'R ',   'name' => 'South African Rand (ZAR)', 'major' => 'South African Rands','minor' => 'Cents',   'scale' => 'intl'],
        'MYR' => ['code' => 'MYR', 'symbol' => 'RM ',  'name' => 'Malaysian Ringgit (MYR)',  'major' => 'Malaysian Ringgits','minor' => 'Sen',     'scale' => 'intl'],
    ];
}

function getCurrencySymbol($curr = 'INR') {
    $list = getCurrenciesList();
    return $list[$curr]['symbol'] ?? '₹';
}

function getCurrencyInfo($curr = 'INR') {
    $list = getCurrenciesList();
    return $list[$curr] ?? $list['INR'];
}

function fmtCurrency($amount, $curr = 'INR') {
    return getCurrencySymbol($curr) . fmtAmount($amount);
}

function intToWordsIntl($n) {
    if ($n <= 0) return '';
    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
             'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
             'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    if ($n < 20) return $ones[$n];
    if ($n < 100) return $tens[(int)($n / 10)] . ($n % 10 ? ' ' . $ones[$n % 10] : '');
    if ($n < 1000) return $ones[(int)($n / 100)] . ' Hundred' . ($n % 100 ? ' ' . intToWordsIntl($n % 100) : '');
    if ($n < 1000000) return intToWordsIntl((int)($n / 1000)) . ' Thousand' . ($n % 1000 ? ' ' . intToWordsIntl($n % 1000) : '');
    if ($n < 1000000000) return intToWordsIntl((int)($n / 1000000)) . ' Million' . ($n % 1000000 ? ' ' . intToWordsIntl($n % 1000000) : '');
    return intToWordsIntl((int)($n / 1000000000)) . ' Billion' . ($n % 1000000000 ? ' ' . intToWordsIntl($n % 1000000000) : '');
}

function numberToWords($amount, $curr = 'INR') {
    $amount  = round((float)$amount, 2);
    $integer = (int)$amount;
    $decimal = round(($amount - $integer) * 100);
    $cinfo   = getCurrencyInfo($curr);
    $isIndian = ($cinfo['scale'] ?? 'indian') === 'indian';

    $words = $integer === 0 ? 'Zero' : ($isIndian ? intToWords($integer) : intToWordsIntl($integer));
    $result = $cinfo['major'] . ' ' . $words;
    if ($decimal > 0) {
        $decWords = $isIndian ? intToWords($decimal) : intToWordsIntl($decimal);
        $result .= ' and ' . $decWords . ' ' . $cinfo['minor'];
    }
    return $result . ' Only';
}

function intToWords($n) {
    if ($n <= 0) return '';
    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
             'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
             'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    if ($n < 20) return $ones[$n];
    if ($n < 100) return $tens[(int)($n / 10)] . ($n % 10 ? ' ' . $ones[$n % 10] : '');
    if ($n < 1000) return $ones[(int)($n / 100)] . ' Hundred' . ($n % 100 ? ' ' . intToWords($n % 100) : '');
    if ($n < 100000) return intToWords((int)($n / 1000)) . ' Thousand' . ($n % 1000 ? ' ' . intToWords($n % 1000) : '');
    if ($n < 10000000) return intToWords((int)($n / 100000)) . ' Lakh' . ($n % 100000 ? ' ' . intToWords($n % 100000) : '');
    return intToWords((int)($n / 10000000)) . ' Crore' . ($n % 10000000 ? ' ' . intToWords($n % 10000000) : '');
}

// ─── Document CRUD ────────────────────────────────────────────────────────────

function getAllDocuments() {
    return loadData('documents');
}

function getDocument($id) {
    $docs = loadData('documents');
    foreach ($docs as $doc) {
        if ($doc['id'] === $id) return $doc;
    }
    return null;
}

function saveDocument($doc) {

    // Synchronize status and payment_status
    if (($doc['payment_status'] ?? '') === 'paid') {
        $doc['status'] = 'paid';
        $doc['advance_amount'] = (float)($doc['grand_total'] ?? $doc['total'] ?? 0);
        $doc['balance_amount'] = 0.0;
    } elseif (($doc['status'] ?? '') === 'paid') {
        $doc['payment_status'] = 'paid';
        $doc['advance_amount'] = (float)($doc['grand_total'] ?? $doc['total'] ?? 0);
        $doc['balance_amount'] = 0.0;
    }

    // Ensure invoice/bill month and document date are always in the same month
    if (!empty($doc['id']) && in_array($doc['type'] ?? '', ['tax_invoice', 'bill'])) {
        if (preg_match('/^[A-Z]+-(\d{4})(\d{2})-\d+$/i', $doc['id'], $m)) {
            $idYm = $m[1] . '-' . $m[2];
            $curDocDate = $doc['date'] ?? '';
            $dateYm = substr($curDocDate, 0, 7);
            if ($dateYm !== $idYm) {
                $doc['date'] = ($idYm === date('Y-m')) ? date('Y-m-d') : ($idYm . '-01');
            }
        }
    }

    $docs = loadData('documents');

    // ANTI-OVERWRITE COLLISION GUARD:
    // If saving a document with an ID that already exists, verify it is indeed the same client/document.
    // If an ID collision exists with a DIFFERENT customer, reassign a new unique document number!
    $existingIndex = -1;
    foreach ($docs as $idx => $d) {
        if ($d['id'] === $doc['id']) {
            $existingIndex = $idx;
            break;
        }
    }

    if ($existingIndex !== -1) {
        $existingDoc = $docs[$existingIndex];
        $existingCust = trim(strtolower($existingDoc['bill_to']['name'] ?? ''));
        $newCust = trim(strtolower($doc['bill_to']['name'] ?? ''));

        // If the ID matches, but the customer name is COMPLETELY different and existing doc was created earlier:
        // Do NOT overwrite the old customer! Auto-assign a new unique document number to protect both!
        if ($existingCust !== '' && $newCust !== '' && $existingCust !== $newCust &&
            abs(strtotime($existingDoc['created_at'] ?? '2000-01-01') - strtotime($doc['created_at'] ?? '2000-01-01')) > 300) {
            $newId = generateDocNumber($doc['type'] ?? 'estimation');
            error_log("ANTI-COLLISION SAFEGUARD: Document ID collision avoided for {$doc['id']} ('{$newCust}' vs existing '{$existingCust}'). Assigned new ID: {$newId}");
            $doc['id'] = $newId;
            $existingIndex = -1; // Treat as brand new document!
        }
    }

    // ANTI-WIPE SAFEGUARD: If existing document has items, never overwrite with empty items / zero total
    if ($existingIndex !== -1 && !empty($docs[$existingIndex]['items']) && count($docs[$existingIndex]['items']) > 0) {
        $incomingDescs = array_filter(array_column($doc['items'] ?? [], 'description'), fn($x) => trim((string)$x) !== '');
        if (empty($incomingDescs) && (float)($doc['grand_total'] ?? 0) <= 0) {
            $ex = $docs[$existingIndex];
            error_log("ANTI-WIPE SAFEGUARD: Prevented overwriting " . $doc['id'] . " items with empty payload. Preserving existing " . count($ex['items']) . " items.");
            $doc['items']               = $ex['items'];
            $doc['subtotal']            = $ex['subtotal'];
            $doc['discount_type']       = $ex['discount_type'] ?? 'percent';
            $doc['discount_percent']    = $ex['discount_percent'] ?? 0;
            $doc['discount_flat']       = $ex['discount_flat'] ?? 0;
            $doc['discount_amount']     = $ex['discount_amount'] ?? 0;
            $doc['taxable_value']       = $ex['taxable_value'];
            $doc['is_gst']              = $ex['is_gst'] ?? true;
            $doc['gst_mode']            = $ex['gst_mode'] ?? 'cgst_sgst';
            $doc['cgst_rate']           = $ex['cgst_rate'] ?? 0;
            $doc['cgst_amount']         = $ex['cgst_amount'] ?? 0;
            $doc['sgst_rate']           = $ex['sgst_rate'] ?? 0;
            $doc['sgst_amount']         = $ex['sgst_amount'] ?? 0;
            $doc['igst_rate']           = $ex['igst_rate'] ?? 0;
            $doc['igst_amount']         = $ex['igst_amount'] ?? 0;
            $doc['gst_breakdown']       = $ex['gst_breakdown'] ?? [];
            $doc['grand_total']         = $ex['grand_total'];
            $doc['amount_in_words']     = $ex['amount_in_words'];
            if (!empty($ex['estimation_subtype'])) {
                $doc['estimation_subtype'] = $ex['estimation_subtype'];
            }
            if (!empty($ex['turnkey_config'])) {
                $doc['turnkey_config']     = $ex['turnkey_config'];
            }
            if (!empty($ex['blocks'])) {
                $doc['blocks']             = $ex['blocks'];
            }
            if (!empty($ex['scope_items'])) {
                $doc['scope_items']        = $ex['scope_items'];
            }
        }
    }

    if ($existingIndex !== -1) {
        $docs[$existingIndex] = $doc;
    } else {
        array_unshift($docs, $doc);
    }

    saveData('documents', $docs);

    // Auto-sync shipping address to customer master record permanently
    $docShipAddr = trim($doc['ship_to']['address'] ?? '');
    $docCustName = trim($doc['bill_to']['name'] ?? '');
    if ($docShipAddr !== '' && $docCustName !== '') {
        $allCust = loadData('customers');
        $cUpdated = false;
        foreach ($allCust as &$c) {
            if ((!empty($doc['customer_id']) && ($c['id'] ?? '') === $doc['customer_id']) ||
                strcasecmp(trim($c['name'] ?? ''), $docCustName) === 0) {
                $c['shipping_name'] = trim($doc['ship_to']['name'] ?? '') ?: ($c['shipping_name'] ?? $c['name']);
                $c['shipping_address'] = $docShipAddr;
                if (!empty($doc['ship_to']['phone'])) $c['shipping_phone'] = trim($doc['ship_to']['phone']);
                if (!empty($doc['ship_to']['state'])) $c['shipping_state'] = trim($doc['ship_to']['state']);
                $cUpdated = true;
                break;
            }
        }
        unset($c);
        if ($cUpdated) {
            saveData('customers', $allCust);
        }
    }

    // Auto-sync to Central GCM Enterprise CRM
    try {
        $ch = curl_init('http://localhost:3020/api/crm/sync-doc');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($doc));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 400);
        curl_exec($ch);
        curl_close($ch);
    } catch (\Throwable $t) {
        // Continue silently on sync timeout
    }
}

function deleteDocument($id) {
    $doc = getDocument($id);
    if ($doc) {
        markDocumentDeleted($id, $doc);
    }
    $docs = loadData('documents');
    $docs = array_values(array_filter($docs, fn($d) => $d['id'] !== $id));
    saveData('documents', $docs);

    // Also remove directly from vault so loadData doesn't re-merge it
    $vPath = vaultPath('documents');
    if (file_exists($vPath)) {
        $vDocs = json_decode(file_get_contents($vPath), true) ?: [];
        $vDocs = array_values(array_filter($vDocs, fn($d) => $d['id'] !== $id));
        @file_put_contents($vPath, json_encode($vDocs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }
}

function getOfficialGstUqcs() {
    return [
        ['code' => 'SQF', 'label' => 'SQF-SQUARE FEET', 'aliases' => ['SQFT', 'SFT', 'SQ.FT', 'SQ FT', 'SQ. FT', 'SQUARE FEET', 'SQF']],
        ['code' => 'MTR', 'label' => 'MTR-METERS', 'aliases' => ['MTR', 'MTRS', 'METER', 'METERS', 'RFT', 'RUNNING FEET', 'RUNNING FOOT', 'R.FT', 'R. FT']],
        ['code' => 'NOS', 'label' => 'NOS-NUMBERS', 'aliases' => ['NOS', 'NO', 'NUMBERS', 'NUMBER', 'PCS', 'PIECES', 'PIECE', 'EA', 'EACH']],
        ['code' => 'SET', 'label' => 'SET-SETS', 'aliases' => ['SET', 'SETS']],
        ['code' => 'KGS', 'label' => 'KGS-KILOGRAMS', 'aliases' => ['KG', 'KGS', 'KILOGRAM', 'KILOGRAMS', 'KILO', 'KILOS']],
        ['code' => 'LTR', 'label' => 'LTR-LITRES', 'aliases' => ['LTR', 'LTRS', 'LITER', 'LITERS', 'LITRE', 'LITRES', 'L']],
        ['code' => 'ROL', 'label' => 'ROL-ROLLS', 'aliases' => ['ROL', 'ROLL', 'ROLLS']],
        ['code' => 'BOX', 'label' => 'BOX-BOX', 'aliases' => ['BOX', 'BOXES', 'BX']],
        ['code' => 'BAG', 'label' => 'BAG-BAGS', 'aliases' => ['BAG', 'BAGS']],
        ['code' => 'BDL', 'label' => 'BDL-BUNDLES', 'aliases' => ['BDL', 'BUNDLE', 'BUNDLES']],
        ['code' => 'SQM', 'label' => 'SQM-SQUARE METERS', 'aliases' => ['SQM', 'SQMT', 'SQ.MT', 'SQUARE METERS', 'SQ METERS', 'SQ METER']],
        ['code' => 'SQY', 'label' => 'SQY-SQUARE YARDS', 'aliases' => ['SQY', 'SQYD', 'SQ.YD', 'SQUARE YARDS', 'SQ YARDS', 'SQ YARD']],
        ['code' => 'TON', 'label' => 'TON-TONNES', 'aliases' => ['TON', 'TONS', 'TONNES', 'TONNE']],
        ['code' => 'MTS', 'label' => 'MTS-METRIC TON', 'aliases' => ['MTS', 'METRIC TON', 'MT']],
        ['code' => 'PAC', 'label' => 'PAC-PACKS', 'aliases' => ['PAC', 'PACK', 'PACKS', 'PKT', 'PACKET', 'PACKETS']],
        ['code' => 'PCS', 'label' => 'PCS-PIECES', 'aliases' => ['PCS', 'PIECE', 'PIECES']],
        ['code' => 'PRS', 'label' => 'PRS-PAIRS', 'aliases' => ['PRS', 'PAIR', 'PAIRS']],
        ['code' => 'CBM', 'label' => 'CBM-CUBIC METERS', 'aliases' => ['CBM', 'CUBIC METERS', 'CUBIC METER']],
        ['code' => 'CMS', 'label' => 'CMS-CENTIMETERS', 'aliases' => ['CMS', 'CENTIMETERS', 'CENTIMETER', 'CM']],
        ['code' => 'DOZ', 'label' => 'DOZ-DOZENS', 'aliases' => ['DOZ', 'DOZEN', 'DOZENS']],
        ['code' => 'DRM', 'label' => 'DRM-DRUMS', 'aliases' => ['DRM', 'DRUM', 'DRUMS']],
        ['code' => 'CAN', 'label' => 'CAN-CANS', 'aliases' => ['CAN', 'CANS']],
        ['code' => 'TUB', 'label' => 'TUB-TUBES', 'aliases' => ['TUB', 'TUBE', 'TUBES']],
        ['code' => 'QTL', 'label' => 'QTL-QUINTAL', 'aliases' => ['QTL', 'QUINTAL', 'QUINTALS']],
        ['code' => 'UNT', 'label' => 'UNT-UNITS', 'aliases' => ['UNT', 'UNIT', 'UNITS']],
        ['code' => 'BAL', 'label' => 'BAL-BALE', 'aliases' => ['BAL', 'BALE', 'BALES']],
        ['code' => 'BKL', 'label' => 'BKL-BUCKLES', 'aliases' => ['BKL', 'BUCKLE', 'BUCKLES']],
        ['code' => 'BOU', 'label' => 'BOU-BILLION OF UNITS', 'aliases' => ['BOU']],
        ['code' => 'BTL', 'label' => 'BTL-BOTTLES', 'aliases' => ['BTL', 'BOTTLE', 'BOTTLES']],
        ['code' => 'BUN', 'label' => 'BUN-BUNCHES', 'aliases' => ['BUN', 'BUNCH', 'BUNCHES']],
        ['code' => 'CCM', 'label' => 'CCM-CUBIC CENTIMETERS', 'aliases' => ['CCM']],
        ['code' => 'CTN', 'label' => 'CTN-CARTONS', 'aliases' => ['CTN', 'CARTON', 'CARTONS']],
        ['code' => 'GGK', 'label' => 'GGK-GREAT GROSS', 'aliases' => ['GGK']],
        ['code' => 'GMS', 'label' => 'GMS-GRAMMES', 'aliases' => ['GMS', 'GM', 'GRAM', 'GRAMS', 'GRAMMES']],
        ['code' => 'GRS', 'label' => 'GRS-GROSS', 'aliases' => ['GRS', 'GROSS']],
        ['code' => 'GYD', 'label' => 'GYD-GROSS YARDS', 'aliases' => ['GYD']],
        ['code' => 'KLR', 'label' => 'KLR-KILOLITRE', 'aliases' => ['KLR', 'KILOLITRE', 'KILOLITER']],
        ['code' => 'KME', 'label' => 'KME-KILOMETRE', 'aliases' => ['KME', 'KILOMETRE', 'KILOMETER', 'KM']],
        ['code' => 'MLT', 'label' => 'MLT-MILILITRE', 'aliases' => ['MLT', 'MILLILITRE', 'MILLILITER', 'ML']],
        ['code' => 'TBS', 'label' => 'TBS-TABLETS', 'aliases' => ['TBS', 'TABLET', 'TABLETS']],
        ['code' => 'TGM', 'label' => 'TGM-TEN GROSS', 'aliases' => ['TGM']],
        ['code' => 'THD', 'label' => 'THD-THOUSANDS', 'aliases' => ['THD', 'THOUSAND', 'THOUSANDS']],
        ['code' => 'UGS', 'label' => 'UGS-US GALLONS', 'aliases' => ['UGS', 'GALLON', 'GALLONS']],
        ['code' => 'YDS', 'label' => 'YDS-YARDS', 'aliases' => ['YDS', 'YARD', 'YARDS']],
        ['code' => 'OTH', 'label' => 'OTH-OTHERS', 'aliases' => ['OTH', 'OTHERS', 'OTHER', 'LOT', 'LOTS', 'JOB', 'LS', 'LUMPSUM']]
    ];
}

function normalizeUqcUnit($unit) {
    $u = strtoupper(trim((string)$unit));
    if ($u === '') return 'SQF-SQUARE FEET';
    foreach (getOfficialGstUqcs() as $entry) {
        if ($u === $entry['label'] || $u === $entry['code']) {
            return $entry['label'];
        }
        if (in_array($u, $entry['aliases'])) {
            return $entry['label'];
        }
        if (strpos($u, $entry['code'] . '-') === 0) {
            return $entry['label'];
        }
    }
    return 'OTH-OTHERS';
}

function getUqcCodeOnly($unit) {
    $normalized = normalizeUqcUnit($unit);
    $parts = explode('-', $normalized, 2);
    return $parts[0] ?: 'OTH';
}

function getOfficialHsnDescription($hsn) {
    $prefix = substr(trim((string)$hsn), 0, 4);
    $map = [
        '5608' => 'KNOTTED NETTING OF TWINE, CORDAGE OR ROPE; MADE UP FISHING NETS AND OTHER MADE UP NETS, OF TEXTILE MATERIALS',
        '5703' => 'CARPETS AND OTHER TEXTILE FLOOR COVERINGS, TUFTED',
        '3506' => 'PREPARED GLUES AND OTHER PREPARED ADHESIVES, NOT ELSEWHERE SPECIFIED OR INCLUDED',
        '7308' => 'STRUCTURES AND PARTS OF STRUCTURES OF IRON OR STEEL (COLUMNS, TRUSSES, SHEDS)',
        '7312' => 'STRANDED WIRE, ROPES, CABLES OF IRON OR STEEL (INVISIBLE GRILL WIRE CABLE)',
        '7610' => 'ALUMINIUM STRUCTURES AND PARTS THEREOF (INVISIBLE GRILL TRACKS & PROFILES)',
        '7323' => 'TABLE, KITCHEN OR OTHER HOUSEHOLD ARTICLES OF IRON OR STEEL (CLOTH DRYING HANGERS)',
        '7210' => 'FLAT-ROLLED PRODUCTS OF IRON OR NON-ALLOY STEEL, PLATED OR COATED WITH ZINC (GI SHEETS)',
        '2505' => 'NATURAL SANDS OF ALL KINDS, WHETHER OR NOT COLOURED (SILICA SAND INFILL)',
        '4004' => 'WASTE, PARINGS AND SCRAP OF RUBBER (SBR INFILL GRANULES)',
        '9405' => 'LUMINAIRES AND LIGHTING FITTINGS (LED FLOOD LIGHTS)',
        '8537' => 'BOARDS, PANELS, CONSOLES FOR ELECTRIC CONTROL (ELECTRICAL PANEL & CABLING)',
        '9506' => 'ARTICLES AND EQUIPMENT FOR GENERAL PHYSICAL CULTURE, GYMNASTICS OR ATHLETICS',
        '9954' => 'GENERAL CONSTRUCTION SERVICES / BUILDING INSTALLATION AND COMPLETION SERVICES',
        '9965' => 'GOODS TRANSPORT SERVICES (FREIGHT & LOGISTICS)',
        '9983' => 'OTHER PROFESSIONAL, TECHNICAL AND BUSINESS SERVICES (ENGINEERING & SUPERVISION)',
        '6810' => 'ARTICLES OF CEMENT, OF CONCRETE OR OF ARTIFICIAL STONE (PAVER BLOCKS)',
        '3918' => 'FLOOR COVERINGS OF PLASTICS (SYNTHETIC / VINYL FLOORING)',
        '3208' => 'PAINTS AND VARNISHES (ACRYLIC COURT COATING)'
    ];
    return $map[$prefix] ?? 'OTHER OUTWARD SUPPLIES';
}

function resolveItemHsn($desc, $existingHsn = '') {
    $h = trim((string)$existingHsn);
    $d = strtolower((string)$desc);

    // Normalize legacy / invalid codes
    if (in_array($h, ['5', '6702'])) {
        return (preg_match('/(glue|adhesive|speb)/i', $d)) ? '3506' : '5703';
    }

    // 1. Installation, Civil, Preparation, Erection, Paving, Flooring Works (SAC 9954)
    // HDPE net installation, invisible grill installation, cloth hangers installation,
    // artificial grass installation, cricket pitch turf installation, gi sheets installation,
    // metal structure installation, civil base preparation, asphalt base installation,
    // synthetic grass installation, acrylic flooring installation, cc flooring installation, etc.
    if (preg_match('/(installation|fixing|erection|fitting|laying|civil|preparation|preperation|excavation|sub-?base|subbase|wmm|gsb|saucer\s*drain|drainage|asphalt|bitumin|acrylic\s*floor|cc\s*floor|concrete\s*base|concrete\s*floor|works\s*contract|labour|labor|rigging)/i', $d)) {
        return '9954';
    }

    // If existing valid HSN is given and matches 4-8 digits, preserve it
    if ($h !== '' && $h !== '—' && $h !== '-' && preg_match('/^[0-9]{4,8}$/', $h)) {
        return $h;
    }

    // 2. Freight & Logistics / Transport (SAC 9965)
    if (preg_match('/(freight|transport|logistics|carriage|lr\b|delivery\s*charge)/i', $d)) {
        return '9965';
    }

    // 3. Engineering & Supervision (SAC 9983)
    if (preg_match('/(engineering|consult|supervision|design|inspection|project\s*mgmt)/i', $d)) {
        return '9983';
    }

    // 4. Glues, Adhesives & PU Compound (HSN 3506)
    if (preg_match('/(glue|adhesive|speb|pu\s*compound|bonding\s*agent|resin)/i', $d)) {
        return '3506';
    }

    // 5. Artificial Grass / Synthetic Grass / Turf Carpet (HSN 5703)
    if (preg_match('/(artificial\s*grass|synthetic\s*grass|grass|turf|carpet|lawn|matting)/i', $d)) {
        return '5703';
    }

    // 6. HDPE Netting, Nylon Netting, Sports Net, Safety Net, Bird Net (HSN 5608)
    if (preg_match('/(net|netting|mesh|twine|cordage|rope|russea|braided|twisted|knotted)/i', $d)) {
        return '5608';
    }

    // 7. Invisible Grill Materials - Wire Rope (HSN 7312) / Aluminium Tracks (HSN 7610)
    if (preg_match('/(invisible\s*grill|wire\s*rope|ss\s*wire|stainless\s*cable|safety\s*cable)/i', $d)) {
        return '7312';
    }
    if (preg_match('/(aluminium\s*track|aluminum\s*profile|alum\s*track|insect\s*mesh)/i', $d)) {
        return '7610';
    }

    // 8. Cloth Drying Hangers / Ceiling Pulley System (HSN 7323)
    if (preg_match('/(cloth\s*hanger|drying\s*hanger|ceiling\s*pulley|clothes\s*dryer)/i', $d)) {
        return '7323';
    }

    // 9. GI Sheets / PPGL Roofing Sheets / Profile Sheets (HSN 7210)
    if (preg_match('/(gi\s*sheet|ppgl|galvalume|corrugated\s*sheet|roofing\s*sheet|profile\s*sheet|cladding\s*sheet)/i', $d)) {
        return '7210';
    }

    // 10. Metal Structures, Steel Columns, Purlins, Trusses, Pipes, Beams (HSN 7308)
    if (preg_match('/(metal\s*structure|steel\s*structure|structure|pipe|pole|column|purlin|truss|beam|channel|shed|framework|shs|rhs|ms\s*pipe|gi\s*pipe|gate|door)/i', $d)) {
        return '7308';
    }

    // 11. Silica Sand Infill (HSN 2505)
    if (preg_match('/(sand|silica|infill\s*sand)/i', $d)) {
        return '2505';
    }

    // 12. SBR Rubber Granules Infill (HSN 4004)
    if (preg_match('/(rubber|granule|sbr|epdm)/i', $d)) {
        return '4004';
    }

    // 13. LED Floodlights & Stadium Luminaires (HSN 9405)
    if (preg_match('/(light|flood\s*light|floodlight|lamp|luminaire|fixture|lux)/i', $d)) {
        return '9405';
    }

    // 14. Electrical Panel, Cabling, DB, Switchgear (HSN 8537)
    if (preg_match('/(cable|panel|switch|db|earthing|electric|wiring)/i', $d)) {
        return '8537';
    }

    // 15. Sports Equipment / Posts / Rings / Bats (HSN 9506)
    if (preg_match('/(goal|marking|pad|stump|crease|wicket|bat|ball|post|ring|rim|backboard|accessory|accessories)/i', $d)) {
        return '9506';
    }

    // 16. Paver Blocks / Concrete Products (HSN 6810)
    if (preg_match('/(paver|interlocking\s*blocks)/i', $d)) {
        return '6810';
    }

    return '5608';
}

function resolveItemGstRate($desc, $hsn, $existingGstRate = null) {
    $existing = ($existingGstRate !== null && $existingGstRate !== '') ? (float)$existingGstRate : null;
    $h = substr(trim((string)$hsn), 0, 4);
    $d = strtolower((string)$desc);

    // 1. Installation, Civil Works Contract, or Service (SAC 9954) -> 18% GST mandatory
    if (preg_match('/(installation|fixing|erection|fitting|laying|civil|preparation|preperation|excavation|sub-?base|subbase|asphalt|acrylic\s*floor|cc\s*floor|works\s*contract|labour|labor)/i', $d) || $h === '9954') {
        // If pure material supply of turf/carpet (5703) or net (5608) without installation words, keep 5%
        if (in_array($h, ['5703', '5608']) && !preg_match('/(installation|laying|fixing|fitting|erection|labour|labor)/i', $d)) {
            return 5.0;
        }
        return 18.0;
    }

    // 2. Glues & Adhesives (HSN 3506) -> 18% GST mandatory as per official GST portal
    if ($h === '3506' || (preg_match('/(glue|adhesive|speb)/i', $d) && !preg_match('/(grass|turf|carpet)/i', $d))) {
        return 18.0;
    }

    // 3. Rubber granules (HSN 4004) -> 18% GST mandatory
    if ($h === '4004' || (preg_match('/(rubber\s*granule|sbr\s*granule|rubber\s*infill)/i', $d) && !preg_match('/(grass|turf|carpet)/i', $d))) {
        return 18.0;
    }

    // If explicitly provided and > 0 (and not a forced service/glue/rubber), keep user-defined rate
    if ($existing !== null && $existing > 0) {
        return $existing;
    }

    // Material supply GST rates:
    if (in_array($h, ['5608', '5703', '2505', '9965', '6005'])) {
        return 5.0; // Net supply, Grass supply, Silica sand, GTA freight = 5%
    }
    if (in_array($h, ['7308', '7210', '7312', '7610', '7323', '9405', '8537', '9506', '9983', '3918', '6810', '3208'])) {
        return 18.0; // Structures, Sheets, Invisible grills, Hangers, Lights, Electrical, Equipment = 18%
    }

    return 5.0;
}
function buildDocument($post, $id = null) {
    $type      = $post['type'] ?? 'estimation';
    // Strict prefix-to-type invariant
    if ($id) {
        if (strpos($id, 'EST-') === 0) {
            $type = 'estimation';
        } elseif (strpos($id, 'INV-') === 0 && $type === 'estimation') {
            $type = 'tax_invoice';
        } elseif (strpos($id, 'BILL-') === 0 && $type === 'estimation') {
            $type = 'bill';
        }
    }
    $companyId = $post['company_id'] ?? null;
    $company   = $companyId ? getCompany($companyId) : getDefaultCompany();
    if (!$company) $company = getDefaultCompany();

    $items = [];
    $cleanBlocks = [];
    $isMultiBlockPost = (($post['estimation_subtype'] ?? '') === 'multi_block');
    $rawBlocks = !empty($post['blocks_json']) ? $post['blocks_json'] : ($post['blocks'] ?? null);
    if (is_string($rawBlocks)) {
        $rawBlocks = json_decode($rawBlocks, true);
    }
    if (is_array($rawBlocks) && !empty($rawBlocks)) {
        foreach ($rawBlocks as $bIdx => $b) {
            $bTitle = trim($b['title'] ?? ('Block - ' . chr(65 + $bIdx)));
            $bItems = is_array($b['items'] ?? null) ? $b['items'] : [];
            $parsedBlockItems = [];
            $bSubtotal = 0;
            $bTax = 0;

            foreach ($bItems as $it) {
                $desc = trim($it['description'] ?? '');
                if ($desc === '') continue;
                $qty = (float)($it['qty'] ?? 0);
                $rate = (float)($it['rate'] ?? 0);
                $amount = round($qty * $rate, 2);
                $gstRate = isset($it['gst_rate']) ? (float)$it['gst_rate'] : 5.0;
                $itHsn = trim($it['hsn'] ?? '5608');

                $specs = [];
                if (!empty($it['specs'])) {
                    if (is_string($it['specs'])) {
                        $specs = array_values(array_filter(array_map('trim', explode("\n", $it['specs']))));
                    } elseif (is_array($it['specs'])) {
                        $specs = array_values(array_filter(array_map('trim', $it['specs'])));
                    }
                }

                $parsedItem = [
                    'description' => $desc,
                    'specs'       => $specs,
                    'hsn'         => $itHsn,
                    'qty'         => $qty,
                    'unit'        => strtoupper(trim($it['unit'] ?? 'SQFT')),
                    'rate'        => $rate,
                    'gst_rate'    => $gstRate,
                    'amount'      => $amount,
                ];
                $parsedBlockItems[] = $parsedItem;
                $bSubtotal += $amount;
                if ($gstRate > 0) {
                    $bTax += round($amount * ($gstRate / 100), 2);
                }
            }

            if (!empty($parsedBlockItems)) {
                $cleanBlocks[] = [
                    'id'          => $b['id'] ?? ('block_' . ($bIdx + 1)),
                    'title'       => $bTitle,
                    'items'       => $parsedBlockItems,
                    'subtotal'    => round($bSubtotal, 2),
                    'gst_rate'    => 5.0,
                    'gst_amount'  => round($bTax, 2),
                    'grand_total' => round($bSubtotal + $bTax, 2),
                ];
            }
        }
    }

    $itemDescs  = $post['item_desc']  ?? [];
    $itemHsns   = $post['item_hsn']   ?? [];
    $itemQtys   = $post['item_qty']   ?? [];
    $itemUnits  = $post['item_unit']  ?? [];
    $itemRates  = $post['item_rate']  ?? [];
    $itemSpecs    = $post['item_specs']    ?? [];
    $itemGstRates = $post['item_gst_rate'] ?? [];

    foreach ($itemDescs as $i => $desc) {
        $qty    = (float)($itemQtys[$i]  ?? 0);
        $rate   = (float)($itemRates[$i] ?? 0);
        if (trim($desc) === '') {
            if ($qty > 0 || $rate > 0) {
                $desc = "Safety Net / Material";
            } else {
                continue;
            }
        }
        $amount = round($qty * $rate, 2);

        $specs = [];
        $rawSpecs = $itemSpecs[$i] ?? [];
        if (is_string($rawSpecs)) $rawSpecs = array_filter(explode("\n", $rawSpecs));
        foreach ($rawSpecs as $spec) {
            $spec = trim($spec);
            if ($spec !== '') $specs[] = $spec;
        }

        $resolvedHsn = resolveItemHsn($desc, $itemHsns[$i] ?? '');
            $resolvedRate = resolveItemGstRate($desc, $resolvedHsn, $itemGstRates[$i] ?? null);
            $resolvedUnit = normalizeUqcUnit($itemUnits[$i] ?? 'SQF-SQUARE FEET');
            $items[] = [
                'description' => trim($desc),
                'hsn'         => $resolvedHsn,
                'qty'         => $qty,
                'unit'        => $resolvedUnit,
                'rate'        => $rate,
                'amount'      => $amount,
                'specs'       => $specs,
                'gst_rate'    => $resolvedRate,
            ];
    }

    if ($isMultiBlockPost && !empty($cleanBlocks)) {
        if (empty($items)) {
            foreach ($cleanBlocks as $b) {
                foreach ($b['items'] as $it) {
                    $items[] = $it;
                }
            }
        }
    }

    $subtotal        = array_sum(array_column($items, 'amount'));
    $discountType    = $post['discount_type'] ?? 'percent'; // 'percent' or 'amount'
    $discountPct     = (float)($post['discount_percent'] ?? 0);
    $discountFlat    = (float)($post['discount_flat'] ?? 0);
    if ($discountType === 'amount') {
        $discountAmt = round(min($discountFlat, $subtotal), 2);
        $discountPct = $subtotal > 0 ? round($discountAmt / $subtotal * 100, 4) : 0;
    } else {
        $discountAmt = round($subtotal * $discountPct / 100, 2);
        $discountFlat = $discountAmt;
    }
    $taxableValue    = round($subtotal - $discountAmt, 2);

    // is_gst: explicit flag sent from form; when false, force gst_mode='none'
    $isGst = isset($post['is_gst'])
        ? ($post['is_gst'] === '1' || $post['is_gst'] === true)
        : true; // default true for backward-compat

    $gstMode = $post['gst_mode'] ?? 'cgst_sgst';
    if (!$isGst) $gstMode = 'none';

    // Calculate GST breakdown directly from items with real rates & HSN mapping
    $gstBreakdown = [];
    $cgstAmt = 0.0;
    $sgstAmt = 0.0;
    $igstAmt = 0.0;

    if ($isGst && !empty($items)) {
        $slabs = [];
        $discFactor = ($subtotal > 0 && $discountAmt > 0) ? (1.0 - ($discountAmt / $subtotal)) : 1.0;

        foreach ($items as &$it) {
            $amt = (float)($it['amount'] ?? 0);
            $discAmt = round($amt * $discFactor, 2);

            // Determine GST rate:
            $rate = 18.0;
            if (isset($it['gst_rate']) && is_numeric($it['gst_rate']) && (float)$it['gst_rate'] > 0) {
                $rate = (float)$it['gst_rate'];
            } else {
                $hsn = trim($it['hsn'] ?? '');
                if (in_array($hsn, ['2505', '9965', '5608', '5703'])) {
                    $rate = 5.0;
                } elseif (in_array($hsn, ['9506'])) {
                    $rate = 12.0;
                } else {
                    $rate = 18.0;
                }
            }
            $it['gst_rate'] = $rate;

            $key = number_format($rate, 1);
            if (!isset($slabs[$key])) {
                $slabs[$key] = ['rate' => $rate, 'taxable' => 0.0];
            }
            $slabs[$key]['taxable'] += $discAmt;
        }
        unset($it);

        ksort($slabs);

        foreach ($slabs as $s) {
            $sRate    = (float)$s['rate'];
            $sTaxable = round((float)$s['taxable'], 2);
            if ($sTaxable <= 0 && $sRate <= 0) continue;

            if ($gstMode === 'cgst_sgst') {
                $halfRate = round($sRate / 2, 2);
                $halfAmt  = round($sTaxable * ($halfRate / 100), 2);
                $gstBreakdown[] = [
                    'rate'        => $sRate,
                    'taxable'     => $sTaxable,
                    'cgst_rate'   => $halfRate,
                    'cgst_amount' => $halfAmt,
                    'sgst_rate'   => $halfRate,
                    'sgst_amount' => $halfAmt,
                    'total_tax'   => round($halfAmt * 2, 2),
                ];
                $cgstAmt += $halfAmt;
                $sgstAmt += $halfAmt;
            } else { // igst
                $taxAmt = round($sTaxable * ($sRate / 100), 2);
                $gstBreakdown[] = [
                    'rate'        => $sRate,
                    'taxable'     => $sTaxable,
                    'igst_rate'   => $sRate,
                    'igst_amount' => $taxAmt,
                    'total_tax'   => $taxAmt,
                ];
                $igstAmt += $taxAmt;
            }
        }
    }

    $cgstRate = count($gstBreakdown) === 1 ? ($gstBreakdown[0]['cgst_rate'] ?? 0) : 0;
    $sgstRate = count($gstBreakdown) === 1 ? ($gstBreakdown[0]['sgst_rate'] ?? 0) : 0;
    $igstRate = count($gstBreakdown) === 1 ? ($gstBreakdown[0]['igst_rate'] ?? 0) : 0;
    $grandTotal = round($taxableValue + $cgstAmt + $sgstAmt + $igstAmt, 2);
    if ($isMultiBlockPost && !empty($cleanBlocks)) {
        $grandTotal = round(array_sum(array_column($cleanBlocks, 'grand_total')), 2);
    }

    $terms = [];
    if (!empty($post['terms'])) {
        foreach ((array)$post['terms'] as $t) {
            $t = trim($t);
            if ($t !== '') $terms[] = $t;
        }
    }

    // E-Way Bill & Transport Details
    $ewayBill = [
        'enabled'             => !empty($post['eway_enabled']) || !empty($post['eway_bill_no']),
        'eway_bill_no'        => preg_replace('/[^0-9]/', '', trim($post['eway_bill_no'] ?? '')),
        'eway_bill_date'      => trim($post['eway_bill_date'] ?? ''),
        'valid_upto'          => trim($post['eway_valid_upto'] ?? ''),
        'vehicle_no'          => strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', trim($post['vehicle_no'] ?? ''))),
        'transporter_name'    => trim($post['transporter_name'] ?? ''),
        'transporter_doc_no'  => trim($post['transporter_doc_no'] ?? ($post['lr_no'] ?? '')),
        'distance_km'         => !empty($post['distance_km']) ? (int)$post['distance_km'] : null,
    ];

    // Payment & Advance Status resolution
    $pStatus = $post['payment_status'] ?? (($post['status'] ?? '') === 'paid' ? 'paid' : 'unpaid');
    $advAmt  = isset($post['advance_amount']) ? (float)$post['advance_amount'] : 0.0;
    if ($pStatus === 'paid') {
        $advAmt = $grandTotal;
        $balDue = 0.0;
    } elseif ($pStatus === 'unpaid') {
        $advAmt = 0.0;
        $balDue = $grandTotal;
    } else { // partial
        $balDue = max(0.0, round($grandTotal - $advAmt, 2));
    }

    return [
        'id'               => $id ?? generateDocNumber($type),
        'type'             => $type,
        'date'             => $post['date'] ?? date('Y-m-d'),
        'customer_type'    => $post['customer_type'] ?? 'client',
        'sale_unit_mode'   => $post['sale_unit_mode']  ?? 'sqft',
        'bill_to'          => (function() use ($post, $id) {
            $name     = trim($post['bt_name']     ?? '');
            $address  = trim($post['bt_address']  ?? '');
            $gstin    = strtoupper(trim($post['bt_gstin'] ?? ''));
            $state    = trim($post['bt_state']    ?? '');
            $phone    = trim($post['bt_phone']    ?? '');
            $whatsapp = trim($post['bt_whatsapp'] ?? ($post['bt_phone'] ?? ''));

            // Fallback 1: If name is blank but customer_id is provided, fetch from customer master
            if ($name === '' && !empty($post['customer_id'])) {
                $cust = getCustomer($post['customer_id']);
                if ($cust) {
                    $name     = $name     ?: trim($cust['name'] ?? '');
                    $address  = $address  ?: trim($cust['address'] ?? '');
                    $gstin    = $gstin    ?: strtoupper(trim($cust['gstin'] ?? ''));
                    $state    = $state    ?: trim($cust['state'] ?? '');
                    $phone    = $phone    ?: trim($cust['phone'] ?? '');
                    $whatsapp = $whatsapp ?: trim($cust['whatsapp'] ?? ($cust['phone'] ?? ''));
                }
            }

            // Fallback 2: If name is still blank and updating an existing document, preserve existing bill_to
            if ($name === '' && $id) {
                $existingDoc = getDocument($id);
                if ($existingDoc && !empty($existingDoc['bill_to']['name'])) {
                    $name     = $name     ?: trim($existingDoc['bill_to']['name'] ?? '');
                    $address  = $address  ?: trim($existingDoc['bill_to']['address'] ?? '');
                    $gstin    = $gstin    ?: strtoupper(trim($existingDoc['bill_to']['gstin'] ?? ''));
                    $state    = $state    ?: trim($existingDoc['bill_to']['state'] ?? '');
                    $phone    = $phone    ?: trim($existingDoc['bill_to']['phone'] ?? '');
                    $whatsapp = $whatsapp ?: trim($existingDoc['bill_to']['whatsapp'] ?? ($existingDoc['bill_to']['phone'] ?? ''));
                }
            }

            return [
                'name'     => $name,
                'address'  => $address,
                'gstin'    => $gstin,
                'state'    => $state,
                'phone'    => $phone,
                'whatsapp' => $whatsapp,
                'country'  => trim($post['bt_country'] ?? 'India'),
            ];
        })(),
        'ship_to'          => (function() use ($post) {
            $stName    = trim($post['st_name']    ?? '');
            $stAddress = trim($post['st_address'] ?? '');
            $stPhone   = trim($post['st_phone']   ?? '');
            $stState   = trim($post['st_state']   ?? '');
            $showShip  = (!empty($post['show_shipping_address']) && $post['show_shipping_address'] !== '0') ? 1 : 0;

            // Smart fallback from customer master if shipping address is blank
            $cName = trim($post['bt_name'] ?? '');
            if ($stAddress === '' && $cName !== '') {
                $allCust = getAllCustomers();
                foreach ($allCust as $c) {
                    if ((!empty($post['customer_id']) && ($c['id'] ?? '') === $post['customer_id']) ||
                        strcasecmp(trim($c['name'] ?? ''), $cName) === 0) {
                        if (!empty($c['shipping_address'])) {
                            $stAddress = $c['shipping_address'];
                            $stName    = $stName ?: ($c['shipping_name'] ?? $c['name']);
                            $stPhone   = $stPhone ?: ($c['shipping_phone'] ?? $c['phone'] ?? '');
                            $stState   = $stState ?: ($c['shipping_state'] ?? $c['state'] ?? '');
                            $showShip  = 1;
                        }
                        break;
                    }
                }
            }

            return [
                'show_on_doc'  => $showShip,
                'same_as_bill' => !empty($post['ship_same_as_bill']) ? 1 : 0,
                'name'         => $stName,
                'address'      => $stAddress,
                'phone'        => $stPhone,
                'state'        => $stState,
                'country'      => trim($post['st_country'] ?? ($post['bt_country'] ?? 'India')),
            ];
        })(),
        'items'            => $items,
        'subtotal'         => $subtotal,
        'discount_type'    => $discountType,
        'discount_percent' => $discountPct,
        'discount_flat'    => $discountFlat,
        'discount_amount'  => $discountAmt,
        'taxable_value'    => $taxableValue,
        'is_gst'           => $isGst,
        'gst_mode'         => $gstMode,
        'cgst_rate'        => $cgstRate,
        'cgst_amount'      => $cgstAmt,
        'sgst_rate'        => $sgstRate,
        'sgst_amount'      => $sgstAmt,
        'igst_rate'        => $igstRate,
        'igst_amount'      => $igstAmt,
        'gst_breakdown'    => $gstBreakdown,
        'grand_total'      => $grandTotal,
        'amount_in_words'  => numberToWords($grandTotal, $post['currency'] ?? 'INR'),
        'currency'         => trim($post['currency'] ?? 'INR'),
        'terms'            => $terms,
        'notes'            => trim($post['notes'] ?? ''),
        'show_signature'   => !empty($post['show_signature']),
        'show_stamp'       => !empty($post['show_stamp']),
        'status'           => ($pStatus === 'paid') ? 'paid' : ($post['status'] ?? 'draft'),
        'payment_status'   => $pStatus,
        'advance_amount'   => $advAmt,
        'balance_amount'   => $balDue,
        'payment_mode'     => trim($post['payment_mode'] ?? ''),
        'payment_notes'    => trim($post['payment_notes'] ?? ''),
        'eway_bill'        => $ewayBill,
        'customer_id'      => (function() use ($post, $id) {
            $cid = !empty($post['customer_id']) ? trim($post['customer_id']) : null;
            if ($cid) return $cid;
            $cName = trim($post['bt_name'] ?? '');
            $cPhone = trim($post['bt_phone'] ?? '');
            if ($cName !== '' || $cPhone !== '') {
                $allCust = getAllCustomers();
                foreach ($allCust as $c) {
                    if (($cPhone !== '' && ($c['phone'] ?? '') === $cPhone) ||
                        ($cName !== '' && strcasecmp(trim($c['name'] ?? ''), $cName) === 0)) {
                        return $c['id'];
                    }
                }
            }
            if ($id) {
                $ex = getDocument($id);
                if (!empty($ex['customer_id'])) return $ex['customer_id'];
            }
            return null;
        })(),
        'company_id'       => $company['id'] ?? null,
        'supplier'         => $company,
        'show_bank'           => !empty($post['show_bank']),
        'show_russea'         => !empty($post['show_russea']),
        'estimation_subtype'  => (function() use ($type, $post) {
            if ($type !== 'estimation') return null;
            return $post['estimation_subtype'] ?? 'normal';
        })(),
        'blocks'              => ($isMultiBlockPost && !empty($cleanBlocks)) ? $cleanBlocks : null,
        'show_combined_total' => isset($post['show_combined_total']) ? (int)!empty($post['show_combined_total']) : 1,
        'turnkey_config'      => (function() use ($type, $post, $id) {
            if ($type !== 'estimation') return null;
            $isTurnkey = (($post['estimation_subtype'] ?? '') === 'turnkey_court');
            if (!$isTurnkey) return null;

            $existingDoc = $id ? getDocument($id) : null;
            $baseCfg = $existingDoc['turnkey_config'] ?? [];

                $cfg = !empty($post['turnkey_config'])
                    ? (is_array($post['turnkey_config']) ? $post['turnkey_config'] : json_decode($post['turnkey_config'], true))
                    : $baseCfg;
                if (!is_array($cfg)) $cfg = [];

                if (isset($post['tk_length']))       $cfg['length'] = (float)$post['tk_length'];
                if (isset($post['tk_width']))        $cfg['width'] = (float)$post['tk_width'];
                if (isset($post['tk_height']))       $cfg['height'] = (float)$post['tk_height'];
                if (isset($post['tk_pitch_length'])) $cfg['pitch_length'] = (float)$post['tk_pitch_length'];
                if (isset($post['tk_gate_count']))   $cfg['gate_count'] = (int)$post['tk_gate_count'];
                if (isset($post['tk_sport_type']))   $cfg['sport_type'] = $post['tk_sport_type'];
                if (isset($post['tk_roof_type']))    $cfg['roof_type'] = $post['tk_roof_type'];
                if (isset($post['tk_package']))      $cfg['package'] = $post['tk_package'];
                if (isset($post['tk_pole_style']))   $cfg['pole_style'] = $post['tk_pole_style'];
                if (isset($post['tk_pipe_size']))    $cfg['pipe_size'] = $post['tk_pipe_size'];
                if (isset($post['tk_pipe_gauge']))   $cfg['pipe_gauge'] = $post['tk_pipe_gauge'];
                if (isset($post['tk_turf_type']))    $cfg['turf_type'] = $post['tk_turf_type'];
                if (isset($post['tk_civil_bed']))    $cfg['civil_bed_type'] = $post['tk_civil_bed'];
                if (isset($post['mz_badminton_floor'])) $cfg['badminton_floor_type'] = $post['mz_badminton_floor'];
                if (isset($post['mz_badminton_bed']))   $cfg['badminton_bed_type'] = $post['mz_badminton_bed'];
                if (isset($post['tk_side_net']))     $cfg['side_net'] = $post['tk_side_net'];
                if (isset($post['tk_top_net']))      $cfg['top_net'] = $post['tk_top_net'];
                if (isset($post['tk_custom_rate']))  $cfg['custom_rate'] = (float)$post['tk_custom_rate'] ?: null;

                if (isset($post['turnkey_gates_json'])) {
                    $gt = is_array($post['turnkey_gates_json']) ? $post['turnkey_gates_json'] : json_decode($post['turnkey_gates_json'], true);
                    if (is_array($gt)) {
                        $cfg['gates'] = $gt;
                        $cfg['gate_count'] = count($gt);
                    }
                } elseif (isset($cfg['gates']) && is_array($cfg['gates'])) {
                    $cfg['gate_count'] = count($cfg['gates']);
                }

                if (isset($post['turnkey_excluded_json'])) {
                    $ex = is_array($post['turnkey_excluded_json']) ? $post['turnkey_excluded_json'] : json_decode($post['turnkey_excluded_json'], true);
                    if (is_array($ex)) $cfg['excluded_items'] = $ex;
                }
                if (isset($post['tk_open_sides'])) {
                    $os = is_array($post['tk_open_sides']) ? $post['tk_open_sides'] : json_decode($post['tk_open_sides'], true);
                    if (is_array($os)) $cfg['open_sides'] = $os;
                }
                if (isset($post['tk_layers'])) {
                    $ly = is_array($post['tk_layers']) ? $post['tk_layers'] : json_decode($post['tk_layers'], true);
                    if (is_array($ly)) $cfg['layers'] = $ly;
                }
                if (isset($post['show_roof_sheets'])) {
                    $cfg['show_roof_sheets'] = !empty($post['show_roof_sheets']);
                }
                if (isset($post['turnkey_zones_json'])) {
                    $zn = is_array($post['turnkey_zones_json']) ? $post['turnkey_zones_json'] : json_decode($post['turnkey_zones_json'], true);
                    if (is_array($zn)) $cfg['zones'] = $zn;
                }

                require_once __DIR__ . '/turnkey_calculator.php';
                if (empty($cfg['specs']) && !empty($cfg['length']) && !empty($cfg['width']) && !empty($cfg['height'])) {
                    $cfg['specs'] = calculateTurnkeyCourtSpecs(
                        $cfg['length'], $cfg['width'], $cfg['height'],
                        $cfg['sport_type'] ?? 'box_cricket',
                        $cfg['roof_type'] ?? 'light_cable_top',
                        $cfg['pole_style'] ?? 'ladder_2line',
                        $cfg['pipe_size'] ?? '40x40',
                        $cfg['pipe_gauge'] ?? '2.0mm',
                        $cfg['turf_type'] ?? 'non_infill_turf',
                        $cfg['civil_bed_type'] ?? 'compacted_wmm',
                        $cfg['side_net'] ?? 'braided_2_5mm_45mm',
                        $cfg['top_net'] ?? 'twisted_1_5mm_35mm',
                        $cfg['gate_count'] ?? (isset($cfg['gates']) ? count($cfg['gates']) : 1),
                        $cfg['pitch_length'] ?? null,
                        $cfg['open_sides'] ?? []
                    );
                }
                return !empty($cfg) ? $cfg : null;
        })(),
        'scope_items'         => (function() use ($type, $post) {
            $titles = (array)($post['scope_title'] ?? []);
            $descs  = (array)($post['scope_desc']  ?? []);
            $notes  = (array)($post['scope_note']  ?? []);
            $items  = [];
            foreach ($titles as $i => $title) {
                $title = trim($title);
                $desc  = trim($descs[$i] ?? '');
                $note  = trim($notes[$i] ?? '');
                if ($title !== '' || $desc !== '') {
                    $items[] = ['title' => $title, 'description' => $desc, 'note' => $note];
                }
            }
            return $items;
        })(),
        'created_at'          => $id ? (getDocument($id)['created_at'] ?? date('c')) : date('c'),
        'updated_at'          => date('c'),
    ];
}

// ─── Customer CRUD ────────────────────────────────────────────────────────────

function getAllCustomers() {
    return loadData('customers');
}

function getCustomer($id) {
    foreach (loadData('customers') as $c) {
        if ($c['id'] === $id) return $c;
    }
    return null;
}

function saveCustomer($customer) {
    $list   = loadData('customers');
    $exists = false;
    foreach ($list as &$c) {
        if ($c['id'] === $customer['id']) {
            $c      = $customer;
            $exists = true;
            break;
        }
    }
    unset($c);
    if (!$exists) $list[] = $customer;
    saveData('customers', $list);
}

function deleteCustomer($id) {
    $list = loadData('customers');
    $list = array_filter($list, fn($c) => $c['id'] !== $id);
    saveData('customers', array_values($list));
}

function generateCustomerId() {
    $list = loadData('customers');
    $max = 0;
    foreach ($list as $c) {
        if (preg_match('/^CUST-(\d+)$/i', $c['id'] ?? '', $m)) {
            $num = (int)$m[1];
            if ($num > $max) $max = $num;
        }
    }
    return 'CUST-' . str_pad($max + 1, 4, '0', STR_PAD_LEFT);
}

// ─── Company CRUD ────────────────────────────────────────────────────────────

function getAllCompanies() {
    return loadData('companies');
}

function getCompany($id) {
    foreach (loadData('companies') as $c) {
        if ($c['id'] === $id) return $c;
    }
    return null;
}

function getDefaultCompany() {
    $list = loadData('companies');
    foreach ($list as $c) {
        if (!empty($c['is_default'])) return $c;
    }
    return $list[0] ?? defaultCompanyData();
}

function defaultCompanyData() {
    $s = getSettings();
    $c = $s['company'] ?? [];
    return array_merge([
        'id'               => 'COMP-0001',
        'name'             => $c['name']        ?? 'NETTINGTEAM',
        'proprietor'       => $c['proprietor']  ?? '',
        'address'          => $c['address']     ?? '',
        'phone'            => $c['phone']        ?? '9912399334',
        'phone2'           => $c['phone2']       ?? '',
        'gstin'            => $c['gstin']        ?? '',
        'email'            => $c['email']        ?? '',
        'state'            => $c['state']        ?? '',
        'state_code'       => $c['state_code']   ?? '',
        'bank_name'        => $c['bank_name']    ?? '',
        'bank_account'     => $c['bank_account'] ?? '',
        'bank_ifsc'        => $c['bank_ifsc']    ?? '',
        'bank_branch'      => $c['bank_branch']  ?? '',
        'bank_account_type'=> $c['bank_account_type'] ?? 'Current Account',
        'signature'        => $s['signature'] ?? null,
        'stamp'            => $s['stamp']     ?? null,
        'logo'             => $s['logo']      ?? null,
        'is_default'       => true,
    ], $c);
}

function generateCompanyStampFile($companyId, $companyName, $designation = null) {
    if (empty($companyName) || empty($companyId)) return null;
    if (!$designation) {
        $upper = strtoupper($companyName);
        $designation = (strpos($upper, "PVT LTD") !== false || strpos($upper, "LTD") !== false) ? "Director" : "Proprietor";
    }
    $cid = escapeshellarg($companyId);
    $cname = escapeshellarg($companyName);
    $desig = escapeshellarg($designation);
    
    $pythonCode = <<<'PY'
import os, sys
from PIL import Image, ImageDraw, ImageFont

uploads_dir = "/var/www/nettingteam.com/admin/uploads"
font_path = "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"

company_id = sys.argv[1]
company_name = sys.argv[2]
designation = sys.argv[3]

width = 1600
height = 650
img = Image.new("RGBA", (width, height), (0, 0, 0, 0))
draw = ImageDraw.Draw(img)

name_len = len(company_name)
if name_len <= 15:
    top_font_size = 76
    bottom_font_size = 62
    top_y = 50
elif name_len <= 25:
    top_font_size = 68
    bottom_font_size = 56
    top_y = 50
else:
    top_font_size = 56
    bottom_font_size = 48
    top_y = 50

font_bold = ImageFont.truetype(font_path, top_font_size)
font_prop = ImageFont.truetype(font_path, bottom_font_size)
ink_color = (32, 54, 150, 240)

text_top = f"For {company_name.upper()}"
top_x = 60
draw.text((top_x, top_y), text_top, fill=ink_color, font=font_bold)

bbox_top = draw.textbbox((top_x, top_y), text_top, font=font_bold)
top_right = bbox_top[2]

text_bottom = designation
bbox_bottom = draw.textbbox((0, 0), text_bottom, font=font_prop)
prop_w = bbox_bottom[2] - bbox_bottom[0]
prop_x = top_right - prop_w
prop_y = bbox_top[3] + 160

draw.text((prop_x, prop_y), text_bottom, fill=ink_color, font=font_prop)

bbox = img.getbbox()
if bbox:
    pad = 25
    crop_box = (max(0, bbox[0] - pad), max(0, bbox[1] - pad), min(width, bbox[2] + pad), min(height, bbox[3] + pad))
    img = img.crop(crop_box)

out_filename = f"{company_id}_stamp.png"
out_path = os.path.join(uploads_dir, out_filename)
img.save(out_path, "PNG")
PY;

    $cmd = 'python3 -c ' . escapeshellarg($pythonCode) . ' ' . $cid . ' ' . $cname . ' ' . $desig;
    shell_exec($cmd);

    $relPath = "uploads/{$companyId}_stamp.png";
    $fullPath = __DIR__ . '/../' . $relPath;
    if (file_exists($fullPath)) {
        return $relPath . '?v=' . filemtime($fullPath);
    }
    return null;
}

function saveCompany($company) {
    // Auto-generate stamp if missing
    if (empty($company['stamp']) || !file_exists(__DIR__ . '/../' . preg_replace('/\?.*$/', '', $company['stamp']))) {
        $newStamp = generateCompanyStampFile($company['id'], $company['name'] ?? '');
        if ($newStamp) { $company['stamp'] = $newStamp; }
    }
    // Auto-copy signature from default company if missing
    if (empty($company['signature']) || !file_exists(__DIR__ . '/../' . preg_replace('/\?.*$/', '', $company['signature']))) {
        $sigSrc = __DIR__ . '/../uploads/COMP-0001_signature.png';
        $sigDst = __DIR__ . '/../uploads/' . $company['id'] . '_signature.png';
        if (!file_exists($sigDst) && file_exists($sigSrc)) { @copy($sigSrc, $sigDst); }
        if (file_exists($sigDst)) { $company['signature'] = 'uploads/' . $company['id'] . '_signature.png?v=' . filemtime($sigDst); }
    }
    $list   = loadData('companies');
    $exists = false;
    // If setting as default, unset others
    if (!empty($company['is_default'])) {
        foreach ($list as &$c) { $c['is_default'] = false; }
        unset($c);
    }
    foreach ($list as &$c) {
        if ($c['id'] === $company['id']) {
            $c      = $company;
            $exists = true;
            break;
        }
    }
    unset($c);
    if (!$exists) $list[] = $company;
    saveData('companies', $list);
}

function deleteCompany($id) {
    $list = loadData('companies');
    $list = array_filter($list, fn($c) => $c['id'] !== $id);
    $list = array_values($list);
    // Ensure at least one default
    if (!empty($list) && !array_filter($list, fn($c) => !empty($c['is_default']))) {
        $list[0]['is_default'] = true;
    }
    saveData('companies', $list);
}

function generateCompanyId() {
    $list = loadData('companies');
    return 'COMP-' . str_pad(count($list) + 1, 4, '0', STR_PAD_LEFT);
}

function buildCompanyFromPost($post, $id = null) {
    return [
        'id'               => $id ?? generateCompanyId(),
        'name'             => strtoupper(trim($post['name']             ?? '')),
        'proprietor'       => trim($post['proprietor']       ?? ''),
        'address'          => trim($post['address']          ?? ''),
        'phone'            => trim($post['phone']            ?? ''),
        'phone2'           => trim($post['phone2']           ?? ''),
        'gstin'            => strtoupper(trim($post['gstin'] ?? '')),
        'email'            => trim($post['email']            ?? ''),
        'state'            => trim($post['state']            ?? ''),
        'state_code'       => trim($post['state_code']       ?? ''),
        'country'          => trim($post['country']          ?? 'India'),
        'bank_name'        => trim($post['bank_name']        ?? ''),
        'bank_account'     => trim($post['bank_account']     ?? ''),
        'bank_ifsc'        => strtoupper(trim($post['bank_ifsc'] ?? '')),
        'bank_branch'      => trim($post['bank_branch']      ?? ''),
        'bank_account_type'=> trim($post['bank_account_type'] ?? 'Current Account'),
        'is_default'       => !empty($post['is_default']),
        'signature'        => $post['existing_signature'] ?: null,
        'stamp'            => $post['existing_stamp']     ?: null,
        'logo'             => $post['existing_logo']      ?: null,
        'created_at'       => $id ? (getCompany($id)['created_at'] ?? date('c')) : date('c'),
        'updated_at'       => date('c'),
    ];
}

function uploadCompanyFile($fileKey, $companyId, $type) {
    $allowed   = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $uploadDir = __DIR__ . '/../uploads/';

    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'msg' => ''];
    }
    $file = $_FILES[$fileKey];
    if (!in_array($file['type'], $allowed)) {
        return ['ok' => false, 'msg' => 'Only JPEG, PNG, GIF, WebP allowed.'];
    }
    if ($file['size'] > 10 * 1024 * 1024) {
        return ['ok' => false, 'msg' => 'File must be under 10 MB.'];
    }
    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = $companyId . '_' . $type . '.' . $ext;
    $dest     = $uploadDir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['ok' => false, 'msg' => 'Failed to save file.'];
    }
    return ['ok' => true, 'path' => 'uploads/' . $filename . '?v=' . time()];
}

// ─── Upload Helper ───────────────────────────────────────────────────────────

function uploadFile($fileKey, $type) {
    $allowed   = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $uploadDir = __DIR__ . '/../uploads/';

    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'msg' => 'No file or upload error.'];
    }

    $file = $_FILES[$fileKey];
    if (!in_array($file['type'], $allowed)) {
        return ['ok' => false, 'msg' => 'Only JPEG, PNG, GIF, WebP allowed.'];
    }
    if ($file['size'] > 10 * 1024 * 1024) {
        return ['ok' => false, 'msg' => 'File must be under 10 MB.'];
    }

    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $type . '.' . strtolower($ext);
    $dest     = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['ok' => false, 'msg' => 'Failed to save file.'];
    }

    return ['ok' => true, 'path' => 'uploads/' . $filename . '?v=' . time()];
}

// ─── UI Helpers ──────────────────────────────────────────────────────────────

function docTypeLabel($type, $isGst = null) {
    // Auto-detect if not passed (backward compat: tax_invoice was always GST)
    if ($isGst === null) {
        $isGst = ($type === 'tax_invoice') ? true : true;
    }
    if ($isGst) {
        return match($type) {
            'tax_invoice'            => 'Tax Invoice',
            'bill'                   => 'GST Bill',
            'proforma_invoice',
            'proforma'               => 'Proforma Invoice',
            default                  => 'GST Estimation',
        };
    } else {
        return match($type) {
            'tax_invoice'            => 'Invoice',
            'bill'                   => 'Bill',
            'proforma_invoice',
            'proforma'               => 'Proforma Invoice',
            default                  => 'Estimation',
        };
    }
}

function docTypeBadgeClass($type) {
    return match($type) {
        'tax_invoice'            => 'type-inv',
        'bill'                   => 'type-bill',
        'proforma_invoice',
        'proforma'               => 'type-pi',
        default                  => 'type-est',
    };
}

function statusBadge($status) {
    $map = [
        'draft'     => 's-draft',
        'sent'      => 's-paid',
        'final'     => 's-final',
        'accepted'  => 's-final',
        'rejected'  => 's-cancelled',
        'paid'      => 's-paid',
        'cancelled' => 's-cancelled',
    ];
    $cls = $map[$status] ?? 's-draft';
    return '<span class="status-badge ' . $cls . '">' . ucfirst($status) . '</span>';
}

function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function flashSet($key, $msg) {
    $_SESSION['flash'][$key] = $msg;
}

function flashGet($key) {
    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
}
