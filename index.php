

        

<?php
require_once __DIR__ . '/helpers.php';
if (!is_installed()) { header('Location: /install.php'); exit; }

$lang = ($_GET['lang'] ?? 'uk') === 'en' ? 'en' : 'uk';


// Builder (admin only) — enables interactive home editing inside admin visual editor
$is_builder = isset($_GET['__builder']);
$is_builder_allowed = false;
if($is_builder){
  if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
  $is_builder_allowed = isset($_SESSION['user']) && (($_SESSION['user']['role'] ?? '') === 'admin');
}

$theme = setting_get('theme', []);
if(!is_array($theme)) $theme = [];
$themeDefaults = [
  'bg1' => 'rgba(124,92,255,.18)',
  'bg2' => 'rgba(35,213,171,.14)',
  'bg3' => 'rgba(124,92,255,.10)',
  'btn1'=> 'rgba(124,92,255,.95)',
  'btn2'=> 'rgba(35,213,171,.95)',
  'cta1'=> '#22d3a9',
  'cta2'=> '#2cff8a',
  'ctaGlow'=> 'rgba(34,211,169,.55)',
];
$theme = array_merge($themeDefaults, $theme);

$theme_mode = (string)setting_get('theme_mode', 'night');
if($theme_mode !== 'day') $theme_mode = 'night';

$burger_icon = (string)setting_get('burger_icon', '');
$burger_ver = (int)setting_get('burger_icon_ver', 0);
if($burger_ver<=0) $burger_ver = (int)setting_get('brand_assets_ver', 0);
// Home Builder settings (order + per-block styles)
$home_default_blocks = ['hero','advantages','limits','reviews','partners','faq','contacts'];
$home_order_desktop = setting_get('home_order_desktop', []);
if(is_string($home_order_desktop)) $home_order_desktop = json_decode($home_order_desktop, true);
if(!is_array($home_order_desktop)) $home_order_desktop = [];

$home_order_mobile = setting_get('home_order_mobile', []);
if(is_string($home_order_mobile)) $home_order_mobile = json_decode($home_order_mobile, true);
if(!is_array($home_order_mobile)) $home_order_mobile = [];

$home_styles_desktop = setting_get('home_styles_desktop', []);
if(is_string($home_styles_desktop)) $home_styles_desktop = json_decode($home_styles_desktop, true);
if(!is_array($home_styles_desktop)) $home_styles_desktop = [];

$home_styles_mobile = setting_get('home_styles_mobile', []);
if(is_string($home_styles_mobile)) $home_styles_mobile = json_decode($home_styles_mobile, true);
if(!is_array($home_styles_mobile)) $home_styles_mobile = [];

$home_el_styles_desktop = setting_get('home_el_styles_desktop', []);
if(is_string($home_el_styles_desktop)) $home_el_styles_desktop = json_decode($home_el_styles_desktop, true);
if(!is_array($home_el_styles_desktop)) $home_el_styles_desktop = [];

$home_el_styles_mobile = setting_get('home_el_styles_mobile', []);
if(is_string($home_el_styles_mobile)) $home_el_styles_mobile = json_decode($home_el_styles_mobile, true);
if(!is_array($home_el_styles_mobile)) $home_el_styles_mobile = [];

$home_el_texts_desktop = setting_get('home_el_texts_desktop', []);
if(is_string($home_el_texts_desktop)) $home_el_texts_desktop = json_decode($home_el_texts_desktop, true);
if(!is_array($home_el_texts_desktop)) $home_el_texts_desktop = [];

$home_el_texts_mobile = setting_get('home_el_texts_mobile', []);

$home_region_desktop = setting_get('home_region_desktop', []);
if(is_string($home_region_desktop)) $home_region_desktop = json_decode($home_region_desktop, true);
if(!is_array($home_region_desktop)) $home_region_desktop = [];

$home_region_mobile = setting_get('home_region_mobile', []);

// locked blocks stay in BODY
$locked_region_enforce = ['hero'=>true,'rates'=>true,'miniCard'=>true];
foreach($locked_region_enforce as $k=>$v){
  if(isset($home_region_desktop[$k])) $home_region_desktop[$k] = 'body';
  if(isset($home_region_mobile[$k]))  $home_region_mobile[$k]  = 'body';
}

$menu_items = [
  ['id'=>'rates','href'=>'#rates','uk'=>'Курс','en'=>'Rate'],
  ['id'=>'how','href'=>'#how','uk'=>'Як це працює','en'=>'How it works'],
  ['id'=>'advantages','href'=>'#advantages','uk'=>'Переваги','en'=>'Advantages'],
  ['id'=>'limits','href'=>'#limits','uk'=>'Ліміти','en'=>'Limits'],
  ['id'=>'reviews','href'=>'#reviews','uk'=>'Відгуки','en'=>'Reviews'],
  ['id'=>'partners','href'=>'#partners','uk'=>'Партнери','en'=>'Partners'],
  ['id'=>'faq','href'=>'#faq','uk'=>'FAQ','en'=>'FAQ'],
  ['id'=>'contacts','href'=>'#contacts','uk'=>'Контакти','en'=>'Contacts'],
];

$custom_blocks = setting_get('custom_blocks', []);
if(is_string($custom_blocks)) $custom_blocks = json_decode($custom_blocks, true);
if(!is_array($custom_blocks)) $custom_blocks = [];
$menu_order = setting_get('menu_order', []);
if(is_string($menu_order)) $menu_order = json_decode($menu_order, true);
if(!is_array($menu_order)) $menu_order = [];

if(count($menu_order)){
  $byId = [];
  foreach($menu_items as $it){ $byId[$it['id']] = $it; }
  $out = [];
  foreach($menu_order as $id){
    $id=(string)$id;
    if(isset($byId[$id])){ $out[]=$byId[$id]; unset($byId[$id]); }
  }
  foreach($menu_items as $it){
    if(isset($byId[$it['id']])) $out[]=$it;
  }
  $menu_items = $out;
}
if(is_string($home_region_mobile)) $home_region_mobile = json_decode($home_region_mobile, true);
if(!is_array($home_region_mobile)) $home_region_mobile = [];
if(is_string($home_el_texts_mobile)) $home_el_texts_mobile = json_decode($home_el_texts_mobile, true);
if(!is_array($home_el_texts_mobile)) $home_el_texts_mobile = [];


function home_norm_order(array $order, array $defaults): array {
  $out = [];
  foreach($order as $b){
    $b = (string)$b;
    if($b!=='' && !in_array($b, $out, true)) $out[] = $b;
  }
  foreach($defaults as $b){
    if(!in_array($b, $out, true)) $out[] = $b;
  }
  return $out;
}
$home_order_desktop = home_norm_order($home_order_desktop, $home_default_blocks);
$home_order_mobile  = home_norm_order($home_order_mobile,  $home_default_blocks);



$calcUi = setting_get('calc_ui_' . $lang, [
  'label_currency' => $lang==='en' ? 'Currency' : 'Валюта',
  'label_amount'   => $lang==='en' ? 'Amount' : 'Кількість',
  'placeholder_phone' => $lang==='en' ? 'Phone' : 'Телефон',
  'btn_submit'     => $lang==='en' ? 'EXCHANGE' : 'ПОМІНЯТИ ВАЛЮТУ',
  'aria_swap'      => $lang==='en' ? 'Swap' : 'Поміняти місцями',
  'fee_label'      => $lang==='en' ? 'Service fee' : 'Комісія сервісу',
  'show_fee'       => true,
  'show_fee_amount'=> false,
]);
if (!is_array($calcUi)) $calcUi = [];

