#!/usr/local/bin/php -q
<?php
// Script to be piped from cPanel email forwarders
// Example email addresses: danger@yourdomain.com, permits@yourdomain.com

// Read the piped email from stdin
$email_content = file_get_contents("php://stdin");

// Basic parsing logic (this is a simplified example, a robust email parser is better)
// In a real scenario, you'd use a library like PhpMimeMailParser or standard regex based on the email format.

$to = '';
if (preg_match('/^To: (.*)$/m', $email_content, $matches)) {
    $to = strtolower(trim($matches[1]));
}

$subject = '';
if (preg_match('/^Subject: (.*)$/m', $email_content, $matches)) {
    $subject = trim($matches[1]);
}

// Find empty line separating headers and body
$headers_end = strpos($email_content, "\r\n\r\n");
if ($headers_end === false) {
    $headers_end = strpos($email_content, "\n\n");
}
$body = ($headers_end !== false) ? substr($email_content, $headers_end) : $email_content;

// Route based on destination address or subject
if (strpos($to, 'danger') !== false || strpos($subject, 'Fire Danger') !== false) {
    processFireDanger($body, $subject);
} elseif (strpos($to, 'permit') !== false || strpos($subject, 'Burn Permit') !== false) {
    processBurnPermit($body, $subject);
}

function processFireDanger($body, $subject) {
    // Get the configured fire danger zone
    $configFile = __DIR__ . '/../config.json';
    $config = [];
    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true);
    }
    $zone = isset($config['fire_danger_zone']) ? $config['fire_danger_zone'] : '8';

    // Example: Looking for "Level: High" or similar
    // The exact regex depends on the email format from the state/source
    $level = "Unknown";
    // Order matters for regex: match longer words first
    $levels = ['Extreme', 'Very High', 'High', 'Moderate', 'Low'];

    // We'll try to find the fire danger level in the plain text part first.
    // The format is: "Zone X: Level Forecast: ..." or "Zone X Level" in HTML table

    // Convert HTML to plain text to make parsing easier if it's HTML, but $body might be raw.
    // Replace newlines with spaces to handle HTML tables that might have line breaks
    $clean_body = strip_tags(str_replace(array("\n", "\r"), ' ', $body));

    // Match "Zone {X}" followed by optional punctuation, then the level.
    // e.g. "Zone 8: Moderate" or "Zone 8 Moderate"
    if (preg_match('/Zone\s*' . preg_quote($zone, '/') . '(?:[^A-Za-z0-9]*(?:Forecast|Fire Danger)?[^A-Za-z0-9]*)?\b(' . implode('|', $levels) . ')\b/i', $clean_body, $matches)) {
        $level = ucwords(strtolower($matches[1]));
    } elseif (preg_match('/Zone\s*' . preg_quote($zone, '/') . '[^\n\r]*?\b(' . implode('|', $levels) . ')\b/i', $clean_body, $line_match)) {
        // Fallback to searching the rest of the line/row
        $level = ucwords(strtolower($line_match[1]));
    } else {
        // Ultimate fallback
        foreach ($levels as $l) {
            if (stripos($clean_body, $l) !== false || stripos($subject, $l) !== false) {
                $level = $l;
                break;
            }
        }
    }

    $data = [
        'level' => $level,
        'updated_at' => date('c')
    ];

    file_put_contents(__DIR__ . '/../data/fire_danger.json', json_encode($data));
}

function processBurnPermit($body, $subject) {
    // This regex logic heavily depends on the structure of the permit emails you receive.
    // Assuming format: "Address: 123 Main St", "Type: Brush", "Expires: 2024-05-20 17:00"

    $address = "Unknown Address";
    if (preg_match('/Address:\s*(.*)/i', $body, $matches)) {
        $address = trim($matches[1]);
    }

    $type = "Open Burn";
    if (preg_match('/Type:\s*(.*)/i', $body, $matches)) {
        $type = trim($matches[1]);
    }

    $expires = date('c', strtotime('+1 day')); // Default
    if (preg_match('/Expires?:\s*(.*)/i', $body, $matches)) {
        $parsed_time = strtotime(trim($matches[1]));
        if ($parsed_time !== false) {
            $expires = date('c', $parsed_time);
        }
    }

    $uid = md5($address . time());

    $new_permit = [
        'uid' => $uid,
        'address' => $address,
        'type' => $type,
        'expires' => $expires,
        'created_at' => date('c'),
        'details' => substr($body, 0, 500) // snippet
    ];

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
