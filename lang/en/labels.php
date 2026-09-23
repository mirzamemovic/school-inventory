<?php

return [
    'title' => 'Print labels',
    'queue_heading' => 'Waiting to be printed',
    'queue_count' => '{0} No labels waiting|{1} 1 label waiting|[2,*] :count labels waiting',
    'queue_empty' => 'Nothing is waiting to be printed.',
    'added_to_queue' => 'Added to the label queue.',
    'removed_from_queue' => 'Taken out of the queue.',
    'print_sheet' => 'Print sheet',
    'print_sheet_help' => 'Printing the sheet empties the queue.',

    'blank_heading' => 'Blank labels for the labelling day',
    'blank_help' => 'Creates that many records carrying nothing but a tag, then gives you the sheet. Stick them on everything, then scan each one to fill in the details.',
    'blank_count' => 'How many',
    'generate' => 'Generate blank labels',

    'start_heading' => 'Start at which sticker?',
    'start_help' => 'Feed a part-used sheet back through the printer. Stickers before the one you choose are left blank.',
    'start_position' => 'Start position',
    'position' => 'Position :number',

    'reprint_heading' => 'Reprint',
    'reprint_help' => 'A reprint always carries the same code, so the item keeps its history.',
];
