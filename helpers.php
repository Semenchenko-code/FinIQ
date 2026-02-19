<?php
declare(strict_types=1);
if (defined('CRYPTOUA_HELPERS_LOADED')) { return; }
define('CRYPTOUA_HELPERS_LOADED', true);

function base_path(string $p=''): string {
  return __DIR__ . ($p ? DIRECTORY_SEPARATOR . $p : '');
}
function storage_path(string $p=''): string {
  return base_path('storage' . ($p ? DIRECTORY_SEPARATOR . $p : ''));
}
function config_path(): string { return storage_path('config.php'); }
function is_installed(): bool { return file_exists(config_path()); }
function cfg(string $key, $default=null) {
  if (!is_installed()) return $default;
  static $cfg=null;
  if ($cfg===null) $cfg = require config_path();
  return $cfg[$key] ?? $default;
}
function db(): PDO {
  static $pdo=null;
  if ($pdo) return $pdo;
  $dsn = cfg('DB_DSN', 'sqlite:' . storage_path('data.sqlite'));
  $user = cfg('DB_USER', null);
  $pass = cfg('DB_PASS', null);
  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
  ensure_migrations($pdo);
  ensure_system_pages_runtime($pdo);
  return $pdo;
}

function is_mysql(): bool {
  $dsn = (string)cfg('DB_DSN','');
  return (stripos($dsn, 'mysql:') === 0);
}

/**
 * Best-effort migrations for existing installs (adds new tables if missing).
 */
function ensure_migrations(PDO $pdo): void {
  static $done = false;
  if ($done) return;
  $done = true;

  try {
    $isMysql = is_mysql();

    // directions table (exchange pairs)
    if ($isMysql) {
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
    } else {
      $pdo->exec("CREATE TABLE IF NOT EXISTS directions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        from_asset TEXT NOT NULL,
        to_asset   TEXT NOT NULL,
        enabled    INTEGER NOT NULL DEFAULT 1,
        rate       REAL NOT NULL DEFAULT 0,
        fee_pct    REAL NOT NULL DEFAULT 0.8,
        min_amount REAL,
        max_amount REAL,
        sort_order INTEGER NOT NULL DEFAULT 100,
        updated_at TEXT,
        UNIQUE(from_asset,to_asset)
      );");
    }

    // Seed defaults if empty (use existing settings rates/fees)
    $cnt = 0;
    try {
      if ($isMysql) $cnt = (int)($pdo->query("SELECT COUNT(*) c FROM directions")->fetch()['c'] ?? 0);
      else $cnt = (int)($pdo->query("SELECT COUNT(*) c FROM directions")->fetch()['c'] ?? 0);
    } catch(Throwable $e) { $cnt = 0; }

    if ($cnt === 0) {
      $rates = setting_get('rates', []);
      $fees  = setting_get('fees', ['feePctC2U'=>0.8,'feePctU2C'=>0.8]);
      $feeC2U = (float)($fees['feePctC2U'] ?? 0.8);
      $feeU2C = (float)($fees['feePctU2C'] ?? 0.8);

      $sort = 10;
      foreach ($rates as $k=>$v){
        if (!is_string($k)) continue;
        if (substr($k, -4) !== '_UAH') continue;
        $asset = substr($k, 0, -4);
        $rateUah = (float)$v;
        if ($rateUah <= 0) continue;

        // crypto -> UAH
        $stmt = $pdo->prepare($isMysql
          ? "INSERT IGNORE INTO directions(from_asset,to_asset,enabled,rate,fee_pct,min_amount,max_amount,sort_order,updated_at) VALUES(?,?,?,?,?,?,?,?,?)"
          : "INSERT OR IGNORE INTO directions(from_asset,to_asset,enabled,rate,fee_pct,min_amount,max_amount,sort_order,updated_at) VALUES(?,?,?,?,?,?,?,?,?)"
        );
        $stmt->execute([$asset,'UAH',1,$rateUah,$feeC2U,2000,300000,$sort,now_iso()]);

        // UAH -> crypto (invert)
        $inv = $rateUah ? (1.0/$rateUah) : 0.0;
        $stmt->execute(['UAH',$asset,1,$inv,$feeU2C,2000,300000,$sort+1,now_iso()]);

        $sort += 10;
      }
    }
  } catch(Throwable $e) {
    // ignore
  }
}

