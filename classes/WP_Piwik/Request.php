<?php

	abstract class WP_Piwik_Request {
		
		protected static $wpPiwik, $requests = array(), $results = array();
		
		public function __construct($wpPiwik) {
			self::$wpPiwik = $wpPiwik;
		}
		
		public static function register($method, $parameter) {
			$id = $method.'?'.self::parameterToString($parameter);
			if (!isset(self::$requests[$id]))
				self::$requests[$id] = array('method' => $method, 'parameter' => $parameter);
			return $id;
		}
		
		abstract function show();
		
		private static function parameterToString($parameter) {
			$return = '';
			if (is_array($parameter))
				foreach ($parameter as $key => $value)
					$return .= '&'.$key.'='.$value;
			return $return;
		}
		
	}