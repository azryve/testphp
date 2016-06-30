<?php

require "database.php";

function mark($lb_id, $ip)
{
        global $db, $table_name;
        $offset = 1000;
        $new_mark = 0;
	$mutex_name = "check_mark_${lb_id}";
	// Check if there is value for ip already
	$res = usePreparedSelectBlade("SELECT fwmark FROM ${table_name}
					WHERE lb_id = ? AND ip = ?",
					array($lb_id, $ip)
	);
	if ($row = $res->fetch(PDO::FETCH_ASSOC))
		return $row['fwmark'];

	// Lock the range
	setDBMutex($mutex_name);
	// Fastpath: if range continious - just grab last one
	$res = usePreparedSelectBlade("SELECT COUNT(*) as count, MAX(fwmark) as max_mark FROM ${table_name} WHERE lb_id = ?", array($lb_id));
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
	// Slowpath: search for holes in fwmark range
	else
	{
		$res = usePreparedSelectBlade("SELECT fwmark FROM ${table_name} WHERE lb_id = ? ORDER BY fwmark", array($lb_id));
		for ($i = $offset; ; $i++)
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
	usePreparedInsertBlade($table_name, array ("lb_id" => $lb_id,
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

