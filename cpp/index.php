<?php
/**
 * Auth.GG C++ Encryption Bypass Server
 * 
 * This script handles encrypted communications for C++ auth.gg clients.
 * It decrypts incoming requests and returns appropriate responses.
 * 
 * Developed by https://github.com/wnelson03, founder of https://keyauth.cc
 * 
 * Auth.GG's encryption is implemented poorly, since the owner steals code
 * https://archive.is/b8WZd
 * 
 * As such, all auth.gg programs can be bypassed regardless of obfuscation.
 * 
 * Tutorial: https://files.catbox.moe/ju42i7.mp4
 * Backup: https://web.archive.org/web/20230424213606/https://files.catbox.moe/ju42i7.mp4
 * 
 * @license MIT
 */

// Configuration
define('ENCRYPTION_METHOD', 'aes-256-cfb');
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
 * Generate a random alphanumeric string
 * 
 * @param int $length Desired length of the string
 * @return string Random string
 */
function generate_random_string($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $char_count = strlen($characters);
    $random_string = '';
    
    for ($i = 0; $i < $length; $i++) {
        $random_string .= $characters[random_int(0, $char_count - 1)];
    }
    
    return $random_string;
}

/**
 * Validate and decrypt POST data
 * 
 * @param string $data Base64-encoded encrypted data
 * @param string $key Decryption key
 * @param string $iv Initialization vector
 * @return string|false Decrypted data or false on failure
 */
function decrypt_data($data, $key, $iv) {
    if (empty($data) || empty($key) || empty($iv)) {
        return false;
    }
    
    $decoded = base64_decode($data);
    if ($decoded === false) {
        return false;
    }
    
    $decrypted = openssl_decrypt($decoded, ENCRYPTION_METHOD, $key, OPENSSL_RAW_DATA, $iv);
    return ($decrypted !== false) ? $decrypted : false;
}

/**
 * Encrypt response data
 * 
 * @param string $data Data to encrypt
 * @param string $key Encryption key
 * @param string $iv Initialization vector
 * @return string|false Base64-encoded encrypted data or false on failure
 */
function encrypt_data($data, $key, $iv) {
    if (empty($data) || empty($key) || empty($iv)) {
        return false;
    }
    
    $encrypted = openssl_encrypt($data, ENCRYPTION_METHOD, $key, OPENSSL_RAW_DATA, $iv);
    if ($encrypted === false) {
        return false;
    }
    
    return base64_encode($encrypted);
}

/**
 * Handle initialization request
 * 
 * @param string $key Encryption key
 * @param string $iv Initialization vector
 * @return string Encrypted response
 */
function handle_start_request($key, $iv) {
    $rand_string = generate_random_string(10);
    
    $response_data = "Enabled|Enabled|UPDATEME|1.0|{$rand_string}|Disabled|Enabled|{$rand_string}|Enabled";
    $encrypted = encrypt_data($response_data, $key, $iv);
    
    if ($encrypted === false) {
        log_error('Failed to encrypt start response');
        return '';
    }
    
    return $encrypted . '|';
}

/**
 * Handle login request
 * 
 * @param string $aid Application ID (encrypted)
 * @param string $apikey API key (encrypted)
 * @param string $key Encryption key
 * @param string $iv Initialization vector
 * @return string Encrypted response
 */
function handle_login_request($aid, $apikey, $key, $iv) {
    // Decrypt application ID
    $decrypted_aid = decrypt_data($aid, $key, $iv);
    if ($decrypted_aid === false) {
        log_error('Failed to decrypt application ID');
        return '';
    }
    
    // Decrypt API key
    $decrypted_apikey = decrypt_data($apikey, $key, $iv);
    if ($decrypted_apikey === false) {
        log_error('Failed to decrypt API key');
        return '';
    }
    
    // Generate random IP address
    $ip = long2ip(random_int(0, 4294967295));
    
    // Build success response
    $success_data = "success" . $decrypted_apikey . $decrypted_aid . $ip;
    
    // Generate HWID and email
    $hwid = md5(generate_random_string(16));
    $email = generate_random_string(10) . '@gmail.com';
    
    // Calculate expiry date
    $expiry_date = new DateTime('now');
    $expiry_date->add(new DateInterval('P' . EXPIRY_DAYS . 'D'));
    $expiry = $expiry_date->format('Y-m-d H:i:s');
    
    // Build final response
    $response_data = $success_data . "|{$hwid}|{$email}|0|{$ip}|{$expiry}|";
    $encrypted = encrypt_data($response_data, $key, $iv);
    
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
    $type = filter_input(INPUT_POST, 'a', FILTER_SANITIZE_STRING);
    if (empty($type)) {
        http_response_code(400);
        die('Missing request type');
    }
    
    // Get and validate encryption parameters
    $enc = filter_input(INPUT_POST, 'e', FILTER_SANITIZE_STRING);
    if (empty($enc)) {
        http_response_code(400);
        die('Missing encryption data');
    }
    
    // Parse encryption key and IV
    $enc_array = explode(':', $enc);
    if (count($enc_array) < 2) {
        http_response_code(400);
        die('Invalid encryption format');
    }
    
    $key = $enc_array[0];
    $iv = $enc_array[1];
    
    // Process request based on type
    switch ($type) {
        case 'start':
            $response = handle_start_request($key, $iv);
            break;
            
        case 'login':
            $aid = filter_input(INPUT_POST, 'b', FILTER_SANITIZE_STRING);
            $apikey = filter_input(INPUT_POST, 'd', FILTER_SANITIZE_STRING);
            
            if (empty($aid) || empty($apikey)) {
                http_response_code(400);
                die('Missing login parameters');
            }
            
            $response = handle_login_request($aid, $apikey, $key, $iv);
            break;
            
        default:
            log_error('Unknown request type', ['type' => $type]);
            http_response_code(400);
            die('Unknown request type');
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
