<?php
/*
Plugin Name: WP-Piwik

Plugin URI: http://www.braekling.de/wp-piwik-wpmu-piwik-wordpress/

Description: Adds Piwik stats to your dashboard menu and Piwik code to your wordpress footer.

Version: 0.8.10
Author: Andr&eacute; Br&auml;kling
Author URI: http://www.braekling.de

****************************************************************************************** 
	Copyright (C) 2009-2011 Andre Braekling (email: webmaster@braekling.de)

	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.
*******************************************************************************************/

/* Avoid direct calls to this file if wp core files not present
   seen (as some other parts) in Heiko Rabe's metabox demo plugin 
   http://tinyurl.com/5r5vnzs */
if (!function_exists ('add_action')) {
	header('Status: 403 Forbidden');
	header('HTTP/1.1 403 Forbidden');
	exit();
}

// Change this to enable *experimental* multisite-mode
$GLOBALS['wp-piwik_wpmu'] = false;

class wp_piwik {

	private static
		$intRevisionId = 81000,
		$strVersion = '0.8.10',
		$intDashboardID = 30,
		$bolWPMU = false,
		$bolOverall = false,
		$strPluginBasename = NULL,
		$aryGlobalSettings = array(
			'revision' => 0,
			'add_tracking_code' => false,
			'last_settings_update' => 0,
			'piwik_token' => '',
			'piwik_url' => '',
			'dashboard_widget' => false,
			'dashboard_chart' => false,
			'capability_stealth' => array(),
			'capability_read_stats' => array('administrator' => true),
			'piwik_shortcut' => false,
			'default_date' => 'yesterday'
		),
		$arySettings = array(
			'tracking_code' => '',
			'site_id' => NULL,
			'track_404' => false,
			'last_tracking_code_update' => 0,
			'dashboard_revision' => 0
		);
		
	private
		$intStatsPage = NULL;

	/**
	 * Load plugin settings 
	 */
	static function loadSettings() {		
		// Running as multisite?
		if (isset($GLOBALS['wp-piwik_wpmu']) && $GLOBALS['wp-piwik_wpmu']) self::$bolWPMU = true;
		// Get global settings depending on mode
		self::$aryGlobalSettings = 
			(self::$bolWPMU?
				get_site_option('wpmu-piwik_global-settings',self::$aryGlobalSettings):
				get_option('wp-piwik_global-settings',self::$aryGlobalSettings)
			);
		// Get mode-independent settings
		self::$arySettings = get_option('wp-piwik_settings',self::$arySettings);
	}
	
	/**
	 * Save plugin settings 
	 */
	static function saveSettings() {
		// Save global settings depending on mode
		if (self::$bolWPMU) update_site_option('wpmu-piwik_global-settings',self::$aryGlobalSettings);
		else update_option('wp-piwik_global-settings',self::$aryGlobalSettings);
		// Save mode-independent settings
		update_option('wp-piwik_settings',self::$arySettings);
		// Assign capabilities to roles
		global $wp_roles;
		if (is_object($wp_roles))
			foreach($wp_roles->role_names as $strKey => $strName)  {
				$objRole = get_role($strKey);
				foreach (array('stealth', 'read_stats') as $strCap)
					if (isset(self::$aryGlobalSettings['capability_'.$strCap][$strKey]) && self::$aryGlobalSettings['capability_'.$strCap][$strKey])
						$objRole->add_cap('wp-piwik_'.$strCap);
					else 
						$objRole->remove_cap('wp-piwik_'.$strCap);
			}
	}
	
	/**
	 * Constructor
	 */
	function __construct() {
		// Store plugin basename
		self::$strPluginBasename = plugin_basename(__FILE__);
		// Load current settings
		self::loadSettings();
		// Upgrade?
		if (self::$aryGlobalSettings['revision'] < self::$intRevisionId) $this->install();
		// Settings changed?
		if (isset($_POST['action']) && $_POST['action'] == 'save_settings')
			$this->applySettings();
		// Load language file
		load_plugin_textdomain('wp-piwik', false, dirname(self::$strPluginBasename)."/languages/");
		// Call install function on activation
		register_activation_hook(__FILE__, array($this, 'install'));
		// Add meta links to plugin details
		if (!self::$bolWPMU) add_filter('plugin_row_meta', array($this, 'setPluginMeta'), 10, 2);
		// Register columns
		//add_filter('screen_layout_columns', array(&$this, 'onScreenLayoutColumns'), 10, 2);
		// Add admin menu
		add_action('admin_menu', array($this, 'buildAdminMenu'));
		// Register the callback been used if options of page been submitted and needs to be processed
		add_action('admin_post_save_wp-piwik_stats', array(&$this, 'onStatsPageSaveChanges'));
		// Add dashboard widget if enabled
		if (self::$aryGlobalSettings['dashboard_widget'] || self::$aryGlobalSettings['dashboard_chart'])
			add_action('wp_dashboard_setup', array($this, 'extend_wp_dashboard_setup'));		
		// Add tracking code to footer if enabled
		if (self::$aryGlobalSettings['add_tracking_code']) add_action('wp_footer', array($this, 'footer'));
	}

	/**
	 * Destructor
	 */
	function __destruct() {}
	
