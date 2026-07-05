<?php

function normalizeSmsPhone(string $phone): ?string
{
    $digits = preg_replace('/\D+/', '', $phone);
    if (!is_string($digits) || $digits === '') {
        return null;
    }

    if (strlen($digits) === 9) {
        return '351' . $digits;
    }

    if (strlen($digits) >= 11 && strlen($digits) <= 15) {
        return $digits;
    }

    return null;
}

function sendInfobipSms(array $employees, string $message, array $config): array
{
    $provider = $config['infobip'] ?? [];
    $enabled = !empty($provider['enabled']);
    $baseUrl = rtrim((string)($provider['base_url'] ?? ''), '/');
    $apiKey = trim((string)($provider['api_key'] ?? ''));
    $sender = trim((string)($provider['sender'] ?? ''));
    $timeoutSeconds = max(5, (int)($provider['timeout_seconds'] ?? 15));

    $result = [
        'configured' => $enabled && $baseUrl !== '' && $apiKey !== '' && $sender !== '',
        'sent_count' => 0,
        'failed_count' => 0,
        'skipped_count' => 0,
        'provider' => 'infobip',
        'details' => [],
        'error' => null,
    ];

    if (!$enabled) {
        $result['error'] = 'Infobip desativado na configuração.';
        return $result;
    }

    if ($baseUrl === '' || $apiKey === '' || $sender === '') {
        $result['error'] = 'Configuração do Infobip incompleta.';
        return $result;
    }

    $destinations = [];
    $employeeByPhone = [];

    foreach ($employees as $employee) {
        $employeeId = (int)($employee['id'] ?? 0);
        $employeeName = (string)($employee['name'] ?? 'Funcionário');
        $normalizedPhone = normalizeSmsPhone((string)($employee['phone'] ?? ''));

        if ($employeeId <= 0 || $normalizedPhone === null) {
            $result['skipped_count']++;
            $result['details'][] = [
                'employee_id' => $employeeId,
                'employee_name' => $employeeName,
                'phone' => (string)($employee['phone'] ?? ''),
                'status' => 'skipped',
                'error' => 'Telefone ausente ou inválido.',
            ];
            continue;
        }

        $destinations[] = ['to' => $normalizedPhone];
        $employeeByPhone[$normalizedPhone] = [
            'employee_id' => $employeeId,
            'employee_name' => $employeeName,
            'phone' => $normalizedPhone,
        ];
    }

    if (empty($destinations)) {
        $result['error'] = 'Nenhum telefone válido para envio.';
        return $result;
    }

    $payload = [
        'messages' => [
            [
                'destinations' => $destinations,
                'sender' => $sender,
                'content' => [
                    'text' => $message,
                ],
            ],
        ],
    ];

    $ch = curl_init($baseUrl . '/sms/3/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: App ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT => $timeoutSeconds,
    ]);

    $rawResponse = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($rawResponse === false) {
        $result['error'] = $curlError !== '' ? $curlError : 'Falha desconhecida ao comunicar com Infobip.';
        $result['failed_count'] = count($destinations);
        foreach ($employeeByPhone as $meta) {
            $result['details'][] = [
                'employee_id' => $meta['employee_id'],
                'employee_name' => $meta['employee_name'],
                'phone' => $meta['phone'],
                'status' => 'failed',
                'error' => $result['error'],
            ];
        }
        return $result;
    }

    $decoded = json_decode($rawResponse, true);
    if ($httpStatus < 200 || $httpStatus >= 300) {
        $result['error'] = is_array($decoded)
            ? (string)($decoded['requestError']['serviceException']['text'] ?? $decoded['message'] ?? 'Erro ao enviar SMS pelo Infobip.')
            : 'Erro ao enviar SMS pelo Infobip.';
        $result['failed_count'] = count($destinations);
        foreach ($employeeByPhone as $meta) {
            $result['details'][] = [
                'employee_id' => $meta['employee_id'],
                'employee_name' => $meta['employee_name'],
                'phone' => $meta['phone'],
                'status' => 'failed',
                'error' => $result['error'],
            ];
        }
        return $result;
    }

    $messages = $decoded['messages'] ?? [];
    if (!is_array($messages) || empty($messages)) {
        foreach ($employeeByPhone as $meta) {
            $result['sent_count']++;
            $result['details'][] = [
                'employee_id' => $meta['employee_id'],
                'employee_name' => $meta['employee_name'],
                'phone' => $meta['phone'],
                'status' => 'sent',
                'provider_message_id' => null,
                'provider_status' => null,
            ];
        }
        return $result;
    }

    foreach ($messages as $messageResult) {
        $to = preg_replace('/\D+/', '', (string)($messageResult['to'] ?? ''));
        $meta = $employeeByPhone[$to] ?? null;
        if ($meta === null) {
            continue;
        }

        $providerStatus = (string)($messageResult['status']['groupName'] ?? $messageResult['status']['description'] ?? 'PENDING');
        $providerMessageId = $messageResult['messageId'] ?? null;
        $statusLower = strtolower($providerStatus);
        $isSuccess = strpos($statusLower, 'reject') === false && strpos($statusLower, 'error') === false && strpos($statusLower, 'fail') === false;

        if ($isSuccess) {
            $result['sent_count']++;
        } else {
            $result['failed_count']++;
        }

        $result['details'][] = [
            'employee_id' => $meta['employee_id'],
            'employee_name' => $meta['employee_name'],
            'phone' => $meta['phone'],
            'status' => $isSuccess ? 'sent' : 'failed',
            'provider_message_id' => $providerMessageId,
            'provider_status' => $providerStatus,
            'error' => $isSuccess ? null : ((string)($messageResult['status']['description'] ?? 'Falha no envio.')),
        ];
    }

    return $result;
}
