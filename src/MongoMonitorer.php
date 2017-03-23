<?php


namespace MongodbZabbix;


use MongoDB\Client;

class MongoMonitorer
{
    /** @var  string */
    private $mongoHostname;
    /** @var  DataCollector */
    private $dataCollector;
    /** @var  Client */
    private $mongoDbClient;

    /**
     * MongoMonitorer constructor.
     * @param $mongoHostname
     * @param DataCollector $dataCollector
     * @param Client $mongoDbClient
     */
    public function __construct(
        $mongoHostname,
        DataCollector $dataCollector,
        Client $mongoDbClient
    )
    {
        $this->mongoHostname = $mongoHostname;
        $this->dataCollector = $dataCollector;
        $this->mongoDbClient = $mongoDbClient;
    }

    /**
     * @return array
     */
    public function getServerStatus()
    {
        //-----------------------------
        // Get server statistics
        //-----------------------------
        $mongoDatabaseHandle = $this->mongoDbClient->selectDatabase("admin");

        $cursor = $mongoDatabaseHandle->command(array('serverStatus' => 1));
        $cursor->setTypeMap(['root' => 'array', 'document' => 'array', 'array' => 'array']);
        $serverStatus = $cursor->toArray()[0];

        if (!isset($serverStatus['ok'])) {
            Debug::writeToLog("Error in executing serverStatus.");
            exit;
        }

        return $serverStatus;
    }

    public function saveServerStatus($serverStatus)
    {
        $this->dataCollector->writeData("version", $serverStatus['version']);
        $this->dataCollector->writeData("uptime", $serverStatus['uptime']);

        if ($serverStatus['globalLock']['totalTime'] != null) {
            $this->dataCollector->writeData("globalLock.totalTime", $serverStatus['globalLock']['totalTime']);
        }

        $this->dataCollector->writeData("globalLock.currentQueue.total", $serverStatus['globalLock']['currentQueue']['total']);
        $this->dataCollector->writeData("globalLock.currentQueue.readers", $serverStatus['globalLock']['currentQueue']['readers']);
        $this->dataCollector->writeData("globalLock.currentQueue.writers", $serverStatus['globalLock']['currentQueue']['writers']);

        $this->dataCollector->writeData("mem.bits", $serverStatus['mem']['bits']);
        $this->dataCollector->writeData("mem.resident", $serverStatus['mem']['resident']);
        $this->dataCollector->writeData("mem.virtual", $serverStatus['mem']['virtual']);

        $this->dataCollector->writeData("connections.current", $serverStatus['connections']['current']);
        $this->dataCollector->writeData("connections.available", $serverStatus['connections']['available']);

        $this->dataCollector->writeData("extra_info.heap_usage", round(($serverStatus['extra_info']['heap_usage_bytes']) / (1024 * 124), 2));
        $this->dataCollector->writeData("extra_info.page_faults", $serverStatus['extra_info']['page_faults']);

        $this->dataCollector->writeData("opcounters.insert", $serverStatus['opcounters']['insert']);
        $this->dataCollector->writeData("opcounters.query", $serverStatus['opcounters']['query']);
        $this->dataCollector->writeData("opcounters.update", $serverStatus['opcounters']['update']);
        $this->dataCollector->writeData("opcounters.delete", $serverStatus['opcounters']['delete']);
        $this->dataCollector->writeData("opcounters.getmore", $serverStatus['opcounters']['getmore']);
        $this->dataCollector->writeData("opcounters.command", $serverStatus['opcounters']['command']);

        $this->dataCollector->writeData("asserts.regular", $serverStatus['asserts']['regular']);
        $this->dataCollector->writeData("asserts.warning", $serverStatus['asserts']['warning']);
        $this->dataCollector->writeData("asserts.msg", $serverStatus['asserts']['msg']);
        $this->dataCollector->writeData("asserts.user", $serverStatus['asserts']['user']);
        $this->dataCollector->writeData("asserts.rollovers", $serverStatus['asserts']['rollovers']);

        $this->dataCollector->writeData("network.inbound.traffic_mb", ($serverStatus['network']['bytesIn']) / (1024 * 1024));
        $this->dataCollector->writeData("network.outbound.traffic_mb", ($serverStatus['network']['bytesOut']) / (1024 * 1024));
        $this->dataCollector->writeData("network.requests", $serverStatus['network']['numRequests']);

        $this->dataCollector->writeData("write_backs_queued", ($serverStatus['writeBacksQueued'] ? "Yes" : "No"));
    }

