<?php

/*
The Bot requires the MANAGE_ROLES permission. 
These permissions require the owner account to use two-factor authentication 
when used on a guild that has server-wide 2FA enabled.

Installation Steps:
1. Copy the Files into the plugin folder from your MyBB installation.
2. Head over to https://discordapp.com/developers/applications/me#top to create a new App.
3. Create a Bot User and copy the Client ID from the App details and Token from the App Bot User
4. Visit: https://discordapi.com/permissions.html#268435456. insert your Client ID and visit the Link
5. Add the Bot to your Server
6. copy your Guild ID (https://support.discordapp.com/hc/en-us/articles/206346498-Where-can-I-find-my-User-Server-Message-ID-)
7. Open your Admin-CP and install this plugin (Configuration -> Plugins)
8. Open the Settings Tab in the Admin-CP (Configuration -> Settings -> Discord Right Sync Settings) and enter your Bot Token and Guild ID
9. Open the Settings Tab and configure your groups
*/

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.");
}

if(file_exists(MYBB_ROOT . "inc/plugins/Unirest/Unirest.php"))
    include_once(MYBB_ROOT . "inc/plugins/Unirest/Unirest.php");
    
if(discord_right_sync_is_installed())   
{
    $plugins->add_hook("admin_config_settings_change", "discord_right_sync_settings_update"); 
    $plugins->add_hook("admin_page_output_header", "discord_right_sync_message");  
}
                                    

function discord_right_sync_message(&$arg)
{
    if(!file_exists(MYBB_ROOT . "inc/plugins/Unirest/Unirest.php")) 
        $arg['this']->extra_messages[]=array('type'=>'error','message'=>'Unirest is required by '.discord_right_sync_info()['name']); 
}
    
function discord_right_sync_info()
{
    return array(
        "name"            => "Discord Right Sync",
        "description"    => "A Discord Bot for syncing the rights from the MyBB to a Discord Guild.",
        "website"        => "http://opt-community.de/",
        "author"        => "natter",
        "authorsite"    => "http://opt-community.de/",
        "version"        => "0.1.0",
        "guid"             => "",
        "codename"        => "",
        "compatibility" => "18*"
    );
}

function discord_right_sync_install()
{
global $db, $mybb,$cache;

    $setting_group = array(
        'name' => 'drs_settings',
        'title' => 'Discord Right Sync Settings',
        'description' => 'This is my plugin and it does some things',
        'disporder' => 0, // The order your setting group will display
        'isdefault' => 0
    ); 
$gid = $db->insert_query("settinggroups", $setting_group);
    
$disporder=0;
 
$setting_array=array();

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
                    'optionscode' => 'select',
                    'value' => (int)0,
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
            $setting['name'] = $name;
            $setting['gid'] = $gid;

            $db->insert_query('settings', $setting);
        }
   
   
//drs_setting_gid is created for each user group in discord_right_sync_settings_update() 

rebuild_settings();
}

function discord_right_sync_is_installed()
{
    global $mybb, $db;

    $result = $db->simple_select('settinggroups', 'gid', "name = 'drs_settings'", array('limit' => 1));
    $group = $db->fetch_array($result);

    return !empty($group['gid']);
}

function discord_right_sync_uninstall()
{
global $db;

$db->delete_query('settings', "name LIKE 'drs_setting%'");
$db->delete_query('settinggroups', "name = 'drs_settings'");

rebuild_settings();
}

function discord_right_sync_activate()
{

}

function discord_right_sync_deactivate()
{

}

function discord_right_sync_get_setting_group_id()
{
   global $db,$mybb;  
    $query = $db->simple_select('settinggroups', 'gid', "name = 'drs_settings'", array('limit' => 1));
    $group = $db->fetch_array($query);
    return $group['gid'];
}

