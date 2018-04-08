<?php
/*
@category MyBB_18
@package  Discord Right Sync
@author   natter https://www.opt-community.de/Forum/member.php?action=emailuser&uid=20
@license  https://opensource.org/licenses/MIT 
@link     https://github.com/opt-natter/discord-mybb-sync
MyBB plugin: Synchronising MyBB Groups with Discord Roles
Installation Steps:
1. Copy the Files into the plugin and task folder from your MyBB installation. inc/...
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
MIT License
Copyright (c) 2017 Fabian Natterer
Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:
The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.
THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
*/

// Disallow direct access to this file for security reasons
if (!defined("IN_MYBB")){
    die("Direct initialization of this file is not allowed.");
}
                          
if(!defined("UNIREST")){
    define("UNIREST", MYBB_ROOT."inc/plugins/Unirest/Unirest.php");
}
if (defined('IN_ADMINCP')) {
    $plugins->add_hook("admin_config_settings_change", "discord_right_sync_settings_update"); //update the group dropdown lists for selecting the discord roles
    $plugins->add_hook("admin_user_groups_delete_commit", "discord_right_sync_del_group"); //remove group from plugin settings if the group is deleted 
}
$plugins->add_hook("usercp_do_profile_end", "discord_right_sync_user_profile"); //provide a check for die right discord id format
define("DRS_DISCORD_ID_FORMAT", '.{1,32}#\d{4}'); //discord ID Format check NAME#0000

function discord_right_sync_info()
{
    return array(
        "name" => "Discord Right Sync",
        "description" => "A Discord Bot for syncing the rights from the MyBB to a Discord Guild.",
        "website" => "https://github.com/opt-natter/discord-mybb-sync",
        "author" => "natter",
        "authorsite" => "https://www.opt-community.de/Forum/user-20.html",
        "version" => "0.6.1",
        "guid" => "",
        "codename" => "",
        "compatibility" => "16*,18*"
    );
}

function discord_right_sync_install()
{
    global $db, $mybb, $cache;
    //generate settings group
    $setting_group = array(
        'name' => 'drs_settings',
        'title' => 'Discord Right Sync Settings',
        'description' => 'This is my plugin and it does some things',
        'disporder' => 0,
        'isdefault' => 0
    );
    $gid = $db->insert_query("settinggroups", $setting_group);
    
    //generate general settings
    $disporder = 1;
    $setting_array = array();
    $setting_array['drs_setting_token'] = array(
        'title' => 'Discord Bot Token',
        'description' => 'The Authentication Token from your Discord bot',
        'optionscode' => 'text',
        'value' => (int)0,
        'disporder' => (int)$disporder++
    );
    $setting_array['drs_setting_guild_id'] = array(
        'title' => 'Discord Guild ID',
        'description' => 'The Guild ID (Discord Server ID) for the server where the rights schould be synced',
        'optionscode' => 'text',
        'value' => (int)0,
        'disporder' => (int)$disporder++
    );
    $setting_array['drs_setting_user_fid'] = array(
        'title' => 'User Discord Name',
        'description' => 'The MyBB Custom Profile Field for Discord(containing the User Name#Number)',
        'optionscode' => 'select\n0=not data yet',
        'value' => (int)0,
        'disporder' => (int)$disporder++
    );
    $setting_array['drs_setting_del_id'] = array(
        'title' => 'Delete invalid Discord ID',
        'description' => 'Delete Invalid Discord IDs from the Users Profile',
        'optionscode' => 'yesno',
        'value' => (int)1,
        'disporder' => (int)$disporder++
    );    
    $setting_array['drs_setting_ratelimit_reset'] = array(
        'title' => 'Do Not Change',
        'description' => 'Epoch time at which the rate limit resets',
        'optionscode' => 'numeric',
        'value' => (int)0,
        'disporder' => 999
    );
    foreach($setting_array as $name => $setting)
        {
        $setting['name'] = $db->escape_string($name);
        $setting['gid'] = $db->escape_string($gid);
        $db->insert_query('settings', $setting);
        }
    //drs_setting_gid{MYBB_GROUP_ID} is created for each user group in discord_right_sync_settings_update()
    rebuild_settings();
}

