#!/bin/bash
PATH=$PATH:/etc/zabbix/externalscripts:/opt/zabbix/externalscripts:/opt/zabbix/bin:/home/zabbix/bin:/bin:/usr/sbin:/usr/bin:/usr/local/bin:/usr/local/sbin
export PATH

PHP=`which php`

BASE_DIR="`dirname $0`"
echo "$PHP $BASE_DIR/zabbix-mongodb.php $*"
$PHP $BASE_DIR/zabbix-mongodb.php $*
echo 0
