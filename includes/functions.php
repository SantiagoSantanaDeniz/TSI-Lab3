<?php
/***************************************************************************
 *                               functions.php
 *                            -------------------
 *   begin                : Saturday, Feb 13, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: functions.php 8377 2008-02-10 12:52:05Z acydburn $
 *
 *
 ***************************************************************************/

/***************************************************************************
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 *
 ***************************************************************************/

function get_db_stat($mode)
{
	global $db;

	switch( $mode )
	{
		case 'usercount':
			$sql = "SELECT COUNT(user_id) AS total
				FROM " . USERS_TABLE . "
				WHERE user_id <> " . ANONYMOUS;
			break;

		case 'newestuser':
			$sql = "SELECT user_id, username
				FROM " . USERS_TABLE . "
				WHERE user_id <> " . ANONYMOUS . "
				ORDER BY user_id DESC
				LIMIT 1";
			break;

		case 'postcount':
		case 'topiccount':
			$sql = "SELECT SUM(forum_topics) AS topic_total, SUM(forum_posts) AS post_total
				FROM " . FORUMS_TABLE;
			break;
	}

	if ( !($result = $db->sql_query($sql)) )
	{
		return false;
	}

	$row = $db->sql_fetchrow($result);

	switch ( $mode )
	{
		case 'usercount':
			return $row['total'];
			break;
		case 'newestuser':
			return $row;
			break;
		case 'postcount':
			return $row['post_total'];
			break;
		case 'topiccount':
			return $row['topic_total'];
			break;
	}

	return false;
}

// added at phpBB 2.0.11 to properly format the username
function phpbb_clean_username($username)
{


	$username = substr(htmlspecialchars(str_replace("\'", "'", trim($username))), 0, 80);


	return $username;
}

/**
* This function is a wrapper for ltrim, as charlist is only supported in php >= 4.1.0
* Added in phpBB 2.0.18
*/
function phpbb_ltrim($str, $charlist = false)
{
	if ($charlist === false)
	{
		return ltrim($str);
	}
	
	$php_version = explode('.', PHP_VERSION);

	// php version < 4.1.0
	if ((int) $php_version[0] < 4 || ((int) $php_version[0] == 4 && (int) $php_version[1] < 1))
	{
		while ($str{0} == $charlist)
		{
			$str = substr($str, 1);
		}
	}
	else
	{
		$str = ltrim($str, $charlist);
	}

	return $str;
}

// added at phpBB 2.0.12 to fix a bug in PHP 4.3.10 (only supporting charlist in php >= 4.1.0)
function phpbb_rtrim($str, $charlist = false)
{
	if ($charlist === false)
	{
		return rtrim($str);
	}
	
	$php_version = explode('.', PHP_VERSION);

	// php version < 4.1.0
	if ((int) $php_version[0] < 4 || ((int) $php_version[0] == 4 && (int) $php_version[1] < 1))
	{
		while ($str{strlen($str)-1} == $charlist)
		{
			$str = substr($str, 0, strlen($str)-1);
		}
	}
	else
	{
		$str = rtrim($str, $charlist);
	}

	return $str;
}

/**
* Our own generator of random values
* This uses a constantly changing value as the base for generating the values
* The board wide setting is updated once per page if this code is called
* With thanks to Anthrax101 for the inspiration on this one
* Added in phpBB 2.0.20
*/
function dss_rand()
{
	global $db, $board_config, $dss_seeded;

	$val = $board_config['rand_seed'] . microtime();
	$val = md5($val);
	$board_config['rand_seed'] = md5($board_config['rand_seed'] . $val . 'a');
   
	if($dss_seeded !== true)
	{
		$sql = "UPDATE " . CONFIG_TABLE . " SET
			config_value = '" . $board_config['rand_seed'] . "'
			WHERE config_name = 'rand_seed'";
		
		if( !$db->sql_query($sql) )
		{
			message_die(GENERAL_ERROR, "Unable to reseed PRNG", "", __LINE__, __FILE__, $sql);
		}

		$dss_seeded = true;
	}

	return substr($val, 4, 16);
}
//
// Get Userdata, $user can be username or user_id. If force_str is true, the username will be forced.
//
function get_userdata($user, $force_str = false) {
    global $db;

	// Comienzo SQL-07 //
    if (!is_string($user) && !is_int($user)) {
        return false;
    }

    if (!is_numeric($user) || $force_str) {
        $username = phpbb_clean_username((string) $user);
        $sql_username = $db->sql_escape_string($username);

        $where_sql = "username = '" . $sql_username . "'";
    } else {
        $user_id = intval($user);
        $where_sql = 'user_id = ' . $user_id;
    }

    $sql = "SELECT *
        FROM " . USERS_TABLE . "
        WHERE " . $where_sql . "
            AND user_id <> " . intval(ANONYMOUS);

    $result = $db->sql_query($sql);

    if (!$result) {
        error_log('User lookup failed');
        message_die(GENERAL_ERROR, 'No se pudo obtener la información del usuario.');
    }

    $row = $db->sql_fetchrow($result);
    $db->sql_freeresult($result);

    return $row ? $row : false;
	// Fin SQL-07 //
}


