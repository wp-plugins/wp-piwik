<?php
/*
Plugin Name: WP-Piwik

Plugin URI: http://wordpress.org/extend/plugins/wp-piwik/

Description: Adds Piwik stats to your dashboard menu and Piwik code to your wordpress footer.

Version: 0.9.1
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

/**
 * Avoid direct calls to this file if wp core files not present
 * seen (as some other parts) in Heiko Rabe's metabox demo plugin 
 *
 * @see http://tinyurl.com/5r5vnzs 
 */
if (!function_exists ('add_action')) {
	header('Status: 403 Forbidden');
	header('HTTP/1.1 403 Forbidden');
	exit();
}

/**
 * Makes sure /wp-admin/includes/plugin.php is loaded before trying to use it
 * 
 * @see http://codex.wordpress.org/Function_Reference/is_plugin_active_for_network
 */
if (!function_exists('is_plugin_active_for_network'))
	require_once(ABSPATH.'/wp-admin/includes/plugin.php');

/***************************************************************************************** 
	IMPORTANT NOTICE - WPMU - MULTISITE - NETWORK
******************************************************************************************
 	If you are using WP-Piwik as WordPress Network Plugin (Multisite/"WPMU"), 
 	you don't have to change values anymore.
 	PLEASE BACKUP YOUR WP & DATABASE _BEFORE_ TESTING THIS NEW WP-PIWIK RELEASE.
	REMEMBER: MULTISITE SUPPORT IS STILL EXPERIMENTAL. USE AT YOUR OWN RISK.
******************************************************************************************/

class wp_piwik {

	private static
		$intRevisionId = 90101,
		$strVersion = '0.9.1',
		$intDashboardID = 30,
		$strPluginBasename = NULL,
		$aryGlobalSettings = array(
			'revision' => 90101,
			'add_tracking_code' => false,
			'last_settings_update' => 0,
			'piwik_token' => '',
			'piwik_url' => '',
			'dashboard_widget' => false,
			'dashboard_chart' => false,
			'dashboard_seo' => false,
			'stats_seo' => false,
			'capability_stealth' => array(),
			'capability_read_stats' => array('administrator' => true),
			'piwik_shortcut' => false,
			'default_date' => 'yesterday',
			'auto_site_config' => true,
			'track_404' => false,
			'track_compress' => false,
			'track_post' => false
		),
		$arySettings = array(
			'tracking_code' => '',
			'site_id' => NULL,
			'last_tracking_code_update' => 0,
			'dashboard_revision' => 0
		);
		
	private
		$intStatsPage = NULL;

	/**
	 * Load plugin settings 
	 */
	static function loadSettings() {		
		// Get global settings
		self::$aryGlobalSettings = (is_plugin_active_for_network('wp-piwik/wp-piwik.php')?
			get_site_option('wp-piwik_global-settings',self::$aryGlobalSettings):
			get_option('wp-piwik_global-settings',self::$aryGlobalSettings)
		);
		// Get site settings
		self::$arySettings = get_option('wp-piwik_settings',self::$arySettings);
	}
	
	/**
	 * Save plugin settings 
	 */
	static function saveSettings() {
		// Save global settings
		if (is_plugin_active_for_network('wp-piwik/wp-piwik.php'))
			update_site_option('wp-piwik_global-settings',self::$aryGlobalSettings);
		else 
			update_option('wp-piwik_global-settings',self::$aryGlobalSettings);
		// Save blog settings
		update_option('wp-piwik_settings',self::$arySettings);
		// Load WP_Roles class 
		global $wp_roles;
		if (!is_object($wp_roles))
			$wp_roles = new WP_Roles();
		if (!is_object($wp_roles)) die("STILL NO OBJECT");
		// Assign capabilities to roles
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
		add_filter('plugin_row_meta', array($this, 'setPluginMeta'), 10, 2);
		// Register columns
		/* TODO: currently not working
		 * add_filter('screen_layout_columns', array(&$this, 'onScreenLayoutColumns'), 10, 2); 
		 */
		// Add network admin menu if required
		if (is_plugin_active_for_network('wp-piwik/wp-piwik.php'))
			add_action('network_admin_menu', array($this, 'buildNetworkAdminMenu'));
		// Add admin menu		
		add_action('admin_menu', array($this, 'buildAdminMenu'));
		// Register the callback been used if options of page been submitted and needs to be processed
		add_action('admin_post_save_wp-piwik_stats', array(&$this, 'onStatsPageSaveChanges'));
		// Add dashboard widget if enabled
		/* TODO: Use bitmask here
		 */
		if (self::$aryGlobalSettings['dashboard_widget'] || self::$aryGlobalSettings['dashboard_chart'] || self::$aryGlobalSettings['dashboard_seo'])
			add_action('wp_dashboard_setup', array($this, 'extendWordPressDashboard'));		
		// Add tracking code to footer if enabled
		if (self::$aryGlobalSettings['add_tracking_code']) add_action('wp_footer', array($this, 'footer'));
	}

