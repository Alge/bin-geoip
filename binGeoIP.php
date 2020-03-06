<?php

$filePath = sys_get_temp_dir().'/rupert';
$binFileNameV4 = $filePath."/geoIP-v4.bin";
$binFileNameV6 = $filePath."/geoIP-v6.bin";
$csvFileNameV4 = $filePath."/geoIP-v4.csv";
$csvFileNameV6 = $filePath."/geoIP-v6.csv";


// Be carefull when using the actual url to download the database.
// Your IP will be banned if you download more than 3 databases per 24-hour period
//
// It might be a good idea to host your own copy of the file and download them from there

$v4URL = "https://example.com/path/IpToCountry.csv.gz";
$v6URL = "https://example.com/path/IpToCountry.6R.csv.gz";

//$v4URL = "https://software77.net/geo-ip/?DL=1";
//$v6URL = "https://software77.net/geo-ip/?DL=7";

function downloadCSV($source, $outFileName){
  global $filePath;
  mkdir($filePath, 0700);
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $source);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  $gzipData = curl_exec ($ch);
  curl_close ($ch);

  if (!$gzipData){
    echo "Download failed\n";
    return false;
  }

  $outFile = fopen($outFileName, "w");
  fwrite($outFile, gzdecode($gzipData));

  fclose($outFile);
  return true;
}


function generateBinfileV4(){
  global $binFileNameV4;
  global $csvFileNameV4;
  global $v4URL;

  if(!file_exists($csvFileNameV4)){
    if (!downloadCSV($v4URL, $csvFileNameV4)){
      // Download failed
      return false;
    }
  }
  $csv = fopen($csvFileNameV4, "r");


  $binfile = fopen($binFileNameV4, "wb");

  if ($csv) {
    while (($line = fgets($csv)) !== false) {
      if ($line[0] == "\n" or $line[0] == "#"){
        continue;
      }
      $fields = explode(",",$line);
      $start = (int)str_replace('"', '', $fields[0]);
      $stop = (int)str_replace('"', '', $fields[1]);
      $country = str_replace('"', '', $fields[4]);

      if(strlen($country) != 2){
        continue; // No use saving something we don't know
      }

      // Write the data to a binary file
      // "N" - Big endian unsigned long(32 bit / 4 bytes)
      // "C" - Unsigned char (8 bit / 1 byte)
      fwrite($binfile, pack("N", $start));
      fwrite($binfile, pack("N", $stop));
      fwrite($binfile, pack("C", ord($country[0])));
      fwrite($binfile, pack("C", ord($country[1])));
    }

    fclose($csv);
  } else {
    // error opening the file.
    echo "Failed opening (and downloading) the csv file";
    return false;
  }
  fclose($binfile);
}

function expandIPv6($ip){
    $hex = unpack("H*hex", inet_pton($ip));
    $ip = substr(preg_replace("/([A-f0-9]{4})/", "$1:", $hex['hex']), 0, -1);

    return $ip;
}

function ipv6repr($ip){
  $ip = explode(":", expandIPv6($ip));
  $iprepr = array();


  $iprepr[0] = hexdec($ip[0]) * (65536**1) + hexdec($ip[1]);
  $iprepr[1] = hexdec($ip[2]) * (65536**1) + hexdec($ip[3]);
  $iprepr[2] = hexdec($ip[4]) * (65536**1) + hexdec($ip[5]);
  $iprepr[3] = hexdec($ip[6]) * (65536**1) + hexdec($ip[7]);

  return $iprepr;
}

function generateBinfileV6(){
  global $binFileNameV6;
  global $csvFileNameV6;
  global $v6URL;

  if(!file_exists($csvFileNameV6)){
    // CSV file doesn't exist yet, lets download it!
    if (!downloadCSV($v6URL, $csvFileNameV6)){
      // Download failed
      return false;
    }
  }

  $csv = fopen($csvFileNameV6, "r");

  $binfile = fopen($binFileNameV6, "wb");

  if ($csv) {
    while (($line = fgets($csv)) !== false) {
      if ($line[0] == "\n" or $line[0] == "#"){
        continue;
      }
      $fields = explode(",",$line);

      $start = ipv6repr(explode("-", $fields[0])[0]);
      $stop = ipv6repr(explode("-", $fields[0])[1]);

      $country = $fields[1];

      if(strlen($country) != 2){
        continue; // No use saving something we don't know
      }

      // Write the data to a binary file
      // "N" - Big endian unsigned long(32 bit / 4 bytes)
      // "C" - Unsigned char (8 bit / 1 byte)
      foreach($start as $chunk){
        fwrite($binfile, pack("N", $chunk));
      }
      foreach($stop as $chunk){
        fwrite($binfile, pack("N", $chunk));
      }

      fwrite($binfile, pack("C", ord($country[0])));
      fwrite($binfile, pack("C", ord($country[1])));
    }

    fclose($csv);
  } else {
    // error opening the file.
    echo "Failed opening (and downloading) the csv file";
    return false;
  }
  fclose($binfile);
}

