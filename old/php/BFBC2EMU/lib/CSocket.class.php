<?php

//require_once('UserData.class.php');

/**
 * Description of Socket
 *
 * @author Sinthetix
 */
class CSocket {

    public static $_instance = array();
    public static $_instanceByUserId = array();
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
        $this->userData = new UserData;
    }

    public static function getInstanceByServer($server) {
        global $bannedUserIP;
        $newSock = @stream_socket_accept($server);
        if (!$newSock) {
            return false;
        }
        $name = stream_socket_get_name($newSock, true);
        $parts = explode(':', $name);

        if (isset($bannedUserIP[$parts[0]])) {
            Log::logDebug('[cache] User login attempt from banned IP: [' . $parts[0] . ']');
            return false;
        }

        $result = dbQuery(sprintf("SELECT `ip` FROM `banned` WHERE `active` = 1 AND `ip` = '%s' AND (`type` = 'c' OR `type` = 'x')", $parts[0]));
        if (!empty($result) && mysql_num_rows($result)) {
            $row = mysql_fetch_array($result);
            if (!empty($row['ip']) && !isset($bannedUserIP[$row['ip']])) {
                $bannedUserIP[$row['ip']] = '';
                Log::logDebug('[db] User login attempt from banned IP: [' . $row['ip'] . ']');
            }
            return false;
        }
        Log::logDebug("[CSocket] connection accepted " . $name);

        $instance = new CSocket();
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

    public static function getInstanceByUserId($userId, $name = null, $forceCreate = false) {
        if (isset(self::$_instanceByUserId[$userId])) {
            if($forceCreate){
                unset(self::$_instanceByUserId[$userId]);
            } else {
                return self::$_instanceByUserId[$userId];
            }
        }
        if (isset($name) && isset(self::$_instance[$name])) {
            self::$_instanceByUserId[$userId] = self::$_instance[$name];
            return self::$_instanceByUserId[$userId];
        }
        return false;
    }

    public static function unsetInstance($name) {
        $userId = isset(self::$_instance[$name]->userData->user_id) ? self::$_instance[$name]->userData->user_id : false;
        unset(self::$_instance[$name]);
        if (!empty($userId)) {            
            unset(self::$_instanceByUserId[$userId]);
        }
        $userData = UserData::getInstanceByUserId($userId);
        UserData::unsetUserData($userData);
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
            Log::logDebug("[CSocket] Recv packet[" . $this->type . "]: " . $data);
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
//        if ($varsArray > 1000) {
//            $varsArray = substr($varsArray, 0, 100);
//        }
//        Log::logDebug("\n\n***** CSocket " . $txn . " *****\n\n");
//        Log::logDebug("\n\n***** CSocket " . $varsArray . " *****\n\n");
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
            }

            //acct    
        } else if ($type == "acct") {

            switch ($txn) {
                case 'GetCountryList' :
                    $this->handleGetCountryList();
                    break;
                case 'NuLogin' :
                    $this->handleNuLogin();
                    break;
                case 'NuAddAccount' :
                    $this->handleNuAddAccount();
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
                case 'NuDisablePersona' :
                    $this->handleNuDisablePersona();
                    break;
                case 'GetTelemetryToken' :
                    $this->handleGetTelemetryToken();
                    break;
                case 'NuGetEntitlements' :
                    $this->handleNuGetEntitlements();
                    break;
                case 'NuEntitleUser' :
                    $this->handleNuEntitleUser();
                    break;
                case 'GetLockerURL' :
                    $this->handleGetLockerURL();
                    break;
                case 'NuSearchOwners' :
                    $this->handleNuSearchOwners();
                    break;
                case 'NuGetTos' :
                    $this->handleNuGetTos();
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

        //xmsg
        else if ($type == "xmsg") {
            switch ($txn) {
                case 'ModifySettings':
                    $this->handleModifySettings();
                    break;
                case 'GetMessages':
                    $this->handleGetMessages();
                    break;
                default :
                    Log::logDebug($txn);
                    break;
            }
        }

        //pres
        else if ($type == "pres") {
            switch ($txn) {
                case 'PresenceSubscribe':
                    $this->handlePresenceSubscribe();
                    break;
                case 'SetPresenceStatus':
                    $this->handleSetPresenceStatus();
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
                case 'GetRankedStats':
                    $this->handleGetRankedStats();
                    break;
                case 'GetRankedStatsForOwners':
                    $this->handleGetRankedStatsForOwners();
                    break;
                case 'GetTopNAndStats':
                    $this->handleGetTopNAndStats();
                    break;
                default : {
                        if (isset($this->vars['data'])) {
                            $this->processUserRank();
                        }
                    }
            }
        }

        //recp
        else if ($type == "recp") {
            switch ($txn) {
                case 'GetRecord':
                    $this->handleGetRecord();
                    break;
                case 'GetRecordAsMap':
                    $this->handleGetRecordAsMap();
                    break;
                case 'UpdateRecord':
                    $this->handleUpdateRecord();
                    break;
                default :
                    Log::logDebug($txn);
                    break;
            }
        }

        unset($receivedPacked);
    }

    public function handleUpdateRecord() {
        Log::logDebug("[CSocket] Handling TXN=UpdateRecord");
        $i = 0;
        while (isset($this->vars['values.' . $i . '.key'])) {
            dbQuery(sprintf("INSERT INTO `dogtags` SET `persona_id`='%s', `key`='%s', `value`='%s' ON DUPLICATE KEY UPDATE `value`='%s'", $this->userData->persona_id, $this->vars['values.' . $i . '.key'], $this->vars['values.' . $i . '.value'], $this->vars['values.' . $i . '.value']));
            $i++;
        }
        $sendPacket = "TXN=" . $this->txn;
        $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
        $this->sendPacket($packet);
        unset($packet);
    }

    public function handleGetRecordAsMap() {
        Log::logDebug("[CSocket] Handling TXN=GetRecordAsMap");
        $result = dbQuery(sprintf("SELECT `key`, `value` FROM `dogtags` WHERE `persona_id` = '%s'", $this->userData->persona_id));

        if (empty($result) ||  mysql_num_rows($result) == 0) {
            $sendPacket = "TXN=" . $this->txn .
                    "\nvalues.{}=0" .
                    "\nTTL=0" .
                    "\nstate=1";
        } else {
            $count = mysql_num_rows($result);
            $sendPacket = "TXN=" . $this->txn .
                    "\nvalues.{}=" . $count;

            while ($row = mysql_fetch_array($result)) {
                $sendPacket .= "\nvalues.{" . $row['key'] . "}=" . $row['value'];
            }

            $sendPacket .= "\nTTL=0" .
                    "\nstate=1";
        }
        $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
        $this->sendPacket($packet);
        unset($packet);
    }

    public function handleGetRecord() {
        Log::logDebug("[CSocket] Handling TXN=GetRecord");
        $sendPacket = "TXN=" . $this->txn .
                "\nlocalizedMessage=\"Record not found\"" .
                "\nerrorContainer.[]=0" .
                "\nerrorCode=5000";
        $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
        $this->sendPacket($packet);
        unset($packet);
    }

    public function handleNuEntitleUser() {
        Log::logDebug("[CSocket] Handling TXN=NuEntitleUser");
        $sendPacket = "TXN=" . $this->txn;
        $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
        $this->sendPacket($packet);
        unset($packet);
    }

    public function handleNuSearchOwners() { //still needs to be finished
        Log::logDebug("[CSocket] Handling TXN=NuSearchOwners");
        $sendPacket = "TXN=" . $this->txn;
        $result = dbQuery(sprintf("SELECT * FROM `personas` WHERE 'persona_name' LIKE '%s'", $this->vars["screenName"]));
        $nrOwners = 0;
        if (!empty($result)) {
            $nrOwners = mysql_num_rows($result);
        }
        $sendPacket .= "\nretrieveUserIds=" . $nrOwners;

        $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
        $this->sendPacket($packet);
        unset($packet);
    }

    public function handleGetLockerURL() {
        Log::logDebug("[CSocket] Handling TXN=GetLockerURL");
        $sendPacket = "TXN=" . $this->txn .
                "\nURL=http://127.0.0.1/test.php";
        $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
        $this->sendPacket($packet);
        unset($packet);
    }

    public function handleNuGetEntitlements() {
        Log::logDebug("[CSocket] Handling TXN=NuGetEntitlements");
        $groupName = $this->vars['groupName'];
        $sendPacket = "TXN=" . $this->txn;
        switch ($groupName) {
            case 'BFBC2PC' :
                $sendPacket .= "\nentitlements.[]=3" .
                        "\nentitlements.0.entitlementId=1114490796" .
                        "\nentitlements.0.entitlementTag=ONLINE_ACCESS" .
                        "\nentitlements.0.grantDate=2011-07-30T0%3a6Z" .
                        "\nentitlements.0.groupName=BFBC2PC" .
                        "\nentitlements.0.productId=DR%3a156691300" .
                        "\nentitlements.0.status=ACTIVE" .
                        "\nentitlements.0.statusReasonCode=" .
                        "\nentitlements.0.terminationDate=" .
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
                break;
            case 'AddsVetRank' :
                $sendPacket .= "\nentitlements.[]=1" .
                        "\nentitlements.0.entitlementId=1114495162" .
                        "\nentitlements.0.entitlementTag=BFBC2%3aPC%3aADDSVETRANK" .
                        "\nentitlements.0.grantDate=2011-07-30T0%3a6Z" .
                        "\nentitlements.0.groupName=AddsVetRank" .
                        "\nentitlements.0.productId=" .
                        "\nentitlements.0.status=ACTIVE" .
                        "\nentitlements.0.statusReasonCode=" .
                        "\nentitlements.0.terminationDate=" .
                        "\nentitlements.0.version=0" .
                        "\nentitlements.0.userId=" . $this->userData->user_id;
                $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
                $this->sendPacket($packet);
                unset($packet);
                break;
            case 'BattlefieldBadCompany2' :
                $sendPacket .= "\nentitlements.[]=0";
                $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
                $this->sendPacket($packet);
                unset($packet);
                break;
            case 'NoVetRank' :
                $sendPacket .= "\nentitlements.[]=0";
                $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
                $this->sendPacket($packet);
                unset($packet);
                break;
            default : {
                    Log::logDebug(sprintf("could not handle TXN=%s group=%s", $this->txn, $groupName));
                    $sendPacket .= "\nentitlements.[]=0";
                    $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
                    $this->sendPacket($packet);
                    unset($packet);
                }
        }
    }

    public function handleGetTelemetryToken() {
        Log::logDebug("[CSocket] Handling TXN=GetTelemetryToken");
        $sendPacket = "TXN=" . $this->txn .
                "\ntelemetryToken=MTU5LjE1My4yMzUuMjYsOTk0NixlblVTLF7ZmajcnLfGpKSJk53K/4WQj7LRw9asjLHvxLGhgoaMsrDE3bGWhsyb4e6woYKGjJiw4MCBg4bMsrnKibuDppiWxYKditSp0amvhJmStMiMlrHk4IGzhoyYsO7A4dLM26rTgAo%3d" .
                "\nenabled=CA,MX,PR,US,VI,AD,AF,AG,AI,AL,AM,AN,AO,AQ,AR,AS,AW,AX,AZ,BA,BB,BD,BF,BH,BI,BJ,BM,BN,BO,BR,BS,BT,BV,BW,BY,BZ,CC,CD,CF,CG,CI,CK,CL,CM,CN,CO,CR,CU,CV,CX,DJ,DM,DO,DZ,EC,EG,EH,ER,ET,FJ,FK,FM,FO,GA,GD,GE,GF,GG,GH,GI,GL,GM,GN,GP,GQ,GS,GT,GU,GW,GY,HM,HN,HT,ID,IL,IM,IN,IO,IQ,IR,IS,JE,JM,JO,KE,KG,KH,KI,KM,KN,KP,KR,KW,KY,KZ,LA,LB,LC,LI,LK,LR,LS,LY,MA,MC,MD,ME,MG,MH,ML,MM,MN,MO,MP,MQ,MR,MS,MU,MV,MW,MY,MZ,NA,NC,NE,NF,NG,NI,NP,NR,NU,OM,PA,PE,PF,PG,PH,PK,PM,PN,PS,PW,PY,QA,RE,RS,RW,SA,SB,SC,clntSock,SG,SH,SJ,SL,SM,SN,SO,SR,ST,SV,SY,SZ,TC,TD,TF,TG,TH,TJ,TK,TL,TM,TN,TO,TT,TV,TZ,UA,UG,UM,UY,UZ,VA,VC,VE,VG,VN,VU,WF,WS,YE,YT,ZM,ZW,ZZ" .
                "\nfilters=" .
                "\ndisabled=";
        $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
        $this->sendPacket($packet);
        unset($packet);
    }

    public function handleNuDisablePersona() {
        $result = dbQuery(sprintf("DELETE FROM `personas` WHERE `persona_name`='%s' AND `user_id`='%s'", $this->vars["name"], $this->userData->user_id));
        unset($this->userData->persona_id);
        unset($this->userData->persona_name);
        $sendPacket = "TXN=" . $this->txn;
        $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
        $this->sendPacket($packet);
        unset($packet);
        //TODO: delete persona from database
    }

    public function handleGetStats() {
        Log::logDebug("[CSocket] Handling TXN=GetStats");
        $this->userData->loadPersonaStats(true);
        if(empty($this->userData->stats)){
            Log::logDebug('Failing load persona stats from database!');
            $this->handleGoodbye();
            return;
        }
        //fix rank
        //$this->userData->stats['rank'] = Util::getRankByScore($this->userData->stats['score']);
        $sendPacket = "TXN=" . $this->txn;

        $count = $this->vars['keys.[]'];

        $sendPacket .= "\nstats.[]=" . $count;
        for ($i = 0; $i < $count; $i++) {
            $keyname = "keys." . $i;
            $key = $this->vars[$keyname];
            $sendPacket .= "\nstats." . $i . ".key=" . $key;
            $sendPacket .= "\nstats." . $i . ".value=" . $this->userData->stats[$key];
        }
        $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
        $this->sendPacket($packet);
        unset($packet);
        unset($this->userData->stats);
    }

    public function handleGetRankedStats() {
        Log::logDebug("[CSocket] Handling TXN=GetRankedStats");
        $sendPacket = "TXN=" . $this->txn;
        $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
        $this->sendPacket($packet);
        unset($packet);
    }

    public function handleGetTopNAndStats() {
        Log::logDebug("[CSocket] Handling TXN=GetTopNAndStats");
        $sendPacket = "TXN=" . $this->txn;
        $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
        $this->sendPacket($packet);
        unset($packet);
    }

    public function handleGetRankedStatsForOwners() {
        Log::logDebug("[CSocket] Handling TXN=GetRankedStatsForOwners");
        $this->sendErrorPacket('GetRankedStatsForOwners empty');
    }

    public function handlePresenceSubscribe() {
        Log::logDebug("[CSocket] Handling TXN=PresenceSubscribe");
        $sendPacket = "TXN=" . $this->txn;
        $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
        $this->sendPacket($packet);
        unset($packet);
    }

    public function handleSetPresenceStatus() {
        Log::logDebug("[CSocket] Handling TXN=SetPresenceStatus");
        $sendPacket = "TXN=" . $this->txn;
        $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
        $this->sendPacket($packet);
        unset($packet);
    }

    public function handleModifySettings() {
        Log::logDebug("[CSocket] Handling TXN=ModifySettings");
        $sendPacket = "TXN=" . $this->txn;
        $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
        $this->sendPacket($packet);
        unset($packet);
    }

    public function handleGetMessages() {
        Log::logDebug("[CSocket] Handling TXN=GetMessages");
        $sendPacket = "TXN=" . $this->txn .
                "\nlocalizedMessage=\"Record not found\"";
        "\nmessages.[]=0";

        $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
        $this->sendPacket($packet);
        unset($packet);
    }

    public function handleGetAssociations() {
        $type = $this->vars['type'];
        Log::logDebug("[CSocket] Handling TXN=GetAssociations " . $type);
        $sendPacket = "TXN=" . $this->txn .
                "\ntype=" . $type .
                "\ndomainPartition.domain=eagames" .
                "\ndomainPartition.subDomain=BFBC2" .
                "\nowner.id=" . $this->userData->persona_id .
                "\nowner.name=" . $this->userData->persona_name .
                "\nowner.type=1";

        switch ($type) {
            case 'PlasmaMute':
                $sendPacket .= "\nmaxListSize=100" .
                        "\nmembers.[]=0";
                $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
                $this->sendPacket($packet);
                unset($packet);
                break;
            case 'PlasmaBlock':
                $sendPacket .= "\nmaxListSize=100" .
                        "\nmembers.[]=0";
                $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
                $this->sendPacket($packet);
                unset($packet);
                break;
            case 'PlasmaFriends':
                $sendPacket .= "\nmaxListSize=100" .
                        "\nmembers.[]=0";
                $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
                $this->sendPacket($packet);
                unset($packet);
                break;
            case 'PlasmaRecentPlayers':
                $sendPacket .= "\nmaxListSize=100" .
                        "\nmembers.[]=0";
                $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
                $this->sendPacket($packet);
                unset($packet);
                break;
            default: {
                    Log::logDebug(sprintf("[CSocket] Could not handle TXN=%s type=%s", $this->txn, $type));
                    return;
                }
        }
    }

    public function handleAddAssociations() {
        Log::logDebug("[CSocket] Handling TXN=AddAssociations");
        $sendPacket = "TXN=" . $this->txn .
                "\ntype=" . $this->vars['type'] .
                "\nresult.[]=0" .
                "\ndomainPartition.domain=eagames" .
                "\ndomainPartition.subDomain=BFBC2" .
                "\nmaxListSize=100";
        $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
        $this->sendPacket($packet);
        unset($packet);
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
            Log::logDebug("[CSocket] Send packet: " . $data);
        }
    }

    public function sendErrorPacket($message, $code = 999) {
        global $globalError;
        Log::logDebug($message, true);
        $sendPacket = "TXN=" . $this->txn .
                "\nlocalizedMessage=\"" . $message . "\"" .
                "\nerrorContainer.[]=0".
                "\nerrorCode=".$code;
        $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
        $this->sendPacket($packet);
        unset($packet);
    }

    // a User connects to us
    public function handleHello() {
        global $emulatorIP;
        $time = date("M-d-Y H%3\ai%3\as") . " UTC";
        Log::logDebug("[CSocket] Handling TXN=Hello");

        $sendPacket = 'TXN=' . $this->txn .
                "\ndomainPartition.domain=eagames" .
                "\nmessengerIp=messaging.ea.com" .
                "\nmessengerPort=13505" .
                "\ndomainPartition.subDomain=BFBC2" .
                "\nactivityTimeoutSecs=0" .
                "\ncurTime=\"" . $time . '"' .
                "\ntheaterIp=" . $emulatorIP .
                "\ntheaterPort=18395";
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
        Log::logDebug("[CSocket] Handling TXN=MemCheck");
    }

    public function handleGetPingSites() {
        Log::logDebug("[CSocket] Handling TXN=GetPingSites");
        $result = dbQuery("SELECT `ping_site_addr`,`ping_site_type`,`ping_site_name` FROM `ping_sites`");
        if (!empty($result)) {
            $count = mysql_num_rows($result);
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
            Log::logDebug("[CSocket] No PingSites in Database!");
        }
    }

    public function handlePing() {
        Log::logDebug("[CSocket] Handling TXN=Ping");
        $sendPacket = "TXN=Ping";
        $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
        $this->sendPacket($packet);
        unset($packet);
    }

    // Connection is shut down
    public function handleGoodbye() {

        Log::logDebug("[CSocket] Handling TXN=Goodbye");
        if (!empty($this->userData)) {
            //$this->userData->savePersonaStats();
            dbUserLogOut($this->userData);
        }
        if(!empty($this->socket)){
            fclose($this->socket);
            unset($this->socket);
        }
        $this->closed = true;
        CSocket::unsetInstance($this->name);
    }

    public function handleGetCountryList() {
        global $clientRegistration;
        if ($clientRegistration) {
            $sendPacket = "TXN=" . $this->txn .
                    "\ncountryList.[]=1" .
                    "\ncountryList.0.allowEmailsDefaultValue=1" .
                    "\ncountryList.0.registrationAgeLimit=" .
                    "\ncountryList.0.parentalControlAgeLimit=" .
                    "\ncountryList.0.ISOCode=USA" .
                    "\ncountryList.0.description=WorldWide";
            $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
            $this->sendPacket($packet);
            unset($packet);
        } else {
            $this->sendErrorPacket('Client registration is not allowed');
        }
    }

    public function handleNuGetTos() {
        $sendPacket = "TXN=" . $this->txn .
                "\ntos=Agree?" .
                "\nversion=";
        $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
        $this->sendPacket($packet);
        unset($packet);
    }

    public function handleNuAddAccount() {
        global $clientRegistration;
        if ($clientRegistration) {
            if (!preg_match("/([a-z0-9]*[-_\.]?[a-z0-9]+)*@([a-z0-9]*[-_]?[a-z0-9]+)+[\.][a-z]{2,3}([\.][a-z]{2};)?/i", $this->vars['nuid'])) {
                $this->sendErrorPacket("Wrong email format");
                return;
            }
            $result = dbQuery(sprintf("SELECT * FROM `users` WHERE `user_nuid` = '%s'", $this->vars['nuid']));
            if(!$result){
                $this->sendErrorPacket("MySQL Error: Cannot select users from database.", 99);
                return;
            }
            if (mysql_num_rows($result)) {
                $this->sendErrorPacket("That account name is already taken", 160);
                return;
            }
            $result1 = dbQuery(sprintf("INSERT INTO `users` SET `user_nuid` = '%s', `user_password`='%s', `user_online` = '0'", $this->vars['nuid'], $this->vars['password']));
            if (!$result) {
                $this->sendErrorPacket("MySQL error: Failed to register user", 99);
                return;
            }
            $sendPacket = $this->txn;
            $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
            $this->sendPacket($packet);
            unset($packet);
        } else {
            $this->sendErrorPacket('Client registration is not allowed');
        }
    }

    // User is logging in with email and password
    public function handleNuLogin() {
        global $bannedUserNuid;
        Log::logDebug("[CSocket] Handling TXN=NuLogin");

        if (isset($this->vars['nuid']) && isset($this->vars["password"])) {
            $nuid = $this->vars['nuid'];
            $password = $this->vars["password"];

            if (isset($bannedUserNuid[$nuid])) {
                Log::logDebug('[cache] Banned user login attempt - NUID: [' . $nuid . ']');
                $this->sendErrorPacket("User banned", 103);
                return;
            }

            $result = dbQuery(sprintf("SELECT `id` FROM `banned` WHERE `active` = 1 AND `user_nuid` = '%s' AND (`type` = 'c' OR `type` = 'x')", $nuid));
            if (!empty($result) && mysql_num_rows($result)) {
                $row = mysql_fetch_array($result);
                if (!isset($bannedUserNuid[$nuid])) {
                    $bannedUserNuid[$nuid] = '';
                    Log::logDebug('[db] Banned user login attempt - NUID: [' . $nuid . '] from IP: ' . $this->ip);
                }
                $this->sendErrorPacket("User banned", 103);
                return;
            }

            //Check login                    
            $result = dbQuery(sprintf("SELECT `user_id`, `user_online` FROM `users` WHERE `user_nuid`='%s' AND `user_password`='%s'", $nuid, $password));
            if (!$result){
                $this->sendErrorPacket("MySQL Error: Cannot select account from database", 99);
                return;
            }
            if (mysql_num_rows($result)) {
                $row = mysql_fetch_array($result);
                if(!empty($row['user_online'])){
                    Log::logDebug('Warning user already online.');
                }

                $this->userData = UserData::getInstance($nuid, true);
                $this->userData->user_id = $row['user_id'];
                $this->userData->profile_id = $row['user_id'];
                //$this->userData->user_lkey = Util::randomString(40);
                $encriptedLogin = $nuid . $password . time();
                $this->userData->user_lkey = md5($encriptedLogin);
                $this->userData->user_loggedin = true;

                self::getInstanceByUserId($row['user_id'], $this->name, true);


                $result = dbQuery(sprintf("UPDATE `users` SET `user_online`='1', `user_lastLogin`=CURRENT_TIMESTAMP(), `user_lkey`='%s' WHERE `user_id`='%s'", $this->userData->user_lkey, $this->userData->user_id));

                $sendPacket = "TXN=" . $this->txn .
                        "\nlkey=" . $this->userData->user_lkey .
                        "\nnuid=" . $this->userData->nuid .
                        "\nprofileId=" . $this->userData->user_id .
                        "\nuserId=" . $this->userData->user_id;
                if (isset($this->vars['returnEncryptedInfo']) && $this->vars['returnEncryptedInfo'] == '1') {
                    $sendPacket .= "\nencryptedLoginInfo=" . $this->userData->user_lkey;
                }

                $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
                $this->sendPacket($packet);
                unset($packet);
            } else {
                $this->sendErrorPacket("The username or password is incorrect", 122);
                return;
            }
        } else if (isset($this->vars['encryptedInfo'])) {
            $result = dbQuery(sprintf("SELECT `user_id`, `user_nuid`, `user_online` FROM `users` WHERE `user_lkey`='%s'", $this->vars['encryptedInfo']));
            if(!empty($row['user_online'])){
                Log::logDebug('Warning user already online.');
            }
            if(!$result){
                $this->sendErrorPacket("MySQL error: Cannot select user encripted info from database", 99);
                return;
            }
            if (mysql_num_rows($result)) {
                $row = mysql_fetch_array($result);

                if (isset($bannedUserNuid[$row['user_nuid']])) {
                    Log::logDebug('Banned user login attempt - NUID: [' . $row['user_nuid'] . ']');
                    $this->sendErrorPacket("User banned", 103);
                    return;
                }

                $res = dbQuery(sprintf("SELECT `id` FROM `banned` WHERE `active` = 1 AND `type` = 'c' AND `user_nuid` = '%s'", $row['user_nuid']));
                if (!empty($res) && mysql_num_rows($res)) {
                    $row2 = mysql_fetch_array($res);
                    if (!isset($bannedUserNuid[$row['user_nuid']])) {
                        $bannedUserNuid[$row['user_nuid']] = '';
                        Log::logDebug('Banned user login attempt - NUID: [' . $row['user_nuid'] . '] from IP: ' . $this->ip);
                    }
                    $this->sendErrorPacket("User banned", 103);
                    return;
                }

                $this->userData = UserData::getInstance($row['user_nuid'], true);
                $this->userData->user_id = $row['user_id'];
                $this->userData->profile_id = $row['user_id'];
                $this->userData->user_lkey = $this->vars['encryptedInfo'];
                $this->userData->user_loggedin = true;

                self::getInstanceByUserId($row['user_id'], $this->name, true);

                $result = dbQuery(sprintf("UPDATE `users` SET `user_online`='1', `user_lastLogin`=CURRENT_TIMESTAMP() WHERE `user_id`='%s'", $this->userData->user_id));

                $sendPacket = "TXN=" . $this->txn .
                        "\nlkey=" . $this->userData->user_lkey .
                        "\nnuid=" . $this->userData->nuid .
                        "\nprofileId=" . $this->userData->user_id .
                        "\nuserId=" . $this->userData->user_id .
                        "\nencryptedLoginInfo=" . $this->userData->user_lkey;

                $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
                $this->sendPacket($packet);
                unset($packet);
            } else {                              
                $this->sendErrorPacket('Wrong encrypted data', 122);
            }
        } else {
            $this->sendErrorPacket("No encrypted data", 122);
        }
    }

    public function handleNuGetPersonas() {
        Log::logDebug("[CSocket] Handling TXN=NuGetPersonas");

        $result = dbQuery(sprintf("SELECT `persona_id`, `persona_name` FROM `personas` WHERE `email`='%s'", $this->userData->nuid));
        
        if (!$result) {
            $this->sendErrorPacket('MySQL error: Failed select account personas.');
            return;
        }
        $nrPersonas = mysql_num_rows($result);
        
        $sendPacket = "TXN=" . $this->txn .
                "\npersonas.[]=" . $nrPersonas;

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
        Log::logDebug("[CSocket] Handling TXN=NuLoginPersonas");
        $result = dbQuery(sprintf("SELECT `persona_id`,`persona_name` FROM `personas` WHERE `email`='%s' AND `persona_name`='%s'", $this->userData->nuid, $this->vars['name']));

        if (!empty($result) && mysql_num_rows($result)) {
            $row = mysql_fetch_array($result);
            $this->userData = UserData::getInstanceByUserId($this->userData->user_id, $this->userData->nuid);
            unset($this->userData->stats);
            UserData::getInstanceByPersonaId($row['persona_id'], $this->userData->nuid);

            $this->userData->persona_id = $row['persona_id'];
            $this->userData->persona_name = $row['persona_name'];
            $this->userData->persona_lkey = Util::randomString(32);
            $this->userData->persona_loggedin = true;
            //$this->userData->loadPersonaStats(true);
            //$this->userData->deletePersonaStats();
            //$this->userData->loadStatsFromFile();
            //$this->userData->fixStats();
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
        if (!preg_match("/^[a-zA-Z0-9_\-]+$/", $this->vars['name'])) {
            $this->sendErrorPacket("Wrong characters used");
            return;
        }

        if (strlen($this->vars['name']) > 16 || strlen($this->vars['name']) < 4) {
            $this->sendErrorPacket("Persona name length is out of bounds");
            return;
        }

        $result = dbQuery(sprintf("SELECT `persona_name` FROM `personas` WHERE `persona_name`='%s'", $this->vars["name"]));
        if (!$result) {
            $this->sendErrorPacket("MySQL Error: failed selecting persona from database");
            return;
        }

        if (mysql_num_rows($result) == 0) {
            global $numberOfUserPersonas;
            $result = dbQuery(sprintf("SELECT `persona_id` FROM `personas` WHERE `user_id` = '%s'", $this->userData->user_id));
            if (isset($numberOfUserPersonas) && $numberOfUserPersonas && mysql_num_rows($result) >= $numberOfUserPersonas) {
                $this->sendErrorPacket("Maximum number of registered personas has been reached.");
                return;
            }

            $res = dbQuery(sprintf("INSERT INTO `personas` (`persona_name`, `ip`, `user_id`, `email`, `persona_lkey`, `persona_lastLogin`, `persona_online`) VALUES ('%s', '%s', '%s', '%s', '%s', CURRENT_TIMESTAMP, '0')", $this->vars['name'], $this->ip, $this->userData->user_id, $this->userData->nuid, Util::randomString(32)));
            if (!$res) {
                $this->sendErrorPacket("MySQL Error: failed creating new persona");
                return;
            } else {
                $persona_id = mysql_insert_id();
                if (!$this->userData->createDefultPersonaStats($persona_id)) {
                    Log::logDebug("Warning, stats could not be created for persona_id: " . $persona_id);
                }
                $sendPacket = "TXN=" . $this->txn;
                $packet = new Packet($this->type, 0x80000000, $this->type2Hex, $sendPacket);
                $this->sendPacket($packet);
                unset($packet);
            }
        } else {
            $this->sendErrorPacket("Selected Name already exists!", 160);
        }
    }

    public function processUserRank() {
        Log::logDebug("[CSocket] Handling Handling TYPE=rank");
        if (!$this->userData->rank_receiving) {
            Log::logDebug("[CSocket] First packet TYPE=rank");
            $this->userData->rank_receiving = true;
            $this->userData->rank_size = (int) $this->vars['size'];
            $this->userData->rank_data = "";
        }

        Log::logDebug("[CSocket] Add data TYPE=rank");
        $this->userData->rank_data.=$this->vars['data'];
        unset($this->vars);
        if (strlen($this->userData->rank_data) >= $this->userData->rank_size) {

            //$this->personaData->rank_data = str_replace('%3d', '=', $this->personaData->rank_data);
            $this->userData->loadPersonaStats(true);
            $decoded_data = base64_decode($this->userData->rank_data);
            if (!empty($decoded_data)) {
                $vars = Util::getRankVars($decoded_data);
                unset($decoded_data);
                $count = $vars['keys.[]']['value'];
                $sendPacket = "TXN=GetStats\nstats.[]=" . $count;

                for ($i = 0; $i < $count; $i++) {
                    $sendPacket .= sprintf("\nstats.%s.key=%s\nstats.%s.value=%s", $i, $vars['keys.' . $i]['value'], $i, $this->userData->stats[$vars['keys.' . $i]['value']]);
                }
                unset($vars);

                $decoded_size = strlen($sendPacket);
                $sendEncoded = base64_encode($sendPacket);
                unset($sendPacket);
                //$sendEncoded = str_replace('=', '', $sendEncoded);

                $encoded_size = strlen($sendEncoded);
                $size = $encoded_size;
                $first_packet = true;
                $packetSize = 8096;

                $splitPacked = chunk_split($sendEncoded, 8096, "\n");
                $splitPacked = explode("\n", $splitPacked);
                unset($sendEncoded);

                foreach ($splitPacked as $data) {
                    if (empty($data))
                        break;
                    $sendPacket = "decodedSize=" . $decoded_size .
                            "\nsize=" . $encoded_size .
                            "\ndata=" . $data;

                    $packet = new Packet($this->type, 0xb0000000, $this->type2Hex, $sendPacket);
                    $this->sendPacket($packet, true, $first_packet);
                    $first_packet = false;
                    unset($packet);
                }
                unset($splitPacked);
                unset($this->userData->rank_data);
                unset($this->userData->stats);
                $this->userData->rank_receiving = false;
            }
        }
    }

    public function checkValidResource() {
        if (is_resource($this->socket)) {
            return true;
        }
        $this->handleGoodbye();
        return false;
    }

}
