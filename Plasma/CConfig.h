// BFBC2 Emulator - Plasma
// Made by Freaky123
// With help from Domo
// © Freaky (Freaky123) 2010-2011

#include "stdafx.h"

class CFramework;


#pragma once
class CConfig {
private:
	CFramework*				fw;
	char*					filename;

public:
	CConfig( CFramework* framework );

	bool setFile( char* file );
	
	bool saveVar( char* sectionName, char* varName, char* value );
	bool saveVar( char* sectionName, char* varName, bool value );
	bool saveVar( char* sectionName, char* varName, float value );
	bool saveVar( char* sectionName, char* varName, int value );

	void loadVarChar( char* sectionName, char* varName, char* value, int valueSize );
	void loadVarBool( char* sectionName, char* varName, bool* value );
	void loadVarFloat( char* sectionName, char* varName, float* value );
	void loadVarInt( char* sectionName, char* varName, int* value );
};