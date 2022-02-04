# Jet Another Gemini Server (for PHP)

A simple easy to understand and extendable single PHP-Class socket server written in PHP.

JAGS is based of Gemini-PHP by @neil@glasgow.social (Matrix). You can read more about it at gemini://glasgow.social/gemini-php

## History
| Date | Version | Changes |
|---|---|---|
| 2022-02-04 | 202202_2 | security fix |
| 2022-02-03 | 202202_1 | Multiple (sub) domains |
| 2021-09-11 | 202109_1 | fixed bug for serving files that are bigger than ~100kb |
| 2021-04-28 | 202104_1 | bugfix for high cpu load |
| 2021-02-25 | 202102_4 | bugfix for path params, try/catch for external php scripts, better get params translation, better logging |
| 2021-02-23 | 202102_2 | log cleanup added, more documentation |
| 2021-02-22 | 202102_1 | first release|

## Quickstart

1. git clone this repository (or download a zipped snapshot)
2. create a new ssl certificate for your host with ```openssl req -x509 -newkey rsa:4096 -keyout key.pem -out cert.pem -days 365 -nodes```
3. concet the key and cert to one file with ```cp cert.pem certs/yourdomain.com.pem; cat key.pem >> certs/yourdomain.com.pem```
4. copy ```config.sample.php``` to ```config.php```
5. configure your hosts in line 49 
6. run the server with ```php server.php```

For more details look at the comments in ```config.sample.php```, ```server.php``` and the files in the hosts/default directory.

## Upgrade from 202109_1

- Check the changes in the config.sample.php. With the new hosts-Array you need to reconfigure your server
- If you use a certificate with pass phrase you must replace it with one certificate without password.

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
	'content' 	=> false,
	'status_code'	=> '20',
	'meta' 		=> 'text/gemini',
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
		'param3'	=> 'test',
		1 		=> 'myparam1',
		'myparam2'	=> '2',
	],
	//...
]
```

Notice: "myparam1" will be added as indexed param.

## What is Gemini?

[Excerpt from gemini.circumlunar.space](https://gemini.circumlunar.space/docs/specification.html):

> Gemini is a client-server protocol featuring request-response transactions, broadly similar to gopher or HTTP. Connections are closed at the end of a single transaction and cannot be reused. When Gemini is served over TCP/IP, servers should listen on port 1965 (the first manned Gemini mission, Gemini 3, flew in March'65). This is an unprivileged port, so it's very easy to run a server as a "nobody" user, even if e.g. the server is written in Go and so can't drop privileges in the traditional fashion.

More about Gemini protocol, tools, servers, clients you can find at the [Awesome Gemini](https://github.com/kr1sp1n/awesome-gemini) repository.
