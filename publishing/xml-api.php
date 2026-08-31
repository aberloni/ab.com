<?php
  error_reporting(E_ALL);

  include_once("builder-api.php");
  include_once("stats-api.php");

  function xml_update()
  {
    echo "<h1>XML API</h1>";

    $list = gatherPages();
    
    echo "<p>".count($list)." pages</p>";

    recreateXmlFile();
  }

  function recreateXmlFile()
  {
    $list = gatherPages();
    
    $strDate = "2015-09-01";
    // filter all articles before this date
    $refDate = strtotime($strDate);
    
    $output = '<?xml version="1.0" ?><rss version="2.0">';
    $output .= '<channel><title>Andre BERLEMONT</title><link>http://www.andreberlemont.com/</link>';
    $output .= '<description></description>';
    $output .= '<lastBuildDate>'.date("r").'</lastBuildDate>';

    $cnt = 0;

    foreach($list as $page)
    {
      $path = ROOT."editing/pages/".$page;
      $dt = explode(".", $page)[0];
      $fileDate = filemtime($path);

      //echo "<br/> '".$path."' ( ref date ".$refDate." < ) ".$fileDate;

      // nothing before ref date
      if($refDate > $fileDate) continue;

      // skip articles with an invalid header (no html is generated for them)
      $art = parseArticleFile(ROOT."editing/pages/".$dt.".md");
      if(!is_array($art)) continue;

      $link = 'http://www.andreberlemont.com/'.$dt;
      $title = htmlspecialchars($art["title"], ENT_XML1 | ENT_COMPAT, 'UTF-8');

      //date is the first 10 chars of the file name (YYYY-MM-DD), ignoring any -N suffix for same-day articles
      $articleDate = strtotime(substr($dt, 0, 10));

      //echo "<br/> '".$path."' (".$refDate.") change date : ".$fileDate;
      $output .= '<item>';
      $output .= '<title>'.$title.'</title>';
      $output .= '<link>'.$link.'</link>';
      $output .= '<guid isPermaLink="false">'.$dt.'</guid>';
      $output .= '<pubDate>'.date("r", $articleDate).'</pubDate>';
      $output .= '<description><![CDATA['.article_toHtml($dt).']]></description>';
      $output .= '</item>';
      $cnt++;
    }

    $output .= '</channel></rss>';

    echo "<p>total processed x".$cnt." (all after : ".$strDate.")</p>";

    // DUMP (collapsed — click to reveal)
    echo '<details><summary style="cursor:pointer">xml output</summary>';
    echo '<pre style="white-space:pre-wrap;background:#181818;border:1px solid #333;padding:10px;overflow:auto;">';
    echo htmlentities($output);
    echo '</pre></details>';

    // WRITE
    $file = fopen(ROOT."rss.xml","w");
    fwrite($file, $output);
    fclose($file);
  }

?>