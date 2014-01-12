#include "BC2.h"
#include <sstream>

// ====		Detours Start	==== //

typedef hostent* (WINAPI *gethostbyname_t)(const char* name);
gethostbyname_t realgethostbyname = (gethostbyname_t)gethostbyname;

hostent* WINAPI custom_gethostbyname(const char* name) {
	char* hostname = (char*)name;
	
	unsigned int PlasmaC = Utils::oneAtATimeHash("bfbc2-pc.fesl.ea.com");
	unsigned int TheaterC = Utils::oneAtATimeHash("bfbc2-pc.theater.ea.com");

	unsigned int PlasmaS = Utils::oneAtATimeHash("bfbc2-pc-server.fesl.ea.com");
	unsigned int TheaterS = Utils::oneAtATimeHash("bfbc2-pc-server.theater.ea.com");

	unsigned int Easo = Utils::oneAtATimeHash("easo.ea.com");
	unsigned int current = Utils::oneAtATimeHash((char*)name);

	if (current == PlasmaC || current == TheaterC || current == Easo || current == PlasmaS || current == TheaterS) {
		hostname = "127.0.0.1";
	}
	
	return realgethostbyname(hostname);
}


int (__cdecl* setPacketValue)(int a1, int a2, int a3, signed int a4);

int __cdecl cSetPacketValue(int a1, int a2, int a3, signed int a4)
{
	// returnEncryptedInfo
	if (a3 == 25332276)
		a4 = 0;

	return setPacketValue(a1, a2, a3, a4);
}

// ====		Detours End		==== //

namespace Nexus
{
	BC2* BC2::gInstance = NULL;

	BC2* BC2::GetInstance()
	{
		if(gInstance == NULL)
			gInstance = new BC2;
		return gInstance;
	}

	// ==========================

	BC2::BC2()
	{
		HANDLE hModule = GetModuleHandle(NULL);

		dwCodeSize = Utils::GetSizeOfCode( hModule );
		dwCodeOffset = Utils::OffsetToCode( hModule );
		dwEntryPoint = (DWORD)hModule + dwCodeOffset;
	}

	int BC2::CheckVersion()
	{
		// "ROMEPC720174" - Server R32
		DWORD mServerVersionAddr = Utils::FindPattern(0x1600000, 0x600000, (BYTE*)"\x22\x52\x4F\x4D\x45\x50\x43\x37\x32\x30\x31\x37\x34\x22", "xxxxxxxxxxxxxx");

		// Check for "ROMEPC" - Server R30
		if (mServerVersionAddr == NULL)
			mServerVersionAddr = Utils::FindPattern(0x1600000, 0x600000, (BYTE*)"\x22\x52\x4F\x4D\x45\x50\x43\x37\x32\x30\x31\x37\x34\x22", "xxxxxxxxxxxxxx");

		// "ROMEPC795745" - Client R11
		DWORD mClientVersionAddr = Utils::FindPattern(0x1400000, 0x600000, (BYTE*)"\x22\x52\x4F\x4D\x45\x50\x43\x37\x39\x35\x37\x34\x35\x22", "xxxxxxxxxxxxxx");

		if (mClientVersionAddr == NULL)
		{
			if (mServerVersionAddr == NULL)
			{
				MessageBoxA(NULL, "Failed to initialize the Client.\r\nUnknown client/server detected!\r\nPlease verify the integrity of your files and try again.", "Initialization Failure", MB_OK |	MB_ICONWARNING);
				return 0;
			}
			return 2;
		}
		return 1;
	}

