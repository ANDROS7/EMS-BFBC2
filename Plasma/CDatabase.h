// BFBC2 Emulator - Plasma
// Made by Freaky123
// With help from Domo
// © Freaky (Freaky123) 2010-2011

#include "stdafx.h"

class CFramework;


#pragma once
class CDatabase {
private:
	CFramework* fw;

	sql::Driver* driver;
	sql::Connection* con;
	sql::Statement* stmt;

	char host[128];
	int port;
	char username[128];
	char password[128];
	char database[128];

public:
	CDatabase( CFramework* framework );
	~CDatabase( );

	void loadConfig( );

	bool Connect( );
	bool Disconnect( );

	sql::ResultSet* Query( char* data, ... );
};
