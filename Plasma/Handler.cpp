// BFBC2 Emulator - Plasma
// Made by IceKobrin
// Started: 2014-01-17 

#include "CFramework.h"
#include "CSocketServer.h"
#include "Opcode.h"

CSocketServer* socketServerC;

Handler::Handler( CFramework* framework )
{
	fw		= framework;
}

typedef struct OpcodeHandler
{
    Opcodes cmd;
	char* status;
    bool (Handler::*handler)(void);
} OpcodeHandler;

const OpcodeHandler table[] =
{
    { CMD_MEMCHECK,     TYPE_FSYS, &Handler::HandleMemCheck    },
	{ CMD_HELLO,		TYPE_FSYS, &Handler::HandleHello       }
};
#define OPCODE_TOTAL_COMMANDS sizeof(table)/sizeof(OpcodeHandler)

void Handler::Run()
{
	socketServerC		= new CSocketServer("Plasma-client", "ALL", 18390, true);

	while(socketServerC)
	{
		CSocket* sock	= socketServerC->Accept( );

		while(sock)
		{
			CPacket* recievePacket	= sock->RecievePacket( );
			CPacket* sendPacket;
			size_t i;

			if(recievePacket == NULL)
				break;

			for (i = 0; i < OPCODE_TOTAL_COMMANDS; ++i)
			{
				if (table[i].cmd == recievePacket->GetType2())
				{

					if (!(*this.*table[i].handler)() && table[i].status == recievePacket->GetType())
					{
						return;
					}
					break;
            }
        }

			if (i == OPCODE_TOTAL_COMMANDS)
			{	
				fw->debug->notification(2, "Handler", "got unknown packet %s", recievePacket->GetType2() );
				return;
			}
		}
	}
}

bool Handler::HandleHello()
{
    return true;
}

bool Handler::HandleMemCheck()
{
    return true;
}