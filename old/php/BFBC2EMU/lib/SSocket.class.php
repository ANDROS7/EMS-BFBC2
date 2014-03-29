<?php

/**
 * Description of SSocket
 *
 * @author Sinthetix
 */
class SSocket {

    public static $_instance = array();
    public $socket;
    public $closed = false;
    public $name;
    public $ip;
    public $userData;
    public $personaData;
    public $type;
    public $type2;
    public $type2Hex;
    public $txn;
    public $vars;
    public $packetCounter = 0;
    public $autoPacketCounter = false;
    public $faults = 0;
    public $fh;

    function __construct() {
        //$this->userData = new UserData;
        //$this->personaData = new UserData;
    }

    public static function getInstanceByServer($server) {
        global $bannedServers;
        $newSock = stream_socket_accept($server);
        if (!$newSock) {
            return false;
        }
        $name = stream_socket_get_name($newSock, true);
        $parts = explode(':', $name);
        
        if (isset($bannedServers[$parts[0]])) {
            Log::logDebug('[cache] Dedicated server creation attempt from banned IP: [' . $parts[0] . ']');
            return false;
        }

        $result = dbQuery(sprintf("SELECT `ip` FROM `banned` WHERE `active` = 1 AND `ip` = '%s' AND (`type` = 's' OR `type` = 'x')", $parts[0]));
        if (!empty($result) && mysql_num_rows($result)) {
            $row = mysql_fetch_array($result);
            if (!empty($row['ip']) && !isset($bannedServers[$row['ip']])) {
                $bannedServers[$row['ip']] = '';
                Log::logDebug('[db] Dedicated server creation attempt from banned IP: [' . $row['ip'] . ']');
            }
            return false;
        }
        
        $instance = new SSocket();
        $instance->socket = $newSock;
        $instance->name = $name;
        $instance->ip = $parts[0];
        self::$_instance[$name] = $instance;

        Log::logDebug("[SSocket] [" . $instance->name . "] connection accepted " . $name);

        return $instance;
    }

    public static function getInstanceBySocket($socket) {
        $name = stream_socket_get_name($socket, true);
        if (isset(self::$_instance[$name])) {
            return self::$_instance[$name];
        }
        return false;
    }
    
    public static function unsetInstance($name){
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
//        if($extendedLog){
//            Log::logDebug("[SSocket] Recv header: ".$header);
//        }


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
        if ($extendedLog) {
            Log::logDebug("[SSocket] Recv packet[" . $this->type . "]: " . $data);
        }

        $receivedPacked = new Packet($this->type, $this->type2, $this->type2Hex, $data);
        $vars = $receivedPacked->getPacketVars();
        $txn = isset($vars['TXN']) ? $vars['TXN'] : false;

        $this->txn = $txn;
        $this->vars = $vars;
//        $varsArray = "VARS:\n";
//        foreach ($vars as $key => $var) {
//            $varsArray.=$key . ' = ' . $var . "\n";
//        }
//
//
//        if ($varsArray > 1000) {
//            $varsArray = substr($varsArray, 0, 100);
//        }
//
//        Log::logDebug("\n\n***** SSocket " . $txn . " *****\n\n");
//        Log::logDebug("\n\n***** SSocket " . $varsArray . " *****\n\n");
        //fsys
        if ($type == "fsys") {

            switch ($txn) {
                case "Hello" :
                    $this->handleHello();
                    break;
                case "MemCheck" :
                    //$this->handleMemCheck();
                    break;
                case "GetPingSites" :
                    $this->handleGetPingSites();
                    break;
                case "Goodbye" :
                    $this->handleGoodbye();
                    break;
                case "Ping" :
                    $this->handlePing();
                    break;
                default :
                    Log::logDebug($txn);
                    break;
            }

            //acct    
        } else if ($type == "acct") {

            switch ($txn) {
                case 'NuLogin' :
                    $this->handleNuLogin();
                    break;
                case 'NuGetPersonas' :
                    $this->handleNuGetPersonas();
                    break;
                case 'NuLoginPersona' :
                    $this->handleNuLoginPersona();
                    break;
                case 'NuAddPersona' :
                    $this->handleNuAddPersona();
                    break;
                case 'NuLookupUserInfo' :
                    $this->handleNuLookupUserInfo();
                    break;
                case 'NuDisablePersona' :
                    $this->handleNuDisablePersona();
                    break;
                case 'NuGrantEntitlement' :
                    $this->handleNuGrantEntitlement();
                    break;
                case 'NuGetEntitlements' :
                    $this->handleNuGetEntitlements();
                    break;
                default :
                    Log::logDebug($txn);
                    break;
            }
        }
        //asso
        else if ($type == "asso") {
            switch ($txn) {
                case 'AddAssociations':
                    $this->handleAddAssociations();
                    break;
                case 'GetAssociations':
                    $this->handleGetAssociations();
                    break;
                default :
                    Log::logDebug($txn);
                    break;
            }
        }

        //rank
        else if ($type == "rank") {
            switch ($txn) {
                case 'GetStats':
                    $this->handleGetStats();
                    break;
                case 'UpdateStats':
                    $this->handleUpdateStats();
                    break;
                case 'GetRankedStats':
                    $this->handleGetRankedStats();
                    break;
                case 'GetRankedStatsForOwners':
                    $this->handleGetRankedStatsForOwners();
                    break;
                default : {
                        if (isset($this->vars['data'])) {
                            $this->processUserRank();
                        }
                    }
            }
        }
        unset($receivedPacked);
    }