$calcIcons = setting_get('calc_icons', ['UAH'=>'','USDT'=>'']);
if (!is_array($calcIcons)) $calcIcons = ['UAH'=>'','USDT'=>''];
$calcIconsVer = (int)setting_get('calc_icons_ver', 0);
$brandLogo = (string)setting_get('brand_logo', '/assets/logo.svg');
$brandBurger = (string)setting_get('brand_burger_icon', '');
$brandVer = (int)setting_get('brand_assets_ver', 0);
function add_ver($url, $ver){
  $url = (string)$url;
  if($ver<=0 || $url==='') return $url;
  $sep = (strpos($url,'?')===false) ? '?' : '&';
  return $url . $sep . 'v=' . (int)$ver;
}
$brandLogoV = add_ver($brandLogo, $brandVer);
$brandBurgerV = add_ver($brandBurger, $brandVer);

// cachebust calc icons too
if($calcIconsVer>0 && is_array($calcIcons)){
  foreach($calcIcons as $k=>$v){
    if(!$v) continue;
    $calcIcons[$k] = add_ver($v, $calcIconsVer);
  }
}
if (!is_array($calcIcons)) $calcIcons = ['UAH'=>'','USDT'=>''];

$seo = setting_get('seo_' . $lang, [
  'title' => ($lang==='en') ? 'CryptoUA — P2P Crypto ⇄ UAH' : 'CryptoUA — P2P Crypto ⇄ UAH',
  'description' => ($lang==='en')
    ? 'Exchange crypto to UAH and back. Rate fixation, operators 24/7.'
    : 'Обмін криптовалют на гривні та у зворотному напрямку. Фіксація курсу, оператори 24/7.',
  'keywords' => 'crypto,uah,exchange,USDT,BTC,ETH,p2p',
  'og_image' => '/assets/og-cover.png'
]);

$baseUrl = rtrim(cfg('BASE_URL', ''), '/');
$og = $seo['og_image'] ?? '/assets/og-cover.png';
if ($og && $og[0] !== '/') $og = '/' . $og;

$defaults_uk = [
  'hero'=>[
    'pill'=>'24/7  •  P2P через операторів  •  Фіксація курсу',
    'title'=>"Обмін криптовалют на гривні\nта у зворотному напрямку",
    'lead'=>"USDT / BTC / ETH → UAH (картка/IBAN) та UAH → Crypto.\nПідтримка операторів. Без прихованих комісій.",
    'cta1'=>'Зробити обмін',
    'cta2'=>'Як це працює'
  ],
  'limits_cards'=>[
    ['title'=>'Мін/макс суми','text'=>'Ліміти залежать від активу та способу оплати. Курс фіксується.'],
    ['title'=>'KYC / AML','text'=>'Для окремих операцій може знадобитись перевірка згідно AML-політик.'],
    ['title'=>'Час фіксації','text'=>'Курс фіксується на N хвилин. Далі — перерахунок за актуальним курсом.'],
  ],
  'reviews'=>[
    ['name'=>'Андрій','text'=>'Тягнеться крипт операція — кинули оператору, дали курс і все.','avatar'=>'/assets/avatars/u1.png'],
    ['name'=>'Олена','text'=>'УФ, реально легше: зафіксували курс і прийняли оплату.','avatar'=>'/assets/avatars/u2.png'],
    ['name'=>'Ігор','text'=>'Це прям топчик: курс не плаває, якщо фіксація — ми встигли.','avatar'=>'/assets/avatars/u3.png'],
  ],
  'partners'=>[
    ['name'=>'Bank','logo'=>'/assets/partners/bank.png'],
    ['name'=>'Provider','logo'=>'/assets/partners/provider.png'],
    ['name'=>'Exchange','logo'=>'/assets/partners/exchange.png'],
    ['name'=>'KYC','logo'=>'/assets/partners/kyc.png'],
  ],
  'faq'=>[
    ['q'=>'Коли фіксується курс?','a'=>'Після створення заявки курс фіксується на N хвилин (налаштовується в адмінці).'],
    ['q'=>'Як працює P2P?','a'=>'Ви створюєте заявку, оператор підтверджує деталі та проводить обмін напряму (P2P).'],
    ['q'=>'Чи є комісія мережі?','a'=>'Мережеві комісії залежать від блокчейну. Сервісна комісія вказана в калькуляторі.'],
  ],
  'contacts'=>['telegram'=>'@your_telegram','phone'=>'+380 XX XXX XX XX','email'=>'support@yourdomain.ua'],
  'footer_links'=>['Умови','/p.php?slug=terms&lang=uk','Конфіденційність','/p.php?slug=privacy&lang=uk','KYC / AML','/p.php?slug=kyc-aml&lang=uk']
];

$defaults_en = [
  'hero'=>[
    'pill'=>'24/7  •  P2P with operators  •  Rate fixation',
    'title'=>"Exchange crypto to UAH\nand back",
    'lead'=>"USDT / BTC / ETH → UAH (card/IBAN) and UAH → Crypto.\nOperators support. No hidden fees.",
    'cta1'=>'Start exchange',
    'cta2'=>'How it works'
  ],
  'limits_cards'=>[
    ['title'=>'Min / Max','text'=>'Limits depend on asset and payment method. Rate is fixed for a short window.'],
    ['title'=>'KYC / AML','text'=>'For some operations we may request verification in line with AML policies.'],
    ['title'=>'Fixation time','text'=>'Rate is fixed for N minutes. After that — recalculated by live rate.'],
  ],
  'reviews'=>[
    ['name'=>'Andrii','text'=>'Fast and clear: operator confirmed and fixed the rate.','avatar'=>'/assets/avatars/u1.png'],
    ['name'=>'Olena','text'=>'Convenient. Rate fixation saved me from volatility.','avatar'=>'/assets/avatars/u2.png'],
    ['name'=>'Ihor','text'=>'Support is always online. Smooth experience.','avatar'=>'/assets/avatars/u3.png'],
  ],
  'partners'=>[
    ['name'=>'Bank','logo'=>'/assets/partners/bank.png'],
    ['name'=>'Provider','logo'=>'/assets/partners/provider.png'],
    ['name'=>'Exchange','logo'=>'/assets/partners/exchange.png'],
    ['name'=>'KYC','logo'=>'/assets/partners/kyc.png'],
  ],
  'faq'=>[
    ['q'=>'When is the rate fixed?','a'=>'After you create a request, the rate is fixed for N minutes (set in admin).'],
    ['q'=>'How does P2P work?','a'=>'You create a request, an operator confirms details and performs the P2P exchange.'],
    ['q'=>'Network fee?','a'=>'Blockchain network fees depend on the chain. Service fee is shown in the calculator.'],
  ],
  'contacts'=>['telegram'=>'@your_telegram','phone'=>'+380 XX XXX XX XX','email'=>'support@yourdomain.ua'],
  'footer_links'=>['Terms','/p.php?slug=terms&lang=en','Privacy','/p.php?slug=privacy&lang=en','KYC / AML','/p.php?slug=kyc-aml&lang=en']
];

$def = ($lang==='en') ? $defaults_en : $defaults_uk;

$blocks = setting_get('blocks_' . $lang, []);
// Merge carefully with defaults
$hero = array_merge($def['hero'], $blocks['hero'] ?? []);
$contacts = array_merge($def['contacts'], $blocks['contacts'] ?? []);
$faq = $blocks['faq'] ?? $def['faq'];

$advantages_cards = $blocks['advantages'] ?? ($def['advantages'] ?? []);
if(!is_array($advantages_cards)) $advantages_cards = [];

$partners = $blocks['partners'] ?? $def['partners'];
$reviews = $blocks['reviews'] ?? $def['reviews'];

$theme = setting_get('theme', [
  'accent'=>'#7c5cff',
  'accent2'=>'#23d5ab',
  'bg'=>'#0b1020',
  'panel'=>'#0f1730',
  'radius'=>18
]);
$homeDesign = setting_get('home_design', [
  'starsTile'=>'/assets/stars-tile.png',
  'bgFxOpacity'=>1
]);
$homeSections = setting_get('home_sections', [
  'limits'=>1,
  'reviews'=>1,
  'partners'=>1,
  'faq'=>1
]);

