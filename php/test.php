<?php

require "database.php";

define ('CHECK_MARK_START', 1000);

function mark($lb_id, $ip)
{
        global $db;
        $new_mark = 0;
	$mutex_name = "check_mark_${lb_id}";
	// Check if there is value for ip already
	$res = usePreparedSelectBlade('SELECT fwmark FROM SLB_RSMarks WHERE lb_id = ? AND ip = ?', array($lb_id, $ip));
	if ($row = $res->fetch(PDO::FETCH_ASSOC))
		return $row['fwmark'];

	// Lock the range
	setDBMutex($mutex_name);
	// Fastpath: if range continious - just grab last one
	$res = usePreparedSelectBlade('SELECT COUNT(*) as ip_count, MAX(fwmark) as mark_max FROM SLB_RSMarks WHERE lb_id = ?', array($lb_id));
	$stat = $res->fetch(PDO::FETCH_ASSOC);
	$ip_count = intval($stat['ip_count']);
	$mark_max = intval($stat['mark_max']);
	if ($ip_count === $mark_max)
	{
		if ($mark_max === 0)
			$new_mark = CHECK_MARK_START;
		else
			$new_mark = $mark_max + 1;
	}
	// Slowpath: search for holes in fwmark range
	else
	{
		$res = usePreparedSelectBlade('SELECT fwmark FROM SLB_RSMarks WHERE lb_id = ? ORDER BY fwmark', array($lb_id));
		for ($i = CHECK_MARK_START; ;$i++)
		{
			$row = $res->fetch(PDO::FETCH_ASSOC);
			$used_mark = (int) $row['fwmark'];
			if ($i !== $used_mark)
			{
				$new_mark = $i;
				break;
			}
		}

	}
	usePreparedInsertBlade('SLB_RSMarks', array ("lb_id" => $lb_id,
						   "ip" => $ip,
						   "fwmark" => $new_mark));
	releaseDBMutex($mutex_name);

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

