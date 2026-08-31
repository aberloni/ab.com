<?php

    //error_reporting(E_ALL);

    include(ROOT."plugins/Parsedown.php");

    // ///////////// CONST

    $mediaPath = ROOT."articles/medias/";   // filesystem path (thumb generation)
    $mediaUrl  = "articles/medias/";         // web path (href/src in generated html)
    $pagesPath = ROOT."editing/pages/";

    function loadConfig()
    {
        $configData = file_get_contents(ROOT."editing/config.json");
        return json_decode($configData);
    }

    /* category whose articles are built + editable in Phanes but kept out of the homepage list / rss / stats (ex: "about") */
    function hiddenCategory()
    {
        $conf = loadConfig();
        return isset($conf->{"hiddenCategory"}) ? $conf->{"hiddenCategory"} : "page";
    }

    /* true if this .md's category is the hidden one */
    function isHiddenPage($id)
    {
        $art = parseArticleFile(ROOT."editing/pages/".$id.".md");
        return is_array($art) && $art["category"] === hiddenCategory();
    }

    /// regenerate index.html (homepage), returns ["status"=>"créé|mis à jour|inchangé", "count"=>n]
    function generateIndex()
    {
        $conf = loadConfig();

        $outputFile = ROOT."index.html";

        $output = "";

        // PAGE
        $output = '<!DOCTYPE html>'.PHP_EOL;
        $output .= '<html lang="en">'.PHP_EOL;

        //HEADER
        $configHeader = $conf->{"header"};
        $output .= '<head>'.PHP_EOL;
        $output .= '<title>'.$configHeader->{"title"}.'</title>'.PHP_EOL;

        $output .= '<link rel="icon" type="image/png" href="favicon.png">';

        $output .= PHP_EOL;
        $output .= '<meta charset="'.$configHeader->{"charset"}.'" />'.PHP_EOL;
        $output .= '<meta name="viewport" content="width=device-width, initial-scale=1" />'.PHP_EOL;
        $output .= '<meta name="Description" content="'.$configHeader->{"description"}.'" />'.PHP_EOL;

        $output .= '<link rel="alternate" type="application/rss+xml" title="'.$configHeader->{"title"}.'" href="rss.xml" />'.PHP_EOL;

          //CSS
        $css = $configHeader->{"css"};
        $output .= PHP_EOL;
        foreach($css as $line)
        {
            $output .= '<link rel="stylesheet" type="text/css" href="'.$line.'">'.PHP_EOL;
        }

          // FONTS
        $fonts = $configHeader->{"fonts"};
        $output .= PHP_EOL;

        $output .= '<link rel="preconnect" href="https://fonts.googleapis.com">'.PHP_EOL;
        $output .= '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'.PHP_EOL;

        foreach($fonts as $font)
        {
            $output .= '<link rel="stylesheet" href="'.$font.'">'.PHP_EOL;
        }

          //JS

        $js = $configHeader->{"js"};
        $output .= PHP_EOL;
        foreach($js as $line)
        {
            $output .= '<script type="text/javascript" src="'.$line.'"></script>'.PHP_EOL;
        }
        //local fallback if the jQuery CDN is unreachable
        $output .= '<script type="text/javascript">window.jQuery || document.write(\'<script src="js/jquery-4.0.0.min.js"><\/script>\')</script>'.PHP_EOL;

          //GOOGLE
        $output .= html_googleAnalytics($configHeader->{"googleAnalytics"}).PHP_EOL;

        $output .= '</head>'.PHP_EOL;

        //BODY
        $output .= PHP_EOL;
        $output .= '<body>'.PHP_EOL;

        $output .= PHP_EOL.PHP_EOL;

        $output .= '<div id="all">';

        //SEARCH BOX
        $output .= '<div class="searchZone">';
        $output .= '<input id="search" value="Filter articles here" />&nbsp;';
        $output .= '</div>';

        //SOMMAIRE : liste plate de tout les articles, triée par date desc (hors catégorie masquée type "about")
        $hidden = hiddenCategory();
        $items = array_values(array_filter(getItems(), function($it) use ($hidden){ return $it["cat"] !== $hidden; }));
        usort($items, function($a, $b){ return strcmp($b["file"], $a["file"]); });

        $output .= '<div id="sommaire">'.PHP_EOL;
        $currentYear = null;
        foreach($items as $item){
            $year = substr($item["file"], 0, 4);
            if($year !== $currentYear){
                $currentYear = $year;
                $output .= '<div class="year-sep"><span>'.htmlspecialchars($year).'</span></div>'.PHP_EOL;
            }
            $output .= '<a href="'.$item["file"].'" class="article-line">';
            if(strlen($item["subcat"]) > 0){
                $output .= '<span class="subcat" data-subcat="'.htmlspecialchars($item["subcat"]).'">'.htmlspecialchars($item["subcat"]).'</span>';
            }
            $output .= '<span class="title">'.htmlspecialchars($item["title"]).'</span>';
            $output .= '<span class="date">('.$item["file"].')</span>';
            $output .= '</a>'.PHP_EOL;
        }
        $output .= '<button id="time-travel" class="article-line" type="button"><span class="title">time travel</span></button>'.PHP_EOL;
        $output .= '</div>';

        $output .= '</div>';

          //WATERMARK : injected verbatim from watermark.html (edit that file to change it)
        $watermarkFile = ROOT."watermark.html";
        if(file_exists($watermarkFile)){
            $output .= trim(file_get_contents($watermarkFile));
        }
        $versionFile = ROOT."version.md";
        if(file_exists($versionFile)){
            $output .= '<span id="watermark-version">v'.htmlspecialchars(trim(file_get_contents($versionFile))).'</span>';
        }

          //OVERLAY
        $output .= PHP_EOL.PHP_EOL;
        $output .= '<div id="overlay-click"></div>';
        $output .= '<div id="overlay-bg"></div>';
        $output .= '<div id="overlay" rel=""></div>';

        $output .= PHP_EOL.PHP_EOL;
        $output .= '</body>'.PHP_EOL;

        $output .= '</html>';

        //WRITE OUPUT
        $previous = file_exists($outputFile) ? file_get_contents($outputFile) : null;
        $status = ($previous === $output) ? "inchangé" : (($previous === null) ? "créé" : "mis à jour");

        $file = fopen($outputFile, "w+");
        fwrite($file, $output);
        fclose($file);

        return ["status"=>$status, "count"=>count($items)];
    }

    function html_googleAnalytics($id)
    {
        $str = "";
        $str .= '<script type="text/javascript">';
        $str .= 'var _gaq = _gaq || []; _gaq.push(["_setAccount", "'.$id.'"]); _gaq.push(["_trackPageview"]);';
        $str .= '(function() {'.PHP_EOL;
        $str .= 'var ga = document.createElement("script"); ga.type = "text/javascript"; ga.async = true;'.PHP_EOL;
        $str .= 'ga.src = ("https:" == document.location.protocol ? "https://ssl" : "http://www") + ".google-analytics.com/ga.js";'.PHP_EOL;
        $str .= 'var s = document.getElementsByTagName("script")[0]; s.parentNode.insertBefore(ga, s);'.PHP_EOL;
        $str .= '})();';
        $str .= '</script>';
        return $str;
    }





    // TXT GENERATION CORE FUNCTIONS
    ///////////////

    function solveSpecificPatternsOnLine($line, $date)
    {
      global $mediaUrl;

      //text to clickable link (not starting with [ and only starting with http...)
      //$line = preg_replace("/([^\[])(http(s)?:\/\/)([^\s\)\]])+/", '<a href="$0" target="_blank">$0</a>', $line);
      //$line = makeLinksClickable($line);
      //var_dump($line);

      // replace "ddd" pattern to file date
      $line = str_replace(":ddd:", $date, $line);

      //replace []() to <a>
      //$line = preg_replace("/\[([^\]]+)\]\(([^\)]+)\)/",'<a href="$2" target="_blank">$1</a>', $line);

      //replace {{file.ext}} to <img>
      $line = preg_replace("/\{\{([^}]+)\}\}/", '<img src="'.$mediaUrl.'$1" alt="$1" onerror="mediaFail(this)" />', $line);

      //replace [[]] to <video>
      $replacement = '<video width="640" height="360" controls onerror="mediaFail(this)" data-media="$1"><source src="'.$mediaUrl.'$1" type="video/mp4">Your browser does not support the video tag.</video>';
      $line = preg_replace("/\{\[([^]]+)\]\}/", $replacement, $line);
      
      return $line;
    }






    /* parse an article file.
       format :
         CATEGORY                <- mandatory
         Article title           <- mandatory
         keyword, keyword, ...    <- optional
         (more header lines reserved for future use)
         ---                     <- separator, marks the start of the body
         markdown body...
       returns ["category"=>, "title"=>, "keywords"=>, "body"=>]  or a string error. */
    function parseArticleFile($fullPath)
    {
      if(!file_exists($fullPath)) return "file missing";

      $lines = preg_split('/\r\n|\r|\n/', file_get_contents($fullPath));

      $sep = -1;
      foreach($lines as $i => $l){
        if(trim($l) === "---"){ $sep = $i; break; }
      }
      if($sep < 0) return "missing '---' separator before the body";

      $head = array();
      for($i = 0; $i < $sep; $i++){
        if(trim($lines[$i]) !== "") $head[] = trim($lines[$i]);
      }
      if(count($head) < 2) return "header needs CATEGORY then Title before '---', found ".count($head)." line".(count($head) == 1 ? "" : "s");

      return array(
        // category is lowercased so "DNS" / "dns" / "Dns" all match
        "category" => function_exists('mb_strtolower') ? mb_strtolower($head[0], 'UTF-8') : strtolower($head[0]),
        "title"    => $head[1],
        "keywords" => isset($head[2]) ? $head[2] : "",
        "body"     => implode("\n", array_slice($lines, $sep + 1)),
      );
    }

    /* génère le code html d'un article */
    function displayItem($path, $fileName)
    {
      $path = $path.$fileName.".md";

      $art = parseArticleFile($path);
      if(!is_array($art)) return "{API} ".$fileName." : ".$art;

      //markdown
      $pd = new Parsedown();
      $pd->setBreaksEnabled(true);
      $content = $pd->text($art["body"]);

      $output = "";

      $output .= '<div id="content-title">';
      $output .= $art["title"];
      $output .= "</div>";

      //keywords under the title
      $kw = array_values(array_filter(array_map('trim', explode(",", $art["keywords"]))));
      if(count($kw) > 0){
        $output .= '<div id="content-keywords">';
        foreach($kw as $k) $output .= '<span class="keyword">'.htmlspecialchars($k).'</span>';
        $output .= '</div>';
      }

      $output .= '<div id="content">';
      $output .= solveSpecificPatternsOnLine($content, $fileName);
      $output .= '</div>';

      return $output;
    }













    function article_toHtml($itemDate)
    {

      if(strlen($itemDate) <= 0){
        return "Nothing returned by ".$itemDate;
      }

      //si il existe un - dans le nom (YYYY-MM-DD-X | YYYY-MM-DD_X) c'est que c'est une date d'article
      //attention : les fichiers qui ne sont pas des articles ne doivent pas contenir de -
      if (strpos($itemDate, "-") === false) {
        // fixed pages (ex: "about") live in editing/pages/ like the others, with a legacy fallback to editing/
        $base = file_exists(ROOT."editing/pages/".trim($itemDate).".md") ? ROOT."editing/pages/" : ROOT."editing/";
        $str = displayItem($base, trim($itemDate));
      }else {
        $str = displayItem(ROOT."editing/pages/", trim($itemDate));
      }

      return $str;
    }

    /* génère (si besoin) la page html d'un article et renvoie son statut : "created", "updated" ou "unchanged" */
    function buildArticle($date)
    {
      $outputDir = ROOT."articles/";
      if(!is_dir($outputDir)) mkdir($outputDir, 0755, true);

      $fileName = $outputDir.$date.".html";
      $output = article_toHtml($date);

      $previous = file_exists($fileName) ? file_get_contents($fileName) : null;

      if($previous === $output) return "unchanged";

      file_put_contents($fileName, $output);

      return ($previous === null) ? "created" : "updated";
    }

    /* régénère les stats et le rss.xml (données agrégées sur tous les articles) */
    function refreshAggregates()
    {
      include_once(ROOT."publishing/stats-api.php");
      stats_update();

      include_once(ROOT."publishing/xml-api.php");
      xml_update();
    }



    /* true if the article header is properly set, else a string describing what's wrong */
    function validateHeader($id){
      $res = parseArticleFile(ROOT."editing/pages/".$id.".md");
      return is_array($res) ? true : $res;
    }

    /* [ filename => reason ] for every article whose header is not properly set */
    function getInvalidHeaders(){
      $out = array();
      if(!is_dir(ROOT."editing/pages/")) return $out;

      foreach(scandir(ROOT."editing/pages/") as $f){
        $info = pathinfo($f);
        if(!isset($info["extension"]) || strcasecmp($info["extension"], "md") != 0) continue;
        if(is_int(strpos($f, "#"))) continue;

        $res = validateHeader($info["filename"]);
        if($res !== true) $out[$info["filename"]] = $res;
      }
      ksort($out);
      return $out;
    }

    function getItems(){
      if(!is_dir(ROOT."editing/pages/")) return "";

      $path = ROOT."editing/pages/";
      $files = scandir($path);
      $all = array();

      for($i = 0; $i < count($files); $i++){
        $f = $files[$i];

        $info = pathinfo($f);
        //echo "<br/><br/>";print_r($info);

        if(is_dir($path.$info["filename"]))  continue;

        //skip #
        if(is_int(strpos($f, "#"))) continue;

        if(!isset($info["extension"]) || strcasecmp($info["extension"], "md") != 0) continue;

        //skip articles whose header is not properly set (listed separately by getInvalidHeaders)
        $art = parseArticleFile($path.$info["filename"].".md");
        if(!is_array($art)) continue;

        // "subcat" key kept : that's what the homepage badge / data-subcat filter use
        $new = array("cat"=>$art["category"],"subcat"=>$art["category"],"file"=>$info["filename"],"title"=>$art["title"]);

        $all[] = $new;
      }
      return $all;
    }

  ?>




  <?php
    
    /* transform ttes les urls en lien cliquable */
    function makeLinksClickable($line){

      //ce qui détermine la fin d'un lien
      $chars = Array(PHP_EOL, ' ');

      //il faut un espace avant parce que si j'ai mis un <iframe src="http://" ça pose problème
      $start = strpos($line, " http://");
      
      if($start === false){
        //echo "no link";
        return $line;
      }

      //don't transform link between [label](www.)
      if($start > 0){
        if($line[$start-1] == '('){
          return $line;
        }
      }
      
      $pos = $start;
      //on avance jusqu'à un character qui détermine la fin du link
      
      echo "<br/> link start at :".$start;

      $limit = 300; // nb max de char dans la chaine de link
      $end = false;

      do{

        //si on est au bout de la chaine
        if($pos >= strlen($line)) $end = true;

        //pour chaque char qui peuvent interrupt un link
        for($i = 0;$i < count($chars);$i++){
          if($line[$pos] == $chars[$i]){
            //found end of link (based on defined chars[])
            $end = true;
          }
        }

        if(!$end) $pos++;

      }while(!$end && $pos < $limit);

      echo "<br/> link end at :".$pos;
      if($pos >= $limit) echo "<div class='error'>!!! OVER LIMIT !!! (pos = ".$pos.")</div>";

      $link = substr($line, $start, $pos - $start);

      echo "<br/> link output : ".$link;

      $output = substr($line, 0, $start)." ";
      $output .= "<a href='".$link."'' target='_blank'>".$link."</a>";
      $output .= " ".substr($line, $pos, strlen($line) - $pos);

      return $output;
    }

    function getSizeString($url, $limitX = 200, $limitY = 150)
    {
      $dim = getimagesize($url);
      $width = $dim[0];
      $height = $dim[1];
      
      $ratioX = ($limitX / $width);
      $widthX = $width * $ratioX;
      $heightX = $height * $ratioX;
      
      if($widthX <= $limitX && $heightX <= $limitY){
        return "width=".intval($widthX)." height=".intval($heightX);
      }
      
      $ratioY = ($limitY / $height);
      $widthY = $width * $ratioY;
      $heightY = $height * $ratioY;
      
      if($widthY <= $limitX && $heightY <= $limitY){
        return "width=".intval($widthY)." height=".intval($heightY);
      }
      
      return "";
    }
    
    function generateList($path)
    {
      $ext = array("png", "jpg");
      $list = array();
      
      //stop si c'est pas un dossier
      if (!is_dir($path)) return array();
      
      $files = scandir($path);
      
      foreach($files as $file) {
        if(is_dir($file)) continue;
        $info = pathinfo($file);
        if(!isset($info["extension"]))  continue;
        
        //take only pics
        $filepath = strtolower($file);
        
        //png / jpg ?
        if(in_array($info["extension"], $ext)){
          $list[] = $filepath;
          //echo $file."<br />";
        }
      }
      
      return $list;
    }
    
    function getScreenshots($id) {
      //create thumbs
      global $mediaPath;
      $path = $mediaPath.$id."/";
      $thumbs = $mediaPath.$id."/thumbs/";

      //echo $path." , ".$thumbs;
      createThumbs($path,$thumbs, 200);
      
      $list = generateList($mediaPath.$id);
      
      $realList = $list;
      
      return $realList;
    }
    
    function createThumbs( $pathToImages, $pathToThumbs, $thumbWidth ) 
    {
      //si pas de dossier avec des images ...
      if (!is_dir($pathToImages)) return;
      
      //créer le thumbs/
      if(!is_dir($pathToThumbs))  mkdir($pathToThumbs);
      
      $files = scandir($pathToImages);
      foreach($files as $file){
        if(is_dir($file)) continue;
        
        // parse path for the extension
        $info = pathinfo($file);
        //$fname = $info["filename"].".".$info["extension"];
        $fname = $info["basename"];
        $filePath = $pathToImages.$fname;
        
        //dossier ? pas d'extension ? ... zap
        if(!isset($info["extension"]))  continue;
        
        $exts = array("jpg", "png");
        foreach($exts as $ext) {
          
          if ( strtolower($info['extension']) == $ext ) 
          {
            //print_r($info);

            $min = $pathToThumbs."m_".$fname;
            //echo "<br/>\nmin ? ".$min;
            if(file_exists($min)) continue;

            // load image and get image size
            if(strpos($ext, "png") !== false){

              try{
                $img = imagecreatefrompng($filePath);
              }catch(Exception $e){
                die("PNG Image creation error");
              }
              
            }else{

              try{
                $img = imagecreatefromjpeg($filePath);
              }catch(Exception $e){
                die("JPG Image creation error");
              }
              
            }
            
            $width = imagesx( $img );
            $height = imagesy( $img );
            
            // calculate thumbnail size
            $new_width = $thumbWidth;
            $new_height = floor( $height * ( $thumbWidth / $width ) );
            
            // create a new temporary image
            $tmp_img = imagecreatetruecolor( $new_width, $new_height );
            
            // copy and resize old image into new image 
            imagecopyresized( $tmp_img, $img, 0, 0, 0, 0, $new_width, $new_height, $width, $height );

            // save thumbnail into a file
            imagejpeg( $tmp_img, "{$pathToThumbs}m_{$fname}");
            imagedestroy($tmp_img);
          }
          
        }
        
      }
    }
  ?>

  <?php

    function getCurrentRewriteUrl(){
      $data = explode("/", $_SERVER['PHP_SELF']);
      $url = "";
      for($i=0;$i<count($data)-1;$i++){
        if($i==0) $url = $data[$i];
        else  $url .= "/".$data[$i];
      }
      return "http://".$_SERVER['HTTP_HOST'].$url;
    }
    
    function getCurrentUrl() {
      $pageURL = 'http';
      
      //if ($_SERVER["HTTPS"] == "on") {$pageURL .= "s";}
      $pageURL .= "://";
      
      if ($_SERVER["SERVER_PORT"] != "80") {
        $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
      } else {
        $pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
      }
      return $pageURL;
    }
    
    function encryptPwd($str){
      return md5($str);
    }

    function redirect($url, $time = 500){
      //header("location:".$url);
      
      echo "<script type='text/javascript'>";
        echo "window.setTimeout(\"window.location='".$url."'\",".$time.");";
      echo "</script>";
      
    }
    
    function manageData($data){
      $out = htmlspecialchars($data);
      return $out;
    }
    


    // ======= DATE


    //GMT +2
    function getDbDate($h = 0){
      return Date("Y-m-d H:i:s", mktime(Date("H") + $h, Date("i"), Date("s"), Date("m"), Date("d"), Date("Y"))); 
    }
    
    function getOnlyDate($datetime, $format = true){
      $str = explode(" ", $datetime);
      if($format){
        $str = explode("-",$str[0]);
        $str = $str[2]."/".$str[1]."/".$str[0];
      }else{
        $str = $str[0];
      }
      return $str;
    }
    
    function formatToDate($dbDate){
      $temp = explode(" ", $dbDate);
      $date = $temp[0];
      list($y,$m,$d) = explode("-", $date);
      return $d."/".$m."/".$y;
    }

    function formatToDateTime($dbDate){
      list($date,$time) = explode(" ", $dbDate);
      list($y,$m,$d) = explode("-", $date);
      list($h,$min,$s) = explode(":", $time);
      return $d."/".$m."/".$y." à ".$h."h".$min;
    }

  ?>





  <?php


    // http://forums.digitalpoint.com/showthread.php?t=182666
    function str_insert($insertstring, $intostring, $offset) {
       $part1 = substr($intostring, 0, $offset);
       $part2 = substr($intostring, $offset);
      
       $part1 = $part1 . $insertstring;
       $whole = $part1 . $part2;
       return $whole;
    }
    
    function str_remove_between($content, $beginChar, $endChar) {
      $startPos = strpos($content, $beginChar);
      $endPos = strpos($content, $endChar);
      // ...
      return $content;
    }
    
    function str_get_between($content, $beginChar, $endChar) {
      $startPos = strpos($content, $beginChar);
      $endPos = strpos($content, $endChar);
      $title = substr($content, $startPos, $endPos);
      return $title;
    }
    


  ?>



  <?php
    // USELESS ?

    function categoryAdd($cats, $newItem){
      $itemCat = $newItem["cat"];
      
      if(!isset($cats[$itemCat])) $cats[$itemCat] = array();
      $cats[$itemCat][] = $newItem;
      
      return $cats;
    }
    



    /* Apply htmlEntities to content between $tagName */
    function htmlEntitiesCode($content, $tagName) {
      $tagNameClose = str_insert("/", $tagName, 1); // "[/tagName]"
      
      $index = 0;
      $endIndex = 0;
      
      echo "<br/>while()";
      
      do {
        $index = strpos($content, $tagName, $index);
        echo "<br/>found ".$tagName." at index : ".$index;

        if ($index !== false) {

          //skip tag
          $index += (2 + strlen($tagName)); // [tagName], ie:[code]

          //search end of current tagName
          $endIndex = strpos($content, $tagNameClose, $index);
          
          //on récup tout entre le tag ouvert/fermé
          $part = substr($content, $index, $endIndex - $index);

          //remove first \n if needed
          if(strcmp($part[0],"\n")) $part = substr($part, 1);

          // remove <br/> from code
          $part = str_replace("<br/>","\n",$part);
          $part = str_replace("<br />","\n",$part);

          //$part = htmlspecialchars($part);

          //re-insert modified part
          $startPart = substr($content, 0, $index);
          $endPart = substr($content, $endIndex, strlen($content) - $endIndex);
          $content = $startPart.$part.$endPart;
        }
        
      }while ($index !== false); // tant qu'on trouve des tags de ce type
      
      echo "<br/>end while";

      return $content;
    }
    
?>