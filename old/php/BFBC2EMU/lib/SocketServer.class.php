<?php

/**
 * Description of SocketServer
 *
 * @author Sinthetix
 */
class SocketServer {

    public static $_instance = array();
    public $socketCount = 0;
    public $server = null;

    public static function getInstance($name, $ip, $port, $type) {
        try {
            if (isset(SocketServer::$_instance[$name])) {
                return SocketServer::$_instance[$name];
            }

            $instance = new SocketServer();            

            // Create the server socket
            //'ssl://0.0.0.0:18390'  
            $addr = $type . '://' . $ip . ':' . $port;
            if ($type == 'ssl') {
                $context = Util::getSSLContext();
                $instance->server = stream_socket_server($addr, $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
            } else if ($type == 'tcp') {
                $instance->server = stream_socket_server($addr, $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN);   
            } else if ($type == 'udp') {
                $instance->server = stream_socket_server($addr, $errno, $errstr, STREAM_SERVER_BIND);
                if(!stream_set_blocking($instance->server, 0)) {
		    throw new Exception('UDPServer: Impossible to set non-blocking mode');
		}
            }

            //$instance->server = stream_socket_client($addr, $errno, $errstr, 0, STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT, $context);
            //stream_set_blocking($socket, false);

            if ($errstr) {
                throw new Exception($errstr);
            }
            self::$_instance[$name] = $instance;
            return $instance;
        } catch (Exception $e) {
            Log::logDebug($e->getMessage());
        }
    }

    public function Accept() {
        try {                    
            Socket::getInstance($socket);          
            $this->socketCount++;
            return $newSock;
        } catch (Exception $e) {
            Log::logDebug($e->getMessage());
        }
    }

}
