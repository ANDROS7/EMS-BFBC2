#define _CRT_SECURE_NO_WARNINGS
#define _CRT_SECURE_NO_DEPRECATE
# pragma warning(disable : 4996)

#define NBC2_VERSION 4
#define NBC2_VERSION_STR "\"ROMEPC133716\""

#define _WINSOCK2API_

#include <Windows.h>
#include <string>
#include <sys/stat.h>
#include <cstdio>
#include <ctime>

#define lINFO 0
#define lWARN 1
#define lERROR 2
#define lDEBUG 4

void Logger(unsigned int lvl, char* caller, char* logline, ...);