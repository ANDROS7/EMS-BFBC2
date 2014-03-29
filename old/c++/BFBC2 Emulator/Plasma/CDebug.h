// BFBC2 Emulator - Plasma
// Made by Freaky123
// With help from Domo
// © Freaky (Freaky123) 2010-2011

#include "stdafx.h"

class CFramework;


#pragma once
class CDebug {
private:
	CFramework*				fw;
	FILE*					fp;

	int						fileNotificationLevel;
	int						fileWarningLevel;
	int						consoleNotificationLevel;
	int						consoleWarningLevel;

public:
	CDebug( CFramework* framework );
	~CDebug( );

	void loadConfig( );

	void notification( int level, char* from, char* message, ... );
	void warning( int level, char* from, char* message, ... );
	void error( char* from, char* message, ... );

	bool setFile( char* filename );
	void setFileLevel( int notification, int warning );
	void setConsoleLevel( int notification, int warning );
};