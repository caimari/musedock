#!/usr/bin/env php
<?php
/**
 * Test customer login with proper CSRF token
 */

// Start session and get CSRF token
session_start();

// Generate CSRF token
if (!isset($_SESSION['_csrf_token'])) {
    $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['_csrf_token'];
$session_id = session_id();

echo "Session ID: $session_id\n";
echo "CSRF Token: $csrf_token\n\n";

// Prepare POST data
$postData = http_build_query([
    '_csrf_token' => $csrf_token,
    'email' => 'nonexistent@test.com',
    'password' => 'wrongpassword'
]);

// Make curl request with session cookie
$ch = curl_init('https://musedock.net/customer/login');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_COOKIE, "PHPSESSID=$session_id");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/x-www-form-urlencoded'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: $httpCode\n\n";

// Split headers and body
list($headers, $body) = explode("\r\n\r\n", $response, 2);

echo "Response Body:\n";
echo $body . "\n";
