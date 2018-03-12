#!/usr/bin/env php
<?php

echo "\n";

// This is where cli settings things are: $argv

include 'functions.php';

set_time_limit(0);

$mysqldump = shell_which('mysqldump');
$tar = shell_which('tar');
$find = shell_which('find');

$script_path = dirname(__FILE__) . '/';
$sites_path = dirname($script_path) . '/';
$backup_path = $script_path . 'tmp/';

$config = @file_get_contents($script_path . 'config.json');
$config = @json_decode($config);

if(!$config) {
	$config = (object) array();
}

var_dump($config);

$config = config_apply(
		$config,
		(object) array(
			'mysqldump' => $mysqldump,
			'tar' => $tar,
			'find' => $find,
			'sites' => $sites_path,
			'backups' => $backup_path
		)
	);

var_dump($config);

exec(
	$find . ' ' . escapeshellarg(substr($sites_path, 0, -1)) . 
		' -name "wp-config.php"',
	$files
);

var_dump($files);