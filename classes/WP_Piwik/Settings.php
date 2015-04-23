<?php

	namespace WP_Piwik;

	class Settings {
		
		private static $wpPiwik, $logger, $defaultSettings;
		
		private $checkSettings = array(
			'piwik_url' => 'checkPiwikUrl',
			'piwik_token' => 'checkPiwikToken',
			'tracking_code' => 'prepareTrackingCode',
			'noscript_code' => 'prepareNocscriptCode',
		);
		
		private $globalSettings = array(
			// Plugin settings
			'revision' => 0,
			'last_settings_update' => 0,
			// User settings: Piwik configuration
			'piwik_mode' => 'http',
			'piwik_url' => '',
			'piwik_path' => '',
			'piwik_user' => '',
			'piwik_token' => '',
			'auto_site_config' => true,
			// User settings: Stats configuration
			'default_date' => 'yesterday',
			'stats_seo' => false,			
			'dashboard_widget' => false,
			'dashboard_chart' => false,
			'dashboard_seo' => false,
			'toolbar' => false,
			'capability_read_stats' => array('administrator' => true),
			'perpost_stats' => false,
			'plugin_display_name' => 'WP-Piwik',
			'piwik_shortcut' => false,
			'shortcodes' => false,
			// User settings: Tracking configuration
			'track_mode' => 'disabled',
			'track_codeposition' => 'footer',			
			'track_noscript' => false,
			'track_nojavascript' => false,
			'track_search' => false,
			'track_404' => false,
			'add_post_annotations' => false,
			'add_customvars_box' => false,
			'add_download_extensions' => '',
			'disable_cookies' => false,
			'limit_cookies' => false,
			'limit_cookies_visitor' => 1209600,
			'limit_cookies_session' => 0,
			'track_admin' => false,
			'capability_stealth' => array(),
			'track_across' => false,
			'track_across_alias' => false,
			'track_feed' => false,
			'track_feed_addcampaign' => false,
			'track_feed_campaign' => 'feed',
			// User settings: Expert configuration
			'cache' => true, //OK
			'disable_timelimit' => false,
			'connection_timeout' => 5,
			'disable_ssl_verify' => false,
			'piwik_useragent' => 'php',
			'piwik_useragent_string' => 'WP-Piwik',
			'track_datacfasync' => false,
			'track_cdnurl' => '',
			'track_cdnurlssl' => '',
			'force_protocol' => 'disabled',
		),
		$settings = array(
			'name' => '',
			'site_id' => NULL,
			'noscript_code' => '',
			'tracking_code' => '',
			'last_tracking_code_update' => 0,
			'dashboard_revision' => 0
		),
		$settingsChanged = false;
	
		public function __construct($wpPiwik, $logger) {
			self::$wpPiwik = $wpPiwik;
			self::$logger = $logger;
			self::$logger->log('Store default settings');
			self::$defaultSettings = array('globalSettings' => $this->globalSettings, 'settings' => $this->settings);
			self::$logger->log('Load settings');
			foreach ($this->globalSettings as $key => $default) {
				$this->globalSettings[$key] = ($this->checkNetworkActivation()?
					get_site_option('wp-piwik_global-'.$key, $default):
					get_option('wp-piwik_global-'.$key, $default)
				);
			}
			foreach ($this->settings as $key => $default)
				$this->settings[$key] = get_option('wp-piwik-'.$key, $default);
		}
		
		public function save() {
			if (!$this->settingsChanged) {
				self::$logger->log('No settings changed yet');
				return;
			}
			self::$logger->log('Save settings');
			foreach ($this->globalSettings as $key => $value) {
				if (is_plugin_active_for_network('wp-piwik/wp-piwik.php'))
					update_site_option('wp-piwik_global-'.$key, $value);
				else
					update_option('wp-piwik_global-'.$key, $value);
			}
			foreach ($this->settings as $key => $value) {
				update_option('wp-piwik-'.$key, $value);
			}
			global $wp_roles;
			if (!is_object($wp_roles))
				$wp_roles = new \WP_Roles();
			if (!is_object($wp_roles)) die("STILL NO OBJECT");
			foreach($wp_roles->role_names as $strKey => $strName)  {
				$objRole = get_role($strKey);
				foreach (array('stealth', 'read_stats') as $strCap) {
					$aryCaps = $this->getGlobalOption('capability_'.$strCap);
					if (isset($aryCaps[$strKey]) && $aryCaps[$strKey])
						$objRole->add_cap('wp-piwik_'.$strCap);
					else $objRole->remove_cap('wp-piwik_'.$strCap);
				}
			}
			$this->settingsChanges = false;
		}

		public function getGlobalOption($key) {
			return isset($this->globalSettings[$key])?$this->globalSettings[$key]:self::$defaultSettings['globalSettings'][$key];
		}	

		public function getOption($key, $blogID = null) {
			if ($this->checkNetworkActivation() && !empty($blogID)) {
				return get_blog_option($blogID, $key);
			}
			return isset($this->settings[$key])?$this->settings[$key]:self::$defaultSettings['settings'][$key];
		}	

		public function setGlobalOption($key, $value) {
			$this->settingsChanged = true;
			self::$logger->log('Changed global option '.$key.': '.(is_array($value)?serialize($value):$value));		
			$this->globalSettings[$key] = $value;
		}	

		public function setOption($key, $value, $blogID = null) {
			$this->settingsChanged = true;
			self::$logger->log('Changed option '.$key.': '.$value);
			if ($this->checkNetworkActivation() && !empty($blogID)) {
				add_blog_option($blogID, $key, $value);
			}
			else $this->settings[$key] = $value;
		}
		
		public function resetSettings($bolFull = false) {
			self::$logger->log('Reset WP-Piwik settings');
			global $wpdb;
			$keepSettings = array(
				'piwik_token' => $this->getGlobalOption('piwik_token'),
				'piwik_url' => $this->getGlobalOption('piwik_url'),
				'piwik_path' => $this->getGlobalOption('piwik_path'),
				'piwik_mode' => $this->getGlobalOption('piwik_mode')
			);
			if (is_plugin_active_for_network('wp-piwik/wp-piwik.php')) {
				delete_site_option('wp-piwik_global-settings');
				$aryBlogs = $wpdb->get_results('SELECT blog_id FROM '.$wpdb->blogs.' ORDER BY blog_id');
				foreach ($aryBlogs as $aryBlog)
					foreach ($this->settings as $key => $value)
						delete_blog_option($aryBlog->blog_id, 'wp-piwik-'.$key);
				if (!$bolFull) update_site_option('wp-piwik_global-settings', $keepSettings);
			} else {
				foreach ($this->globalSettings as $key => $value)
					delete_option('wp-piwik_global-'.$key);
				foreach ($this->settings as $key => $value)
					delete_option('wp-piwik-'.$key);
			}
			$this->globalSettings = self::$defaultSettings['globalSettings'];
			$this->settings = self::$defaultSettings['settings'];
			if (!$bolFull) {
				self::$logger->log('Restore connection settings');
				foreach ($keepSettings as $key => $value)
					$this->setGlobalOption($key, $value);
			}
			$this->save();
		}
		
		public function checkNetworkActivation() {
			if (!function_exists("is_plugin_active_for_network"))
				require_once(ABSPATH.'wp-admin/includes/plugin.php');
			return is_plugin_active_for_network('wp-piwik/wp-piwik.php');
		}
		
		private function applyGlobalOption($id, $value) {
			self::$logger->log('Set '.$id.': '.serialize($this->getGlobalOption($id)).' - '.serialize($value));
			$this->setGlobalOption($id, $value);
		}

		private function applyOption($id, $value) {
			self::$logger->log('Set '.$id.': '.serialize($this->getOption($id)).' - '.serialize($value));
			$this->setOption($id, $value);
		}
		
		public function applyChanges($in) {
			$in = $this->checkSettings($in);
			self::$logger->log('Apply changed settings:');
			foreach (self::$defaultSettings['globalSettings'] as $key => $val)
				$this->applyGlobalOption($key, isset($in[$key]) ? $in[$key]:$val);
			foreach (self::$defaultSettings['settings'] as $key => $val)
				$this->applyOption($key, isset($in[$key]) ? $in[$key]:$val);
			$this->setGlobalOption('last_settings_update', time());
			$this->save();
		}

		public static function registerSettings() {
			$class = 'WP_Piwik\Settings';
			$stringValidator = array($class, 'validateString');
			$n = 'wp-piwik';
			$g = 'wp-piwik_global-';
			register_setting($n, $g.'piwik_token', $stringValidator);
			register_setting($n, $g.'piwik_url', $stringValidator);
		}
		
		public static function validateString($value) {
			return $value;
		}
		
		private function checkSettings($in) {
			foreach ($this->checkSettings as $key => $value)
				if (isset($in[$key]))
					$in[$key] = call_user_func_array(array($this, $value), array($in[$key], $in));
			return $in;
		}
		
		private function checkPiwikUrl($value, $in) {
			return substr($value,-1,1) != '/'?$value.'/':$value;			
		}
		
		private function checkPiwikToken($value, $in) {
			return str_replace('&token_auth=', '', $value);
		}
		
		private function prepareTrackingCode($value, $in) {
			if ($in['track_mode'] == 'manually' || $in['track_mode'] == 'disabled') {
				$value = stripslashes($value);
				return $value;
			}
			$result = self::$wpPiwik->updateTrackingCode();
			$this->setOption('noscript_code', $result['noscript']);
			return $result['script'];
		}
		
		private function prepareNocscriptCode($value, $in) {
			if ($in['track_mode'] == 'manually')
				return stripslashes($value);
			return $value;
		}
		
	}