    // server connection
    public function handleHello() {
        global $emulatorIP;
        $time = date("M-d-Y H%3\ai%3\as") . " UTC";

        Log::logDebug("[SSocket] Handling TXN=Hello");

        $sendPacket = 'TXN=' . $this->txn .
                "\ndomainPartition.domain=eagames" .
                "\nmessengerIp=" . $this->ip .
                "\nmessengerPort=13505" .
                "\ndomainPartition.subDomain=BFBC2" .
                "\nactivityTimeoutSecs=300" .
                "\ncurTime=\"" . $time . '"' .
                "\ntheaterIp=" . $emulatorIP .
                "\ntheaterPort=19026";
        $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
        $this->sendPacket($packet);
        unset($packet);

        $sendPacket = "TXN=MemCheck" .
                "\nmemcheck.[]=0" .
                "\ntype=0" .
                "\nsalt=" . time();

        $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
        $this->sendPacket($packet, false);
        unset($packet);
    }

    public function handleMemCheck() {
        Log::logDebug("[SSocket] Handling TXN=MemCheck");
    }

    public function handleGetPingSites() {
        Log::logDebug("[SSocket] Handling TXN=GetPingSites");
        $result = dbQuery("SELECT `ping_site_addr`,`ping_site_type`,`ping_site_name` FROM `ping_sites`");
        $count = 0;
        if(!empty($result)){
            $count = mysql_num_rows($result);
        }
        if ($count) {
            $sendPacket = "TXN=" . $this->txn .
                    "\npingSite.[]=" . $count .
                    "\nminPingSitesToPing=0";
            $i = 0;
            while ($row = mysql_fetch_array($result)) {
                $sendPacket.= "\npingSite." . $i . ".addr=" . $row['ping_site_addr'] .
                        "\npingSite." . $i . ".type=" . $row['ping_site_type'] .
                        "\npingSite." . $i . ".name=" . $row['ping_site_name'];
                $i++;
            }
            $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
            $this->sendPacket($packet);
            unset($packet);
        } else {
            Log::logDebug("[SSocket] No PingSites in Database!");
        }
    }

    public function handlePing() {
        Log::logDebug("[SSocket] Handling TXN=Ping");
        $sendPacket = "TXN=Ping";
        $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
        $this->sendPacket($packet);
        unset($packet);
    }

    // Connection is shut down
    public function handleGoodbye() {

        Log::logDebug("[SSocket] Handling TXN=Goodbye");
        $this->closed = true;
        //dbUserLogOut($this->userData);
        //sockInfo.persona_data->saveAllStats();				
    }

    // User is logging in with email and password
    public function handleNuLogin() {
        Log::logDebug("[SSocket] Handling TXN=NuLogin");

        $nuid = $this->vars['nuid'];
        $password = $this->vars["password"];
        if ($nuid && $password) {

            //Check login                    
            $result = dbQuery(sprintf("SELECT `user_id`,`user_displayName` FROM `users` WHERE `user_nuid`='%s' AND `user_password`='%s'", $nuid, $password));
            if (!empty($result) && mysql_num_rows($result)) {
                $row = mysql_fetch_array($result);

                $this->userData = UserData::getInstance($nuid);
                $this->userData->user_id = $row['user_id'];
                $this->userData->user_loggedin = true;
                $this->userData->profile_id = $row['user_id'];
                $this->userData->user_lkey = Util::randomString(32);

                $result = dbQuery(sprintf("UPDATE `users` SET `user_online`='1', `user_lastLogin`=CURRENT_TIMESTAMP(), `user_lkey`='%s' WHERE `user_id`='%s'", $this->userData->user_lkey, $this->userData->user_id));

                $sendPacket = "TXN=" . $this->txn .
                        "\nlkey=" . $this->userData->user_lkey .
                        "\nnuid=" . $this->userData->nuid .
                        "\nprofileId=" . $this->userData->profile_id .
                        "\nuserId=" . $this->userData->user_id .
                        "\nencryptedLoginInfo=" . $this->userData->user_lkey;

                $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
                $this->sendPacket($packet);
                unset($packet);
            } else {
                $this->sendErrorPacket("The username or password is incorrect");
            }
        } else {
            Log::logDebug("[SSocket] Didn't recieve nuid and password from NuLogin packet");
            $this->sendErrorPacket("Please contact server admin!");
        }
    }

    public function handleNuGetPersonas() {
        Log::logDebug("[SSocket] Handling TXN=NuGetPersonas");

        $result = dbQuery(sprintf("SELECT `persona_id`, `persona_name` FROM `personas` WHERE `email`='%s'", $this->userData->nuid));

        $sendPacket = "TXN=" . $this->txn .
                "\npersonas.[]=" . mysql_num_rows($result);

        $i = 0;
        while ($row = mysql_fetch_array($result)) {
            if ($i == 5)
                break;
            $sendPacket .= "\npersonas." . $i . "=" . $row['persona_name'];
            $i++;
        }
        $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
        $this->sendPacket($packet);
        unset($packet);
    }

