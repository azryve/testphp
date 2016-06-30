<?php

require "config.php";
require "database.php";

global $db, $table_name;

if (count($argv) < 2)
	exit(1);

$limit = $argv[1];

$res = $db->prepare("DELETE FROM ${table_name} LIMIT ${limit}");
$res->execute();
print("Deleted ". $res->rowCount() . " rows\n");
