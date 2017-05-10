<?php

require 'inc/lib/IP/Lifo/IP/IP.php';
require 'inc/lib/IP/Lifo/IP/BC.php';
require 'inc/lib/IP/Lifo/IP/CIDR.php';

use Lifo\IP\CIDR;

class Bans {
	static public function range_to_string($mask) {
		global $config;

		if($config['obscure_ip_addresses'])
			return $mask[0];

		list($ipstart, $ipend) = $mask;
		
		if (!isset($ipend) || $ipend === false) {
			// Not a range. Single IP address.
			$ipstr = inet_ntop($ipstart);
			return $ipstr;
		}
		
		if (strlen($ipstart) != strlen($ipend))
			return '???'; // What the fuck are you doing, son?
		
		$range = CIDR::range_to_cidr(inet_ntop($ipstart), inet_ntop($ipend));
		if ($range !== false)
			return $range;
		
		return '???';
	}
	
	private static function calc_cidr($mask) {
		$cidr = new CIDR($mask);
		$range = $cidr->getRange();
		
		return array(inet_pton($range[0]), inet_pton($range[1]));
	}
	
	public static function parse_time($str) {
		if (empty($str))
			return false;
	
		if (($time = @strtotime($str)) !== false)
			return $time;
	
		if (!preg_match('/^((\d+)\s?ye?a?r?s?)?\s?+((\d+)\s?mon?t?h?s?)?\s?+((\d+)\s?we?e?k?s?)?\s?+((\d+)\s?da?y?s?)?((\d+)\s?ho?u?r?s?)?\s?+((\d+)\s?mi?n?u?t?e?s?)?\s?+((\d+)\s?se?c?o?n?d?s?)?$/', $str, $matches))
			return false;
	
		$expire = 0;
	
		if (isset($matches[2])) {
			// Years
			$expire += $matches[2]*60*60*24*365;
		}
		if (isset($matches[4])) {
			// Months
			$expire += $matches[4]*60*60*24*30;
		}
		if (isset($matches[6])) {
			// Weeks
			$expire += $matches[6]*60*60*24*7;
		}
		if (isset($matches[8])) {
			// Days
			$expire += $matches[8]*60*60*24;
		}
		if (isset($matches[10])) {
			// Hours
			$expire += $matches[10]*60*60;
		}
		if (isset($matches[12])) {
			// Minutes
			$expire += $matches[12]*60;
		}
		if (isset($matches[14])) {
			// Seconds
			$expire += $matches[14];
		}
	
		return time() + $expire;
	}
	
	static public function parse_range($mask) {
		global $config;

		if($config['obscure_ip_addresses'])
			return array($mask, false);

		$ipstart = false;
		$ipend = false;
		
		if (preg_match('@^(\d{1,3}\.){1,3}([\d*]{1,3})?$@', $mask) && substr_count($mask, '*') == 1) {
			// IPv4 wildcard mask
			$parts = explode('.', $mask);
			$ipv4 = '';
			foreach ($parts as $part) {
				if ($part == '*') {
					$ipstart = inet_pton($ipv4 . '0' . str_repeat('.0', 3 - substr_count($ipv4, '.')));
					$ipend = inet_pton($ipv4 . '255' . str_repeat('.255', 3 - substr_count($ipv4, '.')));
					break;
				} elseif(($wc = strpos($part, '*')) !== false) {
					$ipstart = inet_pton($ipv4 . substr($part, 0, $wc) . '0' . str_repeat('.0', 3 - substr_count($ipv4, '.')));
					$ipend = inet_pton($ipv4 . substr($part, 0, $wc) . '9' . str_repeat('.255', 3 - substr_count($ipv4, '.')));
					break;
				}
				$ipv4 .= "$part.";
			}
		} elseif (preg_match('@^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/\d+$@', $mask)) {
			list($ipv4, $bits) = explode('/', $mask);
			if ($bits > 32)
				return false;
			
			list($ipstart, $ipend) = self::calc_cidr($mask);
		} elseif (preg_match('@^[:a-z\d]+/\d+$@i', $mask)) {
			list($ipv6, $bits) = explode('/', $mask);
			if ($bits > 128)
				return false;
			
			list($ipstart, $ipend) = self::calc_cidr($mask);
		} else {
			if (($ipstart = @inet_pton($mask)) === false)
				return false;
		}
		
		return array($ipstart, $ipend);
	}
	
