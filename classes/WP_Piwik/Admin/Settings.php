<?php

	namespace WP_Piwik\Admin;
	
	class Settings extends WP_Piwik\Admin {

		public function show() {
			self::$wpPiwik->showSettings();
		}
		
		public function printAdminScripts() {
			wp_enqueue_script('jquery');
		}
		
		public function extendAdminHeader() {
			echo '<script type="text/javascript">var $j = jQuery.noConflict();</script>';
			echo '<script type="text/javascript">/* <![CDATA[ */(function() {var s = document.createElement(\'script\');var t = document.getElementsByTagName(\'script\')[0];s.type = \'text/javascript\';s.async = true;s.src = \'//api.flattr.com/js/0.6/load.js?mode=auto\';t.parentNode.insertBefore(s, t);})();/* ]]> */</script>';		
		}
		
	}