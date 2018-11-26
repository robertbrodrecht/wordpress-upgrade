<?php

function cli_get_arg($arg = false) {
	$cli_options_short = '?';
	$cli_options_long = array(
		'auto' => 'Use a 30 second count down instead of manual confirmations.',
		'dry-run' => 'Do everything except execute backup and upgrade.',
		'clean' => 'Delete any WordPress temp files and download a new copy.',
		'help' => 'Show this message.',
		'list' => 'Short cut for no download, no backup, no upgrade.',
		'no-download' => 'Disable download of new WordPress.',
		'no-backup' => 'Do not perform backups of sites.',
		'no-backup-files' => 'Do not create gzips of sites. Database only.',
		'no-upgrade' => 'Do not perform WordPress upgrade.',
		'force' => 'Force all sites, ignoring config exclusions.',
		'package:' => 'Specify the full URL to a WordPress gzip.',
		'single:' => 'Specify a path to a single WordPress install to upgrade.',
		'version:' => 'Override lates version number. For example: 4.9.4',
	);
	
	$cli_options_values = getopt(
			$cli_options_short,
			array_keys($cli_options_long)
		);
	
	if(isset($cli_options_values['list'])) {
		$cli_options_values['no-backup'] = false;
		$cli_options_values['no-download'] = false;
		$cli_options_values['no-upgrade'] = false;
	}
	
	while(substr($arg, 0, 1) === '-') {
		$arg = substr($arg, 1);
	}
	
	if(isset($cli_options_values['?']) || isset($cli_options_values['help'])) {
		echo "Bulk WordPress Upgrades\n\n";
		echo wordwrap(
			"Backup and upgrade a folder full of WordPress installs with " . 
			"one script. This is for people managing a bunch of sites on " .
			"a server where the web user can't write.\n\n",
			80
		);
		
		echo wordwrap(
			"A config file named config.json can be placed in the same " .
			"folder as this script to provide some basic details, but " .
			"the script will detect some defaults. Please check README.md.\n\n",
			80
		);
		
		echo "\nUsage:\n\n";
		$longest = 0;
		foreach($cli_options_long as $argument => $description) {
			$argument = str_replace(':', ' {value}', $argument);
			if(strlen($argument) > $longest) {
				$longest = strlen($argument);
			}
		}
		foreach($cli_options_long as $argument => $description) {
			$argument = str_replace(':', ' {value}', $argument);
			$argument = str_pad($argument, $longest, ' ');
			echo "--{$argument}\t{$description}\n";
		}
		echo "\n\n";
		exit();
	}
	
	if($arg) {
		if(isset($cli_options_values[$arg])) {
			if($cli_options_values[$arg] === false) {
				return true;
			} else {
				return $cli_options_values[$arg];
			}
		}
		
		return false;
	} else {
		return $cli_options_values;
	}
}


function site_size($path = false, $du = false) {
	if(!$path) {
		return 'Unknown Size';
	}
	
	if(!$du) {
		$du = shell_which('du');
	}
	
	$du_command = $du . ' -ksh ' . escapeshellarg($path) . ' 2> /dev/null';
	$du_output = exec($du_command);
	$du_output_parts = explode('	', $du_output);
	$directory_size = $du_output_parts[0];
	
	return trim($directory_size);
}


function wp_database($site = false) {
	if(!$site) {
		return -1;
	}
	
	$wp_conifg = $site . 'wp-config.php';
	if(!file_exists($wp_conifg)) {
		return -2;
	}
	
	$wp_conifg = file_get_contents($wp_conifg);
	
	preg_match_all(
		'/define\(\s*?[\'\"](.*?)[\'\"]\,\s*?[\'\"](.*?)[\'\"]\s*?\);/', 
		$wp_conifg, 
		$settings
	);
	
	$database_name = false;
	$database_user = false;
	$database_password = false;
	$database_host = false;
	
	foreach($settings[1] as $index => $setting_name) {
		switch(trim($setting_name)) {
			case 'DB_NAME':
				$database_name = trim($settings[2][$index]);
			break;
			case 'DB_USER':
				$database_user = trim($settings[2][$index]);
			break;
			case 'DB_PASSWORD':
				$database_password = trim($settings[2][$index]);
			break;
			case 'DB_HOST':
				$database_host = trim($settings[2][$index]);
			break;
		}
	}
	
	$prefix = 'wp_';
	
	preg_match_all(
		'/\$table_prefix\s*?=\s*?[\'\"](.*?)[\'\"];/', 
		$wp_conifg,
		$prefix_match
	);
	
	if(isset($prefix_match[1][0])) {
		$prefix = trim($prefix_match[1][0]);
	}
	
	if($database_host === 'localhost') {
		$database_host = '127.0.0.1';
	}
	
	return (object) array(
			'name' => $database_name, 
			'user' => $database_user, 
			'pass' => $database_password, 
			'host' => $database_host,
			'prefix' => $prefix
		);
}


