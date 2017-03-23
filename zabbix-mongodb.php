<?php

require 'vendor/autoload.php';

error_reporting(E_ALL) ;

$options = getopt("Dh:p:z:u:x:H:P:", array("ssl")) ;
$scriptName = basename($argv[0]) ;
$scriptVersion = "0.9" ;

// Get data collection start time (we will use this to compute the total data collection time)
$start_time = time() ;

// At a minimum, we need to get the Zabbix hostname. If not, display usage message.
if (empty($options) or empty($options['z']) ) {
    echo "
$scriptName Version $scriptVersion
Usage : $scriptName [-D] [-h <mongoDB Server Host>] [-p <mongoDB Port>] [--ssl] [-u <username>] [-x <password>] [-H <Zabbix Server ip/hostname>] [-P <Zabbix Server Port>] -z <Zabbix_Name>
where
   -D    = Run in detail/debug mode
   -h    = Hostname or IP address of server running MongoDB
   -p    = Port number on which to connect to the mongod or mongos process
   -z    = Name (hostname) of MongoDB instance or cluster in the Zabbix UI
   -u    = User name for database authentication
   -x    = Password for database authentication
   -H    = Zabbix server IP or hostname
   -P    = Zabbix server Port or hostname
   --ssl = Use SSL when connecting to MongoDB
"  ;

    exit ;
}

$isDebug = isset($options['D']) ;
if ($isDebug) {
    MongodbZabbix\Debug::writeToLog("version $scriptVersion");
}

$zabbixServer = new MongodbZabbix\ZabbixServer(
    $scriptName,
    $options['z'],
    ($options['H'] ? $options['H'] : '127.0.0.1'),
    ($options['P'] ? $options['P'] : '10051')
);

$dataCollector = new MongodbZabbix\DataCollector(
    $zabbixServer
);

$mongoHostname = ( empty($options['h']) ? MongoClient::DEFAULT_HOST : $options['h'] );
$mongoDbClient = MongodbZabbix\MongoConnection::connect(
    $mongoHostname,
    ( empty($options['p']) ? MongoClient::DEFAULT_PORT : $options['p'] ),
    ( array_key_exists('u', $options) ? $options['u'] : null ),
    ( array_key_exists('x', $options) ? $options['x'] : null ),
    isset($options['ssl'])
);

$mongoMonitorer = new MongodbZabbix\MongoMonitorer(
    $mongoHostname, $dataCollector, $mongoDbClient
);

// Fetching all server status.
$serverStatus = $mongoMonitorer->getServerStatus();

$mongoMonitorer->saveServerStatus($serverStatus);

// Get cumulative DB Info
$mongoMonitorer->getCumulativeDatabaseInfo();

if (array_key_exists('repl', $serverStatus)) {
    $this->getReplicaInfo();
}

// Get data collection end time (we will use this to compute the total data collection time)
$end_time = time() ;
$data_collection_time = $end_time - $start_time ;
$dataCollector->writeData( "plugin.data_collection_time", $data_collection_time) ;

$dataCollector->writeData( "plugin.version", $scriptVersion) ;
$dataCollector->writeData( "plugin.checksum", md5_file($argv[0])) ;

$zabbixServer->sendDataToZabbix($dataCollector, $isDebug);

exit ;