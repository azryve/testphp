<?php

global $db;
global $table_name, $db_name;
$db_name = 'testdb';
$table_name = 'table1';

function init()
{
        global $table_name, $db_name;
        $db = new PDO('mysql:dbname=mysql');
        $init_queries = array ( "DROP DATABASE ${db_name}",
				"CREATE DATABASE ${db_name}",
                                "CREATE TABLE ${db_name}.${table_name} (
                                lb_id int,
                                ip int,
                                fwmark int,
                                PRIMARY KEY (lb_id, ip),
                                UNIQUE (lb_id, fwmark));"
        );  
        foreach ($init_queries as $query)
                $db->exec($query);
}

function showtables()
{
        global $db;
        $res = $db->prepare('SHOW TABLES');
        $res->execute();
        while ($row = $res->fetch(PDO::FETCH_ASSOC))
                var_dump($row);
}

class TestPDOException extends Exception
{
        private $sqlstate;
        function __construct($error_info)
        {   
                list($sqlstate, $code, $message) = $error_info;
                $this->sqlstate = $sqlstate;
                parent::__construct($message, $code);
        }   
        function getSqlState()
        {   
                return $this->sqlstate;
        }   
}

function mark($lb_id, $ip)
{
        global $db, $table_name;
        $offset = 1000;
        $new_mark = 0;
        while (1) {
        $db->beginTransaction();
        try 
        {   
                // Check if there is value for ip already
                $res = $db->prepare("SELECT fwmark FROM ${table_name} WHERE lb_id = ${lb_id} AND ip = ${ip}");
                $res->execute();
                if ($row = $res->fetch(PDO::FETCH_ASSOC))
                {   
                        $new_mark = $row['fwmark'];
                        $db->commit();
                        break;
                }   

                // Fastpath
                $res = $db->prepare("SELECT COUNT(*) as count, MAX(fwmark) as max_mark FROM ${table_name} WHERE lb_id = $lb_id");
                $res->execute();
                $stat = $res->fetch(PDO::FETCH_ASSOC);
                if (
                        // fwmark range is continuous - grab next one
                        ((int) $stat['count'] - 1) === ((int) $stat['max_mark'] - $offset) ||  
                        // no fwmarks for this balancer
                        ((int) $stat['count'] === 0)
                )   
                {   
                        $new_mark = $offset;
                        if ($stat['max_mark'] !== NULL)
                                $new_mark = (int) $stat['max_mark'] + 1;
                }   
                // Slowpath search for holes int fwmark range
                else
                {   
                        $res = $db->prepare("SELECT fwmark FROM ${table_name} WHERE lb_id = ${lb_id} ORDER BY fwmark");
                        $res->execute();
                        for ($i = $offset; ; $i++)
                        {   
                                $row = $res->fetch(PDO::FETCH_ASSOC);
                                if (! $row)
                                        throw new TestPDOException($result->errorInfo());

                                $used_mark = (int) $row['fwmark'];
                                if ($i !== $used_mark)
                                {   
                                        $new_mark = $i; 
                                        break;
                                }   
                        }   
    
                }   

                if ($new_mark === 0)
                        throw new Exception('Mark Not Found');

                $result = $db->prepare("INSERT INTO ${table_name} VALUES (:lb_id, :ip, :fwmark)");
                $ret = $result->execute(array(
                                        ":lb_id" => $lb_id,
                                        ":ip" => $ip,
                                        ":fwmark" => $new_mark
                                )   
                );  
    
                if (!$ret)
                        throw new TestPDOException($result->errorInfo());

                $db->commit();
        }   
        catch (TestPDOException $e) 
        {   
                $db->rollBack();

                if  
                (   
                        // There is value for ip already or
                        // value was deleted since insert will try again
                        $e->getSqlState() === '23000' ||
                        // Restart query - insert lock
                        $e->getSqlState() === '40001'
                )   
                                continue;

                // Something unexpected happened        
                throw $e; 
        }   
        catch (Exception $e) 
        {   
                $db->rollBack();
                throw $e; 
        }   
        } //while (1)
        return $new_mark;
}

function delete_mark($lb_id, $ip, $fwmark)
{
        global $db, $table_name;
        $res = $db->prepare("DELETE FROM ${table_name} WHERE
                        lb_id = ${lb_id} AND
                        ip = ${ip} AND
                        fwmark = ${fwmark}");
        if (!$res->execute())
                throw new TestPDOException($res->errorInfo());

        if ($res->rowCount() !== 1)
                throw new Exception("delete_mark for (${lb_id}, ${ip}, ${fwmark}) failed to delete anything");
}

if ($argv[1] === '-i')
{
	init();
	exit(0);
}

if (count($argv) < 3)
{
        echo "Usage ${argv[0]} <start> <end>";
        exit(1);
}

$start = (int) $argv[1];
$end = (int) $argv[2];

$db = new PDO("mysql:dbname=${db_name}");
for ($i = $start; $i < $end; $i++)
        mark(1,$i);