function discord_right_sync_is_installed()
{
    return !empty(discord_right_sync_get_setting_group_id());
}

function discord_right_sync_uninstall()
{
    global $db;
    $db->delete_query('settings', "name LIKE 'drs_setting%'");
    $db->delete_query('settinggroups', "name = 'drs_settings'");
    $db->delete_query('tasks', "file = 'discord_right_sync'");
    rebuild_settings();
}

function discord_right_sync_activate()
    {
    global $mybb, $db, $plugins, $cache;   
    if(!file_exists(UNIREST))
    {
        flash_message('Unirest is required by '.discord_right_sync_info()['name'], "error");
        admin_redirect("index.php?module=config-plugins");
    }
    require_once UNIREST;
    require_once MYBB_ROOT . '/inc/functions_task.php';
        
    //crate task if it not exist or activate it
    $taskExists = $db->simple_select('tasks', 'tid', 'file = \'discord_right_sync\'', array(
        'limit' => '1'
    ));
    if ($db->num_rows($taskExists) == 0){
        $myTask = array(
            'title' => discord_right_sync_info() ['name'],
            'file' => 'discord_right_sync',
            'description' => 'Task which sync the role of x mebers to discord',
            'minute' => '0,5,10,15,20,25,30,35,40,45,50,55',
            'hour' => '*',
            'day' => '*',
            'weekday' => '*',
            'month' => '*',
            'nextrun' => TIME_NOW + 60,
            'lastrun' => 0,
            'enabled' => 1,
            'logging' => 1,
            'locked' => 0,
        );
        $task_id = $db->insert_query('tasks', $myTask);
        $theTask = $db->fetch_array($db->simple_select('tasks', '*', 'tid = ' . (int)$task_id, 1));
        $nextrun = fetch_next_run($theTask);
        $db->update_query('tasks', 'nextrun = ' . $nextrun, 'tid = ' . (int)$task_id);
        $plugins->run_hooks('admin_tools_tasks_add_commit');
        $cache->update_tasks();
    }
    else{
        $theTask = $db->fetch_array($db->simple_select('tasks', '*', 'file = \'discord_right_sync\'', 1));
        $db->update_query('tasks', array(
            'enabled' => 1,
            'nextrun' => fetch_next_run($theTask)
        ) , 'file = \'discord_right_sync\'');
        $cache->update_tasks();
        }
        
    //update from 5.1
    if(!isset($mybb->settings['drs_setting_del_id'])){
        $setting_array['drs_setting_del_id'] = array(
            'title' => 'Delete invalid Discord ID',
            'description' => 'Delete Invalid Discord IDs from the Users Profile',
            'optionscode' => 'yesno',
            'value' => (int)1,
            'disporder' => (int)5
        ); 
        $gid=discord_right_sync_get_setting_group_id();
        foreach($setting_array as $name => $setting){
            $setting['name'] = $db->escape_string($name);
            $setting['gid'] = $db->escape_string($gid);
            $db->insert_query('settings', $setting);
        }  
        
        rebuild_settings();      
    }   
}

function discord_right_sync_deactivate()
{
    global $db;
    //disable dask
    $db->update_query('tasks', array(
        'enabled' => 0
    ) , 'file = \'discord_right_sync\'');
}

function discord_right_sync_get_setting_group_id()
{
    global $db, $mybb;
    $query = $db->simple_select('settinggroups', 'gid', "name = 'drs_settings'", array(
        'limit' => 1
    ));
    $group = $db->fetch_array($query);
    return $group['gid'];
}
    
