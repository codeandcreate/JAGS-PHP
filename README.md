# Jet Another Gemini Server (for PHP)

A simple easy to understand and extendable single PHP-Class socket server written in PHP.

JAGS is based of Gemini-PHP by @neil@glasgow.social (Matrix). You can read more about it at gemini://glasgow.social/gemini-php

## Changes

- 2021-02-22: 202102_1 - first release

## Quickstart

1. git clone this repository (or download a zipped snapshot)
2. create a new ssl certificate for your host with ```openssl req -x509 -newkey rsa:4096 -keyout key.pem -out cert.pem -days 365```
3. concet the key and cert to one file with ```cp cert.pem certs/yourdomain.com.pem; cat key.pem >> certs/yourdomain.com.pem```
4. copy ```config.sample.php``` to ```config.php```
5. edit lines 22 and 33 and add your certificate and password to them
6. run the server with ```php server.php```

For more details look at the comments in ```config.sample.php```, ```server.php``` or the files in the hosts/default directory.

## Dynamic pages with PHP

The server looks for PHP scripts and runs them with ```include```. All your scripts ```echo``` (...) will returned to the client as ```text/gemini``` mime type.

If you want to check the request data, for example for get params or auth informations you can do this with the ```$JAGSRequest``` array inside your script:

```
[
	'host'		=> '...',
	'scheme' 	=> 'gemini',
	'path'		=> '/dynamic.php',
	'query'		=> '...',
	'get' 		=> [],
	'auth' 		=> false,
	'file_path'	=> '...',
]
```

It's the result of ```parse_url()``` with a extra array for get-params, auth informations by ```openssl_x509_parse()``` and the absolute path to the current file.

To manipulate the return data check the ```$JAGSReturn``` array:

```
[
	'content' 		=> false,
	'status_code'	=> '20',
	'meta' 			=> 'text/gemini',
	'file_size' 	=> 0,
]
```

```file_size``` will be automatically calculated based on ```content```, which will be filled with all stuff you ```echo``` in your PHP script.

If you want virtual paths you could just place a PHP named for example ```dynamic.php``` and open ```/dynamic/myparam1/myparam2=2?param3=test``` in your Gemini browser. The result in $JAGREQUEST is following:

```
[
	//...
	'path'		=> '/dynamic.php',
	'query'		=> 'param3=test&myparam1&myparam2=2',
	'get' 		=> [
		'param3'		=> 'test',
		1 				=> 'myparam1',
		'myparam2'		=> '2',
	],
	//...
]
```

Notice: "myparam1" will be added as indexed param.