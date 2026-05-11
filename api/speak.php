<?php
require_once __DIR__ . "/security_check.php";
verify_dashboard_token();

// Check config for API key
$configFile = '../config.json';
if (!file_exists($configFile)) {
    http_response_code(500);
    exit('Config file missing.');
}

$configData = json_decode(file_get_contents($configFile), true);
$apiKey = $configData['api_integrations']['google_tts_api_key'] ?? '';

if (empty($apiKey)) {
    http_response_code(500);
    exit('TTS API Key not configured.');
}

// Step 2: Set the content type to audio/mpeg for the MP3 response.
header('Content-Type: audio/mpeg');

// Step 3: Get the text from the incoming request.
$postData = json_decode(file_get_contents('php://input'), true);
$text = isset($postData['text']) ? $postData['text'] : '';

if (empty($text)) {
    // If no text is provided, exit gracefully.
    http_response_code(400);
    exit('No text provided.');
}

$cacheDir = __DIR__ . '/../data/tts_cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0777, true);
}

$cacheKey = md5($text);
$cacheFile = $cacheDir . '/' . $cacheKey . '.mp3';

// Background cleanup of old TTS cache files
register_shutdown_function(function() use ($cacheDir) {
    if (rand(1, 50) === 1) { // 2% chance
        $now = time();
        try {
            $dir = new DirectoryIterator($cacheDir);
            foreach ($dir as $fileInfo) {
                if ($fileInfo->isFile() && $fileInfo->getExtension() === 'mp3') {
                    if (($now - $fileInfo->getMTime()) > 2592000) { // older than 30 days
                        @unlink($fileInfo->getRealPath());
                    }
                }
            }
        } catch (Exception $e) {
            // Silence errors during cleanup
        }
    }
});

if (file_exists($cacheFile)) {
    readfile($cacheFile);
    die();
}

// Step 4: Prepare the request data for the Google Cloud TTS API.
$requestData = [
    'input' => [
        'text' => $text
    ],
    'voice' => [
        // You can experiment with other voices, e.g., 'en-US-Studio-M' or 'en-US-Wavenet-D'
        'languageCode' => 'en-US',
        'name' => 'en-US-Studio-M'
    ],
    'audioConfig' => [
        'audioEncoding' => 'MP3'
    ]
];

// Step 5: Make the API call using cURL.
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://texttospeech.googleapis.com/v1/text:synthesize?key=' . $apiKey);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Step 6: Process the response from Google.
if ($httpcode == 200) {
    $responseData = json_decode($response, true);
    // The audio comes back base64-encoded, so we need to decode it.
    if (isset($responseData['audioContent'])) {
        $decodedAudio = base64_decode($responseData['audioContent']);
        file_put_contents($cacheFile, $decodedAudio);
        echo $decodedAudio;
    }
} else {
    // If there was an error, log it and send an error code.
    error_log("Google TTS API Error: " . $response);
    http_response_code(500);
    exit('Failed to synthesize speech.');
}
?>