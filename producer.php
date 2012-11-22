<?php

define("CRYPTO_METHOD", 'aes128');
define("IV_SIZE", 16);

$ctx = new ZMQContext();
$pub = new ZMQSocket($ctx, ZMQ::SOCKET_PUB);
$ctl = new ZMQSocket($ctx, ZMQ::SOCKET_ROUTER);

$pub->bind("tcp://*:45677");
$ctl->bind("tcp://*:45679");

$client_codes = array();
$code = openssl_random_pseudo_bytes(8);
$poll = new ZMQPoll();
$poll->add($ctl, ZMQ::POLL_IN);
$poll->add($pub, ZMQ::POLL_OUT);
$read = $write = array();
$sequence = 0;
$code_sequence = 0;

while(true) {
    $poll->poll($read, $write, 0);
    if(count($read)) {
        // We have new control messages!
        $msg = $ctl->recvMulti();
        if($msg[1] == "ADD") {
            // Note, you'd practically want to use diffie-hellman here
            // or have preshared keys. 
            $client_codes[$msg[2]] = $msg[3];
        } else if($msg[1] == 'RM') {
            unset($client_codes[$msg[2]]);
        }
        $code = openssl_random_pseudo_bytes(8);
        $data = get_codes($client_codes, $code);
        $pub->sendMulti(array("vital.config", $code_sequence++, $data));
        echo "Code update: ", $code_sequence, " ", bin2hex($code), "\n";
    } else {
        $data = secret($code, vital_data());
        $pub->sendMulti(array("vital.data", $sequence++, $data));
    }
    
    // Slow things down to give readable output
    usleep(10000);
}

/* 
 * Ecrypt the key under the individual keys of the various clients
 */ 
function get_codes($clients, $code) {
    $return = array();
    foreach($clients as $client => $secret) {
        $return[$client] = secret($secret, $code);
    }
    return json_encode($return);
}

/*
 * Generate some dummy data to send!
 */
function vital_data() {
    return "My important data is this: " . rand();
}

/*
 * Use the OpenSSL libs to symetrically encrypt the data
 */
function secret($code, $data) {
    $iv = openssl_random_pseudo_bytes(IV_SIZE);
    return base64_encode($iv . openssl_encrypt($data, CRYPTO_METHOD, $code, false, $iv));
}

