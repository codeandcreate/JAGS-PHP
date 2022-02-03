<?php	
/**
 * Jet Another Gemini Server
 * =========================
 *
 * Based on Gemini-PHP by @neil@glasgow.social (Matrix-Network) / gemini://glasgow.social/gemini-php
 *
 * @author Matthias Weiß <info@codeandcreate.de>
 */
class JetAnotherGeminiServer 
{
	// stores the current server configuration
	private $config = [];
	
	// version info
	private $version = "202202_1";
	
	public function __construct(array $config)
	{
		// set default config and overwrite with custom settings:
		$this->config = array_merge(
			[
				'ip' 						=> "0",
				'port' 						=> "1965",
				'work_dir'					=> realpath(dirname(__FILE__) . "/.."),
				'hosts' 					=> ['localhost' => ["root" => "default"]],
				'log_dir' 					=> "logs",
				'default_index_file' 		=> "index.gemini",
				'certs'						=> [],
				'ssl_verify_peer'			=> false,
				'ssl_capture_peer_cert'		=> false,
				'logging' 					=> true,
				'log_sep' 					=> "\t",
				'log_delete_after'			=> "30days"
			],
			$config
		);

		// check for certificate
		foreach($this->config['hosts'] AS $_hostConfig) {
			if (!isset($_hostConfig['cert']) || !isset($_hostConfig['cert_domain']) || !is_readable($this->config['work_dir'] . "/" . $_hostConfig['cert'])) {
				die("ERROR: Certificate file (" . $_hostConfig['cert'] . ") not readable.\n");
			} else {
				$this->config['certs'][$_hostConfig['cert_domain']] = $this->config['work_dir'] . "/" . $_hostConfig['cert'];
			}
		}
		
		// enable access logging (if configured)
		if ($this->config['logging']) {
			if (!is_dir($this->config['work_dir'] . "/" . $this->config['log_dir'])) {
				mkdir($this->config['work_dir'] . "/" . $this->config['log_dir']);
			}
		}
	}
	
	/**
	 * a simple logging function
	 */
	public function log($type = "access", $ipOrMessage = "", $status_code = "", $meta = "", $filepath = "", $filesize = ""): bool
	{
	    switch($type) {
	        case 'error':
	            $log_file = $this->config['work_dir'] . "/" . $this->config['log_dir'] . "/" . date("Y-m-d") . "_error.log";
		        $str = 
		        	date("Y-m-d H:i:s") . $this->config['log_sep'] . 
		        	(microtime(true) * 10000) . $this->config['log_sep'] . 
		        	$ipOrMessage . "\n";
	            break;
	        default:
	            $log_file = $this->config['work_dir'] . "/" . $this->config['log_dir'] . "/" . date("Y-m-d") . ".log";
		        $str = 
		        	date("Y-m-d H:i:s") . $this->config['log_sep'] . 
		        	(microtime(true) * 10000) . $this->config['log_sep'] . 
		        	$ipOrMessage . $this->config['log_sep'] . 
		        	$status_code . $this->config['log_sep'] . 
		        	$meta.$this->config['log_sep'] . 
		        	$filepath . $this->config['log_sep'] . 
		        	$filesize . "\n";
	            break;
	    }
	    
		return file_put_contents($log_file, $str, FILE_APPEND);
	}
	
					
	/**
	 * Returns the $JAGSRequest array:
	 *
	 * a parse_url() result with get vars (indexed and key/value pair), file_path
	 * and, if ssl option "verify_peer" is enabled, with auth information:
	 *
	 *	[
	 *		'host' 		=> "",
	 *		'scheme' 	=> "",
	 *		'path' 		=> "/",
	 *		'query' 	=> "", 
	 *		'get' 		=> [],
	 *		'auth' 		=> false,
	 *		'file_path' => "",
	 *	]
	 *
	 */
	private function get_jags_request(string $requestString, $socket): array
	{
		 // strip <CR><LF> from the end
		$url = trim($requestString);

		// make sure base structure is always present...
		$JAGSRequest = array_merge(
			[
				'host' 		=> "",
				'scheme' 	=> "",
				'path' 		=> "/",
				'query' 	=> "", 
				'get' 		=> [],
				'auth' 		=> false,
				'file_path' => "",
			],
			parse_url($url)
		);

		// Kristall Browser is adding "__" to the end of the filenames
		// wtf am I missing?
		// also removing ".." to mitigate against directory traversal
		$JAGSRequest['path'] = str_replace(array("..", "__"), "", $JAGSRequest['path']);
		// force an index file to be appended if a filename is missing
		if (empty($JAGSRequest['path']) || $JAGSRequest['path'] === "/") {
			$JAGSRequest['path'] = "/" . $this->config['default_index_file'];
		}
		
		/*
		 * make it possible to render virtual paths with a php-script in the host root directory.
		 * for example the path "/dynamic/param/param=value/param" renders to:
		 * [
		 *	 'path'  => "/dynamic.php",
		 *	 'query' => "param&param=value&param"
		 *	 ...
		 * ]
		 */
		$explodedPath = explode("/", $JAGSRequest['path']);
		if (is_file($this->config['work_dir'] . "/hosts/" . $this->config['hosts'][$JAGSRequest['host']]['root'] . "/" . $explodedPath[1] . ".php")) {
			$JAGSRequest['path'] = "/" . $explodedPath[1] . ".php";
			$pathParams = [];
			foreach($explodedPath AS $_index => $param) {
				if (!in_array($_index, [0,1])) {
					$pathParams[] = urldecode($param);
				}
			}
			$JAGSRequest['query'] = implode("&", $pathParams) . (!empty($JAGSRequest['query']) ? ("&" . $JAGSRequest['query']) : "");
		}	
		

		// make it possible to get rid of .php, .gmi and .gemini extensions
		foreach (['php', 'gmi', 'gemini'] AS $suffixToCheck) {
			if (is_file($this->config['work_dir'] . "/hosts/" . $this->config['hosts'][$JAGSRequest['host']]['root'] . $JAGSRequest['path'] . "." . $suffixToCheck)) {
				$JAGSRequest['path'] .= "." . $suffixToCheck;
				break;
			}
		}

		// fill $_GET and $parsed_url['get']:
		if (isset($JAGSRequest['query']) && !empty($JAGSRequest['query'])) {
			$_tmp_GET = explode("&", $JAGSRequest['query']);
			foreach($_tmp_GET AS $_index => $_param) {
				$_param = explode("=", $_param);
				if (!isset($_param[1]))  {
					$_param[1] = null;
					$JAGSRequest['get'][$_index] = $_param[0];
				} else {
					$JAGSRequest['get'][$_param[0]] = $_param[1];
				}
				$_GET[$_param[0]] = $_param[1];
			}
		}
		
		// add file_path to load the content to serve
		$JAGSRequest['file_path'] = $this->config['work_dir'] . "/hosts/" . $this->config['hosts'][$JAGSRequest['host']]['root'] . $JAGSRequest['path'];

		// add auth informations
		if ($this->config['ssl_verify_peer']) {
			$stream_context_get_params = @stream_context_get_params($socket);
			if (!empty($stream_context_get_params['options']['ssl']['peer_certificate'])) {
				$JAGSRequest['auth'] = @openssl_x509_parse($stream_context_get_params['options']['ssl']['peer_certificate']);
			}
		}

		return $JAGSRequest;
	}
	
