<?php
if (file_exists(__DIR__ . "/logger.php")) require_once __DIR__ . "/logger.php";
header('Content-Type: application/json');

// Check config for API key
$configFile = '../config.json';
if (!file_exists($configFile)) {
    if(function_exists("sys_log")) sys_log("Gemini", "Failed to decode config.json", "error");
    echo json_encode(null);
    die();
}

$configData = json_decode(file_get_contents($configFile), true);
$apiKey = $configData['api_integrations']['gemini_api_key'] ?? '';

if (empty($apiKey)) {
    if(function_exists("sys_log")) sys_log("Gemini", "Gemini API key missing", "error");
    echo json_encode(null);
    die();
}

// Get the address from request
$address = $_GET['address'] ?? $_POST['address'] ?? '';
if (empty($address)) {
    echo json_encode(null);
    die();
}

if(function_exists("sys_log")) sys_log("Gemini", "Calling Gemini API for geocoding", "info", ["address" => $address]);

// Check cache first
$cacheFile = '../data/geocode_cache.json';
$normalizedAddress = strtolower(trim($address));
$cacheData = [];

if (file_exists($cacheFile)) {
    $cacheData = json_decode(file_get_contents($cacheFile), true) ?: [];
    if (isset($cacheData[$normalizedAddress])) {
        echo json_encode($cacheData[$normalizedAddress]);
        die();
    }
}

// Prepare the system instruction and prompt for Gemini
$systemInstruction = "You are an expert geospatial routing assistant for a local fire department. Your task is to convert free-text location descriptions from burn permits into approximate latitude and longitude coordinates.
Assume all locations are within or immediately surrounding Oakland, Maine unless explicitly stated otherwise. If the address is an intersection (e.g. 'Corner of Road A and Road B' or 'Road A & Road B'), provide the approximate coordinates of that intersection.
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
    if(function_exists("sys_log")) sys_log("Gemini", "Error in Gemini geocode", "error", ["address" => $address, "http_code" => $httpCode]);
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
    if(function_exists("sys_log")) sys_log("Gemini", "Successfully geocoded address", "success", ["address" => $address, "lat" => $parsedJson['lat'], "lon" => $parsedJson['lon']]);

    $result = [
        'lat' => (float) $parsedJson['lat'],
        'lon' => (float) $parsedJson['lon']
    ];

    // Save to cache
    if (!is_dir('../data')) {
        mkdir('../data', 0755, true);
    }

    // Re-read cache with exclusive lock to prevent race conditions
    $fp = fopen($cacheFile, 'c+');
    if ($fp && flock($fp, LOCK_EX)) {
        // Read current contents
        fseek($fp, 0);
        $currentData = '';
        while (!feof($fp)) {
            $currentData .= fread($fp, 8192);
        }

        $cacheData = json_decode($currentData, true) ?: [];
        $cacheData[$normalizedAddress] = $result;

        // Limit cache size to 1000 items
        if (count($cacheData) > 1000) {
            $cacheData = array_slice($cacheData, -1000, null, true);
        }

        // Write new contents
        ftruncate($fp, 0);
        fseek($fp, 0);
        fwrite($fp, json_encode($cacheData, JSON_PRETTY_PRINT));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
    } else {
        // Fallback if locking fails, though it shouldn't
        $cacheData[$normalizedAddress] = $result;
        if (count($cacheData) > 1000) {
            $cacheData = array_slice($cacheData, -1000, null, true);
        }
        file_put_contents($cacheFile, json_encode($cacheData, JSON_PRETTY_PRINT), LOCK_EX);
    }

    echo json_encode($result);
} else {
    if(function_exists("sys_log")) sys_log("Gemini", "Failed to extract coordinates from response", "error", ["address" => $address, "response" => $textOutput]);
    echo json_encode(null);
}
?>