	/**
	 * Destructor
	 */
	function __destruct() {}
	
	/**
	 * Include WP-Piwik files
	 */
	private function includeFile($strFile) {
		if (file_exists(dirname(__FILE__).DIRECTORY_SEPARATOR.$strFile.'.php'))
			include(dirname(__FILE__).DIRECTORY_SEPARATOR.$strFile.'.php');
	}	
	
	/**
	 * Install or upgrade
	 */
	function install() {
		// Update: Translate options
		if (self::$aryGlobalSettings['revision'] < 80403)
			self::includeFile('update/80403.php');
		if (self::$aryGlobalSettings['revision'] < 80502)
			self::includeFile('update/80502.php');
		if (self::$aryGlobalSettings['revision'] < 80602)
			self::includeFile('update/80602.php');
		if (self::$aryGlobalSettings['revision'] < 80800)
			self::includeFile('update/80800.php');
		if (self::$aryGlobalSettings['revision'] < 90001)
			self::includeFile('update/90001.php');			
		// Show an info message after upgrade/install
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
	 * Upgrade outdated site settings
	 */
	function updateSite() {
		self::$arySettings = array(
			'tracking_code' => '',
			'site_id' => get_option('wp-piwik_siteid', NULL),
			'last_tracking_code_update' => get_option('wp-piwik_scriptupdate', 0),
			'dashboard_revision' => get_option('wp-piwik_dashboardid', 0)
		);
		// Remove deprecated option values
		$aryRemoveOptions = array('wp-piwik_siteid','wp-piwik_404','wp-piwik_scriptupdate','wp-piwik_dashboardid','wp-piwik_jscode');
		foreach ($aryRemoveOptions as $strRemoveOption) delete_option($strRemoveOption);
		// Save upgraded or default settings
		self::saveSettings();
		// Reload settings
		self::loadSettings();
	}

	/**
	 * Send a message after installing/updating
	 */
	function updateMessage() {
		// Message text
		$strText = 'WP-Piwik '.self::$strVersion.' '.__('installed','wp-piwik').'.';
		// Next step information
		$strSettings = (empty(self::$aryGlobalSettings['piwik_token']) && empty(self::$aryGlobalSettings['piwik_url'])?
			__('Next you should connect to Piwik','wp-piwik'):
			__('Please validate your configuration','wp-piwik')
		);
		// Create settings Link
		$strLink = sprintf('<a href="options-general.php?page=%s">%s</a>', self::$strPluginBasename, __('Settings', 'wp-piwik'));
		// Display message
		echo '<div id="message" class="updated fade"><p>'.$strText.' <strong>'.__('Important', 'wp-piwik').':</strong> '.$strSettings.': '.$strLink.'.</p></div>';
	}
	
	/**
	 * Add tracking code
	 */
	function footer() {
		// Hotfix: Custom capability problem with WP multisite
		if (is_multisite()) {
			foreach (self::$aryGlobalSettings['capability_stealth'] as $strKey => $strVal)
				if ($strVal && current_user_can($strKey))
					return;
		// Don't add tracking code?
		} elseif (current_user_can('wp-piwik_stealth')) return;
		// Hotfix: Update network site if not done yet
		if (is_plugin_active_for_network('wp-piwik/wp-piwik.php') && get_option('wp-piwik_siteid', false)) $this->updateSite();
		// Autohandle site if no tracking code available
		if (empty(self::$arySettings['tracking_code']))
			$aryReturn = $this->addPiwikSite();
		// Update/get code if outdated/unknown
		if (self::$arySettings['last_tracking_code_update'] < self::$aryGlobalSettings['last_settings_update'] || empty(self::$arySettings['tracking_code'])) {
			$strJSCode = $this->callPiwikAPI('SitesManager.getJavascriptTag');
			self::$arySettings['tracking_code'] = html_entity_decode((is_string($strJSCode)?$strJSCode:'<!-- WP-Piwik ERROR: Tracking code not availbale -->'."\n"));
			self::$arySettings['last_tracking_code_update'] = time();
			self::saveSettings();
		}
		// Change code if 404
		if (is_404() and self::$aryGlobalSettings['track_404']) $strTrackingCode = str_replace('piwikTracker.trackPageView();', 'piwikTracker.setDocumentTitle(\'404/URL = \'+encodeURIComponent(document.location.pathname+document.location.search) + \'/From = \' + encodeURIComponent(document.referrer));piwikTracker.trackPageView();', self::$arySettings['tracking_code']);
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
				'wp-piwik_read_stats',
				'wp-piwik_stats',
				array($this, 'showStats')
			);
			// Add required scripts
			add_action('admin_print_scripts-'.$this->intStatsPage, array($this, 'loadScripts'));
			// Add required styles
			add_action('admin_print_styles-'.$this->intStatsPage, array($this, 'addAdminStyle'));
			// Add required header tags
			add_action('admin_head-'.$this->intStatsPage, array($this, 'addAdminHeader'));
			// Stats page onload callback
			add_action('load-'.$this->intStatsPage, array(&$this, 'onloadStatsPage'));
		}
		if (!is_plugin_active_for_network('wp-piwik/wp-piwik.php')) {
			// Add options page
			$intOptionsPage = add_options_page(
				__('WP-Piwik', 'wp-piwik'),
				__('WP-Piwik', 'wp-piwik'), 
				'activate_plugins',
				__FILE__,
				array($this, 'show_settings')
			);
			// Add styles required by options page
			add_action('admin_print_styles-'.$intOptionsPage, array($this, 'addAdminStyle'));
		}
	}

	/**
	 * Add pages to network admin menu
	 */
	function buildNetworkAdminMenu() {
		// Show stats dashboard page if WP-Piwik is configured
		if (!empty(self::$aryGlobalSettings['piwik_token']) && !empty(self::$aryGlobalSettings['piwik_url'])) {
			// Add dashboard page
			$this->intStatsPage = add_dashboard_page(
				__('Piwik Statistics', 'wp-piwik'), 
				__('WP-Piwik', 'wp-piwik'), 
				'manage_sites',
				'wp-piwik_stats',
				array($this, 'showStats')
			);
			// Add required scripts
			add_action('admin_print_scripts-'.$this->intStatsPage, array($this, 'loadScripts'));
			// Add required styles
			add_action('admin_print_styles-'.$this->intStatsPage, array($this, 'addAdminStyle'));
			// Add required header tags
			add_action('admin_head-'.$this->intStatsPage, array($this, 'addAdminHeader'));
			// Stats page onload callback
			add_action('load-'.$this->intStatsPage, array(&$this, 'onloadStatsPage'));
		}
        $intOptionsPage = add_submenu_page(
			'settings.php',
			__('WP-Piwik', 'wp-piwik'),
			__('WP-Piwik', 'wp-piwik'),
			'manage_sites',
			__FILE__,
			array($this, 'show_settings')
		);
		
		// Add styles required by options page
		add_action('admin_print_styles-'.$intOptionsPage, array($this, 'addAdminStyle'));
	}
	
	/**
	 * Support two columns 
	 * seen in Heiko Rabe's metabox demo plugin 
	 * 
	 * @see http://tinyurl.com/5r5vnzs 
	 */ 
	function onScreenLayoutColumns($aryColumns, $strScreen) {		
		if ($strScreen == $this->intStatsPage)
			$aryColumns[$this->intStatsPage] = 4;
		return $aryColumns;
	}
	
	/**
	 * Add widgets to WordPress dashboard
	 */
	function extendWordPressDashboard() {
		// Is user allowed to see stats?
		if (current_user_can('wp-piwik_read_stats')) {
			// TODO: Use bitmask here
			// Add data widget if enabled
			if (self::$aryGlobalSettings['dashboard_widget'])
				$this->addWordPressDashboardWidget();
			// Add chart widget if enabled
			if (self::$aryGlobalSettings['dashboard_chart']) {				
				// Add required scripts
				add_action('admin_print_scripts-index.php', array($this, 'loadScripts'));
				// Add required styles
				add_action('admin_print_styles-index.php', array($this, 'addAdminStyle'));
				// Add required header tags
				add_action('admin_head-index.php', array($this, 'addAdminHeader'));
				$this->addWordPressDashboardChart();
			}
			// Add SEO widget if enabled
			if (self::$aryGlobalSettings['dashboard_seo'])
				$this->addWordPressDashboardSEO();
		}
	}

	/**
     * Add a data widget to the WordPress dashboard
	 */
	function addWordPressDashboardWidget() {
		$aryConfig = array(
			'params' => array('period' => 'day','date'  => self::$aryGlobalSettings['dashboard_widget'],'limit' => null),
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
	function addWordPressDashboardChart() {
		$aryConfig = array(
			'params' => array('period' => 'day','date'  => 'last30','limit' => null),
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
	 * Add a SEO widget to the WordPress dashboard
	 */
	function addWordPressDashboardSEO() {
		$aryConfig = array(
			'params' => array('period' => 'day','date'  => self::$aryGlobalSettings['dashboard_widget'],'limit' => null),
			'inline' => true,			
		);
		$strFile = 'seo';
		add_meta_box(
				'wp-piwik_stats-dashboard-seo', 
				__('WP-Piwik', 'wp-piwik').' - '.__('SEO', 'wp-piwik'), 
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
	function loadScripts() {
		// Load WP-Piwik script
		wp_enqueue_script('wp-piwik', $this->getPluginURL().'js/wp-piwik.js', array(), self::$strVersion, true);
		// Load jqPlot
		wp_enqueue_script('wp-piwik-jqplot',$this->getPluginURL().'js/jqplot/wp-piwik.jqplot.js',array('jquery'));
	}

	/**
	 * Load required styles to admin pages
	 */
	function addAdminStyle() {
		// Load WP-Piwik styles
		wp_enqueue_style('wp-piwik', $this->getPluginURL().'css/wp-piwik.css');
	}

	/**
	 * Add required header tags to admin pages
	 */
	function addAdminHeader() {
		// Load jqPlot IE compatibility script
		echo '<!--[if IE]><script language="javascript" type="text/javascript" src="'.$this->getPluginURL().'js/jqplot/excanvas.min.js"></script><![endif]-->';
		// Load jqPlot styles
		echo '<link rel="stylesheet" href="'.$this->getPluginURL().'js/jqplot/jquery.jqplot.min.css" type="text/css"/>';
		echo '<script type="text/javascript">var $j = jQuery.noConflict();</script>';
	}
	
	/**
	 * Get this plugin's URL
	 */
	function getPluginURL() {
		// Return plugins URL + /wp-piwik/
		return trailingslashit(plugins_url().'/wp-piwik/');
	}

	/**
	 * Get remote file
	 * 
	 * @param String $strURL Remote file URL
	 */
	function getRemoteFile($strURL) {
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

	/**
	 * Add a new site to Piwik if a new blog was requested,
	 * or get its ID by URL
	 */ 
	function addPiwikSite() {
		$strBlogURL = get_bloginfo('url');
		$strURL = self::$aryGlobalSettings['piwik_url'];
		// Check if blog URL already known
		if (substr($strURL, -1, 1) != '/') $strURL .= '/';
		$strURL .= '?module=API&method=SitesManager.getSitesIdFromSiteUrl';
		$strURL .= '&url='.urlencode($strBlogURL);
		$strURL .= '&format=PHP';
		$strURL .= '&token_auth='.self::$aryGlobalSettings['piwik_token'];
		$aryResult = unserialize($this->getRemoteFile($strURL));
		if (!empty($aryResult) && isset($aryResult[0]['idsite'])) {
			self::$arySettings['site_id'] = $aryResult[0]['idsite'];
			self::$arySettings['last_tracking_code_update'] = time();
		// Otherwise create new site
		} elseif (!empty(self::$aryGlobalSettings['piwik_token']) && !empty($strURL)) {
			$strName = get_bloginfo('name');
			if (substr($strURL, -1, 1) != '/') $strURL .= '/';
			$strURL .= '?module=API&method=SitesManager.addSite';
			$strURL .= '&siteName='.urlencode($strName).'&urls='.urlencode($strBlogURL);
			$strURL .= '&format=PHP';
			$strURL .= '&token_auth='.self::$aryGlobalSettings['piwik_token'];
			$strResult = unserialize($this->getRemoteFile($strURL));
			if (!empty($strResult)) self::$arySettings['site_id'] = $strResult;
		}
		// Store new data
		self::$arySettings['tracking_code'] = html_entity_decode($this->callPiwikAPI('SitesManager.getJavascriptTag'));
		self::$arySettings['last_tracking_code_update'] = time();
		// Change Tracking code if configured
		self::$arySettings['tracking_code'] = $this->applyJSCodeChanges(self::$arySettings['tracking_code']);
		self::saveSettings();
		return array('js' => self::$arySettings['tracking_code'], 'id' => self::$arySettings['site_id']);
	}

	/**
	 * Apply configured Tracking Code changes
	 */
	function applyJSCodeChanges($strCode) {
		// Change code if js/index.php should be used
		if (self::$aryGlobalSettings['track_compress']) $strCode = str_replace('pkBaseURL + "piwik.js\'', 'pkBaseURL + "js/\'', $strCode);
		// Change code if POST is forced to be used
		if (self::$aryGlobalSettings['track_post']) $strCode = str_replace('piwikTracker.trackPageView();', 'piwikTracker.setRequestMethod(\'POST\');'."\n".'  piwikTracker.trackPageView();', $strCode);
		return $strCode;
	}
	
	/**
	 * Create a WordPress dashboard widget
	 */
	function createDashboardWidget($objPost, $aryMetabox) {
		// Create description and ID
		$strDesc = $strID = '';
		$aryConfig = $aryMetabox['args']['aryConfig'];
		foreach ($aryConfig['params'] as $strParam)
			if (!empty($strParam)) {
				$strDesc .= $strParam.', ';
				$strID .= '_'.$strParam;
			}
		// Remove dots from filename
		$strFile = str_replace('.', '', $aryMetabox['args']['strFile']);
		// Finalize configuration
		$aryConf = array_merge($aryConfig, array(
			'id' => $strFile.$strID,
			'desc' => substr($strDesc, 0, -2)));
		// Include widget file
		if (file_exists(dirname(__FILE__).DIRECTORY_SEPARATOR.'dashboard/'.$strFile.'.php'))
			include(dirname(__FILE__).DIRECTORY_SEPARATOR.'dashboard/'.$strFile.'.php');
 	}

	/**
	 * Call Piwik's API
	 */
	function callPiwikAPI($strMethod, $strPeriod='', $strDate='', $intLimit='',$bolExpanded=false, $intId = false, $strFormat = 'PHP') {
		// Create unique cache key
		$strKey = $strMethod.'_'.$strPeriod.'_'.$strDate.'_'.$intLimit;
		// Call API if data not cached
		if (empty($this->aryCache[$strKey])) {
			$strToken = self::$aryGlobalSettings['piwik_token'];
			$strURL = self::$aryGlobalSettings['piwik_url'];
			// If multisite stats are shown, maybe the super admin wants to show other blog's stats.
			if (is_plugin_active_for_network('wp-piwik/wp-piwik.php') && function_exists('is_super_admin') && function_exists('wp_get_current_user') && is_super_admin() && isset($_GET['wpmu_show_stats'])) {
				$aryOptions = get_blog_option((int) $_GET['wpmu_show_stats'], 'wp-piwik_settings' , array());
				if (!empty($aryOptions) && isset($aryOptions['site_id']))
					$intSite = $aryOptions['site_id'];
				else $intSite = self::$arySettings['site_id'];
			// Otherwise use the current site's id.
			} else $intSite = self::$arySettings['site_id'];
			// Create error message if WP-Piwik isn't configured
			if (empty($strToken) || empty($strURL)) {
				$this->aryCache[$key] = array(
					'result' => 'error',
					'message' => 'Piwik base URL or auth token not set.'
				);
				return $this->aryCache[$strKey];
			}
			// Build URL			
			$strURL .= '?module=API&method='.$strMethod;
			$strURL .= '&idSite='.$intSite.'&period='.$strPeriod.'&date='.$strDate;
			$strURL .= '&filter_limit='.$intLimit;
			$strURL .= '&token_auth='.$strToken;
			$strURL .= '&expanded='.$bolExpanded;
			$strURL .= '&url='.urlencode(get_bloginfo('url'));
			$strURL .= '&format='.$strFormat;			
			// Fetch data if site exists
			if (!empty($intSite) || $strMethod='SitesManager.getSitesWithAtLeastViewAccess') {
				$strResult = (string) $this->getRemoteFile($strURL);			
				$this->aryCache[$strKey] = ($strFormat == 'PHP'?unserialize($strResult):$strResult);
			// Otherwise return error message
			} else $this->aryCache[$strKey] = array('result' => 'error', 'message' => 'Unknown site/blog.');
		}
		return $this->aryCache[$strKey];	
	}
 	
	/* TODO: Add post stats
	 * function display_post_unique_column($aryCols) {
	 * 	$aryCols['wp-piwik_unique'] = __('Unique');
	 *        return $aryCols;
	 * }
	 *
	 * function display_post_unique_content($strCol, $intID) {
	 *	if( $strCol == 'wp-piwik_unique' ) {
	 *	}
	 * }
	 */

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
				'seo' => array(__('SEO', 'wp-piwik'), 'day', 'yesterday'),
				'pages' => array(__('Pages', 'wp-piwik'), 'day', 'yesterday'),
				'keywords' => array(__('Keywords', 'wp-piwik'), 'day', 'yesterday', 10),
				'websites' => array(__('Websites', 'wp-piwik'), 'day', 'yesterday', 10),
				'plugins' => array(__('Plugins', 'wp-piwik'), 'day', 'yesterday'),
			),
			'normal' => array(
				'visitors' => array(__('Visitors', 'wp-piwik'), 'day', 'last30'),
				'browsers' => array(__('Browser', 'wp-piwik'), 'day', 'yesterday'),
				'screens' => array(__('Resolution', 'wp-piwik'), 'day', 'yesterday'),
				'systems' => array(__('Operating System', 'wp-piwik'), 'day', 'yesterday')
			)
		);
		// Don't show SEO stats if disabled
		if (!self::$aryGlobalSettings['stats_seo'])
			unset($arySortOrder['side']['seo']);
			
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
				$aryConfig['params']['title'].' '.($aryConfig['params']['title']!='SEO'?__($aryConfig['params']['date'], 'wp-piwik'):''), 
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
				$aryConfig['params']['title'].' '.($aryConfig['params']['title']!='SEO'?__($aryConfig['params']['date'], 'wp-piwik'):''),
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
		if (is_plugin_active_for_network('wp-piwik/wp-piwik.php') && function_exists('is_super_admin') && is_super_admin()) {
			global $blog_id;
			global $wpdb;
			$aryBlogs = $wpdb->get_results($wpdb->prepare('SELECT blog_id FROM '.$wpdb->prefix.'blogs ORDER BY blog_id'));			
			if (isset($_GET['wpmu_show_stats'])) {
				switch_to_blog((int) $_GET['wpmu_show_stats']);
				self::loadSettings();
			}
			echo '<form method="GET" action="">'."\n";
			echo '<input type="hidden" name="page" value="wp-piwik_stats" />';
			echo '<input type="hidden" name="date" value="'.(isset($_GET['date']) && preg_match('/^[0-9]{8}$/', $_GET['date'])?$_GET['date']:'').'" />';
			echo '<select name="wpmu_show_stats">'."\n";
			$aryOptions = array();
			foreach ($aryBlogs as $aryBlog) {
				$objBlog = get_blog_details($aryBlog->blog_id, true);
				$aryOptions[$objBlog->blogname.'#'.$objBlog->blog_id] = '<option value="'.$objBlog->blog_id.'"'.($blog_id == $objBlog->blog_id?' selected="selected"':'').'>'.$objBlog->blogname.'</option>'."\n";
			}
			// Show blogs in alphabetical order
			ksort($aryOptions);
			foreach ($aryOptions as $strOption) echo $strOption;
			echo '</select><input type="submit" value="'.__('Change').'" />'."\n ";
			echo __('Currently shown stats:').' <a href="'.get_bloginfo('url').'">'.get_bloginfo('name').'</a>'."\n";			
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
				<?php do_meta_boxes($this->intStatsPage, 'side', ''); ?>
			</div>
        	<div id="post-body" class="has-sidebar">
					<div id="post-body-content" class="postbox-container has-sidebar-content">
					<?php $meta_boxes = do_meta_boxes($this->intStatsPage, 'normal', ''); ?>
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
		if (is_plugin_active_for_network('wp-piwik/wp-piwik.php') && function_exists('is_super_admin') && is_super_admin()) {
			restore_current_blog();
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
		
	/**
	 * Apply & store new settings
	 */
	function applySettings() {
		self::$aryGlobalSettings['add_tracking_code']  		= (isset($_POST['wp-piwik_addjs'])?$_POST['wp-piwik_addjs']:'');
		self::$aryGlobalSettings['dashboard_widget'] 		= (isset($_POST['wp-piwik_dbwidget'])?$_POST['wp-piwik_dbwidget']:false);
		self::$aryGlobalSettings['dashboard_chart']			= (isset($_POST['wp-piwik_dbchart'])?$_POST['wp-piwik_dbchart']:false);
		self::$aryGlobalSettings['dashboard_seo']			= (isset($_POST['wp-piwik_dbseo'])?$_POST['wp-piwik_dbseo']:false);
		self::$aryGlobalSettings['stats_seo']				= (isset($_POST['wp-piwik_statsseo'])?$_POST['wp-piwik_statsseo']:false);
		self::$aryGlobalSettings['piwik_shortcut']	 		= (isset($_POST['wp-piwik_piwiklink'])?$_POST['wp-piwik_piwiklink']:false);
		self::$aryGlobalSettings['default_date']	 		= (isset($_POST['wp-piwik_default_date'])?$_POST['wp-piwik_default_date']:'yesterday');
		self::$aryGlobalSettings['piwik_token'] 		 	= (isset($_POST['wp-piwik_token'])?$_POST['wp-piwik_token']:'');
		self::$aryGlobalSettings['piwik_url']				= self::check_url((isset($_POST['wp-piwik_url'])?$_POST['wp-piwik_url']:''));
		self::$aryGlobalSettings['capability_stealth'] 		= (isset($_POST['wp-piwik_filter'])?$_POST['wp-piwik_filter']:array());
		self::$aryGlobalSettings['capability_read_stats'] 	= (isset($_POST['wp-piwik_displayto'])?$_POST['wp-piwik_displayto']:array());
		self::$aryGlobalSettings['last_settings_update'] 	= time();
		self::$aryGlobalSettings['track_404']			 	= (isset($_POST['wp-piwik_404'])?$_POST['wp-piwik_404']:false);
		self::$aryGlobalSettings['track_compress']			= (isset($_POST['wp-piwik_compress'])?$_POST['wp-piwik_compress']:false);
		self::$aryGlobalSettings['track_post']				= (isset($_POST['wp-piwik_reqpost'])?$_POST['wp-piwik_reqpost']:false);		
		if (!is_plugin_active_for_network('wp-piwik/wp-piwik.php')) {
			self::$aryGlobalSettings['auto_site_config'] 	= (isset($_POST['wp-piwik_auto_site_config'])?$_POST['wp-piwik_auto_site_config']:false);
			if (!self::$aryGlobalSettings['auto_site_config'])
				self::$arySettings['site_id']				= (isset($_POST['wp-piwik_siteid'])?$_POST['wp-piwik_siteid']:NULL);
		} else self::$aryGlobalSettings['auto_site_config'] = true;
		if (self::$aryGlobalSettings['auto_site_config']) {
			$aryReturn = $this->addPiwikSite();
			self::$arySettings['tracking_code'] = $aryReturn['js'];
			self::$arySettings['site_id'] = $aryReturn['id'];
		}
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
	
	<div class="wp-piwik-sidebox">
		<div class="wp-piwik-support">	
			<p><strong>Support</strong></p>
			<p><a href="http://peepbo.de/board/viewforum.php?f=3"><?php _e('WP-Piwik support board','wp-piwik'); ?></a> (<?php _e('no registration required, English &amp; German','wp-piwik'); ?>)</p>
			<p><a href="http://wordpress.org/tags/wp-piwik?forum_id=10"><?php _e('WordPress.org forum about WP-Piwik','wp-piwik'); ?></a> (<?php _e('WordPress.org registration required, English','wp-piwik'); ?>)</p>
			<p><?php _e('Please don\'t forget to vote the compatibility at the','wp-piwik'); ?> <a href="http://wordpress.org/extend/plugins/wp-piwik/">WordPress.org Plugin Directory</a>.</p>
		</div>
		<div class="wp-piwik-donate">
			<p><strong><?php _e('Donate','wp-piwik'); ?></strong></p>
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
				<a href="http://www.amazon.de/gp/registry/wishlist/111VUJT4HP1RA?reveal=unpurchased&amp;filter=all&amp;sort=priority&amp;layout=standard&amp;x=12&amp;y=14"><?php _e('My Amazon.de wishlist', 'wp-piwik'); ?></a>
			</div>
		</div>
	</div>

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
							<?php if (!is_plugin_active_for_network('wp-piwik/wp-piwik.php')) { ?>
							<h4><label for="wp-piwik_addjs"><?php _e('Auto config', 'wp-piwik') ?>:</label></h4>
								<div class="input-wrap">
									<input type="checkbox" value="1" id="wp-piwik_auto_site_config" name="wp-piwik_auto_site_config"<?php echo (self::$aryGlobalSettings['auto_site_config']?' checked="checked"':'') ?>/>
								</div>
								<div class="wp-piwik_desc">
                                    <?php _e('Check this to automatically choose your blog from your Piwik sites by URL. If your blog is not added to Piwik yet, WP-Piwik will add a new site.', 'wp-piwik') ?>
                                </div>
							<?php } ?>
								
<?php /************************************************************************/
		if (!empty($strToken) && !empty($strURL)) { 
			$aryData = $this->callPiwikAPI('SitesManager.getSitesWithAtLeastViewAccess');
			if (empty($aryData)) {
				echo '<div class="wp-piwik_desc"><strong>'.__('An error occured', 'wp-piwik').': </strong>'.
					__('Please check URL and auth token. You need at least view access to one site.', 'wp-piwik').
					'</div>';
			} elseif (isset($aryData['result']) && $aryData['result'] == 'error') {
				echo '<div class="wp-piwik_desc"><strong><strong>'.__('An error occured', 'wp-piwik').
					': </strong>'.$aryData['message'].'</div>';
			} else {
				if (!is_plugin_active_for_network('wp-piwik/wp-piwik.php')) {
					if (empty($intSite)) {
						if (self::$aryGlobalSettings['auto_site_config'])
							$aryReturn = $this->addPiwikSite();
						else {
							self::$arySettings['site_id'] = $aryData[0]['idsite'];
							self::saveSettings();
						}
						$intSite = self::$arySettings['site_id'];
					}
					if (!self::$aryGlobalSettings['auto_site_config']) {
						echo '<h4><label for="wp-piwik_siteid">'.__('Choose site', 'wp-piwik').':</label></h4>';
						echo '<div class="input-wrap">';
						echo '<select name="wp-piwik_siteid" id="wp-piwik_siteid">';
						$aryOptions = array();
						foreach ($aryData as $arySite)
							$aryOptions[$arySite['name'].'#'.$arySite['idsite']] = '<option value="'.$arySite['idsite'].
								'"'.($arySite['idsite']==$intSite?' selected="selected"':'').
								'>'.htmlentities($arySite['name'], ENT_QUOTES, 'utf-8').
								'</option>';
						
						ksort($aryOptions);
						foreach ($aryOptions as $strOption) echo $strOption;
						echo '</select></div>';
					} else {
						echo '<h4><label for="wp-piwik_siteid">'.__('Determined site', 'wp-piwik').':</label></h4>';
						echo '<div class="input-text-wrap">';
						foreach ($aryData as $arySite) 
							if ($arySite['idsite'] == $intSite) {
								echo '<input type="text" value="'.htmlentities($arySite['name'], ENT_QUOTES, 'utf-8').'" disabled="disabled" />';
								break;
							}
						echo '</div>';
						echo '<input type="hidden" name="wp-piwik_siteid" id="wp-piwik_siteid" value="'.$intSite.'" />';
					}
				}
				$intSite = self::$arySettings['site_id'];
				$int404 = self::$aryGlobalSettings['track_404'];
				$intAddJS = self::$aryGlobalSettings['add_tracking_code'];
				$intDashboardWidget = self::$aryGlobalSettings['dashboard_widget'];
				$intShowLink = self::$aryGlobalSettings['piwik_shortcut'];
				$strJavaScript = html_entity_decode($this->callPiwikAPI('SitesManager.getJavascriptTag'));
				if ($intAddJS) {
					// Change Tracking code if configured
					$strJavaScript = $this->applyJSCodeChanges($strJavaScript);					
					// Save javascript code
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
						(is_plugin_active_for_network('wp-piwik/wp-piwik.php')?'*** SITE SPECIFIC EXAMPLE CODE ***'."\n":'').
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
						__('If you add the Piwik javascript code by wp_footer(),', 'wp-piwik').' '.
							__('WP-Piwik can automatically add a 404-category to track 404-page-visits.', 'wp-piwik').
						'</div>';
						
				echo '<h4><label for="wp-piwik_compress">'.__('Use js/index.php', 'wp-piwik').':</label></h4>'.
						'<div class="input-wrap"><input type="checkbox" value="1" id="wp-piwik_compress" name="wp-piwik_compress" '.
						(self::$aryGlobalSettings['track_compress']?' checked="checked"':'').'/></div>';
				echo '<div class="wp-piwik_desc">'.
						__('If you add the Piwik javascript code by wp_footer(),', 'wp-piwik').' '.
							__('WP-Piwik can automatically use js/index.php instead of piwik.js. See', 'wp-piwik').' <a href="http://demo.piwik.org/js/README">js/README</a>.'.
						'</div>';

				echo '<h4><label for="wp-piwik_reqpost">'.__('Avoid mod_security', 'wp-piwik').':</label></h4>'.
						'<div class="input-wrap"><input type="checkbox" value="1" id="wp-piwik_reqpost" name="wp-piwik_reqpost" '.
						(self::$aryGlobalSettings['track_post']?' checked="checked"':'').'/></div>';
				echo '<div class="wp-piwik_desc">'.
						__('If you add the Piwik javascript code by wp_footer(),', 'wp-piwik').' '.
							__('WP-Piwik can automatically force the Tracking Code to sent data in POST. See', 'wp-piwik').' <a href="http://piwik.org/faq/troubleshooting/#faq_100">Piwik FAQ</a>.'.
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
				echo '<h4><label for="wp-piwik_dbwidget">'.__('Home Dashboard', 'wp-piwik').':</label></h4>'.
						'<div class="input-wrap"><select id="wp-piwik_dbwidget" name="wp-piwik_dbwidget">'.
						'<option value="0"'.(!$intDashboardWidget?' selected="selected"':'').'>'.__('Hide overview', 'wp-piwik').'</option>'.
						'<option value="yesterday"'.($intDashboardWidget == 'yesterday'?' selected="selected"':'').'>'.__('Show Overview','wp-piwik').' ('.__('yesterday', 'wp-piwik').').</option>'.
						'<option value="today"'.($intDashboardWidget == 'today'?' selected="selected"':'').'>'.__('Show overview','wp-piwik').' ('.__('today', 'wp-piwik').').</option>'.
						'<option value="last30"'.($intDashboardWidget == 'last30'?' selected="selected"':'').'>'.__('Show overview','wp-piwik').' ('.__('last 30 days','wp-piwik').').</option>'.
						'</select>';
				echo ' &nbsp; <input type="checkbox" value="1" name="wp-piwik_dbchart" id="wp-piwik_dbchart" '.
						(self::$aryGlobalSettings['dashboard_chart']?' checked="checked"':"").'/> '.__('Chart');
				echo ' &nbsp; <input type="checkbox" value="1" name="wp-piwik_dbseo" id="wp-piwik_dbseo" '.
						(self::$aryGlobalSettings['dashboard_seo']?' checked="checked"':"").'/> '.__('SEO <em>(slow!)</em>', 'wp-piwik');
				echo '</div>';
				echo '<div class="wp-piwik_desc">'.
					__('Configure WP-Piwik widgets to be shown on your WordPress Home Dashboard.', 'wp-piwik').'</div>';
					
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
					
				echo '<h4><label for="wp-piwik_piwiklink">'.__('SEO data', 'wp-piwik').':</label></h4>'.
						'<div class="input-wrap"><input type="checkbox" value="1" name="wp-piwik_statsseo" id="wp-piwik_statsseo" '.
						(self::$aryGlobalSettings['stats_seo']?' checked="checked"':"").'/></div>';
				echo '<div class="wp-piwik_desc">'.
					__('Display SEO ranking data on statistics page. <em>(Slow!)</em>', 'wp-piwik').'</div>';
					
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

	private static function isSSL() {
		return (isset($_SERVER['HTTPS']) && !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off');
	}
	
	function credits() {
/***************************************************************************/ ?>
	<h2 style="clear:left;"><?php _e('Credits', 'wp-piwik'); ?></h2>
	<div class="inside">
		<p><strong><?php _e('Thank you very much for your donation', 'wp-piwik'); ?>:</strong> Marco L., Rolf W., Tobias U., Lars K., Donna F., <?php _e('the Piwik team itself','wp-piwik');?> <?php _e('and all people flattering this','wp-piwik'); ?>!</p>
		<p><?php _e('Graphs powered by <a href="http://www.jqplot.com/">jqPlot</a>, an open source project by Chris Leonello. Give it a try! (License: GPL 2.0 and MIT)','wp-piwik'); ?></p>
		<p><?php _e('Metabox support inspired by', 'wp-piwik'); echo ' <a href="http://www.code-styling.de/english/how-to-use-wordpress-metaboxes-at-own-plugins">Heiko Rabe\'s metabox demo plugin</a>.'?></p>
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
