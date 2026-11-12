<?php
/***************************************************************************
 *                           usercp_sendpasswd.php
 *                            -------------------
 *   begin                : Saturday, Feb 13, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: usercp_sendpasswd.php 5204 2005-09-14 18:14:30Z acydburn $
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

if ( !defined('IN_PHPBB') )
{
	die('Hacking attempt');
	exit;
}

if ( isset($HTTP_POST_VARS['submit']) ) {
	// Comienzo SQL-06.
	$username_input = isset($HTTP_POST_VARS['username']) ? $HTTP_POST_VARS['username'] : '';

	$email_input = isset($HTTP_POST_VARS['email']) ? $HTTP_POST_VARS['email'] : '';

	if (!is_string($username_input) || !is_string($email_input)) {
		message_die(GENERAL_MESSAGE, $lang['No_email_match']);
	}

	if (strlen($username_input) > 40 || strlen($email_input) > 255) {
		message_die(GENERAL_MESSAGE, $lang['No_email_match']);
	}

	$username = phpbb_clean_username($username_input);
	$email_input = trim($email_input);

	if ($username === '' || filter_var($email_input, FILTER_VALIDATE_EMAIL) === false) {
		message_die(GENERAL_MESSAGE, $lang['No_email_match']);
	}

	$email = htmlspecialchars($email_input);

	$sql_username = $db->sql_escape_string($username);
	$sql_email = $db->sql_escape_string($email);

	$sql = "SELECT user_id, username, user_email, user_active, user_lang
		FROM " . USERS_TABLE . "
		WHERE user_email = '" . $sql_email . "'
			AND username = '" . $sql_username . "'";		
	// Fin SQL-06

	if ( $result = $db->sql_query($sql) ) {
		if ( $row = $db->sql_fetchrow($result) ) {
			if ( !$row['user_active'] ) {
				message_die(GENERAL_MESSAGE, $lang['No_send_account_inactive']);
			}

			$username = $row['username'];
			$user_id = $row['user_id'];

			$user_actkey = gen_rand_string(true);
			$key_len = 54 - strlen($server_url);
			$key_len = ($key_len > 6) ? $key_len : 6;
			$user_actkey = substr($user_actkey, 0, $key_len);
			$user_password = gen_rand_string(false);
			
			$sql_password_hash = $db->sql_escape_string(phpbb_password_hash($user_password));

			$sql_activation_key = $db->sql_escape_string($user_actkey);

			$sql = "UPDATE " . USERS_TABLE . "
				SET user_newpasswd = '" . $sql_password_hash . "',
					user_actkey = '" . $sql_activation_key . "'
				WHERE user_id = " . intval($row['user_id']);

			if (!$db->sql_query($sql)) {
				error_log('Password recovery update failed');
				message_die(GENERAL_ERROR, 'No se pudo procesar la recuperación de contraseña.');
			}

			include($phpbb_root_path . 'includes/emailer.'.$phpEx);
			$emailer = new emailer($board_config['smtp_delivery']);

			$emailer->from($board_config['board_email']);
			$emailer->replyto($board_config['board_email']);

			$emailer->use_template('user_activate_passwd', $row['user_lang']);
			$emailer->email_address($row['user_email']);
			$emailer->set_subject($lang['New_password_activation']);

			$emailer->assign_vars(array(
				'SITENAME' => $board_config['sitename'], 
				'USERNAME' => $username,
				'PASSWORD' => $user_password,
				'EMAIL_SIG' => (!empty($board_config['board_email_sig'])) ? str_replace('<br />', "\n", "-- \n" . $board_config['board_email_sig']) : '', 

				'U_ACTIVATE' => $server_url . '?mode=activate&' . POST_USERS_URL . '=' . $user_id . '&act_key=' . $user_actkey)
			);
			$emailer->send();
			$emailer->reset();

			$template->assign_vars(array('META' => '<meta http-equiv="refresh" content="15;url=' . append_sid("index.$phpEx") . '">'));

			$message = $lang['Password_updated'] . '<br /><br />' . sprintf($lang['Click_return_index'],  '<a href="' . append_sid("index.$phpEx") . '">', '</a>');

			message_die(GENERAL_MESSAGE, $message);
		} else {
			message_die(GENERAL_MESSAGE, $lang['No_email_match']);
		}
	} else {
		error_log('Password recovery lookup failed');
		message_die(GENERAL_ERROR, 'No se pudo procesar la recuperación de contraseña.');
	}
} else {
	$username = '';
	$email = '';
}

//
// Output basic page
//
include($phpbb_root_path . 'includes/page_header.'.$phpEx);

$template->set_filenames(array(
	'body' => 'profile_send_pass.tpl')
);
make_jumpbox('viewforum.'.$phpEx);

$template->assign_vars(array(
	'USERNAME' => $username,
	'EMAIL' => $email,

	'L_SEND_PASSWORD' => $lang['Send_password'], 
	'L_ITEMS_REQUIRED' => $lang['Items_required'],
	'L_EMAIL_ADDRESS' => $lang['Email_address'],
	'L_SUBMIT' => $lang['Submit'],
	'L_RESET' => $lang['Reset'],
	
	'S_HIDDEN_FIELDS' => '', 
	'S_PROFILE_ACTION' => append_sid("profile.$phpEx?mode=sendpassword"))
);

$template->pparse('body');

include($phpbb_root_path . 'includes/page_tail.'.$phpEx);

?>