    public function getCumulativeDatabaseInfo()
    {
        //-----------------------------
        // Get DB list and cumulative DB info
        //-----------------------------
        $databaseInfoIterator = $this->mongoDbClient->listDatabases();

        $this->dataCollector->writeData("db.count", iterator_count($databaseInfoIterator));

        $total_collection_count = 0;
        $total_object_count = 0;
        $total_index_count = 0;
        $total_index_size = 0.0;
        $totalDataSize = 0;

        $isSharded = false;

        $db_info_array = array();
        $dbInfoCollections = array();
        $dbInfoObjects = array();
        $dbInfoIndexes = array();
        $dbInfoAvgObjSize = array();
        $dbInfoDataSize = array();
        $dbInfoIndexSize = array();
        $dbInfoStorageSize = array();
        $dbInfoNumExtentsArray = array();

        foreach ($databaseInfoIterator as $databaseInfo) {
            if (isset($databaseInfo->__debugInfo()['shards'])) {
                $isSharded = true;
            }

            $mongoDatabaseHandle = $this->mongoDbClient->selectDatabase($databaseInfo->getName());
            $cursor = $mongoDatabaseHandle->command(array('dbStats' => 1));
            $cursor->setTypeMap(['root' => 'array', 'document' => 'array', 'array' => 'array']);
            $dbStats = $cursor->toArray()[0];
            $execute_status = $dbStats['ok'];
            if ($execute_status == 0) {
                Debug::writeToLog("Error in executing dbStats for database " . $databaseInfo->getName());
                exit;
            }

            $total_collection_count += $dbStats['collections'];
            $total_object_count += $dbStats['objects'];
            $total_index_count += $dbStats['indexes'];
            $total_index_size += $dbStats['indexSize'];
            $totalDataSize += $dbStats['dataSize'];

            $db_info_array[] = array("{#DBNAME}" => $databaseInfo->getName());
            $dbInfoCollections[$databaseInfo->getName()] = $dbStats['collections'];
            $dbInfoObjects[$databaseInfo->getName()] = $dbStats['objects'];
            $dbInfoIndexes[$databaseInfo->getName()] = $dbStats['indexes'];
            $dbInfoAvgObjSize[$databaseInfo->getName()] = $dbStats['avgObjSize'];
            $dbInfoDataSize[$databaseInfo->getName()] = $dbStats['dataSize'];
            $dbInfoIndexSize[$databaseInfo->getName()] = $dbStats['indexSize'];
            $dbInfoStorageSize[$databaseInfo->getName()] = $dbStats['storageSize'];
            $dbInfoNumExtentsArray[$databaseInfo->getName()] = $dbStats['numExtents'];
        }

        $this->dataCollector->writeData("total.size", round(($totalDataSize) / (1024 * 1024), 2));
        $this->dataCollector->writeData("db.discovery", str_replace("\"", "\\\"", json_encode(array("data" => $db_info_array))));
        $this->dataCollector->writeData("is_sharded", $isSharded ? "Yes" : "No");
        $this->dataCollector->writeData("total.collection.count", $total_collection_count);
        $this->dataCollector->writeData("total.object.count", $total_object_count);
        $this->dataCollector->writeData("total.index.count", $total_index_count);

        $total_index_size = round($total_index_size / (1024 * 1024), 2);
        $this->dataCollector->writeData("total.index.size", $total_index_size);

        foreach ($dbInfoCollections as $name => $dummy) {
            $this->dataCollector->writeData("db.collections[" . $name . "]", $dbInfoCollections[$name]);
            $this->dataCollector->writeData("db.objects[" . $name . "]", $dbInfoObjects[$name]);
            $this->dataCollector->writeData("db.indexes[" . $name . "]", $dbInfoIndexes[$name]);
            $this->dataCollector->writeData("db.avgObjSize[" . $name . "]", $dbInfoAvgObjSize[$name]);
            $this->dataCollector->writeData("db.dataSize[" . $name . "]", $dbInfoDataSize[$name]);
            $this->dataCollector->writeData("db.indexSize[" . $name . "]", $dbInfoIndexSize[$name]);
            $this->dataCollector->writeData("db.storageSize[" . $name . "]", $dbInfoStorageSize[$name]);
            $this->dataCollector->writeData("db.numExtents[" . $name . "]", $dbInfoNumExtentsArray[$name]);
        }


        if ($isSharded) {
            $this->getShardInfo();
        }
    }