function wp_version_upgradable($local = '0.0.0', $remote = false) {
	
	if(!(float) $remote) {
		$remote = wp_version_latest();
	}
	
	$local = wp_version_normalize($local);
	$remote = wp_version_normalize($remote);
	
	if(gettype($local) !== gettype($remote)) {
		return -1;
	}
	
	$local_versions = explode('.', $local);
	$remote_versions = explode('.', $remote);
	
	$longest = 0;
	$version_numbers = array_merge($local_versions, $remote_versions);
	
	foreach($version_numbers as $version_number) {
		$current_length = strlen($version_number);
		if($longest < $current_length) {
			$longest = strlen($version_number);
		}
	}
	
	foreach($local_versions as &$local_version) {
		$local_version = str_pad($local_version, $longest, '0', STR_PAD_LEFT);
	}
	foreach($remote_versions as &$remote_version) {
		$remote_version = str_pad($remote_version, $longest, '0', STR_PAD_LEFT);
	}
	
	$local_version_padded = (int) implode('', $local_versions);
	$remove_version_padded = (int) implode('', $remote_versions);
	
	return $local_version_padded < $remove_version_padded;
}


function wp_version_normalize($wp_version = false) {
	if(!is_string($wp_version)) {
		$wp_version = (float) $wp_version;
		$wp_version = (string) $wp_version;
	}
	if(is_string($wp_version)) {
		$wp_version_array = explode('.', $wp_version);
		for($i = 0; $i < 3; $i++) {
			if(!isset($wp_version_array[$i])) {
				$wp_version_array[$i] = '0';
			}
		}
		
		$wp_version = implode('.', $wp_version_array);
	}
	
	return $wp_version;
}


function wp_version_latest() {
	if(cli_get_arg('version')) {
		return wp_version_normalize(cli_get_arg('version'));
	}
	
	$version_api = 'https://api.wordpress.org/core/version-check/1.7/';
	$remote_json = file_get_contents($version_api);
	if(!$remote_json) {
		return -1;
	}
	
	$version_data = json_decode($remote_json);
	if(!$version_data) {
		return -2;
	}
	
	if(
		!isset($version_data->offers) || 
		!is_array($version_data->offers) || 
		!isset($version_data->offers[0]) ||
		!isset($version_data->offers[0]->version)
	) {
		return -3;
	}
	
	return wp_version_normalize($version_data->offers[0]->version);
}


function wp_version_local($path = false) {
	
	if(!$path || !is_string($path)) {
		return -1;
	}
	
	if(!preg_match('/\/wp-includes\/version.php$/', $path)) {
		if(!preg_match('/\/$/', $path)) {
			$path .= '/';
		}
		$path .= 'wp-includes/version.php';
	}
	
	if(!file_exists($path)) {
		return -2;
	}
	
	try {
		include $path;
		return wp_version_normalize($wp_version);
		
	} catch (Exception $e) {
		return -3;
	}
}


function shell_which($program = false) {
	if(!$program) {
		return false;
	}
	
	$result = exec('which ' . escapeshellcmd($program), $output, $code);
	return $result ? $result : false;
}


