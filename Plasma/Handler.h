// BFBC2 Emulator - Plasma
// Made by IceKobrin
// Started: 2014-01-17 

#include "stdafx.h"

class CFramework;
#pragma once

class Handler {
private:
	CFramework*				fw;
public:
	Handler( CFramework* framework );

	void Run();
	bool HandleHello( );
	bool HandleMemCheck( );
};
