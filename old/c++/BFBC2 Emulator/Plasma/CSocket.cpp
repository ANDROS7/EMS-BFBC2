// BFBC2 Emulator - Plasma
// Made by Freaky123
// With help from Domo
// © Freaky (Freaky123) 2010-2011

#include "CSocket.h"
#include "CFramework.h"

extern CFramework* fw;

CSocket::CSocket( char* createServerName, int createId, SOCKET createSock, sockaddr_in createAddress, bool ssl, SSL_CTX* ctx_sd )
{
	if(!fw)
	{
		delete this;
		return;
	}

	serverName		= createServerName;
	id				= createId;
	sock			= createSock;
	sockAddress		= createAddress;
	sockSSL			= ssl;
	packetCounter	= 0;

	if(sockSSL)
	{
		fw->debug->notification(4, "CSocket", "Trying to create socket SSL [%s:%i] (%s)", inet_ntoa(sockAddress.sin_addr), ntohs(sockAddress.sin_port), serverName);
		
		sock_ssl	= SSL_new(ctx_sd);
		if(!sock_ssl)
			fw->debug->warning(3, "CSocket", "Socket SSL isn't created [%s:%i] (%s)", inet_ntoa(sockAddress.sin_addr), ntohs(sockAddress.sin_port), serverName);
		if(!SSL_set_fd(sock_ssl, sock))
			fw->debug->warning(3, "CSocket", "Socket SSL fd isn't set [%s:%i] (%s)", inet_ntoa(sockAddress.sin_addr), ntohs(sockAddress.sin_port), serverName);

		int accept	= SSL_accept(sock_ssl);
		if(accept <0)
			fw->debug->warning(3, "CSocket", "Socket did not accept SSL %s [%s:%i] (%s)", ERR_error_string(ERR_get_error(), NULL), inet_ntoa(sockAddress.sin_addr), ntohs(sockAddress.sin_port), serverName);

		fw->debug->notification(4, "CSocket", "Socket SSL created [%s:%i] (%s)", inet_ntoa(sockAddress.sin_addr), ntohs(sockAddress.sin_port), serverName);
	}

	fw->debug->notification(1, "CSocket", "New socket created [%s:%i] (%s)", inet_ntoa(sockAddress.sin_addr), ntohs(sockAddress.sin_port), serverName);
}

CSocket::~CSocket( )
{
	if(sockSSL && sock_ssl)
	{
		SSL_shutdown(sock_ssl);
		SSL_free(sock_ssl);
	}

	if(sock)
		closesocket(sock);

	fw->debug->notification(1, "CSocket", "Socket deleted [%s:%i] (%s)", inet_ntoa(sockAddress.sin_addr), ntohs(sockAddress.sin_port), serverName);
}

int CSocket::GetId( )
{
	return id;
}

CPacket* CSocket::RecievePacket( )
{
	unsigned char header[12];
	unsigned char* data;

	fw->debug->notification(5, "CSocket", "Recieve 1 [%s:%i] (%s)", inet_ntoa(sockAddress.sin_addr), ntohs(sockAddress.sin_port), serverName);
	//Read the header
	int iResult		=  Recieve(header, 12);
	if(iResult <= 0)
	{
		if(sockSSL)
			fw->debug->notification(5, "CSocket", "Recieve 1.5 %i %i [%s:%i] (%s)", SSL_get_error(sock_ssl, NULL), ERR_get_error(), inet_ntoa(sockAddress.sin_addr), ntohs(sockAddress.sin_port), serverName );
		else
			fw->debug->notification(5, "CSocket", "Recieve 1.5 [%s:%i] (%s)", inet_ntoa(sockAddress.sin_addr), ntohs(sockAddress.sin_port), serverName );

		return NULL;
	}

	fw->debug->notification(5, "CSocket", "Recieve 2 [%s:%i] (%s)", inet_ntoa(sockAddress.sin_addr), ntohs(sockAddress.sin_port), serverName);

	//Create the package header
	char* type		= new char[4];
	DWORD type2;
	int length;
	memcpy((void*)type, header, 4);
	type2	= Decode(header+4, 4);
	length	= Decode(header+8, 4);

	fw->debug->notification(4, "CSocket", "Recieve \"%s\" \"0x%08x\" %i [%s:%i] (%s)\n", type,  type2, length, inet_ntoa(sockAddress.sin_addr), ntohs(sockAddress.sin_port), serverName );

	if(length <0)
		return NULL;

	//Read the data
	data		= new unsigned char[length-12];
	iResult		=  Recieve(data, length-12);
	if(iResult <= 0)
		return NULL;

	fw->debug->notification(3, "CSocket", "Recieve \"%s\" [%s:%i] (%s)\n", data, inet_ntoa(sockAddress.sin_addr), ntohs(sockAddress.sin_port), serverName);


	//Create the package
	char* sdata			= new char[length-12];
	sprintf(sdata, "%s", data);
	CPacket* packet		= new CPacket(type, type2, length, sdata);
	delete data;

	return packet;
};


