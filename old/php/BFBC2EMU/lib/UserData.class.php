<?php

/**
 * Description of UserData
 *
 * @author Sinthetix
 */
class UserData {

    public static $_instance = array();
    public static $_instanceByUserId = array();
    public static $_instanceByPersonaId = array();
    public $nuid;
    public $user_lkey;
    public $user_id;
    public $user_loggedin;
    public $profile_id;
    public $persona_id;
    public $persona_name;
    public $persona_lkey;
    public $persona_loggedin;
    public $rank_receiving = false;
    public $rank_sending = false;
    public $rank_data_sent = false;
    public $rank_data;
    public $rank_size;
    public $stats;

    public static function getInstance($nuid, $forceCreate = false) {
        if (isset(self::$_instance[$nuid])) {
            if($forceCreate){
                self::unsetUserData(self::$_instance[$nuid]);
            } else {
                return self::$_instance[$nuid];                
            }
        }
        
        $instance = new UserData();
        $instance->nuid = $nuid;
        self::$_instance[$nuid] = $instance;
        return $instance;
    }
    
    public static function getInstanceByUserId($userId, $nuid = null) {
        if (isset(self::$_instanceByUserId[$userId])) {
            return self::$_instanceByUserId[$userId];
        }
        if(isset($nuid)){
            $instance = self::getInstance($nuid);
            self::$_instanceByUserId[$userId] = $instance;
            return $instance;
        }
       return false;        
    }
    public static function getInstanceByPersonaId($personaId, $nuid = null) {
        if (isset(self::$_instanceByPersonaId[$personaId])) {
            return self::$_instanceByPersonaId[$personaId];
        }
        if(isset($nuid)){
            $instance = self::getInstance($nuid);
            self::$_instanceByPersonaId[$personaId] = $instance;
            return $instance;
        }
       return false;        
    }
    
    public static function unsetUserData($userData){
        if(empty($userData)){
            return;
        }
        $userId =  $userData->user_id;
        $personaId =  $userData->persona_id;
        unset(self::$_instanceByUserId[$userId]);
        unset(self::$_instanceByPersonaId[$personaId]);        
    }

  
    function createDefultPersonaStats($persona_id = false) {
        global $rootPath;
        if (!$persona_id) {
            $persona_id = $this->persona_id;
            if (!$persona_id) {
                Log::logDebug("createDefultPersonaStats: persona_id is not set");
                return false;
            }
        }
        $initStats = file_get_contents($rootPath . '/include/initstats.ini');
//        $initStats = unserialize($initStats);
//        foreach($initStats as $key=>$val){
//            $initStats[$key] = 0.0;
//        }
//        $initStats = serialize($initStats);
        return dbQuery(sprintf("INSERT INTO `stats` SET `persona_id`='%s', `persona_stats`='%s'", $persona_id, $initStats));
        unset($initStats);
    }

    function deletePersonaStats($persona_id = false) {
        global $rootPath;
        if (!$persona_id) {
            $persona_id = $this->persona_id;
            if (!$persona_id) {
                Log::logDebug("persona_id is not set");
                return false;
            }
        }
        $initStats = file_get_contents($rootPath . '/include/initstats.ini');
        return dbQuery(sprintf("UPDATE `stats` SET `persona_stats`='%s' WHERE `persona_id`='%s'", $initStats, $persona_id));
        unset($initStats);        
    }

    public function loadPersonaStats($forced = false) {
        if (!empty($this->stats) && !$forced) {
            return true;
        }
        if (empty($this->persona_id)) {
            Log::logDebug("loadPersonaStats: persona_id is not set");
            return false;
        }
        $persona_id = $this->persona_id;
        $result = dbQuery(sprintf("SELECT `persona_stats` FROM  `stats` WHERE `persona_id`='%s'", $persona_id));
        if (mysql_num_rows($result)) {
            $row = mysql_fetch_array($result);
            $row = $row['persona_stats'];
            //$row = stripslashes($row['persona_stats']);
            $row = unserialize($row);
            if (is_array($row)) {
                $this->stats = $row;
                unset($row);
            }
        } else {
            return false;
//            $this->createDefultPersonaStats();
//            $result = dbQuery(sprintf("SELECT `persona_stats` FROM  `stats` WHERE `persona_id`='%s'", $persona_id));
//            if (mysql_num_rows($result)) {
//                $row = mysql_fetch_array($result);
//                $row = stripslashes($row['persona_stats']);
//                $row = unserialize($row);
//                if (is_array($row)) {
//                    $this->stats = $row;
//                    unset($row);
//                }
//            }
        }
        return true;
    }

    public function loadStatsFromFile() {
        global $rootPath;
        if (!isset($persona_id)) {
            $persona_id = $this->persona_id;
            if (!$persona_id) {
                Log::logDebug("persona_id is not set");
                return false;
            }
        }
        $stats = file_get_contents($rootPath . '/include/serializedGrabedStats.txt');
        $this->stats = unserialize($stats);
        return dbQuery(sprintf("UPDATE `stats` SET `persona_stats`='%s' WHERE `persona_id`='%s'", $stats, $persona_id));
        unset($stats);
    }

    public function savePersonaStats() {
        $persona_id = $this->persona_id;
        if (empty($this->stats)) {
            return;
        }
        $stats = serialize($this->stats);
        $result = dbQuery(sprintf("UPDATE `stats` SET  `persona_stats` = '%s' WHERE `persona_id`='%s'", $stats, $persona_id));
        if (!$result) {
            Log::logDebug('Failed saving user:' . $this->user_id . ', persona:' . $this->persona_name . ' stats');
        }
        unset($stats);
    }

    public function fixStats() {
        if (empty($this->stats)) {
            return false;
        }
        $gscore = 0;
        $gscore += (float) $this->stats['sc_assault'];
        $gscore += (float) $this->stats['sc_support'];
        $gscore += (float) $this->stats['sc_recon'];
        $gscore += (float) $this->stats['sc_demo'];
        $gscore += (float) $this->stats['sc_vehicle'];
        $gscore += (float) $this->stats['sc_award'];
        $gscore = sprintf("%.0f", $gscore);
        
        if (!empty($gscore)) {
            $this->stats['score'] = $gscore;
            $rank = Util::getRankByScore($gscore);
            if($rank < $this->stats['rank']){
                return false;
            }
            $this->stats['rank'] = $rank;
            return true;
        }
        return false;
    }

}

