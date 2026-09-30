<?php
declare(strict_types=1);

/**
 * Minimal file-based rate limiter (no external dependencies).
 * Good enough for a single-server deployment; for multi-server setups,
 * replace this with Redis or rate limiting at the reverse-proxy level (nginx/Cloudflare).
 */
function dd_rate_limit_check(string $bucket, int $maxAttempts, int $windowSeconds): bool
{
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ip = explode(',', $ip)[0];
    $key = preg_replace('/[^a-zA-Z0-9._-]/', '_', $bucket . '_' . $ip);

    $dir = __DIR__ . '/storage';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $file = $dir . '/ratelimit_' . $key . '.json';

    $fp = fopen($file, 'c+');
    if (!$fp) {
        // If we can't rate-limit, fail open rather than blocking legitimate traffic.
        return true;
    }

    flock($fp, LOCK_EX);
    $contents = stream_get_contents($fp);
    $timestamps = $contents ? json_decode($contents, true) : [];
    if (!is_array($timestamps)) {
        $timestamps = [];
    }

    $now = time();
    $timestamps = array_values(array_filter($timestamps, fn ($t) => $t > $now - $windowSeconds));

    $allowed = count($timestamps) < $maxAttempts;
    if ($allowed) {
        $timestamps[] = $now;
    }

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($timestamps));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    return $allowed;
}
