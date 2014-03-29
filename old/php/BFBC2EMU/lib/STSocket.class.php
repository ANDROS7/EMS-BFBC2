<?php

//require_once('UserData.class.php');

/**
 * Description of Socket
 *
 * @author Sinthetix
 */
class STSocket {

    public static $_instance = array();
    public static $_instanceByGID = array();
    public $socket;
    public $ugid;
    public $gid;
    public $closed = false;
    public $name;
    public $ip;
    public $port;
    public $userData;
    public $type;
    public $type2;
    public $type2Hex;
    public $txn;
    public $vars;
    public $packetCounter = 0;
    public $faults = 0;
    public $tempPacket = null;
    public $tempPacketTid = 0;

    function __construct() {
        //$this->userData = new UserData;
    }

    public static function getInstanceByServer($server) {

        $newSock = stream_socket_accept($server);
        //$newSock = socket_accept($server);
        if (!$newSock) {
            return false;
        }
        $name = stream_socket_get_name($newSock, true);
        $parts = explode(':', $name);

        Log::logDebug("[STSocket] connection accepted " . $name);

        $instance = new STSocket();
        $instance->socket = $newSock;
        $instance->name = $name;
        $instance->ip = $parts[0];
        $instance->port = $parts[1];
        self::$_instance[$name] = $instance;
        return $instance;
    }

    public static function getInstanceByPeer($peer, $socket) {

        $parts = explode(':', $peer);

        $instance = new STSocket();
        $instance->socket = $socket;
        $instance->name = $peer;
        $instance->ip = $parts[0];
        $instance->port = $parts[1];
        self::$_instance[$peer] = $instance;
        return $instance;
    }

    public static function getInstanceBySocket($socket) {
        $name = stream_socket_get_name($socket, true);
        if (isset(self::$_instance[$name])) {
            return self::$_instance[$name];
        }
        return false;
    }

    public static function getInstanceByGID($gid, $name = false) {

        if (isset(self::$_instanceByGID[$gid])) {
            return self::$_instanceByGID[$gid];
        }
        if (isset(self::$_instance[$name])) {
            $instance = self::$_instance[$name];
            self::$_instanceByGID[$gid] = $instance;
            return $instance;
        }
        return false;
    }
    
    public function unsetInstance(){
        if(!empty($this->gid)){
            unset(self::$_instanceByGID[$this->gid]);
        }
        if(!empty($this->name)){
            unset(self::$_instance[$this->name]);
        }
    }