$accent  = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string)($theme['accent'] ?? '')) ? (string)$theme['accent'] : '#7c5cff';
$accent2 = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string)($theme['accent2'] ?? '')) ? (string)$theme['accent2'] : '#23d5ab';
$bg      = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string)($theme['bg'] ?? '')) ? (string)$theme['bg'] : '#0b1020';
$panel   = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string)($theme['panel'] ?? '')) ? (string)$theme['panel'] : '#0f1730';
$radius  = (int)($theme['radius'] ?? 18);
if ($radius < 8) $radius = 8;
if ($radius > 32) $radius = 32;

$stars = trim((string)($homeDesign['starsTile'] ?? '/assets/stars-tile.png'));
$stars = preg_replace('/[^a-zA-Z0-9_\/\.\-\:]/', '', $stars);
if ($stars === '') $stars = '/assets/stars-tile.png';

$bgfxOpacity = (float)($homeDesign['bgFxOpacity'] ?? 1);
if ($bgfxOpacity <= 0) $bgfxOpacity = 1;
if ($bgfxOpacity > 1.5) $bgfxOpacity = 1.5;

$cssVars = "--accent:$accent;--accent2:$accent2;--bg:$bg;--panel:$panel;--radius:{$radius}px;--stars-tile:url($stars);--bgfx-opacity:$bgfxOpacity;";


// limits: stored as array of ['title','text'] in $blocks['limits'] (legacy). Use that if present, otherwise defaults.
$limits_cards = $def['limits_cards'];
if (!empty($blocks['limits']) && is_array($blocks['limits'])) {
  $tmp = [];
  foreach ($blocks['limits'] as $it) {
    $t = trim($it['title'] ?? '');
    $x = trim($it['text'] ?? '');
    if ($t !== '' || $x !== '') $tmp[] = ['title'=>$t ?: '—', 'text'=>$x ?: ''];
  }
  if (count($tmp) >= 3) $limits_cards = array_slice($tmp, 0, 3);
}
?>
<!doctype html>
<html lang="<?= $lang === 'en' ? 'en' : 'uk' ?>" class="<?php echo ($theme_mode==='day')?'theme-day':'theme-night'; ?>">
<head>
  <meta charset="utf-8" />
<script>
(function(){
  try{
    var m = localStorage.getItem('themeMode');
    if(m==='day'){ document.documentElement.classList.add('theme-day'); document.documentElement.classList.remove('theme-night'); }
    if(m==='night'){ document.documentElement.classList.add('theme-night'); document.documentElement.classList.remove('theme-day'); }
  }catch(e){}
})();
</script>

  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
