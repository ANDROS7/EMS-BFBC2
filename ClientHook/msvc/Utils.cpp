// ==========================================================
// Emulator Nexus - Battlefield Bad Company 2 Hook
//
// Description:
//		Utilities Class
//
// Author: NoFaTe
// ==========================================================

#include "StdAfx.h"
#include "Utils.h"

void Utils::HexDump(void *ptr, int buflen) 
{
	unsigned char *buf = (unsigned char*)ptr;
	int i, j;
	for (i = 0; i < buflen; i += 16) 
	{
		printf("%06x: ", i);
		for (j = 0; j < 16; j++)
			if ((i + j) < buflen)
				printf("%02x ", buf[i+j]);
			else
				printf("   ");
		printf(" ");
		for (j = 0; j < 16; j++) 
			if ((i + j) < buflen)
				printf("%c", isprint(buf[i + j]) ? buf[i + j] : '.');
		printf("\n");
	}
}

void Utils::AllocateConsole(LPCWSTR pTitle)
{
	// Allocate Console Window
	AllocConsole() ;
	AttachConsole(GetCurrentProcessId());
	freopen("CON", "w", stdout) ;
	SetConsoleTitle(pTitle);

	// Resize console (max length)
	COORD cordinates = {80, 32766};
	HANDLE handle = GetStdHandle(STD_OUTPUT_HANDLE);
	SetConsoleScreenBufferSize(handle, cordinates);
}

DWORD Utils::FindPattern( DWORD dwStart, DWORD dwLen, BYTE* pszPatt, char pszMask[] )
{
	unsigned int i = NULL;
	int iLen = strlen( pszMask ) - 1;

	for( DWORD dwRet = dwStart; dwRet < dwStart + dwLen; dwRet++ )
	{
		if( *(BYTE*)dwRet == pszPatt[i] || pszMask[i] == '?' )
		{
			if( pszMask[i+1] == '\0' )
				return( dwRet - iLen );
			i++;
		}
		else
			i = NULL;
	}
	return NULL;
}

unsigned int Utils::oneAtATimeHash(const char *inpStr)
{
	unsigned int value = 0, temp = 0;
	for(size_t i = 0; inpStr[i] != 0; ++i)
	{
		char ctext = tolower(inpStr[i]);
		temp = ctext + value;
		value = temp << 10;
		temp = temp + value;
		value = temp >> 6;
		value = value ^ temp;
	}
	temp = value << 3;
	temp = temp + value;
	unsigned int temp2 = temp >> 11;
	temp = temp2 ^ temp;
	temp2 = temp << 15;
	value = temp2 + temp;
	if(value < 2)
		return value + 2;
	return value;
}

DWORD Utils::GetSizeOfCode( HANDLE hHandle )
{
	HMODULE hModule = (HMODULE)hHandle;

	if ( !hModule )
		return NULL;

	PIMAGE_DOS_HEADER pDosHeader = PIMAGE_DOS_HEADER( hModule );

	if( !pDosHeader )
		return NULL;

	PIMAGE_NT_HEADERS pNTHeader = PIMAGE_NT_HEADERS( (LONG)hModule + pDosHeader->e_lfanew );

	if( !pNTHeader )
		return NULL;

	PIMAGE_OPTIONAL_HEADER pOptionalHeader = &pNTHeader->OptionalHeader;

	if( !pOptionalHeader )
		return NULL;

	return pOptionalHeader->SizeOfCode;
}

DWORD Utils::OffsetToCode( HANDLE hHandle )
{
	HMODULE hModule = (HMODULE)hHandle;

	if ( !hModule )
		return NULL;

	PIMAGE_DOS_HEADER pDosHeader = PIMAGE_DOS_HEADER( hModule );

	if( !pDosHeader )
		return NULL;

	PIMAGE_NT_HEADERS pNTHeader = PIMAGE_NT_HEADERS( (LONG)hModule + pDosHeader->e_lfanew );

	if( !pNTHeader )
		return NULL;

	PIMAGE_OPTIONAL_HEADER pOptionalHeader = &pNTHeader->OptionalHeader;

	if( !pOptionalHeader )
		return NULL;

	return pOptionalHeader->BaseOfCode;
}

bool Utils::FileExists(std::string pFileName)
{
	std::ifstream sFile(pFileName);
	return sFile;
}

std::string Utils::GetCurrentDir()
{
	char buffer[MAX_PATH];
	GetModuleFileNameA(NULL, buffer, MAX_PATH);
	std::string::size_type pos = std::string(buffer).find_last_of("\\/");
	return std::string(buffer).substr(0, pos);
}