/*
 * di-wrapper - A reimplemented version of dinput.dll with the raw input api
 * Copyright (C) 2011 Robert Balint
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.

 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 */

#ifndef DI_WRAP_H
#define DI_WRAP_H

// without this define, sadly compiler wants to use non-portable (ms only) library funcs
#define _CRT_SECURE_NO_WARNINGS
#include <windows.h>
#include <assert.h>
#include <list>

#ifdef DI_WRAPPER8
#define DIRECTINPUT_VERSION 0x0800
#else
#define DIRECTINPUT_VERSION 0x0700
#endif

#include "dinput.h"


typedef IDirectInputDevice7A	DIDeviceClass;
typedef LPDIRECTINPUTDEVICEA	DIDeviceStructPtr;
typedef IDirectInput7A			DIClass;


#ifndef HID_USAGE_PAGE_GENERIC
#define HID_USAGE_PAGE_GENERIC 1
#endif
#ifndef HID_USAGE_GENERIC_MOUSE
#define HID_USAGE_GENERIC_MOUSE 2
#endif


// needed for the original dll funcs
typedef HRESULT ( WINAPI * DIRECTINPUTCREATEAPROC )( HINSTANCE hinst, DWORD dwVersion, LPDIRECTINPUTA * ppDI, LPUNKNOWN punkOuter );
typedef HRESULT ( WINAPI * DIRECTINPUT8CREATEPROC )( HINSTANCE hinst, DWORD dwVersion, REFIID riidltf, LPVOID *ppvOut, LPUNKNOWN punkOuter );



class WrapperSystem {

private:

	static HMODULE								dinputDll;

public:


	static DIRECTINPUT8CREATEPROC				DirectInput8Create;


	static bool									Init( HANDLE mod_hnd );
	static void									Shutdown( );
};

#endif
