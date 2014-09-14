<?php

	class WP_Piwik_Request_Php extends WP_Piwik_Request {
			
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
			$results = $this->call($url, $params);
			foreach ($results as $num => $result)
				self::$results[$map[$num]] = $result;
		}
			
		private function call($url, $params) {
			if (!defined('PIWIK_INCLUDE_PATH'))
				return;
			if (PIWIK_INCLUDE_PATH === FALSE)
				return serialize(array('result' => 'error', 'message' => __('Could not resolve','wp-piwik').' &quot;'.htmlentities(self::$settings->getGlobalOption('piwik_path')).'&quot;: '.__('realpath() returns false','wp-piwik').'.'));
			if (!headers_sent()) {
				$current = ob_get_contents();
				ob_end_clean();
				ob_start();
			}
			if (file_exists(PIWIK_INCLUDE_PATH . "/index.php"))
				require_once PIWIK_INCLUDE_PATH . "/index.php";
			if (file_exists(PIWIK_INCLUDE_PATH . "/core/API/Request.php"))
				require_once PIWIK_INCLUDE_PATH . "/core/API/Request.php";
			if (class_exists('Piwik\FrontController'))
				Piwik\FrontController::getInstance()->init();
			else serialize(array('result' => 'error', 'message' => __('Class Piwik\FrontController does not exists.','wp-piwik')));
			if (class_exists('Piwik\API\Request'))
				$request = new Piwik\API\Request($strParams);
			else serialize(array('result' => 'error', 'message' => __('Class Piwik\API\Request does not exists.','wp-piwik')));
			if (!headers_sent()) {
				ob_end_clean();
				ob_start;
				echo $current;
			}
			$result = $request->process();	
			return $this->unserialize($result);				
		}
	}