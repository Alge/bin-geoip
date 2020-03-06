<?php

require "binGeoIP.php";

$v4 = "123.123.123.123";
$v6 = "2a0f:1cc0:112::";

echo "Looking up ip ".$v4." - ";
echo getgeoip($v4);
echo "\n";

echo "Looking up ip ".$v6."- ";
echo getgeoip($v6);
echo "\n";




?>