function make_jumpbox($action, $match_forum_id = 0) {
	global $template, $userdata, $lang, $db, $nav_links, $phpEx, $SID;

//	$is_auth = auth(AUTH_VIEW, AUTH_LIST_ALL, $userdata);

	$sql = "SELECT c.cat_id, c.cat_title, c.cat_order
		FROM " . CATEGORIES_TABLE . " c, " . FORUMS_TABLE . " f
		WHERE f.cat_id = c.cat_id
		GROUP BY c.cat_id, c.cat_title, c.cat_order
		ORDER BY c.cat_order";
	if ( !($result = $db->sql_query($sql)) )
	{
		message_die(GENERAL_ERROR, "Couldn't obtain category list.", "", __LINE__, __FILE__, $sql);
	}
	
	$category_rows = array();
	while ( $row = $db->sql_fetchrow($result) )
	{
		$category_rows[] = $row;
	}

	if ( $total_categories = count($category_rows) )
	{
		$sql = "SELECT *
			FROM " . FORUMS_TABLE . "
			ORDER BY cat_id, forum_order";
		if ( !($result = $db->sql_query($sql)) )
		{
			message_die(GENERAL_ERROR, 'Could not obtain forums information', '', __LINE__, __FILE__, $sql);
		}

		$boxstring = '<select name="' . POST_FORUM_URL . '" onchange="if(this.options[this.selectedIndex].value != -1){ forms[\'jumpbox\'].submit() }"><option value="-1">' . $lang['Select_forum'] . '</option>';

		$forum_rows = array();
		while ( $row = $db->sql_fetchrow($result) )
		{
			$forum_rows[] = $row;
		}

		if ( $total_forums = count($forum_rows) )
		{
			for($i = 0; $i < $total_categories; $i++)
			{
				$boxstring_forums = '';
				for($j = 0; $j < $total_forums; $j++)
				{
					if ( $forum_rows[$j]['cat_id'] == $category_rows[$i]['cat_id'] && $forum_rows[$j]['auth_view'] <= AUTH_REG )
					{

//					if ( $forum_rows[$j]['cat_id'] == $category_rows[$i]['cat_id'] && $is_auth[$forum_rows[$j]['forum_id']]['auth_view'] )
//					{
						$selected = ( $forum_rows[$j]['forum_id'] == $match_forum_id ) ? 'selected="selected"' : '';
						$boxstring_forums .= '<option value="' . (int) $forum_rows[$j]['forum_id'] . '"' . $selected . '>' . htmlspecialchars($forum_rows[$j]['forum_name'], ENT_QUOTES, $lang['ENCODING']) . '</option>';

						//
						// Add an array to $nav_links for the Mozilla navigation bar.
						// 'chapter' and 'forum' can create multiple items, therefore we are using a nested array.
						//
						$nav_links['chapter forum'][$forum_rows[$j]['forum_id']] = array (
							'url' => append_sid("viewforum.$phpEx?" . POST_FORUM_URL . "=" . $forum_rows[$j]['forum_id']),
							'title' => $forum_rows[$j]['forum_name']
						);
								
					}
				}

				if ( $boxstring_forums != '' )
				{
					$boxstring .= '<option value="-1">&nbsp;</option>';
					$boxstring .= '<option value="-1">' . htmlspecialchars($category_rows[$i]['cat_title'], ENT_QUOTES, $lang['ENCODING']) . '</option>';
					$boxstring .= '<option value="-1">----------------</option>';
					$boxstring .= $boxstring_forums;
				}
			}
		}

		$boxstring .= '</select>';
	}
	else
	{
		$boxstring .= '<select name="' . POST_FORUM_URL . '" onchange="if(this.options[this.selectedIndex].value != -1){ forms[\'jumpbox\'].submit() }"></select>';
	}

	// Let the jumpbox work again in sites having additional session id checks.
//	if ( !empty($SID) )
//	{
		$boxstring .= '<input type="hidden" name="sid" value="' . $userdata['session_id'] . '" />';
//	}

	$template->set_filenames(array(
		'jumpbox' => 'jumpbox.tpl')
	);
	$template->assign_vars(array(
		'L_GO' => $lang['Go'],
		'L_JUMP_TO' => $lang['Jump_to'],
		'L_SELECT_FORUM' => $lang['Select_forum'],

		'S_JUMPBOX_SELECT' => $boxstring,
		'S_JUMPBOX_ACTION' => append_sid($action))
	);
	$template->assign_var_from_handle('JUMPBOX', 'jumpbox');

	return;
}

