<?php

return [
    /*
    | The fixed part of every asset tag: INV-{CATEGORY_PREFIX}-{0000}.
    | Changing this after labels have been printed would orphan them.
    */
    'tag_prefix' => env('SCHOOL_TAG_PREFIX', 'INV'),

    /*
    | The prefix used for pre-printed blank labels, which have no category yet
    | and — because tags are write-once — will keep this prefix for ever.
    */
    'blank_tag_prefix' => 'GEN',

    /*
    | A4 sheet of 24 labels, 3 columns by 8 rows, 70mm x 37mm each. These are
    | the standard sticker sheet dimensions; check a test print on plain paper
    | against a real sheet before a full run.
    */
    'labels' => [
        'columns' => 3,
        'rows' => 8,
        'width_mm' => 70,
        'height_mm' => 37,
        'page_margin_top_mm' => 0.5,
        'page_margin_left_mm' => 0,
    ],

    /*
    | The academic year runs September to June. The end date is what book loans
    | are due on and when a class debt link stops working.
    |
    | Open question 4 in the spec: confirm the school's real dates and change
    | these two lines.
    */
    'academic_year' => [
        'ends' => '06-15',
    ],

    /*
    | Photo uploads. Re-encoded server side to strip EXIF and anything embedded
    | in the file (spec section 13).
    */
    'photos' => [
        'max_kilobytes' => 5120,
        // Decoded size, not file size: a tiny file can declare a huge canvas.
        // Also capped at run time by what memory_limit leaves room for.
        'max_pixels' => 50_000_000,
        'max_dimension' => 1600,
        'quality' => 78,
    ],
];