	static public function find($ip, $board = false, $get_mod_info = false) {
		global $config;
		
		$query = prepare('SELECT ``bans``.*' . ($get_mod_info ? ', `username`' : '') . ' FROM ``bans``
			' . ($get_mod_info ? 'LEFT JOIN ``mods`` ON ``mods``.`id` = `creator`' : '') . '
			WHERE
			(' . ($board !== false ? '(`board` IS NULL OR `board` = :board) AND' : '') . '
			' . ($config['obscure_ip_addresses'] ? '(`ipstart` = MD5(AES_ENCRYPT(:ip, UNHEX(SHA2(:aeskey, 512))))))' : '(`ipstart` = :ip OR (:ip >= `ipstart` AND :ip <= `ipend`)))') . ' 
			ORDER BY `expires` IS NULL, `expires` DESC');
		
		if ($board !== false)
			$query->bindValue(':board', $board, PDO::PARAM_STR);
		
		$query->bindValue(':ip', $config['obscure_ip_addresses'] ? $ip : inet_pton($ip));
		if($config['obscure_ip_addresses'])
			$query->bindValue(':aeskey', $config['db']['ip_encrypt_key']);
		$query->execute() or error(db_error($query));
		
		$ban_list = array();
		
		while ($ban = $query->fetch(PDO::FETCH_ASSOC)) {
			if ($ban['expires'] && ($ban['seen'] || !$config['require_ban_view']) && $ban['expires'] < time()) {
				self::delete($ban['id']);
			} else {
				if ($ban['post'])
					$ban['post'] = json_decode($ban['post'], true);
				$ban['mask'] = self::range_to_string(array($ban['ipstart'], $ban['ipend']));
				$ban_list[] = $ban;
			}
		}
		
		return $ban_list;
	}



