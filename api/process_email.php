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
    // Example: Looking for "Level: High" or similar
    // The exact regex depends on the email format from the state/source
    $level = "Unknown";
    $levels = ['Low', 'Moderate', 'High', 'Very High', 'Extreme'];

    foreach ($levels as $l) {
        if (stripos($body, $l) !== false || stripos($subject, $l) !== false) {
            $level = $l;
            break;
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
