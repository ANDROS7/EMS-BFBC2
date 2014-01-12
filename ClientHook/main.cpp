#include "di_wrap.h"
#include "msvc\BC2.h"
#include "msvc\StdAfx.h"

//
// Globals
//

DIRECTINPUT8CREATEPROC				WrapperSystem::DirectInput8Create;

HMODULE								WrapperSystem::dinputDll;

//
// DLL exports
//

extern "C" {

static BYTE originalCode[5];
static PBYTE originalEP = 0;
bool   ISDEDI = false;

#pragma unmanaged
void Main_UnprotectModule(HMODULE hModule)
{
	PIMAGE_DOS_HEADER header = (PIMAGE_DOS_HEADER)hModule;
	PIMAGE_NT_HEADERS ntHeader = (PIMAGE_NT_HEADERS)((DWORD)hModule + header->e_lfanew);

	// Unprotect the entire PE image
	SIZE_T size = ntHeader->OptionalHeader.SizeOfImage;
	DWORD oldProtect;
	VirtualProtect((LPVOID)hModule, size, PAGE_EXECUTE_READWRITE, &oldProtect);
}

void InitNexusBC2(LPVOID pIsServer)
{
	
	Nexus::BC2::GetInstance()->InitHooks((bool)pIsServer);
}

void Main_DoInit()
{
	HMODULE hModule;
	if (SUCCEEDED(GetModuleHandleEx(GET_MODULE_HANDLE_EX_FLAG_FROM_ADDRESS, (LPCWSTR)Main_DoInit, &hModule)))
	{
		Main_UnprotectModule(hModule);
	}

	DWORD sVerAddr = Nexus::BC2::CheckVersion();
	if (sVerAddr > 0)
		CreateThread(NULL, NULL, (LPTHREAD_START_ROUTINE)InitNexusBC2, (LPVOID)((sVerAddr == 1) ? false : true), NULL, NULL);

	// return to the original EP
	memcpy(originalEP, &originalCode, sizeof(originalCode));
	__asm jmp originalEP
}

void Main_SetSafeInit()
{
	// find the entry point for the executable process, set page access, and replace the EP
	HMODULE hModule = GetModuleHandle(NULL); // passing NULL should be safe even with the loader lock being held (according to ReactOS ldr.c)

	if (hModule)
	{
		PIMAGE_DOS_HEADER header = (PIMAGE_DOS_HEADER)hModule;
		PIMAGE_NT_HEADERS ntHeader = (PIMAGE_NT_HEADERS)((DWORD)hModule + header->e_lfanew);

		Main_UnprotectModule(hModule);

		// back up original code
		PBYTE ep = (PBYTE)((DWORD)hModule + ntHeader->OptionalHeader.AddressOfEntryPoint);
		memcpy(originalCode, ep, sizeof(originalCode));

		// patch to call our EP
		int newEP = (int)Main_DoInit - ((int)ep + 5);
		ep[0] = 0xE9;
		memcpy(&ep[1], &newEP, 4);

		originalEP = ep;
	}
}


BOOL WINAPI DllMain( HANDLE hModule, DWORD ul_reason_for_call, LPVOID lpReserved ) {

	switch( ul_reason_for_call ) {

		case DLL_PROCESS_ATTACH:

			// Delete old files
			Nexus::BC2::DeleteOldHook();

			if( !WrapperSystem::Init( hModule ) ) return FALSE;

			break;

		case DLL_PROCESS_DETACH:

			WrapperSystem::Shutdown( );
			break;
	}

	return TRUE;
}

HRESULT WINAPI DirectInput8Create( HINSTANCE inst_handle, DWORD version, const IID & r_iid, LPVOID *ppvOut, LPUNKNOWN p_unk ) {

	return WrapperSystem::DirectInput8Create( inst_handle, version, r_iid, ppvOut, p_unk );
}

}

bool WrapperSystem::Init( HANDLE mod_hnd ) {
	Main_SetSafeInit();

	char dinputDllName[ MAX_PATH ];

	// returns with system32 even on win64 32bit mode, but image loader solves it
	GetSystemDirectoryA( dinputDllName, MAX_PATH );

	strcat( dinputDllName, "\\dinput8.dll" );

	dinputDll = LoadLibraryA( dinputDllName );

	// MSDN: If the function succeeds, the return value is greater than 31.
	if( dinputDll > ( HMODULE )31 ) {

		DirectInput8Create = ( DIRECTINPUT8CREATEPROC )GetProcAddress( dinputDll, "DirectInput8Create" );
		if( !DirectInput8Create ) {
			Shutdown( );
			return false;
		}
		return true;
	}

	return false;
}

void WrapperSystem::Shutdown( ) {
	FreeLibrary( dinputDll );
}