	/**
	 * Returns the Gemini return codes based on file availability
	 */
	private function get_status_code($filepath) 
	{
		if (is_file($filepath) and file_exists($filepath)) {
			return "20";
		}
		if (!file_exists($filepath)) {
			return "51";
		}
		
		return "50";
	}
	
	/**
	 * Returns the mime type based on extension (gemini, gmi) and mime_content_type()
	 */
	private function get_mime_type($filepath) 
	{
		// we need a way to detect gemini file types, which PHP doesn't
		// so.. if it ends with gemini (or if it has no extension), assume
		$path_parts = pathinfo($filepath);
		if (
			empty($path_parts['extension']) || 
			in_array($path_parts['extension'], ["gemini", "gmi"])
		) {
			$type = "text/gemini";	
		} else {
			$type = mime_content_type($filepath);
		}
		
		return $type;
	}
	
	/**
	 * The main server function.
	 */
	public function serve()
	{
		$this->log("access", "JAGS version " . $this->version . " started");
		
		$connections = [];
		$context = stream_context_create(
			[
				'ssl' => [
					'verify_peer' => $this->config['ssl_verify_peer'],
        			'verify_peername' => true,
					'capture_peer_cert' => $this->config['ssl_capture_peer_cert'],
					'allow_self_signed' => true,
					'SNI_enabled' => true,
					'SNI_server_certs' => $this->config['certs']
				]
			]
		);
		
		$socket = stream_socket_server(
			"tcp://" . $this->config['ip'] . ":" . $this->config['port'], 
			$errno, 
			$errstr, 
			STREAM_SERVER_BIND|STREAM_SERVER_LISTEN, 
			$context
		);
		$connections[] = $socket;
		
		// apply patch from @nervuri:matrix.org to stop supporting out of spec versions of TLS
		$cryptoMethod = STREAM_CRYPTO_METHOD_TLS_SERVER
			& ~ STREAM_CRYPTO_METHOD_TLSv1_0_SERVER
			& ~ STREAM_CRYPTO_METHOD_TLSv1_1_SERVER;

		while(true) {
			$reads = $connections;
	        $writes = NULL;
	        $excepts = NULL;
			$modified = stream_select($reads, $writes, $excepts, 5);
         	if ($modified === false) {
            	break;
         	}

        	foreach ($reads as $modifiedRead) {
            	if ($modifiedRead === $socket) {
					$forkedSocket = @stream_socket_accept($socket, -1, $remoteIP);
					if (!is_bool($forkedSocket)) {
						$connections[] = $forkedSocket;

						stream_set_blocking($forkedSocket, true);
						$enableCryptoReturn = @stream_socket_enable_crypto($forkedSocket, true, $cryptoMethod);

						if ($enableCryptoReturn === true) {
							$line = stream_get_line($forkedSocket, 1024, "\n");
				
							// default return values
							$content = false;
							$meta = "";
							$file_size = 0;
							
							// get request details
							$JAGSRequest = $this->get_jags_request($line, $forkedSocket);
									
							// runtime vars 			
							$JAGSReturn = [
								'content' => false,
								'status_code' => $this->get_status_code($JAGSRequest['file_path']),
								'meta' => "",
								'file_size' => 0
							]; 
							
							if ($JAGSReturn['status_code'] === "20") {
								$JAGSReturn['meta'] = $this->get_mime_type($JAGSRequest['file_path']);
								switch ($JAGSReturn['meta']) {
									// run dynamic code
									case 'text/x-php':
									    // external php scripts must be packed in a try/catch block, to prevent server crashes
										try {
										    $JAGSReturn['meta'] = 'text/gemini'; // overwrite data type
										    ob_start(); // turns on output buffering
											include $JAGSRequest['file_path']; // output goes only to buffer
										    if (empty($JAGSReturn['content'])) { // check if the include filled the content already
											    $JAGSReturn['content'] = ob_get_contents(); // stores buffer contents to the variable
										    }
										    $JAGSReturn['file_size'] = strlen($JAGSReturn['content']);
										    ob_end_clean();
										} catch (\Throwable $e) {
										    $JAGSReturn['status_code'] = '40';
										    $JAGSReturn['meta'] = '';
											$this->log("error", "Exception on running dynamic code (" . $JAGSRequest['file_path'] . "): \n" . $e->getMessage() . "\n" . $e->getTraceAsString());
										}
										break;
									// serve other stuff directly
									default:
										$JAGSReturn['content']   = file_get_contents($JAGSRequest['file_path']);
										$JAGSReturn['file_size'] = filesize($JAGSRequest['file_path']);
										break;
								}
							} else {
								$JAGSReturn['meta'] = "Not found";
							}
				
							fputs($forkedSocket, $JAGSReturn['status_code'] . " " . $JAGSReturn['meta'] . "\r\n" ?: false);										
							if (!empty($JAGSReturn['content'])) {
								fputs($forkedSocket, $JAGSReturn['content']);	
							}
				
							if ($this->config['logging']) {
								$this->log("access", $remoteIP, $JAGSReturn['status_code'], $JAGSReturn['meta'], $JAGSRequest['file_path'], $JAGSReturn['file_size']);
							}
							
							fflush($forkedSocket); 
						} else {
							$lastError = error_get_last();
							if ($this->config['logging']) {
								$this->log("error", "Can't establish connection. check configuration. (" . ($lastError ? $lastError['message'] . "/" . $lastError['line'] : "" ) . ")");
							}
						}
						fclose($forkedSocket);
					}
				} else {
					// garbage collector 1
					$data = fread($modifiedRead, 1024);
	                if (strlen($data) === 0 || $data === false) {
	                    // connection closed
	                    $idx = array_search($modifiedRead, $connections, TRUE);
	                    fclose($modifiedRead);
	                    if ($idx != -1) {
	                        unset($connections[$idx]);
	                        $connections = array_merge($connections);
	                    }
	                }
                }
			}

			// garbage collector 2
			foreach ($connections AS $_index => $connectionToCheck) {
				if (get_resource_type($connectionToCheck) !== "stream") {
					unset($connections[$_index]);
	                $connections = array_merge($connections);
				}
			}
		}
	}
	
	/**
	 * deletes log files that are older than in $this->config['log_delete_after'] defined
	 */
	public function rotate_logs()
	{
		$toDeleteAfterTs = strtotime("-" . $this->config['log_delete_after']);
		$log_list = scandir($this->config['work_dir'] . "/" . $this->config['log_dir']);
		
		foreach($log_list AS $log_filename) {
			if (substr($log_filename, -4, 4) === ".log") {
				if (filectime($this->config['work_dir'] . "/" . $this->config['log_dir'] . "/" . $log_filename) < $toDeleteAfterTs) {
					unlink($this->config['work_dir'] . "/" . $this->config['log_dir'] . "/" . $log_filename);
				}
			}	
		}
		
	}
}

