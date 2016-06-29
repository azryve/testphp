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
			UNIQUE (lb_id, fwmark));"
);  
foreach ($init_queries as $query)
	$db->exec($query);

