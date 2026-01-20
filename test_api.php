<?php

// Configuration
$secret_key = "live_lais4GLfbqmY7hTyRsSs_aEMJ-oMnQk2BtyvCtcprZDhBMh6zTttXUROaTH9ajXnL0r3hIESJ1nRTxUO12jeL-Ay";
$private_key = "-----BEGIN PRIVATE KEY-----\n" .
"MC4CAQAwBQYDK2VwBCIEIIw8bEIM1U1FpNWRJETIzfN7DD9o0oswJEbbekYTDimk\n" .
"-----END PRIVATE KEY-----";

$api_env = 'live'; // Using live key
$base_url = 'https://api.rasedi.com/v1/payment/rest/live';
$endpoint = '/create';
$relativeUrl = '/v1/payment/rest/live' . $endpoint;

// Payload
$payload = array(
    'amount' => '1000',
    'title' => 'WooCommerce Test',
    'description' => 'Testing PHP Logic',
    'gateways' => array('CREDIT_CARD'),
    'redirectUrl' => '127.0.0.1.nip.io:8080/callback',
    'callbackUrl' => '127.0.0.1.nip.io:8080/webhook',
    'collectFeeFromCustomer' => true,
    'collectCustomerEmail' => true,
    'collectCustomerPhoneNumber' => true,
);

$body = json_encode($payload);

// Signature Generation (Logic extracted from class-wc-gateway-rasedi.php)
$method = 'POST';
$key_id = $secret_key; // In our test case, we use secret key as ID or whatever was working in other SDKs (Example.java used secretKey as auth param?)
// Wait, checking Java Example: RasediClient(privateKey, secretKey).
// And header is x-id: auth.getKeyId() -> secretKey.
// So yes, x-id is the secret key string.

$raw_sign = $method . " || " . $key_id . " || " . $relativeUrl;
echo "Signing: " . $raw_sign . "\n";

$private_key_res = openssl_pkey_get_private($private_key);
if (!$private_key_res) {
    die("Invalid Private Key\n");
}

// Ed25519 is 'EdDSA' in modern OpenSSL or might need specialized handling if old OpenSSL.
// The key provided: MC4CAQAwBQYDK2VwBCIEID2nK2pCcGSbtS+U9jc2SCYxHWOo1eA4IR97bdif4+rx
// OID 1.3.101.112 (Ed25519).
// PHP 8+ supports OpenSSL logic if built with it. 
// If this script fails, it might be due to Ed25519 support in local PHP OpenSSL.
// Let's try standard openssl_sign.

// Check if OpenSSL worked
$signature = '';
$success = false;

// Try OpenSSL first (Generic)
try {
    $success = openssl_sign($raw_sign, $signature, $private_key_res, OPENSSL_ALGO_SHA256);
} catch (Exception $e) {}

if (!$success) {
   // Fallback: Try Sodium (Ed25519)
   // 1. Parse generic Ed25519 PEM to get 32-byte Seed
   // Structure typically: 30 2e 02 01 00 30 05 06 03 2b 65 70 04 22 04 20 [32-byte seed]
   // or similar wrapper. We can safely assume the last 32 bytes of the decoded DER is the private key seed for standard Ed25519 PKCS8.
    
    $clean_key = str_replace(array('-----BEGIN PRIVATE KEY-----', '-----END PRIVATE KEY-----', "\n", "\r", " "), '', $private_key);
    $der = base64_decode($clean_key);
    
    if (strlen($der) > 32) {
        $seed = substr($der, -32);
        
        if (function_exists('sodium_crypto_sign_detached') && function_exists('sodium_crypto_sign_seed_keypair')) {
            echo "Falling back to Sodium signing...\n";
            $keypair = sodium_crypto_sign_seed_keypair($seed);
            $secret_key_sodium = sodium_crypto_sign_secretkey($keypair);
            $signature = sodium_crypto_sign_detached($raw_sign, $secret_key_sodium);
            $success = true;
        } else {
            echo "Sodium extension missing.\n";
        }
    }
}

if (!$success) {
    echo "Signing failed completely.\n";
    exit(1);
}

$b64_signature = base64_encode($signature);
echo "Signature: " . $b64_signature . "\n";


// HTTP Request (using cURL)
$ch = curl_init($base_url . $endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json',
    'x-signature: ' . $b64_signature,
    'x-id: ' . $key_id
));
curl_setopt($ch, CURLOPT_VERBOSE, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);


if ($http_code < 200 || $http_code > 209) {
    echo "FAILED to Create: " . $error . "\n";
    exit(1);
}

$data = json_decode($response, true);
$referenceCode = $data['referenceCode'];
echo "Creation SUCCESS. Ref: " . $referenceCode . "\n";


// --- Helper Function for Request ---
function make_request($url, $method, $key_id, $private_key, $body = null) {
    // Generate Signature
    $parsed_url = parse_url($url);
    $relativeUrl = $parsed_url['path'];
    $raw_sign = $method . " || " . $key_id . " || " . $relativeUrl;
    
    // Sign
    $clean_key = str_replace(array('-----BEGIN PRIVATE KEY-----', '-----END PRIVATE KEY-----', "\n", "\r", " "), '', $private_key);
    $der = base64_decode($clean_key);
    $seed = substr($der, -32);
    $keypair = sodium_crypto_sign_seed_keypair($seed);
    $secret_key_sodium = sodium_crypto_sign_secretkey($keypair);
    $signature = sodium_crypto_sign_detached($raw_sign, $secret_key_sodium);
    $b64_signature = base64_encode($signature);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($body) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'x-signature: ' . $b64_signature,
        'x-id: ' . $key_id
    ));
    
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array('code' => $code, 'body' => $res);
}

// --- TEST 2: Get Status ---
echo "\n--- Testing Get Status ---\n";
$status_url = 'https://api.rasedi.com/v1/payment/rest/live/status/' . $referenceCode;
$res = make_request($status_url, 'GET', $key_id, $private_key);
echo "Status Code: " . $res['code'] . "\n";
echo "Body: " . $res['body'] . "\n";

if ($res['code'] != 200) {
    echo "FAILED to Get Status\n";
    exit(1);
}

// --- TEST 3: Cancel Payment ---
echo "\n--- Testing Cancel ---\n";
$cancel_url = 'https://api.rasedi.com/v1/payment/rest/live/cancel/' . $referenceCode;
$res = make_request($cancel_url, 'PATCH', $key_id, $private_key);
echo "Cancel Code: " . $res['code'] . "\n";
echo "Body: " . $res['body'] . "\n";

if ($res['code'] != 200) {
    echo "FAILED to Cancel\n";
    exit(1);
}

echo "\nALL TESTS PASSED.\n";