	/**
	 * Install or upgrade
	 */
	function install() {
		// Update: Translate options
		if (self::$aryGlobalSettings['revision'] < 80403) {
			// Capability read stats: Translate level to role
			$aryTranslate = array(
				'level_10' => array('administrator' => true),
				'level_7' => array('editor' => true, 'administrator' => true),
				'level_2' => array('author' => true, 'editor' => true, 'administrator' => true),
				'level_1' => array('contributor' => true, 'author' => true, 'editor' => true, 'administrator' => true),
				'level_0' => array('subscriber' => true, 'contributor' => true, 'author' => true, 'editor' => true, 'administrator' => true)
			);
			$strDisplayToLevel = get_option('wp-piwik_displayto','level_10');
			if (!is_array($strDisplayToLevel) && isset($aryTranslate[$strDisplayToLevel])) $aryDisplayToCap = $aryTranslate[$strDisplayToLevel];
			else $aryDisplayToCap = array('administrator' => true);
			// Build settings arrays
			$aryDashboardWidgetRange = array(0 => false, 1 => 'yesterday', 2 => 'today', 3 => 'last30');
			if (self::$bolWPMU) self::$aryGlobalSettings = array(
				'revision' 				=> get_site_option('wpmu-piwik_revision', 0),
				'add_tracking_code' 	=> true,
				'last_settings_update' 	=> get_site_option('wpmu-piwik_settingsupdate', time()),
				'piwik_token' 		=> get_site_option('wpmu-piwik_token', ''),
				'piwik_url'		=> get_site_option('wpmu-piwik_url', ''),
				'dashboard_widget' 	=> false,
				'capability_stealth' 	=> get_site_option('wpmu-piwik_filter', array()),
				'capability_read_stats' => $aryDisplayToCap,
				'piwik_shortcut' 	=> false,
			);		
			else self::$aryGlobalSettings = array(
				'revision' 		=> get_option('wp-piwik_revision',0),
				'add_tracking_code' 	=> get_option('wp-piwik_addjs'),
				'last_settings_update' 	=> get_option('wp-piwik_settingsupdate', time()),
				'piwik_token' 		=> get_option('wp-piwik_token', ''),
				'piwik_url' 		=> get_option('wp-piwik_url', ''),
				'dashboard_widget' 	=> $aryDashboardWidgetRange[get_option('wp-piwik_dbwidget', 0)],			
				'capability_stealth' 	=> get_option('wp-piwik_filter', array()),
				'capability_read_stats' => $aryDisplayToCap,
				'piwik_shortcut' 	=> get_option('wp-piwik_piwiklink',false),
			);
			$this->installSite(false);
			// Remove deprecated option values
			$aryRemoveOptions = array(
				'wp-piwik_disable_gapi','wp-piwik_displayto',
				'wp-piwik_revision','wp-piwik_addjs','wp-piwik_settingsupdate','wp-piwik_token',
				'wp-piwik_url','wp-piwik_dbwidget','wp-piwik_filter','wp-piwik_piwiklink'
			);
			foreach ($aryRemoveOptions as $strRemoveOption) {				
				if (self::$bolWPMU) delete_site_option($strRemoveOption);
				else delete_option($strRemoveOption);
			}			
		};
		if (self::$aryGlobalSettings['revision'] < 80502) {
			self::$aryGlobalSettings['default_date'] = 'yesterday';
		}
		if (self::$aryGlobalSettings['revision'] < 80602) {
			self::$aryGlobalSettings['dashboard_chart'] = false;
		}
		if (self::$aryGlobalSettings['revision'] < 80800) {
			self::$aryGlobalSettings['piwik_url'] = self::check_url(self::$aryGlobalSettings['piwik_url']);
		}
		add_action('admin_footer', array($this, 'updateMessage'));
		// Set current revision ID 
		self::$aryGlobalSettings['revision'] = self::$intRevisionId;
		self::$aryGlobalSettings['last_settings_update'] = time();
		// Save upgraded or default settings
		self::saveSettings();
		// Reload settings
		self::loadSettings();
	}

	/**
	 * Install or upgrade site settings
	 */
	function installSite($bolSave = true) {
		self::$arySettings = array(
			'tracking_code' => '',
			'site_id' => get_option('wp-piwik_siteid', NULL),
			'track_404' => get_option('wp-piwik_404', false),
			'last_tracking_code_update' => get_option('wp-piwik_scriptupdate', 0),
			'dashboard_revision' => get_option('wp-piwik_dashboardid', 0)
		);
			
		// Remove deprecated option values
		$aryRemoveOptions = array(
			'wp-piwik_siteid','wp-piwik_404','wp-piwik_scriptupdate','wp-piwik_dashboardid','wp-piwik_jscode'
		);
		foreach ($aryRemoveOptions as $strRemoveOption) {
			delete_option($strRemoveOption);
		};
		if ($bolSave) {
			// Save upgraded or default settings
			self::saveSettings();
			// Reload settings
			self::loadSettings();
		}
	}

	/**
	 * Send a message after installing/updating
	 */
	function updateMessage() {
		$strText = 'WP-Piwik '.self::$strVersion.' '.__('installed','wp-piwik').'.';
		$strSettings = (empty(self::$aryGlobalSettings['piwik_token']) && empty(self::$aryGlobalSettings['piwik_url'])?
			__('Next you should connect to Piwik','wp-piwik'):
			__('Please validate your configuration','wp-piwik')
		);
		$strLink = sprintf('<a href="options-general.php?page=%s">%s</a>', self::$strPluginBasename, __('Settings', 'wp-piwik'));
		echo '<div id="message" class="updated fade"><p>'.$strText.' '.$strSettings.': '.$strLink.'.</p></div>';
	}
	
	/**
	 * Add tracking code
	 */
	function footer() {
		// Hotfix: Custom capability problem with WP multisite
		if (self::$bolWPMU) {
			foreach (self::$aryGlobalSettings['capability_stealth'] as $strKey => $strVal)
				if ($strVal && current_user_can($strKey))
					return;
		// Add tracking code?
		} elseif (current_user_can('wp-piwik_stealth')) return;
		// Hotfix: Update WPMU site if not done yet
		if (self::$bolWPMU && get_option('wp-piwik_siteid', false)) $this->installSite();
		// Handle new WPMU site 
		if (self::$bolWPMU && empty(self::$arySettings['tracking_code'])) {
			$aryReturn = $this->create_wpmu_site();
			self::$arySettings['tracking_code'] = $aryReturn['js'];
			self::saveSettings();
		// Handle existing WPMU site		
		} elseif (self::$bolWPMU) {
			if (self::$arySettings['last_tracking_code_update'] < self::$aryGlobalSettings['last_settings_update']) {
				$strJSCode = $this->call_API('SitesManager.getJavascriptTag');
				self::$arySettings['tracking_code'] = html_entity_decode((is_string($strJSCode)?$strJSCode:'<!-- WP-Piwik ERROR: Tracking code not availbale -->'."\n"));
				self::$arySettings['last_tracking_code_update'] = time();
				self::saveSettings();
			}
		// Get code if not known
		} elseif (empty($strJSCode)) {
			$strJSCode = $this->call_API('SitesManager.getJavascriptTag');
			self::$arySettings['tracking_code'] = html_entity_decode((is_string($strJSCode)?$strJSCode:'<!-- WP-Piwik ERROR: Tracking code not availbale -->'."\n"));
            self::saveSettings();
		}
		// Change code if 404
		if (is_404() and self::$arySettings['track_404']) $strTrackingCode = str_replace('piwikTracker.trackPageView();', 'piwikTracker.setDocumentTitle(\'404/URL = \'+encodeURIComponent(document.location.pathname+document.location.search) + \'/From = \' + encodeURIComponent(document.referrer));piwikTracker.trackPageView();', self::$arySettings['tracking_code']);
		else $strTrackingCode = self::$arySettings['tracking_code'];
		// Send tracking code
		echo '<!-- *** WP-Piwik - see http://www.braekling.de/wp-piwik-wpmu-piwik-wordpress/ -->'."\n";
		echo $strTrackingCode;
		echo '<!-- *** /WP-Piwik *********************************************************** -->'."\n";
	}

