#!/usr/bin/env php
<?php

$plugin_folder_name = 'classic-editor';

$time_start = time();
$count_down = 30;

echo "\n";


include 'functions.php';

$cli_args = cli_get_arg();

$wp_latest_url = 'https://wordpress.org/latest.tar.gz';
if(cli_get_arg('package')) {
	$wp_latest_url = cli_get_arg('package');
}
$wp_latest = wp_version_latest();

set_time_limit(0);

$script_path = dirname(__FILE__) . '/';

$config_default = (object) array(
	'executables' => (object) array(
		'mysqldump' => shell_which('mysqldump'),
		'mysql' => shell_which('mysql'),
		'tar' => shell_which('tar'),
		'find' => shell_which('find'),
		'curl' => shell_which('curl'),
		'du' => shell_which('du'),
		'cp' => shell_which('cp')
	),
	'paths' => (object) array(
		'sites' => dirname($script_path) . '/',
		'backups' => $script_path . 'backups/',
		'wordpress' => $script_path . 'wordpress/',
		'plugin' => $script_path . $plugin_folder_name
	)
);

$config = false;

if(is_object($config)) {
	echo "+ Applying configuration.\n";
	$config = config_apply($config, $config_default);
} else if($config === false) {
	echo "+ Using default configuration.\n";
	$config = $config_default;
}

if(!isset($config->exclude) || !$config->exclude) {
	$config->exclude = array();
}

if(cli_get_arg('single')) {
	$config->paths->sites = cli_get_arg('single');
	if(substr($config->paths->sites, -1) !== '/') {
		$config->paths->sites = $config->paths->sites . '/';
	}
	$config->include = array();
	$config->exclude = array();
}

$config->exclude[] = $config->paths->wordpress;

$exec = $config->executables;
$paths = $config->paths;

echo "\n";
echo "Here are your settings.  Please verify:\n";
if(isset($config->_modifications) && $config->_modifications) {
	$settings_modifications = $config->_modifications;
} else {
	$settings_modifications = array();
}

if(isset($config->_modifications)) {
	unset($config->_modifications);
}

echo str_replace('\/', '/', json_encode($config, JSON_PRETTY_PRINT));
if($settings_modifications) {
	echo "\n\n";
	echo "Modifications to your config:\n";
	foreach($settings_modifications as $settings_modification) {
		echo "+ {$settings_modification}\n";
	}
}

if(!cli_get_arg('no-upgrade') || !cli_get_arg('no-backup')) {
	echo "\n";
	if(cli_get_arg('auto')) {
		echo "\nAre you onboard?  If not, press ctrl+C.\n";
		echo "\n";
		cli_countdown($count_down, 'OK, here we go.');
		echo "\n\n";
	} else {
		echo "\nAre you onboard? Enter Y or N: ";
		$stdin = fopen ('php://stdin', 'r');
		$line = fgets($stdin);
		if(strtolower(trim($line)) != 'y'){
		    echo "Ok, we're done here.\n\n";
		    exit;
		} else {
			echo "\nOK, here we go.\n\n";
		}
		fclose($stdin);	
	}
}


echo "\n";
echo "Searching for likely WordPress installs...\n";

exec(
	$exec->find . ' ' . escapeshellarg(substr($paths->sites, 0, -1)) . 
		' -name "wp-config.php" 2> /dev/null',
	$wp_config_locations
);

