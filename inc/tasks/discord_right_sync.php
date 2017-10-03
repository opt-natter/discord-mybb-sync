<?php
/**
 *    Discord right sync task
 *
 *    Synchronising the Mybb Groups with the Discord Roles
 *
 */

if (!defined('IN_MYBB'))
    {
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
    }

function task_discord_right_sync($task)
    {
    require_once MYBB_ROOT . 'inc/plugins/discord_right_sync.php';

    global $mybb, $db, $lang;
    if (discord_right_sync_roles($task))
        {

        // add_task_log($task, 'All roles synced');

        }
      else
        {
        add_task_log($task, 'Not all roles synced. Discord API limit reached.');
        }
    }
