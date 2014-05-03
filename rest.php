		$strPiwikURL = self::$settings->getGlobalOption('piwik_url');
		if (substr($strPiwikURL, -1, 1) != '/') $strPiwikURL .= '/';
		$strURL = $strPiwikURL.'?module=API'.$strURL;
		// Use cURL if available	
		if (function_exists('curl_init')) {
			// Init cURL
			$c = curl_init($strURL);
			// Disable SSL peer verification if asked to
			curl_setopt($c, CURLOPT_SSL_VERIFYPEER, !self::$settings->getGlobalOption('disable_ssl_verify'));
			// Set user agent
			curl_setopt($c, CURLOPT_USERAGENT, self::$settings->getGlobalOption('piwik_useragent')=='php'?ini_get('user_agent'):self::$settings->getGlobalOption('piwik_useragent_string'));
			// Configure cURL CURLOPT_RETURNTRANSFER = 1
			curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
			// Configure cURL CURLOPT_HEADER = 0 
			curl_setopt($c, CURLOPT_HEADER, 0);
			// Set cURL timeout
			curl_setopt($c, CURLOPT_TIMEOUT, self::$settings->getGlobalOption('connection_timeout'));
			$httpProxyClass = new WP_HTTP_Proxy();
			if ($httpProxyClass->is_enabled() && $httpProxyClass->send_through_proxy($strURL)) {
				curl_setopt($c, CURLOPT_PROXY, $httpProxyClass->host());
				curl_setopt($c, CURLOPT_PROXYPORT, $httpProxyClass->port());
				if ($httpProxyClass->use_authentication())
					curl_setopt($c, CURLOPT_PROXYUSERPWD, $httpProxyClass->username().':'.$httpProxyClass->password());
			}
			// Get result
			$strResult = curl_exec($c);
			// Close connection			
			curl_close($c);
		// cURL not available but url fopen allowed
		} elseif (ini_get('allow_url_fopen')) {
			// Set timeout
			$resContext = stream_context_create(array('http'=>array('timeout' => self::$settings->getGlobalOption('connection_timeout'))));
			// Get file using file_get_contents
			$strResult = @file_get_contents($strURL, false, $strContext);
		// Error: Not possible to get remote file
		} else $strResult = serialize(array(
				'result' => 'error',
				'message' => 'Remote access to Piwik not possible. Enable allow_url_fopen or CURL.'
			));
		// Return result