if(isset($config->include) && $config->include) {
	foreach($config->include as $include_path) {
		exec(
			$exec->find . ' ' . escapeshellarg(substr($include_path, 0, -1)) . 
				' -name "wp-config.php" 2> /dev/null',
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
		
		$database = wp_database(dirname($wp_config_location) . '/');
		foreach($database as $k => $v) {
			if(!$v) {
				$no_reason = 'Bad wp-config.php';
			}
		}
		
		if(cli_get_arg('no-upgrade') || !$no_reason) {
			$wp_upgrades[] = dirname($wp_config_location) . '/';
		} else {
			$wp_no_upgrades[] = "{$no_reason}: " . dirname($wp_config_location);
		}
	} else {
		$wp_no_upgrades[] = 'Not Writable: ' . dirname($wp_config_location);
	}
}

if($wp_upgrades) {
	sort($wp_upgrades);
	echo "\n";
	if(cli_get_arg('no-upgrade')) {
		echo "Will attempt to examine:\n";
	} else {
		echo "Will attempt to install plugin:\n";
	}
	foreach($wp_upgrades as $wp_upgrade) {
		$wp_upgrade_pretty = str_replace($paths->sites, '', $wp_upgrade);
		$wp_upgrade_pretty = substr($wp_upgrade_pretty, 0, -1);
		$wp_current_version = wp_version_local($wp_upgrade);
		
		echo "+ {$wp_upgrade_pretty} (" . $wp_current_version . ")\n";
	}
}

if($wp_no_upgrades) {
	echo "\n";
	echo "Will not attempt to upgrade:\n";
	foreach($wp_no_upgrades as $wp_no_upgrade) {
		echo "+ {$wp_no_upgrade}\n";
	}
}

if(!cli_get_arg('no-upgrade') || !cli_get_arg('no-backup')) {
	if(cli_get_arg('dry-run')) {
		echo "\n!!!! THIS IS A DRY RUN. NO CHANGES WILL BE MADE !!!!";
	}
	echo "\n";
	if(cli_get_arg('auto')) {
		echo "\nAre you onboard?  If not, press ctrl+C.\n";
		echo "\n";
		cli_countdown($count_down, 'OK, here we go.');
		echo "\n\n";
	} else {
		echo "\nAre you onboard? Enter Y or N: ";
		$stdin = fopen ('php://stdin', 'r');
		$line = fgets($stdin);
		if(strtolower(trim($line)) != 'y'){
		    echo "Ok, we're done here.\n\n";
		    exit;
		} else {
			echo "\nOK, here we go.\n\n";
		}
		fclose($stdin);	
	}
}

$counter = 1;
$total = count($wp_upgrades);
$time_upgrade = time();

$success = array();
$failure = array();
$verify = array();


foreach($wp_upgrades as $wp_upgrade) {
	echo "\n" . str_repeat('-', 80) . "\n\n";
	
	$time_this_upgrade = time();
	
	$wp_upgrade_pretty = str_replace($paths->sites, '', $wp_upgrade);
	$wp_upgrade_pretty = substr($wp_upgrade_pretty, 0, -1);
	$wp_current_version = wp_version_local($wp_upgrade);
	$wp_size = site_size($wp_upgrade, $exec->du);
	$wp_db = wp_database($wp_upgrade);
	
	$conf_file = fopen($paths->backups . 'tmp_config.cnf', 'w');
	fwrite($conf_file, "[client]\n");
	fwrite($conf_file, "user={$wp_db->user}\n");
	fwrite($conf_file, "password={$wp_db->pass}\n");
	fwrite($conf_file, "host={$wp_db->host}\n");
	fclose($conf_file);
	
	$info_query_results = false;
	
	$mysql_query = $exec->mysql . 
		' --defaults-extra-file=' . 
		escapeshellarg($paths->backups . 'tmp_config.cnf') .
		' ' . escapeshellarg($wp_db->name) .
		' --batch -e ' .
		escapeshellarg(
			"select * from {$wp_db->prefix}options where option_name = " . 
			"'siteurl' or option_name = 'blogname'; "
		) . ' 2> /dev/null';
	
	@exec($mysql_query, $info_query_results);
	
	$blogname = 'Unknown';
	$site_full_url = false;
	$file_name = str_replace('/', '-', trim($wp_upgrade_pretty, '/'));
	
	$can_dump_database = true;
	
	if($info_query_results) {
		foreach($info_query_results as $result) {
			$result = explode("\t", $result);
			$key = trim($result[1]);
			$value = trim($result[2]);
			
			if($key === 'blogname') {
				$blogname = preg_replace_callback(
					"/(&#[0-9]+;)/", 
					function($m) {
						return mb_convert_encoding(
								$m[1], 
								"UTF-8", 
								"HTML-ENTITIES"
							); 
					}, 
					$value
				);
				
				$blogname = html_entity_decode($blogname);
			}
			if($key === 'siteurl') {
				
				if(!preg_match('/^https?:\/\//', $value)) {
					$value = 'http://' . $value . '/';
				}
				
				$site_full_url = $value;
				$site_url = parse_url($value, PHP_URL_HOST);
				$site_path = trim(parse_url($value, PHP_URL_PATH), '/');
				
				if($site_path && $site_path != '/') {
					$site_path = trim($site_path, '/');
					$site_path = str_replace('/', '-', $site_path);
					$site_path = '-' . $site_path;
				}
				
				$file_name = $site_url . $site_path;
			}
		}
	} else {
		$blogname = $file_name;
		$can_dump_database = false;
	}
	
	$backp_db_success = false;
	$backp_files_success = false;
	
	echo "{$blogname}\n";
	echo "✓ URL: {$site_full_url}\n";
	echo "✓ Path: {$wp_upgrade}\n";
	echo "✓ Site Size: {$wp_size}\n";	
	echo "✓ Current Version: {$wp_current_version}\n";
	echo "✓ Latest Version: {$wp_latest}\n";
	echo "✓ Save File Name Base: {$file_name}\n";
	
	if(!cli_get_arg('no-backup') && !$can_dump_database) {
		echo "\n";
		echo "Can't connect to database.  Aborting.";
		$failure[] = $wp_upgrade;
		
		$counter++;
		continue;
	}
	
	$html_before = false;
	$html_before_md5 = false;
	
	if(!cli_get_arg('no-backup')) {
		echo "\n";
		echo "Backing up database...\n";
		$mysql_dump_output = $paths->backups . $file_name . '.sql';
		$mysql_dump = $exec->mysqldump . 
			' --defaults-extra-file=' . 
			escapeshellarg($paths->backups . 'tmp_config.cnf') .
			' ' . escapeshellarg($wp_db->name) .
			' > ' .
			escapeshellarg($mysql_dump_output) . ' 2> /dev/null';
		
		if(!cli_get_arg('dry-run')) {
			@exec($mysql_dump, $mysql_dump_results);
		}
		
		if(
			(file_exists($mysql_dump_output) && filesize($mysql_dump_output)) ||
			cli_get_arg('dry-run')
		) {
			$backp_db_success = true;
			echo "+ Done.\n";	
		} else {
			echo "- Database backup failed.\n";
		}
		
		echo "\n";
		echo "Backing up files...\n";
		
		$folder_name = str_replace(dirname($wp_upgrade), '', $wp_upgrade);
		$folder_name = trim($folder_name, '/');
		
		$tar_results = false;
		
		$tar_output = $paths->backups . $file_name . '.tar.gz';
		$tar_command = $exec->tar . ' -C ' . 
			escapeshellarg(dirname($wp_upgrade) . '/') .
			' -czf ' . $tar_output . ' ' . 
			escapeshellarg($folder_name) . ' 2> /dev/null';
		
		if(!cli_get_arg('dry-run')) {
			@exec($tar_command, $tar_results);
		}
		
		if(
			(file_exists($tar_output) && filesize($tar_output) > 500) ||
			cli_get_arg('dry-run')
		) {
			$backp_files_success = true;
			echo "+ Done.\n";
		} else {
			echo "- Could not perform backup.\n";
		}
	} else {
		$backp_files_success = true;
		$backp_db_success = true;
	}
	
	if(!$backp_files_success || !$backp_db_success) {
		echo "\n";
		echo "Backup failed. Aborting upgrade.\n";
		$failure[] = $wp_upgrade;
		
	} else if(!cli_get_arg('no-upgrade')) {
		echo "\n";	
		echo "Installing plugin\n";
		
		
		$cp_results = false;
		$cp = $exec->cp . ' -Rf ' . escapeshellarg($config_default->paths->plugin) .  
			' ' . escapeshellarg($wp_upgrade . '/wp-content/plugins/');
		
		if(!cli_get_arg('dry-run')) {
			exec($cp, $cp_results);
		}
		
		if(file_exists($wp_upgrade . '/wp-content/plugins/' . $plugin_folder_name)) {
			echo "+ Complete.\n";
		} else {
			$verify[] = $wp_upgrade;
			echo "- Not 100% sure the upgrade took.\n";
		}
		
		$success[] = $wp_upgrade;
	} else {
		$success[] = $wp_upgrade;
	}
	
	
	if(!cli_get_arg('no-upgrade') || !cli_get_arg('no-backup')) {
		$percent_complete = round($counter / $total * 100);
		$time_elapsed = date_pretty((time() - $time_start));
		$time_this_upgrade = date_pretty((time() - $time_this_upgrade));
		$avg_time = date_pretty(round((time() - $time_upgrade)/$counter));
		
		$success_rate = round(
				count($success) / (count($success) + count($failure)) * 100
			);
		
		echo "\n" . str_repeat('-', 80) . "\n\n";
		
		echo "Progress:\n";
		echo "✓ Complete: {$percent_complete}%\n";
		if(!cli_get_arg('no-upgrade')) {
			echo "✓ Success Rate: {$success_rate}%\n";
			echo "✓ This Upgrade Time: $time_this_upgrade\n";
		} else if(!cli_get_arg('no-backup')) {
			echo "✓ Success Rate: {$success_rate}%\n";
			echo "✓ This Backup Time: $time_this_upgrade\n";
		}
		echo "✓ Total Time Elapsed: $time_elapsed\n";
		echo "✓ Avg Time Per Site: $avg_time\n";
	}
	
	unlink($paths->backups . 'tmp_config.cnf');
	
	$counter++;
}

if(!cli_get_arg('no-upgrade') || !cli_get_arg('no-backup')) {
	if($success || $failure || $verify) {
		echo "\n" . str_repeat('-', 80) . "\n\n";
	}
	
	if($success) {
		echo "Successful updates:\n";
		foreach($success as $site) {
			echo "+ {$site}\n";
		}
	}
	
	if($failure) {
		echo "\n";
		echo "Failed updates:\n";
		foreach($failure as $site) {
			echo "+ {$site}\n";
		}
	}
	
	if($verify) {
		echo "\n";
		echo "Verify these sites:\n";
		foreach($verify as $site) {
			echo "+ {$site}\n";
		}
	}
	
	if(!$success && !$failure && !$verify) {
		echo "\n";
	}
}

echo "\n" . str_repeat('-', 80) . "\n\n";
echo "Done at " . date('Y-m-d H:i:s') . " after " . date_pretty((time() - $time_start)) . ".";
echo "\n\n";