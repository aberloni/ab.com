<?php
  // One-shot recovery script: rebuilds editing/pages/*.md from the surviving
  // articles/*.html + index.html (cat/sub/title come from index.html's link
  // structure, body is reverse-converted from the generated HTML to markdown).
  // Best-effort: not byte-identical to the lost originals.

  define("ROOT", dirname(__DIR__)."/");

  $indexPath = ROOT."index.html";
  $articlesDir = ROOT."articles/";
  $outDir = ROOT."editing/pages/";

  if(!is_dir($outDir)) mkdir($outDir, 0755, true);

  libxml_use_internal_errors(true);
  $dom = new DOMDocument();
  $dom->loadHTMLFile($indexPath);
  $xpath = new DOMXPath($dom);

  $map = []; // file => [cat, sub, title]

  $linksDivs = $xpath->query('//div[@id="categories"]/div[starts-with(@id,"links-")]');
  foreach($linksDivs as $linksDiv){
    $cat = substr($linksDiv->getAttribute("id"), strlen("links-"));

    $items = $xpath->query('.//div[contains(@class,"category-item")]', $linksDiv);
    foreach($items as $item){
      $subNode = $xpath->query('.//a[@class="cat-line-sub"]', $item)->item(0);
      $articleNode = $xpath->query('.//a[@class="cat-line-article"]', $item)->item(0);
      if(!$articleNode) continue;

      $file = $articleNode->getAttribute("href");
      $sub = $subNode ? trim($subNode->textContent) : "";
      $title = trim($articleNode->textContent);

      $map[$file] = ["cat"=>$cat, "sub"=>$sub, "title"=>$title];
    }
  }

  echo count($map)." articles mappés depuis index.html\n";

  function htmlToMarkdown($html){
    $text = preg_replace('/<br\s*\/?>/i', "\n", $html);

    // images {{file}}
    $text = preg_replace_callback('#<img src="[^"]*medias/([^"]+)"\s*/?>#i', function($m){
      return "{{".$m[1]."}}";
    }, $text);

    // video {[file]}
    $text = preg_replace_callback('#<video[^>]*><source src="[^"]*medias/([^"]+)"[^>]*>.*?</video>#is', function($m){
      return "{[".$m[1]."]}";
    }, $text);

    // headings
    $text = preg_replace('#<h1>(.*?)</h1>#is', "# $1\n", $text);
    $text = preg_replace('#<h2>(.*?)</h2>#is', "## $1\n", $text);
    $text = preg_replace('#<h3>(.*?)</h3>#is', "### $1\n", $text);

    // code blocks
    $text = preg_replace_callback('#<pre><code[^>]*>(.*?)</code></pre>#is', function($m){
      return "\n```\n".html_entity_decode($m[1], ENT_QUOTES)."\n```\n";
    }, $text);

    // lists
    $text = preg_replace('#</?ul>#i', "", $text);
    $text = preg_replace('#<li>\s*(?:<p>)?(.*?)(?:</p>)?\s*</li>#is', "- $1\n", $text);

    // links: bare autolink (href == label) stays raw, else -> [label](href)
    $text = preg_replace_callback('#<a href="([^"]+)"[^>]*>(.*?)</a>#is', function($m){
      $href = $m[1]; $label = trim($m[2]);
      return ($href === $label) ? $href : "[".$label."](".$href.")";
    }, $text);

    // paragraphs -> blank-line separated blocks
    $text = preg_replace('#<p>(.*?)</p>#is', "$1\n\n", $text);

    $text = strip_tags($text);
    $text = html_entity_decode($text, ENT_QUOTES, "UTF-8");
    $text = preg_replace("/\n{3,}/", "\n\n", $text);

    return trim($text)."\n";
  }

  $count = 0;
  $skipped = [];

  foreach(glob($articlesDir."*.html") as $htmlFile){
    $file = basename($htmlFile, ".html");

    if(!isset($map[$file])){
      $skipped[] = $file;
      continue;
    }

    $raw = file_get_contents($htmlFile);
    if(!preg_match('#<div id="content">(.*)</div>\s*$#is', $raw, $m)){
      $skipped[] = $file;
      continue;
    }

    $body = htmlToMarkdown($m[1]);

    $info = $map[$file];
    $header = trim($info["cat"]." ".$info["sub"]." ".$info["title"]);
    $header = preg_replace('/\s+/', ' ', $header);

    file_put_contents($outDir.$file.".md", $header."\n\n".$body);
    $count++;
  }

  echo $count." fichiers .md reconstruits dans ".$outDir."\n";

  if(count($skipped) > 0){
    echo count($skipped)." fichiers ignorés (absents de index.html, à reconstruire à la main) :\n";
    echo implode(", ", $skipped)."\n";
  }
?>
