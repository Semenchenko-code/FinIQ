<?php
require_once __DIR__ . '/helpers.php';
if (!is_installed()) { header('Location: /install.php'); exit; }
$lang = ($_GET['lang'] ?? 'uk') === 'en' ? 'en' : 'uk';
$slug = preg_replace('/[^a-z0-9\-]/','', strtolower(trim($_GET['slug'] ?? '')));
if(!$slug){ http_response_code(404); echo "Not found"; exit; }

$pdo=db();
$st=$pdo->prepare("SELECT * FROM pages WHERE slug=? AND lang=? LIMIT 1");
$st->execute([$slug,$lang]);
$p=$st->fetch();
if(!$p){ http_response_code(404); echo "Not found"; exit; }

?><!doctype html>
<html lang="<?= $lang ?>"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($p['title']) ?> — CryptoUA</title>
<link rel="icon" href="/assets/favicon.png">
<style>
body{font-family:system-ui;background:#070a18;color:#e8ecff;margin:0}
.wrap{max-width:900px;margin:0 auto;padding:22px}
.card{border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.03);border-radius:18px;padding:18px}
a{color:#a9b2ff}
pre{white-space:pre-wrap}
</style>
</head><body>
<div class="wrap">
  <div class="card">
    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
      <h1 style="margin:0"><?= h($p['title']) ?></h1>
      <a href="/?lang=<?= h($lang) ?>">← Back</a>
    </div>
    <div style="height:12px"></div>
    <pre><?= h($p['body']) ?></pre>
  </div>
</div>
</body></html>
