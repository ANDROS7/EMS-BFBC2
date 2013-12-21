// BFBC2 Emulator - Plasma
// Made by Freaky123
// With help from Domo
// © Freaky (Freaky123) 2010-2011

#include "stdafx.h"
#include "CFramework.h"
#include "CSocketServer.h"

extern CFramework* fw;
CSocketServer* socketServerS;
void serverSocket(LPVOID lpParam);

void serverListener(LPVOID lpParam)
{
	socketServerS		= new CSocketServer("Theater-server", "ALL", 18326);

	while(socketServerS)
	{
		CSocket* sock	= socketServerS->Accept( );

		_beginthread(serverSocket, 0, (void*)sock);
	}
}

void serverSocket(LPVOID lpParam)
{
	CSocket* sock	= (CSocket*) lpParam;

	while(sock)
	{
		CPacket* recievePacket	= sock->RecievePacket( );

		if(recievePacket == NULL)
			break;
	}

	socketServerS->Remove(sock);
}