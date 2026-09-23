<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Labels</title>
    <style>
        /*
         * A4, 24 up: 3 columns x 8 rows of 70mm x 37mm stickers.
         *
         * Every cell is positioned absolutely rather than laid out in a table.
         * 3 x 70mm is exactly 210mm and 8 x 37mm is exactly 296mm, so a single
         * millimetre of border or padding anywhere in a flow layout pushes a
         * column or a row onto the next page. Absolute positions cannot drift.
         *
         * Print one page onto plain paper and hold it against a real sheet
         * before a full run — printer margins vary and 2mm ruins a sheet.
         */
        @page { size: A4; margin: 0; }

        body { margin: 0; padding: 0; font-family: DejaVu Sans, sans-serif; }

        .sheet {
            position: relative;
            width: 210mm;
            height: 297mm;
            page-break-after: always;
        }

        .sheet:last-child { page-break-after: auto; }

        .cell {
            position: absolute;
            width: {{ $layout['width_mm'] }}mm;
            height: {{ $layout['height_mm'] }}mm;
            /* Crop guide, faint enough to vanish under the sticker edge. */
            border: 0.2mm dotted #cccccc;
        }

        .qr {
            position: absolute;
            top: 5mm;
            left: 3mm;
            width: 27mm;
            height: 27mm;
        }

        .tag {
            position: absolute;
            top: 11mm;
            left: 32mm;
            font-family: DejaVu Sans Mono, monospace;
            font-size: 13pt;
            font-weight: bold;
            letter-spacing: 0.3pt;
            color: #000000;
        }

        .school {
            position: absolute;
            top: 19mm;
            left: 32mm;
            font-size: 6.5pt;
            color: #444444;
        }
    </style>
</head>
<body>
    @foreach ($pages as $cells)
        <div class="sheet">
            @foreach ($cells as $index => $cell)
                @continue(! $cell)
                @php
                    $column = $index % $layout['columns'];
                    $row = intdiv($index, $layout['columns']);
                    $left = $layout['page_margin_left_mm'] + $column * $layout['width_mm'];
                    $top = $layout['page_margin_top_mm'] + $row * $layout['height_mm'];
                @endphp
                {{-- Exactly three things: the QR, the tag in large monospaced
                     type as the fallback when the QR is scratched, and the
                     school name. Nothing else (spec section 6.3). --}}
                <div class="cell" style="left: {{ $left }}mm; top: {{ $top }}mm;">
                    <img class="qr" src="{{ $cell['qr'] }}" alt="">
                    <div class="tag">{{ $cell['tag'] }}</div>
                    <div class="school">{{ $schoolName }}</div>
                </div>
            @endforeach
        </div>
    @endforeach
</body>
</html>
