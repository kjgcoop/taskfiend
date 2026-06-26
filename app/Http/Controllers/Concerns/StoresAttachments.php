<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\Storage;

trait StoresAttachments
{
    // Comma-separated list of allowed MIME types for file uploads.
    protected static function allowedMimetypes(): string
    {
        return
            'image/jpeg,image/png,image/webp,image/gif,image/heic,image/heif,' .
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
        return 'File type not allowed. Accepted: images (JPG, PNG, WebP, GIF, HEIC), PDF, Word, Excel, PowerPoint, LibreOffice formats, CSV, TXT, JSON, Markdown, XML, YAML, ZIP.';
    }

    // Store an uploaded file, scaling it down if it is an image whose largest
    // dimension exceeds SCALE_LARGEST_TO. Returns [path, fileSize, mimeType].
    protected function storeScaled(\Illuminate\Http\UploadedFile $file, string $directory): array
    {
        $mime = $file->getMimeType();
        $scalableMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

        if (in_array($mime, $scalableMimes)) {
            $scaleTo = (int) env('SCALE_LARGEST_TO', 2048);
            $src = @imagecreatefromstring(file_get_contents($file->getRealPath()));

            if ($src) {
                $srcW = imagesx($src);
                $srcH = imagesy($src);

                if (max($srcW, $srcH) > $scaleTo) {
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
                    };
                    $data = ob_get_clean();
                    imagedestroy($dst);

                    $ext  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mime];
                    $path = $directory . '/' . uniqid() . '.' . $ext;
                    Storage::disk('private')->put($path, $data);

                    return [$path, strlen($data), $mime];
                }

                imagedestroy($src);
            }
        }

        $path = $file->store($directory, 'private');
        return [$path, $file->getSize(), $mime];
    }
}
