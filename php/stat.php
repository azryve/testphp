<?php

require "config.php";
require "database.php";

global $table_name, $table_stats;

$res = usePreparedSelectBlade("SELECT COUNT(*) as count, MAX(fwmark) as max FROM ${table_name}");
$stat = $res->fetch(PDO::FETCH_ASSOC);
echo "IPs: ${stat['count']}\tMax: ${stat['max']}\n";

$res = usePreparedSelectBlade("SELECT slowpath, fastpath FROM ${table_stats}");
$stat = $res->fetch(PDO::FETCH_ASSOC);

echo "Slow: ${stat['slowpath']}\tFast: ${stat['fastpath']}\n";
