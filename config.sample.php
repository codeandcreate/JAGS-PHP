<?php
	
$config = [
/*
 * Set the location of your certificate file.  All other settings are optional.
 *
 *
 * This is your certificate file.  A self-signed certificate is acceptable here.
 * You can generate one using:
 *
 *   openssl req -x509 -newkey rsa:4096 -keyout key.pem -out cert.pem -days 365
 *
 * Then combine the key and certificate and copy them to the certs directory:
 *
 *  cp cert.pem certs/yourdomain.com.pem
 *  cat key.pem >> certs/yourdomain.com.pem

 * Enter the passphrase (if you used one) below.
 *
 */

//	'certificate_file'			=> "",
//	'certificate_passphrase'	=> "",

// Additional ssl options
//	'ssl_verify_peer'			=> false,
//	'ssl_capture_peer_cert'		=> false,
	
// IP address to listen to (leave commented out to listen on all interfaces)
//	'ip' => "127.0.0.1",

// Port to listen on (1965 is the default)
//	'port' => "1965",

// Default index file.  If a path isn't specified then the server will
// default to an index file (like index.html on a web server).
//	'default_index_file' => "index.gemini",

// Logging, setting this to false will disable logging (default is on/true);
//	'logging' => true, 

// Each value will be separated with \t by default.
//	'log_sep' => "\t",

// By default old logs will be deleted by "log_cleanup.php" after 30 days
//	'log_delete_after' => "30days",

// 'work_dir' is the folder that contains config.php, server.php, logs/, libs/, ...
// 'work_dir' => "/var/gemini/",

// If you want more than one server running you can change the root directory below "hosts/":
//	'host_dir' => "default",

// The log folder is always relative to 'work_dir'
//	'log_dir' => "logs",

];

?>