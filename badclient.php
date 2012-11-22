<?php

$ctx = new ZMQContext();
$sub = new ZMQSocket($ctx, ZMQ::SOCKET_SUB);

$sub->connect("tcp://localhost:45677");
$sub->setSockOpt(ZMQ::SOCKOPT_SUBSCRIBE, "vital.");

for($i = 0; $i < 10000000; $i++) {
    $data = $sub->recvMulti();
    if($data[0] == 'vital.data') {
        echo $data[1], " ", $data[2], "\n";
    } else if($data[0] == "vital.config") {
        echo "Code update: ", $data[1], "\n";
    }
}
