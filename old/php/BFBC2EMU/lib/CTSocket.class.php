<?php

//require_once('UserData.class.php');

/**
 * Description of Socket
 *
 * @author Sinthetix
 */
class CTSocket {

    public static $_instance = array();
    public $socket;
    public $closed = false;
    public $name;
    public $ip;
    public $userData;
    public $type;
    public $type2;
    public $type2Hex;
    public $txn;
    public $vars;
    public $packetCounter = 0;
    public $faults = 0;

    function __construct() {
        //$this->userData = new UserData;
    }

    public static function getInstanceByServer($server) {

        $newSock = stream_socket_accept($server);
        if (!$newSock) {
            return false;
        }
        $name = stream_socket_get_name($newSock, true);
        $parts = explode(':', $name);

        Log::logDebug("[CTSocket] connection accepted " . $name);

        $instance = new CTSocket();
        $instance->socket = $newSock;
        $instance->name = $name;
        $instance->ip = $parts[0];
        self::$_instance[$name] = $instance;
        return $instance;
    }

    public static function getInstanceBySocket($socket) {
        $name = stream_socket_get_name($socket, true);
        if (isset(self::$_instance[$name])) {
            return self::$_instance[$name];
        }
        return false;
    }

    public static function unsetInstance($name) {
        unset(self::$_instance[$name]);
    }

