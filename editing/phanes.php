<?php

  define("ROOT", dirname(__DIR__)."/");
  include(ROOT."publishing/builder-api.php");

  $pagesDir = ROOT."editing/pages/";
  $action = $_GET["action"] ?? "list";
  $file = isset($_GET["file"]) ? basename($_GET["file"]) : "";
  $message = "";

  if($_SERVER["REQUEST_METHOD"] === "POST"){
    $postAction = $_POST["action"] ?? "save";
    $postFile = basename($_POST["file"] ?? "");

    if($postFile === "" || !preg_match('/^[A-Za-z0-9_\-]+$/', $postFile)){
      $message = "Nom de fichier invalide.";
    } else if($postAction === "delete"){
      $path = $pagesDir.$postFile.".md";
      if(file_exists($path)) unlink($path);
      $message = "Article supprimé : ".$postFile;
      $action = "list";
    } else if($postAction === "reindex"){
      $indexResult = generateIndex();
      $message = "index.html : ".$indexResult["status"]." (".$indexResult["count"]." articles).";
      $file = $postFile;
      $action = ($file !== "") ? "edit" : "list";
    } else {
      // save the source, then build the html page + refresh stats/rss, same as builder.php does per article
      $content = $_POST["content"] ?? "";
      file_put_contents($pagesDir.$postFile.".md", $content);

      $status = buildArticle($postFile);
      refreshAggregates();

      $statusLabel = ["created"=>"créée", "updated"=>"mise à jour", "unchanged"=>"inchangée"][$status];
      $message = "Article enregistré — page html ".$statusLabel.", stats & rss régénérés.";
      $file = $postFile;
      $action = "edit";
    }
  }

  function readArticle($pagesDir, $file){
    $path = $pagesDir.$file.".md";
    if(!file_exists($path)) return "";
    return file_get_contents($path);
  }

  $items = getItems();
  usort($items, function($a, $b){ return strcmp($b["file"], $a["file"]); });

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Phanes</title>
<style>
  * { box-sizing: border-box; }

  :root{
    --bg: #faf6ef;
    --bg-side: #f2ecdf;
    --paper: #fffdf8;
    --text: #3d3128;
    --muted: #8a7b6a;
    --border: #e6dbc7;
    --accent: #b5713f;
    --accent-hover: #9c5e30;
    --danger: #b5573f;
    --ok: #5f8a5a;
  }

  body{
    font-family: Georgia, 'Iowan Old Style', 'Palatino Linotype', serif;
    background:var(--bg);
    color:var(--text);
    margin:0;
    display:flex;
    height:100vh;
    line-height:1.5;
  }

  @media (max-width: 768px){
    body{ flex-direction:column; height:auto; min-height:100vh; }
    .sidebar{ width:100%; height:auto; max-height:40vh; }
    .main{ height:auto; }
    textarea{ height:40vh; }
    input[type=text]{ width:100%; }
  }

  .sidebar{width:320px;flex:none;background:var(--bg-side);border-right:1px solid var(--border);overflow-y:auto;height:100vh;}
  .sidebar h1{font-size:1.1em;font-weight:normal;padding:16px;margin:0;border-bottom:1px solid var(--border);color:var(--accent);}
  .sidebar ul{list-style:none;margin:0;padding:0;}
  .sidebar li{display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border-bottom:1px solid var(--border);}
  .sidebar li:hover{background:var(--paper);}
  .sidebar a{color:var(--text);text-decoration:none;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;}
  .sidebar a:hover{color:var(--accent);}
  .item-meta{display:block;font-size:0.8em;color:var(--muted);margin-top:2px;}

  .delete-btn{background:none;border:none;color:var(--muted);cursor:pointer;font-size:1.1em;padding:2px 6px;flex:none;}
  .delete-btn:hover{color:var(--danger);}

  .main{flex:1;padding:30px 40px;overflow-y:auto;height:100vh;}
  .main h2{font-weight:normal;color:var(--accent);}

  textarea{
    width:100%;height:58vh;
    background:var(--paper);color:var(--text);
    font-family: ui-monospace, 'Cascadia Code', Consolas, monospace;
    font-size:15px;
    border:1px solid var(--border);border-radius:4px;
    padding:14px;
  }
  input[type=text]{
    width:320px;background:var(--paper);color:var(--text);
    border:1px solid var(--border);border-radius:4px;padding:8px;font-family:inherit;
  }

  .msg{background:#eef4ec;border:1px solid #cfe0ca;color:#3f5c3a;padding:10px 14px;border-radius:4px;margin-bottom:16px;}

  button{
    padding:8px 18px;margin-top:12px;
    background:var(--accent);color:#fff;border:none;border-radius:4px;
    font-family:inherit;font-size:0.95em;cursor:pointer;
  }
  button:hover{background:var(--accent-hover);}

  .fab{
    position:fixed;bottom:30px;right:30px;width:56px;height:56px;border-radius:50%;
    background:var(--accent);color:#fff;font-size:1.8em;border:none;cursor:pointer;
    box-shadow:0 3px 10px rgba(60,40,20,0.25);
    display:flex;align-items:center;justify-content:center;text-decoration:none;line-height:1;
  }
  .fab:hover{background:var(--accent-hover);}
</style>
</head>
<body>

<div class="sidebar">
  <h1>Phanes — <?php echo count($items); ?> articles</h1>
  <ul>
  <?php foreach($items as $item): ?>
    <li>
      <a href="phanes.php?action=edit&file=<?php echo urlencode($item["file"]); ?>" title="<?php echo htmlspecialchars($item["title"]); ?>">
        <?php echo htmlspecialchars($item["file"]); ?>
        <span class="item-meta"><?php echo htmlspecialchars($item["cat"]); ?> — <?php echo htmlspecialchars($item["title"]); ?></span>
      </a>
      <form method="post" onsubmit="return confirm('Supprimer <?php echo htmlspecialchars(addslashes($item["file"])); ?> ?');">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="file" value="<?php echo htmlspecialchars($item["file"]); ?>">
        <button type="submit" class="delete-btn" title="Supprimer">🗑</button>
      </form>
    </li>
  <?php endforeach; ?>
  </ul>
</div>

<div class="main">

<?php if($message): ?><div class="msg"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>

<?php if($action === "edit" && $file !== ""): ?>
  <h2>Éditer : <?php echo htmlspecialchars($file); ?></h2>
  <form method="post">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="file" value="<?php echo htmlspecialchars($file); ?>">
    <textarea name="content"><?php echo htmlspecialchars(readArticle($pagesDir, $file)); ?></textarea><br>
    <button type="submit">Update</button>
  </form>

<?php elseif($action === "new"): ?>
  <h2>Nouvel article</h2>
  <form method="post">
    <input type="hidden" name="action" value="save">
    <p>Nom de fichier (date, ex: <?php echo date("Y-m-d"); ?> ou <?php echo date("Y-m-d"); ?>_2) :<br>
    <input type="text" name="file" value="<?php echo date("Y-m-d"); ?>"></p>
    <p>1ère ligne = "categorie soustitre Titre", puis le corps en markdown :</p>
    <textarea name="content">categorie SOUSCAT Titre de l'article

Contenu ici...</textarea><br>
    <button type="submit">Update</button>
  </form>

<?php else: ?>
  <h2>Sélectionne un article dans la barre latérale, ou crée-en un nouveau.</h2>
<?php endif; ?>

</div>

<a href="phanes.php?action=new" class="fab" title="Nouvel article">+</a>

</body>
</html>
