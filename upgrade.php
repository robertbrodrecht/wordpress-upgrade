#!/usr/bin/env php
<?php

$time_start = time();
$count_down = 0;

echo "\n";

// This is where cli settings things are: $argv

include 'functions.php';

set_time_limit(0);

$script_path = dirname(__FILE__) . '/';

$config_default = (object) array(
	'executables' => (object) array(
		'mysqldump' => shell_which('mysqldump'),
		'tar' => shell_which('tar'),
		'find' => shell_which('find'),
		'curl' => shell_which('curl'),
		'du' => shell_which('du')
	),
	'paths' => (object) array(
		'sites' => dirname($script_path) . '/',
		'backups' => $script_path . 'backups/',
		'wordpress' => $script_path . 'wordpress/'
	)
);

echo "Loading config...\n";
if(file_exists($script_path . 'config.json')) {
	$config = @file_get_contents($script_path . 'config.json');
	if($config) {
		$config = @json_decode($config);
		if($config) {
			echo "+ Config loaded.\n";
		} else {
			echo "+ Could not parse config. Check your syntax.\n\n";
			exit();
		}
	} else {
		$config = false;
		echo "+ Could not load config.\n";
	}	
} else {
	$config = false;
	echo "+ No config file found.\n";
}

if(is_object($config)) {
	echo "+ Applying configuration.\n";
	$config = config_apply($config, $config_default);
} else if($config === false) {
	echo "+ Using default configuration.\n";
	$config = $config_default;
}

$exec = $config->executables;
$paths = $config->paths;

echo "\n";
echo "Here are your settings.  Please verify:\n";
$settings_modifications = $config->_modifications;
unset($config->_modifications);
echo str_replace('\/', '/', json_encode($config, JSON_PRETTY_PRINT));
if($settings_modifications) {
	echo "\n\n";
	echo "Modifications to your config:\n";
	foreach($settings_modifications as $settings_modification) {
		echo "+ {$settings_modification}\n";
	}
}
echo "\nAre you onboard?  If not, press ctrl+C.\n";
echo "\n";
cli_countdown($count_down, 'OK, here we go.');
echo "\n\n";



$wordpress_gzip = $paths->wordpress . 'latest.tar.gz';
$wordpress_gzip_output = $paths->wordpress . 'wordpress/';

if(get_arg('--clean')) {
	echo "\n";
	echo "Cleaning up WordPress temporary files...\n";
	exec('rm -rf ' . $wordpress_gzip_output);
}

if(
	!file_exists($wordpress_gzip_output) || 
	wp_version_upgradable(wp_version_local($wordpress_gzip_output))
) {
	echo "\n";
	echo "We need to download WordPress...\n\n";
	sleep(2);
	
	exec(
		$exec->curl . ' ' . escapeshellarg($wp_latest_url) .
		' --output ' . escapeshellarg($wordpress_gzip)
	);
	
	echo "\n";
	
	if(!file_exists($wordpress_gzip)) {
		echo "+ CURL did't work. Trying to use PHP's copy.\n";
		$copy = copy($wp_latest_url, $wordpress_gzip);
	}
	
	if(file_exists($wordpress_gzip)) {
		chmod($wordpress_gzip, 0777);
		if(!file_exists($paths->wordpress)) {
			mkdir($paths->wordpress);
		}
		chmod($paths->wordpress, 0777);
		
		echo "+ Extracting\n";
		exec('tar zxf ' . $wordpress_gzip . ' -C ' . $paths->wordpress, $res);
		echo "+ Deleting gzip.\n";
		unlink($wordpress_gzip);
		
		if(!file_exists($wordpress_gzip_output . 'index.php')) {
			die("- WordPress did not extract.\n");
		}
		echo "+ Removing dangerous stuff\n";
		exec('rm -rf ' . $wordpress_gzip_output . 'wp-content');
		if(file_exists($wordpress_gzip_output . 'wp-content')) {
			die("- Could not remove wp-content.\n");
		}
	} else {
		die("- Could not download WordPress.\n");
	}
}

$wordpress_local_upgrade_to = wp_version_local($wordpress_gzip_output);

