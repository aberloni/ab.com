<?php

    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    define("ROOT", dirname(__DIR__)."/");

    include("builder-api.php");

    //echo "test".PHP_EOL."lol";


    $_CONFIG = loadConfig();
    print_r($_CONFIG);

?>

<html>
<head>
  <style>
    body
    {
      color:#000;
    }

    h1{
      font-size:2em;
      padding-top:20px;
    }
    .error{
      font-size:2em;
      color:#f00;
    }

  </style>

<body>
<div id="builder">

<?php
    generateIndex();
    generateArticles();
?>

</div>
</body>

</head>
</html>

<?php

    function loadConfig()
    {
        $configData = file_get_contents(ROOT."config.json");
        $config = json_decode($configData);
        //print_r($config);
        return $config;
    }

    /// generate all html files based on txts contents
    ///
    function generateArticles()
    {
        $outputFolder = "html/";

        echo '<h1>Generating Articles</h1>';

        $path = ROOT."pages/";

        if(!is_dir($path.$outputFolder)) mkdir($path.$outputFolder, 0755, true);

        $items = getItems();
        $titles = [];

        // write all html pages for buffer

        foreach($items as $article)
        {
            $output = "";

            $date = $article["file"];

            //echo "<br/> -->updating article of date : ".$date;

            $output .= article_toHtml($date);

            echo "<h3>output</h3>";
            echo htmlentities($output);

            $fileName = $path.$outputFolder.$date.".html";

            //add to array of titles
            $titles[] = $fileName;

            file_put_contents($fileName, $output);
        }

        echo "<p>".count($items)." articles updated</p>";

        // STATS

        echo '<h1>Updating stats</h1>';
        include("stats-api.php");
        stats_update();

        // RSS

        echo '<h1>Updating xml</h1>';
        include("xml-api.php");
        xml_update();
        echo '</div>';
    }
    

    /// generating homepage
    ///
    function generateIndex()
    {
        global $_CONFIG;
        $conf = $_CONFIG;
        
        $outputFile = ROOT."index.html";

        echo '<h1>Generating index.html</h1>';

        $output = "";

        // PAGE
        $output = '<html lang="en">'.PHP_EOL;

        //HEADER
        $configHeader = $conf->{"header"};
        $output .= '<head>'.PHP_EOL;
        $output .= '<title>'.$configHeader->{"title"}.'</title>'.PHP_EOL;

        $output .= '<link rel="icon" type="image/png" href="favicon.png">';

        $output .= PHP_EOL;
        $output .= '<meta charset="'.$configHeader->{"charset"}.'" />'.PHP_EOL;
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

          //GOOGLE
        $output .= html_googleAnalytics($configHeader->{"googleAnalytics"}).PHP_EOL;

        $output .= '</head>'.PHP_EOL;

        //BODY
        $output .= PHP_EOL;
        $output .= '<body>'.PHP_EOL;

        $output .= PHP_EOL.PHP_EOL;

        $output .= '<div id="all">';
        $categories = $conf->{"categories"};
        //$categories = getCategories();
        //print_r($categories);

        //rangé dans une hashtable avec les noms des categories pour key
        $articlesByCategories = getItemsByCategories();

        echo "articles x".count($articlesByCategories);

        //SEARCH BOX
        $output .= '<div class="searchZone">';
        $output .= '<input id="search" value="Search for articles here" />&nbsp;';
        $output .= '</div>';

        //CATEGORIES
        $output .= '<div id="categories">'.PHP_EOL.PHP_EOL;

        //CREATE EACH LINE FOR EACH ARTICLE (BY CATEGORY)
        $list = [];
        foreach($categories as $category)
        {
            $cat = $category->{"cat"};
            $catTitle = $category->{"title"};

            $color = floor(rand(1,5));
            $output .= '<a href="#" class="category" id="'.$cat.'" rel="'.$color.'">'.ucfirst($catTitle).'</a>'.PHP_EOL;
            $output .= generateCatListToHtml($articlesByCategories, $cat).PHP_EOL;
        }

        //FIXED PAGE
        $fixed = $conf->{"fixed"};
        foreach($fixed as $page)
        {
            $color = floor(rand(1,5));
            $output .= '<a href="page-'.$page.'" class="category" id="'.$page.'" rel="'.$color.'">'.ucfirst($page).'</a>'.PHP_EOL;
        }

        $output .= '</div>';
        $output .= '</div>';

          //ALL ARTICLE CONCAT
        //echo "articles ? ".count($articlesByCategories);
        $list = generateArticlesLinksToHtml($categories, $articlesByCategories);

        $output .= '<div id="filter">';
        foreach($list as $articleLine)  $output .= $articleLine;
        $output .= '</div>';

          //OVERLAY
        $output .= PHP_EOL.PHP_EOL;
        $output .= '<div id="overlay-click"></div>';
        $output .= '<div id="overlay-bg"></div>';
        $output .= '<div id="overlay" rel=""></div>';

        $output .= PHP_EOL.PHP_EOL;
        $output .= '</body>'.PHP_EOL;

        $output .= '</html>';
        
        //WRITE OUPUT
        $file = fopen($outputFile, "w+");

        fwrite($file, $output);
        fclose($file);

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

?>
