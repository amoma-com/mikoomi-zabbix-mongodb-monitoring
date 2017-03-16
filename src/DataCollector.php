<?php


namespace MongodbZabbix;


class DataCollector
{
    private $dataLines;
    private $zabbixName;

    public function __construct(
        ZabbixServer $zabbixServer
    )
    {
        $this->dataLines = array();
        $this->zabbixName = $zabbixServer->getZabbixName();
    }

    public function writeData($key, $value)
    {
        // Only if we have a value do we want to record this metric
        if(isset($value) && $value !== '')
        {
            $data_line = sprintf("\"%s\" \"mongodb.%s\" \"%s\"", $this->zabbixName, $key, $value) ;
            $this->dataLines[] = $data_line ;
        }
    }

    /**
     * @return array
     */
    public function getDataLines()
    {
        return $this->dataLines;
    }
}