#!/usr/bin/env php
<?php

include 'functions.php';

set_time_limit(0);

$errors = array();


// -- Date testing.
if(date_pretty(122*60+2) !== '2 hours 2 minutes 2 seconds') {
	$errors[] = "date_pretty(122*60+2) isn't working right";
}



// -- Shell testing.
if(!file_exists(shell_which('mysqldump'))) {
	$errors[] = "shell_which('mysqldump') isn't working right";
}

if(shell_which('adfgadfga') !== false) {
	$errors[] = "shell_which('adfgadfga') isn't working right";
}



// -- Normalization testing.
if(wp_version_normalize(false) !== '0.0.0') {
	$errors[] = 'wp_version_normalize(false) !== "0.0.0"';
}

if(wp_version_normalize(true) !== '1.0.0') {
	$errors[] = 'wp_version_normalize(true) !== "1.0.0"';
}

if(wp_version_normalize(2) !== '2.0.0') {
	$errors[] = 'wp_version_normalize(2) !== "2.0.0"';
}

if(wp_version_normalize(3.1) !== '3.1.0') {
	$errors[] = 'wp_version_normalize(3.1) !== "3.1.0"';
}



// -- Local version testing.
if(wp_version_local(2) !== -1) {
	$errors[] = 'wp_version_local(2) !== -1';
}

if(wp_version_local('/') !== -2) {
	$errors[] = 'wp_version_local("/") !== -1';
}

/*
$path_base = '';

$path_tests = array(
	$path_base,
	$path_base . '/',
	$path_base .'/wp-includes/version.php'
);

foreach($path_tests as $path_test) {
	if(!preg_match('/^\d+\.\d+\.\d+/', wp_version_local($path_test))) {
		$errors[] = 'wp_version_local("' . $path_test . '") is not a version.';
	}
}
*/



// -- Latest version testing.
if(!preg_match('/^\d+\.\d+\.\d+/', wp_version_latest())) {
	$errors[] = 'wp_version_latest() is not a version.';
}



// -- Should upgrade testing.
if(wp_version_upgradable('1', '2') !== true) {
	$errors[] = "wp_version_upgradable('1', '2') !== true";
}
if(wp_version_upgradable('2', '1') !== false) {
	$errors[] = "wp_version_upgradable('2', '1') !== false";
}
if(wp_version_upgradable('1.2', '2.1') !== true) {
	$errors[] = "wp_version_upgradable('1.2', '2.1') !== true";
}
if(wp_version_upgradable('2.1', '1.2') !== false) {
	$errors[] = "wp_version_upgradable('2.1', '1.2') !== false";
}
if(wp_version_upgradable('1.2.1', '2.1.2') !== true) {
	$errors[] = "wp_version_upgradable('1.2.1', '2.1.2') !== true";
}
if(wp_version_upgradable('2.1.2', '1.2.1') !== false) {
	$errors[] = "wp_version_upgradable('2.1.2', '1.2.1') !== false";
}
if(wp_version_upgradable('1', '1') !== false) {
	$errors[] = "wp_version_upgradable('1', '1') !== false";
}



// -- Results
echo "\nResults:\n";
if($errors) {
	foreach($errors as $error) {
		echo "- $error\n";
	}
} else {
	echo "+ All good!\n";
}

echo "\n";


echo "Don't forget you have the WP version hard coded into functions.php\n";