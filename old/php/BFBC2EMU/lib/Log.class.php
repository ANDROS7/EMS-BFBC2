<?php

/**
 * Description of Log
 *
 * @author Sinthetix
 */
class Log {    
    public static $filename;
    public static $fh;
    
    public static function logDebug($mes, $force = false){
        global $logToConsole, $logToFile;
        if($logToConsole || $force){
            echo $mes."\n";
        }
        
        if($logToFile || $force){
            fwrite(self::$fh, $mes."\n\n");
        }
    }
    
    public static function createLogFile($filename){
        self::$filename = $filename;
        self::$fh = fopen($filename, "a");        
    }
    
    function __destruct() {
        fclose(self::$fh);
    }
    
}

