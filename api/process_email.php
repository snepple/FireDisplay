#!/usr/local/bin/php -q
<?php
// Script to be piped from cPanel email forwarders
// Example email addresses: danger@yourdomain.com, permits@yourdomain.com

$is_test = isset($_GET['test']) && $_GET['test'] === 'true';

if ($is_test) {
    header("Content-Type: application/json; charset=utf-8");
    $email_content = file_get_contents("php://input");
} else {
    // Read the piped email from stdin
    $email_content = file_get_contents("php://stdin");
}

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
}

function extractFireDanger($body, $subject, $config) {
    $zone = isset($config['fire_danger_zone']) ? $config['fire_danger_zone'] : '8';
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

    return [
        'level' => $level,
        'updated_at' => date('c')
    ];
}

function extractBurnPermit($body, $subject) {
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
    if (preg_match('/Address of Burn Location:\s*(.*?)\s*(?:Burn Location on the Property:|Municipality\/Unorganized Territory:)/i', $clean, $matches)) {
        $burn_location_address = trim($matches[1]);
    }

    $primary_address = $burn_location_address ? $burn_location_address : $person_address;

    $burn_location_property = "";
    if (preg_match('/Burn Location on the Property:\s*(.*?)\s*Municipality\/Unorganized Territory:/i', $clean, $matches)) {
        $burn_location_property = trim($matches[1]);
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