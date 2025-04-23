<?php
// Ensure no output before headers
ob_start();

// Set proper headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to the client
ini_set('log_errors', 1); // Log errors instead

// Function to send JSON response
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(['error' => 'Method not allowed'], 405);
}

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validate input
if (json_last_error() !== JSON_ERROR_NONE) {
    sendJsonResponse(['error' => 'Invalid JSON input'], 400);
}

if (!isset($data['keyword'])) {
    sendJsonResponse(['error' => 'Keyword is required'], 400);
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

try {
    $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
    if ($ch === false) {
        throw new Exception('Failed to initialize cURL');
    }

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

    if ($response === false) {
        throw new Exception('Curl error: ' . curl_error($ch));
    }

    curl_close($ch);

    if ($httpCode !== 200) {
        $errorData = json_decode($response, true);
        $errorMessage = isset($errorData['error']['message']) ? $errorData['error']['message'] : 'Failed to generate caption';
        throw new Exception($errorMessage);
    }

    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON response from API');
    }

    // Check for the response in the correct format
    if (!isset($result['choices'][0]['message']['content'])) {
        if (isset($result['choices'][0]['text'])) {
            $captions = explode("\n", $result['choices'][0]['text']);
        } else {
            throw new Exception('Invalid response format from API');
        }
    } else {
        $captions = explode("\n", $result['choices'][0]['message']['content']);
    }

    $captions = array_filter($captions, 'trim'); // Remove empty lines
    $captions = array_slice($captions, 0, 4); // Take only first 4 captions

    sendJsonResponse(['captions' => $captions]);

} catch (Exception $e) {
    error_log("Error in generate.php: " . $e->getMessage());
    sendJsonResponse(['error' => $e->getMessage()], 500);
}
?>