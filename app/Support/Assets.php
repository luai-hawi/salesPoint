<?php

namespace App\Support;

class Assets
{
    /**
     * URL of a file in /public with a cache-busting query string (file modification time).
     * The service worker caches /js and /css files cache-first, so every script must be versioned.
     */
    public static function versioned(string $path): string
    {
        $file = public_path($path);
        $version = is_file($file) ? filemtime($file) : 0;

        return asset($path) . '?v=' . $version;
    }
}
