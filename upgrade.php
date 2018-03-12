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

var_dump($config_default);
exit();

$config = @file_get_contents($script_path . 'config.json');
$config = @json_decode($config);

if(!$config) {
	$config = (object) array();
}

echo '<pre style="line-height: 1; padding: 10px; background: #EEE; color: #333;">';
var_dump($config);
echo '</pre>';

// die('Apply the config doesn\'t work right.');

var_dump($config);

$config = config_apply(
		$config,
		(object) array(
			'mysqldump' => $mysqldump,
			'tar' => $tar,
			'find' => $find,
			'sites' => $sites_path,
			'backups' => $backup_path,
			'wordpress' => $wordpress_path
		)
	);

var_dump($config);

exec(
	$find . ' ' . escapeshellarg(substr($sites_path, 0, -1)) . 
		' -name "wp-config.php"',
	$files
);

var_dump($files);