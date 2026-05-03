<?php
define('SEMAPHORE_API_KEY', '6f4ef2e2ec634fad72c59a89950c7f82');
define('SEMAPHORE_SENDER_NAME', 'MAGARBO');

define('PHONE_OTP_EXPIRY_MINUTES', 5);
define('PHONE_OTP_RESEND_SECONDS', 60);
define('PHONE_OTP_MAX_ATTEMPTS', 5);
define('PHONE_OTP_MAX_RESENDS', 3);

function normalizePHNumber(string $phone): ?string {
    $digits = preg_replace('/\D/', '', $phone);

    if (strpos($digits, '63') === 0) {
        $digits = '0' . substr($digits, 2);
    }

    if (!preg_match('/^09\d{9}$/', $digits)) {
        return null;
    }

    return $digits;
}

function formatForSemaphore(string $phone): string {
    $normalized = normalizePHNumber($phone);

    if (!$normalized) {
        return $phone;
    }

    return '63' . substr($normalized, 1);
}

function sendSemaphoreSMS(string $number, string $message): array {
    $sendNumber = formatForSemaphore($number);

    $postData = [
        'apikey'     => SEMAPHORE_API_KEY,
        'number'     => $sendNumber,
        'message'    => $message,
        'sendername' => SEMAPHORE_SENDER_NAME
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.semaphore.co/api/v4/messages');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return [
            'success' => false,
            'message' => 'cURL error: ' . $error
        ];
    }

    $decoded = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300 && is_array($decoded)) {
        return [
            'success' => true,
            'message' => 'SMS sent successfully.',
            'sent_to' => $sendNumber,
            'raw' => $decoded
        ];
    }

    return [
        'success' => false,
        'message' => 'Semaphore API error: ' . $response,
        'sent_to' => $sendNumber
    ];
}