    public function handleNuLoginPersona() {
        Log::logDebug("[SSocket] Handling TXN=NuLoginPersonas");
        $result = dbQuery(sprintf("SELECT `persona_id`,`persona_name` FROM `personas` WHERE `email`='%s' AND `persona_name`='%s'", $this->userData->nuid, $this->vars['name']));

        if (!empty($result) && mysql_num_rows($result)) {
            $row = mysql_fetch_array($result);
            $this->userData->persona_id = $row['persona_id'];
            $this->userData->persona_name = $this->vars['name'];
            $this->userData->persona_loggedin = true;
            $this->userData->persona_lkey = Util::randomString(32);

            $result2 = dbQuery(sprintf("UPDATE `personas` SET `persona_online`='1', `persona_lastLogin`=CURRENT_TIMESTAMP(), `persona_lkey`='%s' WHERE `persona_id`='%s'", $this->userData->persona_lkey, $this->userData->persona_id));
            if ($result2) {
                $sendPacket = "TXN=" . $this->txn .
                        "\nlkey=" . $this->userData->persona_lkey .
                        "\nprofileId=" . $this->userData->persona_id .
                        "\nuserId=" . $this->userData->persona_id;
                $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
                $this->sendPacket($packet);
                unset($packet);
            }
        } else {
            $this->sendErrorPacket("Please contact server admin!");
        }
    }

    public function handleNuAddPersona() {
        $result = dbQuery(sprintf("SELECT `persona_name` FROM `personas` WHERE `persona_name`='%s' AND `user_id`='%s'", $this->vars["name"], $this->userData->user_id));
        if (empty($result)) {
            $this->sendErrorPacket("MySQL Error");
            return;
        }
        if (mysql_num_rows($result) == 0) {
            if (strlen($this->vars['name']) > 16 || strlen($this->vars['name']) < 4) {
                $this->sendErrorPacket("Persona name length is out of bounds");
                return;
            }
            $res = dbQuery(sprintf("INSERT INTO `personas` (`persona_name`, `ip`, `user_id`, `email`, `persona_lkey`, `persona_lastLogin`, `persona_online`) VALUES ('%s', '%s', '%s', '%s', '%s', CURRENT_TIMESTAMP, '0')", $this->vars['name'], $this->ip, $this->userData->user_id, $this->userData->nuid, Util::randomString(32)));
            if (!$res) {
                $this->sendErrorPacket("Error creating new persona");
            } else {
                $persona_id = mysql_insert_id();
//                if (!$this->userData->createDefultPersonaStats($persona_id)) {
//                    Log::logDebug("Warning, stats could not be created for persona_id: " . $persona_id);
//                }
                $sendPacket = "TXN=" . $this->txn;
                $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
                $this->sendPacket($packet);
                unset($packet);
            }
        } else {
            $this->sendErrorPacket("Selected Name already exists!");
        }
    }

    public function handleNuGrantEntitlement() {
        Log::logDebug("[SSocket] Handling TXN=NuGrantEntitlement");
        $sendPacket = "TXN=" . $this->txn;
        $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
        $this->sendPacket($packet);
        unset($packet);
    }

