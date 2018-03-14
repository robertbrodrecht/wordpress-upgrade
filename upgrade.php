#!/usr/bin/env php
<?php

$time_start = time();
$count_down = 3;

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

if(!$config->exclude) {
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

if(cli_get_arg('clean')) {
	echo "\n";
	echo "Cleaning up WordPress temporary files...\n";
	exec('rm -rf ' . $wordpress_gzip_output);
}

if(
	(
		!file_exists($wordpress_gzip_output) || 
		wp_version_upgradable(wp_version_local($wordpress_gzip_output))
	) &&
	!cli_get_arg('no-download')
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

if(
	!cli_get_arg('no-upgrade') && 
	wp_version_latest() !== $wordpress_local_upgrade_to
) {
	echo wordwrap(
			"\nVersion " . wp_version_latest() . " is different than the " .
			"local version {$wordpress_local_upgrade_to}. Try running with " .
			"--clean to delete local copy fpr a mew download or --version " .
			"{$wordpress_local_upgrade_to} to force the currently downloaded" .
			"version.\n\n",
			80
		);
	exit;
}

if(!cli_get_arg('no-upgrade')) {
	echo "\n";
	echo "Latest WordPress is {$wordpress_local_upgrade_to}...\n";
}

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
		
		$wp_current_version = wp_version_local(
			dirname($wp_config_location) . '/'
		);
		
		if(!wp_version_upgradable($wp_current_version)) {
			$no_reason = 'Current';
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
		echo "Will attempt to upgrade to {$wp_latest}:\n";
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

echo "\nAre you onboard?  If not, press ctrl+C.\n";
echo "\n";
cli_countdown($count_down, 'OK, here we go.');
echo "\n";

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
	$wp_size = site_size($wp_upgrade);
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
			"'siteurl' or option_name = 'blogname';"
		);
	
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
				$blogname = $value;
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
	
	if(!cli_get_arg('no-upgrade')) {
		echo "\n";
		echo "Taking HTML Snapshot...\n";
		if($site_full_url) {
			$html_before = @file_get_contents($site_full_url);
			if($html_before) {
				$html_before_md5 = md5($html_before);
				echo "+ Snapshot signature: {$html_before_md5}\n";
			} else {
				echo "- Could not get snapshot from '{$site_full_url}'\n";
			}
		} else {
			$html_before = false;
			echo "- Could not determine URL due to database issues.'\n";
		}
	}
	
	if(!cli_get_arg('no-backup')) {
		echo "\n";
		echo "Backing up database...\n";
		$mysql_dump_output = $paths->backups . $file_name . '.sql';
		$mysql_dump = $exec->mysqldump . 
			' --defaults-extra-file=' . 
			escapeshellarg($paths->backups . 'tmp_config.cnf') .
			' ' . escapeshellarg($wp_db->name) .
			' > ' .
			escapeshellarg($mysql_dump_output);
		
		@exec($mysql_dump, $mysql_dump_results);
		
		if(file_exists($mysql_dump_output) && filesize($mysql_dump_output)) {
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
			escapeshellarg($folder_name);
		
		@exec($tar_command, $tar_results);
		
		if(file_exists($tar_output) && filesize($tar_output) > 500) {
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
		echo "Upgrading WordPress\n";
		
		
		$cp_results = false;
		$cp = $exec->cp . ' -Rf ' . escapeshellarg($wordpress_gzip_output) .  
			'* ' . escapeshellarg($wp_upgrade);
			
		exec($cp, $cp_results);
		
		$wp_upgrade_version = wp_version_local($wp_upgrade);
		
		if($wp_upgrade_version === $wp_latest) {
			echo "+ Complete.\n";
		} else {
			$verify[] = $wp_upgrade;
			echo "- Not 100% sure the upgrade took.\n";
		}
		
		echo "\n";
		echo "Comparing HTML Snapshot...\n";
		if($site_full_url && $html_before) {
			$html_after = @file_get_contents($site_full_url);
			$html_after_md5 = md5($html_after);
			
			$diff = false;
			
			echo "+ Snapshot before signature: {$html_before_md5}\n";
			echo "+ Snapshot after signature: {$html_after_md5}\n";
			
			if($html_before_md5 !== $html_after_md5) {
				echo "+ Snapshot signatures differ.\n";
				$diff = diff_html($html_before, $html_after);
			} else {
				
			}
			
			if($diff === false) {
				echo "+ No Differences.\n";
			} else {
				echo "- Differences found. Before and after files are in the" . 
					" backups folder for comparison.\n";
				echo "  First difference starting here:\n";
				echo "  Before: {$diff[0]}\n";
				echo "  After: {$diff[1]}\n";

				file_put_contents(
					$paths->backups . $file_name . '_before.html',
					$html_after
				);
				
				file_put_contents(
					$paths->backups . $file_name . '_after.html',
					$html_after
				);
				
				$verify[] = $wp_upgrade;
			}
			
		} else {
			echo "- Could not compare due to earlier snapshot failure.\n";			
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