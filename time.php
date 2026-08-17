<?php 
  header("Content-Type: text/plain");
  header("Access-Control-Allow-Origin: *");
  
  $info = getdate();
  
  echo $info[0];
  /*
  $date = $info['mday'];
  $month = $info['mon'];
  $year = $info['year'];

  $hour = $info['hours'];
  $min = $info['minutes'];
  $sec = $info['seconds'];

  $current_date = "$year-$month-$date|$hour:$min:$sec";
  echo $current_date;
  */
?>