    public function handleNuGetEntitlements() {
        Log::logDebug("[SSocket] Handling TXN=NuGetEntitlements");
        $groupName = isset($this->vars['groupName']) ? $this->vars['groupName'] : false;
        $sendPacket = "TXN=" . $this->txn;
        $this->personaData = UserData::getInstanceByUserId($this->vars['masterUserId']);
        if (empty($this->personaData)) {
            $sendPacket = "TXN=" . $this->txn;
            $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
            $this->sendPacket($packet);
            unset($packet);
            return;
        }

        if (isset($this->vars['projectId'])) {
            if ($this->vars['projectId'] == '136844') {
                global $optionAllKits;
                if (!empty($optionAllKits)) {
                    $sendPacket = "TXN=" . $this->txn .
                            "\nentitlements.[]=4" .
                            "\nentitlements.0.entitlementTag=ONLINE_ACCESS" .
                            "\nentitlements.0.statusReasonCode=" .
                            "\nentitlements.0.entitlementId=1114490796" .
                            "\nentitlements.0.terminationDate=" .
                            "\nentitlements.0.groupName=BFBC2PC" .
                            "\nentitlements.0.productId=DR%3a156691300" .
                            "\nentitlements.0.status=ACTIVE" .
                            "\nentitlements.0.grantDate=2011-07-30T0%3a6Z" .
                            "\nentitlements.0.version=0" .
                            "\nentitlements.0.userId=" . $this->personaData->user_id .
                            "\nentitlements.1.entitlementId=817764458" .
                            "\nentitlements.1.entitlementTag=BFBC2%3aPC%3aVIETNAM_ACCESS" .
                            "\nentitlements.1.grantDate=2011-07-30T0%3a6Z" .
                            "\nentitlements.1.groupName=BFBC2PC" .
                            "\nentitlements.1.productId=DR%3a219316800" .
                            "\nentitlements.1.status=ACTIVE" .
                            "\nentitlements.1.statusReasonCode=" .
                            "\nentitlements.1.terminationDate=" .
                            "\nentitlements.1.version=0" .
                            "\nentitlements.1.userId=" . $this->personaData->user_id .
                            "\nentitlements.2.entitlementId=817764457" .
                            "\nentitlements.2.entitlementTag=BFBC2%3aPC%3aVIETNAM_PDLC" .
                            "\nentitlements.2.grantDate=2011-07-30T0%3a6Z" .
                            "\nentitlements.2.groupName=BFBC2PC" .
                            "\nentitlements.2.productId=DR%3a219316800" .
                            "\nentitlements.2.status=ACTIVE" .
                            "\nentitlements.2.statusReasonCode=" .
                            "\nentitlements.2.terminationDate=" .
                            "\nentitlements.2.version=0" .
                            "\nentitlements.2.userId=" . $this->personaData->user_id;
                    "\nentitlements.3.entitlementId=1555702272" .
                            "\nentitlements.3.entitlementTag=BFBC2%3aPC%3aALLKIT" .
                            "\nentitlements.3.grantDate=2011-07-30T0%3a6Z" .
                            "\nentitlements.3.groupName=BFBC2PC" .
                            "\nentitlements.3.productId=DR%3a192365600" .
                            "\nentitlements.3.status=ACTIVE" .
                            "\nentitlements.3.statusReasonCode=" .
                            "\nentitlements.3.terminationDate=" .
                            "\nentitlements.3.version=0" .
                            "\nentitlements.3.userId=" . $this->personaData->user_id;
                } else {
                    $sendPacket = "TXN=" . $this->txn .
                            "\nentitlements.[]=3" .
                            "\nentitlements.0.entitlementTag=ONLINE_ACCESS" .
                            "\nentitlements.0.statusReasonCode=" .
                            "\nentitlements.0.entitlementId=1114490796" .
                            "\nentitlements.0.terminationDate=" .
                            "\nentitlements.0.groupName=BFBC2PC" .
                            "\nentitlements.0.productId=DR%3a156691300" .
                            "\nentitlements.0.status=ACTIVE" .
                            "\nentitlements.0.grantDate=2011-07-30T0%3a6Z" .
                            "\nentitlements.0.version=0" .
                            "\nentitlements.0.userId=" . $this->personaData->user_id .
                            "\nentitlements.1.entitlementId=817764458" .
                            "\nentitlements.1.entitlementTag=BFBC2%3aPC%3aVIETNAM_ACCESS" .
                            "\nentitlements.1.grantDate=2011-07-30T0%3a6Z" .
                            "\nentitlements.1.groupName=BFBC2PC" .
                            "\nentitlements.1.productId=DR%3a219316800" .
                            "\nentitlements.1.status=ACTIVE" .
                            "\nentitlements.1.statusReasonCode=" .
                            "\nentitlements.1.terminationDate=" .
                            "\nentitlements.1.version=0" .
                            "\nentitlements.1.userId=" . $this->personaData->user_id .
                            "\nentitlements.2.entitlementId=817764457" .
                            "\nentitlements.2.entitlementTag=BFBC2%3aPC%3aVIETNAM_PDLC" .
                            "\nentitlements.2.grantDate=2011-07-30T0%3a6Z" .
                            "\nentitlements.2.groupName=BFBC2PC" .
                            "\nentitlements.2.productId=DR%3a219316800" .
                            "\nentitlements.2.status=ACTIVE" .
                            "\nentitlements.2.statusReasonCode=" .
                            "\nentitlements.2.terminationDate=" .
                            "\nentitlements.2.version=0" .
                            "\nentitlements.2.userId=" . $this->personaData->user_id;
                }
                $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
                $this->sendPacket($packet);
                unset($packet);
                return;
            } else {
                $sendPacket = "TXN=" . $this->txn .
                        "\nentitlements.[]=0";
                $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
                $this->sendPacket($packet);
                unset($packet);
                return;
            }
        } else {

            switch ($groupName) {
                case 'BFBC2PC' :
                    $sendPacket = "TXN=" . $this->txn .
                            "\nentitlements.[]=3" .
                            "\nentitlements.0.entitlementTag=ONLINE_ACCESS" .
                            "\nentitlements.0.statusReasonCode=" .
                            "\nentitlements.0.entitlementId=1114490796" .
                            "\nentitlements.0.terminationDate=" .
                            "\nentitlements.0.groupName=BFBC2PC" .
                            "\nentitlements.0.productId=DR%3a156691300" .
                            "\nentitlements.0.status=ACTIVE" .
                            "\nentitlements.0.grantDate=2011-07-30T0%3a6Z" .
                            "\nentitlements.0.version=0" .
                            "\nentitlements.0.userId=" . $this->userData->user_id .
                            "\nentitlements.1.entitlementId=817764458" .
                            "\nentitlements.1.entitlementTag=BFBC2%3aPC%3aVIETNAM_ACCESS" .
                            "\nentitlements.1.grantDate=2011-07-30T0%3a6Z" .
                            "\nentitlements.1.groupName=BFBC2PC" .
                            "\nentitlements.1.productId=DR%3a219316800" .
                            "\nentitlements.1.status=ACTIVE" .
                            "\nentitlements.1.statusReasonCode=" .
                            "\nentitlements.1.terminationDate=" .
                            "\nentitlements.1.version=0" .
                            "\nentitlements.1.userId=" . $this->userData->user_id .
                            "\nentitlements.2.entitlementId=817764457" .
                            "\nentitlements.2.entitlementTag=BFBC2%3aPC%3aVIETNAM_PDLC" .
                            "\nentitlements.2.grantDate=2011-07-30T0%3a6Z" .
                            "\nentitlements.2.groupName=BFBC2PC" .
                            "\nentitlements.2.productId=DR%3a219316800" .
                            "\nentitlements.2.status=ACTIVE" .
                            "\nentitlements.2.statusReasonCode=" .
                            "\nentitlements.2.terminationDate=" .
                            "\nentitlements.2.version=0" .
                            "\nentitlements.2.userId=" . $this->userData->user_id;
                    $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
                    $this->sendPacket($packet);
                    unset($packet);
                    return;
                case 'AddsVetRank' :
                    $sendPacket .= "\nentitlements.[]=1" .
                            "\nentitlements.0.statusReasonCode=" .
                            "\nentitlements.0.groupName=AddsVetRank" .
                            "\nentitlements.0.grantDate=2011-07-30T0%3a11Z" .
                            "\nentitlements.0.version=0" .
                            "\nentitlements.0.entitlementId=1114495162" .
                            "\nentitlements.0.terminationDate=" .
                            "\nentitlements.0.productId=" .
                            "\nentitlements.0.entitlementTag=BFBC2%3aPC%3aADDSVETRANK" .
                            "\nentitlements.0.status=ACTIVE" .
                            "\nentitlements.0.userId=" . $this->personaData->user_id;
                    $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
                    $this->sendPacket($packet);
                    unset($packet);
                    return;
                case 'BattlefieldBadCompany2' :
                    $sendPacket .= "\nentitlements.[]=0";
                    $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
                    $this->sendPacket($packet);
                    unset($packet);
                    return;
                case 'NoVetRank' :
                    $sendPacket .= "\nentitlements.[]=0";
                    $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
                    $this->sendPacket($packet);
                    unset($packet);
                    return;
                default : {
                        if (!empty($this->vars['entitlementTag']) && $this->vars['entitlementTag'] == "BFBC2:PC:VIETNAM_ACCESS") {
                            $sendPacket = "TXN=" . $this->txn .
                                    "\nentitlements.[]=1" .
                                    "\nentitlements.0.entitlementTag=BFBC2:PC:VIETNAM_ACCESS" .
                                    "\nentitlements.0.statusReasonCode=" .
                                    "\nentitlements.0.entitlementId=817764458" .
                                    "\nentitlements.0.terminationDate=" .
                                    "\nentitlements.0.groupName=BFBC2PC" .
                                    "\nentitlements.0.productId=DR%3a219316800" .
                                    "\nentitlements.0.status=ACTIVE" .
                                    "\nentitlements.0.grantDate=2011-07-30T0%3a6Z" .
                                    "\nentitlements.0.version=0" .
                                    "\nentitlements.0.userId=" . $this->personaData->user_id;
                            $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
                            $this->sendPacket($packet);
                            unset($packet);
                            return;
                        } else {
                            global $optionAllKits;
                            if (!empty($optionAllKits)) {
                                $sendPacket = "TXN=" . $this->txn .
                                        "\nentitlements.[]=4" .
                                        "\nentitlements.0.entitlementTag=ONLINE_ACCESS" .
                                        "\nentitlements.0.statusReasonCode=" .
                                        "\nentitlements.0.entitlementId=1114490796" .
                                        "\nentitlements.0.terminationDate=" .
                                        "\nentitlements.0.groupName=BFBC2PC" .
                                        "\nentitlements.0.productId=DR%3a156691300" .
                                        "\nentitlements.0.status=ACTIVE" .
                                        "\nentitlements.0.grantDate=2011-07-30T0%3a6Z" .
                                        "\nentitlements.0.version=0" .
                                        "\nentitlements.0.userId=" . $this->personaData->user_id .
                                        "\nentitlements.1.entitlementId=817764458" .
                                        "\nentitlements.1.entitlementTag=BFBC2%3aPC%3aVIETNAM_ACCESS" .
                                        "\nentitlements.1.grantDate=2011-07-30T0%3a6Z" .
                                        "\nentitlements.1.groupName=BFBC2PC" .
                                        "\nentitlements.1.productId=DR%3a219316800" .
                                        "\nentitlements.1.status=ACTIVE" .
                                        "\nentitlements.1.statusReasonCode=" .
                                        "\nentitlements.1.terminationDate=" .
                                        "\nentitlements.1.version=0" .
                                        "\nentitlements.1.userId=" . $this->personaData->user_id .
                                        "\nentitlements.2.entitlementId=817764457" .
                                        "\nentitlements.2.entitlementTag=BFBC2%3aPC%3aVIETNAM_PDLC" .
                                        "\nentitlements.2.grantDate=2011-07-30T0%3a6Z" .
                                        "\nentitlements.2.groupName=BFBC2PC" .
                                        "\nentitlements.2.productId=DR%3a219316800" .
                                        "\nentitlements.2.status=ACTIVE" .
                                        "\nentitlements.2.statusReasonCode=" .
                                        "\nentitlements.2.terminationDate=" .
                                        "\nentitlements.2.version=0" .
                                        "\nentitlements.2.userId=" . $this->personaData->user_id;
                                "\nentitlements.3.entitlementId=1555702272" .
                                        "\nentitlements.3.entitlementTag=BFBC2%3aPC%3aALLKIT" .
                                        "\nentitlements.3.grantDate=2011-07-30T0%3a6Z" .
                                        "\nentitlements.3.groupName=BFBC2PC" .
                                        "\nentitlements.3.productId=DR%3a192365600" .
                                        "\nentitlements.3.status=ACTIVE" .
                                        "\nentitlements.3.statusReasonCode=" .
                                        "\nentitlements.3.terminationDate=" .
                                        "\nentitlements.3.version=0" .
                                        "\nentitlements.3.userId=" . $this->personaData->user_id;
                            } else {
                                $sendPacket = "TXN=" . $this->txn .
                                        "\nentitlements.[]=3" .
                                        "\nentitlements.0.entitlementTag=ONLINE_ACCESS" .
                                        "\nentitlements.0.statusReasonCode=" .
                                        "\nentitlements.0.entitlementId=1114490796" .
                                        "\nentitlements.0.terminationDate=" .
                                        "\nentitlements.0.groupName=BFBC2PC" .
                                        "\nentitlements.0.productId=DR%3a156691300" .
                                        "\nentitlements.0.status=ACTIVE" .
                                        "\nentitlements.0.grantDate=2011-07-30T0%3a6Z" .
                                        "\nentitlements.0.version=0" .
                                        "\nentitlements.0.userId=" . $this->personaData->user_id .
                                        "\nentitlements.1.entitlementId=817764458" .
                                        "\nentitlements.1.entitlementTag=BFBC2%3aPC%3aVIETNAM_ACCESS" .
                                        "\nentitlements.1.grantDate=2011-07-30T0%3a6Z" .
                                        "\nentitlements.1.groupName=BFBC2PC" .
                                        "\nentitlements.1.productId=DR%3a219316800" .
                                        "\nentitlements.1.status=ACTIVE" .
                                        "\nentitlements.1.statusReasonCode=" .
                                        "\nentitlements.1.terminationDate=" .
                                        "\nentitlements.1.version=0" .
                                        "\nentitlements.1.userId=" . $this->personaData->user_id .
                                        "\nentitlements.2.entitlementId=817764457" .
                                        "\nentitlements.2.entitlementTag=BFBC2%3aPC%3aVIETNAM_PDLC" .
                                        "\nentitlements.2.grantDate=2011-07-30T0%3a6Z" .
                                        "\nentitlements.2.groupName=BFBC2PC" .
                                        "\nentitlements.2.productId=DR%3a219316800" .
                                        "\nentitlements.2.status=ACTIVE" .
                                        "\nentitlements.2.statusReasonCode=" .
                                        "\nentitlements.2.terminationDate=" .
                                        "\nentitlements.2.version=0" .
                                        "\nentitlements.2.userId=" . $this->personaData->user_id;
                            }
                            $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
                            $this->sendPacket($packet);
                            unset($packet);
                        }
                    }
            }
        }
    }

