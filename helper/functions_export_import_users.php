<?php
/**
*
* @package Export Import users
* @copyright (c) 2014 ForumHulp.com
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

if (!defined('IN_PHPBB'))
{
	exit;
}

function export_import_users_update($aa)
{
	foreach ($aa as $k => $v)
	{
		$this->$k = $aa[$k];
	}
}

function readDatabase($filename, $time = false)
{
	$tdb = array();
	// read the XML database of asaf_updates
	$data = implode('', file($filename));
	$parser = xml_parser_create();
	xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
	xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
	xml_parse_into_struct($parser, $data, $values, $tags);
	xml_parser_free($parser);

	// loop through the structures
	foreach ($tags as $key => $val)
	{
		if ($key == "user")
		{
			for ($i = 0; $i < count($val); $i+= 2)
			{
				$offset = $val[$i] + 1;
				$len = $val[$i + 1] - $offset;
				$ik = parse_user(array_slice($values, $offset, $len));
				($time) ? $ik['time'] = $time : null;
				$tdb[] = $ik;
			}
		} else
		{
			continue;
		}
	}
	return $tdb;
}

function parse_user($mvalues)
{
	for ($i = 0; $i < count($mvalues); $i++)
	{
		$user_values[$mvalues[$i]['tag']] = (isset($mvalues[$i]['value'])) ? $mvalues[$i]['value'] : '';
	}
	return $user_values;
}

function history($dir)
{
	$history = array();
	$files = array_diff(scandir($dir), array('..', '.'));

	foreach ($files as $file)
	{
		if (file_exists($dir . '/' . $file) && strpos($file, '.xml') !== false)
		{
			$time = strtotime(substr(str_replace(array('user_update ', '.xml'), array('', ''), $file), 0, -9));
			$his = readDatabase($dir . '/' . $file, $time);
			$history = array_merge($history, $his);
		}
	}

	$history = (sizeof($history)) ? array_orderby($history, 'time', SORT_DESC, 'username', SORT_ASC) : array();
	return $history;
}

function array_orderby()
{
	$args = func_get_args();
	$data = array_shift($args);
	foreach ($args as $n => $field)
	{
		if (is_string($field))
		{
			$tmp = array();
			foreach ($data as $key => $row)
			{
				$tmp[$key] = $row[$field];
			}
			$args[$n] = $tmp;
		}
	}
	$args[] = &$data;
	call_user_func_array('array_multisort', $args);
	return array_pop($args);
}

function validate_import($importname = '', $newpassword = '', $newemail = '', $checkdbname = false)
{
	global $db, $config, $user, $phpbb_root_path, $phpEx;
	if (!function_exists('validate_string'))
	{
		include_once($phpbb_root_path . 'includes/functions_user.' . $phpEx);
	}

	$user->add_lang(array('ucp'));
	$error = array();
	$err = validate_string($importname, false, $config['min_name_chars'], $config['max_name_chars']);
	($err) ? $error[] = $user->lang[$err] : null;
	$err = validate_username($importname, 'allowed name');
	($checkdbname && $err == 'USERNAME_TAKEN') ? $error[] = $user->lang[$err . '_USERNAME'] : null;
	($err && $err != 'USERNAME_TAKEN') ? $error[] = $user->lang[$err . '_USERNAME'] : null;
	$err = filter_var($newemail, FILTER_VALIDATE_EMAIL);
	(!$err) ? $error[] = $user->lang['NO_EMAILS_DEFINED'] : null;
	if (strlen($newpassword) == 60 || strlen($newpassword) == 34)
	{
		if ((strlen($newpassword) == 34 && (substr($newpassword, 0,3) == '$H$' || substr($newpassword, 0,3) == '$P$')) || (strlen($newpassword) == 60 && (substr($newpassword, 0,3) == '$2y')))
		{
			$err = false;
		}
	} else
	{
		$err = $user->lang['WRONG_PASSWORD'];
	}
	($err) ? $error[] = $err : null;
	return $error;
}