function config_apply($file, $defaults) {
	if(!isset($file->modifications)) {
		$file->_modifications = array();
	}
	
	if(!isset($file->executables)) {
		$file->executables = $defaults->executables;
		$file->_modifications[] = 'No executables listed. Using defaults.';
	}
	
	foreach($defaults->executables as $name => $value) {
		if(
			!isset($file->executables->$name) ||
			!file_exists($file->executables->$name) || 
			!is_executable($file->executables->$name)
		) {
			$file->executables->$name = $value;
			$file->_modifications[] = "{$name} using default: {$value}";
		}
	}
	
	if(!isset($file->paths)) {
		$file->paths = $defaults->paths;
		$file->_modifications[] = 'No paths listed. Using defaults.';
	}
	
	foreach($defaults->paths as $name => $value) {
		
		if(!isset($file->paths->$name)) {
			$file->paths->$name = $value;
			$file->_modifications[] = "Path {$name} using default: {$value}";
		} else if(
			is_file($file->paths->$name) && !is_dir($file->paths->$name)
		) {
			$file->paths->$name = dirname($file->paths->$name) . '/';
		} else if(substr($file->paths->$name, -1) !== '/') {
			$file->_modifications[] = "{$file->paths->$name} to " . 
				"{$file->paths->$name}/";
			$file->paths->$name = $value . '/';
		}
		
		if(!file_exists($file->paths->$name)) {
			if(
				is_writable(dirname($file->paths->$name)) &&
				mkdir($file->paths->$name)
			) {
				$file->_modifications[] = "Created folder " . 
					"{$file->paths->$name}";
			} else {
				$file->_modifications[] = "{$file->paths->$name} can't be " . 
					"created using default {$file->paths->$name}";
				$file->paths->$name = $value;
			}
		}
		
		if(!file_exists($file->paths->$name)) {
			mkdir($file->paths->$name);
		}
	}
	
	if(isset($file->include) && $file->include) {
		$keep_includes = array();
		
		if(!is_array($file->include)) {
			$file->include = array($file->include);
		}
		
		foreach($file->include as $value) {
			if($value && file_exists($value) && is_writable($value)) {
				if(!is_dir($value)) {
					$value = dirname($value) . '/';
				}
				if(substr($value, -1) !== '/') {
					$file->_modifications[] = "Include {$value} to {$value}/";
					$value = $value . '/';
				}
				$keep_includes[] = $value;
			} else {
				$file->_modifications[] = "Removing unusable " . 
					"include '{$value}'";
			}
		}
		
		$file->include = $keep_includes;
	}
	
	if(isset($file->exclude) && $file->exclude) {
		$keep_excludes = array();
		
		if(!is_array($file->exclude)) {
			$file->exclude = array($file->exclude);
		}
		
		foreach($file->exclude as $value) {
			if(file_exists($value) && !is_dir($value)) {
				$value = pathinfo($value, PATHINFO_DIRNAME) . '/';
			} else if(substr($value, -1) !== '/') {
				$file->_modifications[] = "Exclude {$value} to {$value}/";
				$value = $value . '/';
			}
			
			if(file_exists($value) && is_dir($value)) {
				$keep_excludes[] = $value;
			} else {
				$file->_modifications[] = "Removing unusable " . 
					"exclude '{$value}'";
			}
		}
		
		$file->exclude = $keep_excludes;
	}
	
	return $file;
}

function cli_countdown($seconds = false, $final = false, $string = '%d second%s left...') {
	
	$seconds = (int) $seconds;
	
	if(!$seconds) {
		return;
	}
	
	$pad = 0;
	
	if(is_string($final)) {
		$pad = strlen($final);
	}
	
	for($i = $seconds; $i > 0; $i--) {
		$update_str = sprintf($string, $i, $i === 1 ? '' : 's');
		if(strlen($update_str) > $pad) {
			$pad = strlen($update_str);
		}
	}
	
	for($i = $seconds; $i > 0; $i--) {
		echo str_pad(
				sprintf($string, $i, $i === 1 ? '' : 's'), 
				$pad, 
				' ', 
				STR_PAD_RIGHT
			) . "\r";
		sleep(1);
	}
	
	if($final) {
		echo str_pad($final, $pad, ' ', STR_PAD_RIGHT) . "\r";
		sleep(1);		
	}
}


function diff_html($before = '', $after = '') {
	$before = preg_replace('/ver=.*?\'/', '', $before);
	$after = preg_replace('/ver=.*?\'/', '', $after);
	
	$before = preg_replace('/\s+/', ' ', $before);
	$after = preg_replace('/\s+/', ' ', $after);
	
	$before = preg_replace('/[\r\n]/', '', $before);
	$after = preg_replace('/[\r\n]/', '', $after);
	
	$before = str_replace(">", ">\n", $before);
	$after = str_replace(">", ">\n", $after);		
	
	$before_tmp = explode("\n", $before);
	$after_tmp = explode("\n", $after);
	
	foreach($before_tmp as $line_number => $line) {
		if(trim($line) !== trim($after_tmp[$line_number])) {
			return array(trim($line), trim($after_tmp[$line_number]));
		}
	}
	
	return false;
}


function date_pretty($seconds = 0) {
	$date = explode(
		':',
		date(
			'H:i:s', 
			strtotime('+' . $seconds . ' seconds', strtotime('00:00:00'))
		)
	);
	$parts = array('hour', 'minute', 'second');
	$output = '';
	
	foreach($date as $index => $value) {
		$value = (int) $value;
		if($value > 0) {
			if($output) {
				$output .= ' ';
			}
			$output .= $value . ' ' . $parts[$index];
			if($value !== 1) {
				$output .= 's';
			}
		}
	}
	
	if($output === '') {
		$output = '0 seconds';
	}
	
	return $output;
}