    public function receivePacket() {   
        if(!$this->checkValidResource()){
            return;
        }
        $header = @fread($this->socket, 12);
        if (empty($header)) {
            $this->faults++;
            if ($this->faults > 10) {
                $this->closed = true;
            }
            return;
        }
        $this->faults = 0;
       
        $type = substr($header, 0, 4);
        $this->type = $type;
        $this->type2Hex = Util::Decode(substr($header, 4), 4);
        $this->type2 = sprintf("%u", hexdec($this->type2Hex) & 0xffffffff);

        $length = Util::Decode(substr($header, 8), 4);
        $length = sprintf("%u", hexdec($length) & 0xffffffff);
        if($length < 13 || $length > 8192){
            return;
        }
        $data = fread($this->socket, $length - 12);
        if(empty($data)){
            return;
        }

        //Log::logDebug(sprintf("Recieve '%s' Packet:'%s' %s", $this->type, $this->type2Hex, $length));
        
        $receivedPacked = new Packet($this->type, $this->type2, null, $data);
        $vars = $receivedPacked->getPacketVars();
        $txn = isset($vars['TXN']) ? $vars['TXN'] : false;
      
        $this->txn = $txn;
        $this->vars = $vars;
//        $varsArray = "VARS:\n";
//        foreach ($vars as $key => $var) {
//            $varsArray.=$key . ' = ' . $var . "\n";
//        }
//        Log::logDebug("\n\n***** STSocket " . $txn . " *****\n\n");
//        Log::logDebug("\n\n***** STSocket " . $varsArray . " *****\n\n");

        if ($type == "CONN") {
            Log::logDebug("[STSocket] Handling CONN");
            $sendPacket = "TID=" . $this->vars['TID'] .
                    "\nTIME=" . time() .
                    "\nactivityTimeoutSecs=240";
            "\nPROT=2";
            $packet = new Packet($this->type, 0x00000000, null, $sendPacket);
            $this->sendPacket($packet);
            unset($packet);
        } else if ($type == "USER") {
            Log::logDebug("[STSocket] Handling USER");
            $result = dbQuery(sprintf("SELECT `user_id`, `persona_id`, `persona_name`, `email` FROM `personas` WHERE `persona_lkey`='%s'", $this->vars['LKEY']));
            if (!empty($result) && mysql_num_rows($result)) {
                $row = mysql_fetch_array($result);
                $this->userData = UserData::getInstance($row['email'], true);
                $this->userData->user_id = $row['user_id'];
                $this->userData->persona_id = $row['persona_id'];
                $this->userData->persona_name = $row['persona_name'];
                $this->userData->persona_lkey = $this->vars['LKEY'];

                $sendPacket = "NAME=" . $this->userData->persona_name .
                        "\nTID=" . $this->vars['TID'];

                $packet = new Packet($this->type, 0x00000000, null, $sendPacket);
                $this->sendPacket($packet);
                unset($packet);
            }
        } else if ($type == "CGAM") {
            Log::logDebug("[STSocket] Handling CGAM ServerIP: " . $this->ip);

            $sendPacket = "TID=" . $this->vars['TID'];
            $this->ugid = $this->vars['UGID'];
            $result = dbQuery(sprintf("SELECT `game_id`, `lobby_id`, `game_j`, `game_jp`, `game_mp` FROM `games` WHERE `game_i`='%s' AND `game_p`='%s'", $this->ip, $this->vars['PORT']));
            if (!mysql_num_rows($result)) {
                global $gameVersion;
                Log::logDebug("[STSocket] Handling CGAM - No Servers found");
                $result = dbQuery(sprintf("INSERT INTO `games` (`lobby_id`, `game_hn`, `game_hu`, `game_n`, `game_i`, `game_p`, `game_j`, `game_v`, `game_jp`, `game_qp`, `game_ap`, `game_mp`, `game_f`, `game_nf`, `game_pl`, `game_pw`, `game_hardcore`, `game_hasPassword`, `game_punkbuster`, `game_level`, `game_sguid`, `game_time`, `game_hash`, `game_region`, `game_public`, `B-U-EA`, `B-U-Provider`, `B-U-QueueLength`, `B-U-Softcore`, `B-U-gameMod`, `B-U-gamemode`, `game_elo`, `game_version`, `game_numObservers`, `game_maxObservers`, `server_online`, `B-U-PunkBusterVersion`, `UGID`) VALUES ('257', 'bfbc2.server.p', '%s', '%s', '%s', '%s', 'O', '%s', '0', '0', '0', '%s', '0', '0', 'PC', '0', '%s', '%s', '%s', 'DefaultLevel', '%s', '0', '0', 'OC', '1', '0', '0', '0', '0', 'BC2', 'CONQUEST', '1000', '2.0', '0', '0', '1', '\"v1.826 | A1382 C2.277\"', '%s')", $this->userData->persona_id, $this->vars['NAME'], $this->ip, $this->vars['PORT'], /*$this->vars['B-version']*/ $gameVersion, $this->vars['MAX-PLAYERS'], $this->vars['B-U-Hardcore'], $this->vars['B-U-HasPassword'], $this->vars['B-U-Punkbuster'], $this->vars['SECRET'], $this->ugid));
                $result = dbQuery(sprintf("SELECT `game_id`, `lobby_id`, `game_j`, `game_jp`, `game_mp` FROM `games` WHERE `game_i`='%s' AND `game_p`='%s'", $this->ip, $this->vars['PORT']));
            }

            if (mysql_num_rows($result)) {
                $row = mysql_fetch_array($result);
                $this->gid = $row['game_id'];
                $sendPacket .= "\nMAX-PLAYERS=" . $row['game_mp'] .
                        "\nEKEY=AIBSgPFqRDg0TfdXW1zUGa4%3d" .
                        "\nUGID=" . $this->ugid .
                        "\nJOIN=" . $row['game_jp'] .
                        "\nLID=" . $row['lobby_id'] .
                        "\nSECRET=" . $this->vars['SECRET'] .
                        "\nJ=" . $row['game_j'] .
                        "\nGID=" . $this->gid;

                $result = dbQuery(sprintf("UPDATE `games` SET `server_online`='1', `UGID`='%s'  WHERE `game_id`='%s'", $this->ugid, $this->gid));
                $this->getInstanceByGID($this->gid, $this->name);
            }
            $packet = new Packet($this->type, 0x00000000, null, $sendPacket);
            $this->sendPacket($packet);
            unset($packet);
        } else if ($type == "UGAM") {
            Log::logDebug("[STSocket] Handling UGAM");
            global $ugamArray;
            $upArray = array();
            foreach ($this->vars as $key => $var) {
                if (isset($ugamArray[$key])) {
                    $upArray[] = "`" . $ugamArray[$key] . "` = '" . $this->vars[$key] . "'";
                }
            }
            $result = dbQuery("UPDATE `games` SET " . join(',', $upArray) . " WHERE `game_id`='" . $this->vars['GID'] . "'");
        } else if ($type == "UBRA") {
            Log::logDebug("[STSocket] Handling UBRA");
            $sendPacket = "TID=" . $this->vars['TID'];
            $packet = new Packet($this->type, 0x00000000, null, $sendPacket);
            $this->sendPacket($packet);
            unset($packet);
        } else if ($type == "UGDE") {
            Log::logDebug("[STSocket] Handling UGDE");
            global $ugdeArray;
            $result = dbQuery(sprintf("SELECT `GID` FROM `gdet` WHERE `GID`='%s'", $this->vars['GID']));
            if (!mysql_num_rows($result)) {
                Log::logDebug("[STSocket] Handling UGDE - GDET Data not found, inserting...");
                dbQuery(sprintf("INSERT INTO `gdet` (`LID`, `GID`, `AutoBalance`, `BannerUrl`, `Crosshair`, `FriendlyFire`, `KillCam`, `Minimap`, `MinimapSpotting`, `ServerDescription0`, `ServerDescriptionCount`, `ThirdPersonVehicleCameras`, `ThreeDSpotting`, `pdat00`, `pdat01`, `pdat02`, `pdat03`, `pdat04`, `pdat05`, `pdat06`, `pdat07`, `pdat08`, `pdat09`, `pdat10`, `pdat11`, `pdat12`, `pdat13`, `pdat14`, `pdat15`, `pdat16`, `pdat17`, `pdat18`, `pdat19`, `pdat20`, `pdat21`, `pdat22`, `pdat23`, `pdat24`, `pdat25`, `pdat26`, `pdat27`, `pdat28`, `pdat29`, `pdat30`, `pdat31`) VALUES ('%s', '%s', '1', '', '1', '0.0000', '1', '1', '1', '', '0', '1', '1', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0', '|0|0|0|0')", $this->vars['LID'], $this->vars['GID']));
                $result = dbQuery(sprintf("SELECT `GID` FROM `gdet` WHERE `GID`='%s'", $this->vars['GID']));
            }
            if (mysql_num_rows($result)) {
                $upArray = array();
                foreach ($this->vars as $key => $var) {
                    if (isset($ugdeArray[$key])) {
                        $upArray[] = "`" . $ugdeArray[$key] . "` = '" . $this->vars[$key] . "'";
                    }
                }
                $result = dbQuery("UPDATE `gdet` SET " . join(',', $upArray) . " WHERE `GID` = '" . $this->vars['GID'] . "'");

                //update players on server
                $result = dbQuery(sprintf("SELECT `pdat00`, `pdat01`, `pdat02`, `pdat03`, `pdat04`, `pdat05`, `pdat06`, `pdat07`, `pdat08`, `pdat09`, `pdat10`, `pdat11`, `pdat12`, `pdat13`, `pdat14`, `pdat15`, `pdat16`, `pdat17`, `pdat18`, `pdat19`, `pdat20`, `pdat21`, `pdat22`, `pdat23`, `pdat24`, `pdat25`, `pdat26`, `pdat27`, `pdat28`, `pdat29`, `pdat30`, `pdat31` FROM `gdet` WHERE `LID`='%s' AND `GID`='%s'", $this->vars['LID'], $this->vars['GID']));
                $players = 0;
                $row = mysql_fetch_array($result);
                for ($i = 0; $i < 32; $i++) {
                    $field = sprintf("pdat%s", str_pad($i, 2, "0", STR_PAD_LEFT));
                    if ($row[$field] != '|0|0|0|0') {
                        $players++;
                    }
                }
                Log::logDebug(sprintf("[STSocket] Server with id %s has %s players", $this->vars['GID'], $players));
                dbQuery(sprintf("UPDATE `games` SET `game_ap`='%s' WHERE `game_id`='%s'", $players, $this->vars['GID']));
            }
        } else if ($type == "EGRS") {
            Log::logDebug("[STSocket] Handling EGRS");
            $sendPacket = "TID=" . $this->vars['TID'];
            $packet = new Packet($this->type, 0x00000000, null, $sendPacket);
            $this->sendPacket($packet);
            unset($packet);
        } else if ($type == "PENT") {
            Log::logDebug("[STSocket] Handling PENT - Client accepted");
            $sendPacket = "TID=" . $this->vars['TID'] .
                    "\nPID=" . $this->vars['PID'];
            $packet = new Packet($this->type, 0x00000000, null, $sendPacket);
            $this->sendPacket($packet);
            unset($packet);
        } else if ($type == "PLVT") {
            Log::logDebug("[STSocket] Handling PLVT - player removed");
            $sendPacket = "PID=" . $this->vars['PID'] .
                    "\nLID=" . $this->vars['LID'] .
                    "\nGID=" . $this->vars['GID'];
            $packet = new Packet($this->type, 0x00000000, null, $sendPacket);
            $this->sendPacket($packet);
            unset($packet);
        } else {
            Log::logDebug("[STSocket] Could not handle type: " . $this->type);
        }

       
        unset($receivedPacked);
    }
    
    public function handleGoodbye(){
        dbQuery('UPDATE `games` SET `server_online` = 0 WHERE `game_id`= '.$this->gid);        
        $this->unsetInstance();
    }


    public function sendPacket($packet, $count = true, $first = true) {  
        //if(!$this->checkValidResource()){
        //    return;
        //}
        global $extendedLog;
        $packetNr = hexdec(substr($packet->type2Hex, 1));
        
        $packet->type2 = sprintf("%u", ($packet->type2 & 0xff000000) | $packetNr);
        $data = $packet->type;
        $data.= Util::Putxx($packet->type2, 4);
        $len = strlen($packet->data) + 12;
        $data.= Util::putxx($len, 4);
        $data.= $packet->data;
        fwrite($this->socket, $data);
        if($extendedLog){
            Log::logDebug("[STSocket] Send packet: " . $data);
        }
    }
    
    public function checkValidResource(){
        if(is_resource($this->socket)){
            return true;
        }
        $this->handleGoodbye();
        return false;
    }

  

}