<meta http-equiv="Pragma" content="no-cache" />
<meta http-equiv="Expires" content="0" />
<script>document.documentElement.classList.add('js');</script>
<script>window.CALC_ICONS = <?= json_encode($calcIcons, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;</script>
<title><?= h($seo['title'] ?? 'CryptoUA') ?></title>
  <meta name="description" content="<?= h($seo['description'] ?? '') ?>" />
  <meta name="keywords" content="<?= h($seo['keywords'] ?? '') ?>" />
  <meta name="robots" content="index,follow" />
  <link rel="icon" href="/assets/favicon.png" />

  <meta property="og:type" content="website" />
  <meta property="og:title" content="<?= h($seo['title'] ?? '') ?>" />
  <meta property="og:description" content="<?= h($seo['description'] ?? '') ?>" />
  <meta property="og:image" content="<?= h($og) ?>" />
  <?php if ($baseUrl): ?>
    <meta property="og:url" content="<?= h($baseUrl . '/?lang=' . $lang) ?>" />
  <?php endif; ?>

  <script type="application/ld+json">
  <?= json_encode([
    "@context"=>"https://schema.org",
    "@type"=>"Organization",
    "name"=>"CryptoUA",
    "url"=>$baseUrl ?: "",
    "logo"=>$baseUrl ? ($baseUrl . "/assets/logo.svg") : "/assets/logo.svg",
    "sameAs"=>[]
  ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT) ?>
  </script>

    <link rel="stylesheet" href="/site.css?v=<?= @filemtime(__DIR__ . '/site.css') ?: time() ?>" />
  <link rel="stylesheet" href="/site.mobile.css?v=<?= @filemtime(__DIR__ . '/site.mobile.css') ?: time() ?>" media="(max-width: 820px)" />

  <style id="themeVars">
    :root{
      --theme-bg1: <?= h($theme['bg1']) ?>;
      --theme-bg2: <?= h($theme['bg2']) ?>;
      --theme-bg3: <?= h($theme['bg3']) ?>;
      --theme-btn1: <?= h($theme['btn1']) ?>;
      --theme-btn2: <?= h($theme['btn2']) ?>;
      --theme-cta1: <?= h($theme['cta1']) ?>;
      --theme-cta2: <?= h($theme['cta2']) ?>;
      --theme-ctaGlow: <?= h($theme['ctaGlow']) ?>;
    }
  </style>


  <style id="homeBuilderStyles">
    .homeStack{display:flex;flex-direction:column;gap:0;}
<?php
  // Orders
  $posD = [];
  foreach($home_order_desktop as $i=>$b){ $posD[$b] = (int)$i; }
  foreach($home_default_blocks as $b){
    $o = $posD[$b] ?? 0;
    echo '[data-block="'.h($b).'"]{order:'.$o.';}'."\n";
  }
  echo "@media (max-width:820px){\n";
  $posM = [];
  foreach($home_order_mobile as $i=>$b){ $posM[$b] = (int)$i; }
  foreach($home_default_blocks as $b){
    $o = $posM[$b] ?? 0;
    echo '  [data-block="'.h($b).'"]{order:'.$o.';}'."\n";
  }
  echo "}\n";

  // Styles desktop
  foreach($home_styles_desktop as $b=>$s){
    if(!is_array($s)) continue;
    $sel = '[data-block="'.h((string)$b).'"]';
    $decl = '';
    if(isset($s['bg']) && $s['bg']!=='') $decl .= 'background:'.h((string)$s['bg']).';';
    if(isset($s['text']) && $s['text']!=='') $decl .= 'color:'.h((string)$s['text']).';';
    if(isset($s['radius']) && $s['radius']!=='') $decl .= 'border-radius:'.((int)$s['radius']).'px;';
    if(isset($s['pad']) && $s['pad']!=='') $decl .= 'padding:'.((int)$s['pad']).'px;';
    if(isset($s['shadow']) && $s['shadow']!=='') $decl .= 'box-shadow:'.h((string)$s['shadow']).';';
    if($decl!=='') echo $sel.'{'.$decl.'}'."\n";
  }
  // Styles mobile
  echo "@media (max-width:820px){\n";
  foreach($home_styles_mobile as $b=>$s){
    if(!is_array($s)) continue;
    $sel = '  [data-block="'.h((string)$b).'"]';
    $decl = '';
    if(isset($s['bg']) && $s['bg']!=='') $decl .= 'background:'.h((string)$s['bg']).';';
    if(isset($s['text']) && $s['text']!=='') $decl .= 'color:'.h((string)$s['text']).';';
    if(isset($s['radius']) && $s['radius']!=='') $decl .= 'border-radius:'.((int)$s['radius']).'px;';
    if(isset($s['pad']) && $s['pad']!=='') $decl .= 'padding:'.((int)$s['pad']).'px;';
    if(isset($s['shadow']) && $s['shadow']!=='') $decl .= 'box-shadow:'.h((string)$s['shadow']).';';
    if($decl!=='') echo $sel.'{'.$decl.'}'."\n";
  }
  echo "}\n";
?>
  </style>



  <script id="homeElementStyles">
  (function(){
    try{
      var elD = <?= json_encode($home_el_styles_desktop, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
      var elM = <?= json_encode($home_el_styles_mobile, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
      var txD = <?= json_encode($home_el_texts_desktop, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
      var txM = <?= json_encode($home_el_texts_mobile, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;

      function buildCSS(map){
        var css = '';
        if(!map) return css;
        Object.keys(map).forEach(function(id){
          var s = map[id] || {};
          var decl = '';
          if(s.bg) decl += 'background:'+s.bg+';';
          if(s.color) decl += 'color:'+s.color+';';
          if(s.radius!==undefined && s.radius!=='') decl += 'border-radius:'+(parseInt(s.radius,10)||0)+'px;';
          if(s.pad!==undefined && s.pad!=='') decl += 'padding:'+(parseInt(s.pad,10)||0)+'px;';
          if(s.shadow) decl += 'box-shadow:'+s.shadow+';';
          if(s.fs!==undefined && s.fs!=='') decl += 'font-size:'+(parseInt(s.fs,10)||0)+'px;';
          if(s.w!==undefined && s.w!=='') decl += 'font-weight:'+String(s.w)+';';
          if(decl) css += '[data-el="'+id+'"]{'+decl+'}\n';
        });
        return css;
      }
      var style = document.createElement('style');
      style.id = 'homeElementStylesTag';
      style.textContent = buildCSS(elD) + '\n@media (max-width:820px){\n' + buildCSS(elM) + '}\n';
      document.head.appendChild(style);

      function applyTexts(map){
        if(!map) return;
        Object.keys(map).forEach(function(id){
          var v = map[id];
          if(typeof v!=='string') return;
          var el = document.querySelector('[data-el="'+id+'"]');
          if(el) el.textContent = v;
        });
      }
      applyTexts(txD);
      if(window.matchMedia && window.matchMedia('(max-width:820px)').matches){
        applyTexts(txM);
      }
    }catch(e){}
  })();
  </script>


</head>
<body id="top" style="<?= h($cssVars) ?>" data-builder="<?= $is_builder_allowed?'1':'0' ?>">
  <div class="bgLayer" aria-hidden="true"></div>
  
<div id="mobileMenu" class="mobileMenu" aria-hidden="true">
  <div class="mobileMenu__panel" role="dialog" aria-modal="true" aria-label="Menu">
    <div class="mobileMenu__top">
      <div class="mobileMenu__topLeft" style="display:flex;gap:10px;align-items:center">
        <a class="langIcon <?= $lang==='uk'?'active':'' ?>" href="/?lang=uk" title="Українська" aria-label="UA">🇺🇦</a>
        <a class="langIcon <?= $lang==='en'?'active':'' ?>" href="/?lang=en" title="English" aria-label="EN">🇬🇧</a>
        <button class="themeToggleBtn themeToggleBtn--menu" id="themeToggleBtnMenu" type="button" aria-label="Day/Night" aria-pressed="false" title="Day/Night">
          <span class="ttIcon">☀</span>
          <span class="ttTrack"><span class="ttThumb"></span></span>
          <span class="ttIcon">☾</span>
        </button>
      </div>
      <button class="mobileMenu__close" type="button" aria-label="Close">✕</button>
    </div>
    <div class="mobileMenu__links">
      <?php foreach($menu_items as $it): ?>
        <a class="mobileMenu__link" href="<?= h($it['href']) ?>"><?= $lang==='en' ? h($it['en']) : h($it['uk']) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<header class="header">
  <div class="container header__inner">
    <a class="logo" href="/?lang=<?= h($lang) ?>" aria-label="CryptoUA">
      <img class="logo__img" src="<?= h($brandLogoV) ?>" alt="CryptoUA" style="height:30px" />
    </a>
<nav class="nav" id="mobileNav" aria-label="Навігація">
      <?php foreach($menu_items as $it): ?>
        <a href="<?= h($it['href']) ?>"><?= $lang==='en' ? h($it['en']) : h($it['uk']) ?></a>
      <?php endforeach; ?>
    </nav>

    <div class="lang">
      <a class="lang__btn <?= $lang==='uk'?'active':'' ?>" href="/?lang=uk">UA</a>
      <a class="lang__btn <?= $lang==='en'?'active':'' ?>" href="/?lang=en">EN</a>
    </div>

    <button class="themeToggleBtn" id="themeToggleBtn" type="button" aria-label="Day/Night" aria-pressed="false" title="Day/Night">
      <span class="ttIcon">☀</span>
      <span class="ttTrack"><span class="ttThumb"></span></span>
      <span class="ttIcon">☾</span>
    </button>

    <button class="burgerBtn" data-has-icon="<?= $burger_icon?'1':'0' ?>" style="<?php if($burger_icon){ $u=$burger_icon; $sep=(strpos($u,'?')===false)?'?':'&'; echo 'background-image:url('.h($u.$sep.'v='.$burger_ver).');'; } ?>" type="button" aria-label="Menu" aria-expanded="false" aria-controls="mobileMenu"><?php if(!empty($brandBurgerV)): ?>
      <img class="burgerBtn__icon" src="<?= h($brandBurgerV) ?>" alt="menu" />
    <?php else: ?>
      <span></span><span></span><span></span>
    <?php endif; ?></button>
</div>
</header>
  <div id="regionHeader" class="region region--header" aria-label="Header region"></div>


<main class="container homeStack">
  <section class="hero" aria-label="Hero" id="hero" data-block="hero" data-block-label="Hero">
    <div class="heroGrid" style="display:grid;grid-template-columns:1fr 420px;gap:22px;align-items:start">
      <!-- Left -->
      <div>
        <div class="hero__pill"><?= h($hero['pill'] ?? '') ?></div>

        <h1 style="margin:18px 0 10px; font-size:40px; line-height:1.12; letter-spacing:-.3px; white-space:pre-line;">
          <?= nl2br(h($hero['title'] ?? '')) ?>
        </h1>

        <div style="color:rgba(232,236,255,.78); font-weight:600; white-space:pre-line; max-width:560px">
          <?= nl2br(h($hero['lead'] ?? '')) ?>
        </div>

        <div class="hero__actions">
          <a class="btnPrimary" href="#rates"><?= h($hero['cta1'] ?? '') ?></a>
          <a class="btnGhost" href="#how"><?= h($hero['cta2'] ?? '') ?></a>
        </div>

        <div id="advantages" style="margin-top:22px; padding-top:14px; border-top:1px solid rgba(255,255,255,.10); display:grid; grid-template-columns:repeat(3,1fr); gap:14px">
          <?php
            $adv = [];
            foreach($advantages_cards as $a){
              if(!is_array($a)) continue;
              $t = trim((string)($a['title'] ?? ''));
              $x = trim((string)($a['text'] ?? ''));
              if($t!=='' || $x!=='') $adv[] = $a;
            }
            if(!count($adv)){
              $adv = [
                ['_id'=>'a_speed','title'=>'5–15 хв','text'=>$lang==='en'?'Typical speed':'типова швидкість','icon'=>'round'],
                ['_id'=>'a_dir','title'=>'UAH ⇄ Crypto','text'=>$lang==='en'?'2 directions':'2 напрямки','icon'=>'square'],
                ['_id'=>'a_support','title'=>'Support','text'=>$lang==='en'?'Operators 24/7':'оператори 24/7','icon'=>'green'],
              ];
            }
            $adv = array_slice($adv, 0, 3);
          ?>
          <?php foreach($adv as $a): $aid = (string)($a['_id'] ?? ''); ?>
            <div class="advCard" data-adv-id="<?= h($aid) ?>" draggable="<?= isset($_GET['builder'])?'true':'false' ?>">
              <?= isset($_GET['builder'])?'<span class="builderItemHandle" title="Drag">⋮⋮</span>':'' ?>
              <div style="display:flex;gap:10px;align-items:center">
                <?php
                  $icon = (string)($a['icon'] ?? 'round');
                  $style = 'border-radius:999px;width:26px;height:26px';
                  if($icon==='square') $style = 'border-radius:8px;width:26px;height:26px';
                  if($icon==='green') $style = 'border-radius:999px;width:26px;height:26px;background:rgba(35,213,171,.22);border:1px solid rgba(35,213,171,.45)';
                ?>
                <span class="assetIcon" style="<?= h($style) ?>"></span>
                <div>
                  <div style="font-weight:900"><?= h($a['title'] ?? '') ?></div>
                  <div style="color:rgba(232,236,255,.7);font-size:12px;font-weight:700"><?= h($a['text'] ?? '') ?></div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Limits -->
        <h2 class="sectionTitle" id="limits" data-el="advantages_title"><?= $lang==='en' ? 'Limits & rules' : 'Ліміти та правила' ?></h2>
        <div class="cards3">
          <?php foreach ($limits_cards as $c): ?>
            <div class="card limitCard" data-limit-id="<?= h($c['_id'] ?? '') ?>" draggable="<?= isset($_GET['builder'])?'true':'false' ?>"><?= isset($_GET['builder'])?'<span class="builderItemHandle" title="Drag">⋮⋮</span>':'' ?>
              <h4><?= h($c['title'] ?? '') ?></h4>
              <p><?= h($c['text'] ?? '') ?></p>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Reviews -->
        <h2 class="sectionTitle" id="reviews" data-el="limits_title"><?= $lang==='en' ? 'Reviews' : 'Відгуки' ?></h2>
        <div class="reviewsGrid">
          <?php foreach (array_slice($reviews,0,3) as $r): 
            $av = trim($r['avatar'] ?? '');
            if ($av==='') $av = '/assets/avatars/u1.png';
          ?>
            <div class="card reviewCard" data-review-id="<?= h($r['_id'] ?? '') ?>" draggable="<?= isset($_GET['builder'])?'true':'false' ?>"><?= isset($_GET['builder'])?'<span class="builderItemHandle" title="Drag">⋮⋮</span>':'' ?>
              <img class="avatar" src="<?= h($av) ?>" alt="<?= h($r['name'] ?? 'User') ?>">
              <div>
                <div style="font-weight:900"><?= h($r['name'] ?? '') ?></div>
                <div style="color:rgba(232,236,255,.75);font-size:13px;margin-top:6px"><?= h($r['text'] ?? '') ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Partners -->
        <h2 class="sectionTitle" id="partners" data-el="reviews_title"><?= $lang==='en' ? 'Partners' : 'Партнери' ?></h2>
        <div class="partnerGrid">
          <?php foreach (array_slice($partners,0,4) as $p):
            $logo = trim($p['logo'] ?? '');
            if ($logo==='') {
              // fallback map by index
              $i = array_search($p, $partners, true);
            }
          ?>
            <div class="partnerItem" data-partner-id="<?= h($p['_id'] ?? '') ?>" draggable="<?= isset($_GET['builder'])?'true':'false' ?>"><?= isset($_GET['builder'])?'<span class="builderItemHandle" title="Drag">⋮⋮</span>':'' ?>
              <?php if ($logo): ?>
                <img class="partnerIcon" src="<?= h($logo) ?>" alt="<?= h($p['name'] ?? 'Partner') ?>">
              <?php else: ?>
                <span class="assetIcon" style="width:22px;height:22px;border-radius:7px"></span>
              <?php endif; ?>
              <div style="font-weight:900"><?= h($p['name'] ?? '') ?></div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- FAQ -->
        <h2 class="sectionTitle" id="faq" data-el="partners_title">FAQ</h2>
        <div class="faqList">
          <?php foreach (array_slice($faq,0,6) as $i=>$f): $fid = (string)($f['_id'] ?? $i); ?>
            <div class="faqRow card" style="padding:0" data-faq-id="<?= h($fid) ?>" draggable="<?= isset($_GET['builder'])?'true':'false' ?>">
              <?= isset($_GET['builder'])?'<span class="builderItemHandle" title="Drag">⋮⋮</span>':'' ?>
              <div class="faqItem" data-faq="<?= h($fid) ?>">
                <div style="display:flex;gap:10px;align-items:center">
                  <span class="assetIcon" style="width:18px;height:18px;border-radius:7px;opacity:.75"></span>
                  <div style="font-weight:800"><?= h($f['q'] ?? '') ?></div>
                </div>
                <div style="opacity:.7">›</div>
              </div>
              <div class="faqAns" style="display:none;margin-top:-6px;margin-bottom:10px;padding:0 14px 14px">
                <p style="margin:0"><?= h($f['a'] ?? '') ?></p>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Contacts -->
        <h2 class="sectionTitle" id="contacts" data-el="faq_title"><?= $lang==='en' ? 'Contacts' : 'Контакти' ?></h2>
        <div class="contactsGrid">
          <div class="card contactCard">
            <span class="assetIcon" style="width:34px;height:34px;border-radius:12px"></span>
            <div>
              <div style="font-weight:900">CryptoUA</div>
              <div style="color:rgba(232,236,255,.75);font-weight:700"><?= h($contacts['telegram'] ?? '') ?></div>
            </div>
          </div>
          <div class="card contactCard">
            <span class="assetIcon" style="width:34px;height:34px;border-radius:12px;background:rgba(35,213,171,.22);border:1px solid rgba(35,213,171,.45)"></span>
            <div>
              <div style="font-weight:900">WhatsApp</div>
              <div style="color:rgba(232,236,255,.75);font-weight:700"><?= h($contacts['phone'] ?? '') ?></div>
            </div>
          </div>
          <div class="card contactCard">
            <span class="assetIcon" style="width:34px;height:34px;border-radius:12px;background:rgba(255,255,255,.08)"></span>
            <div>
              <div style="font-weight:900">Email</div>
              <div style="color:rgba(232,236,255,.75);font-weight:700"><?= h($contacts['email'] ?? '') ?></div>
            </div>
          </div>
        </div>

        <div class="footerLine">
          <div style="display:flex;gap:14px;justify-content:center;align-items:center;opacity:.75;margin-bottom:8px">
            <span>© <?= date('Y') ?> CryptoUA</span>
          </div>
          <div style="display:flex;gap:22px;justify-content:center;flex-wrap:wrap;font-weight:700">
            <a href="/p.php?slug=terms&amp;lang=<?= h($lang) ?>"><?= $lang==='en'?'Terms':'Умови' ?></a>
            <a href="/p.php?slug=privacy&amp;lang=<?= h($lang) ?>"><?= $lang==='en'?'Privacy':'Конфіденційність' ?></a>
            <a href="/p.php?slug=kyc-aml&amp;lang=<?= h($lang) ?>"><?= $lang==='en'?'KYC / AML':'KYC / AML' ?></a>
          </div>
        </div>
      </div>

      <!-- Right column -->
      <div id="heroRight">
        <aside class="card calc calc2" id="rates" style="padding:16px" data-block="rates" data-block-label="Calculator">
          <form id="ppForm" class="calc2Form">
            <div class="calc2Row">
              <div class="calc2Left">
                <div class="calc2Label"><?= h($calcUi['label_currency'] ?? ($lang==='en'?'Currency':'Валюта')) ?></div>
                <div class="assetSelectWrap">
                  <span class="calc2Flag" id="fromFlag">🇺🇦</span>
                  <select class="assetSelect" id="fromAssetSelect" aria-label="From currency"></select>
                  <div class="assetDD" data-select="fromAssetSelect"></div>
                </div>
              </div>
              <div class="calc2Right">
                <div class="calc2Label"><?= h($calcUi['label_amount'] ?? ($lang==='en'?'Amount':'Кількість')) ?></div>
                <input id="fromAmount" class="calc2Amount" inputmode="decimal" autocomplete="off" placeholder="0" value="0" />
              </div>
            </div>

            <div class="calc2SwapWrap">
              <button type="button" class="calc2Swap" id="swapBtn" aria-label="<?= h($calcUi['aria_swap'] ?? ($lang==='en'?'Swap':'Поміняти місцями')) ?>">
                <span class="calc2SwapIcon">⇅</span>
              </button>
            </div>

            <div class="calc2Row">
              <div class="calc2Left">
                <div class="calc2Label"><?= h($calcUi['label_currency'] ?? ($lang==='en'?'Currency':'Валюта')) ?></div>
                <div class="assetSelectWrap">
                  <span class="calc2Flag" id="toFlag">💱</span>
                  <select class="assetSelect" id="toAssetSelect" aria-label="To currency"></select>
                  <div class="assetDD" data-select="toAssetSelect"></div>
                </div>
              </div>
              <div class="calc2Right">
                <div class="calc2Label"><?= h($calcUi['label_amount'] ?? ($lang==='en'?'Amount':'Кількість')) ?></div>
                <input id="toAmount" class="calc2Amount" inputmode="decimal" autocomplete="off" placeholder="0" value="0" readonly />
              </div>
            </div>

            <div class="calc2Phone">
              <input id="contact" name="contact" required class="calc2PhoneInput" inputmode="tel" autocomplete="tel" placeholder="<?= h($calcUi['placeholder_phone'] ?? ($lang==='en'?'Phone':'Телефон')) ?>" />
            </div>

            <div class="calc2Bottom">
              <div class="calc2Rate" id="rateText">1 USDT = — UAH</div>
              <div class="calc2FeeWrap" id="feeWrap" style="display:none">
                <div class="calc2Fee" id="feeText"></div>
              </div>
              <button class="calc2Submit" type="submit"><?= h($calcUi['btn_submit'] ?? ($lang==='en'?'EXCHANGE':'ПОМІНЯТИ ВАЛЮТУ')) ?></button>
            </div>

            <div id="receipt" class="calc2Receipt"></div>
</div>

      </form>
        </aside>

        <aside class="card miniCard" style="margin-top:16px;padding:16px" data-block="miniCard" data-block-label="Mini data">
          <div style="font-weight:900;font-size:18px"><?= $lang==='en' ? 'Minimal data for request' : 'Мінімальні дані для заявки' ?></div>
          <ul>
            <li><span class="check">✓</span><?= $lang==='en'?'Exchange direction':'Напрямок обміну' ?></li>
            <li><span class="check">✓</span><?= $lang==='en'?'Amount and asset':'Сума та актив' ?></li>
            <li><span class="check">✓</span><?= $lang==='en'?'Contact':'Контакт' ?></li>
            <li><span class="check">✓</span><?= $lang==='en'?'Details (card/IBAN or crypto address)':'Реквізити (картка/IBAN або крипто-адреса)' ?></li>
          </ul>
        </aside>



        
      </div>
    </div>

    <div id="how" style="margin-top:28px"></div>
  
    <div id="regionFooter" class="region region--footer" aria-label="Footer region"></div>
</section>

    <?php /* CUSTOM_BLOCKS_RENDER */ ?>
    <?php foreach($custom_blocks as $cb):
      if(!is_array($cb)) continue;
      $cid = (string)($cb['id'] ?? '');
      if($cid==='') continue;
      $type = (string)($cb['type'] ?? 'text');
      $title = (string)($cb['title_'.$lang] ?? ($cb['title_uk'] ?? ''));
      $text  = (string)($cb['text_'.$lang] ?? ($cb['text_uk'] ?? ''));
      $btnText = (string)($cb['btn_'.$lang] ?? ($cb['btn_uk'] ?? ''));
      $btnUrl  = (string)($cb['url'] ?? '#rates');
      $img     = (string)($cb['img'] ?? '');
      $blockId = 'cb_'.$cid;
    ?>
      <section class="card customBlock <?= ($cb['preset']??'')?("preset-".h($cb['preset'])):'' ?>" data-block="<?= h($blockId) ?>" data-block-label="Custom block">
                <?php $preset = (string)($cb['preset'] ?? ''); ?>
        <?php if($preset==='split'): ?><div class="customBlock__split"><?php endif; ?>
<?php if($title): ?><h2 class="sectionTitle" data-el="<?= h($blockId.'_title') ?>"><?= h($title) ?></h2><?php endif; ?>
        <?php if($img && $type==='image'): ?>
          <div class="customBlock__img"><img src="<?= h($img) ?>" alt="" loading="lazy" /></div>
        <?php endif; ?>
        <?php if($text): ?><p class="muted" data-el="<?= h($blockId.'_text') ?>"><?= nl2br(h($text)) ?></p><?php endif; ?>
        <?php if($btnText): ?><a class="btn btn--cta" href="<?= h($btnUrl) ?>" data-el="<?= h($blockId.'_cta') ?>"><?= h($btnText) ?></a><?php endif; ?>
      
        <?php if($preset==='split'): ?></div><?php endif; ?>
</section>
    <?php endforeach; ?>

</main>

<!-- Asset picker modal -->
<div class="modal" id="modal" role="dialog" aria-modal="true" aria-label="Select asset">
  <div class="modal__overlay" id="modalOverlay"></div>
  <div class="modal__panel">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px">
      <div id="modalTitle" style="font-weight:900">Вибери актив</div>
      <button id="modalClose" type="button" style="border:none;background:rgba(255,255,255,.06);color:var(--text);border-radius:12px;padding:8px 10px;cursor:pointer">✕</button>
    </div>
    <div class="assetList" id="assetList"></div>
  </div>
</div>

<script>
  // FAQ toggle
  document.querySelectorAll(".faqItem").forEach((it)=>{
    it.addEventListener("click", ()=>{
      const i = it.getAttribute("data-faq");
      const ans = document.getElementById("faqAns"+i);
      if (!ans) return;
      const open = ans.style.display !== "none";
      ans.style.display = open ? "none" : "block";
    });
  });
</script>
<script src="/site.js?v=<?= @filemtime(__DIR__ . '/site.js') ?: time() ?>"></script>





<?php if($is_builder_allowed): ?>
<script id="CryptoUA_BUILDER">
(function(){
  function qsAll(sel){ return Array.prototype.slice.call(document.querySelectorAll(sel)); }
  function closest(el, sel){
    while(el && el !== document.documentElement){
      if(el.matches && el.matches(sel)) return el;
      el = el.parentElement;
    }
    return null;
  }

  var blocks = qsAll('[data-block]');
  if(!blocks.length) return;

  var st = document.createElement('style');
  st.textContent = `
    [data-block]{ position:relative; }
    [data-block].builder-hover{ outline:2px dashed rgba(35,213,171,.55); outline-offset:4px; }
    [data-block].builder-selected{ outline:2px solid rgba(124,92,255,.75); outline-offset:4px; }
    [data-el].el-hover{ outline:2px dashed rgba(124,92,255,.55); outline-offset:3px; }
    [data-el].el-selected{ outline:2px solid rgba(35,213,171,.75); outline-offset:3px; }
    .builderHandle{
      position:absolute; top:10px; right:10px;
      width:34px; height:30px;
      border-radius:10px;
      border:1px solid rgba(255,255,255,.14);
      background:rgba(255,255,255,.06);
      display:flex; align-items:center; justify-content:center;
      cursor:grab;
      z-index:50;
      opacity:.0;
      transition:opacity .15s ease, transform .15s ease;
      user-select:none;
    }
    [data-block]:hover .builderHandle{ opacity:1; transform:translateY(-1px); }
    .builderHandle:active{ cursor:grabbing; transform:translateY(0px) scale(.98); }
  `;
  document.head.appendChild(st);

  // inject drag handles
  blocks.forEach(function(el){
    if(el.querySelector('.builderHandle')) return;
    var h = document.createElement('div');
    h.className = 'builderHandle';
    h.setAttribute('draggable','true');
    h.setAttribute('title','Drag block');
    h.innerHTML = '⋮⋮';
    el.appendChild(h);
  });

  function clearStates(){
    qsAll('[data-block].builder-hover').forEach(function(el){ el.classList.remove('builder-hover'); });
    qsAll('[data-block].builder-selected').forEach(function(el){ el.classList.remove('builder-selected'); });
    qsAll('[data-el].el-hover').forEach(function(el){ el.classList.remove('el-hover'); });
    qsAll('[data-el].el-selected').forEach(function(el){ el.classList.remove('el-selected'); });
  }

  // element hover/select
  document.addEventListener('mousemove', function(e){
    var el = closest(e.target, '[data-el]');
    qsAll('[data-el].el-hover').forEach(function(x){ if(x!==el) x.classList.remove('el-hover'); });
    if(el) el.classList.add('el-hover');
  }, {passive:true});

  document.addEventListener('click', function(e){
    if(!window.parent || window.parent === window) return;

    var el = closest(e.target, '[data-el]');
    if(el){
      e.preventDefault(); e.stopPropagation();
      clearStates();
      el.classList.add('el-selected');
      var id = el.getAttribute('data-el');
      window.parent.postMessage({type:'cryptoua_builder_select_el', id:id}, '*');
      return;
    }

    var b = closest(e.target, '[data-block]');
    if(b){
      e.preventDefault(); e.stopPropagation();
      clearStates();
      b.classList.add('builder-selected');
      window.parent.postMessage({type:'cryptoua_builder_select_block', id: b.getAttribute('data-block')}, '*');
    }
  }, true);

  // Drag reorder blocks (CSS order) by handles
  var dragId = null;

  function getOrder(){
    var arr = qsAll('[data-block]').map(function(el){
      var o = parseInt(getComputedStyle(el).order || '0', 10) || 0;
      return {id: el.getAttribute('data-block'), order:o};
    });
    arr.sort(function(a,b){ return a.order - b.order; });
    return arr.map(function(x){ return x.id; });
  }
  function applyOrder(order){
    if(!Array.isArray(order)) return;
    order.forEach(function(id, i){
      var el = document.querySelector('[data-block="'+id+'"]');
      if(el) el.style.order = String(i);
    });
  }

  document.addEventListener('dragstart', function(e){
    var h = closest(e.target, '.builderHandle');
    if(!h) return;
    var b = closest(h, '[data-block]');
    if(!b) return;
    dragId = b.getAttribute('data-block');
    try{ e.dataTransfer.setData('text/plain', dragId); }catch(err){}
    try{ e.dataTransfer.effectAllowed = 'move'; }catch(err){}
  }, true);

  document.addEventListener('dragover', function(e){
    if(!dragId) return;
    var b = closest(e.target, '[data-block]');
    if(!b) return;
    e.preventDefault();
  }, true);

  document.addEventListener('drop', function(e){
    if(!dragId) return;
    var b = closest(e.target, '[data-block]');
    if(!b) return;
    e.preventDefault();
    var targetId = b.getAttribute('data-block');
    if(!targetId || targetId === dragId) { dragId=null; return; }

    var order = getOrder();
    var a = order.indexOf(dragId);
    var t = order.indexOf(targetId);
    if(a<0 || t<0) { dragId=null; return; }
    order.splice(a,1);
    order.splice(t,0,dragId);
    applyOrder(order);
    dragId = null;
    window.parent.postMessage({type:'cryptoua_builder_order_changed', order: order}, '*');
  }, true);

  document.addEventListener('dragend', function(){ dragId=null; }, true);

  // Apply styles/texts from parent
  function applyBlockStyles(map){
    if(!map || typeof map!=='object') return;
    Object.keys(map).forEach(function(id){
      var el = document.querySelector('[data-block="'+id+'"]');
      if(!el) return;
      var s = map[id] || {};
      if(s.bg!==undefined) el.style.background = s.bg || '';
      if(s.text!==undefined) el.style.color = s.text || '';
      if(s.radius!==undefined) el.style.borderRadius = (s.radius!=='' && s.radius!==null) ? (parseInt(s.radius,10)||0)+'px' : '';
      if(s.pad!==undefined) el.style.padding = (s.pad!=='' && s.pad!==null) ? (parseInt(s.pad,10)||0)+'px' : '';
      if(s.shadow!==undefined) el.style.boxShadow = s.shadow || '';
    });
  }
  function applyElStyles(map){
    if(!map || typeof map!=='object') return;
    Object.keys(map).forEach(function(id){
      var el = document.querySelector('[data-el="'+id+'"]');
      if(!el) return;
      var s = map[id] || {};
      if(s.bg!==undefined) el.style.background = s.bg || '';
      if(s.color!==undefined) el.style.color = s.color || '';
      if(s.radius!==undefined) el.style.borderRadius = (s.radius!=='' && s.radius!==null) ? (parseInt(s.radius,10)||0)+'px' : '';
      if(s.pad!==undefined) el.style.padding = (s.pad!=='' && s.pad!==null) ? (parseInt(s.pad,10)||0)+'px' : '';
      if(s.shadow!==undefined) el.style.boxShadow = s.shadow || '';
      if(s.fs!==undefined) el.style.fontSize = (s.fs!=='' && s.fs!==null) ? (parseInt(s.fs,10)||0)+'px' : '';
      if(s.w!==undefined) el.style.fontWeight = (s.w!=='' && s.w!==null) ? String(s.w) : '';
    });
  }
  function applyElTexts(map){
    if(!map || typeof map!=='object') return;
    Object.keys(map).forEach(function(id){
      var v = map[id];
      if(typeof v!=='string') return;
      var el = document.querySelector('[data-el="'+id+'"]');
      if(el) el.textContent = v;
    });
  
  function applyRegions(map){
    try{
      var header = document.getElementById('regionHeader');
      var footer = document.getElementById('regionFooter');
      var bodyWrap = document.querySelector('main.homeStack, main.container.homeStack, main.container');
      if(!header || !footer || !bodyWrap) return;
      var all = Array.prototype.slice.call(document.querySelectorAll('[data-block]'));
      // move all back to body
      all.forEach(function(el){ bodyWrap.appendChild(el); });
      all.forEach(function(el){
        var id = el.getAttribute('data-block');
        var region = (map && map[id]) ? String(map[id]) : 'body';
        if(region==='header') header.appendChild(el);
        else if(region==='footer') footer.appendChild(el);
        else bodyWrap.appendChild(el);
      });
    }catch(e){}
  }

}

  window.addEventListener('message', function(ev){
    var d = ev.data || {};
    if(d && d.type==='cryptoua_builder_apply'){
      if(d.order) applyOrder(d.order);
      if(d.blockStyles) applyBlockStyles(d.blockStyles);
      if(d.elStyles) applyElStyles(d.elStyles);
      if(d.elTexts) applyElTexts(d.elTexts);
      if(d.regions) applyRegions(d.regions);
    }
    if(d && d.type==='cryptoua_builder_select_block'){
      var el = document.querySelector('[data-block="'+d.id+'"]');
      if(el){
        clearStates();
        el.classList.add('builder-selected');
      }
    }
    if(d && d.type==='cryptoua_builder_select_el'){
      var el = document.querySelector('[data-el="'+d.id+'"]');
      if(el){
        clearStates();
        el.classList.add('el-selected');
      }
    }
  });
})();
</script>
<?php endif; ?>


<script id="CryptoUA_REGION_MOVER">
(function(){
  try{
    var mapD = <?= json_encode($home_region_desktop, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
    var mapM = <?= json_encode($home_region_mobile, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
    var header = document.getElementById('regionHeader');
    var footer = document.getElementById('regionFooter');
    var bodyWrap = document.querySelector('main.homeStack, main.container.homeStack, main.container');
    var heroRight = document.getElementById('heroRight');

    function hasDataBlock(el){
      return !!(el && el.getAttribute && el.getAttribute('data-block'));
    }

    function collectTop(out, root){
      if(!root || !root.children) return;
      Array.prototype.slice.call(root.children).forEach(function(ch){
        if(hasDataBlock(ch)) out.push(ch);
      });
    }

    function apply(){
      var isMobile = window.matchMedia && window.matchMedia('(max-width:820px)').matches;
      var map = isMobile ? mapM : mapD;
      if(!header || !footer || !bodyWrap) return;

      // Collect ONLY top-level blocks from the three regions.
      var all = [];
      collectTop(all, header);
      collectTop(all, bodyWrap);
      collectTop(all, footer);

      // Reset: move everything back to BODY first (keeps DOM stable for region moves).
      all.forEach(function(el){
        if(el.parentElement !== bodyWrap) bodyWrap.appendChild(el);
      });

      // Apply region placement for top-level blocks only.
      all.forEach(function(el){
        var id = el.getAttribute('data-block');
        var region = (map && map[id]) ? String(map[id]) : 'body';
        if(region === 'header') header.appendChild(el);
        else if(region === 'footer') footer.appendChild(el);
        else bodyWrap.appendChild(el);
      });

      // Keep inner hero right-column blocks inside hero (prevents "calculator goes to bottom").
      try{
        heroRight = heroRight || document.getElementById('heroRight');
        if(heroRight){
          ['rates','miniCard'].forEach(function(id){
            var el = document.querySelector('[data-block="'+id+'"]');
            if(el && el.parentElement !== heroRight) heroRight.appendChild(el);
          });
        }
      }catch(e){}
    }

    if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', apply);
    else apply();

    // update on breakpoint change
    try{
      var mq = window.matchMedia('(max-width:820px)');
      if(mq && mq.addEventListener) mq.addEventListener('change', apply);
      else if(mq && mq.addListener) mq.addListener(apply);
    }catch(e){}
  }catch(e){}
})();
</script>



<script id="CryptoUA_ITEMS_REORDER">
(function(){
  if(!location.search.match(/builder=1/)) return;

  function makePlaceholder(){
    var ph = document.createElement('div');
    ph.className = 'dndPlaceholder';
    ph.innerHTML = '<div class="dndPlaceholder__inner"></div>';
    return ph;
  }

  function dnd(containerSel, itemSel, attr, msgType){
    var wrap = document.querySelector(containerSel);
    if(!wrap) return;

    var dragEl = null;
    var ph = makePlaceholder();

    function order(){
      return Array.prototype.slice.call(wrap.querySelectorAll(itemSel)).map(function(el){
        return el.getAttribute(attr) || '';
      }).filter(Boolean);
    }

    function onDragStart(e){
      var it = e.target.closest(itemSel);
      if(!it) return;
      dragEl = it;
      it.classList.add('dragging');
      try{ e.dataTransfer.setData('text/plain', it.getAttribute(attr) || ''); }catch(err){}
      try{ e.dataTransfer.effectAllowed = 'move'; }catch(err){}
      // placeholder height matches element
      ph.style.height = (it.getBoundingClientRect().height || 48) + 'px';
      // insert placeholder after dragged element
      it.parentNode.insertBefore(ph, it.nextSibling);
      setTimeout(function(){ it.style.opacity = '0.3'; }, 0);
    }

    function onDragOver(e){
      if(!dragEl) return;
      var over = e.target.closest(itemSel);
      if(!over || over===dragEl) return;
      e.preventDefault();
      var rect = over.getBoundingClientRect();
      var before = (e.clientY - rect.top) < rect.height/2;
      if(before) wrap.insertBefore(ph, over);
      else wrap.insertBefore(ph, over.nextSibling);
    }

    function onDrop(e){
      if(!dragEl) return;
      e.preventDefault();
      wrap.insertBefore(dragEl, ph);
      cleanup(true);
    }

    function cleanup(notify){
      if(!dragEl) return;
      dragEl.style.opacity = '1';
      dragEl.classList.remove('dragging');
      if(ph.parentNode) ph.parentNode.removeChild(ph);
      if(notify){
        try{
          if(window.parent && window.parent !== window){
            window.parent.postMessage({type: msgType, order: order()}, '*');
          }
        }catch(err){}
      }
      dragEl = null;
    }

    wrap.addEventListener('dragstart', onDragStart, true);
    wrap.addEventListener('dragover', onDragOver, true);
    wrap.addEventListener('drop', onDrop, true);
    wrap.addEventListener('dragend', function(){ cleanup(true); }, true);
  }

  // Reviews
  dnd('.reviewsGrid', '.reviewCard', 'data-review-id', 'cryptoua_builder_reviews_order');
  // Partners
  dnd('.partnerGrid', '.partnerItem', 'data-partner-id', 'cryptoua_builder_partners_order');
  // Limits cards
  dnd('.cards3', '.limitCard', 'data-limit-id', 'cryptoua_builder_limits_order');
  // FAQ rows
  dnd('.faqList', '.faqRow', 'data-faq-id', 'cryptoua_builder_faq_order');
  // Advantages cards
  dnd('#advantages', '.advCard', 'data-adv-id', 'cryptoua_builder_adv_order');
})();
</script>



<script id="CryptoUA_FAQ_TOGGLE_V54">
(function(){
  function init(){
    var list = document.querySelector('.faqList');
    if(!list) return;
    list.addEventListener('click', function(e){
      var item = e.target.closest('.faqItem');
      if(!item) return;
      var row = item.closest('.faqRow');
      if(!row) return;
      var ans = row.querySelector('.faqAns');
      if(!ans) return;
      ans.style.display = (ans.style.display==='none' || !ans.style.display) ? 'block' : 'none';
    });
  }
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
</script>


<script id="burgerMenuScriptV57">
(function(){
  function init(){
    var btn = document.querySelector('.burgerBtn');
    var menu = document.getElementById('mobileMenu');
    if(!btn || !menu) return;

    function setOpen(open){
      document.documentElement.classList.toggle('navOpen', !!open);
      try{ btn.setAttribute('aria-expanded', open ? 'true' : 'false'); }catch(e){}
      try{ menu.setAttribute('aria-hidden', open ? 'false' : 'true'); }catch(e){}
      if(open){
        try{
          var closeBtn = menu.querySelector('.mobileMenu__close');
          if(closeBtn) closeBtn.focus();
        }catch(e){}
      }
    }

    function toggle(){
      setOpen(!document.documentElement.classList.contains('navOpen'));
    }

    // ensure button does not have a conflicting inline onclick
    try{ btn.onclick = null; }catch(e){}

    btn.addEventListener('click', function(e){
      e.preventDefault();
      toggle();
    });

    // close on backdrop / close / link
    menu.addEventListener('click', function(e){
      if(e.target === menu) return setOpen(false);
      if(e.target && e.target.closest && e.target.closest('.mobileMenu__close')) return setOpen(false);
      var a = e.target && e.target.closest ? e.target.closest('a.mobileMenu__link') : null;
      if(a) return setOpen(false);
    });

    document.addEventListener('keydown', function(e){
      if(e.key === 'Escape') setOpen(false);
    });

    // start closed
    setOpen(false);
  }

  if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
</script>

</body>
</html>
