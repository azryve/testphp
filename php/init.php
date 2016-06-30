<?php

require "config.php";

global $table_name, $db_name;
$db = new PDO('mysql:dbname=mysql');
$init_queries = array ( "DROP DATABASE ${db_name}",
			"CREATE DATABASE ${db_name}",
			"CREATE TABLE ${db_name}.${table_name} (
			lb_id int,
			ip int,
			fwmark int NOT NULL,
			PRIMARY KEY (lb_id, ip),
			UNIQUE (lb_id, fwmark));",
			"CREATE TABLE ${db_name}.${table_stats} (
			id int auto_increment,
			slowpath int unsigned,
			fastpath int unsigned,
			PRIMARY KEY (id));",
			"INSERT INTO ${db_name}.${table_stats} (slowpath, fastpath) VALUES (0,0)"
);  

$db->beginTransaction();
foreach ($init_queries as $query) {
	$res = $db->prepare($query);
	$res->execute();
}
$db->commit();
