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
	private $version = "202102_1";
	
	public function __construct(array $config)
	{
		// set default config and overwrite with custom settings:
		$this->config = array_merge(
			[
				'ip' 						=> "0",
				'port' 						=> "1965",
				'work_dir'					=> realpath(dirname(__FILE__) . "/.."),
				'host_dir' 					=> "default",
				'log_dir' 					=> "logs",
				'default_index_file' 		=> "index.gemini",
				'certificate_file'			=> "",
				'certificate_passphrase'	=> "",
				'ssl_verify_peer'			=> false,
				'ssl_capture_peer_cert'		=> false,
				'logging' 					=> true,
				'log_sep' 					=> "\t",
				'log_delete_after'			=> "30days"
			],
			$config
		);
		
		// check for certificate
		if (!isset($this->config['certificate_file']) || !is_readable($this->config['work_dir'] . "/" . $this->config['certificate_file'])) {
			die("ERROR: Certificate file (" . $this->config['certificate_file'] . ") not readable.\n");
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
	public function log ($ip, $status_code = "", $meta = "", $filepath = "", $filesize = ""): bool
	{
		$log_file = $this->config['work_dir'] . "/" . $this->config['log_dir'] . "/" . date("Y-m-d") . "_" . $this->config['host_dir'] . ".log";
		$str = date("Y-m-d H:i:s") . $this->config['log_sep'] . (microtime(true) * 10000) . $this->config['log_sep'] . $ip . $this->config['log_sep'] . $status_code . $this->config['log_sep'] . $meta.$this->config['log_sep'] . $filepath . $this->config['log_sep'] . $filesize . "\n";
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

		// make it possible to get rid of .gemini extensions
		if (is_file($this->config['work_dir'] . "/hosts/" . $this->config['host_dir'] . $JAGSRequest['path'] . ".gemini")) {
			$JAGSRequest['path'] .= ".gemini";
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
		if (is_file($this->config['work_dir'] . "/hosts/" . $this->config['host_dir'] . "/" . $explodedPath[1] . ".php")) {
			$JAGSRequest['path'] = "/" . $explodedPath[1] . ".php";
			$pathParams = [];
			foreach($explodedPath AS $_index => $param) {
				if (!in_array($_index, [0,1])) {
					$pathParams[] = urldecode($param);
				}
			}
			$JAGSRequest['query'] = implode("&", $pathParams) . (!empty($JAGSRequest['query']) ? ("&" . $JAGSRequest['query']) : "");
		}	

		// fill $_GET and $parsed_url['get']:
		if (!empty($JAGSRequest['query'])) {
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
		$JAGSRequest['file_path'] = $this->config['work_dir'] . "/hosts/" . $this->config['host_dir'] . $JAGSRequest['path'];

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
		$this->log("JAGS version " . $this->version . " started");
		
		$context = stream_context_create();

		stream_context_set_option($context, 'ssl', 'local_cert', $this->config['certificate_file']);
		stream_context_set_option($context, 'ssl', 'passphrase', $this->config['certificate_passphrase']);
		stream_context_set_option($context, 'ssl', 'allow_self_signed', true);
		stream_context_set_option($context, 'ssl', 'verify_peer', $this->config['ssl_verify_peer']);
		stream_context_set_option($context, 'ssl', 'capture_peer_cert', $this->config['ssl_capture_peer_cert']);
		
		$socket = stream_socket_server("tcp://" . $this->config['ip'] . ":" . $this->config['port'], $errno, $errstr, STREAM_SERVER_BIND|STREAM_SERVER_LISTEN, $context);
		
		// apply patch from @nervuri:matrix.org to stop supporting out of spec versions of TLS
		$cryptoMethod = STREAM_CRYPTO_METHOD_TLS_SERVER
			& ~ STREAM_CRYPTO_METHOD_TLSv1_0_SERVER
			& ~ STREAM_CRYPTO_METHOD_TLSv1_1_SERVER;

		while(true) {
			$forkedSocket = @stream_socket_accept($socket, "-1", $remoteIP);
			if (!is_bool($forkedSocket)) {
				stream_set_blocking($forkedSocket, true);
				$enableCryptoReturn = @stream_socket_enable_crypto($forkedSocket, true, $cryptoMethod);
				if ($enableCryptoReturn === true) {
					$line = fread($forkedSocket, 1024);
					stream_set_blocking($forkedSocket, false);
		
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
								$JAGSReturn['meta'] = 'text/gemini'; // overwrite data type
								ob_start(); // turns on output buffering
								include $JAGSRequest['file_path']; // output goes only to buffer
								if (empty($JAGSReturn['content'])) { // check if the include filled the content already
									$JAGSReturn['content'] = ob_get_contents(); // stores buffer contents to the variable
								}
								$JAGSReturn['file_size'] = strlen($JAGSReturn['content']);
								ob_end_clean();
								break;
							// serve other stuff directly
							default:
								$JAGSReturn['file_size'] = filesize($JAGSRequest['file_path']);
								$JAGSReturn['content'] = file_get_contents($JAGSRequest['file_path']);
								break;
						}
					} else {
						$JAGSReturn['meta'] = "Not found";
					}
		
					if ($this->config['logging']) {
						$this->log($remoteIP, $JAGSReturn['status_code'], $JAGSReturn['meta'], $JAGSRequest['file_path'], $JAGSReturn['file_size']);
					}
					
					fwrite($forkedSocket, $JAGSReturn['status_code'] . " " . $JAGSReturn['meta'] . "\r\n" . $JAGSReturn['content'] ?: false);
				} else {
					if ($this->config['logging']) {
						$this->log("ERROR: can't establish connection. check configuration.");
					}
				}
				fclose($forkedSocket);
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
