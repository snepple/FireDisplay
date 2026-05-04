<?php
header('Content-Type: application/json');

$input = file_get_contents('php://input');
$data = json_decode($input, true);
$text = $data['contents'][0]['parts'][0]['text'] ?? '';

$response = [
    'candidates' => [
        [
            'content' => [
                'parts' => [
                    [
                        'text' => ''
                    ]
                ]
            ]
        ]
    ]
];

if (stripos($text, 'fire danger level') !== false) {
    $response['candidates'][0]['content']['parts'][0]['text'] = '{"level": "Moderate"}';
} elseif (stripos($text, 'burn permit details') !== false) {
    $response['candidates'][0]['content']['parts'][0]['text'] = json_encode([
        'name' => 'Mock User',
        'person_address' => 'Mock Address',
        'phone' => '555-MOCK',
        'email' => 'mock@example.com',
        'burn_location_address' => '',
        'burn_location_property' => 'Mock Yard',
        'burn_type' => 'Mock Burn',
        'items_to_burn' => 'Mock items',
        'expires_date' => date('Y-m-d', strtotime('+1 day'))
    ]);
} else {
    $response['candidates'][0]['content']['parts'][0]['text'] = '{"error": "unknown instruction"}';
}

echo json_encode($response);
