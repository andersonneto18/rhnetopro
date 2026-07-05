<?php

$defaultSmsConfig = [
    'infobip' => [
        'enabled' => filter_var(getenv('INFOBIP_ENABLED') ?: '0', FILTER_VALIDATE_BOOLEAN),
        'base_url' => rtrim((string)(getenv('INFOBIP_BASE_URL') ?: ''), '/'),
        'api_key' => trim((string)(getenv('INFOBIP_API_KEY') ?: '')),
        'sender' => trim((string)(getenv('INFOBIP_SENDER') ?: '')),
        'timeout_seconds' => max(5, (int)(getenv('INFOBIP_TIMEOUT') ?: 15)),
    ],
];

$localConfigPath = __DIR__ . '/sms_config.local.php';
if (file_exists($localConfigPath)) {
    $localSmsConfig = require $localConfigPath;
    if (is_array($localSmsConfig)) {
        $defaultSmsConfig = array_replace_recursive($defaultSmsConfig, $localSmsConfig);
    }
}

return $defaultSmsConfig;
