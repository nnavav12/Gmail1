<?php
/**
 * Session Initialization for cPanel/Linux Compatibility
 * Include this file at the top of every PHP file that needs sessions
 * This fixes session directory permission issues on cPanel
 */

// Set custom session save path for cPanel compatibility
if (!is_dir(__DIR__ . '/session/tmp')) {
    @mkdir(__DIR__ . '/session/tmp', 0755, true);
}
if (is_writable(__DIR__ . '/session/tmp')) {
    ini_set('session.save_path', __DIR__ . '/session/tmp');
}

// Set session cookie parameters for better persistence
ini_set('session.cookie_lifetime', 3600); // 1 hour
ini_set('session.gc_maxlifetime', 3600);
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);

// Start session if not already active
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
