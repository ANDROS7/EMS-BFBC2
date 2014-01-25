// BFBC2 Emulator - Plasma
// Made by Freaky123
// With help from Domo
// © Freaky (Freaky123) 2010-2011

#include "stdafx.h"
#include "CFramework.h"

CFramework* fw;
void serverListener(LPVOID lpParam);


int _tmain(int argc, _TCHAR* argv[])
{
	fw		= new CFramework( );

	//Load the config
	fw->config->setFile(".\\plasma_config.ini");
	fw->loadConfig();

	//Connect to the database
	fw->database->Connect();

	//Create the server listener
	_beginthread(serverListener, 0, NULL);

	fw->handler->Run();

	//Keep alive
	while(true)
	{
		Sleep(30000);
	}
	
	delete fw;

	return 0;
}

