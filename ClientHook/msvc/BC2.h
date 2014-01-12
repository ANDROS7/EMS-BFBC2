#include "StdAfx.h"
#include <WinSock2.h>
#include <stdint.h>
#include "Utils.h"
#include "detours.h"

#ifndef BC2_H
#define BC2_H

#pragma comment(lib, "ws2_32.lib")

namespace Nexus
{
	class BC2
	{
	public:
		static BC2* GetInstance();
		static BC2* gInstance;

		static void DeleteOldHook();
		static int CheckVersion();
		void InitHooks(bool pIsServer);

	private:
		BC2();
		~BC2();

		bool IsServer;
		DWORD dwCodeSize;
		DWORD dwCodeOffset;
		DWORD dwEntryPoint;
	};
}

#endif