	/**
	 * Add pages to admin menu
	 */
	function buildAdminMenu() {		
		// Show stats dashboard page if WP-Piwik is configured
		if (!empty(self::$aryGlobalSettings['piwik_token']) && !empty(self::$aryGlobalSettings['piwik_url'])) {
			// Add dashboard page
			$this->intStatsPage = add_dashboard_page(
				__('Piwik Statistics', 'wp-piwik'), 
				__('WP-Piwik', 'wp-piwik'), 
				(!self::$bolWPMU?'wp-piwik_read_stats':'administrator'),
				'wp-piwik_stats',
				array($this, 'showStats')
			);
			// Add required scripts
			add_action('admin_print_scripts-'.$this->intStatsPage, array($this, 'load_scripts'));
			// Add required styles
			add_action('admin_print_styles-'.$this->intStatsPage, array($this, 'add_admin_style'));
			// Add required header tags
			add_action('admin_head-'.$this->intStatsPage, array($this, 'add_admin_header'));
			// Stats page onload callback
			add_action('load-'.$this->intStatsPage, array(&$this, 'onloadStatsPage'));
		}
		// Add options page if not multi-user
		if (!self::$bolWPMU)
			// Add options page
			$intOptionsPage = add_options_page(
				__('WP-Piwik', 'wp-piwik'),
				__('WP-Piwik', 'wp-piwik'), 
				'activate_plugins', 
				__FILE__,
				array($this, 'show_settings')
			);
		// Add options page if multi-user and current user is site admin
		elseif (function_exists('is_super_admin') && is_super_admin())
			// Add options page
			$intOptionsPage = add_options_page(
				__('WPMU-Piwik', 'wpmu-piwik'),
				__('WPMU-Piwik', 'wpmu-piwik'), 
				'manage_sites', 
				__FILE__,
				array($this, 'show_mu_settings')
			);
		else $intOptionsPage = false;
		// Add styles required by options page
		if ($intOptionsPage)
			add_action('admin_print_styles-'.$intOptionsPage, array($this, 'add_admin_style'));
	}

	/* Support two columns 
	   seen in Heiko Rabe's metabox demo plugin 
	   http://tinyurl.com/5r5vnzs */ 
	function onScreenLayoutColumns($aryColumns, $strScreen) {		
		if ($strScreen == $this->intStatsPage)
			$aryColumns[$this->intStatsPage] = 4;
		return $aryColumns;
	}
	
	function extend_wp_dashboard_setup() {
		if (current_user_can('wp-piwik_read_stats')) {
			if (self::$aryGlobalSettings['dashboard_widget'])
				$this->add_wp_dashboard_widget();
			if (self::$aryGlobalSettings['dashboard_chart']) {				
				// Add required scripts
				add_action('admin_print_scripts-index.php', array($this, 'load_scripts'));
				// Add required styles
				add_action('admin_print_styles-index.php', array($this, 'add_admin_style'));
				// Add required header tags
				add_action('admin_head-index.php', array($this, 'add_admin_header'));
				$this->add_wp_dashboard_chart();
			}
		}
	}

	function add_wp_dashboard_widget() {
		$aryConfig = array(
			'params' => array(
            	'period' => 'day',
				'date'  => self::$aryGlobalSettings['dashboard_widget'],
				'limit' => null
			),
			'inline' => true,			
		);
		$strFile = 'overview';
		add_meta_box(
				'wp-piwik_stats-dashboard-overview', 
				__('WP-Piwik', 'wp-piwik').' - '.__(self::$aryGlobalSettings['dashboard_widget'], 'wp-piwik'), 
				array(&$this, 'createDashboardWidget'), 
				'dashboard', 
				'side', 
				'high',
				array('strFile' => $strFile, 'aryConfig' => $aryConfig)
			);
	}
	
	/**
	 * Add a visitor chart to the WordPress dashboard
	 */
	function add_wp_dashboard_chart() {
		$aryConfig = array(
			'params' => array(
            	'period' => 'day',
				'date'  => 'last30',
				'limit' => null
			),
			'inline' => true,			
		);
		$strFile = 'visitors';
		add_meta_box(
				'wp-piwik_stats-dashboard-chart', 
				__('WP-Piwik', 'wp-piwik').' - '.__('Visitors', 'wp-piwik'), 
				array(&$this, 'createDashboardWidget'), 
				'dashboard', 
				'side', 
				'high',
				array('strFile' => $strFile, 'aryConfig' => $aryConfig)
			);
	}	

	/**
	 * Add plugin meta links to plugin details
	 * 
	 * @see http://wpengineer.com/1295/meta-links-for-wordpress-plugins/
	 */
	function setPluginMeta($strLinks, $strFile) {
		// Get plugin basename
		$strPlugin = plugin_basename(__FILE__);
		// Add link just to this plugin's details
		if ($strFile == self::$strPluginBasename) 
			return array_merge(
				$strLinks,
				array(
					sprintf('<a href="options-general.php?page=%s">%s</a>', self::$strPluginBasename, __('Settings', 'wp-piwik'))
				)
			);
		// Don't affect other plugins details
		return $strLinks;
	}

	/**
	 * Load required scripts to admin pages
	 */
	function load_scripts() {
		// Load WP-Piwik script
		wp_enqueue_script('wp-piwik', $this->get_plugin_url().'js/wp-piwik.js', array(), self::$strVersion, true);
		// Load jqPlot
		wp_enqueue_script('wp-piwik-jqplot',$this->get_plugin_url().'js/jqplot/wp-piwik.jqplot.js',array('jquery'));
	}

	/**
	 * Load required styles to admin pages
	 */
	function add_admin_style() {
		// Load WP-Piwik styles
		wp_enqueue_style('wp-piwik', $this->get_plugin_url().'css/wp-piwik.css', array('dashboard'));
	}

	/**
	 * Add required header tags to admin pages
	 */
	function add_admin_header() {
		// Load jqPlot IE compatibility script
		echo '<!--[if IE]><script language="javascript" type="text/javascript" src="'.$this->get_plugin_url().(self::$bolWPMU?'wp-piwik/':'').'js/jqplot/excanvas.min.js"></script><![endif]-->';
		// Load jqPlot styles
		echo '<link rel="stylesheet" href="'.$this->get_plugin_url().'js/jqplot/jquery.jqplot.min.css" type="text/css"/>';
		echo '<script type="text/javascript">var $j = jQuery.noConflict();</script>';
	}
	
