#!/usr/bin/env php
<?php

$base = "/home/big/";
$top = scandir($base);

$list = array();

foreach($top as $dir) {
	if(substr($dir, 0, 1) !== '.') {
		$middle = $base . $dir . '/';
		$sites = scandir($middle);
		
		foreach($sites as $site) {
			if(substr($site, 0, 1) !== '.') {
				$bottom = $middle . $site . '/';
				if(
					(
						file_exists($bottom . 'index.html') || 
						file_exists($bottom . 'index.php')
					) &&
					!stristr($site, 'bigdev.co')
				) {
					if(!isset($list[$dir])) {
						$list[$dir] = array();
					}
					$list[$dir][] = $site;
				}
			}
		}
	}
}

foreach($list as $client => $sites) {
	echo "$client\n";
	foreach($sites as $site) {
		echo "- $site\n";
	}
	echo "\n";
}