// BFBC2 Emulator - Plasma
// Made by Freaky123
// With help from Domo
// © Freaky (Freaky123) 2010-2011

#include "stdafx.h"
#include "CPacket.h"

#pragma once
class CSocket {
private:
	int				id;
	SOCKET			sock;
	sockaddr_in		sockAddress;
	bool			sockSSL;
	int				packetCounter;
	char*			serverName;
	SSL*			sock_ssl;

	//int Send( char* buf, int len );
	int Recieve( unsigned char* buf, int len );
	int RecieveFunc( unsigned char* buf, int len );

	unsigned int Decode( unsigned char* data, int bytes );
	unsigned int Encode( unsigned char* data, unsigned int num, int bytes );

public:
	CSocket( char* createServerName, int createId, SOCKET createSock, sockaddr_in createAddress, bool ssl = false, SSL_CTX* ctx_sd = NULL );
	~CSocket( );

	int	GetId( );

	int Send( char* buf, int len );
	int SendPacket( CPacket* packet, bool count = true );
	CPacket* RecievePacket( );
};

typedef std::list<CSocket*> socket_list;