	/**
	 * Get this plugin's URL
	 */
	function get_plugin_url() {
		// Return plugins URL + /wp-piwik/
		return trailingslashit(plugins_url().'/wp-piwik/');
	}

	/**
	 * Get remote file
	 * 
	 * @param String $strURL Remote file URL
	 */
	function get_remote_file($strURL) {
		// Use cURL if available	
		if (function_exists('curl_init')) {
			// Init cURL
			$c = curl_init($strURL);
			// Configure cURL CURLOPT_RETURNTRANSFER = 1
			curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
			// Configure cURL CURLOPT_HEADER = 0 
			curl_setopt($c, CURLOPT_HEADER, 0);
			// Get result
			$strResult = curl_exec($c);
			// Close connection			
			curl_close($c);
		// cURL not available but url fopen allowed
		} elseif (ini_get('allow_url_fopen'))
			// Get file using file_get_contents
			$strResult = file_get_contents($strURL);
		// Error: Not possible to get remote file
		else $strResult = serialize(array(
				'result' => 'error',
				'message' => 'Remote access to Piwik not possible. Enable allow_url_fopen or CURL.'
			));
		// Return result
		return $strResult;
	}

	function call_API($strMethod, $strPeriod='', $strDate='', $intLimit='',$bolExpanded=false) {
		$strKey = $strMethod.'_'.$strPeriod.'_'.$strDate.'_'.$intLimit;
		if (empty($this->aryCache[$strKey])) {
			$strToken = self::$aryGlobalSettings['piwik_token'];
			$strURL = self::$aryGlobalSettings['piwik_url'];
			$intSite = self::$arySettings['site_id'];
			if (self::$bolWPMU && empty($intSite)) {
				$aryReturn = $this->create_wpmu_site();
				$intSite = $aryReturn['id'];
			}
			if (self::$bolOverall) $intSite = 'all';
			if (empty($strToken) || empty($strURL)) {
				$this->aryCache[$key] = array(
					'result' => 'error',
					'message' => 'Piwik base URL or auth token not set.'
				);
				return $this->aryCache[$strKey];
			}			
			$strURL .= '?module=API&method='.$strMethod;
			$strURL .= '&idSite='.$intSite.'&period='.$strPeriod.'&date='.$strDate;
			$strURL .= '&format=PHP&filter_limit='.$intLimit;
			$strURL .= '&token_auth='.$strToken;
			$strURL .= '&expanded='.$bolExpanded;
			$strResult = $this->get_remote_file($strURL);			
			$this->aryCache[$strKey] = unserialize($strResult);
		}
		return $this->aryCache[$strKey];	
	}

	function create_wpmu_site() {		
		$strURL = self::$aryGlobalSettings['piwik_url'];
		$strJavaScript = '';
		if (!empty(self::$aryGlobalSettings['piwik_token']) && !empty($strURL)) {			
			if (empty(self::$arySettings['site_id'])) {
				$strName = get_bloginfo('name');
				$strBlogURL = get_bloginfo('url');
				if (substr($strURL, -1, 1) != '/') $strURL .= '/';
				$strURL .= '?module=API&method=SitesManager.addSite';
				$strURL .= '&siteName='.urlencode('WPMU: '.$strName).'&urls='.urlencode($strBlogURL);
				$strURL .= '&format=PHP';
				$strURL .= '&token_auth='.self::$aryGlobalSettings['piwik_token'];
				$strResult = unserialize($this->get_remote_file($strURL));
				if (!empty($strResult)) {
					self::$arySettings['site_id'] = $strResult;
					self::$arySettings['last_tracking_code_update'] = time();					
					$strJavaScript = html_entity_decode($this->call_API('SitesManager.getJavascriptTag'));
				}
			} else $strJavaScript = html_entity_decode($this->call_API('SitesManager.getJavascriptTag'));
			self::$arySettings['tracking_code'] = $strJavaScript;
			self::saveSettings();
		}
		return array('js' => $strJavaScript, 'id' => self::$arySettings['site_id']);
	}

	function createDashboardWidget($objPost, $aryMetabox) {
		$strDesc = $strID = '';
		$aryConfig = $aryMetabox['args']['aryConfig'];
		foreach ($aryConfig['params'] as $strParam)
			if (!empty($strParam)) {
				$strDesc .= $strParam.', ';
				$strID .= '_'.$strParam;
			}
		$strFile = str_replace('.', '', $aryMetabox['args']['strFile']);
		$aryConf = array_merge($aryConfig, array(
			'id' => $strFile.$strID,
			'desc' => substr($strDesc, 0, -2)));
		$strRoot = dirname(__FILE__);
		if (file_exists($strRoot.DIRECTORY_SEPARATOR.'dashboard/'.$strFile.'.php'))
			include($strRoot.DIRECTORY_SEPARATOR.'dashboard/'.$strFile.'.php');
 	}

	function display_post_unique_column($aryCols) {
	 	$aryCols['wp-piwik_unique'] = __('Unique');
	        return $aryCols;
	}

	function display_post_unique_content($strCol, $intID) {
		if( $strCol == 'wp-piwik_unique' ) {
		}
	}