//
// Initialise user settings on page load
function init_userprefs($userdata)
{
	global $board_config, $theme, $images;
	global $template, $lang, $phpEx, $phpbb_root_path, $db;
	global $nav_links;

	if ( $userdata['user_id'] != ANONYMOUS )
	{
		if ( !empty($userdata['user_lang']))
		{
			$default_lang = phpbb_ltrim(basename(phpbb_rtrim($userdata['user_lang'])), "'");
		}

		if ( !empty($userdata['user_dateformat']) )
		{
			$board_config['default_dateformat'] = $userdata['user_dateformat'];
		}

		if ( isset($userdata['user_timezone']) )
		{
			$board_config['board_timezone'] = $userdata['user_timezone'];
		}
	}
	else
	{
		$default_lang = phpbb_ltrim(basename(phpbb_rtrim($board_config['default_lang'])), "'");
	}

	if ( !file_exists(@phpbb_realpath($phpbb_root_path . 'language/lang_' . $default_lang . '/lang_main.'.$phpEx)) )
	{
		if ( $userdata['user_id'] != ANONYMOUS )
		{
			// For logged in users, try the board default language next
			$default_lang = phpbb_ltrim(basename(phpbb_rtrim($board_config['default_lang'])), "'");
		}
		else
		{
			// For guests it means the default language is not present, try english
			// This is a long shot since it means serious errors in the setup to reach here,
			// but english is part of a new install so it's worth us trying
			$default_lang = 'english';
		}

		if ( !file_exists(@phpbb_realpath($phpbb_root_path . 'language/lang_' . $default_lang . '/lang_main.'.$phpEx)) )
		{
			message_die(CRITICAL_ERROR, 'Could not locate valid language pack');
		}
	}

	// If we've had to change the value in any way then let's write it back to the database
	// before we go any further since it means there is something wrong with it
	if ( $userdata['user_id'] != ANONYMOUS && $userdata['user_lang'] !== $default_lang )
	{
		$sql = 'UPDATE ' . USERS_TABLE . "
			SET user_lang = '" . $db->sql_escape_string($default_lang) . "'
			WHERE user_lang = '" . $db->sql_escape_string($userdata['user_lang']) . "'";

		if ( !($result = $db->sql_query($sql)) )
		{
			message_die(CRITICAL_ERROR, 'Could not update user language info');
		}

		$userdata['user_lang'] = $default_lang;
	}
	elseif ( $userdata['user_id'] == ANONYMOUS && $board_config['default_lang'] !== $default_lang )
	{
		$sql = 'UPDATE ' . CONFIG_TABLE . "
			SET config_value = '" . $db->sql_escape_string($default_lang) . "'
			WHERE config_name = 'default_lang'";

		if ( !($result = $db->sql_query($sql)) )
		{
			message_die(CRITICAL_ERROR, 'Could not update user language info');
		}
	}

	$board_config['default_lang'] = $default_lang;

	include($phpbb_root_path . 'language/lang_' . $board_config['default_lang'] . '/lang_main.' . $phpEx);

	if ( defined('IN_ADMIN') )
	{
		if( !file_exists(@phpbb_realpath($phpbb_root_path . 'language/lang_' . $board_config['default_lang'] . '/lang_admin.'.$phpEx)) )
		{
			$board_config['default_lang'] = 'english';
		}

		include($phpbb_root_path . 'language/lang_' . $board_config['default_lang'] . '/lang_admin.' . $phpEx);
	}

	//
	// Set up style
	//
	if ( !$board_config['override_user_style'] )
	{
		if ( $userdata['user_id'] != ANONYMOUS && $userdata['user_style'] > 0 )
		{
			if ( $theme = setup_style($userdata['user_style']) )
			{
				return;
			}
		}
	}

	$theme = setup_style($board_config['default_style']);

	//
	// Mozilla navigation bar
	// Default items that should be valid on all pages.
	// Defined here to correctly assign the Language Variables
	// and be able to change the variables within code.
	//
	$nav_links['top'] = array ( 
		'url' => append_sid($phpbb_root_path . 'index.' . $phpEx),
		'title' => sprintf($lang['Forum_Index'], $board_config['sitename'])
	);
	$nav_links['search'] = array ( 
		'url' => append_sid($phpbb_root_path . 'search.' . $phpEx),
		'title' => $lang['Search']
	);
	$nav_links['help'] = array ( 
		'url' => append_sid($phpbb_root_path . 'faq.' . $phpEx),
		'title' => $lang['FAQ']
	);
	$nav_links['author'] = array ( 
		'url' => append_sid($phpbb_root_path . 'memberlist.' . $phpEx),
		'title' => $lang['Memberlist']
	);

	return;
}

function setup_style($style)
{
	global $db, $board_config, $template, $images, $phpbb_root_path;

	$sql = 'SELECT *
		FROM ' . THEMES_TABLE . '
		WHERE themes_id = ' . (int) $style;
	if ( !($result = $db->sql_query($sql)) )
	{
		message_die(CRITICAL_ERROR, 'Could not query database for theme info');
	}

	if ( !($row = $db->sql_fetchrow($result)) )
	{
		// We are trying to setup a style which does not exist in the database
		// Try to fallback to the board default (if the user had a custom style)
		// and then any users using this style to the default if it succeeds
		if ( $style != $board_config['default_style'])
		{
			$sql = 'SELECT *
				FROM ' . THEMES_TABLE . '
				WHERE themes_id = ' . (int) $board_config['default_style'];
			if ( !($result = $db->sql_query($sql)) )
			{
				message_die(CRITICAL_ERROR, 'Could not query database for theme info');
			}

			if ( $row = $db->sql_fetchrow($result) )
			{
				$db->sql_freeresult($result);

				$sql = 'UPDATE ' . USERS_TABLE . '
					SET user_style = ' . (int) $board_config['default_style'] . "
					WHERE user_style = " . (int) $style;
				if ( !($result = $db->sql_query($sql)) )
				{
					message_die(CRITICAL_ERROR, 'Could not update user theme info');
				}
			}
			else
			{
				message_die(CRITICAL_ERROR, "Could not get theme data for themes_id [$style]");
			}
		}
		else
		{
			message_die(CRITICAL_ERROR, "Could not get theme data for themes_id [$style]");
		}
	}

	$row = phpbb_style_row($row, false);
	$template_path = 'templates/' ;
	$template_name = $row['template_name'] ;

	$template = new Template($phpbb_root_path . $template_path . $template_name);

	if ( $template )
	{
		$current_template_path = $template_path . $template_name;
		@include($phpbb_root_path . $template_path . $template_name . '/' . $template_name . '.cfg');

		if ( !defined('TEMPLATE_CONFIG') )
		{
			message_die(CRITICAL_ERROR, "Could not open $template_name template config file", '', __LINE__, __FILE__);
		}

		$img_lang = ( file_exists(@phpbb_realpath($phpbb_root_path . $current_template_path . '/images/lang_' . $board_config['default_lang'])) ) ? $board_config['default_lang'] : 'english';

		while( list($key, $value) = @each($images) )
		{
			if ( !is_array($value) )
			{
				$images[$key] = str_replace('{LANG}', 'lang_' . $img_lang, $value);
			}
		}
	}

	return $row;
}

