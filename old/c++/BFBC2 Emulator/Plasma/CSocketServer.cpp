// BFBC2 Emulator - Plasma
// Made by Freaky123
// With help from Domo
// © Freaky (Freaky123) 2010-2011

#include "CSocketServer.h"
#include "CFramework.h"


extern CFramework* fw;

CSocketServer::CSocketServer( char* name, char* ip, int port, bool ssl )
{
	if(!fw)
	{
		delete this;
		return;
	}

	sockName		= name;
	sockSSL			= ssl;
	fw->debug->notification(2, "CSocketServer", "Trying to create socket server (%s %s:%i)", sockName, ip, port);

	//Startup WSA
	WSADATA wsaData;
	if(WSAStartup(MAKEWORD(2, 0), &wsaData) != 0)
	{
		fw->debug->error("CSocketServer", "Could not start WSA");
		return;
	}

	//Create the socket
	sock	= socket(AF_INET, SOCK_STREAM, 0);
	if(sock == INVALID_SOCKET)
	{
		fw->debug->error("CSocketServer", "Could not create socket");
		return;
	}

	//Setup the socket_address structure
	memset(&sockAddress, 0, sizeof(sockAddress));
	sockAddress.sin_family			= AF_INET;
	sockAddress.sin_port			= htons(port);
	sockPort						= port;

	//Get the right ip
	if(stricmp(ip, "ALL") == 0)
		sockAddress.sin_addr.s_addr		= htonl(INADDR_ANY);
	else
		sockAddress.sin_addr.s_addr		= inet_addr(ip);

	//Try to bind the socket
	if(bind(sock, (sockaddr *)&sockAddress, sizeof(sockAddress)) == SOCKET_ERROR)
	{
		fw->debug->error("CSocketServer", "Could not bind socket");
		closesocket(sock);
		return;
	}

	//Try to listen on the socket
	if(listen(sock, 5) != 0)
	{
		fw->debug->error("CSocketServer", "Could not listen on socket");
		return;
	}

	//Load SSL
	ctx_sd		= NULL;
	if(sockSSL)
	{
		SSL_library_init(); /* load encryption & hash algorithms for SSL */                
		SSL_load_error_strings(); /* load the error strings for good error reporting */

		ctx_sd		= SSL_CTX_new(SSLv3_method());

		SSL_CTX_set_cipher_list(ctx_sd, "ALL");
		SSL_CTX_set_options(ctx_sd, SSL_OP_ALL);
		//SSL_CTX_use_certificate_file(ctx_sd, "mycert.pem", SSL_FILETYPE_PEM);
		//SSL_CTX_use_PrivateKey_file(ctx_sd, "mycert.pem", SSL_FILETYPE_PEM);
		SSL_CTX_use_certificate_ASN1(ctx_sd, sizeof(SSL_CERT_X509), SSL_CERT_X509);
		SSL_CTX_use_PrivateKey_ASN1(EVP_PKEY_RSA, ctx_sd, SSL_CERT_RSA, sizeof(SSL_CERT_RSA));
		SSL_CTX_set_verify_depth(ctx_sd, 1);
	}

	fw->debug->notification(1, "CSocketServer", "Created socket server (%s %s:%i)", sockName, ip, port);
}

CSocketServer::~CSocketServer( )
{
	fw->debug->notification(2, "CSocketServer", "Trying to delete socket server (%s)", sockName);

	//Disconnect all clients
	for(socket_list::iterator so =sockList.begin(); so!=sockList.end(); so++)
	{
		sockList.remove(*so);
		delete *so;
	}

	//Remove the socket
	if(sockSSL)
		SSL_CTX_free(ctx_sd);
	if(sock)
		closesocket(sock);

	fw->debug->notification(1, "CSocketServer", "Deleted socket server (%s)", sockName);
}

CSocket* CSocketServer::Accept( )
{
	sockaddr_in	newSockAddress;
	int	sockLen		= sizeof(newSockAddress);

	//Try to accept new connections
	SOCKET newSock	= accept(sock, (sockaddr *)&newSockAddress, &sockLen);
	if(newSock == INVALID_SOCKET)
	{
		fw->debug->notification(3, "CSocketServer", "Could not accept client on socket (%s)", sockName);
		return NULL;
	}

	CSocket* r		= new CSocket(sockName, sockCount, newSock, newSockAddress, sockSSL, ctx_sd);
	sockList.push_back(r);

	fw->debug->notification(2, "CSocketServer", "New socket connection [%s:%i] (%s)", inet_ntoa(newSockAddress.sin_addr), ntohs(newSockAddress.sin_port) ,sockName);

	sockCount++;
	return r;
}

void CSocketServer::Remove( CSocket* removeSocket )
{
	sockList.remove(removeSocket);
	delete removeSocket;

	fw->debug->notification(2, "CSocketServer", "Removed socket connection (%s)", sockName);
}