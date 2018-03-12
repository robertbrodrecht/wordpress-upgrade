#!/usr/bin/env php
<?php

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
		echo "+ Could not load config. Using defaults.\n";
	}	
} else {
	$config = false;
	echo "+ No config file found. Using defaults.\n";
}

if($config) {
	echo "+ Applying configuration.\n";
	$config = config_apply($config, $config_default);
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

exec(
	$config->find . ' ' . escapeshellarg(substr($config->sites, 0, -1)) . 
		' -name "wp-config.php"',
	$files
);

var_dump($files);