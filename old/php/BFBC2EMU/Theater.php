<?php

//error_reporting(0);
require_once('config.php');
require_once('lib/db.php');
require_once('lib/arrayKeys.php');
require_once('lib/Util.class.php');
require_once('lib/UserData.class.php');
require_once('lib/Packet.class.php');
require_once('lib/CTSocket.class.php');
require_once('lib/STSocket.class.php');
require_once('lib/SocketServer.class.php');
require_once('lib/Log.class.php');

if ($logToFile) {
    Log::createLogFile($theaterLogFile);
}

// Create plasma client
$theaterClient = SocketServer::getInstance('Theater-client', '0.0.0.0', 18395, 'tcp');
$theaterServer = SocketServer::getInstance('Theater-server', '0.0.0.0', 19026, 'tcp');
$cServer = $theaterClient->server;
$sServer = $theaterServer->server;
//$sServerUDP = $theaterServerUDP->server;
if (!$theaterClient->server) {
    echo "Failing createing theaterClient socket\n";
    die;
}
Log::logDebug('Created theater client socket');


if (!$theaterServer->server) {
    echo "Failing createing theaterServer socket";
    die;
}
Log::logDebug('Created theater server socket');

$cMaster[] = $cServer;
$sMaster[] = $sServer;

//Set servers offline
dbQuery('DELETE FROM `games`');
dbQuery('ALTER TABLE `games` AUTO_INCREMENT = 1');


while (1) {
    //handle theater client
    $read = $cMaster;
    $mod_fd = @stream_select($read, $_w = NULL, $_e = NULL, 0);
    if ($mod_fd === FALSE) {
        break;
    }
    for ($i = 0; $i < $mod_fd; ++$i) {
        if ($read[$i] === $cServer) {
            $newSocket = CTSocket::getInstanceByServer($cServer);
            if (!$newSocket) {
                continue;
            }
            $cMaster[] = $newSocket->socket;
            if ($newSocket) {
                $newSocket->receivePacket();
            }
        } else {
            $socket = CTSocket::getInstanceBySocket($read[$i]);
            if (!empty($socket->socket)) {
                $socket->receivePacket();
            }
            if (empty($socket->socket) || $socket->closed == true) { // connection closed
                Log::logDebug("connection " . $socket->name . " closed");
                $key_to_del = array_search($read[$i], $cMaster, TRUE);
                fclose($read[$i]);
                CTSocket::unsetInstance($socket->name);
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
            $newSocket = STSocket::getInstanceByServer($sServer);
            if (!$newSocket) {
                continue;
            }
            $sMaster[] = $newSocket->socket;
            if ($newSocket) {
                $newSocket->receivePacket();
            }
        } else {
            $socket = STSocket::getInstanceBySocket($read[$i]);
            if (!empty($socket->socket)) {
                $socket->receivePacket();
            }
            if (empty($socket->socket) || $socket->closed == true) { // connection closed
                Log::logDebug("connection " . $socket->name . " closed");
                $socket->handleGoodBye();
                $key_to_del = array_search($read[$i], $sMaster, TRUE);
                fclose($read[$i]);
                unset($sMaster[$key_to_del]);
            }
        }
    }


    usleep(100);
    //die;
}