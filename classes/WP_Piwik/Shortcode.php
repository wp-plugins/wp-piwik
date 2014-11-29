<?php
	
	namespace WP_Piwik;
	
	class Shortcode {
		
		private $available = array(
			'opt-out' => 'OptOut',
			'post' => 'Post',
			'overview' => 'Overview'
		);
		
		public function construct($attributes) {
			$class = 'WP_Piwik'.NAMESPACE_SEPARATOR.'Shortcode'.NAMESPACE_SEPARATOR;
		}
		
	}