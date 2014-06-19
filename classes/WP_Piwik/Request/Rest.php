<?php

	class WP_Piwik_Request_Rest extends WP_Piwik_Request {
			
		protected function request($id) {
			$count = 0;
			$url = self::$settings->getGlobalOption('piwik_url');
			$params = 'module=API&method=API.getBulkRequest&format=php';
			foreach (self::$requests as $requestID => $config) {
				if (!isset(self::$results[$requestID])) {
					$params .= '&urls['.$count.']='.urlencode($this->buildURL($config));
					$map[$count] = $requestID;
					$count++;
				}
			}
			$results = (function_exists('curl_init')?$this->curl($url, $params):$this->fopen($url, $params));
			foreach ($results as $num => $result)
				self::$results[$map[$num]] = $result;
		}
			
		private function curl($url, $params) {
			$c = curl_init($url);
			curl_setopt($c, CURLOPT_POST, 1);
			curl_setopt($c, CURLOPT_POSTFIELDS, $params.'&token_auth='.self::$settings->getGlobalOption('piwik_token'));
			curl_setopt($c, CURLOPT_SSL_VERIFYPEER, !self::$settings->getGlobalOption('disable_ssl_verify'));
			curl_setopt($c, CURLOPT_USERAGENT, self::$settings->getGlobalOption('piwik_useragent')=='php'?ini_get('user_agent'):self::$settings->getGlobalOption('piwik_useragent_string'));
			curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($c, CURLOPT_HEADER, 0);
			curl_setopt($c, CURLOPT_TIMEOUT, self::$settings->getGlobalOption('connection_timeout'));
			$httpProxyClass = new WP_HTTP_Proxy();
			if ($httpProxyClass->is_enabled() && $httpProxyClass->send_through_proxy($strURL)) {
				curl_setopt($c, CURLOPT_PROXY, $httpProxyClass->host());
				curl_setopt($c, CURLOPT_PROXYPORT, $httpProxyClass->port());
				if ($httpProxyClass->use_authentication())
					curl_setopt($c, CURLOPT_PROXYUSERPWD, $httpProxyClass->username().':'.$httpProxyClass->password());
			}
			$result = curl_exec($c);
			curl_close($c);
			return unserialize($result);				
		}
			
		private function fopen($url, $params) {
			$context = stream_context_create(array('http'=>array('timeout' => self::$settings->getGlobalOption('connection_timeout'))));
			$result = @file_get_contents($url.'?'.$params.'&token_auth='.self::$settings->getGlobalOption('piwik_token'), false, $context);
			return unserialize($result);
		}
	}