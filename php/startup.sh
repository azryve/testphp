#!/bin/bash

proc=$1
concurents=$2
size=$3

service mysql start
php /tmp/php/init.php
begin=`date +%s`
for p in `seq 1 $proc`; do
	for c in `seq 1 $concurents`; do
		php /tmp/php/test.php $((p * size)) $(((p+1) * size)) &
	done
	sleep $p && php /tmp/php/delete.php $size &
done
for p in `jobs -p`; do
	wait $p
done
finish=`date +%s`
printf "Running time: %ds\n" $((finish-begin))
php /tmp/php/stat.php
