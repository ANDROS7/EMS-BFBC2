// BFBC2 Emulator - Plasma
// Made by Freaky123
// With help from Domo
// © Freaky (Freaky123) 2010-2011

#include "CFramework.h"


CConfig::CConfig( CFramework* framework )
{
	fw		= framework;
}

bool CConfig::setFile( char* file )
{
	struct stat stFileInfo;

	if(stat(file, &stFileInfo) == 0)
	{
		filename		= file;
		return true;
	}
	
	fw->debug->warning(1, "CConfig", "Could not find config file");
	return false;
}

bool CConfig::saveVar( char* sectionName, char* varName, char* value )
{
	return WritePrivateProfileString( sectionName, varName, value, filename );
}

bool CConfig::saveVar( char* sectionName, char* varName, bool value )
{
	char* data	= "FALSE";
	if(value)
		data	= "TRUE";

	return WritePrivateProfileString( sectionName, varName, data, filename );
}

bool CConfig::saveVar( char* sectionName, char* varName, float value )
{
	char data[100];
	sprintf(data, "%f", value);

	return WritePrivateProfileString( sectionName, varName, data, filename );
}

bool CConfig::saveVar( char* sectionName, char* varName, int value )
{
	char data[100];
	sprintf(data, "%i", value);

	return WritePrivateProfileString( sectionName, varName, data, filename );
}

void CConfig::loadVarChar( char* sectionName, char* varName, char* value, int valueSize )
{
	if(GetPrivateProfileString( sectionName, varName, NULL, value, valueSize, filename ) <= 0)
		fw->debug->warning(2, "CConfig", "Could not get private profile string (%s, %s)", sectionName, varName);
}

void CConfig::loadVarBool( char* sectionName, char* varName, bool* value)
{
	char data[100];

	if(GetPrivateProfileString( sectionName, varName, NULL, data, 100, filename ) <= 0)
		fw->debug->warning(2, "CConfig", "Could not get private profile string (%s, %s)", sectionName, varName);

	if(stricmp(data, "TRUE") == 0)
		*value		= true;
	else if(stricmp(data, "FALSE") == 0)
		*value		= false;
	else
		*value		= false;

}

void CConfig::loadVarFloat( char *sectionName, char *varName, float *value )
{
	char data[100];

	if(GetPrivateProfileString( sectionName, varName, NULL, data, 100, filename ) <= 0)
		fw->debug->warning(2, "CConfig", "Could not get private profile string (%s, %s)", sectionName, varName);

	if(data)
		*value		= atof(data);
}

void CConfig::loadVarInt( char *sectionName, char *varName, int *value )
{
	char data[100];

	if(GetPrivateProfileString( sectionName, varName, NULL, data, 100, filename ) <= 0)
		fw->debug->warning(2, "CConfig", "Could not get private profile string (%s, %s)", sectionName, varName);

	if(data)
		*value		= atoi(data);
}