//remove Group from settings if the group is deleted
function discord_right_sync_del_group()
{
    global $db, $mybb;
    if(isset($mybb->settings['drs_setting_gid' . (int)$usergroup['gid']]))
    {
        $db->delete_query("settings", "name='drs_setting_gid".(int)$usergroup['gid']."'");  
        unset($mybb->settings['drs_setting_gid' . (int)$usergroup['gid']]);
    }
}
    
//provide a check for die right discord id format
function discord_right_sync_user_profile()
{
    global $mybb;
    if(!isset($mybb->settings['drs_setting_user_fid']))
       return;
       
    if(empty($mybb->input['profile_fields']['fid' . $mybb->settings['drs_setting_user_fid']]))
       return;
               
    if(!preg_match('/'.DRS_DISCORD_ID_FORMAT.'$/i',trim($mybb->input['profile_fields']['fid' . $mybb->settings['drs_setting_user_fid']])))
       error('Your Discord ID does is probably wrong. Please check it again.');
}
function discord_right_sync_settings_update()
{
    global $db, $mybb, $page, $cache;
    if (discord_right_sync_get_setting_group_id() != $mybb->input['gid']) 
        return;
         
    require_once UNIREST;
    // update user Fild selection
    $select_user_field_str = '';
    $query = $db->simple_select('profilefields', 'fid,name');
    while ($profilefields = $db->fetch_array($query))
        {
        $select_user_field_str.= '\n' . $db->escape_string(htmlspecialchars($profilefields['fid'])) . '=' . $db->escape_string(htmlspecialchars($profilefields['name']));
        }
    $db->update_query("settings", array(
        'optionscode' => 'select' . $select_user_field_str
    ) , "name = 'drs_setting_user_fid'");
    
    rebuild_settings();
     
    $discord_right_sync_settings_set = true;
    // check for auth token
    if (empty($mybb->settings['drs_setting_token']))
        {
        $discord_right_sync_settings_set = false;
        $page->extra_messages[] = array(
            'type' => 'error',
            'message' => discord_right_sync_info() ['name'] . ': Auth Token is not set'
        );
        }
    // check for guild id
    if (empty($mybb->settings['drs_setting_guild_id']))
        {
        $discord_right_sync_settings_set = false;
        $page->extra_messages[] = array(
            'type' => 'error',
            'message' => discord_right_sync_info() ['name'] . ': Auth Guild ID is not set'
        );
        }
    //build the selection fields for each mybb group with the discord roles
    if ($discord_right_sync_settings_set)
        {
        $disporder = 10;
        // Read the usergroups cache
        $usergroups = $cache->read("usergroups");
        // If the groups cache doesn't exist, update it and re-read it
        if (!is_array($usergroups))
            {
            $cache->update_usergroups();
            $usergroups = $cache->read("usergroups");
            }
        $setting_array = array();
        foreach($usergroups as $usergroup)
            {
            if (!$usergroup['isbannedgroup'] AND !isset($mybb->settings['drs_setting_gid' . (int)$usergroup['gid']]))
                {
                $setting_array['drs_setting_gid' . (int)$usergroup['gid']] = array(
                    'title' => $db->escape_string($usergroup['title']) ,
                    'description' => $db->escape_string($usergroup['description']) ,
                    'optionscode' => 'select\n0=Enter Settings First',
                    'value' => (int)0,
                    'disporder' => (int)$disporder++
                );
                }
            }
        foreach($setting_array as $name => $setting)
            {
            $setting['name'] = $db->escape_string($name);
            $setting['gid'] = (int)$mybb->input['gid'];
            $db->insert_query('settings', $setting);
            }
        //get the discord guild roles
        $response = discord_right_sync_get_querry_discord('guilds/' . $mybb->settings['drs_setting_guild_id'] . '/roles');
        
        if ($response->code == 200)
            {
                
            usort($response->body, function ($a, $b){
                return $a->position - $b->position;});
                
            $select_group_str = '\n0=none';
            foreach($response->body as $role)
                {
                $select_group_str.= '\n' . $db->escape_string(htmlspecialchars($role->id)) . '=' . $db->escape_string(htmlspecialchars($role->name));
                }
            $db->update_query("settings", array(
                'optionscode' => 'select' . $select_group_str
            ) , "name LIKE 'drs_setting_gid%' AND gid='" . (int)$mybb->input['gid'] . "'");
            }
          else
            {
            $page->extra_messages[] = array(
                'type' => 'error',
                'message' => discord_right_sync_info()['name'] . ': Could connect to Discord guild. (' . htmlspecialchars($response->body->message) . ')'
            );
            }
        rebuild_settings();
        }
}
//header for API request
function discord_right_sync_discord_header()
{
    global $mybb;
    return array(
        'Authorization' => 'Bot ' . $mybb->settings['drs_setting_token'],
        'User-Agent' => 'DRS (' . discord_right_sync_info() ['website'] . ',' . discord_right_sync_info() ['version'] . ')'
    );
}
function discord_right_sync_get_querry_discord($path)
{
    global $mybb;
    require_once UNIREST;
    
    $headers = discord_right_sync_discord_header();
    $response = Unirest\Request::get('https://discordapp.com/api/' . $path, $headers);
    return $response;
}
function discord_right_sync_patch_querry_discord($path,$content)
{
    global $mybb;
    require_once UNIREST;
    
    //build discord roles update request
    $headers = discord_right_sync_discord_header();
    $headers['Content-Type'] = 'application/json';
    $body = Unirest\Request\Body::json(array(
        'roles' => $content
    ));
    $response = Unirest\Request::patch('https://discordapp.com/api/' . $path, $headers, $body);
    return $response;
}
       
