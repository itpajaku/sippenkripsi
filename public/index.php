<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use SippEnkripsi\SippEnkripsi;

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/**
 * Send JSON response
 */
function sendResponse(int $statusCode, array $data): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Check API Key if configured in environment
$expectedApiKey = getenv('API_KEY') ?: ($_ENV['API_KEY'] ?? null);
if (!empty($expectedApiKey)) {
    $providedKey = null;

    // Check X-API-Key header
    if (!empty($_SERVER['HTTP_X_API_KEY'])) {
        $providedKey = $_SERVER['HTTP_X_API_KEY'];
    } elseif (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        // Check Authorization: Bearer <key>
        if (preg_match('/Bearer\s+(.*)$/i', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
            $providedKey = trim($matches[1]);
        }
    }

    if ($providedKey === null || !hash_equals($expectedApiKey, $providedKey)) {
        sendResponse(401, [
            'success' => false,
            'error' => 'Unauthorized: Invalid or missing API Key.'
        ]);
    }
}

// Parse URI path
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$defaultKey = getenv('SIPP_ENCRYPTION_KEY') ?: ($_ENV['SIPP_ENCRYPTION_KEY'] ?? null);

// Routes
if ($requestMethod === 'GET' && in_array($requestUri, ['/', '/health', '/api/health'], true)) {
    sendResponse(200, [
        'status' => 'ok',
        'service' => 'SippEnkripsi REST API',
        'version' => '1.0.0',
        'php_version' => PHP_VERSION,
        'has_default_key' => !empty($defaultKey),
        'timestamp' => time()
    ]);
}

if ($requestMethod === 'POST' && in_array($requestUri, ['/encrypt', '/api/encrypt'], true)) {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);

    if (!is_array($input) || !isset($input['data']) || $input['data'] === '') {
        sendResponse(400, [
            'success' => false,
            'error' => "Missing required field 'data' in JSON body."
        ]);
    }

    $data = (string) $input['data'];
    $key = !empty($input['key']) ? (string) $input['key'] : $defaultKey;
    $isUrlSafe = !empty($input['url_safe']);

    if (empty($key)) {
        sendResponse(400, [
            'success' => false,
            'error' => "Encryption key is not provided in payload 'key' or server SIPP_ENCRYPTION_KEY environment."
        ]);
    }

    try {
        $sipp = new SippEnkripsi($key);
        $result = $isUrlSafe ? $sipp->encodeUrlSafe($data) : $sipp->encode($data);

        sendResponse(200, [
            'success' => true,
            'result' => $result,
            'url_safe' => $isUrlSafe
        ]);
    } catch (\Throwable $e) {
        sendResponse(500, [
            'success' => false,
            'error' => 'Encryption failed: ' . $e->getMessage()
        ]);
    }
}

if ($requestMethod === 'POST' && in_array($requestUri, ['/decrypt', '/api/decrypt'], true)) {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);

    if (!is_array($input) || !isset($input['data']) || $input['data'] === '') {
        sendResponse(400, [
            'success' => false,
            'error' => "Missing required field 'data' in JSON body."
        ]);
    }

    $data = (string) $input['data'];
    $key = !empty($input['key']) ? (string) $input['key'] : $defaultKey;
    $isUrlSafe = !empty($input['url_safe']);

    if (empty($key)) {
        sendResponse(400, [
            'success' => false,
            'error' => "Encryption key is not provided in payload 'key' or server SIPP_ENCRYPTION_KEY environment."
        ]);
    }

    try {
        $sipp = new SippEnkripsi($key);
        $result = $isUrlSafe ? $sipp->decodeUrlSafe($data) : $sipp->decode($data);

        if ($result === false) {
            sendResponse(422, [
                'success' => false,
                'error' => 'Decryption failed: invalid ciphertext or incorrect encryption key.'
            ]);
        }

        sendResponse(200, [
            'success' => true,
            'result' => $result,
            'url_safe' => $isUrlSafe
        ]);
    } catch (\Throwable $e) {
        sendResponse(500, [
            'success' => false,
            'error' => 'Decryption error: ' . $e->getMessage()
        ]);
    }
}

// 404 Not Found
sendResponse(404, [
    'success' => false,
    'error' => 'Endpoint not found. Available endpoints: GET /health, POST /encrypt, POST /decrypt'
]);
