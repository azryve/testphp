<?php

require "config.php";
require "exeption.php";

global $db_name;
global $db;
$db = new PDO("mysql:dbname=${db_name}");

function usePreparedSelectBlade ($query, $args = array())
{
	global $db;
	try
	{
		$prepared = $db->prepare ($query);
		$prepared->execute ($args);
		return $prepared;
	}
	catch (PDOException $e)
	{
		throw convertPDOException ($e);
	}
}

function usePreparedInsertBlade ($tablename, $columns)
{
	global $db;
	$query = "INSERT INTO ${tablename} (" . implode (', ', array_keys ($columns));
	$query .= ') VALUES (' . questionMarks (count ($columns)) . ')';
	// Now the query should be as follows:
	// INSERT INTO table (c1, c2, c3) VALUES (?, ?, ?)
	try
	{
		$prepared = $db->prepare ($query);
		$prepared->execute (array_values ($columns));
		return $prepared->rowCount();
	}
	catch (PDOException $e)
	{
		throw convertPDOException ($e);
	}
}

function convertPDOException ($e)
{
	switch ($e->getCode() . '-' . $e->errorInfo[1])
	{
	case '23000-1062':
	case '23000-1205':
		$text = 'such record already exists';
		break;
	case '23000-1451':
	case '23000-1452':
		$text = 'foreign key violation';
		break;
	case 'HY000-1205':
		$text = 'lock wait timeout';
		break;
	default:
		return $e;
	}
	return new TestException ($text);
}

function getDBName()
{
	global $db_name;
	return $db_name;
}

// return a "?, ?, ?, ... ?, ?" string consisting of N question marks
function questionMarks ($count = 0)
{
        if ($count <= 0)
                throw new InvalidArgException ('count', $count, 'must be greater than zero');
        return implode (', ', array_fill (0, $count, '?'));
}

function setDBMutex ($name, $timeout = 5)
{
	$fullname = getDBName() . '.' . $name;
	$result = usePreparedSelectBlade ('SELECT GET_LOCK(?, ?)', array ($fullname, $timeout));
	$row = $result->fetch (PDO::FETCH_COLUMN, 0);
	if ($row === NULL)
		throw new TestException ("error occured when executing GET_LOCK on $fullname");
	if ($row !== '1')
		throw new TestException ("lock wait timeout for $fullname");
	return TRUE;
}

function tryDBMutex ($name, $timeout = 0)
{
	try
	{
		return setDBMutex ($name, $timeout);
	}
	catch (RTDatabaseError $e)
	{
		return FALSE;
	}
}

function releaseDBMutex ($name)
{
	$result = usePreparedSelectBlade ('SELECT RELEASE_LOCK(?)', array (getDBName() . '.' . $name));
	$row = $result->fetch (PDO::FETCH_COLUMN, 0);
	return $row === '1';
}