function discord_right_sync_settings_update()
{
    global $db,$mybb,$page,$cache;                  
    

    if(discord_right_sync_get_setting_group_id()!=$mybb->input['gid'])
        return;
        
        
    //update user Fild selection
    $select_user_field_str='';
    $query = $db->simple_select('profilefields', 'fid,name');
    while ($profilefields = $db->fetch_array($query))
    { 
    $select_user_field_str.='\n'.htmlspecialchars($profilefields['fid']).'='.htmlspecialchars($profilefields['name']);
    } 
    $db->update_query("settings",array('optionscode' => 'select'.$select_user_field_str),"name = 'drs_setting_user_fid'");

    
    $discord_right_sync_settings_set=true;
    
    //check for auth token
    if(empty($mybb->settings['drs_setting_token']))  
    {
        $discord_right_sync_settings_set=false;
        $page->extra_messages[]=array('type'=>'error','message'=>discord_right_sync_info()['name'].': Auth Token is not set'); 
    }  
        
     //check for guild id   
    if(empty($mybb->settings['drs_setting_guild_id']))
    {
        $discord_right_sync_settings_set=false; 
        $page->extra_messages[]=array('type'=>'error','message'=>discord_right_sync_info()['name'].': Auth Guild ID is not set');  
    } 
    
   
    if ($discord_right_sync_settings_set)
    {    
    $disporder=10;

        // Read the usergroups cache
        $usergroups = $cache->read("usergroups");
        // If the groups cache doesn't exist, update it and re-read it
        if(!is_array($usergroups))
        {
            $cache->update_usergroups();
            $usergroups = $cache->read("usergroups");
        }
        $setting_array=array();
        foreach ($usergroups as $usergroup){
            if(!$usergroup['isbannedgroup'] AND !isset($mybb->settings['drs_setting_gid'.(int)$usergroup['gid']]))
            {
                $setting_array['drs_setting_gid'.(int)$usergroup['gid']] = array(
                            'title' => $db->escape_string($usergroup['title']),
                            'description' => $db->escape_string($usergroup['description']),
                            'optionscode' => 'select\n0=Enter Settings First',
                            'value' => (int)0,
                            'disporder' => (int)$disporder++
                );
            }
        }

        foreach($setting_array as $name => $setting)
        {
            $setting['name'] = $name;
            $setting['gid'] = (int)$mybb->input['gid'];

            $db->insert_query('settings', $setting);
        }
        
            $response=discord_right_sync_querry_discord('guilds/'.$mybb->settings['drs_setting_guild_id'].'/roles');

            if($response->code==200)
            {
                usort($response->body, function($a, $b) {
                    return $a->position - $b->position;
                });

                $select_group_str='\n0=none';
                foreach($response->body as $role)
                {
                    $select_group_str.='\n'.htmlspecialchars($role->id).'='.htmlspecialchars($role->name);  
                }
                 $db->update_query("settings",array('optionscode' => 'select'.$select_group_str),"name LIKE 'drs_setting_gid%' AND gid='".(int)$mybb->input['gid']."'");
    
            }else{
                    $page->extra_messages[]=array('type'=>'error','message'=>discord_right_sync_info()['name'].': Could connect to Discord guild. ('.$response->body->message.')'); 
            }
     rebuild_settings();      
    }        
}
function discord_right_sync_discord_header()
{
    global $mybb;
                return array('Authorization' => 'Bot '.$mybb->settings['drs_setting_token'], 
                                'User-Agent' => 'DRS ('.discord_right_sync_info()['website'].','.discord_right_sync_info()['version'].')');
}


function discord_right_sync_querry_discord($path)
{
    global $mybb;
    
            $headers = discord_right_sync_discord_header();
            $response = Unirest\Request::get('https://discordapp.com/api/'.$path, $headers);
           return $response;
}