	function onloadStatsPage() {
	wp_enqueue_script('common');
		wp_enqueue_script('wp-lists');
		wp_enqueue_script('postbox');
		$strToken = self::$aryGlobalSettings['piwik_token'];
		$strPiwikURL = self::$aryGlobalSettings['piwik_url'];
		$aryDashboard = array();
		// Set default configuration
		$arySortOrder = array(
			'side' => array(
				'overview' => array(__('Overview', 'wp-piwik'), 'day', 'yesterday'),
				'pages' => array(__('Pages', 'wp-piwik'), 'day', 'yesterday'),
				'keywords' => array(__('Keywords', 'wp-piwik'), 'day', 'yesterday', 10),
				'websites' => array(__('Websites', 'wp-piwik'), 'day', 'yesterday', 10),
				'plugins' => array(__('Plugins', 'wp-piwik'), 'day', 'yesterday')
			),
			'normal' => array(
				'visitors' => array(__('Visitors', 'wp-piwik'), 'day', 'last30'),
				'browsers' => array(__('Browser', 'wp-piwik'), 'day', 'yesterday'),
				'screens' => array(__('Resolution', 'wp-piwik'), 'day', 'yesterday'),
				'systems' => array(__('Operating System', 'wp-piwik'), 'day', 'yesterday')
			)
		);
		foreach ($arySortOrder as $strCol => $aryWidgets) {
			if (is_array($aryWidgets)) foreach ($aryWidgets as $strFile => $aryParams) {
					$aryDashboard[$strCol][$strFile] = array(
						'params' => array(
							'title'	 => (isset($aryParams[0])?$aryParams[0]:$strFile),
							'period' => (isset($aryParams[1])?$aryParams[1]:''),
							'date'   => (isset($aryParams[2])?$aryParams[2]:''),
							'limit'  => (isset($aryParams[3])?$aryParams[3]:'')
						)
					);
					if (isset($_GET['date']) && preg_match('/^[0-9]{8}$/', $_GET['date']) && $strFile != 'visitors')
						$aryDashboard[$strCol][$strFile]['params']['date'] = $_GET['date'];
					elseif ($strFile != 'visitors') 
						$aryDashboard[$strCol][$strFile]['params']['date'] = self::$aryGlobalSettings['default_date'];
			}
		}
		$intSideBoxCnt = $intContentBox = 0;
		foreach ($aryDashboard['side'] as $strFile => $aryConfig) {
			$intSideBoxCnt++;
			add_meta_box(
				'wp-piwik_stats-sidebox-'.$intSideBoxCnt, 
				$aryConfig['params']['title'].' '.$aryConfig['params']['date'], 
				array(&$this, 'createDashboardWidget'), 
				$this->intStatsPage, 
				'side', 
				'core',
				array('strFile' => $strFile, 'aryConfig' => $aryConfig)
			);
		}
		foreach ($aryDashboard['normal'] as $strFile => $aryConfig) {
			$intContentBox++;
			add_meta_box(
				'wp-piwik_stats-contentbox-'.$intContentBox, 
				$aryConfig['params']['title'].' '.$aryConfig['params']['date'],
				array(&$this, 'createDashboardWidget'), 
				$this->intStatsPage, 
				'normal', 
				'core',
				array('strFile' => $strFile, 'aryConfig' => $aryConfig)
			);
		}
	}
		
	function showStats() {
		//we need the global screen column value to be able to have a sidebar in WordPress 2.8
		global $screen_layout_columns;		
/***************************************************************************/ ?>
<div id="wp-piwik-stats-general" class="wrap">
	<?php screen_icon('options-general'); ?>
	<h2><?php _e('Piwik Statistics', 'wp-piwik'); ?></h2>
<?php /************************************************************************/
		if (self::$bolWPMU && function_exists('is_super_admin') && is_super_admin()) {
			if (isset($_POST['wpmu_show_stats']))
				switch_to_blog((int) $_POST['wpmu_show_stats']);
			global $blog_id;
			global $wpdb;
			$aryBlogs = $wpdb->get_results($wpdb->prepare('SELECT blog_id FROM '.$wpdb->prefix.'blogs ORDER BY blog_id'));			
			echo '<form method="POST" action="">'."\n";
			echo '<select name="wpmu_show_stats">'."\n";
			foreach ($aryBlogs as $aryBlog) {
				$objBlog = get_blog_details($aryBlog->blog_id, true);
				echo '<option value="'.$objBlog->blog_id.'"'.($blog_id == $objBlog->blog_id?' selected="selected"':'').'>'.$objBlog->blogname.'</option>'."\n";
			}
			echo '</select><input type="submit" value="'.__('Change').'" />'."\n ";
			if (!self::$bolOverall) echo __('Currently shown stats:').' <a href="'.get_bloginfo('url').'">'.get_bloginfo('name').'</a>'."\n";
			else _e('Current shown stats: <strong>Overall</strong>');
			echo '</form>'."\n";
		}
/***************************************************************************/ ?>
	<form action="admin-post.php" method="post">
		<?php wp_nonce_field('wp-piwik_stats-general'); ?>
		<?php wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false ); ?>
        <?php wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false ); ?>
		<input type="hidden" name="action" value="save_wp-piwik_stats_general" />		
		<div id="poststuff" class="metabox-holder has-right-sidebar" style="width:<?php echo 528+281; ?>px;">
			<div id="side-info-column" class="inner-sidebar wp-piwik-side">
				<?php do_meta_boxes($this->intStatsPage, 'side', $data); ?>
			</div>
        	<div id="post-body" class="has-sidebar">
					<div id="post-body-content" class="postbox-container has-sidebar-content">
					<?php $meta_boxes = do_meta_boxes($this->intStatsPage, 'normal', $data); ?>
				</div>
			</div>
			<br class="clear"/>
    	</div>
	</form>
</div>
<script type="text/javascript">
	//<![CDATA[
	jQuery(document).ready( function($) {
		// close postboxes that should be closed
		$('.if-js-closed').removeClass('if-js-closed').addClass('closed');
		// postboxes setup
		postboxes.add_postbox_toggles('<?php echo $this->intStatsPage; ?>');
	});
	//]]>
