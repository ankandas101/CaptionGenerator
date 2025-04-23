<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['keyword'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Keyword is required']);
    exit;
}

$keyword = $data['keyword'];
$apiKey = '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';

// Split keywords if multiple are provided
$keywords = array_map('trim', explode(',', $keyword));

// Create a more detailed prompt based on the number of keywords
if (count($keywords) > 1) {
    $prompt = "Generate a short (1-2 lines), emotional, and engaging Facebook-style caption in Bengali that combines these themes: " . implode(', ', $keywords) . ". The caption should be catchy, creative, and relatable for young Bangladeshi audiences. Use poetic or romantic tones if suitable. Include emojis to enhance expression. Make sure to incorporate all the themes naturally.";
} else {
    $prompt = "Generate a short (1-2 lines), emotional, and engaging Facebook-style caption in Bengali about {$keyword}. The caption should be catchy, creative, and relatable for young Bangladeshi audiences. Use poetic or romantic tones if suitable. Include emojis to enhance expression.";
}

$ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $apiKey,
    'HTTP-Referer: http://localhost/caption/',
    'X-Title: Bengali Caption Generator'
]);

$postData = [
    'model' => 'openai/gpt-4.1-mini',
    'messages' => [
        [
            'role' => 'user',
            'content' => [
                [
                    'type' => 'text',
                    'text' => $prompt . " Generate 4 different variations of this caption. Each caption should be on a new line."
                ]
            ]
        ]
    ],
    'temperature' => 0.7,
    'max_tokens' => 500
];

curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Log the response for debugging
error_log("API Response: " . $response);
error_log("HTTP Code: " . $httpCode);

if ($response === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Curl error: ' . curl_error($ch)]);
    curl_close($ch);
    exit;
}

curl_close($ch);

if ($httpCode !== 200) {
    http_response_code(500);
    $errorData = json_decode($response, true);
    $errorMessage = isset($errorData['error']['message']) ? $errorData['error']['message'] : 'Failed to generate caption';
    echo json_encode(['error' => $errorMessage]);
    exit;
}

$result = json_decode($response, true);

// Log the decoded result for debugging
error_log("Decoded Result: " . print_r($result, true));

// Check for the response in the correct format
if (!isset($result['choices'][0]['message']['content'])) {
    // Try alternative response format
    if (isset($result['choices'][0]['text'])) {
        $captions = explode("\n", $result['choices'][0]['text']);
        $captions = array_filter($captions, 'trim'); // Remove empty lines
        $captions = array_slice($captions, 0, 4); // Take only first 4 captions
    } else {
        error_log("API Response Structure: " . print_r($result, true));
        http_response_code(500);
        echo json_encode(['error' => 'Invalid response format from API: ' . json_encode($result)]);
        exit;
    }
} else {
    $captions = explode("\n", $result['choices'][0]['message']['content']);
    $captions = array_filter($captions, 'trim'); // Remove empty lines
    $captions = array_slice($captions, 0, 4); // Take only first 4 captions
}

// Ensure we're sending a valid JSON response
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to encode response: ' . json_last_error_msg()]);
    exit;
}

echo json_encode(['captions' => $captions]);
?>