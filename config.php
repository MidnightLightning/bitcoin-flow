<?php
$db = new PDO('sqlite:/var/www/cache.sqlite3:');

$sql = <<<SQL
CREATE TABLE `txn` (
  `index` integer UNIQUE,
  `hash` text UNIQUE,
  `cache_time` integer,
  `data` text
)
SQL;
$db->exec($sql);
