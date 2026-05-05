#!/usr/local/bin/php -q
<?php
if (file_exists(__DIR__ . "/logger.php")) require_once __DIR__ . "/logger.php";
// Script to be piped from cPanel email forwarders
// Example email addresses: danger@yourdomain.com, permits@yourdomain.com

$is_test = isset($_GET['test']) && $_GET['test'] === 'true';

// Security: Prevent unauthenticated HTTP injection while allowing mail server CLI
if (php_sapi_name() !== 'cli') {
    require_once __DIR__ . "/security_check.php";
    verify_dashboard_token();
}

if ($is_test) {
    header("Content-Type: application/json; charset=utf-8");
    $email_content = file_get_contents("php://input");
} else {
    // Read the piped email from stdin
    $email_content = file_get_contents("php://stdin");
    if (empty($email_content) && php_sapi_name() !== 'cli') {
        $email_content = file_get_contents("php://input");
    }
}

// Basic parsing logic (this is a simplified example, a robust email parser is better)
// In a real scenario, you'd use a library like PhpMimeMailParser or standard regex based on the email format.

$from = '';
if (preg_match('/^From: (.*)$/im', $email_content, $matches)) {
    $from = trim($matches[1]);
}

$to = '';
if (preg_match('/^To: (.*)$/im', $email_content, $matches)) {
    $to = strtolower(trim($matches[1]));
}

$subject = '';
if (preg_match('/^Subject: (.*)$/im', $email_content, $matches)) {
    $subject = trim($matches[1]);
}

if(function_exists("sys_log")) sys_log("Email", "Received email", "info", ["from" => $from, "to" => $to, "subject" => $subject]);

// Find empty line separating headers and body
$headers_end = strpos($email_content, "\r\n\r\n");
if ($headers_end === false) {
    $headers_end = strpos($email_content, "\n\n");
}
$body = ($headers_end !== false) ? substr($email_content, $headers_end) : $email_content;

// Read config for dynamically set routing addresses
$configFile = __DIR__ . '/../config.json';
$config = [];
if (file_exists($configFile)) {
    $config = json_decode(file_get_contents($configFile), true);
}
$danger_addr = isset($config['email_integration']['danger_address']) && !empty($config['email_integration']['danger_address']) ? strtolower($config['email_integration']['danger_address']) : 'danger';
$permit_addr = isset($config['email_integration']['permit_address']) && !empty($config['email_integration']['permit_address']) ? strtolower($config['email_integration']['permit_address']) : 'permit';

// Route based on destination address or subject
if (strpos($to, $danger_addr) !== false || strpos($subject, 'Fire Danger') !== false) {
    $data = extractFireDanger($body, $subject, $config);
    if ($is_test) {
        echo json_encode(['type' => 'danger', 'data' => $data, 'match_reason' => 'Address matched: ' . $danger_addr . ' or Subject matched: Fire Danger']);
    } else {
        file_put_contents(__DIR__ . '/../data/fire_danger.json', json_encode($data));
    }
} elseif (strpos($to, $permit_addr) !== false || strpos($subject, 'Burn Permit') !== false) {
    $data = extractBurnPermit($body, $subject);
    if ($is_test) {
        echo json_encode(['type' => 'permit', 'data' => $data, 'match_reason' => 'Address matched: ' . $permit_addr . ' or Subject matched: Burn Permit']);
    } else {
        saveBurnPermit($data);
    }
} else {
    if ($is_test) {
        echo json_encode(['type' => 'none', 'error' => 'No match found for routing']);
    }
    if(function_exists("sys_log")) sys_log("Email", "Failed to parse email. No match found for routing.", "error", ["body" => substr($body, 0, 500)]);
}

function callGeminiExtract($systemInstruction, $textToParse, $config) {
    if(function_exists("sys_log")) sys_log("Gemini", "Calling Gemini API for extraction", "info", ["instruction" => $systemInstruction, "text" => substr($textToParse, 0, 500) . "..."]);
    $apiKey = $config['api_integrations']['gemini_api_key'] ?? '';
    if (empty($apiKey)) return null;

    $payload = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $systemInstruction . "\nText to parse: " . $textToParse]
                ]
            ]
        ],
        "generationConfig" => [
            "temperature" => 0.1,
        ]
    ];

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $apiKey;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) return null;

    $responseData = json_decode($response, true);
    $textOutput = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if (empty($textOutput)) return null;

    // Clean markdown code blocks
    $textOutput = trim(str_replace(['```json', '```'], '', $textOutput));
    $parsed = json_decode($textOutput, true);
    if ($parsed && function_exists("sys_log")) sys_log("Gemini", "Successfully extracted data with Gemini", "success", ["extracted" => $parsed]);

    return $parsed;
}

