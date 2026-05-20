<?php

namespace Webhub\BackupViewer\Services;

use Throwable;

/**
 * Detects whether the first entry of a ZIP archive is password-protected
 * by peeking at the "general purpose bit flag" of the local file header.
 * Bit 0 (0x01) signals that the entry data is encrypted.
 *
 * Returns null when we cannot determine encryption status (read errors,
 * truncated files, non-ZIP magic, ...).
 *
 * Only call this on local files — remote disks would require downloading
 * the file just to peek the header, which defeats the purpose.
 */
class EncryptionDetector
{
    public function isEncrypted(string $absolutePath): ?bool
    {
        try {
            $handle = @fopen($absolutePath, 'rb');
        } catch (Throwable) {
            return null;
        }

        if ($handle === false) {
            return null;
        }

        try {
            $header = fread($handle, 8);
        } catch (Throwable) {
            return null;
        } finally {
            fclose($handle);
        }

        if (! is_string($header) || strlen($header) < 8) {
            return null;
        }

        // Local file header signature is 0x04034b50 (little-endian "PK\x03\x04").
        if (substr($header, 0, 4) !== "PK\x03\x04") {
            return null;
        }

        // Bytes 6-7 are the general purpose bit flag (little-endian uint16).
        $flag = unpack('v', substr($header, 6, 2));

        if (! is_array($flag) || ! isset($flag[1])) {
            return null;
        }

        return (bool) ($flag[1] & 0x01);
    }
}