int putxx(u8 *data, u32 num, int bytes) {
    int     i;

    for(i = 0; i < bytes; i++) {
        //data[i] = num >> (i << 3);    // little
        data[i] = num >> ((bytes - 1 - i) << 3);    // big
    }
    return(bytes);
}

int CSocket::SendPacket( CPacket* packet, bool count )
{
	/*fw->debug->notification(1, "CSocket", "Send0");
	char* data				= packet->GetData();
	int len					= strlen(data)+13;
	char* buf				= new char[len];

	fw->debug->notification(1, "CSocket", "Send1");

	DWORD type2		= packet->GetType2();
	if((type2 & 0x80000000) == 0x80000000) {				// this thing is useful to keep the counter of the
        if((type2 & 0x00ffffff) == 1) packetCounter = 0;	// packets so that I don't need
        packetCounter++;									// to modify my code if I add/remove one ea_send
        type2 = (type2 & 0xff000000) | packetCounter;
    }

	fw->debug->notification(1, "CSocket", "Send2");

	unsigned char header[12];
	memcpy(header, (void*)packet->GetType(), 4);
	Encode(header+4, (unsigned int)type2, 4);
	Encode(header+8, (unsigned int)len, 4);

	DWORD test1	= Decode(header+4, 4);
	int test2	= Decode(header+8, 4);

	fw->debug->notification(1, "CSocket", "Send3 0x%x %i", test1, test2);

	sprintf(buf, "%s%s", header, data);
	fw->debug->notification(2, "CSocket", "Send \"%s\" [%s:%i] (%s)", data, inet_ntoa(sockAddress.sin_addr), ntohs(sockAddress.sin_port), serverName );
	delete data;*/

	u8 *type1		= (u8*)packet->GetType();
	u32 type2		= (u32)packet->GetType2();
	u8 *fmt			= (u8*)packet->GetData();

    va_list ap;
    static int  buffsz  = 0;    // fast solution
    static u8   *buff   = NULL;
    int     slen,
            len,
			mlen;
	u8      *data = (u8*)malloc(1024);  

	if(strlen((char*)fmt) > 1024)
		data = (u8*)realloc(data, (strlen((char*)fmt)+128));

    va_start(ap, fmt);
	//len = vspr(&data, fmt, ap);
	len = vsprintf((char *)data, (char *)fmt, ap);
    va_end(ap);

	//len = strlen((char *)data);
    len++;  // EA uses the final NULL delimiter

    slen = 12 + len;
    if(slen > buffsz) {
        buffsz = slen;
        buff = (u8 *)realloc(buff, slen);
        if(!buff) printf("no buff");
    }
    memcpy(buff, type1, 4);
    if(count && (type2 & 0x80000000) == 0x80000000) {        // this thing is useful to keep the counter of the
        if((type2 & 0x00ffffff) == 1) packetCounter = 0;  // packets so that I don't need
        packetCounter++;                                  // to modify my code if I add/remove one ea_send
        type2 = (type2 & 0xff000000) | packetCounter;
    }
    putxx(buff + 4, type2, 4);
    putxx(buff + 8, slen, 4);
    memcpy(buff + 12, data, len);
    free(data);

	fw->debug->notification(3, "CSocket", "Send 0x%x \"%s\" [%s:%i] (%s)\n", type2, fmt, inet_ntoa(sockAddress.sin_addr), ntohs(sockAddress.sin_port), serverName );

	return Send((char*)buff, slen);
}

int CSocket::Send( char* buf, int len )
{
	if(sockSSL)
		return SSL_write(sock_ssl, buf, len);
	else
		return send( sock, buf, len, 0 );
}

int CSocket::Recieve( unsigned char* buf, int len )
{
	int count;
	for(int i = 0; i < len; i += count)
	{
		count		=  RecieveFunc(buf+i, len-i);
		if(count <= 0)
			return NULL;
	}
	return count;
}

int CSocket::RecieveFunc( unsigned char* buf, int len )
{
	if(sockSSL)
		return SSL_read(sock_ssl, buf, len);
	else
		return recv( sock, (char *)buf, len, 0 );
}

unsigned int CSocket::Decode( unsigned char* data, int bytes ) {
    int num;
    unsigned int i;
    for(num = i = 0; i < bytes; i++) {
        //num |= (data[i] << (i << 3)); // little
        num |= (data[i] << ((bytes - 1 - i) << 3)); // big
    }
    return(num);
}

unsigned int CSocket::Encode( unsigned char* data, unsigned int num, int bytes)
{
    unsigned int i;
    for(i = 0; i < bytes; i++) {
        //data[i] = num >> (i << 3);    // little
        data[i] = num >> ((bytes - 1 - i) << 3);    // big
    }
    return(bytes);
}