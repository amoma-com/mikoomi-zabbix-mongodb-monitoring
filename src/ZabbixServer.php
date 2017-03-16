<?php


namespace MongodbZabbix;


class ZabbixServer
{
    /** @var  string */
    private $scriptName;
    /** @var  string */
    private $zabbixName;
    /** @var  string */
    private $zabbixServer;
    /** @var  string */
    private $zabbixServerPort;

    /**
     * ZabbixServer constructor.
     * @param string $scriptName
     * @param string $zabbixName
     * @param string $zabbixServer
     * @param string $zabbixServerPort
     */
    public function __construct($scriptName, $zabbixName, $zabbixServer, $zabbixServerPort)
    {
        $this->scriptName = $scriptName;
        $this->zabbixName = $zabbixName;
        $this->zabbixServer = $zabbixServer;
        $this->zabbixServerPort = $zabbixServerPort;
    }

    /**
     * @return string
     */
    public function getZabbixName()
    {
        return $this->zabbixName;
    }

    /**
     * @return string
     */
    public function getFileBaseName()
    {
        // Remove spaces from zabbix name for file data and log file creation
        return str_replace(' ', '_', $this->zabbixName);
    }

    /**
     * @return string
     */
    public function getZabbixServer()
    {
        return $this->zabbixServer;
    }

    /**
     * @return string
     */
    public function getZabbixServerPort()
    {
        return $this->zabbixServerPort;
    }

    public function sendDataToZabbix(
        DataCollector $dataCollector,
        $isDebug
    )
    {
        // For DEBUG
        if ($isDebug) {
            $data_file_name = "/tmp/" . $this->scriptName . "_" . $this->getFileBaseName() . ".data" ;
            file_put_contents($data_file_name, implode("\n", $dataCollector->getDataLines()) . "\n") ;
            Debug::writeToLog(implode("\n", $dataCollector->getDataLines()));
        }

        $descriptorSpec = array(
            0 => array("pipe", "r"),  // stdin
            1 => array("pipe", "w"),  // stdout
            2 => array("pipe", "w")   // stderr
        ) ;
        $process = proc_open("zabbix_sender -vv -z " . $this->zabbixServer . " -p  " . $this->zabbixServerPort . " -i - 2>&1", $descriptorSpec, $pipes) ;

        if (is_resource($process)) {
            fwrite($pipes[0], implode("\n", $dataCollector->getDataLines())) ;
            fclose($pipes[0]) ;

            while($s = fgets($pipes[1], 1024)) {
                Debug::writeToLog("O: " . trim($s)) ;
            }
            fclose($pipes[1]);

            while($s= fgets($pipes[2], 1024)) {
                Debug::writeToLog("E: " . trim($s)) ;
            }
            fclose($pipes[2]) ;
        }
    }
}