<?php

//error_reporting(0);
require_once('config.php');
require_once('lib/db.php');
require_once('lib/Util.class.php');
require_once('lib/UserData.class.php');
require_once('lib/Packet.class.php');
require_once('lib/CSocket.class.php');
require_once('lib/SSocket.class.php');
require_once('lib/SocketServer.class.php');
require_once('lib/Log.class.php');

if ($logToFile) {
    Log::createLogFile($plasmaLogFile);
}

// Create plasma client
$plasmaClient = SocketServer::getInstance('Plasma-client', '0.0.0.0', 18390, 'ssl');
$plasmaServer = SocketServer::getInstance('Plasma-server', '0.0.0.0', 19021, 'ssl');
$cServer = $plasmaClient->server;
$sServer = $plasmaServer->server;
if (!$plasmaClient->server) {
    echo "Failing createing plasmaClient socket\n";
    die;
}
Log::logDebug('Created plasma client socket');

if (!$plasmaServer->server) {
    echo "Failing createing plasmaServer socket";
    die;
}
Log::logDebug('Created plasma server socket');

//refresh banned data
dbQuery(sprintf("UPDATE `banned` SET `active` = 0 WHERE `expire` IS NOT NULL AND `expire` < '%s'",date('Y-m-d H:i:s')));
$bannedUserIP = array();
$bannedUserNuid = array();
$bannedServers = array();

$cMaster[] = $cServer;
$sMaster[] = $sServer;

//set users offline
dbQuery('UPDATE `users` SET `user_online` = 0');
//set personas offline
dbQuery('UPDATE `personas` SET `persona_online` = 0');

while (1) {
    //handle plsma client
    $read = $cMaster;
    $mod_fd = @stream_select($read, $_w = NULL, $_e = NULL, 0);
    if ($mod_fd === FALSE) {
        break;
    }
    for ($i = 0; $i < $mod_fd; ++$i) {
        if ($read[$i] === $cServer) {            
            $newSocket = CSocket::getInstanceByServer($cServer);
            if (!$newSocket) {
                continue;
            }
            $cMaster[] = $newSocket->socket;
            if ($newSocket) {
                $newSocket->receivePacket();
            }
        } else {
            $socket = CSocket::getInstanceBySocket($read[$i]);
            if (!empty($socket->socket)) {
                $socket->receivePacket();
            }
            if (empty($socket->socket) || $socket->closed == true) { // connection closed
                Log::logDebug("connection " . $socket->name . " closed");
                $key_to_del = array_search($read[$i], $cMaster, TRUE);
                if(is_resource($read[$i])){
                    fclose($read[$i]);                    
                }
                CSocket::unsetInstance($socket->name);
                unset($cMaster[$key_to_del]);
            }
        }
    }


    //handle plasma server
    $read = $sMaster;
    $mod_fd = @stream_select($read, $_w = NULL, $_e = NULL, 0);
    if ($mod_fd === FALSE) {
        break;
    }
    for ($i = 0; $i < $mod_fd; ++$i) {
        if ($read[$i] === $sServer) {
            $newSocket = SSocket::getInstanceByServer($sServer);
            if (!$newSocket) {
                continue;
            }
            $sMaster[] = $newSocket->socket;
            if ($newSocket) {
                $newSocket->receivePacket();
            }
        } else {
            $socket = SSocket::getInstanceBySocket($read[$i]);
            if (!empty($socket->socket)) {
                $socket->receivePacket();
            }
            if (empty($socket->socket) || $socket->closed == true) { // connection closed
                Log::logDebug("connection " . $socket->name . " closed");
                $key_to_del = array_search($read[$i], $sMaster, TRUE);
                fclose($read[$i]);
                SSocket::unsetInstance($socket->name);
                unset($sMaster[$key_to_del]);
            }
        }
    }

    usleep(100);
}