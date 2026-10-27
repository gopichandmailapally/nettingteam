<?php
require_once __DIR__ . '/includes/functions.php';

$doc = getDocument('EST-202610-011');
if ($doc) {
    $doc['estimation_subtype'] = 'supply_only';
    $doc['items'] = [
        [
            'description' => 'Safety Net for Building',
            'hsn'         => '5608',
            'qty'         => 30000,
            'unit'        => 'SQFT',
            'rate'        => 17,
            'amount'      => 510000,
            'specs'       => [
                'Thickness : 2.5MM',
                'Square Gap : 40MM',
                'Colour : WHITE'
            ],
            'gst_rate'    => 5
        ]
    ];
    $doc['subtotal']        = 510000;
    $doc['taxable_value']   = 510000;
    $doc['grand_total']     = 510000;
    $doc['balance_amount']  = 510000;
    $doc['amount_in_words'] = 'Rupees Five Lakh Ten Thousand Only';
    $doc['scope_items']     = [
        [
            'title'       => 'Material Supply',
            'description' => 'Supply of all specified materials as per the estimation including packing, quality check, and delivery to site.',
            'note'        => 'All materials will be supplied as per approved specifications. Any changes after order confirmation may incur additional charges.'
        ]
    ];
    $doc['updated_at'] = date('c');

    saveDocument($doc);
    echo "SUCCESS: EST-202610-011 updated with 1 item @ Rs 17 = Rs 510,000 (Net Installation removed)
";
} else {
    echo "ERROR: Document EST-202610-011 not found
";
}
