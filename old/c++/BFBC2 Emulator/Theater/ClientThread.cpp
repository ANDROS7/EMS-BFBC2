// BFBC2 Emulator - Plasma
// Made by Freaky123
// With help from Domo
// © Freaky (Freaky123) 2010-2011

#include "stdafx.h"
#include "CFramework.h"
#include "CSocketServer.h"

extern CFramework* fw;
CSocketServer* socketServerC;
void clientSocket(LPVOID lpParam);

void clientListener(LPVOID lpParam)
{
	socketServerC		= new CSocketServer("Theater-client", "ALL", 18395);

	while(socketServerC)
	{
		CSocket* sock	= socketServerC->Accept( );

		_beginthread(clientSocket, 0, (void*)sock);
	}
}

struct clientInfo
{
	int		sock_id;
	int		sock_tid;
	int		persona_id;
	char	persona_lkey[30];
};

void clientSocket(LPVOID lpParam)
{
	CSocket* sock	= (CSocket*) lpParam;

	clientInfo sockInfo;
	sockInfo.sock_id			= sock->GetId();
	sockInfo.sock_tid			= 0;
	sockInfo.persona_id			= -1;

	while(sock)
	{
		CPacket* recievePacket	= sock->RecievePacket( );
		CPacket* sendPacket;

		if(recievePacket == NULL)
			break;

		char* type		= recievePacket->GetType();
		if(strcmp(type, "CONN") == 0)
		{
			sendPacket		= new CPacket("CONN", 0x00000000);
			sendPacket->SetVar("TID", ++sockInfo.sock_tid);
			sendPacket->SetVar("ATIME", "NuLoginPersona");
			sendPacket->SetVar("activityTimeoutSecs", "240");
			sendPacket->SetVar("PROT", "2");
			sock->SendPacket(sendPacket);
			delete sendPacket;
		}
		else if(strcmp(type, "USER") == 0)
		{
			strcpy(sockInfo.persona_lkey, recievePacket->GetVar("LKEY"));
			sql::ResultSet*	result		=  fw->database->Query("SELECT `persona_id`,`persona_name` FROM `personas` WHERE `persona_lkey`='%s'", sockInfo.persona_lkey);
				
			if(result->rowsCount() == 1)
			{
				result->first();
				sockInfo.persona_id		= result->getInt("persona_id");

				sendPacket		= new CPacket("USER", 0x00000000);
				sendPacket->SetVar("TID", ++sockInfo.sock_tid);
				sendPacket->SetVar("NAME", result->getString("persona_name").c_str());
				sock->SendPacket(sendPacket);
				delete sendPacket;
			}
		}
		else if(strcmp(type, "LLST") == 0)
		{
			sql::ResultSet*	result		=  fw->database->Query("SELECT `lobby_id`,`lobby_name`,`lobby_locale`,`lobby_num_games`,`lobby_max_games` FROM `lobbies`");
			sendPacket		= new CPacket("LLST", 0x00000000);
			sendPacket->SetVar("TID", ++sockInfo.sock_tid);
			sendPacket->SetVar("NUM-LOBBIES", result->rowsCount());
			sock->SendPacket(sendPacket);

			while(result->next())
			{
				sendPacket		= new CPacket("LDAT", 0x00000000);
				sendPacket->SetVar("TID", sockInfo.sock_tid);
				sendPacket->SetVar("LID", result->getInt("lobby_id"));
				sendPacket->SetVar("PASSING", result->getInt("lobby_num_games"));
				sendPacket->SetVar("NAME", result->getString("lobby_name").c_str());
				sendPacket->SetVar("LOCALE", result->getString("lobby_locale").c_str());
				sendPacket->SetVar("MAX-GAMES", result->getInt("lobby_max_games"));
				sendPacket->SetVar("FAVORITE-GAMES", 0); //TODO: find out how it works
				sendPacket->SetVar("FAVORITE-PLAYERS", 0); //TODO: find out how it works
				sendPacket->SetVar("NUM-GAMES", result->getInt("lobby_num_games"));
				sock->SendPacket(sendPacket);
			}
		}
		else if(strcmp(type, "GLST") == 0)
		{
			sql::ResultSet*	lobby_result		=  fw->database->Query("SELECT `lobby_id`,`lobby_num_servers`,`lobby_max_servers` FROM `lobbies` WHERE `lobby_id`='%s'", recievePacket->GetVar("LID"));
			sql::ResultSet*	result				=  fw->database->Query("SELECT * FROM `games` WHERE `lobby_id`='%s' LIMIT %s", recievePacket->GetVar("LID"), recievePacket->GetVar("COUNT"));
			
			sendPacket		= new CPacket("GLST", 0x00000000);
			sendPacket->SetVar("TID", ++sockInfo.sock_tid);
			sendPacket->SetVar("LID", lobby_result->getInt("lobby_id"));
			sendPacket->SetVar("LOBBY-NUM-GAMES", lobby_result->getInt("lobby_num_games"));
			sendPacket->SetVar("LOBBY-NAX-GAMES", lobby_result->getInt("lobby_max_games"));
			sendPacket->SetVar("NUM-GAMES", result->rowsCount());

			while(result->next())
			{
				sendPacket		= new CPacket("GDAT", 0x00000000);
				sendPacket->SetVar("TID", sockInfo.sock_tid);
				sendPacket->SetVar("LID", result->getInt("lobby_id"));
				sendPacket->SetVar("GID", result->getInt("game_id"));

				sendPacket->SetVar("B-maxObservers", result->getString("game_hn").c_str());

				//sendPacket->SetVar("TYPE", result->getString("game_type").c_str());	//Type?
				sendPacket->SetVar("HN", result->getString("game_hn").c_str());
				sendPacket->SetVar("HU", result->getInt("game_hu"));
				sendPacket->SetVar("N", result->getString("game_n").c_str());		//Name
				sendPacket->SetVar("I", result->getString("game_i").c_str());		//IP
				sendPacket->SetVar("P", result->getInt("game_p"));					//Port
				sendPacket->SetVar("J", result->getInt("game_j"));
				sendPacket->SetVar("V", result->getString("game_v").c_str());		//Version?
				sendPacket->SetVar("JP", result->getInt("game_jp"));
				sendPacket->SetVar("QP", result->getInt("game_qp"));
				sendPacket->SetVar("AP", result->getInt("game_ap"));				//Active players?
				sendPacket->SetVar("MP", result->getInt("game_mp"));				//Max players?
				sendPacket->SetVar("F", result->getInt("game_f"));
				sendPacket->SetVar("NF", result->getInt("game_nf"));
				sendPacket->SetVar("PL", result->getInt("game_pl"));				//Platform
				sendPacket->SetVar("PW", result->getInt("game_pw"));				//Password?
				

				//Userdata
				sendPacket->SetVar("B-U-Hardcore", result->getInt("game_hardcore"));			//Game is hardcore
				sendPacket->SetVar("B-U-HasPassword", result->getInt("game_hasPassword"));		//Game has password
				sendPacket->SetVar("B-U-Punkbuster", result->getInt("game_punkbuster"));		//Game has punkbuster

				sendPacket->SetVar("B-U-level", result->getString("game_level").c_str());		//Game level
				sendPacket->SetVar("B-U-sguid", result->getInt("game_sguid"));					//Game PB Server GUID?
				sendPacket->SetVar("B-U-Time", result->getString("game_time").c_str());			//Game time?
				//sendPacket->SetVar("B-U-balance", result->getInt("game_pw"));					//Game balanace?
				sendPacket->SetVar("B-U-hash", result->getString("game_hash").c_str());			//Game hash?
				//sendPacket->SetVar("B-U-VIPCount", result->getInt("game_pw"));				//Game vips
				//sendPacket->SetVar("B-U-MaxVIPCount", result->getInt("game_pw"));				//Game max vips
				sendPacket->SetVar("B-U-region", result->getString("game_region").c_str());		//Game region
				//sendPacket->SetVar("B-U-BannerUrl", result->getInt("game_pw"));				//Game banner url
				//sendPacket->SetVar("B-U-type", result->getInt("game_pw"));						//Game type
				sendPacket->SetVar("B-U-public", result->getInt("game_public"));				//Game is public
				sendPacket->SetVar("B-U-elo", result->getInt("game_elo"));						//Game elo?
				

				sendPacket->SetVar("B-version", result->getInt("game_version"));				//Game version?
				sendPacket->SetVar("B-numObservers", result->getInt("game_numObservers"));		//Game observers
				sendPacket->SetVar("B-maxObservers", result->getInt("game_maxObservers"));		//Game max observers

				/*	TID=4
					LID=257
					JP=0
					F=0 (full??)
					HN=bfbc2.server.p
					B-U-level=Levels/MP_001
					B-U-sguid=-1921058344
					N="Quebec BSN Gaming HardCore"
					I=209.44.97.145
					J=O
					HU=224444376
					B-U-Time="T%3a14340.00 S%3a 9.84 L%3a 0.00"
					V=1.0 (version)
					B-U-gamemode=CONQUEST
					P=19569 (port)
					B-U-balance=NORMAL
					B-U-Hardcore=1
					B-U-hash=9831B7DE-AB54-7BF2-64DC-BCDB2205E982
					B-numObservers=0
					TYPE=G
					B-U-VIPCount=0
					B-version=ROMEPC528299
					B-U-region=NAm
					QP=0
					MP=32 (max_players)
					B-U-HasPassword=0
					B-U-BannerUrl=http%3a//www.bsngaming.com/images/bannerbsn.png
					B-U-type=HARDCORE
					GID=49123 (game_id)
					B-U-public=1
					B-U-Punkbuster=1
					NF=0 (not_full??)
					PL=PC (platform)
					B-U-elo=1000
					B-maxObservers=0
					PW=0 (password)
					AP=0
					B-U-MaxVIPCount=0	*/
				sock->SendPacket(sendPacket);
			}
		}
		else
		{
				fw->debug->warning(2, "clientSocket", "Could not handle type=%s", type);
		}
	}

	socketServerC->Remove(sock);
}