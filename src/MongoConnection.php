<?php


namespace MongodbZabbix;


use MongoDB\Client;

class MongoConnection
{
    /**
     * @param string $mongoHost
     * @param string $mongoPort
     * @param string|null $mongoUsername
     * @param string|null $mongoPassword
     * @param bool $isSsl
     * @return Client
     */
    public static function connect(
        $mongoHost,
        $mongoPort,
        $mongoUsername = null,
        $mongoPassword = null,
        $isSsl
    )
    {
        if (!is_null($mongoUsername) && !is_null($mongoPassword)) {
            $connect_string = $mongoUsername . ':' . $mongoPassword . '@' . $mongoHost . ':' . $mongoPort  ;
        }
        else {
            $connect_string = $mongoHost . ':' . $mongoPort ;
        }

        if ($isSsl) {
          $connect_string .= "/?ssl=true" ;
        }
        $mongoClient = new Client("mongodb://$connect_string") ;

        if (is_null($mongoClient)) {
            Debug::writeToLog("Error in connection to mongoDB using connect string $connect_string") ;
            exit ;
        }
        else {
            Debug::writeToLog("Successfully connected to mongoDB using connect string $connect_string") ;
        }

        return $mongoClient;
    }
}