<?php
// Carrega variáveis do .env para getenv()/$_ENV/$_SERVER, sem sobrescrever valores já definidos.
if (!function_exists('rhneto_load_env')) {
    function rhneto_load_env(string $envPath): void
    {
        if (!file_exists($envPath)) {
            return;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || strpos($trimmed, '#') === 0 || strpos($trimmed, '=') === false) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $trimmed, 2));
            if ($key === '') {
                continue;
            }

            $hasDoubleQuotes = strlen($value) >= 2 && substr($value, 0, 1) === '"' && substr($value, -1) === '"';
            $hasSingleQuotes = strlen($value) >= 2 && substr($value, 0, 1) === "'" && substr($value, -1) === "'";
            if ($hasDoubleQuotes || $hasSingleQuotes) {
                $value = substr($value, 1, -1);
            }

            if (getenv($key) === false) {
                putenv($key . '=' . $value);
            }
            if (!isset($_ENV[$key])) {
                $_ENV[$key] = $value;
            }
            if (!isset($_SERVER[$key])) {
                $_SERVER[$key] = $value;
            }
        }
    }
}

rhneto_load_env(dirname(__DIR__) . '/.env');
