// BFBC2 Emulator - Plasma
// Made by Freaky123
// With help from Domo
// © Freaky (Freaky123) 2010-2011

#include "stdafx.h"

struct CPacketVar
{
	char	name[512];
	char	value[8224];
};
typedef std::list<CPacketVar> packetvar_list;

class CPacket {
private:
	char*				type;
	DWORD				type2;
	int					length;
	char*				data;
	packetvar_list		vars;

public:
	CPacket( char* createType, DWORD createType2, int createLength = -1, char* createData = NULL );
	~CPacket( );

	char* GetVar( char* varname );
	void SetVar( char* varname, const char* varvalue );
	void SetVar( char* varname, int varvalue );

	char* GetData( );
	char* GetType( );
	DWORD GetType2( );
};