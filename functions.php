<?php

function get_arg($arg) {
	echo "\n\nCLI OPTIONS DO NOT WORK YET!!!\n\n";
	return false;
}


function site_size($path = false) {
	if(!$path) {
		return 'Unknown Size';
	}
	
	$du_command = 'du -ksh ' . escapeshellarg($path);
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
		'/define\([\'\"](.*?)[\'\"]\,\s*[\'\"](.*?)[\'\"]\)/', 
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
	
	return (object) array(
			'name' => $database_name, 
			'user' => $database_user, 
			'pass' => $database_password, 
			'host' => $database_host
		);
}


function wp_version_upgradable($local = '0.0.0', $remote = false) {
	global $wp_latest;
	
	if(!(float) $wp_latest) {
		wp_version_latest();
	}
	
	if(!(float) $remote) {
		$remote = $wp_latest;
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
	global $wp_latest, $wp_latest_url;
	
	if($wp_latest && $wp_latest !== '0.0.0') {
		return $wp_latest;
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
		!isset($version_data->offers[0]->version) ||
		!isset($version_data->offers[0]->packages->no_content)
	) {
		return -3;
	}
	
	$wp_latest_url = $version_data->offers[0]->packages->no_content;
	$wp_latest = wp_version_normalize($version_data->offers[0]->version);
	
	return $wp_latest;
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
	
	if($file->include) {
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
	
	if($file->exclude) {
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


global $wp_latest, $wp_latest_url;

echo "\n\nIF YOU ARE GOING LIVE, DON'T FORGET TO ADJUST THE HARD CODING IN FUNCTIONS!!!\n\n";
//wp_version_latest();
$wp_latest = '4.9.4';
$wp_latest_url = 'https://downloads.wordpress.org/release/wordpress-4.9.4-no-content.zip';