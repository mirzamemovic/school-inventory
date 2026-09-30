<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Photos are re-encoded server side after upload (spec section 13). That strips
 * EXIF — including the school's GPS coordinates — and destroys anything
 * embedded in the file. Files are stored outside the webroot under a random
 * name and served through a controller, because an uploads directory inside
 * public/ that can execute PHP is game over on shared hosting.
 */
class PhotoStorage
{
    private const DISK = 'local';

    public function store(UploadedFile $file, string $directory): string
    {
        $image = $this->decode($file->getRealPath());
        $image = $this->resize($image, (int) config('school.photos.max_dimension'));

        $path = $directory.'/'.Str::random(40).'.jpg';

        ob_start();
        imagejpeg($image, null, (int) config('school.photos.quality'));
        $encoded = (string) ob_get_clean();
        imagedestroy($image);

        Storage::disk(self::DISK)->put($path, $encoded);

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path && Storage::disk(self::DISK)->exists($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    public function exists(string $path): bool
    {
        return Storage::disk(self::DISK)->exists($path);
    }

    public function read(string $path): string
    {
        return (string) Storage::disk(self::DISK)->get($path);
    }

    /**
     * Whether an image of this size can be decoded without running out of
     * memory.
     *
     * The file size says nothing about this: a PNG of a few dozen bytes can
     * declare itself 20,000 × 20,000 pixels, and GD allocates the full canvas
     * — about four bytes a pixel — before it reads a single row. On shared
     * hosting that exhausts the request and, repeated, the account's resource
     * limits, taking the report form down for everyone.
     */
    public static function fitsInMemory(int $width, int $height): bool
    {
        if ($width < 1 || $height < 1) {
            return false;
        }

        $ceiling = (int) config('school.photos.max_pixels');
        $limit = self::memoryLimitInBytes();

        if ($limit !== null) {
            // Room for what the request already holds, the resized copy and
            // the encoder, then roughly five bytes for each decoded pixel.
            $budget = $limit - memory_get_usage() - 32 * 1024 * 1024;
            $ceiling = min($ceiling, intdiv(max(0, $budget), 5));
        }

        return $width * $height <= $ceiling;
    }

    private static function memoryLimitInBytes(): ?int
    {
        $raw = trim((string) ini_get('memory_limit'));

        if ($raw === '' || $raw === '-1') {
            return null;
        }

        $number = (int) $raw;

        return match (strtolower(substr($raw, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    /** @return \GdImage */
    private function decode(string $realPath)
    {
        // Trust the file's actual content, never its extension.
        $info = @getimagesize($realPath);

        // Validation already refuses these; this is the last line before GD
        // allocates anything.
        if (! $info || ! self::fitsInMemory((int) $info[0], (int) $info[1])) {
            throw new RuntimeException('That image is too large to decode safely.');
        }

        $image = match ($info['mime'] ?? null) {
            'image/jpeg' => @imagecreatefromjpeg($realPath),
            'image/png' => @imagecreatefrompng($realPath),
            'image/webp' => @imagecreatefromwebp($realPath),
            default => null,
        };

        if (! $image) {
            throw new RuntimeException('That file is not a JPEG, PNG or WebP image.');
        }

        return $image;
    }

    /** @return \GdImage */
    private function resize(\GdImage $image, int $max)
    {
        $width = imagesx($image);
        $height = imagesy($image);

        if ($width <= $max && $height <= $max) {
            // Still re-encode onto a fresh canvas so nothing survives from the
            // original file.
            $max = max($width, $height);
        }

        $scale = $max / max($width, $height);
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);

        return $canvas;
    }
}
