<?php

/*
| Idempotently set KEY=VALUE pairs in a .env file.
| Usage: printf 'KEY=value\n...' | php set-env.php /path/to/.env
| Values are written single-quoted (literal) so passwords with $ or # are safe.
*/

$file = $argv[1] ?? '.env';
$env = file_exists($file) ? file_get_contents($file) : '';

foreach (preg_split('/\r?\n/', stream_get_contents(STDIN)) as $line) {
    if (! str_contains($line, '=')) {
        continue;
    }
    [$key, $value] = explode('=', $line, 2);
    $key = trim($key);
    if (! preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
        fwrite(STDERR, "Skipping invalid key {$key}\n");

        continue;
    }

    $quoted = ! str_contains($value, "'")
        ? "'{$value}'"
        : '"'.addcslashes($value, "\"\\\$").'"';

    $pattern = '/^#?\s*'.preg_quote($key, '/').'=.*$/m';
    $env = preg_match($pattern, $env)
        ? preg_replace_callback($pattern, fn () => "{$key}={$quoted}", $env, 1)
        : rtrim($env)."\n{$key}={$quoted}\n";
}

file_put_contents($file, $env);
chmod($file, 0600);