function encode_ip($dotquad_ip)
{
	$ip_sep = explode('.', $dotquad_ip);
	return sprintf('%02x%02x%02x%02x', $ip_sep[0], $ip_sep[1], $ip_sep[2], $ip_sep[3]);
}

function decode_ip($int_ip)
{
	$hexipbang = explode('.', chunk_split($int_ip, 2, '.'));
	return hexdec($hexipbang[0]). '.' . hexdec($hexipbang[1]) . '.' . hexdec($hexipbang[2]) . '.' . hexdec($hexipbang[3]);
}

//
// Create date/time from format and timezone
//
function create_date($format, $gmepoch, $tz)
{
	global $board_config, $lang;
	static $translate;

	if ( empty($translate) && $board_config['default_lang'] != 'english' )
	{
		@reset($lang['datetime']);
		while ( list($match, $replace) = @each($lang['datetime']) )
		{
			$translate[$match] = $replace;
		}
	}

	return ( !empty($translate) ) ? strtr(@gmdate($format, $gmepoch + (3600 * $tz)), $translate) : @gmdate($format, $gmepoch + (3600 * $tz));
}

//
// Pagination routine, generates
// page number sequence
//
function generate_pagination($base_url, $num_items, $per_page, $start_item, $add_prevnext_text = TRUE)
{
	global $lang;

	$total_pages = ceil($num_items/$per_page);

	if ( $total_pages == 1 )
	{
		return '';
	}

	$on_page = floor($start_item / $per_page) + 1;

	$page_string = '';
	if ( $total_pages > 10 )
	{
		$init_page_max = ( $total_pages > 3 ) ? 3 : $total_pages;

		for($i = 1; $i < $init_page_max + 1; $i++)
		{
			$page_string .= ( $i == $on_page ) ? '<b>' . $i . '</b>' : '<a href="' . append_sid($base_url . "&amp;start=" . ( ( $i - 1 ) * $per_page ) ) . '">' . $i . '</a>';
			if ( $i <  $init_page_max )
			{
				$page_string .= ", ";
			}
		}

		if ( $total_pages > 3 )
		{
			if ( $on_page > 1  && $on_page < $total_pages )
			{
				$page_string .= ( $on_page > 5 ) ? ' ... ' : ', ';

				$init_page_min = ( $on_page > 4 ) ? $on_page : 5;
				$init_page_max = ( $on_page < $total_pages - 4 ) ? $on_page : $total_pages - 4;

				for($i = $init_page_min - 1; $i < $init_page_max + 2; $i++)
				{
					$page_string .= ($i == $on_page) ? '<b>' . $i . '</b>' : '<a href="' . append_sid($base_url . "&amp;start=" . ( ( $i - 1 ) * $per_page ) ) . '">' . $i . '</a>';
					if ( $i <  $init_page_max + 1 )
					{
						$page_string .= ', ';
					}
				}

				$page_string .= ( $on_page < $total_pages - 4 ) ? ' ... ' : ', ';
			}
			else
			{
				$page_string .= ' ... ';
			}

			for($i = $total_pages - 2; $i < $total_pages + 1; $i++)
			{
				$page_string .= ( $i == $on_page ) ? '<b>' . $i . '</b>'  : '<a href="' . append_sid($base_url . "&amp;start=" . ( ( $i - 1 ) * $per_page ) ) . '">' . $i . '</a>';
				if( $i <  $total_pages )
				{
					$page_string .= ", ";
				}
			}
		}
	}
	else
	{
		for($i = 1; $i < $total_pages + 1; $i++)
		{
			$page_string .= ( $i == $on_page ) ? '<b>' . $i . '</b>' : '<a href="' . append_sid($base_url . "&amp;start=" . ( ( $i - 1 ) * $per_page ) ) . '">' . $i . '</a>';
			if ( $i <  $total_pages )
			{
				$page_string .= ', ';
			}
		}
	}

	if ( $add_prevnext_text )
	{
		if ( $on_page > 1 )
		{
			$page_string = ' <a href="' . append_sid($base_url . "&amp;start=" . ( ( $on_page - 2 ) * $per_page ) ) . '">' . $lang['Previous'] . '</a>&nbsp;&nbsp;' . $page_string;
		}

		if ( $on_page < $total_pages )
		{
			$page_string .= '&nbsp;&nbsp;<a href="' . append_sid($base_url . "&amp;start=" . ( $on_page * $per_page ) ) . '">' . $lang['Next'] . '</a>';
		}

	}

	$page_string = $lang['Goto_page'] . ' ' . $page_string;

	return $page_string;
}

