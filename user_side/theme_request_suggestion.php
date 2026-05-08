<?php
session_name('USERSESSID');
session_start();

require_once '../db_connection.php';
require_once 'gemini_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit();
}

$themeId   = (int)($_POST['theme_id'] ?? 0);
$themeName = trim($_POST['theme_name'] ?? '');
$eventType = trim($_POST['event_type'] ?? '');

if ($themeId <= 0 && $themeName === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Missing theme information.'
    ]);
    exit();
}

$requests = [];
$savedSuggestion = '';

function collectRequests($res, &$requests, &$savedSuggestion) {
    while ($row = mysqli_fetch_assoc($res)) {
        $request = trim($row['request'] ?? '');
        $suggestion = trim($row['suggestion_text'] ?? '');

        if ($request !== '') {
            $requests[] = $request;
        }

        if ($suggestion !== '' && $savedSuggestion === '') {
            $savedSuggestion = $suggestion;
        }
    }
}

if ($themeId > 0) {
    $sql = "SELECT request, suggestion_text
            FROM bookings
            WHERE booking_status = 'Approved'
              AND selected_theme_id = ?
              AND request IS NOT NULL
              AND TRIM(request) <> ''
            ORDER BY created_at DESC, id DESC
            LIMIT 5";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $themeId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    collectRequests($res, $requests, $savedSuggestion);

    mysqli_stmt_close($stmt);
}

if (empty($requests) && $themeName !== '') {
    $sql = "SELECT request, suggestion_text
            FROM bookings
            WHERE booking_status = 'Approved'
              AND selected_theme = ?
              AND request IS NOT NULL
              AND TRIM(request) <> ''
            ORDER BY created_at DESC, id DESC
            LIMIT 5";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $themeName);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    collectRequests($res, $requests, $savedSuggestion);

    mysqli_stmt_close($stmt);
}

if ($savedSuggestion !== '') {
    $requests[] = $savedSuggestion;
}

$requests = array_values(array_unique(array_filter($requests)));

$requestCount = count($requests);

if ($requestCount === 0) {
    $mode = 'generate';
} elseif ($requestCount === 1) {
    $mode = 'refine';
} else {
    $mode = 'combine';
}

$cleanThemeName = preg_replace('/[^a-zA-Z0-9\s\-]/', '', $themeName);
$cleanThemeName = trim($cleanThemeName);

if (
    $cleanThemeName === '' ||
    strlen($cleanThemeName) < 4 ||
    !preg_match('/[aeiouAEIOU]/', $cleanThemeName)
) {
    $cleanThemeName = '';
}

if ($eventType === '') {
    $eventType = 'event';
}

if ($mode === 'refine') {
    $prompt = "You are rewriting a client's event request into a new version.

Theme: {$cleanThemeName}
Event type: {$eventType}

Past client request:
{$requests[0]}

Task:
Rewrite the request into a DIFFERENT version with the SAME meaning.

Rules:
- Do not copy the original sentence.
- Change the wording and sentence structure.
- Keep the same main idea.
- Make it sound natural, polished, and professional.
- Keep it to 1 to 2 sentences only.
- No quotation marks.
- No bullet points.";
} elseif ($mode === 'combine') {
    $allRequests = implode("\n- ", $requests);

    $prompt = "You are analyzing multiple client requests for the same event theme.

Theme: {$cleanThemeName}
Event type: {$eventType}

Client requests:
- {$allRequests}

Task:
Extract the common ideas and create ONE new professional booking request suggestion.

Rules:
- Do not copy any sentence directly.
- Combine only the useful main ideas.
- Focus on setup, decorations, mood, arrangement, and overall presentation.
- Make it sound natural, polished, and professional.
- Keep it to 1 to 2 sentences only.
- No quotation marks.
- No bullet points.";
} else {
    $prompt = "You are generating a new booking request suggestion for an event booking system.

Theme: {$cleanThemeName}
Event type: {$eventType}

Task:
Create one short professional booking request suggestion.

Rules:
- If the theme name is clear, use its idea naturally.
- If the theme name is unclear, random, or meaningless, ignore it and focus on the event type.
- Focus on setup, decorations, mood, arrangement, and overall presentation.
- Make it sound natural, polished, and professional.
- Keep it to 1 to 2 sentences only.
- No quotation marks.
- No bullet points.";
}

$payload = [
    'contents' => [[
        'parts' => [[
            'text' => $prompt
        ]]
    ]]
];

$endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/' 
    . rawurlencode(GEMINI_MODEL) 
    . ':generateContent?key=' 
    . urlencode(GEMINI_API_KEY);

$ch = curl_init($endpoint);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

$ch = null;

if ($response === false || $httpCode >= 400) {

    $errorData = json_decode($response, true);
    $status = $errorData['error']['status'] ?? '';
    $errorMessage = $errorData['error']['message'] ?? '';

    if ($status === 'RESOURCE_EXHAUSTED') {

        if (!empty($requests)) {
            echo json_encode([
                'success' => true,
                'suggestion' => $requests[0],
                'source' => 'quota_fallback'
            ]);
            exit();
        }

        echo json_encode([
            'success' => true,
            'suggestion' => 'Please provide your preferred event setup, decorations, color motif, and overall style.',
            'source' => 'quota_default'
        ]);
        exit();
    }

    error_log("Gemini Error: HTTP {$httpCode} | {$errorMessage} | {$response}");

    echo json_encode([
        'success' => false,
        'message' => 'Gemini API failed.',
        'debug' => $errorMessage,
        'source' => 'gemini_error'
    ]);
    exit();
}

$data = json_decode($response, true);
$aiSuggestion = trim(
    $data['candidates'][0]['content']['parts'][0]['text']
    ?? ''
);

if ($aiSuggestion === '') {

    if (!empty($requests)) {
        echo json_encode([
            'success' => true,
            'suggestion' => $requests[0],
            'source' => 'empty_fallback'
        ]);
        exit();
    }

    echo json_encode([
        'success' => true,
        'suggestion' => 'Please provide your preferred event setup, decorations, color motif, and overall style.',
        'source' => 'empty_default'
    ]);
    exit();
}

echo json_encode([
    'success' => true,
    'suggestion' => $aiSuggestion,
    'source' => $mode === 'refine' ? 'gemini_refine' : ($mode === 'combine' ? 'gemini_combine' : 'gemini_generate')
]);