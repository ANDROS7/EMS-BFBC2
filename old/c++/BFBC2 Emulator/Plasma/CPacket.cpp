// BFBC2 Emulator - Plasma
// Made by Freaky123
// With help from Domo
// © Freaky (Freaky123) 2010-2011

#include "CPacket.h"
#include "CFramework.h"

extern CFramework* fw;

CPacket::CPacket( char* createType, DWORD createType2, int createLength, char* createData )
{
	if(!fw)
	{
		delete this;
		return;
	}

	type		= createType;
	type2		= createType2;
	length		= 12;

	//When no length and data
	if(createLength < 0)
	{
		data		= new char[0];
		return;
	}

	length		= createLength;

	data		= new char[length-12];
	strcpy(data, createData);

	fw->debug->notification(4, "CPacket", "Recieve data \"%s\"\n", data );

	//Splitting the data in to vars
	char *line	= NULL;
	line			= strtok( data, "\n" );
	while( line != NULL )
	{
		CPacketVar var;
		sscanf(line, "%[^=]=%s", var.name, var.value);
		vars.push_back(var);

		line			= strtok( NULL, "\n" );
    }
}

CPacket::~CPacket( )
{
	if(data)
		delete data;

	vars.clear();
}

char* CPacket::GetVar( char* varname )
{
	for(packetvar_list::iterator so =vars.begin(); so!=vars.end(); so++)
	{
		if(strcmp((*so).name, varname) == 0)
			return (*so).value;
	}

	return NULL;
}

void CPacket::SetVar( char* varname, const char* varvalue )
{
	for(packetvar_list::iterator so =vars.begin(); so!=vars.end(); so++)
	{
		if(strcmp((*so).name, varname) == 0)
		{
			length		= length-strlen((*so).value)+strlen(varvalue);
			strcpy((*so).value, varvalue);
			return;
		}
	}

	length			+= strlen(varname)+strlen(varvalue)+3;
	CPacketVar var;
	strcpy(var.name, varname);
	strcpy(var.value, varvalue);
	vars.push_back(var);
}

void CPacket::SetVar( char* varname, int varvalue )
{
	char* buffer	= new char[(int)ceil((float)(varvalue/10))+1];
	sprintf(buffer, "%i", varvalue);
	SetVar(varname, buffer);
	delete buffer;
}

char* CPacket::GetData( )
{
	//Generate the data
	if(data)
		delete data;

	data		= new char[length-12];

	for(packetvar_list::iterator so =vars.begin(); so!=vars.end(); so++)
	{
		int len		= strlen((*so).name)+strlen((*so).value)+3;
		char* line	= new char[len];
		sprintf(line, "%s=%s\n", (*so).name, (*so).value);

		if(so != vars.begin())
			strcat(data, line);
		else
			strcpy(data, line);
	}

	return data;
}

char* CPacket::GetType( )
{
	return type;
}

DWORD CPacket::GetType2( )
{
	return type2;
}