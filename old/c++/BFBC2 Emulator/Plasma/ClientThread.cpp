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
	socketServerC		= new CSocketServer("Plasma-client", "ALL", 18390, true);

	while(socketServerC)
	{
		CSocket* sock	= socketServerC->Accept( );

		_beginthread(clientSocket, 0, (void*)sock);
	}
}

struct clientInfo
{
	int		sock_id;
	int		profile_id;

	int		user_id;
	bool	user_loggedIn;
	char	user_lkey[32];

	int		persona_id;
	bool	persona_loggedIn;
	char	persona_lkey[32];
	char	persona_name[16];

	bool	rank_recieving;
	int		rank_size;
	char*	rank_data;
};

void clientSocket(LPVOID lpParam)
{
	CSocket* sock			= (CSocket*) lpParam;

	clientInfo sockInfo;
	sockInfo.sock_id			= sock->GetId();
	sockInfo.profile_id			= -1;
	sockInfo.user_id			= -1;
	sockInfo.user_loggedIn		= false;
	sockInfo.persona_id			= -1;
	sockInfo.persona_loggedIn	= false;
	sockInfo.rank_recieving		= false;
	sockInfo.rank_size			= -1;

	while(sock)
	{
		CPacket* recievePacket	= sock->RecievePacket( );
		CPacket* sendPacket;

		if(recievePacket == NULL)
			break;

		char* txn	= recievePacket->GetVar("TXN");
		if(txn != NULL)
		{
			if(strcmp(txn, "Hello") == 0)
			{
					fw->debug->notification(2, "clientSocket", "Handling TXN=Hello");

					sendPacket		= new CPacket(recievePacket->GetType(), 0x80000000);
					sendPacket->SetVar("TXN", "Hello");
					sendPacket->SetVar("domainPartition.domain", "eagames");
					sendPacket->SetVar("messengerPort", "13505");
					sendPacket->SetVar("domainPartition.subDomain", "bfbc2-pc");
					sendPacket->SetVar("activityTimeoutSecs", "0");
					sendPacket->SetVar("curTime", fw->getTime());
					sendPacket->SetVar("theaterIp", "127.0.0.1");
					sendPacket->SetVar("theaterPort", "18395");
					sock->SendPacket(sendPacket);
					delete sendPacket;

					char* salt		= new char[512];
					sprintf(salt, "%u", time(NULL));

					sendPacket		= new CPacket(recievePacket->GetType(), 0x80000000);
					sendPacket->SetVar("TXN", "MemCheck");
					sendPacket->SetVar("memcheck.[]", "0");
					sendPacket->SetVar("type", "0");
					sendPacket->SetVar("salt", salt);
					sock->SendPacket(sendPacket, false);
					delete sendPacket;
			}
			else if(strcmp(txn, "MemCheck") == 0)
			{
				fw->debug->notification(2, "clientSocket", "Handling TXN=MemCheck");
				//Just ignore
			}
			else if(strcmp(txn, "NuLogin") == 0)
			{
				fw->debug->notification(2, "clientSocket", "Handling TXN=NuLogin");
			
				char* nuid		= recievePacket->GetVar("nuid");
				char* password	= recievePacket->GetVar("password");
				if(nuid && password)
				{
					//Check login
					sql::ResultSet*	result		=  fw->database->Query("SELECT `user_id`,`user_displayName`,`profile_id` FROM `users` WHERE `user_nuid`='%s' AND `user_password`='%s'", nuid, password);
				
					if(result->rowsCount() == 1)
					{
						result->first();
						strcpy(sockInfo.user_lkey, fw->randomString(30));
						strcat(sockInfo.user_lkey,".");
						sockInfo.user_id		= result->getInt("user_id");
						sockInfo.profile_id		= result->getInt("profile_id");
						sockInfo.user_loggedIn	= true;
						fw->database->Query("UPDATE `users` SET `user_online`='1', `user_lastLogin`=CURRENT_TIMESTAMP(), `user_lkey`='%s' WHERE `user_id`='%i'", sockInfo.user_lkey, sockInfo.user_id);

						sendPacket		= new CPacket(recievePacket->GetType(), 0x80000000);
						sendPacket->SetVar("TXN", "NuLogin");
						sendPacket->SetVar("lkey", sockInfo.user_lkey);
						sendPacket->SetVar("nuid", nuid);
						sendPacket->SetVar("displayName", result->getString("user_displayName").c_str());
						sendPacket->SetVar("profileId", sockInfo.profile_id);
						sendPacket->SetVar("userId", sockInfo.user_id);
						sock->SendPacket(sendPacket);
						delete sendPacket;
					}
					else
					{
						sendPacket		= new CPacket(recievePacket->GetType(), 0x80000000);
						sendPacket->SetVar("TXN", "NuLogin");
						sendPacket->SetVar("localizedMessage", "\"The username or password is incorrect\"");
						sendPacket->SetVar("errorCode", "122");
						sock->SendPacket(sendPacket);
						delete sendPacket;
					}

					delete result;
				}
				else
				{
					fw->debug->warning(2, "clientSocket", "Didn't recieve nuid and password from NuLogin packet");

					sendPacket		= new CPacket(recievePacket->GetType(), 0x80000000);
					sendPacket->SetVar("TXN", "NuLogin");
					sendPacket->SetVar("localizedMessage", "\"ERROR 0x00001 Please contact server admin!\"");
					sendPacket->SetVar("errorCode", "122");
					sock->SendPacket(sendPacket);
					delete sendPacket;
				}
			}
			else if(strcmp(txn, "NuGetPersonas") == 0)
			{
				fw->debug->notification(2, "clientSocket", "Handling TXN=NuGetPersonas");

				sql::ResultSet*	result		=  fw->database->Query("SELECT `persona_name` FROM `personas` WHERE `user_id`='%i'", sockInfo.user_id);

				sendPacket		= new CPacket(recievePacket->GetType(), 0x80000000);
				sendPacket->SetVar("TXN", "NuGetPersonas");
				sendPacket->SetVar("personas.[]", result->rowsCount());

				for(int i=0;result->next();i++)
				{
					char* buffer	= new char[512];
					sprintf(buffer, "personas.%i", i);
					sendPacket->SetVar(buffer, result->getString("persona_name").c_str());
					delete buffer;
				}

				sock->SendPacket(sendPacket);
				delete sendPacket;
			}
			else if(strcmp(txn, "NuLoginPersona") == 0)
			{
				fw->debug->notification(2, "clientSocket", "Handling TXN=NuLoginPersonas");

				sql::ResultSet*	result		=  fw->database->Query("SELECT `persona_id`,`persona_name` FROM `personas` WHERE `user_id`='%i' AND `persona_name`='%s'", sockInfo.user_id, recievePacket->GetVar("name"));
			
				if(result->rowsCount() == 1)
				{
					result->first();
					strcpy(sockInfo.persona_lkey, fw->randomString(30));
					strcat(sockInfo.persona_lkey,".");
					strcpy(sockInfo.persona_name, result->getString("persona_name").c_str());
					sockInfo.persona_loggedIn		= true;
					sockInfo.persona_id				= result->getInt("persona_id");
					fw->database->Query("UPDATE `personas` SET `persona_online`='1', `persona_lastLogin`=CURRENT_TIMESTAMP(), `persona_lkey`='%s' WHERE `persona_id`='%i'", sockInfo.persona_lkey, sockInfo.persona_id);

					sendPacket		= new CPacket(recievePacket->GetType(), 0x80000000);
					sendPacket->SetVar("TXN", "NuLoginPersona");
					sendPacket->SetVar("lkey", sockInfo.persona_lkey);
					sendPacket->SetVar("profileId", sockInfo.profile_id);
					sendPacket->SetVar("userId", sockInfo.user_id);
					sock->SendPacket(sendPacket);
					delete sendPacket;
				}
				else
				{
					sendPacket		= new CPacket(recievePacket->GetType(), 0x80000000);
					sendPacket->SetVar("TXN", "NuLoginPersona");
					sendPacket->SetVar("localizedMessage", "\"ERROR 0x00002 Please contact server admin!\"");
					sendPacket->SetVar("errorCode", "122");
					sock->SendPacket(sendPacket);
					delete sendPacket;
				}
			}
			else if(strcmp(txn, "GetPingSites") == 0)
			{
				fw->debug->notification(2, "clientSocket", "Handling TXN=GetPingSites");

				sql::ResultSet*	result		=  fw->database->Query("SELECT `ping_site_addr`,`ping_site_type`,`ping_site_name` FROM `ping_sites`");

				sendPacket		= new CPacket(recievePacket->GetType(), 0x80000000);
				sendPacket->SetVar("TXN", "GetPingSites");
				sendPacket->SetVar("pingSite.[]", result->rowsCount());

				for(int i=0;result->next();i++)
				{
					char* buffer	= new char[128];

					sprintf(buffer, "pingSite.%i.addr", i);
					sendPacket->SetVar(buffer, result->getString("ping_site_addr").c_str());
					sprintf(buffer, "pingSite.%i.type", i);
					sendPacket->SetVar(buffer, result->getString("ping_site_type").c_str());
					sprintf(buffer, "pingSite.%i.name", i);
					sendPacket->SetVar(buffer, result->getString("ping_site_name").c_str());

					delete buffer;
				}

				sock->SendPacket(sendPacket);
				delete sendPacket;
			}
			else if(strcmp(txn, "GetAssociations") == 0)
			{
				fw->debug->notification(2, "clientSocket", "Handling TXN=GetAssociations");

				char* type		= recievePacket->GetVar("type");
				if(strcmp(type, "PlasmaFriends") == 0)
				{
					//TODO: make the friends list database
					sendPacket		= new CPacket(recievePacket->GetType(), 0x80000000);
					sendPacket->SetVar("TXN", "GetAssociations");
					sendPacket->SetVar("type", "PlasmaFriends");
					sendPacket->SetVar("maxListSize", 20);
					sendPacket->SetVar("domainPartition.domain", "eagames");
					sendPacket->SetVar("domainPartition.subDomain", "BFBC2");
					sendPacket->SetVar("owner.id", sockInfo.persona_id);
					sendPacket->SetVar("owner.name", sockInfo.persona_name);
					sendPacket->SetVar("owner.type", 1);
					sendPacket->SetVar("members.[]", 0);
					sock->SendPacket(sendPacket);
					delete sendPacket;
				}
				else if(strcmp(type, "PlasmaMute") == 0)
				{
					//TODO: make the mute list database
					sendPacket		= new CPacket(recievePacket->GetType(), 0x80000000);
					sendPacket->SetVar("TXN", "GetAssociations");
					sendPacket->SetVar("type", "PlasmaMute");
					sendPacket->SetVar("maxListSize", 20);
					sendPacket->SetVar("domainPartition.domain", "eagames");
					sendPacket->SetVar("domainPartition.subDomain", "BFBC2");
					sendPacket->SetVar("owner.id", sockInfo.persona_id);
					sendPacket->SetVar("owner.name", sockInfo.persona_name);
					sendPacket->SetVar("owner.type", 1);
					sendPacket->SetVar("members.[]", 0);
					sock->SendPacket(sendPacket);
					delete sendPacket;
				}
				else if(strcmp(type, "PlasmaBlock") == 0)
				{
					//TODO: make the block list database
					sendPacket		= new CPacket(recievePacket->GetType(), 0x80000000);
					sendPacket->SetVar("TXN", "GetAssociations");
					sendPacket->SetVar("type", "PlasmaBlock");
					sendPacket->SetVar("maxListSize", 20);
					sendPacket->SetVar("domainPartition.domain", "eagames");
					sendPacket->SetVar("domainPartition.subDomain", "BFBC2");
					sendPacket->SetVar("owner.id", sockInfo.persona_id);
					sendPacket->SetVar("owner.name", sockInfo.persona_name);
					sendPacket->SetVar("owner.type", 1);
					sendPacket->SetVar("members.[]", 0);
					sock->SendPacket(sendPacket);
					delete sendPacket;
				}
				else if(strcmp(type, "PlasmaRecentPlayers") == 0)
				{
					//TODO: make the recent list database
					sendPacket		= new CPacket(recievePacket->GetType(), 0x80000000);
					sendPacket->SetVar("TXN", "GetAssociations");
					sendPacket->SetVar("type", "PlasmaRecentPlayers");
					sendPacket->SetVar("maxListSize", 20);
					sendPacket->SetVar("domainPartition.domain", "eagames");
					sendPacket->SetVar("domainPartition.subDomain", "BFBC2");
					sendPacket->SetVar("owner.id", sockInfo.persona_id);
					sendPacket->SetVar("owner.name", sockInfo.persona_name);
					sendPacket->SetVar("owner.type", 1);
					sendPacket->SetVar("members.[]", 0);
					sock->SendPacket(sendPacket);
					delete sendPacket;
				}
				else
				{
					fw->debug->warning(2, "clientSocket", "Could not handle TXN=%s type=%s", txn, type);
				}
			}
			else if(strcmp(txn, "ModifySettings") == 0)
			{
				fw->debug->notification(2, "clientSocket", "Handling TXN=ModifySettings");

				//TODO: modify settings in database
				sendPacket		= new CPacket(recievePacket->GetType(), 0x80000000);
				sendPacket->SetVar("TXN", "ModifySettings");
				sock->SendPacket(sendPacket);
				delete sendPacket;
			}
			else if(strcmp(txn, "GetTelemetryToken") == 0)
			{
				fw->debug->notification(2, "clientSocket", "Handling TXN=GetTelemetryToken");

				//TODO: make the telemetry token database
				sendPacket		= new CPacket(recievePacket->GetType(), 0x80000000);
				sendPacket->SetVar("TXN", "GetTelemetryToken");
				sendPacket->SetVar("telemetryToken", "MTU5LjE1My4yMzUuMjYsOTk0NixlblVTLF7ZmajcnLfGpKSJk53K/4WQj7LRw9asjLHvxLGhgoaMsrDE3bGWhsyb4e6woYKGjJiw4MCBg4bMsrnKibuDppiWxYKditSp0amvhJmStMiMlrHk4IGzhoyYsO7A4dLM26rTgAo%3d");
				sendPacket->SetVar("enabled", "CA,MX,PR,US,VI,AD,AF,AG,AI,AL,AM,AN,AO,AQ,AR,AS,AW,AX,AZ,BA,BB,BD,BF,BH,BI,BJ,BM,BN,BO,BR,BS,BT,BV,BW,BY,BZ,CC,CD,CF,CG,CI,CK,CL,CM,CN,CO,CR,CU,CV,CX,DJ,DM,DO,DZ,EC,EG,EH,ER,ET,FJ,FK,FM,FO,GA,GD,GE,GF,GG,GH,GI,GL,GM,GN,GP,GQ,GS,GT,GU,GW,GY,HM,HN,HT,ID,IL,IM,IN,IO,IQ,IR,IS,JE,JM,JO,KE,KG,KH,KI,KM,KN,KP,KR,KW,KY,KZ,LA,LB,LC,LI,LK,LR,LS,LY,MA,MC,MD,ME,MG,MH,ML,MM,MN,MO,MP,MQ,MR,MS,MU,MV,MW,MY,MZ,NA,NC,NE,NF,NG,NI,NP,NR,NU,OM,PA,PE,PF,PG,PH,PK,PM,PN,PS,PW,PY,QA,RE,RS,RW,SA,SB,SC,clntSock,SG,SH,SJ,SL,SM,SN,SO,SR,ST,SV,SY,SZ,TC,TD,TF,TG,TH,TJ,TK,TL,TM,TN,TO,TT,TV,TZ,UA,UG,UM,UY,UZ,VA,VC,VE,VG,VN,VU,WF,WS,YE,YT,ZM,ZW,ZZ");
				sendPacket->SetVar("filters", "");
				sendPacket->SetVar("disabled", "");
				sock->SendPacket(sendPacket);
				delete sendPacket;
			}
			else if(strcmp(txn, "SetPresenceStatus") == 0)
			{
				fw->debug->notification(2, "clientSocket", "Handling TXN=SetPresenceStatus");

				//TODO: make the Presence Status database
				sendPacket		= new CPacket(recievePacket->GetType(), 0x80000000);
				sendPacket->SetVar("TXN", "SetPresenceStatus");
				sock->SendPacket(sendPacket);
				delete sendPacket;
			}
			else if(strcmp(txn, "NuGetEntitlements") == 0)
			{
				fw->debug->notification(2, "clientSocket", "Handling TXN=NuGetEntitlements");

				char* groupName		= recievePacket->GetVar("groupName");

				if(strcmp(groupName, "BFBC2PC") == 0)
				{
					//TODO: make the BFBC2 entitlements database
					sendPacket		= new CPacket(recievePacket->GetType(), 0x80000000);
					sendPacket->SetVar("TXN", "NuGetEntitlements");
					sendPacket->SetVar("entitlements.[]", 0);
					sock->SendPacket(sendPacket);
					delete sendPacket;
				}
				else if(strcmp(groupName, "AddsVetRank") == 0)
				{
					//TODO: make the AddsVetRank entitlements database
					sendPacket		= new CPacket(recievePacket->GetType(), 0x80000000);
					sendPacket->SetVar("TXN", "NuGetEntitlements");
					sendPacket->SetVar("entitlements.[]", 0);

					/*"entitlements.[]=1\n"
					"entitlements.0.productId=\n"
					"entitlements.0.terminationDate=\n"
					"entitlements.0.entitlementId=547891760\n"
					"entitlements.0.entitlementTag=\n"
					"entitlements.0.status=ACTIVE\n"
					"entitlements.0.userId=2327389728\n"
					"entitlements.0.grantDate=\n"
					"entitlements.0.groupName=AddsVetRank\n"
					"entitlements.0.version=1\n"
					*/
					sock->SendPacket(sendPacket);
					delete sendPacket;
				}
				else if(strcmp(groupName, "BattlefieldBadCompany2") == 0)
				{
					//TODO: make the BattlefieldBadCompany2 entitlements database
					sendPacket		= new CPacket(recievePacket->GetType(), 0x80000000);
					sendPacket->SetVar("TXN", "NuGetEntitlements");
					sendPacket->SetVar("entitlements.[]", 0);
					sock->SendPacket(sendPacket);
					delete sendPacket;
				}
				else
				{
					fw->debug->warning(2, "clientSocket", "Could not handle TXN=%s group=%s", txn, groupName);
				}
			}
			else if(strcmp(txn, "GetStats") == 0)
			{
				fw->debug->notification(2, "clientSocket", "Handling TXN=GetStats");
				sendPacket		= new CPacket(recievePacket->GetType(), 0x80000000);
				sendPacket->SetVar("TXN", "GetStats");

				int count		= atoi(recievePacket->GetVar("keys.[]"));
				sendPacket->SetVar("stats.[]", count);
				for(int i=0; i<count; i++)
				{
					char keyname[25], key[25];
					sprintf(keyname, "keys.%i", i);
					strcpy(key, recievePacket->GetVar(keyname));

					sql::ResultSet*	result		=  fw->database->Query("SELECT `persona_stat_key`,`persona_stat_value` FROM `persona_stats` WHERE `persona_stat_key`='%s' AND `persona_id`='%i'", key, sockInfo.persona_id );
					result->first();

					sprintf(keyname, "stats.%i.key", i);
					sendPacket->SetVar(keyname, result->getString("persona_stat_key").c_str());
					sprintf(keyname, "stats.%i.value", i);
					sendPacket->SetVar(keyname, result->getString("persona_stat_value").c_str());
				}

				sock->SendPacket(sendPacket);
				delete sendPacket;
			}
			else if(strcmp(txn, "GetMessages") == 0)
			{
				fw->debug->notification(2, "clientSocket", "Handling TXN=GetMessages");
				//TODO: find out what it is, and what to do with it
				/*	TXN=GetMessages
					attachmentTypes.[]=1
					attachmentTypes.0=text/plain
					box=inbox
					chunkSize=0	*/
				sendPacket		= new CPacket(recievePacket->GetType(), 0x80000000);
				sendPacket->SetVar("TXN", "GetMessages");
				sendPacket->SetVar("localizedMessage", "\"Record not found\"");
				sendPacket->SetVar("messages.[]", 0);
				sock->SendPacket(sendPacket);
				delete sendPacket;
			}
			else if(strcmp(txn, "GetRecord") == 0)
			{
				fw->debug->notification(2, "clientSocket", "Handling TXN=GetRecord");
				//TODO: find out what it is, and what to do with it
				//	recordName=clan
				sendPacket		= new CPacket(recievePacket->GetType(), 0x80000000);
				sendPacket->SetVar("TXN", "GetRecord");
				sendPacket->SetVar("localizedMessage", "\"Record not found\"");
				sendPacket->SetVar("errorContainer.[]", 0);
				sendPacket->SetVar("errorCode", 5000);
				sock->SendPacket(sendPacket);
				delete sendPacket;
			}
			else if(strcmp(txn, "GetRecordAsMap") == 0)
			{
				fw->debug->notification(2, "clientSocket", "Handling TXN=GetRecordAsMap");
				//TODO: find out what it is, and what to do with it
				//	recordName=dogtags
				sendPacket		= new CPacket(recievePacket->GetType(), 0x80000000);
				sendPacket->SetVar("TXN", "GetRecordAsMap");
				sendPacket->SetVar("localizedMessage", "\"Record not found\"");
				sendPacket->SetVar("errorContainer.[]", 0);
				sendPacket->SetVar("errorCode", 5000);
				sock->SendPacket(sendPacket);
				delete sendPacket;
			}
			else if(strcmp(txn, "GetLockerURL") == 0)
			{
				fw->debug->notification(2, "clientSocket", "Handling TXN=GetLockerURL");
				//TODO: find out what the locker url exactly does
				sendPacket		= new CPacket(recievePacket->GetType(), 0x80000000);
				sendPacket->SetVar("TXN", "GetLockerURL");
				sendPacket->SetVar("URL", "http://127.0.0.1/test.php");
				sock->SendPacket(sendPacket);
				delete sendPacket;
			}
			else if(strcmp(txn, "Goodbye") == 0)
			{
				fw->debug->notification(2, "clientSocket", "Handling TXN=Goodbye");
				
				delete recievePacket;
				break;
			}
			else
			{
					fw->debug->warning(2, "clientSocket", "Could not handle TXN=%s", txn);
			}
		}
		else if(strcmp(recievePacket->GetType(), "rank") == 0)
		{
			fw->debug->notification(2, "clientSocket", "Handling TYPE=rank");
			if(!sockInfo.rank_recieving)
			{
				//First rank packet recieved
				fw->debug->notification(3, "clientSocket", "First packet TYPE=rank");
				sockInfo.rank_recieving		= true;
				sockInfo.rank_size			= atoi(recievePacket->GetVar("size"));
				sockInfo.rank_data			= new char[sockInfo.rank_size];
				sockInfo.rank_data[0]		= 0;
			}

			//Add data from packet to string
			fw->debug->notification(3, "clientSocket", "Add data TYPE=rank");
			strcat(sockInfo.rank_data, recievePacket->GetVar("data"));

			if(strlen(sockInfo.rank_data) == sockInfo.rank_size)
			{
				//All data is recieved
				//sockInfo.rank_data[strlen(sockInfo.rank_data)-1]		= '=';
				fw->debug->notification(3, "clientSocket", "All data recieved TYPE=rank   - data:\n%s", sockInfo.rank_data);
				
				char* decoded_data		= b64_decode(sockInfo.rank_data, sockInfo.rank_size);
				fw->debug->notification(3, "clientSocket", "All data decoded TYPE=rank   - data:\n%s", decoded_data);

				CPacket* rankRecievePacket		= new CPacket(recievePacket->GetType(), recievePacket->GetType2(), strlen(decoded_data)+12, decoded_data);
				int count						= atoi(rankRecievePacket->GetVar("keys.[]"));

				delete decoded_data;
				delete sockInfo.rank_data;
				sockInfo.rank_recieving			= false;

				CPacket* rankSendPacket			= new CPacket(recievePacket->GetType(), recievePacket->GetType2());
				rankSendPacket->SetVar("TXN", "GetStats");
				rankSendPacket->SetVar("stats.[]", count);

				for(int i=0; i<count; i++)
				{
					//TODO: Get stats from database
					char* buffer	= new char[128];
					char* buffer2	= new char[128];
					sprintf(buffer, "keys.%i", i);

					sprintf(buffer2, "stats.%i.key", i);
					rankSendPacket->SetVar(buffer2, rankRecievePacket->GetVar(buffer));
					sprintf(buffer2, "stats.%i.value", i);
					rankSendPacket->SetVar(buffer2, "0.0");

					delete buffer;
					delete buffer2;
				}

				//Generate encoded packet
				char* send_data		= rankSendPacket->GetData();
				int decoded_size	= strlen(send_data);
				fw->debug->notification(3, "clientSocket", "All data send TYPE=rank   - data:\n%s", send_data);
				send_data			= b64_encode(send_data, strlen(send_data));
				int encoded_size	= strlen(send_data);
				fw->debug->notification(3, "clientSocket", "All data encoded TYPE=rank   - data:\n%s", send_data);

				delete rankRecievePacket;
				delete rankSendPacket;

				//Split into smaller packets
				int new_size		= encoded_size;
				for(int i=0; new_size > 0; i++)
				{
					sendPacket			= new CPacket(recievePacket->GetType(), 0x80000000);
					sendPacket->SetVar("decodedSize", decoded_size);
					sendPacket->SetVar("size", encoded_size);
					
					char* packet_data	= new char[8096];
					strncpy(packet_data, send_data+(i*8096), 8096);
					sendPacket->SetVar("data", packet_data);


					new_size		-= 8096;
					sock->SendPacket(sendPacket);
					delete sendPacket;
					delete packet_data;
				}
			}
		}

		delete recievePacket;
	}

	//Logout in database
	if(sockInfo.persona_loggedIn)
		fw->database->Query("UPDATE `personas` SET `persona_online`='0', `persona_lkey`=NULL WHERE `persona_id`='%i'", sockInfo.persona_id);
	if(sockInfo.user_loggedIn)
		fw->database->Query("UPDATE `users` SET `user_online`='0', `user_lkey`=NULL WHERE `user_id`='%i'", sockInfo.user_id);


	socketServerC->Remove(sock);
}