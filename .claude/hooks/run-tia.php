<?php

/**
 * PostToolUse hook: after Claude edits/writes a PHP class under app/ or a
 * test under tests/, re-run the suite with Pest's --tia flag so only the
 * tests impacted by that change execute. Never run --tia in CI.
 */

$payload = json_decode(stream_get_contents(STDIN), true) ?? [];
$filePath = $payload['tool_input']['file_path'] ?? null;

if (! is_string($filePath) || ! str_ends_with(strtolower($filePath), '.php')) {
    exit(0);
}

$relative = str_replace('\\', '/', $filePath);
$root = str_replace('\\', '/', dirname(__DIR__, 2));

if (str_starts_with(strtolower($relative), strtolower($root).'/')) {
    $relative = substr($relative, strlen($root) + 1);
}

$isRelatedClass = preg_match('#^app/.+\.php$#', $relative) === 1;
$isTest = preg_match('#^tests/.+\.php$#', $relative) === 1;

if (! $isRelatedClass && ! $isTest) {
    exit(0);
}

chdir(dirname(__DIR__, 2));
passthru('php vendor/bin/pest --tia --colors=never', $exitCode);

exit(0);
