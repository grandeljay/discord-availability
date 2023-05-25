<?php

use Discord\Interaction;
use Discord\InteractionResponseType;

include __DIR__ . '/vendor/autoload.php';

$CLIENT_PUBLIC_KEY = '7bf824dcba90e06caceeb4a789fa6ff0e943522786f5a22ea97726e941e8378c'; // getenv('CLIENT_PUBLIC_KEY');

$signature = $_SERVER['HTTP_X_SIGNATURE_ED25519'];
$timestamp = $_SERVER['HTTP_X_SIGNATURE_TIMESTAMP'];
$postData  = file_get_contents('php://input');

if (Interaction::verifyKey($postData, $signature, $timestamp, $CLIENT_PUBLIC_KEY)) {
    echo json_encode(
        array(
            'type' => InteractionResponseType::PONG,
        )
    );
} else {
    http_response_code(401);
    echo 'Not verified';
}
