<?php
/**
 * Auth.GG C# Encryption Bypass Server
 * 
 * This script handles encrypted communications for C# auth.gg clients.
 * It decrypts incoming requests and returns appropriate responses.
 * 
 * Developed by https://github.com/wnelson03, founder of https://keyauth.cc
 * 
 * Auth.GG's encryption is implemented poorly, since the owner steals code
 * https://archive.is/b8WZd
 * 
 * As such, all auth.gg programs can be bypassed regardless of obfuscation.
 * 
 * Tutorial: https://youtu.be/LtiPOj6DuAg?t=36
 * Backup: https://files.catbox.moe/8nm18s.mp4
 * 
 * @license MIT
 */

// Configuration
define('ENCRYPTION_METHOD', 'aes-256-cbc');
define('EXPIRY_DAYS', 5);
define('LOG_ERRORS', true);

// Set response headers
header('Content-Type: text/plain; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

/**
 * Log error messages for debugging
 * 
 * @param string $message Error message to log
 * @param array $context Additional context data
 */
function log_error($message, $context = []) {
    if (LOG_ERRORS) {
        $log_entry = date('Y-m-d H:i:s') . ' - ' . $message;
        if (!empty($context)) {
            $log_entry .= ' | Context: ' . json_encode($context);
        }
        error_log($log_entry . PHP_EOL, 3, __DIR__ . '/auth_proxy.log');
    }
}

/**
 * Decrypt incoming data using AES-256-CBC
 * 
 * @param string $string Base64-encoded encrypted data
 * @return string|false Decrypted data or false on failure
 */
function decrypt($string) {
    if (empty($string)) {
        return false;
    }
    
    $password = filter_input(INPUT_POST, 'api_key', FILTER_SANITIZE_STRING);
    $iv = filter_input(INPUT_POST, 'api_id', FILTER_SANITIZE_STRING);
    
    if (empty($password) || empty($iv)) {
        log_error('Missing decryption parameters');
        return false;
    }
    
    $password = base64_decode($password);
    $iv = base64_decode($iv);
    
    if ($password === false || $iv === false) {
        log_error('Failed to decode encryption parameters');
        return false;
    }
    
    // Derive key using SHA-256
    $key = substr(hash('sha256', $password, true), 0, 32);
    
    $decoded = base64_decode($string);
    if ($decoded === false) {
        log_error('Failed to base64 decode data');
        return false;
    }
    
    $decrypted = openssl_decrypt($decoded, ENCRYPTION_METHOD, $key, OPENSSL_RAW_DATA, $iv);
    if ($decrypted === false) {
        log_error('OpenSSL decryption failed');
        return false;
    }
    
    return $decrypted;
}

/**
 * Encrypt response data using AES-256-CBC
 * 
 * @param string $string Data to encrypt
 * @return string|false Base64-encoded encrypted data or false on failure
 */
function encrypt($string) {
    if (empty($string)) {
        return false;
    }
    
    $password = filter_input(INPUT_POST, 'api_key', FILTER_SANITIZE_STRING);
    $iv = filter_input(INPUT_POST, 'api_id', FILTER_SANITIZE_STRING);
    
    if (empty($password) || empty($iv)) {
        log_error('Missing encryption parameters');
        return false;
    }
    
    $password = base64_decode($password);
    $iv = base64_decode($iv);
    
    if ($password === false || $iv === false) {
        log_error('Failed to decode encryption parameters');
        return false;
    }
    
    // Derive key using SHA-256
    $key = substr(hash('sha256', $password, true), 0, 32);
    
    $encrypted = openssl_encrypt($string, ENCRYPTION_METHOD, $key, OPENSSL_RAW_DATA, $iv);
    if ($encrypted === false) {
        log_error('OpenSSL encryption failed');
        return false;
    }
    
    return base64_encode($encrypted);
}

/**
 * Generate a random numeric string
 * 
 * @param int $length Desired length of the string
 * @return string Random numeric string
 */
function generate_random_number($length = 1) {
    $result = '';
    for ($i = 0; $i < $length; $i++) {
        $result .= random_int(0, 9);
    }
    return $result;
}

/**
 * Handle initialization request
 * 
 * @param string $token Request token
 * @param string $timestamp Request timestamp
 * @return string Encrypted response
 */
function handle_start_request($token, $timestamp) {
    // Generate random hash
    $rand_hash = md5(random_bytes(32));
    
    // Build response
    $response_data = "{$token}|{$timestamp}|success|Enabled|Enabled|{$rand_hash}|1.0||Disabled|Enabled|{$rand_hash}|Enabled|Disabled|" . generate_random_number(3);
    
    $encrypted = encrypt($response_data);
    if ($encrypted === false) {
        log_error('Failed to encrypt start response');
        return '';
    }
    
    return $encrypted;
}

/**
 * Handle login request
 * 
 * @param string $token Request token
 * @param string $timestamp Request timestamp
 * @param string $username Username
 * @return string Encrypted response
 */
function handle_login_request($token, $timestamp, $username) {
    // Generate Windows-style HWID
    $hwid = "S-1-5-21-" . generate_random_number(9) . "-" . generate_random_number(10) . "-" . generate_random_number(10) . "-" . generate_random_number(4);
    
    // Generate random IP address
    $ip = long2ip(random_int(0, 4294967295));
    
    // Calculate dates
    $expiry_date = new DateTime('now');
    $expiry_date->add(new DateInterval('P' . EXPIRY_DAYS . 'D'));
    $expiry = $expiry_date->format('Y-m-d H:i:s');
    
    $last_login = date('Y-m-d H:i:s', strtotime('-1 days'));
    $register_date = date('Y-m-d H:i:s', strtotime('-2 days'));
    
    // Build response
    $response_data = "{$token}|{$timestamp}|success|" . generate_random_number(7) . "|{$username}|{$username}|{$username}|{$hwid}||1|{$ip}|{$expiry}|{$last_login}|{$register_date}||https://i.imgur.com/xn4APqWs.gif";
    
    $encrypted = encrypt($response_data);
    if ($encrypted === false) {
        log_error('Failed to encrypt login response');
        return '';
    }
    
    return $encrypted;
}

// Main request handling
try {
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        die('Method Not Allowed');
    }
    
    // Get and validate request type
    $type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);
    if (empty($type)) {
        http_response_code(400);
        die('Missing request type');
    }
    
    // Decrypt request type
    $decrypted_type = decrypt($type);
    if ($decrypted_type === false) {
        http_response_code(400);
        die('Failed to decrypt request type');
    }
    
    // Get and decrypt application ID
    $aid = filter_input(INPUT_POST, 'aid', FILTER_SANITIZE_STRING);
    if (empty($aid)) {
        http_response_code(400);
        die('Missing application ID');
    }
    
    $decrypted_aid = decrypt($aid);
    if ($decrypted_aid === false) {
        http_response_code(400);
        die('Failed to decrypt application ID');
    }
    
    // Extract length prefix
    $length = intval(substr($decrypted_aid, 0, 2));
    if ($length <= 0 || $length > 50) {
        http_response_code(400);
        die('Invalid length prefix');
    }
    
    // Get and decrypt token
    $token_encrypted = filter_input(INPUT_POST, 'token', FILTER_SANITIZE_STRING);
    if (empty($token_encrypted)) {
        http_response_code(400);
        die('Missing token');
    }
    
    $token = substr($token_encrypted, 0, -$length);
    $token = decrypt($token);
    if ($token === false) {
        http_response_code(400);
        die('Failed to decrypt token');
    }
    
    // Get and decrypt timestamp
    $timestamp_encrypted = filter_input(INPUT_POST, 'timestamp', FILTER_SANITIZE_STRING);
    if (empty($timestamp_encrypted)) {
        http_response_code(400);
        die('Missing timestamp');
    }
    
    $timestamp = substr($timestamp_encrypted, 0, -$length);
    $timestamp = decrypt($timestamp);
    if ($timestamp === false) {
        http_response_code(400);
        die('Failed to decrypt timestamp');
    }
    
    // Get and decrypt username
    $username_encrypted = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    if (empty($username_encrypted)) {
        http_response_code(400);
        die('Missing username');
    }
    
    $username = decrypt($username_encrypted);
    if ($username === false) {
        http_response_code(400);
        die('Failed to decrypt username');
    }
    
    // Process request based on type
    switch ($decrypted_type) {
        case 'start':
            $response = handle_start_request($token, $timestamp);
            break;
            
        case 'login':
            $response = handle_login_request($token, $timestamp, $username);
            break;
            
        case 'log':
            // Log request (no response needed)
            log_error('Log request received', ['username' => $username]);
            http_response_code(200);
            exit;
            
        default:
            // Default response for unknown types
            log_error('Unknown request type', ['type' => $decrypted_type]);
            $response_data = "{$token}|{$timestamp}|success";
            $response = encrypt($response_data);
            break;
    }
    
    // Send response
    if (empty($response)) {
        http_response_code(500);
        die('Internal server error');
    }
    
    die($response);
    
} catch (Exception $e) {
    log_error('Unhandled exception', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    http_response_code(500);
    die('Internal server error');
} catch (Error $e) {
    log_error('Fatal error', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    http_response_code(500);
    die('Internal server error');
}
