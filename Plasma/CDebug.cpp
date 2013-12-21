// BFBC2 Emulator - Plasma
// Made by Freaky123
// With help from Domo
// © Freaky (Freaky123) 2010-2011

#include "CFramework.h"


CDebug::CDebug( CFramework* framework )
{
	fw		= framework;
	fp		= NULL;

	//Set standerd values
	fileNotificationLevel		= 0;
	fileWarningLevel			= 0;
	consoleNotificationLevel	= 0;
	consoleWarningLevel			= 0;
}

CDebug::~CDebug( )
{
	//Close the debug file
	if(fp)
	{
		notification(0, "", "");
		fclose(fp);
	}
}

void CDebug::loadConfig( )
{
	bool splitFile			= false;

	//Load config values
	char filename[128];
	fw->config->loadVarChar("debug", "file_name", filename, 128);
	fw->config->loadVarBool("debug", "file_split", &splitFile);
	fw->config->loadVarInt("debug", "file_notification_level", &fileNotificationLevel);
	fw->config->loadVarInt("debug", "file_warning_level", &fileWarningLevel);
	fw->config->loadVarInt("debug", "console_notification_level", &consoleNotificationLevel);
	fw->config->loadVarInt("debug", "console_warning_level", &consoleWarningLevel);

	//Set the filename
	if(filename)
	{
		if(splitFile)
		{
			
			struct tm * current_tm;
			time_t current_time;
			time(&current_time);
			current_tm		= localtime(&current_time);

			char logbuf[128];
			sprintf(logbuf, " [%02d-%02d-%d %02d.%02d.%02d]", current_tm->tm_mday, current_tm->tm_mon, (1900+current_tm->tm_year), current_tm->tm_hour, current_tm->tm_min, current_tm->tm_sec);
			strcat(filename, logbuf);
		}
		strcat(filename, ".log");
		setFile(filename);
	}
}

void CDebug::notification( int level, char* from, char* message, ... )
{
	va_list va_alist;
	char logbuf[47940];

	//Empty line
	if(strcmp(from, "") != 0)
	{
		//Add the time and from to the line
		struct tm * current_tm;
		time_t current_time;
		time(&current_time);
		current_tm		= localtime(&current_time);

		sprintf(logbuf, "[%02d:%02d:%02d - %s] ", current_tm->tm_hour, current_tm->tm_min, current_tm->tm_sec, from);

		//Generate the message
		va_start(va_alist, message);
		_vsnprintf(logbuf+strlen(logbuf), sizeof(logbuf) - strlen(logbuf), message, va_alist);
		va_end(va_alist);
	}
	else
		sprintf(logbuf, "");

	//Print message into file
	if(fp && fileNotificationLevel >= level)
		fprintf(fp, "%s\n", logbuf);

	//Print the message in the console
	if(consoleNotificationLevel >= level)
		printf("%s\n", logbuf);
}

void CDebug::warning( int level, char* from, char* message, ... )
{
	va_list va_alist;
	char logbuf[1024];
	struct tm * current_tm;
	time_t current_time;

	//Add the time and from to the line
	time(&current_time);
	current_tm		= localtime(&current_time);
	sprintf(logbuf, "[%02d:%02d:%02d - %s](WARNING) ", current_tm->tm_hour, current_tm->tm_min, current_tm->tm_sec, from);

	//Generate the message
	va_start(va_alist, message);
	_vsnprintf(logbuf+strlen(logbuf), sizeof(logbuf) - strlen(logbuf), message, va_alist);
	va_end(va_alist);

	//Print message into file
	if(fp && fileWarningLevel >= level)
		fprintf(fp, "%s\n", logbuf);

	//Print the message in the console
	if(consoleWarningLevel >= level)
		printf("%s\n", logbuf);
}

void CDebug::error( char* from, char* message, ... )
{
	va_list va_alist;
	char logbuf[1024];
	struct tm * current_tm;
	time_t current_time;

	//Add the time and from to the line
	time(&current_time);
	current_tm		= localtime(&current_time);
	sprintf(logbuf, "[%02d:%02d:%02d - %s](ERROR) ", current_tm->tm_hour, current_tm->tm_min, current_tm->tm_sec, from);

	//Generate the message
	va_start(va_alist, message);
	_vsnprintf(logbuf+strlen(logbuf), sizeof(logbuf) - strlen(logbuf), message, va_alist);
	va_end(va_alist);
	
	//Print message into file
	if(fp)
		fprintf(fp, "%s\n", logbuf);

	//Open a messagebox
	MessageBox(NULL, logbuf, "Error", MB_OK|MB_ICONWARNING|MB_SYSTEMMODAL);
}

bool CDebug::setFile( char* filename )
{
	//Try to open the log file
	if((fp = fopen( filename, "a" )) != NULL)
	{
		notification(0, "CDebug", "New log started");
		return true;
	}

	warning(1, "CDebug", "Could not open log file (%s)", filename);
	return false;
}

void CDebug::setFileLevel( int notification, int warning )
{
	fileNotificationLevel	= notification;
	fileWarningLevel		= warning;
}

void CDebug::setConsoleLevel( int notification, int warning )
{
	consoleNotificationLevel	= notification;
	consoleWarningLevel			= warning;
}