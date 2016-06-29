#!/bin/bash

proc=$1
size=$2

service mysql start
php /tmp/php/test.php -i
#php /tmp/php/test.php 1 1000
for p in `seq 1 $proc`; do
	php /tmp/php/test.php $((p * size)) $(((p+1) * size)) &
done
for p in `jobs -p`; do
	wait $p
done
echo 'SELECT COUNT(*), MAX(fwmark) FROM testdb.table1;' | mysql
