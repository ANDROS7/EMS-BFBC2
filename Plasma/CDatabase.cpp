// BFBC2 Emulator - Plasma
// Made by Freaky123
// With help from Domo
// © Freaky (Freaky123) 2010-2011

#include "CFramework.h"


CDatabase::CDatabase( CFramework* framework )
{
	fw		= framework;

	//Set up mysql
	driver	= get_driver_instance();
	con		= NULL;
}

CDatabase::~CDatabase( )
{
	if(con)
		delete con;
}

void CDatabase::loadConfig( )
{
	//Load config values
	fw->config->loadVarChar("database", "host", host, 128);
	fw->config->loadVarInt("database", "port", &port);
	fw->config->loadVarChar("database", "username", username, 128);
	fw->config->loadVarChar("database", "password", password, 128);
	fw->config->loadVarChar("database", "database", database, 128);
}

bool CDatabase::Connect( )
{
	//Make the connection url
	char connectUrl[265];
	sprintf(connectUrl, "tcp://%s:%i", host, port);

	//Try to connect
	con = driver->connect(connectUrl, username, password);
	
	if(!con)
	{
		fw->debug->error("CDatabase", "Could not connect to database");
		return false;
	}

	//Select the database
	con->setSchema(database);
	fw->debug->notification(1, "CDatabase", "Connected to the database");

	//Create statement
	stmt	= con->createStatement();

	return true;
}

bool CDatabase::Disconnect( )
{
	if(stmt)
		delete stmt;
	
	if(con)
		delete con;

	return true;
}

sql::ResultSet* CDatabase::Query( char* query, ... )
{
	char data[2048];

	//Create the query
	if(query)
	{
		va_list ap;
        va_start(ap, query);
		vsprintf(data, query, ap);
		va_end(ap);
    }

	//Execute the query
	bool retvalue			= stmt->execute(data);
	fw->debug->notification(3, "CDatabase", "Query (%s)", data);

	sql::ResultSet* res;
	if(retvalue)
		res		= stmt->getResultSet();
	else
		res		= NULL;

	return res;
}