<?php


namespace MongodbZabbix;


class Debug
{

    public static function writeToLog($outputLine)
    {
        fprintf(STDERR, "%s\n", $outputLine) ;
    }
}