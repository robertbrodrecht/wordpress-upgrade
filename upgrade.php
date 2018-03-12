#!/usr/bin/env php
<?php

$time_start = time();

exec('clear');

echo "\n";

// This is where cli settings things are: $argv

include 'functions.php';

set_time_limit(0);

$script_path = dirname(__FILE__) . '/';

$config_default = (object) array(
	'executables' => (object) array(
		'mysqldump' => shell_which('mysqldump'),
		'tar' => shell_which('tar'),
		'find' => shell_which('find')
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

echo "\n";
echo "Here are your settings.  Please verify:\n";
echo str_replace('\/', '/', json_encode($config, JSON_PRETTY_PRINT));
echo "\n\n";
for($count_down = 3; $count_down > 0; $count_down--) {
	echo 'Starting in ';
	if($count_down < 10) {
		echo '0';
	}
	echo "{$count_down} seconds...\r";
	sleep(1);
}

echo "Ok, here we go.                    \n\n";
sleep(1);

echo "Searching for likely WordPress installs...\n\n";


$exec = $config->executables;
$paths = $config->paths;

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
		$keep = true;
		foreach($config->exclude as $exclude) {
			if(strpos($wp_config_location, $exclude) === 0) {
				$keep = false;
			}
		}
		
		if($keep) {
			$wp_upgrades[] = dirname($wp_config_location);
		} else {
			$wp_no_upgrades[] = 'Excluded: ' . dirname($wp_config_location);
		}
	} else {
		$wp_no_upgrades[] = 'Not Writable: ' . dirname($wp_config_location);
	}
}

if($wp_upgrades) {
	echo "\n";
	echo "Will attempt to upgrade:\n";
	foreach($wp_upgrades as $wp_upgrade) {
		echo "+ {$wp_upgrade}\n";
	}
}

if($wp_no_upgrades) {
	echo "\n";
	echo "Will not attempt to upgrade:\n";
	foreach($wp_no_upgrades as $wp_upgrade) {
		echo "+ {$wp_upgrade}\n";
	}
}


echo "\n\n--------------\n\n";
echo "Done at " . date('Y-m-d H:i:s') . " after " . (time() - $time_start) . " seconds.";
echo "\n\n";