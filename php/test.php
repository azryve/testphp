<?php

require "database.php";

define ('CHECK_MARK_START', 1000);


function mark($lb_id, $ip)
{
        $new_mark = 0;
	$mutex_name = "check_mark_${lb_id}";
	setDBMutex($mutex_name);
	// Check if there is value for ip already
	$res = usePreparedSelectBlade('SELECT fwmark FROM SLB_RSMarks WHERE lb_id = ? AND ip = ?', array($lb_id, $ip));
	if ($row = $res->fetch(PDO::FETCH_ASSOC))
	{
		releaseDBMutex($mutex_name);
		return $row['fwmark'];
	}

	$res = usePreparedSelectBlade('SELECT fwmark FROM SLB_RSMarks WHERE lb_id = ? ORDER BY fwmark', array($lb_id));
	for ($i = CHECK_MARK_START; ;$i++)
	{
		if
		(
			($row = $res->fetch(PDO::FETCH_ASSOC)) &&
			($i === intval($row['fwmark']))
		)
			continue;

		$new_mark = $i;
		break;
	}

	usePreparedInsertBlade('SLB_RSMarks', array (
						"lb_id" => $lb_id,
						"ip" => $ip,
						"fwmark" => $new_mark)
	);
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