    public function getReplicaInfo()
    {
        $mongo_db_handle = $this->mongoDbClient->selectDatabase('admin');
        $cursor = $mongo_db_handle->command(array('replSetGetStatus' => 1));
        $cursor->setTypeMap(['root' => 'array', 'document' => 'array', 'array' => 'array']);
        $rs_status = $cursor->toArray()[0];

        if (!($rs_status['ok'])) {
            $this->dataCollector->writeData("is_replica_set", "No");
        } else {
            $this->dataCollector->writeData("is_replica_set", "Yes");
            $this->dataCollector->writeData("replica_set_name", $rs_status['set']);
            $this->dataCollector->writeData("replica_set_member_count", count($rs_status['members']));

            $repl_set_member_names = '';
            foreach ($rs_status['members'] as $repl_set_member) {
                $repl_set_member_names .= 'host#' . $repl_set_member['_id'] . ' = ' . $repl_set_member['name'] . ' || ';
            }
            $this->dataCollector->writeData("replica_set_hosts", $repl_set_member_names);

            $local_mongo_db_handle = $this->mongoDbClient->selectDatabase('local');
            $col_name = 'oplog.rs';
            $mongo_collection = $local_mongo_db_handle->$col_name;
            $oplog_rs_count = $mongo_collection->count();
            $this->dataCollector->writeData("oplog.rs_count", $oplog_rs_count);

            $repl_member_attention_state_count = 0;
            $repl_member_attention_state_info = '';

            foreach ($rs_status['members'] as $member) {
                $member_state = $member['state'];

                $host = explode(':', $member['name']);
                $hostname = $host[0];

                $master_optime = ($member_state == 1) ? $member['optime'] : 0;

                $fqdn = explode('.', $this->mongoHostname);
                $mongodb_host_simple = $fqdn[0];

                if (!in_array($hostname, array($mongodb_host_simple, $this->mongoHostname))) {
                    continue;
                }

                $mongo_host_optime = $member['optime'];
                $seconds = $master_optime->sec - $mongo_host_optime->sec;

                if ($seconds < 0) {
                    $seconds = 0;
                }

                $this->dataCollector->writeData("repl_member_replication_lag_sec", $seconds);

                if ($member_state == 0 or $member_state == 3 or $member_state == 4 or $member_state == 5 or $member_state == 6 or $member_state == 8) {
                    // 0 = Starting up, phase 1
                    // 1 = primary
                    // 2 = secondary
                    // 3 = recovering
                    // 4 = fatal error
                    // 5 = starting up, phase 2
                    // 6 = unknown state
                    // 7 = arbiter
                    // 8 = down
                    $repl_member_attention_state_count++;
                    switch ($member_state) {
                        case 0:
                            $member_state = 'starting up, phase 1';
                            break;
                        case 3:
                            $member_state = 'recovering';
                            break;
                        case 4:
                            $member_state = 'fatal error';
                            break;
                        case 5:
                            $member_state = 'starting up, phase 2';
                            break;
                        case 6:
                            $member_state = 'unknown';
                            break;
                        case 8:
                            $member_state = 'down';
                            break;
                        default:
                            $member_state = 'unknown';
                            break;
                    }
                    $repl_member_attention_state_info .= $member['name'] . ' is in state ' . $member_state . ' ||';
                }
            }
            $this->dataCollector->writeData("repl_member_attention_state_count", $repl_member_attention_state_count);
            $this->dataCollector->writeData("repl_member_attention_state_info", ($repl_member_attention_state_count > 0 ? $repl_member_attention_state_info : 'empty'));
        }
    }

    private function getShardInfo()
    {
        $mongo_db_handle = $this->mongoDbClient->selectDatabase('config');

        $mongo_collection = $mongo_db_handle->chunks;
        $shard_info = $mongo_collection->count();
        $this->dataCollector->writeData("shard_chunk_count", $shard_info);

        $mongo_collection = $mongo_db_handle->collections;
        $shard_info = $mongo_collection->count();
        $this->dataCollector->writeData("sharded_collections_count", $shard_info);

        $collection = $this->mongoDbClient->selectDatabase('config')->selectCollection('collections');
        $cursor = $collection->find();
        $collection_array = iterator_to_array($cursor);
        $collection_info = '';
        foreach ($collection_array as $shard) {
            $collection_info .= $shard['_id'] . ' || ';
        }
        $this->dataCollector->writeData("sharded_collection_info", $collection_info);

        $mongo_collection = $mongo_db_handle->shards;
        $shard_info = $mongo_collection->count();
        $this->dataCollector->writeData("shard_count", $shard_info);

        $collection = $this->mongoDbClient->selectDatabase('config')->selectCollection('shards');
        $cursor = $collection->find();
        $shards_array = iterator_to_array($cursor);
        $shard_info = '';
        foreach ($shards_array as $shard) {
            $shard_info .= $shard['_id'] . ' = ' . $shard['host'] . ' || ';
        }
        $this->dataCollector->writeData("shard_info", $shard_info);

        $collection = $this->mongoDbClient->selectDatabase('config')->selectCollection('databases');
        $cursor = $collection->find();
        $db_array = iterator_to_array($cursor);
        $db_info = '';
        foreach ($db_array as $db) {
            if ($db['partitioned']) {
                $partitioned = 'yes';
            } else {
                $partitioned = 'no';
            }
            $db_info .= $db['_id'] . ' : ' . 'partitioned = ' . $partitioned . ', primary = ' . $db['primary'] . ' || ';
        }
        $this->dataCollector->writeData("db_info", $db_info);
    }
}