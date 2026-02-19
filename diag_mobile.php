<?php
header('Content-Type: text/html; charset=utf-8');
?><!doctype html>
<html><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Mobile diag</title>
<style>
body{font-family:system-ui;margin:0;padding:16px;background:#0b1024;color:#e8ecff}
.card{max-width:860px;margin:0 auto;border:1px solid rgba(255,255,255,.14);border-radius:16px;padding:14px;background:rgba(255,255,255,.04)}
pre{white-space:pre-wrap;word-break:break-word;background:rgba(0,0,0,.25);padding:12px;border-radius:12px}
</style>
</head><body>
<div class="card">
<h2 style="margin:0 0 10px">Mobile diagnostics (CryptoUA)</h2>
<p style="margin:0 0 12px">Відкрий це на iPhone і скинь мені текст блоку нижче.</p>
<pre id="out">loading…</pre>
</div>
<script>
(function(){
  const dpr = window.devicePixelRatio || 1;
  const cssScreenW = (screen && screen.width) ? (screen.width / dpr) : window.innerWidth;
  const info = {
    userAgent: navigator.userAgent,
    innerWidth: window.innerWidth,
    innerHeight: window.innerHeight,
    devicePixelRatio: dpr,
    screenWidth: screen.width,
    screenHeight: screen.height,
    cssScreenW: cssScreenW,
    touch: ('ontouchstart' in window) || (navigator.maxTouchPoints>0),
    match_760: window.matchMedia('(max-width: 760px)').matches,
    match_1024: window.matchMedia('(max-width: 1024px)').matches,
    forceMobileClass: document.documentElement.classList.contains('forceMobile')
  };
  document.getElementById('out').textContent = JSON.stringify(info, null, 2);
})();
</script>
</body></html>
