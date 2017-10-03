# discord-mybb-sync
A MyBB Plugin to syncronise MyBB Groups with Discord Roles

Installation Steps:
1. Copy the Files into the plugin folder from your MyBB installation. inc/...
2. Download unirest (this plugin was tested with v3.0.4) and upload it to inc/plugins/Unirest
https://github.com/Mashape/unirest-php
3. Head over to https://discordapp.com/developers/applications/me#top to create a new App.
4. Create a Bot User and copy the Client ID from the App details and Token from the App Bot User
5. Visit: https://discordapi.com/permissions.html#268435456. insert your Client ID and visit the Link
6. Add the Bot to your Server
7. copy your Guild ID (https://support.discordapp.com/hc/en-us/articles/206346498-Where-can-I-find-my-User-Server-Message-ID-)
8. Open your Admin-CP and install this plugin (Configuration -> Plugins)
9. Open the Settings Tab in the Admin-CP (Configuration -> Settings -> Discord Right Sync Settings) and enter your Bot Token and Guild ID
10. Open the Settings Tab and configure your groups

Description:
This bot assings Discord Roles to user which are in mybb groups. The update task runs ervery 5 minutes and comares the discoard roles with the mybb usergroups. 
Due to an limited amount of request from the discord api, usually only 10 users are updatet per plugin task run. The remaining ones are processed in the next task run. 

If a user has not an role, but is in the corresponding mybb goup, the role is assinged to the user
If a user has an role, but that role is not configured in mybb, the role is not changed
If a user has an role, and that role is configured in mybb, the role is removed from the user
If two user have entered the same Discord name#number no roles are changed.

Notes:
The Bot requires the MANAGE_ROLES permission.
These permissions require the owner account to use two-factor authentication
when used on a guild that has server-wide 2FA enabled.
