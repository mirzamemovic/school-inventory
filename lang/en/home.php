<?php

return [
    'title' => 'Home',
    'nothing_needs_attention' => 'Nothing needs your attention.',
    'needs_attention' => 'Needs attention',
    'open_reports' => '{0} Nothing reported|{1} 1 open report|[2,*] :count open reports',

    'totals' => [
        'all' => 'Assets',
        'in_use' => 'In use',
        'under_repair' => 'Under repair',
        'retired' => 'Retired',
    ],

    'attention' => [
        'stale_report' => ':item was reported :time and is still open',
        'stuck_repair' => ':item has been under repair for over a month',
        'warranty' => 'The warranty on :item runs out on :date',
        'overdue_inspection' => ':item is overdue an inspection',
        'license_expiring' => 'The license for :item runs out on :date',
        'license_expired' => 'The license for :item ran out on :date',
        'low_supply' => ':item is down to :quantity',
        'book_debt' => '{1} 1 student still owes books|[2,*] :count students still owe books',
    ],
];
