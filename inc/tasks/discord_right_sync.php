<?php
/**
 *	Discord right sync task
 *
 *	Synchronising the Mybb Groups with the Discord Roles
 *
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

function task_discord_right_sync($task)
{
    global $mybb, $db, $lang;

    discord_right_sync_roles();
}
