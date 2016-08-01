<?php
date_default_timezone_set('America/New_York');

include "xml.php";
include "config.inc";
require('S3.php');
$s3 = new S3($s3_key, $s3_secret);
$zone = 0;

// Wharf Shuttle
$pdo=new PDO("mysql:dbname=mv_shuttle;host=".$db,$db_user,$db_pass);
$statement=$pdo->prepare("SELECT ID,Latitude,Longitude,Angle,Speed,DateOccurred FROM positions WHERE FK_Users_ID = 4 ORDER BY ID desc LIMIT 1");
$statement->execute();

$pdo_big=new PDO("mysql:dbname=prod_mntvernon;host=".$db,$db_user,$db_pass);
$statement_big=$pdo_big->prepare("SELECT start_time,end_time,start_date,end_date,stop_wait FROM mv_shuttle WHERE route = 4");
$statement_big->execute();

$results_big=$statement_big->fetchAll(PDO::FETCH_ASSOC);
$results=$statement->fetchAll(PDO::FETCH_ASSOC);

foreach( $results_big as $row ) {
    $results["start_time"]= $row["start_time"];
    $results["end_time"]= $row["end_time"];
    $results["start_date"]= $row["start_date"];
    $results["end_date"]= $row["end_date"];
    $results["stop_wait"]= $row["stop_wait"];
}

$today = date("Y-m-d");

if (time() >= strtotime($results["start_time"]) && time() <= strtotime($results["end_time"]) && $results["start_date"] <= $today && $results["end_date"] >= $today ) {
  $results["status"] = "open";
} else {
  $results["status"] = "closed";
}

$location_data=json_encode($results);
$location = json_decode($location_data);

// Find out what zone the bus is in
// On the map is top, bottom, left, rights
function busZone($lat,$long){
  if ($lat < 38.711079 && $lat > 38.709729 && $long > -77.090243 && $long < -77.087647){
    $zone = 1;
  }
  if ($lat < 38.710886 && $lat > 38.709729 && $long > -77.092382 && $long < -77.090243){
    $zone = 2;
  }
  if ($lat < 38.709729 && $lat > 38.707585 && $long > -77.092924 && $long < -77.091307){
    $zone = 3;
  }
  if ($lat < 38.707585 && $lat > 38.705752 && $long > -77.092287 && $long < -77.090442){
    $zone = 4;
  }
  if ($lat < 38.706635 && $lat > 38.70484 && $long > -77.090442 && $long < -77.088912){
    $zone = 5;
  }
  if ($lat < 38.705693 && $lat > 38.70484 && $long > -77.088912 && $long < -77.087666){
    $zone = 6;
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
          $message_stop2 = "3 min";
          $message_stop3 = "6 min";
        }
        if ($zone == 6){
          $message_stop1 = "6 min";
          $message_stop2 = "2 min";
          $message_stop3 = "Arriving";
        }
        // Calculate direction
        if ($zone == 2 && $location->{'0'}->Angle > 180){
          $message_stop1 = "1 min";
          $message_stop2 = "4 min";
          $message_stop3 = "7 min";
        }
        if ($zone == 2 && $location->{'0'}->Angle < 180){
          $message_stop1 = "7 min";
          $message_stop2 = "2 min";
          $message_stop3 = "4 min";
        }

        if ($zone == 3 && $location->{'0'}->Angle > 90 && $location->{'0'}->Angle < 270){
          $message_stop1 = "8 min";
          $message_stop2 = "1 min";
          $message_stop3 = "3 min";
        }
        if ($zone == 3 && ($location->{'0'}->Angle < 90 || $location->{'0'}->Angle > 270)){
          $message_stop1 = "2 min";
          $message_stop2 = "5 min";
          $message_stop3 = "7 min";
        }

        if ($zone == 4 && $location->{'0'}->Angle > 90 && $location->{'0'}->Angle < 270){
          $message_stop1 = "7 min";
          $message_stop2 = "Arriving";
          $message_stop3 = "2 min";
        }
        if ($zone == 4 && ($location->{'0'}->Angle < 90 || $location->{'0'}->Angle > 270)){
          $message_stop1 = "3 min";
          $message_stop2 = "Arriving";
          $message_stop3 = "8 min";
        }

        if ($zone == 5 && $location->{'0'}->Angle > 90 && $location->{'0'}->Angle < 270){
          $message_stop1 = "6 min";
          $message_stop2 = "3 min";
          $message_stop3 = "1 min";
        }
        if ($zone == 5 && ($location->{'0'}->Angle < 90 || $location->{'0'}->Angle > 270)){
          $message_stop1 = "4 min";
          $message_stop2 = "1 min";
          $message_stop3 = "9 min";
        }

    } else {
      $message_stop1 = "Closed";
      $message_stop2 = "Closed";
      $message_stop3 = "Closed";
    }
} else {
  $message_stop1 = "Closed";
  $message_stop2 = "Closed";
  $message_stop3 = "Closed";
}
echo "Lat ".$location->{'0'}->Latitude." Long ".$location->{'0'}->Longitude."\n";
echo "In Zone ".$zone." Moving ".$location->{'0'}->Angle."\n";
echo "Stop 1: ".$message_stop1."\n";
echo "Stop 2: ".$message_stop2."\n";
echo "Stop 3: ".$message_stop3."\n";

// EC going to the wharf
file_put_contents("stop1.xml",$xml_pre.$message_stop1.$xml_post);
// 16-Sided Barn
file_put_contents("stop5.xml",$xml_pre.$message_stop2.$xml_post);
// Wharf
file_put_contents("stop4.xml",$xml_pre.$message_stop3.$xml_post);

// Load to Amazon S3
$stop1 = file_get_contents('stop1.xml');
S3::putObject($stop1,$s3_bucket,'stop1.xml',S3::ACL_PUBLIC_READ,array(),array('Content-Type' => 'text/plain'),S3::STORAGE_CLASS_RRS);
$stop4 = file_get_contents('stop4.xml');
S3::putObject($stop4,$s3_bucket,'stop4.xml',S3::ACL_PUBLIC_READ,array(),array('Content-Type' => 'text/plain'),S3::STORAGE_CLASS_RRS);
$stop5 = file_get_contents('stop5.xml');
S3::putObject($stop5,$s3_bucket,'stop5.xml',S3::ACL_PUBLIC_READ,array(),array('Content-Type' => 'text/plain'),S3::STORAGE_CLASS_RRS);

?>
