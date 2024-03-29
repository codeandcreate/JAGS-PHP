<?php
/*
 * Set the hosts configuration with of your certificate file(s).
 * All other settings are optional.
 *
 * You can generate a certificate using:
 *
 *  openssl req -x509 -newkey rsa:4096 -keyout key.pem -out cert.pem -days 365 -nodes
 *
 * Then combine the key and certificate and copy them to the certs directory:
 *
 *  cp cert.pem yourdomain.com.pem
 *  cat key.pem >> yourdomain.com.pem
 *
 * Do not use a passphrase!
 */

$config = [
//	'hosts' => [
//		'localhost' => [																// domain name
//			'root'				=> "default",										// folder below 'work_dir'
//			'cert'				=> "certs/yourdomain.com.pem",	// your cert file
//			'cert_domain'	=> "localhost"									// domain name registered in the cert
//		],
//	]

// Additional ssl options - shouldn't be used for a public host atm.
//	'ssl_verify_peer'				=> false,
//	'ssl_capture_peer_cert'	=> false,

// IP address to listen to (leave commented out to listen on all interfaces)
//	'ip' => "127.0.0.1",

// Port to listen on (1965 is the default)
//	'port' => "1965",

// Default index file(s).  If a file isn't specified then the server will
// default to an index file (like index.html on a web server).
// Since it is an array, you can also add for example "index.php" to it.
//	'default_index_file' => ["index.gemini"],

// Logging, setting this to false will disable logging (default is on/true);
//	'logging' => true, 

// Each value will be separated with \t by default.
//	'log_sep' => "\t",

// By default old logs will be deleted by "log_cleanup.php" after 30 days
//	'log_delete_after' => "30days",

// 'work_dir' is the folder that contains config.php, server.php, logs/, libs/, ...
//	'work_dir' => "/var/gemini/",

// The log folder is always relative to 'work_dir'
//	'log_dir' => "logs",

];
?>
