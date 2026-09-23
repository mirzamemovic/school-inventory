<?php

return [
    'title' => 'Licenses',
    'add' => 'Add a license',
    'edit' => 'Edit license',
    'added' => 'License added.',
    'changes_saved' => 'Changes saved.',
    'deleted' => ':name has been removed from the list.',
    'renewed' => 'Renewed. It now runs until :date.',
    'none_yet' => 'Nothing recorded yet. Add the subscriptions and licences the school pays for.',
    'no_matches' => 'No license matches that.',
    'search_placeholder' => 'Search by name, vendor or account',
    'delete' => 'Remove this license',
    'delete_confirm' => 'Remove this license from the list? The history of what happened to it is kept.',

    'never_expires' => 'Never expires',
    'expires_on' => 'Expires :date',
    'expired_on' => 'Expired :date',
    'expires_in_days' => '{1} 1 day left|[2,*] :count days left',
    'auto_renew' => 'Renews itself',
    'manual_renew' => 'Renewed by hand',
    'seats' => ':used of :total',
    'seats_none' => 'No seats',
    'expiry' => 'Expiry',

    'warning' => [
        'manual' => 'This has to be renewed by hand. If it runs out, the service stops.',
        'auto' => 'This renews itself. If nobody cancels it, money leaves the account on that date.',
        'expired_manual' => 'This ran out. The service may already have stopped.',
        'expired_auto' => 'This was set to renew itself, so it may already have been charged.',
    ],

    'renew_heading' => 'Renew',
    'renew_help' => 'Put in the new expiry date. The old one is kept in the history.',
    'renew_action' => 'Renew',
    'new_expiry' => 'New expiry date',
    'updated_cost' => 'Updated cost (optional)',

    'history' => 'What has happened to this license',
    'history_empty' => 'Nothing has happened to this license yet.',
    'tied_to' => 'Tied to',
    'tied_to_help' => 'Only for a licence that belongs to one specific machine, such as a Windows license on a PC.',

    'fields' => [
        'name' => 'name',
        'vendor' => 'vendor',
        'type' => 'kind',
        'licence_model' => 'how it is licensed',
        'seats_total' => 'seats owned',
        'seats_used' => 'seats used',
        'purchase_date' => 'purchase date',
        'expires_at' => 'expiry date',
        'renewal_cost' => 'renewal cost',
        'auto_renew' => 'renews itself',
        'account_reference' => 'account it belongs to',
        'asset_id' => 'machine',
        'notes' => 'notes',
    ],

    'labels' => [
        'name' => 'Name',
        'vendor' => 'Vendor',
        'type' => 'Kind',
        'licence_model' => 'How it is licensed',
        'seats_total' => 'Seats owned',
        'seats_used' => 'Seats used',
        'purchase_date' => 'Bought on',
        'expires_at' => 'Expiry date',
        'renewal_cost' => 'Renewal cost',
        'auto_renew' => 'It renews itself automatically',
        'account_reference' => 'Account it belongs to',
        'asset_id' => 'Machine it belongs to',
        'notes' => 'Notes',
    ],

    'help' => [
        'expires_at' => 'Leave this empty if the licence never expires.',
        'account_reference' => 'The email or account that owns the subscription. Worth filling in — this is what gets lost when someone leaves.',
        'seats' => 'Two plain numbers. Nobody is assigned a seat here.',
    ],

    'type' => [
        'software' => 'Software',
        'domain' => 'Domain name',
        'hosting' => 'Hosting',
        'antivirus' => 'Antivirus',
        'other' => 'Other',
    ],

    'model' => [
        'subscription' => 'Subscription',
        'perpetual' => 'Bought outright',
        'volume' => 'Volume licence',
    ],
];
