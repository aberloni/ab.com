<?php

  define("ROOT", dirname(__DIR__)."/");
  include(ROOT."publishing/builder-api.php");

  $pagesDir = ROOT."editing/pages/";
  $mediasDir = ROOT."articles/medias/";
  $cacheFile = ROOT."editing/.pages-cache.json";
  $action = $_GET["action"] ?? "list";
  $file = isset($_GET["file"]) ? basename($_GET["file"]) : "";
  $message = "";

  if($_SERVER["REQUEST_METHOD"] === "POST"){
    $postAction = $_POST["action"] ?? "save";
    $postFile = basename($_POST["file"] ?? "");

    if($postAction === "refreshbuffer"){
      loadPageItems($cacheFile, true);
      $message = "Liste des articles rafraîchie.";
      $action = "list";
    } else if($postAction === "deletemedia"){
      $mediaName = basename($_POST["media"] ?? "");
      if($mediaName !== "" && strpos($mediaName, "..") === false && is_file($mediasDir.$mediaName)){
        unlink($mediasDir.$mediaName);
        $message = "Média supprimé : ".$mediaName;
      } else {
        $message = "Média introuvable.";
      }
      if($postFile !== "" && preg_match('/^[A-Za-z0-9_\-]+$/', $postFile) && is_file($pagesDir.$postFile.".md")){
        $file = $postFile; $action = "edit";
      } else {
        $action = "list";
      }
    } else if($postAction === "reindex"){
      $indexResult = generateIndex();
      $message = "index.html : ".$indexResult["status"]." (".$indexResult["count"]." articles).";
      $file = $postFile;
      $action = ($file !== "") ? "edit" : "list";
    } else if($postFile === "" || !preg_match('/^[A-Za-z0-9_\-]+$/', $postFile)){
      $message = "Nom de fichier invalide.";
    } else if($postAction === "delete"){
      $path = $pagesDir.$postFile.".md";
      if(file_exists($path)) unlink($path);
      @unlink($cacheFile);
      $message = "Article supprimé : ".$postFile;
      $action = "list";
    } else {
      // save the source and rebuild only this page's html
      $content = $_POST["content"] ?? "";
      file_put_contents($pagesDir.$postFile.".md", $content);
      @unlink($cacheFile);

      ob_start();
      $status = buildArticle($postFile);
      ob_end_clean();

      $statusLabel = ["created"=>"créée", "updated"=>"mise à jour", "unchanged"=>"inchangée"][$status];
      $message = "Page ".$statusLabel.".";
      $file = $postFile;
      $action = "edit";
    }
  }

  function readArticle($pagesDir, $file){
    $path = $pagesDir.$file.".md";
    if(!file_exists($path)) return "";
    return file_get_contents($path);
  }

  // buffered article list : rescanned only when the buffer is missing or older than 1h
  function loadPageItems($cacheFile, $force = false){
    if(!$force && is_file($cacheFile) && (time() - filemtime($cacheFile)) < 3600){
      $c = json_decode(file_get_contents($cacheFile), true);
      if(is_array($c)) return $c;
    }
    $items = getItems();
    if(!is_array($items)) $items = array();
    usort($items, function($a, $b){ return strcmp($b["file"], $a["file"]); });
    file_put_contents($cacheFile, json_encode($items));
    return $items;
  }

  $items = loadPageItems($cacheFile);
  $cacheAge = is_file($cacheFile) ? (time() - filemtime($cacheFile)) : 0;
  $issues = function_exists("getInvalidHeaders") ? getInvalidHeaders() : array();
  krsort($issues); // most recent first (filenames are dates)
  $tab = ($_GET["tab"] ?? "") === "issue" ? "issue" : "published";
  // deduce the tab from the selected article when not explicitly given
  if($action === "edit" && $file !== "" && isset($issues[$file])) $tab = "issue";

  // saved an article from the issue tab and it's now valid -> jump to the next issue
  if(($_POST["action"] ?? "") === "save" && ($_POST["tab"] ?? "") === "issue" && !isset($issues[$file])){
    $tab = "issue";
    if($issues){ reset($issues); $file = key($issues); $action = "edit"; }
    else { $file = ""; $action = "list"; }
  }

  $sort = ($_GET["sort"] ?? "") === "date" ? "date" : "edited";
  if($sort === "edited" && is_array($items)){
    foreach($items as &$it){ $it["mtime"] = @filemtime($pagesDir.$it["file"].".md"); }
    unset($it);
    usort($items, function($a, $b){ return $b["mtime"] - $a["mtime"]; });
  }

  $medias = [];
  if(is_dir($mediasDir)){
    foreach(scandir($mediasDir) as $m){
      if($m === "." || $m === ".." || is_dir($mediasDir.$m)) continue;
      $ext = strtolower(pathinfo($m, PATHINFO_EXTENSION));
      $medias[] = [
        "file" => $m,
        "ext"  => $ext,
        "img"  => in_array($ext, ["jpg","jpeg","png","gif","webp","svg"]),
        "size" => filesize($mediasDir.$m),
        "mtime" => filemtime($mediasDir.$m),
      ];
    }
    usort($medias, function($a, $b){ return $b["mtime"] - $a["mtime"]; });
  }

  function humanSize($b){
    if($b >= 1048576) return round($b/1048576, 1)."M";
    if($b >= 1024) return round($b/1024)."k";
    return $b."o";
  }

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

  .tabs{display:flex;border-bottom:1px solid var(--border);}
  .tabs a{flex:1;text-align:center;padding:9px;text-decoration:none;color:var(--muted);font-size:0.9em;}
  .tabs a.active{color:var(--accent);background:var(--paper);box-shadow:inset 0 -2px 0 var(--accent);}
  .tabs a:hover{color:var(--accent);}
  .issue-reason{font-size:0.85em;color:var(--danger);}
  .issue-reason::before{content:" — ";}
  .navbar{display:flex;gap:8px;padding:12px 14px;}
  .navbar form{flex:1;margin:0;}
  .navbar button{width:100%;margin:0;padding:8px 6px;font-size:0.85em;}
  .sort-toggle{flex:none;font-size:0.8em;color:var(--muted);text-decoration:none;border:1px solid var(--border);border-radius:4px;padding:6px 8px;white-space:nowrap;}
  .sort-toggle:hover{color:var(--accent);border-color:var(--accent);}
  .live{flex:none;margin-left:6px;font-size:0.95em;line-height:1;}
  .live.on{color:var(--ok);}
  .live.off{color:var(--muted);opacity:0.6;}

  .catalog{
    position:fixed;top:0;right:0;width:360px;max-width:90vw;height:100vh;
    background:var(--bg-side);border-left:1px solid var(--border);
    box-shadow:-4px 0 16px rgba(60,40,20,0.18);
    transform:translateX(100%);transition:transform 0.22s ease;
    z-index:50;display:flex;flex-direction:column;
  }
  .catalog.open{transform:translateX(0);}
  .catalog h2{font-size:1.05em;font-weight:normal;color:var(--accent);margin:0;padding:16px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;}
  .catalog .close{background:none;border:none;color:var(--muted);font-size:1.3em;cursor:pointer;padding:0;margin:0;line-height:1;}
  .catalog .close:hover{color:var(--danger);}
  .catalog-body{overflow-y:auto;flex:1;}
  .catalog-backdrop{position:fixed;inset:0;background:rgba(40,28,16,0.28);opacity:0;pointer-events:none;transition:opacity 0.22s ease;z-index:40;}
  .catalog-backdrop.open{opacity:1;pointer-events:auto;}

  .media-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;padding:12px;}
  .media-cell{position:relative;background:var(--paper);border:1px solid var(--border);border-radius:4px;padding:6px;cursor:pointer;overflow:hidden;}
  .media-cell:hover{border-color:var(--accent);}
  .media-del{position:absolute;top:4px;right:4px;margin:0;opacity:0;transition:opacity 0.12s;}
  .media-cell:hover .media-del{opacity:1;}
  .media-del button{margin:0;padding:2px 5px;font-size:0.85em;background:rgba(255,253,248,0.9);color:var(--muted);border:1px solid var(--border);border-radius:3px;}
  .media-del button:hover{color:#fff;background:var(--danger);border-color:var(--danger);}
  .media-cell img{width:100%;height:80px;object-fit:cover;border-radius:2px;display:block;background:var(--bg-side);}
  .media-cell .ph{width:100%;height:80px;display:flex;align-items:center;justify-content:center;background:var(--bg-side);border-radius:2px;color:var(--muted);text-transform:uppercase;font-size:0.9em;}
  .media-cell .name{font-size:0.72em;color:var(--muted);margin-top:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  .sidebar li{display:flex;align-items:center;justify-content:space-between;padding:4px 12px;border-bottom:1px solid var(--border);}
  .sidebar li:hover{background:var(--paper);}
  .sidebar a{color:var(--text);text-decoration:none;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;min-width:0;}
  .sidebar a:hover{color:var(--accent);}
  .item-meta{display:block;font-size:0.9em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  .item-cat{font-size:0.8em;color:var(--muted);opacity:0.7;margin-right:6px;text-transform:uppercase;letter-spacing:0.03em;}
  .item-date{display:block;font-size:0.68em;color:var(--muted);font-family:ui-monospace,Consolas,monospace;}

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

  .preview{
    margin-top:16px;background:var(--paper);border:1px solid var(--border);
    border-radius:4px;padding:20px 24px;font-family:Georgia,serif;
  }
  .preview img,.preview video{max-width:100%;height:auto;border-radius:3px;display:block;margin:12px 0;background:var(--bg-side);}
  .preview h1{color:var(--accent);font-weight:normal;margin-top:0;}
  .preview pre{background:var(--bg-side);padding:12px;border-radius:4px;overflow-x:auto;font-family:ui-monospace,Consolas,monospace;font-size:13px;tab-size:4;-moz-tab-size:4;}
  .preview code{background:var(--bg-side);padding:1px 4px;border-radius:3px;font-size:0.9em;}
  .preview hr{border:none;border-top:1px solid var(--border);}
  .preview-label{font-size:0.8em;color:var(--muted);margin:0 0 6px;text-transform:uppercase;letter-spacing:0.05em;}

  .editor-split{display:flex;flex-direction:column;gap:20px;}
  .editor-split > form{flex:1;min-width:0;}
  .editor-split .preview-pane{flex:1;min-width:0;}
  .editor-split .preview{margin-top:0;}
  @media (min-width:1100px){
    .editor-split{flex-direction:row;align-items:flex-start;}
    .editor-split textarea{height:72vh;}
    .editor-split .preview{max-height:72vh;overflow-y:auto;}
  }

  .msg{background:#eef4ec;border:1px solid #cfe0ca;color:#3f5c3a;padding:10px 14px;border-radius:4px;margin-bottom:16px;}

  button{
    padding:8px 18px;margin-top:12px;
    background:var(--accent);color:#fff;border:none;border-radius:4px;
    font-family:inherit;font-size:0.95em;cursor:pointer;
  }
  button:hover{background:var(--accent-hover);}

  .fab-stack{position:fixed;bottom:30px;right:30px;display:flex;flex-direction:column;gap:12px;z-index:60;}
  .fab{
    width:56px;height:56px;border-radius:50%;
    background:var(--accent);color:#fff;font-size:1.8em;border:none;cursor:pointer;
    box-shadow:0 3px 10px rgba(60,40,20,0.25);
    display:flex;align-items:center;justify-content:center;text-decoration:none;line-height:1;
  }
  .fab.secondary{background:var(--bg-side);color:var(--accent);border:1px solid var(--border);font-size:1.4em;}
  .fab:hover{background:var(--accent-hover);color:#fff;}
</style>
</head>
<body>

<div class="sidebar">
  <h1>Phanes — <?php echo $tab === "issue" ? count($issues)." à corriger" : count($items)." publiés"; ?></h1>
  <div class="tabs">
    <a href="phanes.php?tab=published" class="<?php echo $tab === "published" ? "active" : ""; ?>">Publiés (<?php echo count($items); ?>)</a>
    <a href="phanes.php?tab=issue" class="<?php echo $tab === "issue" ? "active" : ""; ?>">À corriger (<?php echo count($issues); ?>)</a>
  </div>
  <div class="navbar">
    <form method="post">
      <input type="hidden" name="action" value="reindex">
      <button type="submit">Régénérer l'index</button>
    </form>
    <form method="post">
      <input type="hidden" name="action" value="refreshbuffer">
      <button type="submit" title="Buffer : <?php echo $cacheAge < 60 ? $cacheAge."s" : floor($cacheAge/60)."min"; ?>">Rafraîchir</button>
    </form>
  </div>
  <div style="padding:0 14px 12px;display:flex;gap:8px;align-items:center;">
    <input type="text" id="search" placeholder="Rechercher…" autocomplete="off" style="flex:1;min-width:0;">
    <?php if($tab === "published"): ?>
    <a class="sort-toggle" href="phanes.php?tab=published&sort=<?php echo $sort === "edited" ? "date" : "edited"; ?>" title="Trier">
      <?php echo $sort === "edited" ? "↻ édité" : "▾ date"; ?>
    </a>
    <?php endif; ?>
  </div>

  <?php if($tab === "issue"): ?>
  <ul id="artlist">
  <?php foreach($issues as $name => $reason):
    $firstLine = trim((string)@file_get_contents($pagesDir.$name.".md", false, null, 0, 300));
    $firstLine = strtok($firstLine, "\n");
    if($firstLine === false || $firstLine === "") $firstLine = "(vide)";
  ?>
    <li data-search="<?php echo htmlspecialchars(strtolower($name." ".$firstLine." ".$reason)); ?>">
      <a href="phanes.php?tab=issue&action=edit&file=<?php echo urlencode($name); ?>" title="<?php echo htmlspecialchars($firstLine." — ".$reason); ?>">
        <?php echo htmlspecialchars($firstLine); ?><span class="issue-reason"><?php echo htmlspecialchars(is_string($reason) ? $reason : "en-tête invalide"); ?></span>
      </a>
      <form method="post" onsubmit="return confirm('Supprimer <?php echo htmlspecialchars(addslashes($name)); ?> ?');">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="file" value="<?php echo htmlspecialchars($name); ?>">
        <button type="submit" class="delete-btn" title="Supprimer">🗑</button>
      </form>
    </li>
  <?php endforeach; ?>
  </ul>
  <?php else: ?>
  <ul id="artlist">
  <?php foreach($items as $item): $live = is_file(ROOT."articles/".$item["file"].".html"); ?>
    <li data-search="<?php echo htmlspecialchars(strtolower($item["title"]." ".$item["file"]." ".$item["cat"])); ?>">
      <a href="phanes.php?tab=<?php echo $tab; ?>&action=edit&file=<?php echo urlencode($item["file"]); ?>" title="<?php echo htmlspecialchars($item["title"]); ?>">
        <span class="item-meta"><span class="item-cat"><?php echo htmlspecialchars($item["cat"]); ?></span><?php echo htmlspecialchars($item["title"]); ?></span><span class="item-date"><?php echo htmlspecialchars($item["file"]); ?></span>
      </a>
      <form method="post" onsubmit="return confirm('Supprimer <?php echo htmlspecialchars(addslashes($item["file"])); ?> ?');">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="file" value="<?php echo htmlspecialchars($item["file"]); ?>">
        <button type="submit" class="delete-btn" title="Supprimer">🗑</button>
      </form>
      <span class="live <?php echo $live ? "on" : "off"; ?>" title="<?php echo $live ? "En ligne" : "Pas de HTML"; ?>"><?php echo $live ? "✓" : "○"; ?></span>
    </li>
  <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</div>

<div class="main">

<?php if($message): ?><div class="msg"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>

<?php if($action === "edit" && $file !== ""): ?>
  <h2>Éditer : <?php echo htmlspecialchars($file); ?></h2>
  <div class="editor-split">
    <form method="post">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="file" value="<?php echo htmlspecialchars($file); ?>">
      <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
      <p class="preview-label">Source</p>
      <textarea name="content" id="src"><?php echo htmlspecialchars(readArticle($pagesDir, $file)); ?></textarea><br>
      <button type="submit">Update</button>
    </form>
    <div class="preview-pane">
      <p class="preview-label">Aperçu</p>
      <div class="preview" id="preview"></div>
    </div>
  </div>
  <form method="post" style="display:inline;">
    <input type="hidden" name="action" value="reindex">
    <input type="hidden" name="file" value="<?php echo htmlspecialchars($file); ?>">
    <button type="submit">Régénérer l'index seul</button>
  </form>

<?php elseif($action === "new"): ?>
  <h2>Nouvel article</h2>
  <form method="post">
    <input type="hidden" name="action" value="save">
    <p>Nom de fichier (date, ex: <?php echo date("Y-m-d"); ?> ou <?php echo date("Y-m-d"); ?>_2) :<br>
    <input type="text" name="file" value="<?php echo date("Y-m-d"); ?>"></p>
    <p>1ère ligne = "categorie soustitre Titre", puis le corps en markdown :</p>
    <div class="editor-split">
      <div style="flex:1;min-width:0;">
        <p class="preview-label">Source</p>
        <textarea name="content" id="src">categorie SOUSCAT Titre de l'article

Contenu ici...</textarea><br>
        <button type="submit">Update</button>
      </div>
      <div class="preview-pane">
        <p class="preview-label">Aperçu</p>
        <div class="preview" id="preview"></div>
      </div>
    </div>
  </form>

<?php else: ?>
  <h2>Sélectionne un article dans la barre latérale, ou crée-en un nouveau.</h2>
<?php endif; ?>

</div>

<div class="fab-stack">
  <button type="button" class="fab secondary" title="Catalogue médias" onclick="toggleCatalog()">&#128366;</button>
  <a href="phanes.php?action=new" class="fab" title="Nouvel article">+</a>
</div>

<div class="catalog-backdrop" id="catalogBackdrop" onclick="toggleCatalog(false)"></div>
<aside class="catalog" id="catalog">
  <h2><?php echo count($medias); ?> médias <button type="button" class="close" onclick="toggleCatalog(false)" title="Fermer">×</button></h2>
  <div class="catalog-body">
    <div class="media-grid">
    <?php foreach($medias as $m):
      $isVideo = in_array($m["ext"], ["mp4","webm","ogg","mov"]);
      $jsName = htmlspecialchars(addslashes($m["file"]));
    ?>
      <div class="media-cell" title="<?php echo htmlspecialchars($m["file"])." — ".humanSize($m["size"]); ?>" onclick="insertMedia('<?php echo $jsName; ?>', <?php echo $isVideo ? "true" : "false"; ?>)">
        <?php if($m["img"]): ?>
          <img src="/articles/medias/<?php echo rawurlencode($m["file"]); ?>" loading="lazy" alt="">
        <?php else: ?>
          <div class="ph"><?php echo htmlspecialchars($m["ext"]); ?></div>
        <?php endif; ?>
        <div class="name"><?php echo htmlspecialchars($m["file"]); ?></div>
        <form method="post" class="media-del" onclick="event.stopPropagation();" onsubmit="return confirm('Supprimer <?php echo $jsName; ?> ?');">
          <input type="hidden" name="action" value="deletemedia">
          <input type="hidden" name="media" value="<?php echo htmlspecialchars($m["file"]); ?>">
          <input type="hidden" name="file" value="<?php echo ($action === "edit") ? htmlspecialchars($file) : ""; ?>">
          <button type="submit" title="Supprimer">🗑</button>
        </form>
      </div>
    <?php endforeach; ?>
    </div>
  </div>
</aside>

<script>
(function(){
  var s = document.getElementById("search"), list = document.getElementById("artlist");
  if(!s || !list) return;
  s.addEventListener("input", function(){
    var q = s.value.trim().toLowerCase();
    list.querySelectorAll("li").forEach(function(li){
      li.style.display = (!q || li.dataset.search.indexOf(q) !== -1) ? "" : "none";
    });
  });
})();

function toggleCatalog(force){
  var c = document.getElementById("catalog"), b = document.getElementById("catalogBackdrop");
  var open = force === undefined ? !c.classList.contains("open") : force;
  c.classList.toggle("open", open);
  b.classList.toggle("open", open);
}

function insertMedia(name, isVideo){
  var ta = document.getElementById("src");
  if(!ta){ return; }
  var snippet = isVideo ? ("{[" + name + "]}") : ("{{" + name + "}}");
  var s = ta.selectionStart, e = ta.selectionEnd;
  ta.value = ta.value.slice(0, s) + snippet + ta.value.slice(e);
  var pos = s + snippet.length;
  ta.focus();
  ta.setSelectionRange(pos, pos);
  ta.dispatchEvent(new Event("input"));
}

function mdEsc(s){ return s.replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;"); }
function mdMedia(f){
  f = f.trim();
  if(/^(https?:)?\//.test(f)) return f;
  if(/^medias\//.test(f)) return "/articles/" + f;
  return "/articles/medias/" + f;
}
function mdInline(s){
  s = mdEsc(s);
  s = s.replace(/!\[([^\]]*)\]\(([^)]+)\)/g, function(m,a,u){ return '<img src="'+mdMedia(u)+'" alt="'+a+'">'; });
  s = s.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank">$1</a>');
  s = s.replace(/\*\*([^*]+)\*\*/g, "<strong>$1</strong>");
  s = s.replace(/\*([^*]+)\*/g, "<em>$1</em>");
  s = s.replace(/`([^`]+)`/g, "<code>$1</code>");
  return s;
}
function mdRender(src){
  var parts = src.split(/^---$/m), head = "", body = src;
  if(parts.length > 1){ head = parts[0]; body = parts.slice(1).join("---"); }
  var out = "";
  var hl = head.split(/\n/).map(function(l){ return l.trim(); }).filter(Boolean);
  if(hl[1]) out += "<h1>" + mdEsc(hl[1]) + "</h1>";
  body = body.replace(/\{\{([^}]+)\}\}/g, function(m,f){ return '<img src="'+mdMedia(f)+'" alt="">'; });
  body = body.replace(/\{\[([^\]]+)\]\}/g, function(m,f){ return '<video controls src="'+mdMedia(f)+'"></video>'; });
  var lines = body.split(/\n/), html = "", inList = false, inCode = false, code = "";
  function closeList(){ if(inList){ html += "</ul>"; inList = false; } }
  for(var i = 0; i < lines.length; i++){
    var l = lines[i];
    if(/^```/.test(l)){
      if(inCode){ html += "<pre>" + mdEsc(code) + "</pre>"; code = ""; inCode = false; }
      else { closeList(); inCode = true; }
      continue;
    }
    if(inCode){ code += (code ? "\n" : "") + l; continue; }
    if(/^\s*$/.test(l)){ closeList(); continue; }
    if(/^#{1,6}\s/.test(l)){ closeList(); var n = l.match(/^#+/)[0].length; html += "<h"+n+">" + mdInline(l.replace(/^#+\s/,"")) + "</h"+n+">"; continue; }
    if(/^\s*[-*]\s+/.test(l)){ if(!inList){ html += "<ul>"; inList = true; } html += "<li>" + mdInline(l.replace(/^\s*[-*]\s+/,"")) + "</li>"; continue; }
    if(/^\s*(---|\*\*\*|___)\s*$/.test(l)){ closeList(); html += "<hr>"; continue; }
    closeList();
    var ind = (l.match(/^[\t ]+/) || [""])[0];
    var w = 0; for(var k = 0; k < ind.length; k++) w += (ind[k] === "\t") ? (4 - (w % 4)) : 1;
    html += "<p>" + (w ? "&nbsp;".repeat(w) : "") + mdInline(l.slice(ind.length)) + "</p>";
  }
  if(inCode) html += "<pre>" + mdEsc(code) + "</pre>";
  closeList();
  return out + html;
}
(function(){
  var ta = document.getElementById("src"), pv = document.getElementById("preview");
  if(!ta || !pv) return;
  var upd = function(){ pv.innerHTML = mdRender(ta.value); };
  ta.addEventListener("input", upd);
  ta.addEventListener("keydown", function(e){
    if(e.key !== "Tab" || e.shiftKey) return;
    e.preventDefault();
    var s = ta.selectionStart, en = ta.selectionEnd, pad = "\t";
    ta.value = ta.value.slice(0, s) + pad + ta.value.slice(en);
    ta.selectionStart = ta.selectionEnd = s + pad.length;
    upd();
  });
  upd();
})();
</script>

</body>
</html>