function extractFireDanger($body, $subject, $config) {
    $zone = isset($config['fire_danger_zone']) ? $config['fire_danger_zone'] : '7';
    $level = "Unknown";
    $levels = ['Extreme', 'Very High', 'High', 'Moderate', 'Low'];

    $clean_body = strip_tags(str_replace(array("\n", "\r"), ' ', $body));

    if (preg_match('/Zone\s*' . preg_quote($zone, '/') . '(?:[^A-Za-z0-9]*(?:Forecast|Fire Danger)?[^A-Za-z0-9]*)?\b(' . implode('|', $levels) . ')\b/i', $clean_body, $matches)) {
        $level = ucwords(strtolower($matches[1]));
    } elseif (preg_match('/Zone\s*' . preg_quote($zone, '/') . '[^\n\r]*?\b(' . implode('|', $levels) . ')\b/i', $clean_body, $line_match)) {
        $level = ucwords(strtolower($line_match[1]));
    } else {
        foreach ($levels as $l) {
            if (stripos($clean_body, $l) !== false || stripos($subject, $l) !== false) {
                $level = $l;
                break;
            }
        }
    }

    if ($level === "Unknown") {
        $instruction = "You are an assistant for a local fire department. Extract the fire danger level from the provided email text. The possible levels are: Extreme, Very High, High, Moderate, Low. Return your response strictly as a JSON object with one key: \"level\" (string). If the level cannot be determined, return \"Unknown\". Example output: {\"level\": \"Moderate\"}";
        $geminiResult = callGeminiExtract($instruction, $subject . "\n" . $clean_body, $config);

        if ($geminiResult && isset($geminiResult['level']) && in_array(ucwords(strtolower($geminiResult['level'])), $levels)) {
            $level = ucwords(strtolower($geminiResult['level']));
        }
    }

    return [
        'level' => $level,
        'updated_at' => date('c')
    ];
}