if(wp_version_latest() !== $wordpress_local_upgrade_to) {
	echo "\nVersion " . wp_version_latest() . " is different than the " .
		"local version {$wordpress_local_upgrade_to}. Try running with " .
		"--clean to delete local copy.\n\n";
	exit;
}

echo "\n";
echo "Latest WordPress is {$wordpress_local_upgrade_to}...\n";

echo "\n";
echo "Searching for likely WordPress installs...\n";

exec(
	$exec->find . ' ' . escapeshellarg(substr($paths->sites, 0, -1)) . 
		' -name "wp-config.php"',
	$wp_config_locations
);

if($config->include) {
	foreach($config->include as $include_path) {
		exec(
			$exec->find . ' ' . escapeshellarg(substr($include_path, 0, -1)) . 
				' -name "wp-config.php"',
			$wp_extra_locations
		);
		
		$wp_config_locations = array_merge(
				$wp_config_locations, 
				$wp_extra_locations
			);
	}
}

$wp_upgrades = array();
$wp_no_upgrades = array();

foreach($wp_config_locations as $wp_config_location) {
	if(
		is_readable($wp_config_location) &&
		is_writable($wp_config_location)
	) {
		$no_reason = '';
		foreach($config->exclude as $exclude) {
			if(strpos($wp_config_location, $exclude) === 0) {
				$no_reason = 'Excluded';
			}
		}
		
		$database = wp_database(dirname($wp_config_location) . '/');
		foreach($database as $k => $v) {
			if(!$v) {
				$no_reason = 'Bad wp-config.php';
			}
		}
		
		if(!$no_reason) {
			$wp_upgrades[] = dirname($wp_config_location) . '/';
		} else {
			$wp_no_upgrades[] = "{$no_reason}: " . dirname($wp_config_location);
		}
	} else {
		$wp_no_upgrades[] = 'Not Writable: ' . dirname($wp_config_location);
	}
}

if($wp_upgrades) {
	echo "\n";
	echo "Will attempt to upgrade to {$wp_latest}:\n";
	foreach($wp_upgrades as $wp_upgrade) {
		$wp_upgrade_pretty = str_replace($paths->sites, '', $wp_upgrade);
		$wp_upgrade_pretty = substr($wp_upgrade_pretty, 0, -1);
		$wp_current_version = wp_version_local($wp_upgrade);
		
		if(wp_version_upgradable($wp_current_version)) {
			echo "+ {$wp_upgrade_pretty} (" . $wp_current_version . ")\n";
		} else {
			$wp_no_upgrades[] = 'Current: ' . $wp_upgrade;
		}
	}
}

if($wp_no_upgrades) {
	echo "\n";
	echo "Will not attempt to upgrade:\n";
	foreach($wp_no_upgrades as $wp_no_upgrade) {
		echo "+ {$wp_no_upgrade}\n";
	}
}

echo "\nAre you onboard?  If not, press ctrl+C.\n";
echo "\n";
cli_countdown($count_down, 'OK, here we go.');
echo "\n";

foreach($wp_upgrades as $wp_upgrade) {
	echo "\n\n";
	$wp_upgrade_pretty = str_replace($paths->sites, '', $wp_upgrade);
	$wp_upgrade_pretty = substr($wp_upgrade_pretty, 0, -1);
	$wp_current_version = wp_version_local($wp_upgrade);
	$wp_size = site_size($wp_upgrade);
	$wp_db = wp_database($wp_upgrade);
	
	echo "{$wp_upgrade_pretty}\n";
	echo "✓ Path: {$wp_upgrade}\n";
	echo "✓ Site Size: {$wp_size}\n";	
	echo "✓ Current Version: {$wp_current_version}\n";
	echo "✓ Latest Version: {$wp_latest}\n";
	
	echo "\n";
	echo "Backing up...\n";
	echo "+ Database dump...\n";
	echo "+ Gzipping...\n";

	echo "\n";	
	echo "+ Upgrading WordPress\n";
	

}

echo "\n\n--------------\n\n";
echo "Done at " . date('Y-m-d H:i:s') . " after " . (time() - $time_start) . " seconds.";
echo "\n\n";