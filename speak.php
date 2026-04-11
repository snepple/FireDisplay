<?php
// Step 1: Retrieve the Google Cloud API Key from an environment variable.
// 🔒 IMPORTANT: Keep this key secure. Do not share it publicly.
$googleApiKey = getenv('GOOGLE_API_KEY');

if (!$googleApiKey) {
    error_log("Google TTS API Error: GOOGLE_API_KEY environment variable is not set.");
    http_response_code(500);
    exit('Server configuration error: API key missing.');
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
curl_setopt($ch, CURLOPT_URL, 'https://texttospeech.googleapis.com/v1/text:synthesize?key=' . $googleApiKey);
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
        echo base64_decode($responseData['audioContent']);
    }
} else {
    // If there was an error, log it and send an error code.
    error_log("Google TTS API Error: " . $response);
    http_response_code(500);
    exit('Failed to synthesize speech.');
}
?>