function discord_right_sync_roles()
{
    global $mybb,$db,$cache;
    
    if($mybb->settings['drs_setting_ratelimit_reset']>time())
        return; //wait with new reqeust till the api rate limit resets
    
$user_roles=array();
    $query = $db->query("
        SELECT `usergroup`,`additionalgroups`,`fid".$db->escape_string($mybb->settings['drs_setting_user_fid'])."`
        FROM `".TABLE_PREFIX."users` AS u
        INNER JOIN `".TABLE_PREFIX."userfields` AS uf ON (u.`uid`=uf.`ufid`)
        WHERE uf.`fid".$db->escape_string($mybb->settings['drs_setting_user_fid'])."` LIKE '%#____' ");
    while($user = $db->fetch_array($query))
    {
        $usergroup_string=$user['usergroup'];
        if(!empty($user['additionalgroups']))
            $usergroup_string.=','.$user['additionalgroups'];
        $usergroups = explode(',', $usergroup_string);
        $usergroups = array_diff($usergroups, array(''),array(' '));//remove all empty elements
        
        $user_d_name= $user['fid'.$mybb->settings['drs_setting_user_fid']];
        
        if(isset($user_roles[$user_d_name])) //same Discord Name in multiple accounts => ignore accounts.
        {
            $user_roles[$user_d_name]=-1;
            continue;
        }
            
        foreach($usergroups as $mybb_gid)
        {
            if(!empty($mybb->settings['drs_setting_gid'.$mybb_gid]))
                $user_roles[$user_d_name]['mybb_groups'][$mybb_gid]=$mybb->settings['drs_setting_gid'.$mybb_gid];
        }
    }
    $query->free_result;                                                                                  
   
$response=discord_right_sync_querry_discord('guilds/'.$mybb->settings['drs_setting_guild_id'].'/members?limit=1000');

foreach($response->body as $d_user_obj)
{
    $user_d_name=$d_user_obj->user->username.'#'.$d_user_obj->user->discriminator;
    if($user_roles[$user_d_name]==-1)  //same Discord Name in multiple accounts => ignore accounts.
        continue;
    $user_roles[$user_d_name]['d_roles']= $d_user_obj->roles;
    $user_roles[$user_d_name]['d_id']= $d_user_obj->user->id;
}
unset($response);

$controlled_groups=array(); //controlled groups by mybb
        // Read the usergroups cache
        $usergroups = $cache->read("usergroups");
        // If the groups cache doesn't exist, update it and re-read it
        if(!is_array($usergroups))
        {
            $cache->update_usergroups();
            $usergroups = $cache->read("usergroups");
        }
        foreach ($usergroups as $usergroup){
            $discord_role_id_from_mybb=$mybb->settings['drs_setting_gid'.$usergroup['gid']];
            if(!empty($discord_role_id_from_mybb))
                $controlled_groups[]=$discord_role_id_from_mybb;   
        }
        

foreach($user_roles as $user_d_name => $data)
{
    if(empty($data['mybb_groups']) AND empty($data['d_roles']))
        continue;
        
    if(empty($data['d_id']))
         continue;
         
    $add_role=array();
    $remove_role=array();
    $must_have_groups=array();//roles which are assigned to the user by mybb
    if(!empty($data['mybb_groups']))
        $must_have_groups=array_merge($must_have_groups,$data['mybb_groups']);
    
    $complete_roles=$data['d_roles'];
    
    foreach($must_have_groups as $mybb_gid => $d_role_id)
    {
        if(!in_array($d_role_id,$data['d_roles']))          
            array_push($add_role, $d_role_id);               
    }
    
    foreach($controlled_groups as $mybb_role_id)
    {
        if(in_array($mybb_role_id,$data['d_roles']) AND !in_array($mybb_role_id,$must_have_groups))
            array_push($remove_role, $mybb_role_id);                   
    }  
    
    if(!empty($add_role) OR !empty($remove_role))
    {
        if(!empty($add_role))
            $complete_roles=array_merge($complete_roles,$add_role);
        if(!empty($remove_role))    
            $complete_roles=array_diff($complete_roles,$remove_role);
        $complete_roles=array_unique($complete_roles);
        $complete_roles=array_values($complete_roles);
		
    $headers = discord_right_sync_discord_header();
    $headers['Content-Type'] = 'application/json';

    $body = Unirest\Request\Body::json(array('roles'=>$complete_roles));

            $response = Unirest\Request::patch('https://discordapp.com/api/guilds/'.$mybb->settings['drs_setting_guild_id'].'/members/'.$data['d_id'], $headers,$body);
    if($response->code==204)
        {
            //success
        }  
        
    if(isset($response->headers['X-RateLimit-Reset'])) //update reset time
        { 
             $db->update_query("settings",array('value' => (int)$response->headers['X-RateLimit-Reset']),"name = 'drs_setting_ratelimit_reset'");
        }    
    if($response->headers['X-RateLimit-Remaining']==0) //no more Querries allowed
        {
            return;    
        }     
        
        
    }
    
}
}