    public function receivePacket() {
        global $extendedLog;
        if (!$this->checkValidResource()) {
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

        //Log::logDebug("Recv header: ".$header);


        $type = substr($header, 0, 4);
        $this->type = $type;
        $this->type2Hex = Util::Decode(substr($header, 4), 4);
        $this->type2 = sprintf("%u", hexdec($this->type2Hex) & 0xffffffff);

        $length = Util::Decode(substr($header, 8), 4);
        $length = sprintf("%u", hexdec($length) & 0xffffffff);
        if ($length < 13 || $length > 8192) {
            return;
        }
        $data = fread($this->socket, $length - 12);
        if (empty($data)) {
            return;
        }

        //Log::logDebug(sprintf("Recieve '%s' Packet:'%s' %s", $this->type, $this->type2Hex, $length));
        if ($extendedLog) {
            Log::logDebug("[CTSocket] Recv packet: " . $data);
        }

        $receivedPacked = new Packet($this->type, $this->type2, $this->type2Hex, $data);
        $vars = $receivedPacked->getPacketVars();
        $txn = isset($vars['TXN']) ? $vars['TXN'] : false;

        $this->txn = $txn;
        $this->vars = $vars;
//        $varsArray="VARS:\n";
//        foreach($vars as $key=>$var){
//            $varsArray.=$key.' = '.$var."\n";
//        }
//        if($varsArray > 1000){
//            $varsArray = substr($varsArray, 0,100);
//        }
//        Log::logDebug("\n\n***** CTSocket " . $txn . " *****\n\n");
//        Log::logDebug("\n\n***** CTSocket " . $varsArray . " *****\n\n");


        if ($type == "CONN") {
            Log::logDebug("[CTSocket] Handling CONN");
            $sendPacket = "TID=" . $this->vars['TID'] .
                    "\nTIME=" . time() .
                    "\nactivityTimeoutSecs=240";
            "\nPROT=2";
            $packet = new Packet($this->type, 0x00000000, null, $sendPacket);
            $this->sendPacket($packet);
            unset($packet);
        } else if ($type == "USER") {
            Log::logDebug("[CTSocket] Handling USER");
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
        } else if ($type == "LLST") {
            Log::logDebug("[CTSocket] Handling LLST");
            $result = dbQuery("SELECT `lobby_id`,`lobby_name`,`lobby_locale`,`lobby_num_games`,`lobby_max_games` FROM `lobbies`");
            $sendPacket = "TID=" . $this->vars['TID'] .
                    "\nNUM-LOBBIES=" . mysql_num_rows($result);
            $packet = new Packet($this->type, 0x00000000, null, $sendPacket);
            $this->sendPacket($packet);
            unset($packet);
            while ($row = mysql_fetch_array($result)) {
                $sendPacket = "TID=" . $this->vars['TID'] .
                        "\nLID=" . $row['lobby_id'] .
                        "\nPASSING=" . $row['lobby_num_games'] .
                        "\nNAME=" . $row['lobby_name'] .
                        "\nLOCALE=" . $row['lobby_locale'] .
                        "\nMAX-GAMES=" . $row['lobby_max_games'] .
                        "\nFAVORITE-GAMES=0" .
                        "\nFAVORITE-PLAYERS=0" .
                        "\nNUM-GAMES=" . $row['lobby_num_games'];
                $packet = new Packet('LDAT', 0x00000000, null, $sendPacket);
                $this->sendPacket($packet);
                unset($packet);
            }
        } else if ($type == "GLST") { //game list
            Log::logDebug("[CTSocket] Handling GLST");
            $gameModeStr = '';
            if (!empty($this->vars['FILTER-ATTR-U-gameMod'])) {
                $gameModeStr = "AND `B-U-gameMod` = '" . $this->vars['FILTER-ATTR-U-gameMod'] . "'";
            }
            $lresult = dbQuery(sprintf("SELECT `lobby_num_games`,`lobby_max_games` FROM `lobbies` WHERE `lobby_id`='%s'", $this->vars['LID']));

            $gid = isset($this->vars['GID']) ? $this->vars['GID'] : null;
            $count = intval($this->vars['COUNT']);
            if ($count <= 0) {
                $this->vars['COUNT'] = 0;
            }
            if (isset($gid)) {
                $result = dbQuery(sprintf("SELECT * FROM `games` WHERE `lobby_id`='%s' AND `server_online`='%s' AND `game_id`='%s' " . $gameModeStr . " LIMIT %s", $this->vars['LID'], 1, $gid, $this->vars['COUNT']));
            } else {
                $result = dbQuery(sprintf("SELECT * FROM `games` WHERE `lobby_id`='%s' AND `server_online`='%s' " . $gameModeStr . " LIMIT %s", $this->vars['LID'], 1, $this->vars['COUNT']));
            }

            $nrGames = 0;
            if (!empty($result)) {
                $nrGames = mysql_num_rows($result);
            }

            $lrow = mysql_fetch_array($lresult);

            $sendPacket = "TID=" . $this->vars['TID'] .
                    "\nLID=" . $this->vars['LID'] .
                    "\nLOBBY-NUM-GAMES=" . $lrow['lobby_num_games'] .
                    "\nLOBBY-MAX-GAMES=" . $lrow['lobby_max_games'] .
                    "\nNUM-GAMES=" . $nrGames;
            $packet = new Packet('GLST', 0x00000000, null, $sendPacket);
            $this->sendPacket($packet);
            unset($packet);

            while ($row = mysql_fetch_array($result)) {
                $sendPacket = "TID=" . $this->vars['TID'] .
                        "\nLID=" . $row['lobby_id'] . //id of lobby
                        "\nGID=" . $row['game_id'] . //game id
                        "\nTYPE=G" . //??
                        "\nHN=" . $row['game_hn'] . //account name of server (host name)
                        "\nHU=" . $row['game_hu'] . //account id of server (host user)
                        "\nN=" . $row['game_n'] . //name of server in list
                        //OMG
                        "\nI=" . $row['game_i'] . //IP - which one? (probably outside server ip)
                        "\nP=" . $row['game_p'] . //Port
                        "\nJ=" . $row['game_j'] . //??? - value never changes in the one logged session
                        "\nJP=" . $row['game_jp'] . //Joining Players?
                        "\nQP=" . $row['game_qp'] . //something with the queue...though its sometimes higher than B-U-QueueLength (+1 from what I have seen in the logged packets)
                        "\nAP=" . $row['game_ap'] . //current number of players on server(Active players)
                        "\nMP=" . $row['game_mp'] . //Maximum players on server
                        "\nF=" . $row['game_f'] . //??? - value never changes in the one logged session
                        "\nNF=" . $row['game_nf'] . //??? - value never changes in the one logged session
                        "\nPL=" . $row['game_pl'] . // Platform
                        "\nPW=" . $row['game_pw'] . //Password
                        "\nB-U-EA=" . $row['B-U-EA'] . //Is server EA Orginal
                        //Userdata
                        "\nB-U-Softcore=" . $row['B-U-Softcore'] . //Game is softcore
                        "\nB-U-Hardcore=" . $row['game_hardcore'] . //Game is hardcore
                        "\nB-U-HasPassword=" . $row['game_hasPassword'] . //Game is hardcore
                        "\nB-U-Punkbuster=" . $row['game_punkbuster'] . //Game has punkbuster

                        "\nB-version=" . $row['game_v'] . //Version of the server (exact version) - TRY TO CONNECT TO ACTUAL VERSION OF SERVER
                        "\nV=" . $row['game_version'] . //"clientVersion" of server (shows up in server log)
                        "\nB-U-level=" . $row['game_level'] . //current map of server
                        "\nB-U-gamemode=" . $row['B-U-gamemode'] . //Gameplay Mode (Conquest, Rush, SQDM,  etc)
                        "\nB-U-sguid=" . $row['game_sguid'] . //Game PB Server GUID?
                        "\nB-U-Time=" . $row['game_time'] . //uptime of server?
                        "\nB-U-hash=" . $row['game_hash'] . //Game hash?
                        "\nB-U-region=" . $row['game_region'] . //Game region
                        "\nB-U-public=" . $row['game_public'] . //Game is public
                        "\nB-U-elo=" . $row['game_elo'] . //???                        

                        "\nB-numObservers=" . $row['game_numObservers'] . //observers = spectators? or admins?
                        "\nB-maxObservers=" . $row['game_maxObservers'] . //Game max observers

                        "\nB-U-Provider=" . $row['B-U-Provider'] . //provider id, figured out by server
                        "\nB-U-gameMod=" . $row['B-U-gameMod'] . //maybe different value for vietnam here?   
                        "\nB-U-QueueLength=" . $row['B-U-QueueLength']; //players in queue or maximum queue length? (sometimes smaller than QP (-1?))

                if (!empty($this->vars['B-U-Punkbuster'])) {
                    $sendPacket.="\nB-U-PunkBusterVersion=" . $row['B-U-PunkBusterVersion'];
                }

                $packet = new Packet('GDAT', 0x00000000, null, $sendPacket);
                $this->sendPacket($packet);
                unset($packet);
            }
        } else if ($type == "GDAT") {
            Log::logDebug("[CTSocket] Handling GDAT");

            if (!isset($this->vars['GID']) && !isset($this->vars['LID'])) {
                Log::logDebug("[CTSocket] GDAT - GID and LID is null");
                $sendPacket = "TID=" . $this->vars['TID'];
                $packet = new Packet('GDAT', 0x00000000, null, $sendPacket);
                $this->sendPacket($packet);
                unset($packet);
            } else {
                $result = dbQuery(sprintf("SELECT * FROM `games` WHERE `game_id`='%s'", $this->vars['GID']));
                if (mysql_num_rows($result) == 1) {
                    $row = mysql_fetch_array($result);
                    $sendPacket = "TID=" . $this->vars['TID'] .
                            "\nLID=" . $row['lobby_id'] . //id of lobby
                            "\nGID=" . $row['game_id'] . //game id
                            "\nTYPE=G" . //??
                            "\nHN=" . $row['game_hn'] . //account name of server (host name)
                            "\nHU=" . $row['game_hu'] . //account id of server (host user)
                            "\nN=" . $row['game_n'] . //name of server in list
                            //OMG
                            "\nI=" . $row['game_i'] . //IP - which one? (probably outside server ip)
                            "\nP=" . $row['game_p'] . //Port
                            "\nJ=" . $row['game_j'] . //??? - value never changes in the one logged session
                            "\nJP=" . $row['game_jp'] . //Joining Players?
                            "\nQP=" . $row['game_qp'] . //something with the queue...though its sometimes higher than B-U-QueueLength (+1 from what I have seen in the logged packets)
                            "\nAP=" . $row['game_ap'] . //current number of players on server(Active players)
                            "\nMP=" . $row['game_mp'] . //Maximum players on server
                            "\nF=" . $row['game_f'] . //??? - value never changes in the one logged session
                            "\nNF=" . $row['game_nf'] . //??? - value never changes in the one logged session
                            "\nPL=" . $row['game_pl'] . // Platform
                            "\nPW=" . $row['game_pw'] . //Password
                            "\nB-U-EA=" . $row['B-U-EA'] . //Is server EA Orginal
                            //Userdata
                            "\nB-U-Softcore=" . $row['B-U-Softcore'] . //Game is softcore
                            "\nB-U-Hardcore=" . $row['game_hardcore'] . //Game is hardcore
                            "\nB-U-HasPassword=" . $row['game_hasPassword'] . //Game is hardcore
                            "\nB-U-Punkbuster=" . $row['game_punkbuster'] . //Game has punkbuster

                            "\nB-version=" . $row['game_v'] . //Version of the server (exact version) - TRY TO CONNECT TO ACTUAL VERSION OF SERVER
                            "\nV=" . $row['game_version'] . //"clientVersion" of server (shows up in server log)
                            "\nB-U-level=" . $row['game_level'] . //current map of server
                            "\nB-U-gamemode=" . $row['B-U-gamemode'] . //Gameplay Mode (Conquest, Rush, SQDM,  etc)
                            "\nB-U-sguid=" . $row['game_sguid'] . //Game PB Server GUID?
                            "\nB-U-Time=" . $row['game_time'] . //uptime of server?
                            "\nB-U-hash=" . $row['game_hash'] . //Game hash?
                            "\nB-U-region=" . $row['game_region'] . //Game region
                            "\nB-U-public=" . $row['game_public'] . //Game is public
                            "\nB-U-elo=" . $row['game_elo'] . //???                        

                            "\nB-numObservers=" . $row['game_numObservers'] . //observers = spectators? or admins?
                            "\nB-maxObservers=" . $row['game_maxObservers'] . //Game max observers

                            "\nB-U-Provider=" . $row['B-U-Provider'] . //provider id, figured out by server
                            "\nB-U-gameMod=" . $row['B-U-gameMod'] . //maybe different value for vietnam here?   
                            "\nB-U-QueueLength=" . $row['B-U-QueueLength']; //players in queue or maximum queue length? (sometimes smaller than QP (-1?))

                    if (!empty($this->vars['B-U-Punkbuster'])) {
                        $sendPacket.="\nB-U-PunkBusterVersion=" . $row['B-U-PunkBusterVersion'];
                    }

                    $packet = new Packet('GDAT', 0x00000000, null, $sendPacket);
                    $this->sendPacket($packet);
                    unset($packet);

                    $result2 = dbQuery(sprintf("SELECT * FROM `gdet` WHERE `GID`='%s'", $this->vars['GID']));
                    if (mysql_num_rows($result2) == 1) {
                        $sendPacket = "TID=" . $this->vars['TID'] .
                                "\nLID=" . $row['lobby_id'] .
                                "\nGID=" . $this->vars['GID'];
                        Log::logDebug("Getting Normal Game Data");
                        $row2 = mysql_fetch_array($result2);
                        $sendPacket .= "\nD-AutoBalance=" . $row2['AutoBalance'] .
                                "\nD-Crosshair=" . $row2['Crosshair'] .
                                "\nD-FriendlyFire=" . $row2['FriendlyFire'] .
                                "\nD-KillCam=" . $row2['KillCam'] .
                                "\nD-Minimap=" . $row2['Minimap'] .
                                "\nD-MinimapSpotting=" . $row2['MinimapSpotting'] .
                                "\nD-ServerDescriptionCount=" . $row2['ServerDescriptionCount'];
                        if (!empty($row2['ServerDescriptionCount'])) {
                            $sendPacket .= "\nD-ServerDescription0=" . $row2['ServerDescription0'] .
                                    "\nD-BannerUrl=" . $row2['BannerUrl'];
                        }
                        $sendPacket .= "\nD-ThirdPersonVehicleCameras=" . $row2['ThirdPersonVehicleCameras'] .
                                "\nD-ThreeDSpotting=" . $row2['ThreeDSpotting'] .
                                "\nUGID=" . $row['UGID'];

                        $packet = new Packet('GDET', 0x00000000, null, $sendPacket);
                        $this->sendPacket($packet);
                        unset($packet);
                        
                        /*
                          Log::logDebug("Getting Player Game Data");
                          $sendPacket .= "\npdat00=" . $row2['pdat00'] .
                          "\npdat01=" . $row2['pdat01'] .
                          "\npdat02=" . $row2['pdat02'] .
                          "\npdat03=" . $row2['pdat03'] .
                          "\npdat04=" . $row2['pdat04'] .
                          "\npdat05=" . $row2['pdat05'] .
                          "\npdat06=" . $row2['pdat06'] .
                          "\npdat07=" . $row2['pdat07'] .
                          "\npdat08=" . $row2['pdat08'] .
                          "\npdat09=" . $row2['pdat09'] .
                          "\npdat10=" . $row2['pdat10'] .
                          "\npdat11=" . $row2['pdat11'] .
                          "\npdat12=" . $row2['pdat12'] .
                          "\npdat13=" . $row2['pdat13'] .
                          "\npdat14=" . $row2['pdat14'] .
                          "\npdat15=" . $row2['pdat15'] .
                          "\npdat16=" . $row2['pdat16'] .
                          "\npdat17=" . $row2['pdat17'] .
                          "\npdat18=" . $row2['pdat18'] .
                          "\npdat19=" . $row2['pdat19'] .
                          "\npdat20=" . $row2['pdat20'] .
                          "\npdat21=" . $row2['pdat21'] .
                          "\npdat22=" . $row2['pdat22'] .
                          "\npdat23=" . $row2['pdat23'] .
                          "\npdat24=" . $row2['pdat24'] .
                          "\npdat25=" . $row2['pdat25'] .
                          "\npdat26=" . $row2['pdat26'] .
                          "\npdat27=" . $row2['pdat27'] .
                          "\npdat28=" . $row2['pdat28'] .
                          "\npdat29=" . $row2['pdat29'] .
                          "\npdat30=" . $row2['pdat30'] .
                          "\npdat31=" . $row2['pdat31'];

                         */
                    }

                    /*
                      $sendPacket = "NAME=" . $this->userData->persona_name .
                      "\nTID=" . $this->vars['TID'] .
                      "\nPID=" . $this->userData->persona_id .
                      "\nUID=" . $this->userData->user_id .
                      "\nLID=" . $this->vars['LID'] .
                      "\nGID=" . $this->vars['GID'];
                      $packet = new Packet('PDAT', 0x00000000, null, $sendPacket);
                      $this->sendPacket($packet);
                      unset($packet);
                     */
                }
            }
        } else if ($type == "EGAM") {
            Log::logDebug("[CTSocket] Handling EGAM: Client requesting to join server");
            $sendPacket = "TID=" . $this->vars['TID'] .
                    "\nLID=" . $this->vars['LID'] . //id of lobby
                    "\nGID=" . $this->vars['GID']; //game id
            $packet = new Packet('EGAM', 0x00000000, null, $sendPacket);
            $this->sendPacket($packet);
            unset($packet);

            Log::logDebug(sprintf("[CTSocket] Persona Name: %s", $this->userData->persona_name));

            $clientSocket = STSocket::getInstanceByGID($this->vars['GID']);
            if (empty($clientSocket)) {
                return;
            }

            $ticket = time();
            $sendPacket = "R-INT-PORT=" . $this->vars['R-INT-PORT'] .
                    "\nR-INT-IP=" . $this->vars['R-INT-IP'] .
                    "\nPORT=" . $this->vars['PORT'] .
                    "\nNAME=" . $this->userData->persona_name .
                    "\nPTYPE=" . $this->vars['PTYPE'] .
                    "\nTICKET=" . $ticket .
                    "\nPID=" . $this->userData->persona_id .
                    "\nUID=" . $this->userData->persona_id .
                    "\nIP=" . $this->ip .
                    "\nLID=" . $this->vars['LID'] .
                    "\nGID=" . $this->vars['GID'];

            $packet = new Packet('EGRQ', 0x00000000, null, $sendPacket);
            $clientSocket->sendPacket($packet);
            unset($packet);

            $result = dbQuery(sprintf("SELECT * FROM `games` WHERE `game_id`='%s'", $this->vars['GID']));
            if (mysql_num_rows($result)) {
                $row = mysql_fetch_array($result);
                $sendPacket = "LID=" . $row['lobby_id'] .
                        "\nGID=" . $this->vars['GID'] .
                        "\nPL=pc" .
                        "\nPID=" . $this->userData->persona_id .
                        "\nHUID=" . $row['game_hu'] .
                        "\nTICKET=" . $ticket .
                        "\nEKEY=AIBSgPFqRDg0TfdXW1zUGa4%3d" .
                        "\nI=" . $row['game_i'] .
                        "\nP=" . $row['game_p'] .
                        //the database doesnt have the internal ip!!
                        "\nINT-IP=" . $row['game_i'] .
                        "\nINT-PORT=" . $row['game_p'] .
                        "\nUGID=" . $row['UGID'];

                $packet = new Packet('EGEG', 0x00000000, null, $sendPacket);
                $this->sendPacket($packet);
                unset($packet);
            }
        } else if ($type == "ECNL") {
            $sendPacket = "TID=" . $this->vars['TID'] .
                    "\nLID=" . $this->vars['LID'] .
                    "\nGID=" . $this->vars['GID'];
            $packet = new Packet('ECNL', 0x00000000, null, $sendPacket);
            $this->sendPacket($packet);
            unset($packet);
        } else {
            Log::logDebug("[CTSocket] Could not handle type=" . $this->txn);
        }

        unset($receivedPacked);
    }

    public function sendPacket($packet, $count = true, $first = true) {
        global $extendedLog;
        if (!$this->checkValidResource()) {
            return;
        }
        $packetNr = hexdec(substr($packet->type2Hex, 1));

        $packet->type2 = sprintf("%u", ($packet->type2 & 0xff000000) | $packetNr);
        $data = $packet->type;
        $data.= Util::Putxx($packet->type2, 4);
        $len = strlen($packet->data) + 12;
        $data.= Util::putxx($len, 4);
        $data.= $packet->data;
        fwrite($this->socket, $data);
        if ($extendedLog) {
            Log::logDebug("[CTSocket] Send packet: " . $data);
        }
    }

    public function checkValidResource() {
        if (is_resource($this->socket)) {
            return true;
        }
        $this->closed = true;
        return false;
    }

}

