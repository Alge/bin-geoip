<?php

require "binGeoIP.php";

// Download the csv database (if it doesn't exist) and generate the binary database
generateBinfileV4();
generateBinfileV6();

echo "creation of database done, lets use it now!\n";

echo "Looking up ip 2001:67c:23c0:0013:: - ";
echo getGeoIP6("2001:67c:23c0:0013::");
echo "\n";

echo "Looking up ip 123.123.123.123 - ";
echo getGeoIP4("123.123.123.123");
echo "\n";



?>
