<?php
header('Content-Type: text/plain; charset=utf-8');
echo "PHP: " . PHP_VERSION . "\n";
echo "SAPI: " . php_sapi_name() . "\n\n";
echo "PDO drivers: " . implode(', ', PDO::getAvailableDrivers()) . "\n";
echo "Extensions loaded:\n";
$exts = ['pdo','pdo_mysql','pdo_sqlite','sqlite3','mysqli','curl','mbstring','json'];
foreach($exts as $e){
  echo " - {$e}: " . (extension_loaded($e)?'yes':'no') . "\n";
}