function extractBurnPermit($body, $subject) {
    global $config;

    $clean = strip_tags(str_replace(array("\n", "\r"), ' ', $body));
    $clean = preg_replace('/\s+/', ' ', $clean);
    $clean = trim($clean);

    $name = "Unknown";
    if (preg_match('/Permission is hereby granted to:\s*(.*?)\s*\(DOB:/i', $clean, $matches)) {
        $name = trim($matches[1]);
    }

    $person_address = "Unknown Address";
    if (preg_match('/\(DOB:[^\)]+\)\s*(.*?)\s*Phone:/i', $clean, $matches)) {
        $person_address = trim($matches[1]);
    }

    $phone = "Unknown Phone";
    if (preg_match('/Phone:\s*(.*?)\s*(?:--\s*)?Email:/i', $clean, $matches)) {
        $phone = trim($matches[1]);
    }

    $email = "Unknown Email";
    if (preg_match('/Email:\s*(.*?)\s*Date\/Time Permit was Issued/i', $clean, $matches)) {
        $email = trim($matches[1]);
    }

    $burn_location_address = "";
    if (preg_match('/Address of Burn Location:\s*(.*?)\s*(?:Burn Location on the Property:|Municipality\/Unorganized Territory:|Burn Type:)/i', $clean, $matches)) {
        $burn_location_address = trim($matches[1]);
    }

    $burn_location_property = "";
    if (preg_match('/Burn Location on the Property:\s*(.*?)\s*(?:Municipality\/Unorganized Territory:|Burn Type:)/i', $clean, $matches)) {
        $burn_location_property = trim($matches[1]);
    }

    $primary_address = $person_address;
    if (!empty($burn_location_address) && trim($burn_location_address) !== '--') {
        $primary_address = $burn_location_address;
    } elseif (!empty($burn_location_property) && trim($burn_location_property) !== '--') {
        $primary_address = $burn_location_property;
    }

    $burn_type = "Open Burn";
    if (preg_match('/Burn Type:\s*(.*?)\s*Type of Item\(s\) to Burn:/i', $clean, $matches)) {
        $burn_type = trim($matches[1]);
    }

    $items_to_burn = "";
    if (preg_match('/Type of Item\(s\) to Burn:\s*(.*?)(?:\s*Burn Requirements|$)/i', $clean, $matches)) {
        $items_to_burn = trim($matches[1]);
    }

    $expires = date('c', strtotime('+1 day')); // Default
    if (preg_match('/Burning may be conducted from .*? to .*? on (\d{2}\/\d{2}\/\d{4})/i', $clean, $matches)) {
        $parsed_time = strtotime(trim($matches[1]));
        if ($parsed_time !== false) {
            $expires = date('c', $parsed_time);
        }
    }

    // Fallback to Gemini if regex fails to extract essential fields
    if ($name === "Unknown" || $primary_address === "Unknown Address") {
        $instruction = "You are an assistant for a local fire department. Extract burn permit details from the provided email text. Return your response strictly as a JSON object with the following keys: \"name\" (string), \"person_address\" (string), \"phone\" (string), \"email\" (string), \"burn_location_address\" (string), \"burn_location_property\" (string), \"burn_type\" (string), \"items_to_burn\" (string), \"expires_date\" (string, format YYYY-MM-DD). If a field cannot be found, return an empty string for it. Example output: {\"name\": \"John Doe\", \"person_address\": \"123 Main St\", \"phone\": \"555-1234\", \"email\": \"\", \"burn_location_address\": \"\", \"burn_location_property\": \"Backyard\", \"burn_type\": \"Brush\", \"items_to_burn\": \"Leaves\", \"expires_date\": \"2024-04-15\"}";
        $geminiResult = callGeminiExtract($instruction, $clean, $config);

        if ($geminiResult) {
            $name = $geminiResult['name'] ?? $name;
            $person_address = $geminiResult['person_address'] ?? $person_address;
            $phone = $geminiResult['phone'] ?? $phone;
            $email = $geminiResult['email'] ?? $email;
            $burn_location_address = $geminiResult['burn_location_address'] ?? $burn_location_address;
            $burn_location_property = $geminiResult['burn_location_property'] ?? $burn_location_property;
            $burn_type = $geminiResult['burn_type'] ?? $burn_type;
            $items_to_burn = $geminiResult['items_to_burn'] ?? $items_to_burn;

            $primary_address = $person_address;
            if (!empty($burn_location_address) && trim($burn_location_address) !== '--') {
                $primary_address = $burn_location_address;
            } elseif (!empty($burn_location_property) && trim($burn_location_property) !== '--') {
                $primary_address = $burn_location_property;
            }

            if (!empty($geminiResult['expires_date'])) {
                $parsed_time = strtotime($geminiResult['expires_date']);
                if ($parsed_time !== false) {
                    $expires = date('c', $parsed_time);
                }
            }
        }
    }

    $uid = md5($primary_address . time());

    return [
        'uid' => $uid,
        'name' => $name,
        'address' => $primary_address,
        'person_address' => $person_address,
        'phone' => $phone,
        'email' => $email,
        'burn_location_address' => $burn_location_address,
        'burn_location_property' => $burn_location_property,
        'burn_type' => $burn_type,
        'type' => $burn_type,
        'items_to_burn' => $items_to_burn,
        'expires' => $expires,
        'created_at' => date('c'),
        'details' => substr($body, 0, 500) // snippet
    ];
}

function saveBurnPermit($new_permit) {
    if(function_exists("sys_log")) sys_log("Email", "Burn permit added successfully", "success", ["permit" => $new_permit]);
    $file = __DIR__ . '/../data/permits.json';
    $permits = [];
    if (file_exists($file)) {
        $permits = json_decode(file_get_contents($file), true) ?: [];
    }

    // Add new permit
    $permits[] = $new_permit;

    // Cleanup expired
    $now = time();
    $permits = array_filter($permits, function($p) use ($now) {
        return strtotime($p['expires']) > $now;
    });

    file_put_contents($file, json_encode(array_values($permits)));
}
?>