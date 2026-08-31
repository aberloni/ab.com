<?php

    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    define("ROOT", dirname(__DIR__)."/");

    include("builder-api.php");

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Build</title>
<style>
  body{font-family:monospace;background:#111;color:#eee;padding:20px 30px;}
  h1{font-size:1.3em;border-bottom:1px solid #333;padding-bottom:6px;margin-top:30px;}
  .summary{background:#181818;border:1px solid #333;padding:12px 16px;margin:10px 0;}
  .summary b{color:#8cf;}
  table{border-collapse:collapse;width:100%;margin-top:10px;}
  td,th{border:1px solid #333;padding:4px 10px;text-align:left;}
  .created{color:#7d7;}
  .updated{color:#dc7;}
  .error{color:#f66;}
  .muted{color:#777;}
  #home-link{position:fixed;left:0;bottom:0;padding:8px 12px;background:#181818;border:1px solid #333;color:#8cf;text-decoration:none;font-size:0.85em;}
  #home-link:hover{color:#fff;}
</style>
</head>
<body>

<a id="home-link" href="/">← homepage</a>

<?php
    $indexResult = generateIndex();
    echo '<h1>index.html</h1>';
    echo '<div class="summary">'.$indexResult["count"]." articles — index.html : ".$indexResult["status"].'</div>';

    generateArticles();
?>

</body>
</html>

<?php

    /// generate all html files based on md contents, skipping articles whose output hasn't changed
    /// (uses the same buildArticle() as phanes.php's individual "Update" button)
    function generateArticles()
    {
        $items = getItems();

        $created = [];
        $updated = [];
        $unchangedCount = 0;

        foreach($items as $article)
        {
            $status = buildArticle($article["file"]);

            if($status === "created") $created[] = $article["file"];
            else if($status === "updated") $updated[] = $article["file"];
            else $unchangedCount++;
        }

        echo '<h1>Articles</h1>';
        echo '<div class="summary">';
        echo count($items)." articles au total — ";
        echo "<b>".count($created)."</b> créés, ";
        echo "<b>".count($updated)."</b> mis à jour, ";
        echo "<span class='muted'>".$unchangedCount." inchangés (ignorés)</span>";
        echo '</div>';

        if(count($created) > 0 || count($updated) > 0){
            echo '<table><tr><th>Fichier</th><th>Statut</th></tr>';
            foreach($created as $date) echo '<tr><td>'.htmlspecialchars($date).'</td><td class="created">créé</td></tr>';
            foreach($updated as $date) echo '<tr><td>'.htmlspecialchars($date).'</td><td class="updated">mis à jour</td></tr>';
            echo '</table>';
        }

        // STATS + RSS

        echo '<h1>Stats & RSS</h1>';
        refreshAggregates();
        echo '<div class="summary">stats/data_year.js, data_month.js et rss.xml régénérés</div>';
    }


?>