function parseStructV4($data){
  $struct = array();
  $struct['start'] = unpack("N", $data, 0)[1];

  $struct['stop'] = unpack("N", $data, 4)[1];

  $c1 = unpack("C", $data, 8)[1];
  $c2 = unpack("C", $data, 9)[1];
  $struct['country'] = chr($c1).chr($c2);
  return $struct;
}

function parseStructV6($data){
  $struct = array();

  $struct["start"] = array();
  $struct["start"][] = unpack("N", $data, 0)[1];
  $struct["start"][] = unpack("N", $data, 4)[1];
  $struct["start"][] = unpack("N", $data, 8)[1];
  $struct["start"][] = unpack("N", $data, 12)[1];

  $struct["stop"] = array();
  $struct["stop"][] = unpack("N", $data, 16)[1];
  $struct["stop"][] = unpack("N", $data, 20)[1];
  $struct["stop"][] = unpack("N", $data, 24)[1];
  $struct["stop"][] = unpack("N", $data, 28)[1];

  $c1 = unpack("C", $data, 32)[1];
  $c2 = unpack("C", $data, 33)[1];
  $struct['country'] = chr($c1).chr($c2);
  return $struct;
}

function getGeoIP4($ip){

  global $binFileNameV4;

  // Convert the ip into a long so we can search for it
  $iprepr = ip2long($ip);

  // Time to start searching the file
  // 4 bytes start
  // 4 bytes length
  // 2 bytes country name
  $structSize = 4 + 4 + 2;

  if (!file_exists($binFileNameV4)){
    generateBinfileV4();
  }
  $binfile = fopen($binFileNameV4, "rb");
  $fileLength = filesize($binFileNameV4);

  $low = 0;
  $high = ($fileLength -1)/$structSize;

  while ($low <= $high) {
    // compute middle index
    $mid = floor(($low + $high) / 2);
    $bytesIn = $mid * $structSize;

    fseek ($binfile, $mid*$structSize);
    $data = fread($binfile, $structSize);

    $line = parseStructV4($data);

    // Are we to low in the file?
    if ($iprepr < $line['start']){
      $high = $mid -1;
    }
    // Are we in the right place?
    elseif($iprepr <= $line['stop']){
      return $line['country'];
    }
    // Are we to deep into the file?
    else{
      $low = $mid + 1;
    }
  }
  return false;
}

function getGeoIP6($ip){

  global $binFileNameV6;

  $ip = ipv6repr($ip);

  // Time to start searching the file :)

  // 16 bytes start
  // 16 bytes length
  // 2 bytes country name
  $structSize = 16 + 16 + 2;

  if (!file_exists($binFileNameV6)){
    generateBinfileV6();
  }
  $binfile = fopen($binFileNameV6, "rb");

  $fileLength = filesize($binFileNameV6);

  $low = 0;
  $high = ($fileLength -1)/$structSize;

  while ($low <= $high) {
    // compute middle index
    $mid = floor(($low + $high) / 2);
    $bytesIn = $mid * $structSize;

    fseek ($binfile, $mid*$structSize);
    $data = fread($binfile, $structSize);

    $line = parseStructV6($data);

    // Are we to low in the file?
    $ishigh = false;
    if($ip[0] < $line['start'][0]){
      $ishigh = true;
    }
    elseif($ip[1] < $line['start'][1]){
      $ishigh = true;
    }
    elseif($ip[2] < $line['start'][2]){
      $ishigh = true;
    }
    elseif($ip[3] < $line['start'][3]){
      $ishigh = true;
    }
    if ($ishigh){
      $high = $mid -1;
      continue;
    }

    if (
      $ip[0] <= $line['stop'][0] &&
      $ip[1] <= $line['stop'][1] &&
      $ip[2] <= $line['stop'][2] &&
      $ip[3] <= $line['stop'][3]
    ){
      return $line['country'];
    }
    // If we aren't high we are high we are low
    if (!$ishigh){
      $low = $mid + 1;
    }
  }
  return false;
}
function getgeoip($ip){
  if (strpos($ip, ":", 1)){
    return getGeoIP6($ip);
  }
  else{
    return getGeoIP4($ip);
  }
}
?>
