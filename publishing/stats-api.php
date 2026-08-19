

<?php

  /* GRAPH DATA TOOLS */

  function gatherPages(){
    
    $path = ROOT."editing/pages".DIRECTORY_SEPARATOR;
    $files = scandir($path);
    
    //echo "Files = ".count($files);
    
    $list = array();
    foreach($files as $f){
      if(is_dir($path.$f)){ continue; } // . ..
      $info = pathinfo($f);
      $fileName = $info["basename"];
      
      if(strcmp($fileName,".") == 0) continue;
      if(strcmp($fileName,"#") == 0) continue;
      if(strpos($fileName,"page") > -1) continue;

      //only source .md files, skip the generated .html counterpart
      if(!isset($info["extension"]) || strcasecmp($info["extension"], "md") != 0) continue;

      //echo "<br/> added ".$fileName;
      $list[] = $fileName;
    }
    
    //echo "<br />Filtered files ".count($list);
    return $list;
  }
  
  function addTo($array, $content){
    
    //check if already exist
    foreach($array as $val){
      if($val == $content) return $array;
    }

    $array[] = $content;
    return $array;
  }
  
  function generateStringArray($list){
    
    $output = "[";
    foreach($list as $line){
      if(is_int($line)) $output .= $line.",";
      else $output .= "'".$line."',";
    }
    $output = substr($output, 0, strlen($output) - 1);
    $output .= "]";
    return $output;
  }
  
  function generateFile($fileName, $graphName, $xValues, $yValues){
    $statsDir = ROOT."stats/";
    if(!is_dir($statsDir)) mkdir($statsDir, 0755, true);

    $filePath = $statsDir."data_".$fileName.".js";
    $file = fopen($filePath, "w+");
    
    $xAxis = generateStringArray($xValues);
    $yAxis = generateStringArray($yValues);
    
    //echo "<br />x=".$xAxis;
    //echo "<br />y=".$yAxis;
    
    $output = "$('body').append('<div id=\"".$fileName."\"></div>');\n\n";
    $output .= "chart = new Highcharts.Chart({\n";
    $output .= "chart: {renderTo: '".$fileName."',type: 'column'},\n";
    $output .= "title: {text: '".$graphName."'},\n";
    $output .= "subtitle: {text: 'andreberlemont.com'},\n";
    $output .= "xAxis: {categories:".$xAxis."},\n";
    $output .= "yAxis: {min: 0,title: {  text: 'Quantity of articles' }},\n";
    $output .= "series: [{name: 'Articles',data:".$yAxis."}]\n";
    $output .= "});\n";
    
    fwrite($file, $output);
    
    fclose($file);
    //echo "<br />created file ".$filePath;
  }

  function updateYearData($list){
    $labelArray = [];
    $arrayCount = [];
    
    foreach($list as $f){
      $data = explode("-", $f);
      
      //safe
      if(count($data) < 2) continue;
      $val = intval($data[0]);
      if(!is_int($val)) continue;
      
      $labelArray = addTo($labelArray, $val);
      
      //cherche l'index du label
      $index = array_search($val, $labelArray);
      while(count($arrayCount) <= $index) $arrayCount[] = 0; // reset value if new
      
      $arrayCount[$index]++;
    }

    //foreach($listVal as $val){ echo "<br/>".$val; }

    generateFile("year", "Website activity per year", $labelArray, $arrayCount);
  }
  
  function updateMonthData($list){
    $arrayCount = [0,0,0,0,0,0,0,0,0,0,0,0]; // 12 mois
     
    foreach($list as $f){
      $data = explode("-", $f); // YYYY-MM-DD
      
      //safe check for valid article file
      if(count($data) < 2) continue;
      $val = intval($data[0]);
      if(!is_int($val)) continue;
      
      //echo "<br/>".$val." vs ".Date("Y");

      if($val != Date("Y")) continue;

      $val = intval($data[1]);
      if(!is_int($val)) continue;
      
      $arrayCount[$val-1]++;
    }

    generateFile("month", "Website activity per month", ["Janv","Fevr","Mars","Avri","Mai","Juin","Juil","Aou","Sept","Oct","Nov","Dec"], $arrayCount);
  }

  function stats_update(){
    echo "<p>-> STATS API</p>";

    $list = gatherPages();
    
    echo "<p>-> ".count($list)." pages</p>";

    echo "<p>-> updating year data</p>";
    updateYearData($list);

    echo "<p>-> updating month data</p>";
    updateMonthData($list);
  }
  
?>
