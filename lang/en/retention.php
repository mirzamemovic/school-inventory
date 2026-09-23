<?php

return [
    'removed_person' => 'Removed person #:id',

    // data:enforce-retention
    'heading_loans' => 'Book loans to delete',
    'heading_people' => 'People to delete',
    'heading_held_back' => 'Past their date, but kept',
    'held_back_why' => 'These people still hold a book. An unreturned book is a live debt, so nothing of theirs is deleted.',
    'none' => 'Nothing.',
    'nothing_to_do' => 'Nothing has reached the end of its retention period.',
    'dry_run_notice' => 'Dry run — nothing was deleted.',
    'about_to_delete' => 'About to permanently delete :loans loan(s) and :people person record(s).',
    'confirm' => 'Delete these permanently?',
    'cancelled' => 'Nothing was changed.',
    'done' => 'Deleted :loans loan(s) and :people person record(s).',

    'col_person' => 'Person',
    'col_title' => 'Title',
    'col_year' => 'School year',
    'col_returned' => 'Returned',
    'col_type' => 'Staff or student',
    'col_left' => 'Left on',
    'col_loans' => 'Loans',
    'col_open_loans' => 'Books still out',

    // person:erase
    'person_not_found' => 'No person with id :id.',
    'about_to_erase' => 'About to erase :name (#:id). Their name becomes ":pseudonym" everywhere.',
    'heading_inspections' => 'Inspections that will be scrubbed',
    'inspections_caveat' => 'Matched on the exact name only — inspector names are free text with no link to a person. Check this list is right before agreeing.',
    'erase_keeps_loans' => 'Their :count book loan(s) are kept, pointing at the pseudonym.',
    'erase_keeps_log' => 'Every audit log row is kept. Names, device tokens and IP addresses inside them are overwritten.',
    'confirm_erase' => 'Erase this person?',
    'erased' => 'Erased person #:id.',

    'col_date' => 'Date',
    'col_asset' => 'Item',
    'col_inspector' => 'Recorded name',
    'col_what' => 'What',
    'col_count' => 'Rows',
    'row_reports' => 'Reports renamed',
    'row_inspections' => 'Inspections renamed',
    'row_events_actor' => 'Log rows: actor renamed',
    'row_events_meta' => 'Log rows: name removed from stored details',
    'row_identifiers' => 'Device tokens and IP addresses cleared',
    'row_loans_kept' => 'Book loans kept',
];
