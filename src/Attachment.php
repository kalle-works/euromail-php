<?php

namespace EuroMail;

final class Attachment
{
    public static function fromFile(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \InvalidArgumentException(sprintf('File "%s" is not readable.', $path));
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new \InvalidArgumentException(sprintf('Failed to read file "%s".', $path));
        }

        return [
            'filename' => basename($path),
            'content_type' => self::detectContentType($path),
            'content' => base64_encode($content),
        ];
    }

    private static function detectContentType(string $path): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $detected = finfo_file($finfo, $path);
                finfo_close($finfo);
                if (is_string($detected) && $detected !== '') {
                    return $detected;
                }
            }
        }

        return 'application/octet-stream';
    }
}
