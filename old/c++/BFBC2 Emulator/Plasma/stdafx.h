// BFBC2 Emulator - Plasma
// Made by Freaky123
// With help from Domo
// © Freaky (Freaky123) 2010-2011

#pragma once

#include "targetver.h"

#include <windows.h>
#include <stdio.h>
#include <process.h>
#include <tchar.h>
#include <sys/stat.h> 
#include <time.h>
#include <cppconn/driver.h>
#include <cppconn/exception.h>
#include <cppconn/resultset.h>
#include <cppconn/statement.h>
#include <cppconn/prepared_statement.h>
#include <openssl/ssl.h>
#include <openssl/err.h>

#include "SSL_cert.h"
#include "Base64.h"

#pragma comment(lib, "wsock32.lib")
#pragma comment(lib, "libeay32MTd.lib")
#pragma comment(lib, "ssleay32MTd.lib")

//For old send method
typedef uint8_t     u8;
typedef uint16_t    u16;
typedef uint32_t    u32;
