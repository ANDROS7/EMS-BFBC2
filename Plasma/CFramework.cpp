// BFBC2 Emulator - Plasma
// Made by Freaky123
// With help from Domo
// © Freaky (Freaky123) 2010-2011

#include "CFramework.h"

CFramework::CFramework( )
{
	//Create classes
	debug		= new CDebug( this );
	config		= new CConfig( this );
	database	= new CDatabase( this );
}

CFramework::~CFramework( )
{
	//Delete classes
	delete debug;
	delete config;
}

void CFramework::loadConfig( )
{
	//Load classes config
	debug->loadConfig( );
	database->loadConfig( );
}

char* CFramework::getTime()
{
    time_t		datex;
    struct tm*	tmx;
    char*		months[12]	= { "Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec" };

    time(&datex);
    tmx = localtime(&datex);

	char* str;
	str		= new char[512];
    sprintf(str,"\"%3s-%02d-%4d %02d%%3a%02d%%3a%02d UTC\"",
		months[tmx->tm_mon], tmx->tm_mday, (1900+tmx->tm_year),
        tmx->tm_hour, tmx->tm_min, tmx->tm_sec);
	return str;
}

char* CFramework::randomString(int len)
{
	static const char alphanum[] =
        "0123456789"
        "ABCDEFGHIJKLMNOPQRSTUVWXYZ"
        "abcdefghijklmnopqrstuvwxyz";

    char* data		= new char[len];
	for (int i = 0; i < len; ++i) {
        data[i] = alphanum[rand() % (sizeof(alphanum) - 1)];
    }
	data[len]		= 0;

    return data;
}