function discord_right_sync_roles($task = NULL)
    {
    global $mybb, $db, $cache;
    require_once UNIREST;
    require_once MYBB_ROOT . '/inc/functions_task.php';
    
    if ($mybb->settings['drs_setting_ratelimit_reset'] > time()) 
        return; //wait with new reqeust till the api rate limit resets
   
    $user_roles = array();
    //get all mybb users with discord name field
    $query = $db->query("
        SELECT `uid`,`usergroup`,`additionalgroups`,`fid" . (int)$mybb->settings['drs_setting_user_fid'] . "`
        FROM `" . TABLE_PREFIX . "users` AS u
        INNER JOIN `" . TABLE_PREFIX . "userfields` AS uf ON (u.`uid`=uf.`ufid`)
        WHERE uf.`fid" . (int)$mybb->settings['drs_setting_user_fid'] . "` IS NOT NULL");
    while ($user = $db->fetch_array($query))
    {
        //join all user usergroups
        $usergroup_string = $user['usergroup'];
        if (!empty($user['additionalgroups'])) 
            $usergroup_string.= ',' . $user['additionalgroups'];
        
        $usergroups = explode(',', $usergroup_string);
        $usergroups = array_diff($usergroups, array('') , array(' ')); //remove all empty elements
        
        
        $user_d_name = trim($user['fid' . $mybb->settings['drs_setting_user_fid']]);;
        if (isset($user_roles[$user_d_name])) //same Discord Name in multiple accounts => ignore accounts.
        {
            $user_roles[$user_d_name] = - 1;
            add_task_log($task, 'ERROR: Duplicated Discord Name: '.htmlspecialchars($user_d_name));
            continue;
        }   
            
            //check for correct NAME#0000 format
            if(!preg_match('/'.DRS_DISCORD_ID_FORMAT.'$/i',$user_d_name))
            {
                //wrong format
                if($mybb->settings['drs_setting_del_id'])
                {
                    //delete user field content
                     $db->update_query('userfields', array("fid".(int)$mybb->settings['drs_setting_user_fid'] => "NULL"), '`ufid` = ' . (int)$user['uid'],"",true);  
                      add_task_log($task, 'ERROR: Invalid Discord Name: '.htmlspecialchars($user_d_name).' (deleted)');      
                }
                continue;
            }
            
        foreach($usergroups as $mybb_gid)
        {
            //add role to user if a discord role is selected for that group
            if (!empty($mybb->settings['drs_setting_gid' . $mybb_gid])) 
                $user_roles[$user_d_name]['mybb_groups'][$mybb_gid] = $mybb->settings['drs_setting_gid' . $mybb_gid];
        }
    }
    $query->free_result;
    
    //get all discord members with their roles
    $response = discord_right_sync_get_querry_discord('guilds/' . $mybb->settings['drs_setting_guild_id'] . '/members?limit=1000');
    if($response->code != 200)
        return false;
        
    foreach($response->body as $d_user_obj)
    {
        $user_d_name = $d_user_obj->user->username . '#' . $d_user_obj->user->discriminator;
        if ($user_roles[$user_d_name] == - 1) //same Discord Name in multiple accounts => ignore accounts.
            continue;
        $user_roles[$user_d_name]['d_roles'] = $d_user_obj->roles;
        $user_roles[$user_d_name]['d_id'] = $d_user_obj->user->id;
    }
    unset($response);
    
    
    $controlled_groups = array(); //controlled groups by mybb
    // Read the usergroups cache
    $usergroups = $cache->read("usergroups");
    // If the groups cache doesn't exist, update it and re-read it
    if (!is_array($usergroups))
        {
        $cache->update_usergroups();
        $usergroups = $cache->read("usergroups");
        }
    //get all discord roles which are controlled by mybb
    foreach($usergroups as $usergroup)
        {
        $discord_role_id_from_mybb = $mybb->settings['drs_setting_gid' . $usergroup['gid']];
        if (!empty($discord_role_id_from_mybb)) 
            $controlled_groups[] = $discord_role_id_from_mybb;
        }
    foreach($user_roles as $user_d_name => $data)
        {
        if (empty($data['mybb_groups']) AND empty($data['d_roles'])) 
            continue;
            
        if (empty($data['d_id'])) 
            continue;
            
        $add_role = array();
        $remove_role = array();
        $must_have_groups = array(); //roles which are assigned to the user by mybb
        if (!empty($data['mybb_groups'])) 
            $must_have_groups = array_merge($must_have_groups, $data['mybb_groups']);
            
        $complete_roles = $data['d_roles'];
        foreach($must_have_groups as $mybb_gid => $d_role_id)
        {
            if (!in_array($d_role_id, $data['d_roles'])) 
                array_push($add_role, $d_role_id);
        }
        foreach($controlled_groups as $mybb_role_id)
        {
            if (in_array($mybb_role_id, $data['d_roles']) AND !in_array($mybb_role_id, $must_have_groups)) 
                array_push($remove_role, $mybb_role_id);
        }
        //check if roles have changed
        if (!empty($add_role) OR !empty($remove_role))
        {
            if (!empty($add_role)) 
                $complete_roles = array_merge($complete_roles, $add_role);
                
            if (!empty($remove_role)) 
                $complete_roles = array_diff($complete_roles, $remove_role);
                
            $complete_roles = array_unique($complete_roles);
            $complete_roles = array_values($complete_roles);
            
            //patch discord roles
            $response = discord_right_sync_patch_querry_discord('guilds/' . $mybb->settings['drs_setting_guild_id'] . '/members/' . $data['d_id'],$complete_roles);
            if ($response->code == 204)
            {
                // success
            }
            if (isset($response->headers['X-RateLimit-Reset'])) //update reset time
            {
                $db->update_query("settings", array(
                    'value' => (int)$response->headers['X-RateLimit-Reset']
                ) , "name = 'drs_setting_ratelimit_reset'");
            }
            if ($response->headers['X-RateLimit-Remaining'] == 0) //no more Querries allowed
            {
                return false;
            }
        }
    }
    return true;
}