/** Ensure standard public pages exist (Terms/Privacy/KYC-AML) */
function ensure_system_pages_runtime(PDO $pdo): void {
  static $done=false;
  if($done) return;
  $done=true;

  $defs = [
    ['slug'=>'terms','uk_title'=>'Умови','en_title'=>'Terms',
      'uk_body'=>"Тут будуть умови використання сервісу.\n\n(Текст можна змінити в адмінці.)",
      'en_body'=>"Service terms will be here.\n\n(You can edit this in admin.)"],
    ['slug'=>'privacy','uk_title'=>'Конфіденційність','en_title'=>'Privacy',
      'uk_body'=>"Тут буде політика конфіденційності.\n\n(Текст можна змінити в адмінці.)",
      'en_body'=>"Privacy policy will be here.\n\n(You can edit this in admin.)"],
    ['slug'=>'kyc-aml','uk_title'=>'KYC / AML','en_title'=>'KYC / AML',
      'uk_body'=>"Тут буде інформація щодо KYC/AML.\n\n(Текст можна змінити в адмінці.)",
      'en_body'=>"KYC/AML information will be here.\n\n(You can edit this in admin.)"],
  ];

  try{
    $chk = $pdo->prepare("SELECT id FROM pages WHERE slug=? AND lang=? LIMIT 1");
    $ins = $pdo->prepare("INSERT INTO pages(lang,slug,title,body,created_at,updated_at) VALUES(?,?,?,?,?,?)");
    foreach($defs as $d){
      foreach(['uk','en'] as $lng){
        $chk->execute([$d['slug'],$lng]);
        $ex = $chk->fetch();
        if(!$ex){
          $title = ($lng==='uk') ? $d['uk_title'] : $d['en_title'];
          $body  = ($lng==='uk') ? $d['uk_body']  : $d['en_body'];
          $ins->execute([$lng,$d['slug'],$title,$body,now_iso(),now_iso()]);
        }
      }
    }
  }catch(Throwable $e){
    // ignore if pages table not exists yet
  }
}



/** Read whole config array (from storage/config.php). */
function cfg_all(): array {
  if (!is_installed()) return [];
  $cfg = require config_path();
  return is_array($cfg) ? $cfg : [];
}

/** Persist a config key to storage/config.php (best-effort). */
function cfg_set(string $key, $value): bool {
  if (!is_installed()) return false;
  $cfg = cfg_all();
  $cfg[$key] = $value;
  $php = "<?php\nreturn " . var_export($cfg, true) . ";\n";
  return (bool)@file_put_contents(config_path(), $php);
}

/** Get a direction row (enabled only by default). */
function direction_get(string $from, string $to, bool $enabledOnly=true): ?array {
  $pdo = db();
  $isMysql = is_mysql();
  $sql = "SELECT * FROM directions WHERE from_asset=? AND to_asset=? " . ($enabledOnly ? "AND enabled=1" : "") . " LIMIT 1";
  $st = $pdo->prepare($sql);
  $st->execute([$from,$to]);
  $row = $st->fetch();
  return $row ?: null;
}

/** List directions (enabled only by default). */
function directions_list(bool $enabledOnly=true): array {
  $pdo = db();
  $sql = "SELECT * FROM directions " . ($enabledOnly ? "WHERE enabled=1 " : "") . "ORDER BY sort_order ASC, id ASC";
  return $pdo->query($sql)->fetchAll() ?: [];
}

function now_iso(): string { return gmdate('c'); }

function json_out($data, int $code=200): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}

function require_login(): array {
  if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
  if (!isset($_SESSION['user'])) {
    header('Location: /admin/index.php');
    exit;
  }
  return $_SESSION['user'];
}
function require_role(string $role): array {
  $u = require_login();
  if (($u['role'] ?? '') !== $role) {
    http_response_code(403);
    echo "Forbidden";
    exit;
  }
  return $u;
}
function audit(string $action, array $meta=[]): void {
  try {
    $u = $_SESSION['user'] ?? ['username'=>'(system)','role'=>'system'];
    $pdo = db();
    $stmt=$pdo->prepare("INSERT INTO audit_log(ts, username, role, ip, action, meta_json) VALUES(?,?,?,?,?,?)");
    $stmt->execute([now_iso(), $u['username'], $u['role'], $_SERVER['REMOTE_ADDR'] ?? '', $action, json_encode($meta, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
  } catch(Throwable $e) { /* ignore */ }
}

function setting_get(string $key, $default=null) {
  $pdo=db();
  $dsn = (string)cfg('DB_DSN','');
  $isMysql = (stripos($dsn, 'mysql:') === 0);
  $sql = $isMysql ? "SELECT value_json FROM settings WHERE `key`=?" : "SELECT value_json FROM settings WHERE key=?";
  $st=$pdo->prepare($sql);
  $st->execute([$key]);
  $row=$st->fetch();
  if(!$row) return $default;
  $v=json_decode($row['value_json'], true);
  return $v===null ? $default : $v;
}
function setting_set(string $key, $value): void {
  $pdo=db();
  $json=json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  $dsn = (string)cfg('DB_DSN','');
  $isMysql = (stripos($dsn, 'mysql:') === 0);
  if ($isMysql) {
    $pdo->prepare("INSERT INTO settings(`key`,value_json,updated_at) VALUES(?,?,?) ON DUPLICATE KEY UPDATE value_json=VALUES(value_json), updated_at=VALUES(updated_at)")
        ->execute([$key,$json,now_iso()]);
  } else {
    $pdo->prepare("INSERT INTO settings(key,value_json,updated_at) VALUES(?,?,?) ON CONFLICT(key) DO UPDATE SET value_json=excluded.value_json, updated_at=excluded.updated_at")
        ->execute([$key,$json,now_iso()]);
  }
}
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
