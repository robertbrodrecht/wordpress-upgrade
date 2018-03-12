<?php


function wp_version_upgradable($local = '0.0.0', $remote = false) {
	global $wp_latest;
	
	if(!(float) $wp_latest) {
		$wp_latest = wp_version_latest();
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
	global $wp_latest;
	
	if($wp_latest && $wp_latest === '0.0.0') {
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
		!isset($version_data->offers[0]->version)
	) {
		return -3;
	}
	
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
	if(!isset($file->executables)) {
		$file->executables = $defaults->executables;
	}
	
	foreach($defaults->executables as $name => $value) {
		if(!isset($file->executables->$name)) {
			$file->executables->$name = $value;
		}
	}
	
	foreach($defaults as $top_keys => $top_values) {
		if(!isset($file->$top_keys)) {
			$file->$top_keys = $top_values;
		}
		
		foreach($top_values as $sub_keys => $sub_values) {
			if(!isset($file->$top_keys->$sub_keys)) {
				$file->$top_keys->$sub_keys = $sub_values;
			}
		}
	}
	return $file;
}


global $wp_latest;
$wp_latest = '4.9.4'; //wp_version_latest();