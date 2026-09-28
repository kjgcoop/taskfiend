<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait StoresAttachments
{
    // Image formats accepted anywhere an image can be uploaded (task/comment
    // attachments, project background images). Shared with backgroundImageRules()
    // in ProjectController so there's one list to update when a format is added.
    public const IMAGE_MIMETYPES = 'image/jpeg,image/png,image/webp,image/gif,image/avif,image/heic,image/heif';

    // Of the image types above, the ones storeScaled()/resizeImage() know how to
    // decode and re-encode with GD, so they can be scaled down. HEIC/HEIF are
    // accepted but stored as-is.
    private const SCALABLE_IMAGE_MIMETYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif'];

    private const SCALABLE_IMAGE_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
        'image/avif' => 'avif',
    ];

    // Comma-separated list of allowed MIME types for file uploads.
    protected static function allowedMimetypes(): string
    {
        return
            self::IMAGE_MIMETYPES . ',' .
            'application/pdf,' .
            'application/msword,' .
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document,' .
            'application/vnd.ms-excel,' .
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,' .
            'application/vnd.ms-powerpoint,' .
            'application/vnd.openxmlformats-officedocument.presentationml.presentation,' .
            'application/vnd.oasis.opendocument.text,' .
            'application/vnd.oasis.opendocument.spreadsheet,' .
            'application/vnd.oasis.opendocument.presentation,' .
            'text/csv,text/plain,text/markdown,text/xml,application/xml,text/yaml,application/yaml,' .
            'application/json,text/json,' .
            'application/zip,application/x-zip-compressed';
    }

    protected static function allowedMimetypesMessage(): string
    {
        return 'File type not allowed. Accepted: images (JPG, PNG, WebP, GIF, AVIF, HEIC), PDF, Word, Excel, PowerPoint, LibreOffice formats, CSV, TXT, JSON, Markdown, XML, YAML, ZIP.';
    }

    // If $file is a scalable image type whose largest dimension exceeds
    // SCALE_LARGEST_TO, decodes it, resizes it down, and re-encodes it,
    // returning [rawBytes, extension]. Returns null if the file isn't a
    // scalable image type, isn't decodable, or doesn't need resizing --
    // callers should fall back to storing the original upload as-is.
    private static function resizeImage(UploadedFile $file): ?array
    {
        $mime = $file->getMimeType();
        if (!in_array($mime, self::SCALABLE_IMAGE_MIMETYPES)) {
            return null;
        }

        $src = @imagecreatefromstring(file_get_contents($file->getRealPath()));
        if (!$src) {
            return null;
        }

        $scaleTo = (int) config('taskfiend.scale_largest_to');
        $srcW = imagesx($src);
        $srcH = imagesy($src);

        if (max($srcW, $srcH) <= $scaleTo) {
            imagedestroy($src);
            return null;
        }

        $ratio = $scaleTo / max($srcW, $srcH);
        $newW  = (int) round($srcW * $ratio);
        $newH  = (int) round($srcH * $ratio);

        $dst = imagecreatetruecolor($newW, $newH);
        if ($mime === 'image/png') {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
            imagefilledrectangle($dst, 0, 0, $newW, $newH, $transparent);
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);
        imagedestroy($src);

        ob_start();
        match ($mime) {
            'image/jpeg' => imagejpeg($dst, null, 90),
            'image/png'  => imagepng($dst),
            'image/webp' => imagewebp($dst, null, 90),
            'image/gif'  => imagegif($dst),
            'image/avif' => imageavif($dst, null, 90),
        };
        $data = ob_get_clean();
        imagedestroy($dst);

        return [$data, self::SCALABLE_IMAGE_EXTENSIONS[$mime]];
    }

    // Store an uploaded file, scaling it down if it is an image whose largest
    // dimension exceeds SCALE_LARGEST_TO. Returns [path, fileSize, mimeType].
    protected function storeScaled(UploadedFile $file, string $directory): array
    {
        $mime = $file->getMimeType();
        $resized = self::resizeImage($file);

        if ($resized !== null) {
            [$data, $ext] = $resized;
            $path = $directory . '/' . uniqid() . '.' . $ext;
            Storage::disk('private')->put($path, $data);

            return [$path, strlen($data), $mime];
        }

        $path = $file->store($directory, 'private');
        return [$path, $file->getSize(), $mime];
    }
}
