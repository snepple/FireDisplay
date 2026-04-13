<?php
header('Content-Type: application/json');

// Check config for API key
$configFile = '../config.json';
if (!file_exists($configFile)) {
    echo json_encode(null);
    die();
}

$configData = json_decode(file_get_contents($configFile), true);
$apiKey = $configData['api_integrations']['gemini_api_key'] ?? '';

if (empty($apiKey)) {
    echo json_encode(null);
    die();
}

// Get the address from request
$address = $_GET['address'] ?? $_POST['address'] ?? '';
if (empty($address)) {
    echo json_encode(null);
    die();
}

// Prepare the system instruction and prompt for Gemini
$systemInstruction = "You are an expert geospatial routing assistant for a local fire department. Your task is to convert free-text location descriptions from burn permits into approximate latitude and longitude coordinates.
Assume all locations are within or immediately surrounding Oakland, Maine unless explicitly stated otherwise.
Location description to map: " . $address . "
Return your response strictly as a JSON object with two keys: \"lat\" (float) and \"lon\" (float). Do not include any markdown formatting, explanations, or additional text. If the location is completely impossible to estimate even with the local context, return null for both values. Example output: {\"lat\": 44.5445, \"lon\": -69.7262}";

$payload = [
    "contents" => [
        [
            "parts" => [
                ["text" => $systemInstruction]
            ]
        ]
    ]
];

$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$response) {
    echo json_encode(null);
    die();
}

$responseData = json_decode($response, true);
$textOutput = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '';

if (empty($textOutput)) {
    echo json_encode(null);
    die();
}

// The output should be raw JSON string like {"lat": 44.5, "lon": -69.7}
// Sometimes Gemini might wrap it in markdown code blocks despite instructions, try to clean it
$textOutput = trim(str_replace(['```json', '```'], '', $textOutput));

$parsedJson = json_decode($textOutput, true);

if ($parsedJson && isset($parsedJson['lat']) && isset($parsedJson['lon'])) {
    echo json_encode([
        'lat' => (float) $parsedJson['lat'],
        'lon' => (float) $parsedJson['lon']
    ]);
} else {
    echo json_encode(null);
}
?>