    public function handleNuLookupUserInfo() {
        $result = dbQuery(sprintf("SELECT `user_id`, `persona_id` FROM `personas` WHERE `persona_name` = '%s'", $this->vars['userInfo.0.userName']));
        if (mysql_num_rows($result)) {
            $row = mysql_fetch_array($result);
            $userData = UserData::getInstanceByUserId($row['user_id']);
            if (empty($userData)) {
                $sendPacket = "TXN=" . $this->txn;
                $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
                $this->sendPacket($packet);
                unset($packet);
                return;
            }
            $this->personaData = $userData;
            $this->personaData->rank_data_sent = null;
            Log::logDebug("[SSocket] Handling TXN=NuLookupUserInfo: userId=" . $this->personaData->user_id . " personaId=" . $this->personaData->persona_id);
            $sendPacket = "TXN=NuLookupUserInfo" .
                    "\nuserInfo.0.userName=" . $this->vars['userInfo.0.userName'] .
                    "\nuserInfo.0.namespace=battlefield" .
                    "\nuserInfo.0.userId=" . $this->personaData->persona_id .
                    "\nuserInfo.0.masterUserId=" . $this->personaData->user_id .
                    "\nuserInfo.[]=1";

            $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
            $this->sendPacket($packet);
            unset($packet);
        }
    }

