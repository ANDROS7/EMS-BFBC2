// BFBC2 Emulator - Plasma
// Made by Freaky123
// With help from Domo
// © Freaky (Freaky123) 2010-2011

#include "stdafx.h"
#include "CSocket.h"


#pragma once
class CSocketServer {
private:
	SOCKET			sock;
	char*			sockName;
	sockaddr_in		sockAddress;
	int				sockPort;
	bool			sockSSL;
	socket_list		sockList;
	int				sockCount;
	SSL_CTX*		ctx_sd;

public:
	CSocketServer( char* name, char* ip, int port, bool ssl = false );
	~CSocketServer( );

	CSocket*	Accept( );
	void		Remove( CSocket* removeSocket );
};