//
// This does exactly what preg_quote() does in PHP 4-ish
// If you just need the 1-parameter preg_quote call, then don't bother using this.
//
function phpbb_preg_quote($str, $delimiter)
{
	$text = preg_quote($str);
	$text = str_replace($delimiter, '\\' . $delimiter, $text);
	
	return $text;
}

//
// Obtain list of naughty words and build preg style replacement arrays for use by the
// calling script, note that the vars are passed as references this just makes it easier
// to return both sets of arrays
//
function obtain_word_list(&$orig_word, &$replacement_word) {
	global $db, $lang;

	//
	// Define censored word matches
	//
	$sql = "SELECT word, replacement
		FROM  " . WORDS_TABLE;
	if( !($result = $db->sql_query($sql)) ) {
		message_die(GENERAL_ERROR, 'Could not get censored words from database', '', __LINE__, __FILE__, $sql);
	}

	if ( $row = $db->sql_fetchrow($result) ) {
		do {
			$orig_word[] = '#\b(' . str_replace('\*', '\w*?', preg_quote($row['word'], '#')) . ')\b#i';
			$replacement_word[] = htmlspecialchars($row['replacement'], ENT_QUOTES, $lang['ENCODING']);
		} while ( $row = $db->sql_fetchrow($result) );
	}

	return true;
}

//
// This is general replacement for die(), allows templated
// output in users (or default) language, etc.
//
// $msg_code can be one of these constants:
//
// GENERAL_MESSAGE : Use for any simple text message, eg. results 
// of an operation, authorisation failures, etc.
//
// GENERAL ERROR : Use for any error which occurs _AFTER_ the 
// common.php include and session code, ie. most errors in 
// pages/functions
//
// CRITICAL_MESSAGE : Used when basic config data is available but 
// a session may not exist, eg. banned users
//
// CRITICAL_ERROR : Used when config data cannot be obtained, eg
// no database connection. Should _not_ be used in 99.5% of cases
//
function message_die($msg_code, $msg_text = '', $msg_title = '', $err_line = '', $err_file = '', $sql = '')
{
	global $db, $template, $board_config, $theme, $lang, $phpEx, $phpbb_root_path, $nav_links, $gen_simple_header, $images;
	global $userdata, $user_ip, $session_length;
	global $starttime;

	if(defined('HAS_DIED'))
	{
		die("message_die() was called multiple times. This isn't supposed to happen. Was message_die() used in page_tail.php?");
	}
	
	define('HAS_DIED', 1);
	

	$sql_store = $sql;
	
	//
	// Get SQL error if we are debugging. Do this as soon as possible to prevent 
	// subsequent queries from overwriting the status of sql_error()
	//
	if ( DEBUG && ( $msg_code == GENERAL_ERROR || $msg_code == CRITICAL_ERROR ) ) {
		$sql_error = $db->sql_error();
		$debug_encoding = !empty($lang['ENCODING']) ? $lang['ENCODING'] : 'ISO-8859-1';

		$debug_text = '';

		if ( $sql_error['message'] != '' ) {
			$debug_text .= '<br /><br />SQL Error : ' . (int) $sql_error['code'] . ' ' . htmlspecialchars($sql_error['message'], ENT_QUOTES, $debug_encoding);
		}

		if ( $sql_store != '' ) {
			$debug_text .= '<br /><br />' . htmlspecialchars($sql_store, ENT_QUOTES, $debug_encoding);
		}

		if ( $err_line != '' && $err_file != '' ) {
			$debug_text .= '<br /><br />Line : ' . (int) $err_line . '<br />File : ' . htmlspecialchars(basename($err_file), ENT_QUOTES, $debug_encoding);
		}
	}

	if( empty($userdata) && ( $msg_code == GENERAL_MESSAGE || $msg_code == GENERAL_ERROR ) )
	{
		$userdata = session_pagestart($user_ip, PAGE_INDEX);
		init_userprefs($userdata);
	}

	//
	// If the header hasn't been output then do it
	//
	if ( !defined('HEADER_INC') && $msg_code != CRITICAL_ERROR )
	{
		if ( empty($lang) )
		{
			if ( !empty($board_config['default_lang']) )
			{
				include($phpbb_root_path . 'language/lang_' . $board_config['default_lang'] . '/lang_main.'.$phpEx);
			}
			else
			{
				include($phpbb_root_path . 'language/lang_english/lang_main.'.$phpEx);
			}
		}

		if ( empty($template) || empty($theme) )
		{
			$theme = setup_style($board_config['default_style']);
		}

		//
		// Load the Page Header
		//
		if ( !defined('IN_ADMIN') )
		{
			include($phpbb_root_path . 'includes/page_header.'.$phpEx);
		}
		else
		{
			include($phpbb_root_path . 'admin/page_header_admin.'.$phpEx);
		}
	}

	switch($msg_code)
	{
		case GENERAL_MESSAGE:
			if ( $msg_title == '' )
			{
				$msg_title = $lang['Information'];
			}
			break;

		case CRITICAL_MESSAGE:
			if ( $msg_title == '' )
			{
				$msg_title = $lang['Critical_Information'];
			}
			break;

		case GENERAL_ERROR:
			if ( $msg_text == '' )
			{
				$msg_text = $lang['An_error_occured'];
			}

			if ( $msg_title == '' )
			{
				$msg_title = $lang['General_Error'];
			}
			break;

		case CRITICAL_ERROR:
			//
			// Critical errors mean we cannot rely on _ANY_ DB information being
			// available so we're going to dump out a simple echo'd statement
			//
			include($phpbb_root_path . 'language/lang_english/lang_main.'.$phpEx);

			if ( $msg_text == '' )
			{
				$msg_text = $lang['A_critical_error'];
			}

			if ( $msg_title == '' )
			{
				$msg_title = 'phpBB : <b>' . $lang['Critical_Error'] . '</b>';
			}
			break;
	}

	//
	// Add on DEBUG info if we've enabled debug mode and this is an error. This
	// prevents debug info being output for general messages should DEBUG be
	// set TRUE by accident (preventing confusion for the end user!)
	//
	if ( DEBUG && ( $msg_code == GENERAL_ERROR || $msg_code == CRITICAL_ERROR ) )
	{
		if ( $debug_text != '' )
		{
			$msg_text = $msg_text . '<br /><br /><b><u>DEBUG MODE</u></b>' . $debug_text;
		}
	}

	if ( $msg_code != CRITICAL_ERROR )
	{
		if ( !empty($lang[$msg_text]) )
		{
			$msg_text = $lang[$msg_text];
		}

		if ( !defined('IN_ADMIN') )
		{
			$template->set_filenames(array(
				'message_body' => 'message_body.tpl')
			);
		}
		else
		{
			$template->set_filenames(array(
				'message_body' => 'admin/admin_message_body.tpl')
			);
		}

		$template->assign_vars(array(
			'MESSAGE_TITLE' => $msg_title,
			'MESSAGE_TEXT' => $msg_text)
		);
		$template->pparse('message_body');

		if ( !defined('IN_ADMIN') )
		{
			include($phpbb_root_path . 'includes/page_tail.'.$phpEx);
		}
		else
		{
			include($phpbb_root_path . 'admin/page_footer_admin.'.$phpEx);
		}
	}
	else
	{
		echo "<html>\n<body>\n" . $msg_title . "\n<br /><br />\n" . $msg_text . "</body>\n</html>";
	}

	exit;
}