    public function handleGetAssociations() {
        $type = $this->vars['type'];
        Log::logDebug("[SSocket] Handling TXN=GetAssociations " . $type . " owner.id=" . $this->userData->user_id);
        $sendPacket = "TXN=" . $this->txn;

        switch ($type) {
            case 'PlasmaFriends':
                $sendPacket .= "\ntype=PlasmaFriends" .
                        "\nmaxListSize=20" .
                        "\ndomainPartition.domain=eagames" .
                        "\ndomainPartition.subDomain=BFBC2" .
                        "\nowner.id=" . $this->userData->persona_id .
                        "\nowner.name=" . $this->userData->persona_name .
                        "\nowner.type=1" .
                        "\nmembers.[]=0";
                $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
                $this->sendPacket($packet);
                unset($packet);
                break;
            case 'PlasmaMute':
                $sendPacket .= "\ntype=PlasmaMute" .
                        "\nmaxListSize=100" .
                        "\ndomainPartition.domain=eagames" .
                        "\ndomainPartition.subDomain=BFBC2" .
                        "\nowner.id=" . $this->userData->persona_id .
                        "\nowner.name=" . $this->userData->persona_name .
                        "\nowner.type=1" .
                        "\nmembers.[]=0";
                $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
                $this->sendPacket($packet);
                unset($packet);
                break;
            case 'PlasmaBlock':
                $sendPacket .= "\ntype=PlasmaBlock" .
                        "\nmaxListSize=20" .
                        "\ndomainPartition.domain=eagames" .
                        "\ndomainPartition.subDomain=BFBC2" .
                        "\nowner.id=" . $this->userData->persona_id .
                        "\nowner.name=" . $this->userData->persona_name .
                        "\nowner.type=1" .
                        "\nmembers.[]=0";
                $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
                $this->sendPacket($packet);
                unset($packet);
                break;

            case 'PlasmaRecentPlayers':
                $sendPacket .= "\ntype=PlasmaRecentPlayers" .
                        "\nmaxListSize=20" .
                        "\ndomainPartition.domain=eagames" .
                        "\ndomainPartition.subDomain=BFBC2" .
                        "\nowner.id=" . $this->userData->persona_id .
                        "\nowner.name=" . $this->userData->persona_name .
                        "\nowner.type=1" .
                        "\nmembers.[]=0";
                $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
                $this->sendPacket($packet);
                unset($packet);
                break;
            case 'dogtags':
                $sendPacket .= "\ntype=dogtags" .
                        "\nmaxListSize=20" .
                        "\ndomainPartition.domain=eagames" .
                        "\ndomainPartition.subDomain=BFBC2" .
                        "\nowner.id=" . $this->userData->persona_id .
                        "\nowner.name=" . $this->userData->persona_name .
                        "\nowner.type=1" .
                        "\nmembers.[]=0";
                $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
                $this->sendPacket($packet);
                unset($packet);
                break;
            default: {
                    Log::logDebug(sprintf("[SSocket] Could not handle TXN=%s type=%s", $this->txn, $type));
                    return;
                }
        }
    }

