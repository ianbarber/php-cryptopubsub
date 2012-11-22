<?php

define("CRYPTO_METHOD", 'aes128');
define("IV_SIZE", 16);

$ctx = new ZMQContext();
$sub = new ZMQSocket($ctx, ZMQ::SOCKET_SUB);
$ctl = new ZMQSocket($ctx, ZMQ::SOCKET_DEALER);

$sub->connect("tcp://localhost:45677");
$sub->setSockOpt(ZMQ::SOCKOPT_SUBSCRIBE, "vital.");
$ctl->connect("tcp://localhost:45679");

$code = openssl_random_pseudo_bytes(8);
$decode_key = null;
$myName = uniqid();

// Insecure key exchange! Fnord!
$ctl->sendMulti(array("ADD", $myName, $code));

for($i = 0; $i < 10000000; $i++) {
    $data = $sub->recvMulti();
    if($data[0] == 'vital.data' && $decode_key != null) {
        echo $data[1], " ", plaintext($decode_key, $data[2]), "\n";
    } else if($data[0] == "vital.config") {
        $keys = json_decode($data[2], true); 
        $decode_key = plaintext($code, $keys[$myName]);
        echo "Code update: ", $data[1], " ", bin2hex($decode_key), "\n";
    }
}

$ctl->sendMulti(array("RM", $myName));

function plaintext($code, $data) {
    $data = base64_decode($data);
    $iv = substr($data, 0, IV_SIZE);
    $data = substr($data, IV_SIZE);
    return openssl_decrypt($data, CRYPTO_METHOD, $code, false, $iv);
}