//
// This function is for compatibility with PHP 4.x's realpath()
// function.  In later versions of PHP, it needs to be called
// to do checks with some functions.  Older versions of PHP don't
// seem to need this, so we'll just return the original value.
// dougk_ff7 <October 5, 2002>
function phpbb_realpath($path)
{
	global $phpbb_root_path, $phpEx;

	return (!@function_exists('realpath') || !@realpath($phpbb_root_path . 'includes/functions.'.$phpEx)) ? $path : @realpath($path);
}

function redirect($url)
{
	global $db, $board_config;

	if (!empty($db))
	{
		$db->sql_close();
	}

	if (strstr(urldecode($url), "\n") || strstr(urldecode($url), "\r") || strstr(urldecode($url), ';url'))
	{
		message_die(GENERAL_ERROR, 'Tried to redirect to potentially insecure url.');
	}

	$server_protocol = ($board_config['cookie_secure']) ? 'https://' : 'http://';
	$server_name = preg_replace('#^\/?(.*?)\/?$#', '\1', trim($board_config['server_name']));
	$server_port = ($board_config['server_port'] <> 80) ? ':' . trim($board_config['server_port']) : '';
	$script_name = preg_replace('#^\/?(.*?)\/?$#', '\1', trim($board_config['script_path']));
	$script_name = ($script_name == '') ? $script_name : '/' . $script_name;
	$url = preg_replace('#^\/?(.*?)\/?$#', '/\1', trim($url));

	// Redirect via an HTML form for PITA webservers
	if (@preg_match('/Microsoft|WebSTAR|Xitami/', getenv('SERVER_SOFTWARE')))
	{
		header('Refresh: 0; URL=' . $server_protocol . $server_name . $server_port . $script_name . $url);
		echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"><html><head><meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1"><meta http-equiv="refresh" content="0; url=' . $server_protocol . $server_name . $server_port . $script_name . $url . '"><title>Redirect</title></head><body><div align="center">If your browser does not support meta redirection please click <a href="' . $server_protocol . $server_name . $server_port . $script_name . $url . '">HERE</a> to be redirected</div></body></html>';
		exit;
	}

	// Behave as per HTTP/1.1 spec for others
	header('Location: ' . $server_protocol . $server_name . $server_port . $script_name . $url);
	exit;
}

function phpbb_purify_html($html, $allowed_elements = null) {
    global $phpbb_root_path, $lang;

    require_once($phpbb_root_path. 'includes/htmlpurifier-4.19.1/library/HTMLPurifier.auto.php');

    static $purifiers = array();

    $encoding = $lang['ENCODING'];
    $cache_key = serialize(array($encoding, $allowed_elements));

    if (!isset($purifiers[$cache_key])) {
        $config = HTMLPurifier_Config::createDefault();

        $config->set('Core.Encoding', $encoding);
        $config->set('Cache.DefinitionImpl', null);

        if ($allowed_elements !== null) {
            $config->set('HTML.AllowedElements', $allowed_elements);
        }

        $purifiers[$cache_key] = new HTMLPurifier($config);
    }

	$html = preg_replace('#(<(?:ul|ol)\b[^>]*>)(?:\s|<br\s*/?>)+(?=<li\b)#i', '$1', $html);
    return $purifiers[$cache_key]->purify($html);
}

