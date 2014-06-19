<?php

	abstract class WP_Piwik_Request {
		
		protected static $wpPiwik, $settings, $requests = array(), $results = array();
		
		public function __construct($wpPiwik, $settings) {
			self::$wpPiwik = $wpPiwik;
			self::$settings = $settings;
		}
		
		public static function register($method, $parameter) {
			$id = $method.'?'.self::parameterToString($parameter);
			if (!isset(self::$requests[$id]))
				self::$requests[$id] = array('method' => $method, 'parameter' => $parameter);
			return $id;
		}
		
		private static function parameterToString($parameter) {
			$return = '';
			if (is_array($parameter))
				foreach ($parameter as $key => $value)
					$return .= '&'.$key.'='.$value;
			return $return;
		}
		
		public function perform($id) {
			if (!isset(self::$requests[$id]))
				return array('result' => 'error', 'message' => 'Request '.$id.' was not registered.');
			elseif (!isset(self::$results[$id])) {
				$this->request($id);
			}
			return self::$results[$id];
		}
		
		protected function buildURL($config) {
			$url = 'method='.urlencode($config['method']).'&idSite='.self::$settings->getOption('site_id');
			foreach ($config['parameter'] as $key => $value)
				$url .= '&'.$key.'='.urlencode($value);
			return $url;
		}
		
		abstract protected function request($id);
			
	}