<?php
require_once __DIR__ . '/helpers.php';
// force reinstall: /install.php?force=1
if (isset($_GET['force']) && $_GET['force']==='1') { @unlink(config_path()); }

if (is_installed()) { header('Location: /admin/index.php'); exit; }

$error=''; $ok='';

function create_schema(PDO $pdo, bool $isMysql): void {
  if (!$isMysql) {
    $pdo->exec("PRAGMA journal_mode=WAL;");
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value_json TEXT NOT NULL, updated_at TEXT);");
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT UNIQUE, role TEXT, pass_hash TEXT, created_at TEXT);");
    $pdo->exec("CREATE TABLE IF NOT EXISTS quotes (id INTEGER PRIMARY KEY AUTOINCREMENT, created_at TEXT, expires_at_ms INTEGER, mode TEXT, from_asset TEXT, to_asset TEXT, from_amount REAL, to_amount REAL, rate REAL, fee_pct REAL);");
    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (id INTEGER PRIMARY KEY AUTOINCREMENT, created_at TEXT, updated_at TEXT, status TEXT, mode TEXT, quote_id INTEGER, from_asset TEXT, to_asset TEXT, from_amount REAL, to_amount REAL, fee_pct REAL, name TEXT, contact TEXT, payout TEXT, notes TEXT, tg_message_id INTEGER, tg_chat_id TEXT);");
    $pdo->exec("CREATE TABLE IF NOT EXISTS pages (id INTEGER PRIMARY KEY AUTOINCREMENT, lang TEXT, slug TEXT, title TEXT, body TEXT, created_at TEXT, updated_at TEXT);");
    $pdo->exec("CREATE TABLE IF NOT EXISTS audit_log (id INTEGER PRIMARY KEY AUTOINCREMENT, ts TEXT, username TEXT, role TEXT, ip TEXT, action TEXT, meta_json TEXT);");
    $pdo->exec("CREATE TABLE IF NOT EXISTS directions (id INTEGER PRIMARY KEY AUTOINCREMENT, from_asset TEXT NOT NULL, to_asset TEXT NOT NULL, enabled INTEGER NOT NULL DEFAULT 1, rate REAL NOT NULL DEFAULT 0, fee_pct REAL NOT NULL DEFAULT 0.8, min_amount REAL, max_amount REAL, sort_order INTEGER NOT NULL DEFAULT 100, updated_at TEXT, UNIQUE(from_asset,to_asset));");
  } else {
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
      `key` VARCHAR(190) PRIMARY KEY,
      value_json LONGTEXT NOT NULL,
      updated_at VARCHAR(64) NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
      id INT AUTO_INCREMENT PRIMARY KEY,
      username VARCHAR(190) UNIQUE,
      role VARCHAR(32),
      pass_hash VARCHAR(255),
      created_at VARCHAR(64)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS quotes (
      id INT AUTO_INCREMENT PRIMARY KEY,
      created_at VARCHAR(64),
      expires_at_ms BIGINT,
      mode VARCHAR(32),
      from_asset VARCHAR(16),
      to_asset VARCHAR(16),
      from_amount DOUBLE,
      to_amount DOUBLE,
      rate DOUBLE,
      fee_pct DOUBLE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
      id INT AUTO_INCREMENT PRIMARY KEY,
      created_at VARCHAR(64),
      updated_at VARCHAR(64),
      status VARCHAR(32),
      mode VARCHAR(32),
      quote_id INT,
      from_asset VARCHAR(16),
      to_asset VARCHAR(16),
      from_amount DOUBLE,
      to_amount DOUBLE,
      fee_pct DOUBLE,
      name VARCHAR(190),
      contact VARCHAR(190),
      payout VARCHAR(255),
      notes LONGTEXT,
      tg_message_id BIGINT NULL,
      tg_chat_id VARCHAR(64) NULL,
      KEY idx_status(status),
      KEY idx_created(created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS pages (
      id INT AUTO_INCREMENT PRIMARY KEY,
      lang VARCHAR(8),
      slug VARCHAR(190),
      title VARCHAR(255),
      body LONGTEXT,
      created_at VARCHAR(64),
      updated_at VARCHAR(64),
      UNIQUE KEY uniq_lang_slug(lang, slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS audit_log (
      id INT AUTO_INCREMENT PRIMARY KEY,
      ts VARCHAR(64),
      username VARCHAR(190),
      role VARCHAR(32),
      ip VARCHAR(64),
      action VARCHAR(64),
      meta_json LONGTEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS directions (
      id INT AUTO_INCREMENT PRIMARY KEY,
      from_asset VARCHAR(16) NOT NULL,
      to_asset   VARCHAR(16) NOT NULL,
      enabled    TINYINT(1) NOT NULL DEFAULT 1,
      rate       DOUBLE NOT NULL DEFAULT 0,
      fee_pct    DOUBLE NOT NULL DEFAULT 0.8,
      min_amount DOUBLE NULL,
      max_amount DOUBLE NULL,
      sort_order INT NOT NULL DEFAULT 100,
      updated_at VARCHAR(64) NULL,
      UNIQUE KEY uniq_from_to(from_asset,to_asset)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
  }
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $adminUser = trim($_POST['admin_user'] ?? 'admin');
  $adminPass = (string)($_POST['admin_pass'] ?? '');
  $opUser = trim($_POST['op_user'] ?? 'operator');
  $opPass = (string)($_POST['op_pass'] ?? '');

  $baseUrl = trim($_POST['base_url'] ?? '');
  $tgToken = trim($_POST['tg_token'] ?? '');
  $tgChat  = trim($_POST['tg_chat'] ?? '');
  $secret  = trim($_POST['tg_secret'] ?? '');

  $dbType = ($_POST['db_type'] ?? 'sqlite') === 'mysql' ? 'mysql' : 'sqlite';

  if (!$adminPass || !$opPass) $error = 'Вкажи паролі admin/operator';
  else {
    @mkdir(__DIR__ . '/storage', 0775, true);
    @mkdir(__DIR__ . '/uploads', 0775, true);

    if ($dbType === 'mysql') {
      $dbHost = trim($_POST['db_host'] ?? 'localhost');
      $dbName = trim($_POST['db_name'] ?? '');
      $dbUser = trim($_POST['db_user'] ?? '');
      $dbPass = (string)($_POST['db_pass'] ?? '');
      if (!$dbName || !$dbUser) {
        $error = 'Вкажи MySQL: db_name та db_user';
      } else {
        $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
        $cfg = [
          'DB_DSN' => $dsn,
          'DB_USER' => $dbUser,
          'DB_PASS' => $dbPass,
          'BASE_URL' => $baseUrl,
          'TELEGRAM_BOT_TOKEN' => $tgToken,
          'TELEGRAM_CHAT_ID' => $tgChat,
          'TG_WEBHOOK_SECRET' => $secret,
        ];
        file_put_contents(__DIR__ . '/storage/config.php', "<?php\nreturn " . var_export($cfg, true) . ";\n");

        try {
          $pdo = db();
          create_schema($pdo, true);

          $pdo->prepare("INSERT INTO users(username, role, pass_hash, created_at) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE role=VALUES(role), pass_hash=VALUES(pass_hash)")
              ->execute([$adminUser,'admin', password_hash($adminPass, PASSWORD_DEFAULT), now_iso()]);
          $pdo->prepare("INSERT INTO users(username, role, pass_hash, created_at) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE role=VALUES(role), pass_hash=VALUES(pass_hash)")
              ->execute([$opUser,'operator', password_hash($opPass, PASSWORD_DEFAULT), now_iso()]);

          setting_set('rates', ['USDT_UAH'=>39.50,'BTC_UAH'=>2000000,'ETH_UAH'=>110000]);
          setting_set('fees', ['feePctC2U'=>0.8,'feePctU2C'=>0.9]);
          setting_set('quote', ['quoteTtlSec'=>300]);
          setting_set('seo_uk', ['title'=>'CryptoUA — P2P Crypto ⇄ UAH','description'=>'Обмін криптовалют на гривні та у зворотному напрямку. Фіксація курсу, оператори 24/7.','keywords'=>'crypto,uah,обмін,USDT,BTC,ETH,p2p','og_image'=>'/assets/og-cover.png']);
          setting_set('seo_en', ['title'=>'CryptoUA — P2P Crypto ⇄ UAH','description'=>'Exchange crypto to UAH and back. Rate fixation, operators 24/7.','keywords'=>'crypto,uah,exchange,USDT,BTC,ETH,p2p','og_image'=>'/assets/og-cover.png']);

          $base = rtrim($baseUrl,'/');
          file_put_contents(__DIR__ . '/robots.txt', "User-agent: *\nAllow: /\nSitemap: {$base}/sitemap.xml\n");
          file_put_contents(__DIR__ . '/sitemap.xml', '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>');

          $ok = "Встановлено ✅ Перейди в /admin (логін: {$adminUser})";
        } catch(Throwable $e) {
          @unlink(config_path());
          $error = "MySQL error: " . $e->getMessage();
        }
      }
    } else {
      $cfg = [
        'DB_DSN' => 'sqlite:' . __DIR__ . '/storage/data.sqlite',
        'BASE_URL' => $baseUrl,
        'TELEGRAM_BOT_TOKEN' => $tgToken,
        'TELEGRAM_CHAT_ID' => $tgChat,
        'TG_WEBHOOK_SECRET' => $secret,
      ];
      file_put_contents(__DIR__ . '/storage/config.php', "<?php\nreturn " . var_export($cfg, true) . ";\n");
      try {
        $pdo = db();
        create_schema($pdo, false);

        $pdo->prepare("INSERT OR IGNORE INTO users(username, role, pass_hash, created_at) VALUES(?,?,?,?)")
            ->execute([$adminUser,'admin', password_hash($adminPass, PASSWORD_DEFAULT), now_iso()]);
        $pdo->prepare("INSERT OR IGNORE INTO users(username, role, pass_hash, created_at) VALUES(?,?,?,?)")
            ->execute([$opUser,'operator', password_hash($opPass, PASSWORD_DEFAULT), now_iso()]);

        setting_set('rates', ['USDT_UAH'=>39.50,'BTC_UAH'=>2000000,'ETH_UAH'=>110000]);
        setting_set('fees', ['feePctC2U'=>0.8,'feePctU2C'=>0.9]);
        setting_set('quote', ['quoteTtlSec'=>300]);
        setting_set('seo_uk', ['title'=>'CryptoUA — P2P Crypto ⇄ UAH','description'=>'Обмін криптовалют на гривні та у зворотному напрямку. Фіксація курсу, оператори 24/7.','keywords'=>'crypto,uah,обмін,USDT,BTC,ETH,p2p','og_image'=>'/assets/og-cover.png']);
        setting_set('seo_en', ['title'=>'CryptoUA — P2P Crypto ⇄ UAH','description'=>'Exchange crypto to UAH and back. Rate fixation, operators 24/7.','keywords'=>'crypto,uah,exchange,USDT,BTC,ETH,p2p','og_image'=>'/assets/og-cover.png']);

        $base = rtrim($baseUrl,'/');
        file_put_contents(__DIR__ . '/robots.txt', "User-agent: *\nAllow: /\nSitemap: {$base}/sitemap.xml\n");
        file_put_contents(__DIR__ . '/sitemap.xml', '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>');

        $ok = "Встановлено ✅ Перейди в /admin (логін: {$adminUser})";
      } catch(Throwable $e) {
        @unlink(config_path());
        $error = "SQLite error (потрібен модуль pdo_sqlite/sqlite3): " . $e->getMessage();
      }
    }
  }
}
?><!doctype html>
<html lang="uk"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>CryptoUA Installer</title>
<style>
body{font-family:system-ui;background:#070a18;color:#e8ecff;margin:0;display:flex;justify-content:center;align-items:center;min-height:100vh}
.card{width:min(820px,92%);border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.03);border-radius:18px;padding:18px}
input,select{width:100%;padding:10px 12px;border-radius:14px;border:1px solid rgba(255,255,255,.14);background:rgba(255,255,255,.04);color:#e8ecff}
.row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.btn{margin-top:12px;padding:10px 14px;border-radius:14px;border:none;cursor:pointer;background:linear-gradient(135deg, rgba(124,92,255,.95), rgba(35,213,171,.95));color:#071022;font-weight:800}
.muted{color:#a9b2d6}
.err{color:#ffb1b1;margin-top:8px;white-space:pre-wrap}
.ok{color:#b7ffcf;margin-top:8px}
hr{border:none;border-top:1px solid rgba(255,255,255,.12);margin:14px 0}
.small{font-size:12px}
</style>
<script>
function toggleDb(){
  const t = document.getElementById('db_type').value;
  document.getElementById('mysqlBlock').style.display = (t==='mysql') ? 'block' : 'none';
}
</script>
</head><body onload="toggleDb()">
<div class="card">
  <h2 style="margin:0 0 8px">CryptoUA — One‑Click Install</h2>
  <p class="muted" style="margin:0 0 12px">Помилка <b>could not find driver</b> = немає <b>pdo_sqlite</b>. Обери <b>MySQL</b> і введи доступи.</p>

  <form method="post">
    <div class="row">
      <div>
        <label class="muted">DB type</label>
        <select id="db_type" name="db_type" onchange="toggleDb()">
          <option value="sqlite" <?= (($_POST['db_type'] ?? 'sqlite')==='sqlite')?'selected':'' ?>>SQLite (one‑click)</option>
          <option value="mysql"  <?= (($_POST['db_type'] ?? 'sqlite')==='mysql')?'selected':'' ?>>MySQL / MariaDB</option>
        </select>
        <div class="muted small" style="margin-top:6px">SQLite потребує модуль pdo_sqlite. MySQL працює майже на всіх хостингах.</div>
      </div>
      <div>
        <label class="muted">BASE_URL (https://domain.com)</label>
        <input name="base_url" value="<?= h($_POST['base_url'] ?? '') ?>" placeholder="https://domain.com">
      </div>
    </div>

    <div id="mysqlBlock" style="display:none">
      <hr>
      <div class="row">
        <div>
          <label class="muted">MySQL host</label>
          <input name="db_host" value="<?= h($_POST['db_host'] ?? 'localhost') ?>">
        </div>
        <div>
          <label class="muted">MySQL database</label>
          <input name="db_name" value="<?= h($_POST['db_name'] ?? '') ?>" placeholder="dbname">
        </div>
      </div>
      <div style="height:10px"></div>
      <div class="row">
        <div>
          <label class="muted">MySQL user</label>
          <input name="db_user" value="<?= h($_POST['db_user'] ?? '') ?>" placeholder="dbuser">
        </div>
        <div>
          <label class="muted">MySQL password</label>
          <input name="db_pass" type="password" value="<?= h($_POST['db_pass'] ?? '') ?>">
        </div>
      </div>
    </div>

    <hr>

    <div class="row">
      <div>
        <label class="muted">Admin username</label>
        <input name="admin_user" value="<?= h($_POST['admin_user'] ?? 'admin') ?>">
      </div>
      <div>
        <label class="muted">Admin password</label>
        <input name="admin_pass" type="password" required>
      </div>
    </div>
    <div style="height:10px"></div>
    <div class="row">
      <div>
        <label class="muted">Operator username</label>
        <input name="op_user" value="<?= h($_POST['op_user'] ?? 'operator') ?>">
      </div>
      <div>
        <label class="muted">Operator password</label>
        <input name="op_pass" type="password" required>
      </div>
    </div>

    <hr>
    <div class="row">
      <div>
        <label class="muted">Telegram bot token (optional)</label>
        <input name="tg_token" value="<?= h($_POST['tg_token'] ?? '') ?>">
      </div>
      <div>
        <label class="muted">Telegram chat_id (optional)</label>
        <input name="tg_chat" value="<?= h($_POST['tg_chat'] ?? '') ?>">
      </div>
    </div>
    <div style="height:10px"></div>
    <div>
      <label class="muted">Telegram webhook secret (optional)</label>
      <input name="tg_secret" value="<?= h($_POST['tg_secret'] ?? '') ?>">
      <div class="muted small" style="margin-top:6px">Webhook: /tg/webhook.php?token=SECRET</div>
    </div>

    <button class="btn" type="submit">Install</button>

    <?php if($error): ?><div class="err"><?= h($error) ?></div><?php endif; ?>
    <?php if($ok): ?><div class="ok"><?= h($ok) ?></div><?php endif; ?>
  </form>

  <div style="height:10px"></div>
  <div class="muted">
    Після встановлення:<br>
    • Адмінка: <code>/admin</code><br>
    • Telegram webhook: <code>/tg/webhook.php?token=SECRET</code>
  </div>
</div>
</body></html>