function phpbb_password_policy_error($password, $username) {
    global $lang;
    if (!is_string($password) || !is_string($username) || strpos($password, "\0") !== false) {
        return 'Datos de contrase&ntilde;a invalidos.';
    }
    $encoding = !empty($lang['ENCODING']) ? $lang['ENCODING'] : 'ISO-8859-1';
    $length = strlen($password);
    if (strcasecmp($encoding, 'UTF-8') === 0 || strcasecmp($encoding, 'UTF8') === 0) {
        $length = preg_match_all('/./us', $password, $characters);
        if ($length === false) { 
			return 'Datos de contrase&ntilde;a invalidos.'; 
		}
    }
    if ($length < 8) {
        return 'La contrase&ntilde;a debe tener al menos 8 caracteres.';
    }
    if (strlen($password) > 32) {
        return 'La contrase&ntilde;a supera la longitud permitida.';
    }
    $username = phpbb_decoded_text($username);
    if (function_exists('mb_strtolower')) {
        $matches_username = mb_strtolower($password, $encoding) === mb_strtolower($username, $encoding);
    } else {
        $matches_username = strcasecmp($password, $username) === 0;
        if (strcasecmp($encoding, 'ISO-8859-1') === 0) {
            $upper = "\xC0\xC1\xC2\xC3\xC4\xC5\xC6\xC7\xC8\xC9\xCA\xCB\xCC\xCD\xCE\xCF\xD0\xD1\xD2\xD3\xD4\xD5\xD6\xD8\xD9\xDA\xDB\xDC\xDD\xDE";
            $lower = "\xE0\xE1\xE2\xE3\xE4\xE5\xE6\xE7\xE8\xE9\xEA\xEB\xEC\xED\xEE\xEF\xF0\xF1\xF2\xF3\xF4\xF5\xF6\xF8\xF9\xFA\xFB\xFC\xFD\xFE";
            $matches_username = strtr(strtolower($password), $upper, $lower) === strtr(strtolower($username), $upper, $lower);
        }
    }
    return $matches_username ? 'La contrase&ntilde;a debe ser distinta del nombre de usuario.' : '';
}

function phpbb_password_hash($password) {
	if (!is_string($password) || $password === '' || strlen($password) > 72 || strpos($password, "\0") !== false) {
		message_die(GENERAL_MESSAGE, 'Contrase&ntilde;a invalida o demasiado larga.');
	}

	$hash = password_hash($password, PASSWORD_BCRYPT, array('cost' => 12));

	if ($hash === false) {
		message_die(GENERAL_ERROR, 'No se pudo proteger la contrase&ntilde;a.');
	}

	return $hash;
}

function phpbb_password_verify($password, $stored_hash) {
	if (!is_string($password) || !is_string($stored_hash) || strpos($password, "\0") !== false) {
		return false;
	}

	if (preg_match('/\A[a-f0-9]{32}\z/i', $stored_hash)) {
		return hash_equals(strtolower($stored_hash), md5($password));
	}

	if (strlen($password) > 72) {
		return false;
	}

	return password_verify($password, $stored_hash);
}

function phpbb_html($value, $double_encode = true) {
    global $lang;
    $encoding = !empty($lang['ENCODING']) ? $lang['ENCODING'] : 'ISO-8859-1';
    if ($value === null) { 
		return ''; 
	}
    if (!is_scalar($value)) { 
		return ''; 
	}
    return htmlspecialchars((string) $value, ENT_QUOTES, $encoding, $double_encode);
}

function phpbb_decoded_text($value) {
    global $lang;
    $encoding = !empty($lang['ENCODING']) ? $lang['ENCODING'] : 'ISO-8859-1';
    return is_scalar($value) ? html_entity_decode((string) $value, ENT_QUOTES, $encoding) : '';
}

function phpbb_http_url($value) {
    $value = trim(phpbb_decoded_text($value));
    if ($value === '' || preg_match('/[\x00-\x20\x7f<>"\\\\]/', $value)) { 
		return ''; 
	}
    $parts = @parse_url($value);
    if (!$parts || empty($parts['host']) || empty($parts['scheme']) || !in_array(strtolower($parts['scheme']), array('http', 'https'), true)) { 
		return ''; 
	}
    return $value;
}

function phpbb_asset_url($value) {
    $value = trim(phpbb_decoded_text($value));
    if ($value === '') { 
		return ''; 
	}e
    if (preg_match('#^https?://#i', $value)) { 
		return phpbb_http_url($value); 
	}
    if (substr($value, 0, 2) === '//' || strpos($value, ':') !== false || preg_match('/[\x00-\x1f\x7f<>"\\\\]/', $value)) { 
		return ''; 
	}
    return $value;
}

function phpbb_url_parameter($value) {
    return rawurlencode(phpbb_decoded_text($value));
}

function phpbb_require_scalar_fields($values, $fields) {
    foreach ($fields as $field) {
        if (isset($values[$field]) && !is_string($values[$field])) {
            message_die(GENERAL_MESSAGE, 'Datos de formulario invalidos.');
        }
    }
}

