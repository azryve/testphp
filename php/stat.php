<?php

require "config.php";
require "database.php";

$res = usePreparedSelectBlade("SELECT COUNT(*) as count, MAX(fwmark) as max FROM ${table_name}");
$stat = $res->fetch(PDO::FETCH_ASSOC);
echo "IPs: ${stat['count']}\tMax: ${stat['max']}\n";
