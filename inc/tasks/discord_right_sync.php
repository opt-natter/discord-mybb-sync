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
	discord_right_sync_add_task_log($task, 'DEBUG: task_discord_right_sync was called', 'TASK discord_right_sync.php line '. __LINE__,2);
    if (discord_right_sync_roles($task))
        {
			discord_right_sync_add_task_log($task, 'INFO: All roles synced', 'TASK discord_right_sync.php line '. __LINE__,1);
        }
      else
        {
			discord_right_sync_add_task_log($task, 'discord_right_sync returned false', 'TASK discord_right_sync.php line '. __LINE__,0);
        }
    }
