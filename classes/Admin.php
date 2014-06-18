<?php

	abstract class WP_Piwik_Admin {
		
		protected static $logger, $settings, $wpPiwik, $pageID;
		
		public function __construct($config) {
			self::$logger = $config['logger'];
			self::$settings = $config['settings'];
			self::$wpPiwik = $config['wp_piwik'];
		}

		public function add($pageID) {
			self::$pageID = $pageID;
			add_action('admin_head-'.self::$pageID, array($this, 'extendAdminHeader'));
			add_action('admin_print_scripts-'.self::$pageID, array($this, 'printAdminScripts'));
			add_action('admin_print_styles-'.self::$pageID, array($this, 'printAdminStyles'));
			add_action('load-'.self::$pageID, array($this, 'onLoad'));
		}

		abstract public function show();
		
		abstract public function printAdminScripts();
				
		abstract public function extendAdminHeader();

		public function printAdminStyles() {
			wp_enqueue_style('wp-piwik', self::$wpPiwik->getPluginURL().'css/wp-piwik.css', array(), self::$wpPiwik->getPluginVersion());
		}
		
		public function onLoad() {}

	}