    public function handleAddAssociations() {
        Log::logDebug("[SSocket] Handling TXN=AddAssociations");

        $result = dbQuery(sprintf("SELECT `user_id`, `persona_name`, `email` FROM `personas` WHERE `persona_id`='%s'", $this->vars['addRequests.0.member.id']));
        if (mysql_num_rows($result)) {
            $row = mysql_fetch_array($result);
            $this->personaData = UserData::getInstanceByPersonaId($this->vars['addRequests.0.member.id'], $row['email']);
            if (!$this->personaData) {
                $message = sprintf("[SSocket] Couldn't find persona id %s in memory!!", $this->vars['addRequests.0.member.id']);
                Log::logDebug($message);
                $this->sendErrorPacket($message);
                return;
            }
            $this->personaData->user_id = $row['user_id'];
            $this->personaData->persona_id = $this->vars['addRequests.0.member.id'];
            $this->personaData->persona_name = $row['persona_name'];
            $this->personaData->nuid = $row['email'];
            $this->personaData->rank_data_sent = false;
        } else {
            Log::logDebug(sprintf("[SSocket] Couldn't find persona id %s in database!!", $this->personaData->user_id));
        }
        $sendPacket = "TXN=" . $this->txn;
        $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
        $this->sendPacket($packet);
        unset($packet);
    }

    public function handleGetStats() {
        Log::logDebug("[SSocket] Handling TXN=GetStats");
        if (empty($this->personaData)) {
                $sendPacket = "TXN=" . $this->txn;
                $sendPacket .= "\nstats.[]=" . 0;
                $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
                $this->sendPacket($packet);
                unset($packet);
                return;
            }
        $this->personaData->loadPersonaStats(true);
        //fix rank
        $this->personaData->stats['rank'] = Util::getRankByScore($this->personaData->stats['score']);
        $sendPacket = "TXN=" . $this->txn;

        $count = $this->vars['keys.[]'];

        $sendPacket .= "\nstats.[]=" . $count;
        for ($i = 0; $i < $count; $i++) {
            $keyname = "keys." . $i;
            $key = $this->vars[$keyname];
            $sendPacket .= "\nstats." . $i . ".key=" . $key;
            $sendPacket .= "\nstats." . $i . ".value=" . $this->personaData->stats[$key];
        }
        $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
        $this->sendPacket($packet);
        unset($packet);
    }

    public function handleUpdateStats() {
        Log::logDebug("[SSocket] Handling TXN=UpdateStats");
//        if(!isset($this->fh)){
//            $this->fh = fopen('unknownvrs.txt', "a"); 
//        }       
        //get csocket by user id
        if (!isset($this->vars['u.0.o'])) {
            return;
        }

        $userData = UserData::getInstanceByPersonaId($this->vars['u.0.o']);
        if (!empty($userData) && !empty($userData->persona_id)) {
            Log::logDebug("[SSocket] UserData persona_id from packet: ". $this->vars['u.0.o']. " user_id:" . $userData->user_id . ", persona_id:" . $userData->persona_id);
            if($this->vars['u.0.o'] != $userData->persona_id){
                Log::logDebug('persona id from packet and instance don\'t match. Extracting from database.');
                $result = dbQuery(sprintf("SELECT `persona_id`, `user_id`, `email` FROM `personas` WHERE `persona_id`='%s'", $this->vars['u.0.o']));
                if (!empty($result) && mysql_num_rows($result)) {
                    $row = mysql_fetch_array($result);
                    $userData = UserData::getInstance($row['email']);
                    $userData->persona_id = $row['persona_id'];
                    $userData->user_id = $row['user_id'];
                }
            }
            if (!empty($userData)) {
                global $statsSetValues, $statsAverageValues;
                $count = $this->vars['u.0.s.[]'];
                $userData->loadPersonaStats(true);
                if(empty($userData->stats)){
                    $message = 'Failing loading stats from database, stats will not be saved';
                    Log::logDebug($message);
                    $this->sendErrorPacket($message);
                    return;
                }
                $rank = $userData->stats['rank'];
                for ($i = 0; $i < $count; $i++) {
                    $key = $this->vars['u.0.s.' . $i . '.k'];
//                if(!isset($userData->stats[$key])){
//                    fwrite($this->fh, $key."\n");
//                }
                    //if ($key == 'accuracy') {
                    if (isset($statsAverageValues[$key])) {
                        $userData->stats[$key] = sprintf("%.2f",($userData->stats[$key] + $this->vars['u.0.s.' . $i . '.v']) / 2);
                    } else if(isset($statsSetValues[$key])){
                        $userData->stats[$key] = sprintf("%.2f",$this->vars['u.0.s.' . $i . '.v']);
                    } else {
                        if(gettype($this->vars['u.0.s.' . $i . '.v']) == 'double'){
                            $userData->stats[$key] = sprintf("%.2f",$userData->stats[$key] + $this->vars['u.0.s.' . $i . '.v']);
                        } else {
                            $userData->stats[$key] = sprintf("%.0f",$userData->stats[$key] + $this->vars['u.0.s.' . $i . '.v']);
                        }
                    }
                }
                unset($this->vars);
                $userData->stats['rank'] = $rank;
                if($userData->fixStats()){
                    $userData->savePersonaStats();
                }
                //$this->personaData = $userData;
                $this->personaData->rank_data_sent = false;
            }
        }

        $sendPacket = "TXN=" . $this->txn;
        $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
        $this->sendPacket($packet);
        unset($packet);
    }