</script>
<?php /************************************************************************/
		if (self::$bolWPMU && function_exists('is_super_admin') && is_super_admin()) {
			restore_current_blog(); self::$bolOverall = false;
		}
	}

	/* Stats page changes by POST submit
	   seen in Heiko Rabe's metabox demo plugin 
	   http://tinyurl.com/5r5vnzs */
	function onStatsPageSaveChanges() {
		//user permission check
		if ( !current_user_can('manage_options') )
			wp_die( __('Cheatin&#8217; uh?') );			
		//cross check the given referer
		check_admin_referer('wp-piwik_stats');
		//process here your on $_POST validation and / or option saving
		//lets redirect the post request into get request (you may add additional params at the url, if you need to show save results
		wp_redirect($_POST['_wp_http_referer']);		
	}
		
	function applySettings() {
		if (!self::$bolWPMU) {
			self::$aryGlobalSettings['add_tracking_code']  	= (isset($_POST['wp-piwik_addjs'])?$_POST['wp-piwik_addjs']:'');
			self::$aryGlobalSettings['dashboard_widget'] 	= (isset($_POST['wp-piwik_dbwidget'])?$_POST['wp-piwik_dbwidget']:false);
			self::$aryGlobalSettings['dashboard_chart']		= (isset($_POST['wp-piwik_dbchart'])?$_POST['wp-piwik_dbchart']:false);
			self::$aryGlobalSettings['piwik_shortcut']	 	= (isset($_POST['wp-piwik_piwiklink'])?$_POST['wp-piwik_piwiklink']:false);
			self::$arySettings['site_id']			 		= (isset($_POST['wp-piwik_siteid'])?$_POST['wp-piwik_siteid']:NULL);
			self::$arySettings['track_404'] 			 	= (isset($_POST['wp-piwik_404'])?$_POST['wp-piwik_404']:false);
			self::$aryGlobalSettings['default_date'] 		= (isset($_POST['wp-piwik_default_date'])?$_POST['wp-piwik_default_date']:'yesterday');
		}
		self::$aryGlobalSettings['piwik_token'] 		 	= (isset($_POST['wp-piwik_token'])?$_POST['wp-piwik_token']:'');
		self::$aryGlobalSettings['piwik_url']				= self::check_url((isset($_POST['wp-piwik_url'])?$_POST['wp-piwik_url']:''));
		self::$aryGlobalSettings['capability_stealth'] 		= (isset($_POST['wp-piwik_filter'])?$_POST['wp-piwik_filter']:array());
		self::$aryGlobalSettings['capability_read_stats'] 	= (isset($_POST['wp-piwik_displayto'])?$_POST['wp-piwik_displayto']:array());
		self::$aryGlobalSettings['last_settings_update'] 	= time();
		self::saveSettings();
	}

	static function check_url($strURL) {
		if (substr($strURL, -1, 1) != '/' && substr($strURL, -10, 10) != '/index.php') 
			$strURL .= '/';
		return $strURL;
	}
	
	function show_settings() { 		
		$strToken = self::$aryGlobalSettings['piwik_token'];
		$strURL = self::$aryGlobalSettings['piwik_url'];
		$intSite = self::$arySettings['site_id'];
		if (isset($_POST['action']) && $_POST['action'] == 'save_settings') 
			echo '<div id="message" class="updated fade"><p>'.__('Changes saved','wp-piwik').'</p></div>';
/***************************************************************************/ ?>
<div class="wrap">
	<h2><?php _e('WP-Piwik Settings', 'wp-piwik') ?></h2>
	<?php $this->donate(); ?>
	<form method="post" action="">
		<div id="dashboard-widgets-wrap">
			<div id="dashboard-widgets" class="metabox-holder">
				<div class="wp-piwik-settings-container" id="postbox-container">
					<div class="postbox wp-piwik-settings" >
						<h3 class='hndle'><span><?php _e('Account settings', 'wp-piwik'); ?></span></h3>
						<div class="inside">
							<h4><label for="wp-piwik_url"><?php _e('Piwik URL', 'wp-piwik'); ?>:</label></h4>
								<div class="input-text-wrap">
									<input type="text" name="wp-piwik_url" id="wp-piwik_url" value="<?php echo $strURL; ?>" />
								</div>
							<h4><label for="wp-piwik_token"><?php _e('Auth token', 'wp-piwik'); ?>:</label></h4>
								<div class="input-text-wrap">
									<input type="text" name="wp-piwik_token" id="wp-piwik_token" value="<?php echo $strToken; ?>" />
								</div>
								<div class="wp-piwik_desc">
<?php _e(
	'To enable Piwik statistics, please enter your Piwik'.
	' base URL (like http://mydomain.com/piwik) and your'.
	' personal authentification token. You can get the token'.
	' on the API page inside your Piwik interface. It looks'.
	' like &quot;1234a5cd6789e0a12345b678cd9012ef&quot;.'
	, 'wp-piwik'
); ?>
								</div>
								<div class="wp-piwik_desc">
<?php _e(
	'<strong>Important note:</strong> If you do not host this blog on your own, your site admin is able to get your auth token from the database. So he is able to access your statistics. You should never use an auth token with more than simple view access!',
	'wp-piwik'
); ?>
								</div>
<?php /************************************************************************/
		if (!empty($strToken) && !empty($strURL)) { 
			$aryData = $this->call_API('SitesManager.getSitesWithAtLeastViewAccess');
			if (empty($aryData)) {
				echo '<div class="wp-piwik_desc"><strong>'.__('An error occured', 'wp-piwik').': </strong>'.
					__('Please check URL and auth token. You need at least view access to one site.', 'wp-piwik').
					'</div>';
			} elseif (isset($aryData['result']) && $aryData['result'] == 'error') {
				echo '<div class="wp-piwik_desc"><strong><strong>'.__('An error occured', 'wp-piwik').
					': </strong>'.$aryData['message'].'</div>';
			} else {
				echo '<h4><label for="wp-piwik_siteid">'.__('Choose site', 'wp-piwik').':</label></h4>'.
					'<div class="input-wrap"><select name="wp-piwik_siteid" id="wp-piwik_siteid">';
				foreach ($aryData as $arySite)
					echo '<option value="'.$arySite['idsite'].
						'"'.($arySite['idsite']==$intSite?' selected=""':'').
						'>'.htmlentities($arySite['name'], ENT_QUOTES, 'utf-8').
						'</option>';
				echo '</select></div>';
				if (empty($intSite)) {
					self::$arySettings['site_id'] = $aryData[0]['idsite'];
					self::saveSettings();
				}
				$intSite = self::$arySettings['site_id'];
				$int404 = self::$arySettings['track_404'];
				$intAddJS = self::$aryGlobalSettings['add_tracking_code'];
				$intDashboardWidget = self::$aryGlobalSettings['dashboard_widget'];
				$intShowLink = self::$aryGlobalSettings['piwik_shortcut'];
				$strJavaScript = html_entity_decode($this->call_API('SitesManager.getJavascriptTag'));
				if ($intAddJS) {
					self::$arySettings['tracking_code'] = $strJavaScript;
					self::saveSettings();
				}
/***************************************************************************/ ?>
<div><input type="submit" name="Submit" value="<?php _e('Save settings', 'wp-piwik') ?>" /></div>
					</div>
				</div>
				<div class="postbox wp-piwik-settings" >
					<h3 class='hndle'><span><?php _e('Tracking settings', 'wp-piwik'); ?></span></h3>
					<div class="inside">
<?php /************************************************************************/
				echo '<h4><label for="wp-piwik_jscode">JavaScript:</label></h4>'.
					'<div class="input-text-wrap"><textarea id="wp-piwik_jscode" name="wp-piwik_jscode" readonly="readonly" rows="13" cols="55">'.
						htmlentities($strJavaScript).'</textarea></div>';
				echo '<h4><label for="wp-piwik_addjs">'.__('Add script', 'wp-piwik').':</label></h4>'.
						'<div class="input-wrap"><input type="checkbox" value="1" id="wp-piwik_addjs" name="wp-piwik_addjs" '.
						($intAddJS?' checked="checked"':'').'/></div>';
				echo '<div class="wp-piwik_desc">'.
                                                __('If your template uses wp_footer(), WP-Piwik can automatically'.
                                                        ' add the Piwik javascript code to your blog.', 'wp-piwik').
                                                '</div>';
				echo '<h4><label for="wp-piwik_404">'.__('Track 404', 'wp-piwik').':</label></h4>'.
						'<div class="input-wrap"><input type="checkbox" value="1" id="wp-piwik_404" name="wp-piwik_404" '.
						($int404?' checked="checked"':'').'/></div>';
				echo '<div class="wp-piwik_desc">'.
						__('If you add the Piwik javascript code by wp_footer(), '.
							'WP-Piwik can automatically add a 404-category to track 404-page-visits.', 'wp-piwik').
						'</div>';
				global $wp_roles;
				echo '<h4><label>'.__('Tracking filter', 'wp-piwik').':</label></h4>';
				echo '<div class="input-wrap">';
				$aryFilter = self::$aryGlobalSettings['capability_stealth'];
				foreach($wp_roles->role_names as $strKey => $strName)  {
					echo '<input type="checkbox" '.(isset($aryFilter[$strKey]) && $aryFilter[$strKey]?'checked="checked" ':'').'value="1" name="wp-piwik_filter['.$strKey.']" /> '.$strName.' &nbsp; ';
				}
				echo '</div>';
				echo '<div class="wp-piwik_desc">'.
					__('Choose users by user role you do <strong>not</strong> want to track.'.
					' Requires enabled &quot;Add script&quot;-functionality.','wp-piwik').'</div>';
				/***************************************************************************/ ?>
<div><input type="submit" name="Submit" value="<?php _e('Save settings', 'wp-piwik') ?>" /></div>
						</div>
					</div>
					<div class="postbox wp-piwik-settings" >
						<h3 class='hndle'><span><?php _e('Statistic view settings', 'wp-piwik'); ?></span></h3>
						<div class="inside">
	<?php
				echo '<h4><label for="wp-piwik_dbwidget">'.__('Dashboard data', 'wp-piwik').':</label></h4>'.
						'<div class="input-wrap"><select id="wp-piwik_dbwidget" name="wp-piwik_dbwidget">'.
						'<option value="0"'.(!$intDashboardWidget?' selected="selected"':'').'>'.__('No', 'wp-piwik').'</option>'.
						'<option value="yesterday"'.($intDashboardWidget == 'yesterday'?' selected="selected"':'').'>'.__('Yes','wp-piwik').' ('.__('yesterday', 'wp-piwik').').</option>'.
						'<option value="today"'.($intDashboardWidget == 'today'?' selected="selected"':'').'>'.__('Yes','wp-piwik').' ('.__('today', 'wp-piwik').').</option>'.
						'<option value="last30"'.($intDashboardWidget == 'last30'?' selected="selected"':'').'>'.__('Yes','wp-piwik').' ('.__('last 30 days','wp-piwik').').</option>'.
						'</select></div>';
				echo '<div class="wp-piwik_desc">'.
					__('Display an overview widget to your WordPress dashboard.', 'wp-piwik').'</div>';
				 
				echo '<h4><label for="wp-piwik_dbwidget">'.__('Boad chart', 'wp-piwik').':</label></h4>'.
						'<div class="input-wrap"><input type="checkbox" value="1" name="wp-piwik_dbchart" id="wp-piwik_dbchart" '.
						(self::$aryGlobalSettings['dashboard_chart']?' checked="checked"':"").'/></div>';
				echo '<div class="wp-piwik_desc">'.
					__('Display a visitor graph widget to your WordPress dashboard.', 'wp-piwik').'</div>';
				
				echo '<h4><label for="wp-piwik_piwiklink">'.__('Shortcut', 'wp-piwik').':</label></h4>'.
						'<div class="input-wrap"><input type="checkbox" value="1" name="wp-piwik_piwiklink" id="wp-piwik_piwiklink" '.
						($intShowLink?' checked="checked"':"").'/></div>';
				echo '<div class="wp-piwik_desc">'.
					__('Display a shortcut to Piwik itself.', 'wp-piwik').'</div>';
				echo '<h4><label for="wp-piwik_default_date">'.__('Default date', 'wp-piwik').':</label></h4>'.
						'<div class="input-wrap"><select id="wp-piwik_default_date" name="wp-piwik_default_date">'.
						'<option value="yesterday"'.(self::$aryGlobalSettings['default_date'] == 'yesterday'?' selected="selected"':'').'> '.__('yesterday', 'wp-piwik').'</option>'.
						'<option value="today"'.(self::$aryGlobalSettings['default_date'] == 'today'?' selected="selected"':'').'> '.__('today', 'wp-piwik').'</option>'.
						'</select></div>';
				echo '<div class="wp-piwik_desc">'.
					__('Default date shown on statistics page.', 'wp-piwik').'</div>';
				echo '<h4><label>'.__('Display to', 'wp-piwik').':</label></h4>';
				echo '<div class="input-wrap">';
				$intDisplayTo = self::$aryGlobalSettings['capability_read_stats'];
				foreach($wp_roles->role_names as $strKey => $strName) {
						$role = get_role($strKey);						
						echo '<input name="wp-piwik_displayto['.$strKey.']" type="checkbox" value="1"'.(isset(self::$aryGlobalSettings['capability_read_stats'][$strKey]) && self::$aryGlobalSettings['capability_read_stats'][$strKey]?' checked="checked"':'').'/> '.$strName.' &nbsp; ';
				}
				echo '</div><div class="wp-piwik_desc">'.
						__('Choose user roles allowed to see the statistics page.', 'wp-piwik').
						'</div>';
			}
		}
/***************************************************************************/ ?>
					<div><input type="submit" name="Submit" value="<?php _e('Save settings', 'wp-piwik') ?>" /></div>
				</div>
			</div>
		</div>
		<input type="hidden" name="action" value="save_settings" />
		</div></div>		
		</form>
<pre><?php $current_user = wp_get_current_user(); ?></pre>
	</div>
	<?php $this->credits(); ?>
<?php /************************************************************************/
	}

	function show_mu_settings() { 
		$strToken = self::$aryGlobalSettings['piwik_token'];
		$strURL = self::$aryGlobalSettings['piwik_url'];
		if (isset($_POST['action']) && $_POST['action'] == 'save_settings') 
			echo '<div id="message" class="updated fade"><p>'.__('Changes saved','wp-piwik').'</p></div>';		
/***************************************************************************/ ?>
<div class="wrap">
	<h2><?php _e('WPMU-Piwik Settings', 'wp-piwik') ?></h2>
	<?php $this->donate(); ?>
		<form method="post" action="">
		<div id="dashboard-widgets-wrap">
			<div id="dashboard-widgets" class="metabox-holder">
				<div class="wp-piwik-settings-container" id="postbox-container">
					<div class="postbox wp-piwik-settings" >
					<h3 class='hndle'><span><?php _e('Account settings', 'wp-piwik'); ?></span></h3>
						<div class="inside">
							<h4><label for="wp-piwik_url"><?php _e('Piwik URL', 'wp-piwik'); ?>:</label></h4>
								<div class="input-text-wrap">
									<input type="text" name="wp-piwik_url" id="wp-piwik_url" value="<?php echo $strURL; ?>" />
								</div>
							<h4><label for="wp-piwik_token"><?php _e('Auth token', 'wp-piwik'); ?>:</label></h4>
								<div class="input-text-wrap">
									<input type="text" name="wp-piwik_token" id="wp-piwik_token" value="<?php echo $strToken; ?>" />
								</div>
								<div class="wp-piwik_desc">
								<?php _e(
									'To enable Piwik statistics, please enter your Piwik'.
									' base URL (like http://mydomain.com/piwik) and your'.
									' personal authentification token. You can get the token'.
									' on the API page inside your Piwik interface. It looks'.
									' like &quot;1234a5cd6789e0a12345b678cd9012ef&quot;.'
									, 'wp-piwik'
								); ?>
								</div>
								<div class="wp-piwik_desc">
								<?php _e(
									'<strong>Important note:</strong> You have to choose a token which provides administration access. WPMU-Piwik will create new Piwik sites for each blog if it is shown the first time and it is not added yet. All users can access their own statistics only, while site admins can access all statistics. To avoid conflicts, you should use a clean Piwik installation without other sites added. The provided themes should use wp_footer, because it adds the Piwik javascript code to each page.', 'wp-piwik');
								?>
								</div>
<?php /************************************************************************/
		if (!empty($strToken) && !empty($strURL)) {
			global $wp_roles;
			echo '<h4><label>'.__('Tracking filter', 'wp-piwik').':</label></h4>';
			echo '<div class="input-wrap">';
			$aryFilter = self::$aryGlobalSettings['capability_stealth'];
			foreach($wp_roles->role_names as $strKey => $strName)  {
				echo '<input type="checkbox" '.(isset($aryFilter[$strKey]) && $aryFilter[$strKey]?'checked="checked" ':'').'value="1" name="wp-piwik_filter['.$strKey.']" /> '.$strName.' &nbsp; ';
			};
			echo '</div>';
			echo '<div class="wp-piwik_desc">'.
				__('Choose users by user role you do <strong>not</strong> want to track.', 'wp-piwik').'</div>';
		}
/***************************************************************************/ ?>
			<div><input type="submit" name="Submit" value="<?php _e('Save settings', 'wp-piwik') ?>" /></div>
				</div>
			</div>
		</div>
		<input type="hidden" name="action" value="save_settings" />
		</div></div>		
		</form>
	<?php $this->credits(); ?>
</div>
<?php /************************************************************************/
	}

	function donate() {
/***************************************************************************/ ?>
	<div class="wp-piwik-sidebox">
	<strong>Donate</strong>
	<p><?php _e('If you like WP-Piwik, you can support its development by a donation:', 'wp-piwik'); ?></p>
	<div>
<script type="text/javascript">
	var flattr_url = 'http://www.braekling.de/wp-piwik-wpmu-piwik-wordpress';
</script>
<script src="http<?php echo (self::isSSL()?'s':''); ?>://api.flattr.com/button/load.js" type="text/javascript"></script>
	</div>
	<div>Paypal
<form action="https://www.paypal.com/cgi-bin/webscr" method="post">
<input type="hidden" name="cmd" value="_s-xclick" />
<input type="hidden" name="hosted_button_id" value="6046779" />
<input type="image" src="https://www.paypal.com/en_GB/i/btn/btn_donateCC_LG.gif" name="submit" alt="PayPal - The safer, easier way to pay online." />
<img alt="" border="0" src="https://www.paypal.com/de_DE/i/scr/pixel.gif" width="1" height="1" />
</form>
	</div>
	<div>
		<a href="http://www.amazon.de/gp/registry/wishlist/111VUJT4HP1RA?reveal=unpurchased&amp;filter=all&amp;sort=priority&amp;layout=standard&amp;x=12&amp;y=14"><?php _e('My Amazon.de wishlist (German)', 'wp-piwik'); ?></a>
	</div>
	</div>
<?php /************************************************************************/
	}

	private static function isSSL() {
		return (isset($_SERVER['HTTPS']) && !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off');
	}
	
	function credits() {
/***************************************************************************/ ?>
	<h2 style="clear:left;"><?php _e('Credits', 'wp-piwik'); ?></h2>
	<div class="inside">
		<p><strong><?php _e('Thank you very much for your donation', 'wp-piwik'); ?>:</strong> Marco L., Rolf W., Tobias U., Lars K., Donna F. <?php _e('and all people flattering this','wp-piwik'); ?>!</p>
		<p><?php _e('Graphs powered by <a href="http://www.jqplot.com/">jqPlot</a>, an open source project by Chris Leonello. Give it a try! (License: GPL 2.0 and MIT)','wp-piwik'); ?></p>
		<p><?php _e('Metabox support inspired by <a href="http://www.code-styling.de/english/how-to-use-wordpress-metaboxes-at-own-plugins">Heiko Rabe\'s metabox demo plugin</a>.')?></p>
		<p><?php _e('Thank you very much','wp-piwik'); ?>, <a href="http://blogu.programeshqip.org/">Besnik Bleta</a>, <a href="http://www.fatcow.com/">FatCow</a>, <a href="http://www.pamukkaleturkey.com/">Rene</a>, Fab, <a href="http://ezbizniz.com/">EzBizNiz</a>, Gormer, Natalya, <a href="www.aggeliopolis.gr">AggelioPolis</a><?php _e(', and', 'wp-piwik'); ?> <a href="http://wwww.webhostinggeeks.com">Galina Miklosic</a> <?php _e('for your translation work','wp-piwik'); ?>!</p>
		<p><?php _e('Thank you very much, all users who send me mails containing criticism, commendation, feature requests and bug reports! You help me to make WP-Piwik much better.','wp-piwik'); ?></p>
		<p><?php _e('Thank <strong>you</strong> for using my plugin. It is the best commendation if my piece of code is really used!','wp-piwik'); ?></p>
	</div>
<?php /************************************************************************/
	}
}

if (class_exists('wp_piwik'))
	$GLOBALS['wp_piwik'] = new wp_piwik();

/* EOF */
