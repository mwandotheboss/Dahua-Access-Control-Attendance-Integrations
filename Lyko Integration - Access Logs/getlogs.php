<?php

//include_once('directory.php');

//http://{{host}}/brms/api/v1.0/accounts/authorize

//This is the first login

// Define constants for the URL and credentials
define('API_URL', 'https://41.139.152.133:443/brms/api/v1.0/accounts/authorize');
define('USERNAME', 'mwando');
define('PASSWORD', 'Admin@123');

// Function to perform a cURL request
function performCurlRequest($url, $postFields = null, $headers = []) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Disable SSL verification
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Disable hostname verification
    if ($postFields) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    }
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Curl error: ' . curl_error($ch);
    }
    curl_close($ch);
    return $response;
}

// First login to get the random key
$response = performCurlRequest(API_URL, json_encode([
    "userName" => USERNAME,
    "clientType" => "WINPC_V2"
]), [
    "Content-Type: application/json;charset=UTF-8",
    "Accept-Language: en",
    "User-Agent: PHP Script",
    "Referer: https://41.139.152.133:443/brms",
    "Time-Zone: UTC"
]);

if ($response === false) {
    die('Error: Unable to connect to the server.');
}

$lines = explode(",", $response);

if (count($lines) < 2) {
    die('Error: Unexpected response format.');
}

$full = explode(":", $lines[1]);
$full2 = explode(":", $lines[0]);

if (count($full) < 2 || count($full2) < 2) {
    die('Error: Unexpected response format.');
}

$realm = trim(str_replace('"', '', $full2[1]));
$randomkey = trim(str_replace('"', '', $full[1]));

// Generate signature
$temp1 = md5(PASSWORD);
$temp2 = md5(USERNAME . $temp1);
$temp3 = md5($temp2);
$temp4 = md5(USERNAME . ":" . $realm . ":" . $temp3);
$signatureString = $temp4 . ":" . $randomkey;
$finalSignature = md5($signatureString);

// Debugging: Output the generated signature and other details
echo "Generated Signature: " . $finalSignature . "\n";
echo "Realm: " . $realm . "\n";
echo "Random Key: " . $randomkey . "\n";

// Define the request payload
$requestPayload = [
    "randomKey" => $randomkey,
    "userName" => USERNAME,
    "signature" => $finalSignature,
    "password" => PASSWORD,
    "encryptType" => "MD5",
    "clientType" => "WINPC_V2"
];

// Log the request payload
echo "Request Payload: " . json_encode($requestPayload) . "\n";

// Define headers
$headers = [
    "Content-Type: application/json;charset=UTF-8",
    "Accept-Language: en",
    "User-Agent: PHP Script",
    "Referer: https://41.139.152.133:443/brms",
    "Time-Zone: UTC"
];

// Log the headers
echo "Request Headers: " . json_encode($headers) . "\n";

// Second login with the generated signature
$request = json_encode($requestPayload);

$response = performCurlRequest(API_URL, $request, $headers);

if ($response === false) {
    die('Error: Unable to connect to the server.');
}

$responseData = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    die('Error: Invalid JSON response.');
}

if (isset($responseData['error'])) {
    die('API Error: ' . $responseData['error']);
}

// Log the full response
echo "Full Response: " . $response . "\n";

echo "Response: " . $response;

?>