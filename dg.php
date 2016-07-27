<?php
date_default_timezone_set('America/New_York');

include "xml.php";
include "config.inc";
require('S3.php');
$s3 = new S3($s3_key, $s3_secret);
$zone = 0;

// Distillery and Gristmill Shuttle
$location_data = file_get_contents("http://www.mountvernon.org/shuttle-tracker/api?bus=5&cache=".date("hisa"),true);
$location = json_decode($location_data);

// Find out what zone the bus is in
// On the map is top, bottom, left, rights
function busZone($lat,$long){
  if ($lat < 38.712681 && $lat > 38.709685 && $long < -77.089047 && $long > -77.087145){
    $zone = 1;
  }
  if ($lat < 38.717292 && $lat > 38.711172 && $long > -77.096535 && $long < -77.089047){
    $zone = 2;
  }
  if ($lat < 38.717292 && $lat > 38.71069 && $long > -77.104616 && $long < -77.096535){
    $zone = 3;
  }
  if ($lat < 38.713101 && $lat > 38.706944 && $long > -77.112269 && $long < -77.104616){
    $zone = 4;
  }
  if ($lat < 38.709392 && $lat > 38.706944 && $long > -77.120969 && $long < -77.112269){
    $zone = 5;
  }
  if ($lat < 38.711246 && $lat > 38.706944 && $long > -77.128281 && $long < -77.120969){
    $zone = 6;
  }
  if ($lat < 38.714563 && $lat > 38.709789 && $long > -77.13119 && $long < -77.128281){
    $zone = 7;
  }
  return $zone;
}

// First check to see if we are in season of the bus running
if (new DateTime() > new DateTime($location->start_date) && new DateTime() < new DateTime($location->end_date)) {
    // Now check to see if we are within the hours the bus is scheduled to run
    $current_time = strtotime('now');
    if ($current_time > strtotime('today '.$location->start_time) && $current_time < strtotime('today '.$location->end_time)) {
        $zone = busZone($location->{'0'}->Latitude,$location->{'0'}->Longitude);
        // Fixed stops
        if ($zone == 1){
          $message_stop1 = "Arriving";
          $message_stop2 = "7 min";
        }
        if ($zone == 7){
          $message_stop1 = "7 min";
          $message_stop2 = "Arriving";
        }
        // Calculate direction
        if ($zone == 2 && $location->{'0'}->Angle > 180){
          $message_stop1 = "13 min";
          $message_stop2 = "6 min";
        }
        if ($zone == 2 && $location->{'0'}->Angle < 180){
          $message_stop1 = "1 min";
          $message_stop2 = "8 min";
        }

        if ($zone == 3 && $location->{'0'}->Angle > 180){
          $message_stop1 = "12 min";
          $message_stop2 = "5 min";
        }
        if ($zone == 3 && $location->{'0'}->Angle < 180){
          $message_stop1 = "2 min";
          $message_stop2 = "9 min";
        }

        if ($zone == 4 && $location->{'0'}->Angle > 180){
          $message_stop1 = "11 min";
          $message_stop2 = "4 min";
        }
        if ($zone == 4 && $location->{'0'}->Angle < 180){
          $message_stop1 = "3 min";
          $message_stop2 = "10 min";
        }

        if ($zone == 5 && $location->{'0'}->Angle > 180){
          $message_stop1 = "10 min";
          $message_stop2 = "3 min";
        }
        if ($zone == 5 && $location->{'0'}->Angle < 180){
          $message_stop1 = "4 min";
          $message_stop2 = "11 min";
        }

        if ($zone == 6 && $location->{'0'}->Angle > 180){
          $message_stop1 = "9 min";
          $message_stop2 = "2 min";
        }
        if ($zone == 6 && $location->{'0'}->Angle < 180){
          $message_stop1 = "5 min";
          $message_stop2 = "12 min";
        }

    } else {
      $message_stop1 = "Closed";
      $message_stop2 = "Closed";
    }
} else {
  $message_stop1 = "Closed";
  $message_stop2 = "Closed";
}

echo "In Zone ".$zone." Moving ".$location->{'0'}->Angle."\n";
echo "Stop 1: ".$message_stop1."\n";
echo "Stop 2: ".$message_stop2."\n";
// Distillery & Gristmill
file_put_contents("stop3.xml",$xml_pre.$message_stop2.$xml_post);
// EC going to DG
file_put_contents("stop2.xml",$xml_pre.$message_stop1.$xml_post);

// Load to Amazon S3
$stop3 = file_get_contents('stop3.xml');
S3::putObject($stop3,$s3_bucket,'stop3.xml',S3::ACL_PUBLIC_READ,array(),array('Content-Type' => 'text/plain'),S3::STORAGE_CLASS_RRS);

$stop2 = file_get_contents('stop2.xml');
S3::putObject($stop2,$s3_bucket,'stop2.xml',S3::ACL_PUBLIC_READ,array(),array('Content-Type' => 'text/plain'),S3::STORAGE_CLASS_RRS);

?>