    public function processUserRank() {
        if(empty($this->personaData)){
            return;
        }
        Log::logDebug("[SSocket] Handling Handling TYPE=rank");
        if (!$this->personaData->rank_receiving) {
            if(empty($this->personaData->user_id) || empty($this->personaData->persona_id)){
                return;
            }
            Log::logDebug("[SSocket] ProccessUserRank masterUserId=" . $this->personaData->user_id . " personaId=" . $this->personaData->persona_id . " personaName=" . $this->personaData->persona_name);
            Log::logDebug("[SSocket] First packet TYPE=rank");
            $this->personaData->rank_receiving = true;
            $this->personaData->rank_size = (int) $this->vars['size'];
            $this->personaData->rank_data = "";
        }

        Log::logDebug("[SSocket] Add data TYPE=rank");
        $this->personaData->rank_data.=$this->vars['data'];
        if (strlen($this->personaData->rank_data) >= $this->personaData->rank_size) {

            Log::logDebug("[SSocket] Received full rank data masterUserId=" . $this->personaData->user_id . " personaId=" . $this->personaData->persona_id . " personaName=" . $this->personaData->persona_name);
            $this->personaData->loadPersonaStats(true);
            $decoded_data = base64_decode($this->personaData->rank_data);

            if (!empty($decoded_data)) {
                $vars = Util::getRankVars($decoded_data);
                unset($decoded_data);
                $sendPacket = "TXN=GetStats";
                if(!isset($vars['keys.[]'])){
                    $sendPacket.= "\nstats.[]=" . 0;
                    $packet = new Packet($this->type, 0xb0000000, $this->type2Hex, $sendPacket);
                    $this->sendPacket($packet, false, true);
                    $userInstance = CSocket::getInstanceByUserId($this->personaData->user_id);
                    if(!empty($userInstance)){
                        $userInstance->handleGoodbye();
                    }
                    return;
                }
                $count = $vars['keys.[]']['value'];

                for ($i = 0; $i < $count; $i++) {
                    $sendPacket .= sprintf("\nstats.%s.key=%s\nstats.%s.value=%s", $i, $vars['keys.' . $i]['value'], $i, $this->personaData->stats[$vars['keys.' . $i]['value']]);
                }
                unset($vars);
                $sendPacket.= "\nstats.[]=" . $count;

                $decoded_size = strlen($sendPacket);
                $sendEncoded = base64_encode($sendPacket);

                $encoded_size = strlen($sendEncoded);
                $size = $encoded_size;
                $first_packet = true;
                $packetSize = 8096;


                $splitPacked = chunk_split($sendEncoded, 8096, "\n");
                $splitPacked = explode("\n", $splitPacked);
                unset($sendEncoded);

                $i = 0;
                Log::logDebug("[SSocket] Sending rank data packets masterUserId=" . $this->personaData->user_id . " personaId=" . $this->personaData->persona_id . " personaName=" . $this->personaData->persona_name);
                foreach ($splitPacked as $data) {
                    if (empty($data))
                        break;

                    $sendPacket = "decodedSize=" . $decoded_size .
                            "\nsize=" . $encoded_size .
                            "\ndata=" . $data;
                    //$data2 .= $data;
                    $packet = new Packet($this->type, 0xb0000000, $this->type2Hex, $sendPacket);
                    $this->sendPacket($packet, true, $first_packet);
                    $first_packet = false;
                    unset($packet);
                }

                unset($splitPacked);
                $this->personaData->rank_receiving = false;
                unset($this->personaData->rank_data);
                unset($this->personaData->stats);
            }
        }
    }

    public function sendPacket($packet, $count = true, $first = true) {
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
        //Log::logDebug("Send packet: " . $data);
    }

    public function sendErrorPacket($message) {
        $sendPacket = "TXN=" . $this->txn .
                "\nlocalizedMessage=\"" . $message . "\"" .
                "\nerrorCode=122";
        $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
        $this->sendPacket($packet);
        unset($packet);
    }

    public function checkValidResource() {
        if (is_resource($this->socket)) {
            return true;
        }
        $this->handleGoodbye();
        return false;
    }

}