	void BC2::InitHooks(bool pIsServer)
	{
		IsServer = pIsServer;

		//Logger(lINFO, "BC2", "Starting Nexus BC2");

		// Wait for basic DLLs to load
		while(GetModuleHandle(TEXT("binkw32")) == 0 || GetModuleHandle(TEXT("D3DCompiler_42")) == 0 )
			Sleep(10);

		DWORD sVersionAddr = NULL;

		if (IsServer)
		{
			// "ROMEPC720174" - Server R32
			sVersionAddr = Utils::FindPattern(0x1600000, 0x600000, (BYTE*)"\x22\x52\x4F\x4D\x45\x50\x43\x37\x32\x30\x31\x37\x34\x22", "xxxxxxxxxxxxxx");

			// Check for "ROMEPC638140" - Server R30
			if (sVersionAddr == NULL)
				sVersionAddr = Utils::FindPattern(0x1600000, 0x600000, (BYTE*)"\x22\x52\x4F\x4D\x45\x50\x43\x36\x33\x38\x31\x34\x30\x22", "xxxxxxxxxxxxxx");
		}
		else
		{
			// "ROMEPC795745" - Client R11
			sVersionAddr = Utils::FindPattern(0x1400000, 0x600000, (BYTE*)"\x22\x52\x4F\x4D\x45\x50\x43\x37\x39\x35\x37\x34\x35\x22", "xxxxxxxxxxxxxx");
		}

		

		// Patch protocol version
		//Logger(lDEBUG, "BC2", "Patching %s Version to %s.", (IsServer) ? "Server" : "Client", NBC2_VERSION_STR);
		strcpy((char*)(sVersionAddr - 16), NBC2_VERSION_STR);

		// Patch SSL Certificate Verification
		DWORD sSSLPatchAddr = Utils::FindPattern(dwEntryPoint, dwCodeSize, (BYTE*)"\x5E\xB8\x03\x10\x00\x00\x5D\xC3", "xxxxxxxx");
		while (sSSLPatchAddr == NULL)
			sSSLPatchAddr = Utils::FindPattern(dwEntryPoint, dwCodeSize, (BYTE*)"\x5E\xB8\x03\x10\x00\x00\x5D\xC3", "xxxxxxxx");

		*(BYTE*)(sSSLPatchAddr + 2) = 0x15;
		*(BYTE*)(sSSLPatchAddr + 3) = 0x00;

		//Logger(lDEBUG, "BC2", "Patched SSL Certificate Verification!");

		// Hook gethostbyname
		PBYTE offset = (PBYTE)GetProcAddress(GetModuleHandleA("ws2_32.dll"), "gethostbyname");
		realgethostbyname = (gethostbyname_t)DetourFunction(offset, (PBYTE)&custom_gethostbyname);

		if (IsServer)
		{
			// Hook SetPacketValue
			DWORD sSetPacketValueAddr = Utils::FindPattern(dwEntryPoint, dwCodeSize, (BYTE*)"\x83\xEC\x0C\x53\x8B\x5C\x24\x20\x83\xFB\x0A\x56", "xxxxxxxxxxxx");
			if (sSetPacketValueAddr != NULL)
				setPacketValue = (int (__cdecl*)(int a1, int a2, int a3, signed int a4))DetourFunction((PBYTE)sSetPacketValueAddr, (PBYTE)cSetPacketValue);
		}
		else
		{
			// Nothing to see here....
			// Reserved for future use
		}
	}

	void BC2::DeleteOldHook()
	{
		// Remove any old file
		remove((Utils::GetCurrentDir() + "\\xinput1_3.exe").c_str());
		remove((Utils::GetCurrentDir() + "\\NexusBC2.exe").c_str());
		remove((Utils::GetCurrentDir() + "\\NexusBC2.dll").c_str());
		remove((Utils::GetCurrentDir() + "\\NexusBC2.dat").c_str());
		remove((Utils::GetCurrentDir() + "\\NexusBC2loader.exe").c_str());
		remove((Utils::GetCurrentDir() + "\\bfbc2cfg.ini").c_str());
		remove((Utils::GetCurrentDir() + "\\Nexus.dll").c_str());
		remove((Utils::GetCurrentDir() + "\\bfbc2_icon_48x48.ico").c_str());
	}
}