<?php

/**
 * Description of Packet
 *
 * @author Sinthetix
 */
class Packet {
    public $type;
    public $type2;
    public $type2Hex;
    //public $length;
    public $data;
   
    function __construct($type, $type2, $type2Hex,  $createData = false) {        
        $this->data = $createData."\x0a\x0";
        $this->type = $type;
        $this->type2 = $type2;        
        $this->type2Hex = $type2Hex;        
    }
    
    public function getPacketVars(){
        $data = $this->data;
        $data = explode("\n", $data);
        $vars = array();
        foreach ($data as $line) {
            $ln = explode('=', $line);
            if (isset($ln[1])) {
                $vars[$ln[0]] = $ln[1];
            }
        }
        return $vars;
    }
    
    
    

}
