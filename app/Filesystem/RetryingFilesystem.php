<?php

namespace App\Filesystem;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;

class RetryingFilesystem extends Filesystem
{
    /**
     * Write the contents of a file, replacing it atomically if it already exists.
     */
    public function replace($path, $content, $mode = null): void
    {
        clearstatcache(true, $path);

        $path = realpath($path) ?: $path;
        $directory = dirname($path);

        $attempts = (int) env('FILESYSTEM_REPLACE_RETRIES', 5);
        $delayMs = (int) env('FILESYSTEM_REPLACE_DELAY_MS', 100);

        while ($attempts-- > 0) {
            $tempPath = tempnam($directory, basename($path));

            if ($tempPath === false) {
                throw new RuntimeException("Unable to create temporary file for [{$path}].");
            }

            if (! is_null($mode)) {
                @chmod($tempPath, $mode);
            } else {
                @chmod($tempPath, 0777 - umask());
            }

            file_put_contents($tempPath, $content);

            if (@rename($tempPath, $path)) {
                return;
            }

            @unlink($tempPath);

            if ($attempts > 0) {
                usleep($delayMs * 1000);
            }
        }

        $error = error_get_last();
        $message = $error['message'] ?? "Unable to replace [{$path}].";

        throw new RuntimeException($message);
    }
}
