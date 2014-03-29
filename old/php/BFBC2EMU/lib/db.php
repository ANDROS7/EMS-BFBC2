<?php

$dbLink = mysql_connect($dbHost, $dbUser, $dbPassword);
if (!$dbLink) {
    die('Could not connect: ' . mysql_error());
}
$db = mysql_select_db($dbName, $dbLink);
if (!$db) {
    die('Can\'t use database : ' . mysql_error());
}

function dbQuery($q) {
    global $logDatabase, $dbLink;
    if (!mysql_ping($dbLink)) {
        echo 'MySQL lost connection!';
        return false;
    }
    $ret = mysql_query($q);
    if (!$ret) {
        Log::logDebug("MySQL Error: " . mysql_errno());
        Log::logDebug("Query: ".$q);
        return false;
    } else if($logDatabase){
        Log::logDebug("Query: ".$q);
    }
    return $ret;
}

function dbUserLogOut($userData) {    
    if (!empty($userData->persona_loggedin)) {
        if (!empty($userData->persona_loggedin)) {
            dbQuery(sprintf("UPDATE `personas` SET `persona_online`='0' WHERE `persona_id`='%s'", $userData->persona_id));
            $userData->persona_loggedin = false;
        }
    }
    if (!empty($userData->user_loggedin)) {
        if (!empty($userData->user_loggedin)) {
            dbQuery(sprintf("UPDATE `users` SET `user_online`='0' WHERE `user_id`='%s'", $userData->user_id));
            $userData->user_loggedin = false;
        }
    }
}
