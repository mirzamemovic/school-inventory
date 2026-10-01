<?php

return [
    'someone' => 'Someone',
    'the_system' => 'The system',
    'self_reported' => 'Self-reported',
    'title' => 'Activity',
    'search_placeholder' => 'Search the log',
    'filter_action' => 'What happened',
    'filter_who' => 'Who',
    'filter_from' => 'From',
    'filter_to' => 'To',
    'filter' => 'Filter',
    'clear' => 'Clear filters',
    'export' => 'Download as CSV',
    'empty' => 'Nothing has happened yet.',
    'no_matches' => 'Nothing in the log matches that.',
    'block_device' => 'Block this device',
    'device_blocked' => 'That device can no longer send anything.',
    'device_unblocked' => 'That device can send again.',
    'blocked_devices' => 'Blocked devices',
    'unblock' => 'Unblock',
    'no_blocked_devices' => 'No devices are blocked.',
    'blocked_on' => 'Blocked :date',

    'csv' => [
        'when' => 'When',
        'what' => 'What happened',
        'action' => 'Action',
        'who' => 'Who',
        'verified' => 'Verified',
        'subject' => 'Item',
        'details' => 'Details',
        'footer' => 'Exported :date by :name. This file contains personal data: keep it only as long as it is needed, then delete it, as the retention rules in RETENTION.md require.',
    ],
    'ago' => ':time ago',

    'actor' => [
        'admin' => 'Admin',
        'staff' => 'Staff',
        'student' => 'Student',
        'public' => 'Anyone',
        'system' => 'System',
    ],

    /*
     * Nested rather than flat: Laravel reads a dot in a translation key as
     * nesting, so 'asset.moved' as a literal key would never be found by
     * __('activity.sentence.asset.moved').
     */
    'sentence' => [
        'asset' => [
            'created' => ':actor added this item',
            'moved' => ':actor moved it from :from to :to',
            'assigned' => ':actor made :to answerable for it',
            'status_changed' => ':actor changed the status from :from to :to',
            'edited' => ':actor edited the details',
            'retired' => ':actor retired it',
            'inspected' => ':actor inspected it',
        ],
        'report' => [
            'created' => ':actor reported a problem',
            'viewed' => ':actor opened the report',
            'acknowledged' => ':actor started working on it',
            'resolved' => ':actor marked it fixed',
            'dismissed' => ':actor decided it was not a problem',
        ],
        'book' => [
            'issued' => ':actor issued books',
            'returned' => ':actor took books back',
            'title_added' => ':actor added the book :name',
            'title_edited' => ':actor changed the book :name from :from to :to',
            'class_link_replaced' => ':actor made a new class book-debt link, and the old one stopped working',
        ],
        'supply' => [
            'taken' => ':actor took some',
            'recounted' => ':actor recounted it',
            'added' => ':actor added the supply :name',
            'edited' => ':actor changed the supply :name from :from to :to',
        ],
        // People are not named in these sentences, as in the ones before them.
        'person' => [
            'added' => ':actor added a person',
            'edited' => ':actor changed a person\'s details',
            'deactivated' => ':actor deactivated a person',
            'reactivated' => ':actor reactivated a person',
            'erased' => ':actor erased a person record at their request',
        ],
        'data' => [
            'retention_enforced' => ':actor removed records that had reached the end of their retention period',
            'exported' => ':actor downloaded a copy of the records',
        ],
        'device' => [
            'blocked' => ':actor blocked a device from sending anything',
            'unblocked' => ':actor let a blocked device send again',
        ],
        'inspector_phone' => [
            'invited' => ':actor made a code to set up a phone for safety checks',
            'claimed' => ':actor set up a phone for safety checks',
            'withdrawn' => ':actor withdrew a phone from safety checks',
        ],
        'licence' => [
            'created' => ':actor added this license',
            'edited' => ':actor changed :from to :to',
            'renewed' => ':actor renewed it from :from to :to',
            'deleted' => ':actor removed this license',
        ],
        'room' => [
            'created' => ':actor added the room :name',
            'edited' => ':actor changed the room :name from :from to :to',
            'counted' => ':actor marked :name as counted',
            'furniture_added' => ':actor added :item to :name',
            'furniture_changed' => ':actor changed the number of :item in :name from :from to :to',
        ],
        'category' => [
            'created' => ':actor added the category :name',
            'edited' => ':actor changed the category :name from :from to :to',
        ],
        'settings' => [
            'changed' => ':actor changed a setting from :from to :to',
        ],
        'user' => [
            'created' => ':actor added :account as a user',
            'edited' => ':actor changed the details of :account from :from to :to',
            'password_reset' => ':actor reset the password for :account',
            'password_set' => ':actor chose a password with their once-only link',
            'password_changed' => ':actor changed their own password',
            'deactivated' => ':actor deactivated :account',
            'reactivated' => ':actor reactivated :account',
        ],
        'fallback' => ':actor did something',
    ],
];