function phpbb_check_length($value, $maximum) {
    if (!is_string($value) || strlen($value) > $maximum) {
        message_die(GENERAL_MESSAGE, 'Un campo supera la longitud permitida.');
    }
}

function phpbb_id_list($values) {
    if (!is_array($values)) { message_die(GENERAL_MESSAGE, 'Lista de identificadores invalida.'); }
    $ids = array();
    foreach ($values as $value) {
        if ((!is_string($value) && !is_int($value)) || !preg_match('/\A(?:-1|[1-9][0-9]{0,9})\z/', (string) $value) || (float) $value > 2147483647) { 
			message_die(GENERAL_MESSAGE, 'Identificador invalido.'); 
		}
        $ids[] = (int) $value;
    }
    return $ids;
}

function phpbb_permission_map($values) {
    if (!is_array($values)) { message_die(GENERAL_MESSAGE, 'Permisos invalidos.'); }
    $result = array();
    foreach ($values as $id => $value) {
        if (!preg_match('/\A[1-9][0-9]{0,9}\z/', (string) $id) || (float) $id > 2147483647 || !in_array($value, array('0', '1', 0, 1), true)) {
            message_die(GENERAL_MESSAGE, 'Permisos invalidos.');
        }
        $result[(int) $id] = (int) $value;
    }
    return $result;
}

function phpbb_message_html($message, $html_enabled) {
    global $board_config;
    if (!$html_enabled) { return phpbb_html($message, false); }
    $allowed = preg_split('/\s*,\s*/', strtolower(trim($board_config['allow_html_tags'])), -1, PREG_SPLIT_NO_EMPTY);
    return phpbb_purify_html($message, $allowed);
}

function phpbb_style_fields() {
    return array('template_name', 'style_name', 'head_stylesheet', 'body_background',
        'body_bgcolor', 'body_text', 'body_link', 'body_vlink', 'body_alink', 'body_hlink',
        'tr_color1', 'tr_color2', 'tr_color3', 'tr_class1', 'tr_class2', 'tr_class3',
        'th_color1', 'th_color2', 'th_color3', 'th_class1', 'th_class2', 'th_class3',
        'td_color1', 'td_color2', 'td_color3', 'td_class1', 'td_class2', 'td_class3',
        'fontface1', 'fontface2', 'fontface3', 'fontsize1', 'fontsize2', 'fontsize3',
        'fontcolor1', 'fontcolor2', 'fontcolor3', 'span_class1', 'span_class2', 'span_class3',
        'img_size_poll', 'img_size_privmsg');
}

function phpbb_template_name($name)
{
    global $phpbb_root_path;
    if (!is_string($name) || !preg_match('/\A[a-zA-Z_][a-zA-Z0-9_]*\z/', $name) || !is_dir($phpbb_root_path . 'templates/' . $name)) {
        message_die(GENERAL_MESSAGE, 'Plantilla invalida.');
    }
    return $name;
}

function phpbb_style_value($field, $value, $strict = true) {
    $valid = is_string($value) || is_int($value);
    $value = $valid ? (string) $value : '';
    if ($field === 'template_name') {
        if (!$strict && (!preg_match('/\A[a-zA-Z_][a-zA-Z0-9_]*\z/', $value) || !is_dir($GLOBALS['phpbb_root_path'] . 'templates/' . $value))) { 
			return 'subSilver';
		}
        return phpbb_template_name($value);
    }
    if ($field === 'style_name') { 
		$valid = $valid && strlen($value) <= 30; 
	} elseif (preg_match('/\A(?:fontsize[123]|img_size_(?:poll|privmsg))\z/', $field)) {
        $valid = $valid && preg_match('/\A[0-9]{1,4}\z/', $value);
        if ($valid) { 
			return (int) $value; 
		}
    } elseif (preg_match('/\A(?:body_(?:bgcolor|text|link|vlink|alink|hlink)|[t][rhd]_color[123]|fontcolor[123])\z/', $field)) {
        $value = ltrim($value, '#');
        $valid = $valid && ($value === '' || preg_match('/\A(?:[a-fA-F0-9]{3}|[a-fA-F0-9]{6})\z/', $value));
    } elseif (preg_match('/\Afontface[123]\z/', $field)) {
        $value = str_replace(array("\\'", '\\"', "'", '"'), '', $value);
        $valid = $valid && strlen($value) <= 100 && preg_match('/\A[a-zA-Z0-9_, -]*\z/', $value);
    } else {

        $valid = $valid && strlen($value) <= 255 && preg_match('/\A[a-zA-Z0-9_.\/%+\/-]*\z/', $value) && !preg_match('#(?:\A|/)\.\.(?:/|\z)#', $value) && substr($value, 0, 2) !== '//';
    }
    if (!$valid) {
        if ($strict) { 
			message_die(GENERAL_MESSAGE, 'Valor de estilo invalido.'); 
		}
        return preg_match('/\A(?:fontsize|img_size)/', $field) ? 0 : '';
    }
    return $value;
}

function phpbb_style_row($row, $strict = true) {
    foreach (phpbb_style_fields() as $field) {
        if (isset($row[$field])) { 
			$row[$field] = phpbb_style_value($field, $row[$field], $strict); 
		}
    }
    return $row;
}

?>