	static public function findWarning($ip, $get_mod_info = false) {
		global $config;
		
		$query = prepare('SELECT ``warnings``.*' . ($get_mod_info ? ', `username`' : '') . ' FROM ``warnings``
			' . ($get_mod_info ? 'LEFT JOIN ``mods`` ON ``mods``.`id` = `creator`' : '') . '
			WHERE `ip` = ' . ($config['obscure_ip_addresses'] ? 'MD5(AES_ENCRYPT(:ip, UNHEX(SHA2(:aeskey, 512))))' : ':ip'));
					
		$query->bindValue(':ip', $config['obscure_ip_addresses'] ? $ip : inet_pton($ip));
		if($config['obscure_ip_addresses'])
			$query->bindValue(':aeskey', $config['db']['ip_encrypt_key']);
		$query->execute() or error(db_error($query));
		
		$warning_list = array();
		
		while ($warning = $query->fetch(PDO::FETCH_ASSOC)) {
			if ($warning['seen']) {
				// self::deleteWarning($warning['id']);
			} else {
				if ($warning['post'])
					$warning['post'] = json_decode($warning['post'], true);
				$warning_list[] = $warning;
			}
		}
		
		return $warning_list;
	}




	// Check if cookie is banned
	static public function findCookie($uuser_cookie)
	{
		global $config;

		$query = prepare('SELECT ``id``, ``expires`` FROM ``bans_cookie`` WHERE ``cookie`` = :cookie LIMIT 1');
		$query->bindValue(':cookie', get_uuser_cookie($uuser_cookie));
		$query->execute() or error(db_error($query));
		
		// If we find a result we return true
		if ($post = $query->fetch(PDO::FETCH_ASSOC)){
			// Check if ban has expired
			if($post['expires'] < time())
			{
				$query = prepare('DELETE FROM ``bans_cookie`` WHERE ``id`` = ' . (int)$post['id']);
				$query->execute() or error(db_error($query));
			} else {
				return true;
			}
		}
		// Return false if nothing was found
		return false;
	}



	static public function stream_json($out = false, $filter_ips = false, $filter_staff = false, $board_access = false) {
		$query = query("SELECT ``bans``.*, `username` FROM ``bans``
			LEFT JOIN ``mods`` ON ``mods``.`id` = `creator`
 			ORDER BY `created` DESC") or error(db_error());
                $bans = $query->fetchAll(PDO::FETCH_ASSOC);

		if ($board_access && $board_access[0] == '*') $board_access = false;

		$out ? fputs($out, "[") : print("[");

		$end = end($bans);

                foreach ($bans as &$ban) {
                        $ban['mask'] = self::range_to_string(array($ban['ipstart'], $ban['ipend']));

			if ($ban['post']) {
				$post = json_decode($ban['post']);
				$ban['message'] = isset($post->body) ? $post->body : 0;
			}
			unset($ban['ipstart'], $ban['ipend'], $ban['post'], $ban['creator']);

			if ($board_access === false || in_array ($ban['board'], $board_access)) {
				$ban['access'] = true;
			}

			if (filter_var($ban['mask'], FILTER_VALIDATE_IP) !== false) {
				$ban['single_addr'] = true;
			}
			if ($filter_staff || ($board_access !== false && !in_array($ban['board'], $board_access))) {
				$ban['username'] = '?';				
			}
			if ($filter_ips || ($board_access !== false && !in_array($ban['board'], $board_access))) {
				@list($ban['mask'], $subnet) = explode("/", $ban['mask']);
				$ban['mask'] = preg_split("/[\.:]/", $ban['mask']);
				$ban['mask'] = array_slice($ban['mask'], 0, 2);
				$ban['mask'] = implode(".", $ban['mask']);
				$ban['mask'] .= ".x.x";
				if (isset ($subnet)) {
					$ban['mask'] .= "/$subnet";
				}
				$ban['masked'] = true;
			}

			$json = json_encode($ban);
			$out ? fputs($out, $json) : print($json);

			if ($ban['id'] != $end['id']) {
				$out ? fputs($out, ",") : print(",");
			}
		}

                $out ? fputs($out, "]") : print("]");

	}
	


	static public function seenWarning($warning_id) {
		$query = query("UPDATE ``warnings`` SET `seen` = 1 WHERE `id` = " . (int)$warning_id) or error(db_error());
	}
	


	
	static public function seen($ban_id) {
		$query = query("UPDATE ``bans`` SET `seen` = 1 WHERE `id` = " . (int)$ban_id) or error(db_error());
                rebuildThemes('bans');
	}
	
	static public function purge() {
		$query = query("DELETE FROM ``bans_cookie`` WHERE `expires` IS NOT NULL AND `expires` < " . time()) or error(db_error());
		$query = query("DELETE FROM ``bans`` WHERE `expires` IS NOT NULL AND `expires` < " . time() . " AND `seen` = 1") or error(db_error());
		rebuildThemes('bans');
	}
	
	static public function delete($ban_id, $modlog = false, $boards = false, $dont_rebuild = false) {
		global $config;

		if ($boards && $boards[0] == '*') $boards = false;

		if ($modlog) {
			$query = query("SELECT `ipstart`, `ipend`, `board` FROM ``bans`` WHERE `id` = " . (int)$ban_id) or error(db_error());
			if (!$ban = $query->fetch(PDO::FETCH_ASSOC)) {
				// Ban doesn't exist
				return false;
			}

			if ($boards !== false && !in_array($ban['board'], $boards))
		                error($config['error']['noaccess']);
			
			$mask = self::range_to_string(array($ban['ipstart'], $ban['ipend']));
			
			modLog("Removed ban #{$ban_id} for " .
				(filter_var($mask, FILTER_VALIDATE_IP) !== false ? "<a href=\"?/IP/$mask\">$mask</a>" : $mask));
		}
		
		// Remove cookie ban if cunique user cookie is banned
		$query = query("SELECT `cookie` FROM ``bans`` WHERE `cookiebanned` = 1 AND `id` = " . (int)$ban_id) or error(db_error());
		if($uuser_cookie = $query->fetchColumn())
		{
			$query = prepare("DELETE FROM ``bans_cookie`` WHERE `cookie` = :cookie");
			$query->bindValue(':cookie', $uuser_cookie, PDO::PARAM_STR);
			$query->execute() or error(db_error($query));
		}
		
		query("DELETE FROM ``bans`` WHERE `id` = " . (int)$ban_id) or error(db_error());

		if (!$dont_rebuild) rebuildThemes('bans');
		
		return true;
	}




	
	static public function new_warning($mask, $reason, $warning_board = false, $mod_id = false, $post = false) {
		global $mod, $pdo, $board, $config;
		
		if ($mod_id === false) {
			$mod_id = isset($mod['id']) ? $mod['id'] : -1;
		}
		
		$range = self::parse_range($mask);
		$mask = self::range_to_string($range);
		
		$query = prepare("INSERT INTO ``warnings`` VALUES (NULL, :ip, :time, :board, :mod, :reason, 0, :post)");
		
		$query->bindValue(':ip', $range[0]);
		$query->bindValue(':mod', $mod_id);
		$query->bindValue(':time', time());
		
		if ($warning_board)
			$query->bindValue(':board', $warning_board);
		else
			$query->bindValue(':board', null, PDO::PARAM_NULL);
		
		if ($reason !== '') {
			$reason = escape_markup_modifiers($reason);
			markup($reason);
			$query->bindValue(':reason', $reason);
		} else
			$query->bindValue(':reason', null, PDO::PARAM_NULL);

		if ($post) {
			$post['board'] = $board['uri'];
			$query->bindValue(':post', json_encode($post));
		} else
			$query->bindValue(':post', null, PDO::PARAM_NULL);
		
		$query->execute() or error(db_error($query));
		
		if (isset($mod['id']) && $mod['id'] == $mod_id) {
			modLog('Issued a new warning for ' .
				(filter_var($mask, FILTER_VALIDATE_IP) !== false ? "<a href=\"?/IP/$mask\">$mask</a>" : $mask) .
				' (<small>#' . $pdo->lastInsertId() . '</small>)' .
				' with ' . ($reason ? 'reason: ' . utf8tohtml($reason) . '' : 'no reason'));
		}

		return $pdo->lastInsertId();
	}





	
	static public function new_ban($mask, $uuser_cookie, $reason, $length = false, $ban_board = false, $mod_id = false, $post = false) {
		global $mod, $pdo, $board, $config;
		
		if ($mod_id === false) {
			$mod_id = isset($mod['id']) ? $mod['id'] : -1;
		}
		
		$range = self::parse_range($mask);
		$mask = self::range_to_string($range);
		
		$query = prepare("INSERT INTO ``bans`` VALUES (NULL, :ipstart, :ipend, :cookie, 0, :time, :expires, :board, :mod, :reason, 0, :post)");
		
		$query->bindValue(':ipstart', $range[0]);
		if ($range[1] !== false && $range[1] != $range[0])
			$query->bindValue(':ipend', $range[1]);
		else
			$query->bindValue(':ipend', null, PDO::PARAM_NULL);

		// Add and sanitize (just in case) cookie
		$query->bindValue(':cookie', get_uuser_cookie($uuser_cookie));
		
		$query->bindValue(':mod', $mod_id);
		$query->bindValue(':time', time());
		
		if ($reason !== '') {
			$reason = escape_markup_modifiers($reason);
			markup($reason);
			$query->bindValue(':reason', $reason);
		} else
			$query->bindValue(':reason', null, PDO::PARAM_NULL);
		
		if ($length) {
			if (is_int($length) || ctype_digit($length)) {
				$length = time() + $length;
			} else {
				$length = self::parse_time($length);
			}
			$query->bindValue(':expires', $length);
		} else {
			$query->bindValue(':expires', null, PDO::PARAM_NULL);
		}
		
		if ($ban_board)
			$query->bindValue(':board', $ban_board);
		else
			$query->bindValue(':board', null, PDO::PARAM_NULL);
		
		if ($post) {
			$post['board'] = $board['uri'];
			$query->bindValue(':post', json_encode($post));
		} else
			$query->bindValue(':post', null, PDO::PARAM_NULL);
		
		$query->execute() or error(db_error($query));
		
		if (isset($mod['id']) && $mod['id'] == $mod_id) {
			modLog('Created a new ' .
				($length > 0 ? preg_replace('/^(\d+) (\w+?)s?$/', '$1-$2', until($length)) : 'permanent') .
				' ban on ' .
				($ban_board ? '/' . $ban_board . '/' : 'all boards') .
				' for ' .
				(filter_var($mask, FILTER_VALIDATE_IP) !== false ? "<a href=\"?/IP/$mask\">$mask</a>" : $mask) .
				' (<small>#' . $pdo->lastInsertId() . '</small>)' .
				' with ' . ($reason ? 'reason: ' . utf8tohtml($reason) . '' : 'no reason'));
		}

		rebuildThemes('bans');

		return $pdo->lastInsertId();
	}



	// Ban Unique User Cookie from being able to post
	static public function ban_cookie($ban_id, $mod_id = false) {
		global $mod, $config;
		
		if ($mod_id === false) {
			$mod_id = isset($mod['id']) ? $mod['id'] : -1;
		}
		
		// Get cookie for spesific ban
		$queryGet = prepare('SELECT ``cookie`` FROM ``bans`` WHERE ``id`` = ' . (int)$ban_id);
		$queryGet->execute() or error(db_error($query));

		// If we find a result we return true
		if ($post = $queryGet->fetch(PDO::FETCH_ASSOC)){
			// Add cookie to ban
			$query = prepare('INSERT INTO ``bans_cookie`` VALUES (NULL, :cookie, :expires, :mod)');
			
			$uuser_cookie = get_uuser_cookie($post['cookie']);
			$query->bindValue(':cookie', $uuser_cookie, PDO::PARAM_STR);
			$query->bindValue(':mod', $mod_id);

			$length = time() + $config['uuser_cookie_ban_lifetime'];
			$query->bindValue(':expires', $length);

			$query->execute() or error(db_error($query));

			// Mark Cookies as banned in ban list
			$query = prepare("UPDATE ``bans`` SET `cookiebanned` = 1 WHERE `cookie` = :cookie");
			$query->bindValue(':cookie', $uuser_cookie, PDO::PARAM_STR);
			$query->execute() or error(db_error($query));
		}

		return true;
	}


}
