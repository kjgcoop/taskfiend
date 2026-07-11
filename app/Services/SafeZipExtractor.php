<?php

namespace App\Services;

use ZipArchive;

class SafeZipExtractor
{
    /**
     * Safely extract a zip archive.
     *
     * Rejects symlink entries, absolute paths, and path traversal (defends
     * against the classic unzip-symlink escape), and enforces limits on
     * entry count and total uncompressed size (defends against zip bombs).
     *
     * Returns true on success, false if the archive is invalid, contains a
     * rejected entry, or exceeds the configured limits.
     */
    public static function extract(
        string $zipPath,
        string $destDir,
        int $maxEntries = 500,
        int $maxTotalUncompressedBytes = 50 * 1024 * 1024
    ): bool {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return false;
        }

        if (!file_exists($destDir)) {
            mkdir($destDir, 0755, true);
        }
        $realDestDir = realpath($destDir);
        if ($realDestDir === false) {
            $zip->close();
            return false;
        }

        $entryCount = $zip->numFiles;
        if ($entryCount > $maxEntries) {
            $zip->close();
            return false;
        }

        $totalSize = 0;

        for ($i = 0; $i < $entryCount; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) {
                $zip->close();
                return false;
            }

            $name = $stat['name'];

            if ($name === '' || str_starts_with($name, '/') || str_contains($name, '..')) {
                $zip->close();
                return false;
            }

            // Reject symlink entries: on unix-authored zips, file-type bits
            // live in the top 16 bits of the external attributes; 0120000
            // is S_IFLNK.
            if (!$zip->getExternalAttributesIndex($i, $opsys, $externalAttr)) {
                $zip->close();
                return false;
            }
            if ($opsys === ZipArchive::OPSYS_UNIX) {
                $unixMode = ($externalAttr >> 16) & 0xFFFF;
                if (($unixMode & 0170000) === 0120000) {
                    $zip->close();
                    return false;
                }
            }

            $totalSize += $stat['size'];
            if ($totalSize > $maxTotalUncompressedBytes) {
                $zip->close();
                return false;
            }

            $normalized = self::normalizeRelativePath($name);
            if ($normalized === null) {
                $zip->close();
                return false;
            }
        }

        $extracted = $zip->extractTo($destDir);
        $zip->close();

        return $extracted;
    }

    /**
     * Normalize a zip entry's internal path and confirm it cannot escape
     * its extraction root. Returns null if the path is unsafe.
     */
    private static function normalizeRelativePath(string $path): ?string
    {
        $parts = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                // A leading .. (or one that pops past the root) escapes
                // the extraction directory — reject rather than clamp.
                if (empty($parts)) {
                    return null;
                }
                array_pop($parts);
                continue;
            }
            $parts[] = $segment;
        }

        return implode('/', $parts);
    }
}
