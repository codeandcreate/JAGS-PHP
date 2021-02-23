<?php

// load config from command line
$configFile = "config.php";
if (isset($argv[1])) {
	$configFile = $argv[1];
}

if (!is_file($configFile))
	die("ERROR: can not load config (" . $configFile . "). Please copy config.php.sample to config.php and customize your settings or check param.\n");

require $configFile;	
require "libs/jags.class.php";

$jagsInstance = new JetAnotherGeminiServer($config);
$jagsInstance->rotate_logs();