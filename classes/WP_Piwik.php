<?php

class WP_Piwik {

	private static
		$intRevisionId = 94000,
		$strVersion = '0.10.RC1',
		$blog_id,
		$intDashboardID = 30,
		$strPluginBasename = NULL,
		$bolJustActivated = false,
		$logger,
		$settings,
		$request;
				
	private
		$bolNetwork = false,
		$aryAttributes = array(),
		$strResult = '';

	public function __construct() {
		global $blog_id;
		self::$blog_id = (isset($blog_id)?$blog_id:'n/a');
		$this->openLogger();
		$this->openSettings();
		$this->setup();
		$this->addFilters();
		$this->addActions();
		$this->addShortcodes();
		self::$settings->save();
	}

	public function __destruct() {
		$this->closeLogger();
	}

	private function setup() {
		self::$strPluginBasename = plugin_basename(__FILE__);
		register_activation_hook(__FILE__, array($this, 'installPlugin'));
		if ($this->isUpdated())
			$this->upgradePlugin();
		if ($this->isConfigSubmitted())
			$this->applySettings();
		if ($this->isPHPMode())
			self::definePiwikConstants();
	}
	
	private function addActions() {
		add_action('admin_menu', array($this, 'buildAdminMenu'));
		add_action('admin_post_save_wp-piwik_stats', array(&$this, 'onStatsPageSaveChanges'));
		add_action('load-post.php', array(&$this, 'addPostMetaboxes'));
		add_action('load-post-new.php', array(&$this, 'addPostMetaboxes'));
		if ($this->isNetworkMode())
			add_action('network_admin_menu', array($this, 'buildNetworkAdminMenu'));
		if ($this->isDashboardActive())
			add_action('wp_dashboard_setup', array($this, 'extendWordPressDashboard'));
		if ($this->isToolbarActive()) {
			add_action(is_admin()?'admin_head':'wp_head', array($this, 'loadToolbarRequirements'));
			add_action('admin_bar_menu', array(&$this, 'extendWordPressToolbar'), 1000);
		}
		if ($this->isTrackingActive()) {
			add_action(self::$settings->getGlobalOption('track_codeposition') == 'footer'?'wp_footer':'wp_head', array($this, 'addJavascriptCode'));
			if ($this->isAddNoScriptCode())
				add_action('wp_footer', array($this, 'addNoscriptCode'));
			if ($this->isAdminTrackingActive())
				add_action(self::$settings->getGlobalOption('track_codeposition') == 'footer'?'admin_footer':'admin_head', array($this, 'addAdminHeaderTracking'));
		}
		if (self::$settings->getGlobalOption('add_post_annotations'))
			add_action('transition_post_status', array($this, 'onPostStatusTransition'),10, 3);
	}

	private function addFilters() {
		add_filter('plugin_row_meta', array($this, 'setPluginMeta'), 10, 2);
		add_filter('screen_layout_columns', array(&$this, 'onScreenLayoutColumns'), 10, 2);
		if ($this->isTrackingActive()) {
			if ($this->isTrackFeed()) {
				add_filter('the_excerpt_rss', array(&$this, 'addFeedTracking'));
				add_filter('the_content', array(&$this, 'addFeedTracking'));
			}
			if ($this->isAddFeedCampaign())
				add_filter('post_link', array(&$this, 'addFeedCampaign'));
		}
	}
		
	private function addShortcodes() {
		if ($this->isAddShortcode())
			add_shortcode('wp-piwik', array(&$this, 'shortcode'));
	}
	
	private function installPlugin() {
		self::$logger->log('Running WP-Piwik installation');
		add_action('admin_notices', array($this, 'updateMessage'));
		self::$bolJustActivated = true;
		self::$settings->setGlobalOption('revision', self::$intRevisionId);
		self::$settings->setGlobalOption('last_settings_update', time());
	}

	public static function uninstallPlugin() {
		self::$logger->log('Running WP-Piwik uninstallation');
		if (!defined('WP_UNINSTALL_PLUGIN'))
			exit();
		self::$settings->resetSettings(true);
	}

	private function upgradePlugin() {
		self::$logger->log('Upgrade WP-Piwik to '.self::$strVersion);
		add_action('admin_notices', array($this, 'updateMessage'));
		$patches = glob(dirname(__FILE__).DIRECTORY_SEPARATOR.'update'.DIRECTORY_SEPARATOR.'*.php');
		if (is_array($patches)) {
			sort($patches);
			foreach ($patches as $patch) {
				$patchVersion = (int) pathinfo($patch, PATHINFO_FILENAME);
				if ($patchVersion && self::$settings->getGlobalOption('revision') < $patchVersion)
					self::includeFile('update'.DIRECTORY_SEPARATOR.$patchVersion);
			} 
		}
		$this->installPlugin();	  
	}

	public function updateMessage() {
		$text = sprintf(__('%s %s installed.', 'wp-piwik'), self::$settings->getGlobalOption('plugin_display_name'), self::$strVersion);
		$notice = (!self::isConfigured()?
			__('Next you should connect to Piwik','wp-piwik'):
			__('Please validate your configuration','wp-piwik')
		);
		$link = sprintf('<a href="'.$this->getSettingsURL().'">%s</a>', __('Settings', 'wp-piwik'));
		printf('<div class="updated fade"><p>%s <strong>%s:</strong> %s: %s</p></div>', $text, __('Important', 'wp-piwik'), $notice, $link);
	}
	
	private function getSettingsURL() {
		return (self::$settings->checkNetworkActivation()?'settings':'options-general').'.php?page='.self::$strPluginBasename;
	}

	public function addJavascriptCode() {
		if ($this->isHiddenUser()) {
			self::$logger->log('Do not add tracking code to site (user should not be tracked) Blog ID: '.self::$blog_id.' Site ID: '.self::$settings->getOption('site_id'));
			return;
		}
		$trackingCode = new WP_Piwik\TrackingCode($this);
		$trackingCode->is404 = (is_404() && self::$settings->getGlobalOption('track_404'));
		$trackingCode->isSearch = (is_search() && self::$settings->getGlobalOption('track_search'));
		self::$logger->log('Add tracking code. Blog ID: '.self::$blog_id.' Site ID: '.self::$settings->getOption('site_id'));
		echo $trackingCode->getTrackingCode();
		// TODO: Move to a better position
		$strName = get_bloginfo('name');
		if (self::$settings->getOption('name') != $strName)
			$this->updatePiwikSite();
	}
		
	private function addNoscriptCode() {
		if ($this->isHiddenUser()) {
			self::$logger->log('Do not add noscript code to site (user should not be tracked) Blog ID: '.self::$blog_id.' Site ID: '.self::$settings->getOption('site_id'));
			return;
		}
		self::$logger->log('Add noscript code. Blog ID: '.self::$blog_id.' Site ID: '.self::$settings->getOption('site_id'));
		echo self::$settings->getOption('noscript_code')."\n";
	}
	
	public function addPostMetaboxes() {
		if (self::$settings->getGlobalOption('add_customvars_box')) {
			add_action('add_meta_boxes', array(new WP_Piwik\Template\MetaBoxCustomVars($this), 'addMetabox'));
			add_action('save_post', array(new WP_Piwik\Template\MetaBoxCustomVars($this), 'saveCustomVars'), 10, 2);
		}
		if (self::$settings->getGlobalOption('perpost_stats')) {
			add_action('add_meta_boxes', array(new WP_Piwik\Template\MetaBoxPerPostStats($this), 'addMetabox'));
		}
	}

	public function buildAdminMenu() {
		if (self::isConfigured()) {
			$statsPage = new WP_Piwik\Admin\Statistics($this);
			$pageID = add_dashboard_page(__('Piwik Statistics', 'wp-piwik'), self::$settings->getGlobalOption('plugin_display_name'), 'wp-piwik_read_stats', 'wp-piwik_stats', array($statsPage, 'show'));
			$statsPage->add($pageID);
		}
		if (!self::$settings->checkNetworkActivation()) {
			$optionsPage = new WP_Piwik\Admin\Settings($this);
			$optionsPageID = add_options_page(self::$settings->getGlobalOption('plugin_display_name'), self::$settings->getGlobalOption('plugin_display_name'), 'activate_plugins', __FILE__, array($optionsPage, 'show'));
			$optionsPage->add($optionsPageID);
		}
	}

	public function buildNetworkAdminMenu() {
		if (self::isConfigured()) {
			$statsPage = new WP_Piwik\Admin\Network($this);
			$pageID = add_dashboard_page(__('Piwik Statistics', 'wp-piwik'), self::$settings->getGlobalOption('plugin_display_name'), 'manage_sites', 'wp-piwik_stats', array($statsPage, 'show'));
			$statsPage->add($pageID);
		}
		$optionsPage = new WP_Piwik\Admin\Settings($this);
		$optionsPageID = add_submenu_page('settings.php', self::$settings->getGlobalOption('plugin_display_name'), self::$settings->getGlobalOption('plugin_display_name'), 'manage_sites', __FILE__, array($optionsPage, 'show'));
		$optionsPage->add($optionsPageID);
	}
	
	public function extendWordPressDashboard() {
		if (current_user_can('wp-piwik_read_stats')) {
			if (self::$settings->getGlobalOption('dashboard_widget'))
				new WP_Piwik\Widget\Overview($this, self::$settings);
			if (self::$settings->getGlobalOption('dashboard_chart'))
				new WP_Piwik\Widget\Chart($this, self::$settings);
			if (self::$settings->getGlobalOption('dashboard_seo'))
				new WP_Piwik\Widget\Seo($this, self::$settings);
		}
	}
	
	public function extendWordPressToolbar($toolbar) {
		if (current_user_can('wp-piwik_read_stats') && is_admin_bar_showing()) {
			$id = WP_Piwik\Request::register('VisitsSummary.getUniqueVisitors', array('period' => 'day', 'date' => 'last30'));
			$unique = $this->request($id);
			$graph = "<script type='text/javascript'>var \$jSpark = jQuery.noConflict();\$jSpark(function() {var piwikSparkVals=[".implode(',',$unique)."];\$jSpark('.wp-piwik_dynbar').sparkline(piwikSparkVals, {type: 'bar', barColor: '#ccc', barWidth:2});});</script><span class='wp-piwik_dynbar'>Loading...</span>";
			$toolbar->add_menu(array('id' => 'wp-piwik_stats', 'title' => $graph, 'href' => $this->getStatsURL()));
		}
	}

	public function setPluginMeta($links, $file) {
		if ($file == 'wp-piwik/wp-piwik.php') 
			return array_merge($links,array(sprintf('<a href="%s">%s</a>', self::getSettingsURL(), __('Settings', 'wp-piwik'))));
		return $links;
	}

	public function loadToolbarRequirements() {
		if (current_user_can('wp-piwik_read_stats') && is_admin_bar_showing()) {
			wp_enqueue_script('wp-piwik-sparkline', $this->getPluginURL().'js/sparkline/jquery.sparkline.min.js', array('jquery'), self::$strVersion);
			wp_enqueue_style('wp-piwik', $this->getPluginURL().'css/wp-piwik-spark.css', array(), self::$strVersion);
		}
	}

	public function addFeedTracking($content) {
		global $post;
		if(is_feed()) {
			self::$logger->log('Add tracking image to feed entry.');
			if (!self::$settings->getOption('site_id')) self::addPiwikSite();
			$title = the_title(null,null,false);
			$posturl = get_permalink($post->ID);
			$urlref = get_bloginfo('rss2_url');
			$url = self::$settings->getGlobalOption('piwik_url');
			if (substr($url, -10, 10) == '/index.php') $url = str_replace('/index.php', '/piwik.php', $url);
			else $url .= 'piwik.php';
			$trackingImage = $url.'?idsite='.self::$settings->getOption('site_id').'&amp;rec=1&amp;url='.urlencode($posturl).'&amp;action_name='.urlencode($title).'&amp;urlref='.urlencode($urlref);
			$content .= '<img src="'.$trackingImage.'" style="border:0;width:0;height:0" width="0" height="0" alt="" />';
		}
		return $content;
	}

	public function addFeedCampaign($permalink) {
		global $post;
		if(is_feed()) {
			self::$logger->log('Add campaign to feed permalink.');
			$sep = (strpos($permalink, '?') === false?'?':'&');
			$permalink .= $sep.'pk_campaign='.urlencode(self::$settings->getGlobalOption('track_feed_campaign')).'&pk_kwd='.urlencode($post->post_name);
		}
		return $permalink;
	}

	public function addPiwikAnnotation($postID) {
		$this->callPiwikAPI('Annotations.add', '', date('Y-m-d'), '', false, false, 'PHP', '', false, 'Published: '.get_post($postID)->post_title.' - URL: '.get_permalink($postID));
	}

	private function applySettings() {
		self::$settings->applyChanges();
		if (self::$settings->getGlobalOption('auto_site_config') && self::isConfigured()) {
			if (self::$settings->getGlobalOption('piwik_mode') == 'php' && !defined('PIWIK_INCLUDE_PATH')) 
				self::definePiwikConstants();
			$aryReturn = $this->addPiwikSite();
			self::$settings->getOption('tracking_code', $aryReturn['js']);
			self::$settings->getOption('site_id', $aryReturn['id']);
		}
	}
	
	public static function isConfigured() {
		return (
			self::$settings->getGlobalOption('piwik_token') 
			&& (
				(
					(self::$settings->getGlobalOption('piwik_mode') == 'http') && (self::$settings->getGlobalOption('piwik_url'))
				) || (
					(self::$settings->getGlobalOption('piwik_mode') == 'php') && (self::$settings->getGlobalOption('piwik_path'))
				)
			)
		);
	}
		
	private function isUpdated() {
		return self::$settings->getGlobalOption('revision') && self::$settings->getGlobalOption('revision') < self::$intRevisionId;
	}
	
	private function isConfigSubmitted() {
		return isset($_POST['action']) && $_POST['action'] == 'save_wp-piwik_settings';
	}
	
	private function isPHPMode() {
		return self::$settings->getGlobalOption('piwik_mode') && self::$settings->getGlobalOption('piwik_mode') == 'php';
	}
	
	private function isNetworkMode() {
		return self::$settings->checkNetworkActivation();
	}
	
	private function isDashboardActive() {
		return self::$settings->getGlobalOption('dashboard_widget') || self::$settings->getGlobalOption('dashboard_chart') || self::$settings->getGlobalOption('dashboard_seo');
	}
	
	private function isToolbarActive() {
		return self::$settings->getGlobalOption('toolbar');
	}
	
	private function isTrackingActive() {
		return self::$settings->getGlobalOption('add_tracking_code');
	}
	
	private function isAdminTrackingActive() {
		return self::$settings->getGlobalOption('track_admin') && is_admin();
	}
	
	private function isAddNoScriptCode() {
		return self::$settings->getGlobalOption('track_noscript');
	}
	
	private function isTrackFeed() {
		return self::$settings->getGlobalOption('track_feed');
	}
	
	private function isAddFeedCampaign() {
		return self::$settings->getGlobalOption('track_feed_addcampaign');
	}
	
	private function isAddShortcode() {
		return self::$settings->getGlobalOption('shortcodes');
	}

	private static function definePiwikConstants() {
	if (!defined('PIWIK_INCLUDE_PATH')) {
			@header('Content-type: text/xml');
			define('PIWIK_INCLUDE_PATH', self::$settings->getGlobalOption('piwik_path'));
			define('PIWIK_USER_PATH', self::$settings->getGlobalOption('piwik_path'));
			define('PIWIK_ENABLE_DISPATCH', false);
			define('PIWIK_ENABLE_ERROR_HANDLER', false);
			define('PIWIK_ENABLE_SESSION_START', false);
		}
	}
	
	private function openLogger() {
		switch (WP_PIWIK_ACTIVATE_LOGGER) {
			case 1:
				self::$logger = new WP_Piwik\Logger\Screen(__CLASS__);
			break;
			case 2:
				self::$logger = new WP_Piwik\Logger\File(__CLASS__);
			break;
			default:
				self::$logger = new WP_Piwik\Logger\Dummy(__CLASS__);
		}
	}
	
	public static function log($message) {
		self::$logger->log($message);
	}

	private function closeLogger() {
		self::$logger = null;
	}

	private function openSettings() {
		self::$settings = new WP_Piwik\Settings(self::$logger);
	}
	
	private function includeFile($strFile) {
		self::$logger->log('Include '.$strFile.'.php');
		if (WP_PIWIK_PATH.$strFile.'.php')
			include(WP_PIWIK_PATH.$strFile.'.php');
	}
	
	private function isHiddenUser() {
		if (is_multisite())
			foreach (self::$settings->getGlobalOption('capability_stealth') as $key => $val)
				if ($val && current_user_can($key)) return true;
		return current_user_can('wp-piwik_stealth');
	}
	
	public function isCurrentTrackingCode() {
		return (self::$settings->getOption('last_tracking_code_update') > self::$settings->getGlobalOption('last_settings_update'));
	}
	
	public function site_header() {
		self::$logger->log('Using deprecated function site_header');
		$this->addJavascriptCode();
	}
	
	public function site_footer() {
		self::$logger->log('Using deprecated function site_footer');
		$this->addNoscriptCode();
	}
	
	public function onPostStatusTransition($newStatus, $oldStatus, $post) {
		if ($newStatus == 'publish' && $oldStatus != 'publish' ) {
			add_action('publish_post', array($this, 'addPiwikAnnotation'));
		}
	}
	
	public function getPluginURL() {
		return trailingslashit(plugins_url().'/wp-piwik/');
	}

	public function getPluginVersion() {
		return self::$strVersion;
	}

	public function onScreenLayoutColumns($aryColumns, $strScreen) {		
		if ($strScreen == $this->intStatsPage)
			$aryColumns[$this->intStatsPage] = 3;
		return $aryColumns;
	}
	
	function addAdminHeaderTracking() {
		$this->site_header();	
	}
	
	public function getOption($key) {
		return self::$settings->getOption($key);
	}
	
	public function getStatsURL() {
		return admin_url().'?page=wp-piwik_stats';
	}
	
	public function updateTrackingCode() {
		// TODO: Add update method
	}
	
	public function request($id) {
		if (!isset(self::$request))
			if (self::$settings->getGlobalOption('piwik_mode') == 'http') self::$request = new WP_Piwik\Request\Rest($this, self::$settings);
			else self::$request = new WP\Piwik\Request\Php($this, self::$settings);
		return self::$request->perform($id);
	}

	private static function readRSSFeed($feed, $cnt = 5) {
 		$result = array();
		if (function_exists('simplexml_load_file') && !empty($feed)) {
			$xml = @simplexml_load_file($feed);
			if (!xml || !isset($xml->channel[0]->item))
				return array(array('title' => 'Can\'t read RSS feed.','url' => $xml));
 			foreach($xml->channel[0]->item as $item) {
				if ($cnt-- == 0) break;
				$result[] = array('title' => $item->title[0], 'url' => $item->link[0]);
			}
		}
		return $result;
	}
	
	public static function getSiteID($blogID = null) {
		$result = self::$settings->getOption('site_id');
		if (self::$settings->checkNetworkActivation() && !empty($blogID)) {
			$result = get_blog_option($blogID, 'wp-piwik_settings');
			$result = $result['site_id'];
		}
		return (is_int($result)?$result:'n/a');
	}
	
	// ------- END OF REFACTORING -------
	public function shortcode($attributes) {
		shortcode_atts(array(
			'title' => '',
			'module' => 'overview',
			'period' => 'day',
			'date' => 'yesterday',
			'limit' => 10,
			'width' => '100%',
			'height' => '200px',
			'language' => 'en',
			'range' => false,
			'key' => 'sum_daily_nb_uniq_visitors'
		), $attributes);
		switch ($attributes['module']) {
			case 'opt-out':
				return '<iframe frameborder="no" width="'.$attributes['width'].'" height="'.$attributes['height'].'" src="'.self::$settings->getGlobalOption('piwik_url').'index.php?module=CoreAdminHome&action=optOut&language='.$attributes['language'].'"></iframe>';
			break;
			case 'post':
				self::includeFile('shortcodes/post');
			break;
			case 'overview':
			default:
				self::includeFile('shortcodes/overview');
		}
	}
	
	/**
	 * Update a site 
	 */ 
	function updatePiwikSite() {
		$strBlogURL = get_bloginfo('url');
		// Check if blog URL already known
		$strName = get_bloginfo('name');
		if (empty($strName)) $strName = $strBlogURL;
		self::$settings->setOption('name', $strName);
		$strURL = '&method=SitesManager.updateSite';
		$strURL .= '&idSite='.self::$settings->getOption('site_id');
		$strURL .= '&siteName='.urlencode($strName).'&urls='.urlencode($strBlogURL);
		$strURL .= '&format=PHP';
		$strURL .= '&token_auth='.self::$settings->getGlobalOption('piwik_token');
		$strResult = unserialize($this->getRemoteFile($strURL));		
		// Store new data
		self::$settings->getOption('tracking_code', $this->callPiwikAPI('SitesManager.getJavascriptTag'));
		self::$settings->save();
	}
 	
	function onloadStatsPage($id) {
		$this->intStatsPage = $id;
		wp_enqueue_script('common');
		wp_enqueue_script('wp-lists');
		wp_enqueue_script('postbox');
		wp_enqueue_script('wp-piwik', $this->getPluginURL().'js/wp-piwik.js', array(), self::$strVersion, true);
		wp_enqueue_script('wp-piwik-jqplot',$this->getPluginURL().'js/jqplot/wp-piwik.jqplot.js',array('jquery'));
		$strToken = self::$settings->getGlobalOption('piwik_token');
		$strPiwikURL = self::$settings->getGlobalOption('piwik_url');
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
				'search' => array(__('Site Search Keywords', 'wp-piwik'), 'day', 'yesterday', 10),
				'noresult' => array(__('Site Search without Results', 'wp-piwik'), 'day', 'yesterday', 10),
			),
			'normal' => array(
				'visitors' => array(__('Visitors', 'wp-piwik'), 'day', 'last30'),
				'browsers' => array(__('Browser', 'wp-piwik'), 'day', 'yesterday'),
				'browserdetails' => array(__('Browser Details', 'wp-piwik'), 'day', 'yesterday'),
				'screens' => array(__('Resolution', 'wp-piwik'), 'day', 'yesterday'),
				'systems' => array(__('Operating System', 'wp-piwik'), 'day', 'yesterday')
			)
		);
		// Don't show SEO stats if disabled
		if (!self::$settings->getGlobalOption('stats_seo'))
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
						$aryDashboard[$strCol][$strFile]['params']['date'] = self::$settings->getGlobalOption('default_date');
			}
		}
		$intSideBoxCnt = $intContentBox = 0;
		foreach ($aryDashboard['side'] as $strFile => $aryConfig) {
			$intSideBoxCnt++;
			if (preg_match('/(\d{4})(\d{2})(\d{2})/', $aryConfig['params']['date'], $aryResult))
				$strDate = $aryResult[1]."-".$aryResult[2]."-".$aryResult[3];
			else $strDate = $aryConfig['params']['date'];
			add_meta_box(
				'wp-piwik_stats-sidebox-'.$intSideBoxCnt, 
				$aryConfig['params']['title'].' '.($aryConfig['params']['title']!='SEO'?__($strDate, 'wp-piwik'):''), 
				array(&$this, 'createDashboardWidget'), 
				$this->intStatsPage, 
				'side', 
				'core',
				array('strFile' => $strFile, 'aryConfig' => $aryConfig)
			);
		}
		foreach ($aryDashboard['normal'] as $strFile => $aryConfig) {
			if (preg_match('/(\d{4})(\d{2})(\d{2})/', $aryConfig['params']['date'], $aryResult))
				$strDate = $aryResult[1]."-".$aryResult[2]."-".$aryResult[3];
			else $strDate = $aryConfig['params']['date'];
			$intContentBox++;
			add_meta_box(
				'wp-piwik_stats-contentbox-'.$intContentBox, 
				$aryConfig['params']['title'].' '.($aryConfig['params']['title']!='SEO'?__($strDate, 'wp-piwik'):''),
				array(&$this, 'createDashboardWidget'), 
				$this->intStatsPage, 
				'normal', 
				'core',
				array('strFile' => $strFile, 'aryConfig' => $aryConfig)
			);
		}
	}
	
	// Open stats page as network admin
	function showStatsNetwork() {
		$this->bolNetwork = true;
		$this->showStats();
	}	
	
	function showStats() {
		// Disabled time limit if required
		if (self::$settings->getGlobalOption('disable_timelimit') && self::$settings->getGlobalOption('disable_timelimit')) 
			set_time_limit(0);
		//we need the global screen column value to be able to have a sidebar in WordPress 2.8
		global $screen_layout_columns;
		if (empty($screen_layout_columns)) $screen_layout_columns = 2;
/***************************************************************************/ ?>
<div id="wp-piwik-stats-general" class="wrap">
	<?php screen_icon('options-general'); ?>
	<h2><?php echo (self::$settings->getGlobalOption('plugin_display_name') == 'WP_Piwik'?'Piwik '.__('Statistics', 'wp-piwik'):self::$settings->getGlobalOption('plugin_display_name')); ?></h2>
<?php /************************************************************************/
		if (self::$settings->checkNetworkActivation() && function_exists('is_super_admin') && is_super_admin() && $this->bolNetwork) {
			if (isset($_GET['wpmu_show_stats'])) {
				switch_to_blog((int) $_GET['wpmu_show_stats']);
				// TODO OPTIMIZE
			} else {
				$this->includeFile('settings'.DIRECTORY_SEPARATOR.'sitebrowser');
				return;
			}
			echo '<p>'.__('Currently shown stats:').' <a href="'.get_bloginfo('url').'">'.(int) $_GET['wpmu_show_stats'].' - '.get_bloginfo('name').'</a>.'.' <a href="?page=wp-piwik_stats">Show site overview</a>.</p>'."\n";			
			echo '</form>'."\n";
		}
/***************************************************************************/ ?>
	<form action="admin-post.php" method="post">
		<?php wp_nonce_field('wp-piwik_stats-general'); ?>
		<?php wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false ); ?>
		<?php wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false ); ?>
		<input type="hidden" name="action" value="save_wp-piwik_stats_general" />		
		<div id="dashboard-widgets" class="metabox-holder columns-<?php echo $screen_layout_columns; ?><?php echo 2 <= $screen_layout_columns?' has-right-sidebar':''; ?>">
				<div id='postbox-container-1' class='postbox-container'>
					<?php $meta_boxes = do_meta_boxes($this->intStatsPage, 'normal', null); ?>	
				</div>
				
				<div id='postbox-container-2' class='postbox-container'>
					<?php do_meta_boxes($this->intStatsPage, 'side', null); ?>
				</div>
				
				<div id='postbox-container-3' class='postbox-container'>
					<?php do_meta_boxes($this->intStatsPage, 'column3', null); ?>
				</div>
				
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
		if (self::$settings->checkNetworkActivation() && function_exists('is_super_admin') && is_super_admin()) {
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
	 * Add tabs to settings page
	 * See http://wp.smashingmagazine.com/2011/10/20/create-tabs-wordpress-settings-pages/
	 */
	function showSettingsTabs($bolFull = true, $strCurr = 'homepage') {
		$aryTabs = ($bolFull?array(
			'homepage' => __('Home','wp-piwik'),
			'piwik' => __('Piwik Settings','wp-piwik'),
			'tracking' => __('Tracking','wp-piwik'),
			'views' => __('Statistics','wp-piwik'),
			'support' => __('Support','wp-piwik'),
			'credits' => __('Credits','wp-piwik')
		):array(
			'piwik' => __('Piwik Settings','wp-piwik'),
			'support' => __('Support','wp-piwik'),
			'credits' => __('Credits','wp-piwik')
		));
		if (empty($strCurr)) $strCurr = 'homepage';
		elseif (!isset($aryTabs[$strCurr]) && $strCurr != 'sitebrowser') $strCurr = 'piwik';
		echo '<div id="icon-themes" class="icon32"><br></div>';
		echo '<h2 class="nav-tab-wrapper">';
		foreach($aryTabs as $strTab => $strName) {
			$strClass = ($strTab == $strCurr?' nav-tab-active':'');
			echo '<a class="nav-tab'.$strClass.'" href="?page=wp-piwik/classes/WP_Piwik.php&tab='.$strTab.'">'.$strName.'</a>';
		}
		echo '</h2>';
		return $strCurr;
	}

	/**
	 * Show settings page
	 */
	function showSettings() {
		// Define globals and get request vars
		global $pagenow;
		$strTab = (isset($_GET['tab'])?$_GET['tab']:'homepage');
		// Show update message if stats saved
		if (isset($_POST['wp-piwik_settings_submit']) && $_POST['wp-piwik_settings_submit'] == 'Y')
			echo '<div id="message" class="updated fade"><p>'.__('Changes saved','wp-piwik').'</p></div>';
		// Show settings page title
		echo '<div class="wrap"><h2>'.self::$settings->getGlobalOption('plugin_display_name').' '.__('Settings', 'wp-piwik').'</h2>';
		// Show tabs
		$strTab = $this->showSettingsTabs(self::isConfigured(), $strTab);
		if ($strTab != 'sitebrowser') {
/***************************************************************************/ ?>
		<div class="wp-piwik-donate">
			<p><strong><?php _e('Donate','wp-piwik'); ?></strong></p>
			<p><?php _e('If you like WP-Piwik, you can support its development by a donation:', 'wp-piwik'); ?></p>
			<script type="text/javascript">
			/* <![CDATA[ */
			window.onload = function() {
        		FlattrLoader.render({
            		'uid': 'flattr',
            		'url': 'http://wp.local',
            		'title': 'Title of the thing',
            		'description': 'Description of the thing'
				}, 'element_id', 'replace');
			}
			/* ]]> */
			</script>
			<div>
				<a class="FlattrButton" style="display:none;" title="WordPress Plugin WP-Piwik" rel="flattr;uid:braekling;category:software;tags:wordpress,piwik,plugin,statistics;" href="https://www.braekling.de/wp-piwik-wpmu-piwik-wordpress">This WordPress plugin adds a Piwik stats site to your WordPress dashboard. It's also able to add the Piwik tracking code to your blog using wp_footer. You need a running Piwik installation and at least view access to your stats.</a>
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
			<div>
				<?php _e('Please don\'t forget to vote the compatibility at the','wp-piwik'); ?> <a href="http://wordpress.org/extend/plugins/wp-piwik/">WordPress.org Plugin Directory</a>. 
			</div>
		</div>
<?php /***************************************************************************/
		}
		echo '<form class="'.($strTab != 'sitebrowser'?'wp-piwik-settings':'').'" method="post" action="'.admin_url(($pagenow == 'settings.php'?'network/':'').$pagenow.'?page=wp-piwik/classes/WP_Piwik.php&tab='.$strTab).'">';
		echo '<input type="hidden" name="action" value="save_wp-piwik_settings" />';
		wp_nonce_field('wp-piwik_settings');
		// Show settings
		if (($pagenow == 'options-general.php' || $pagenow == 'settings.php') && $_GET['page'] == 'wp-piwik/classes/WP_Piwik.php') {
			echo '<table class="wp-piwik-form-table form-table">';
			// Get tab contents
			$this->includeFile('settings'.DIRECTORY_SEPARATOR.$strTab);				
		// Show submit button
			if (!in_array($strTab, array('homepage','credits','support','sitebrowser')))
				echo '<tr><td><p class="submit" style="clear: both;padding:0;margin:0"><input type="submit" name="Submit"  class="button-primary" value="'.__('Save settings', 'wp-piwik').'" /><input type="hidden" name="wp-piwik_settings_submit" value="Y" /></p></td></tr>';
			echo '</table>';
		}
		// Close form
		echo '</form></div>';
	}

	/**
	 * Show an error message extended by a support site link
	 */
	private static function showErrorMessage($strMessage) {
		echo '<strong class="wp-piwik-error">'.__('An error occured', 'wp-piwik').':</strong> '.$strMessage.' [<a href="'.(self::$settings->checkNetworkActivation()?'network/settings':'options-general').'.php?page=wp-piwik/wp-piwik.php&tab=support">'.__('Support','wp-piwik').'</a>]';
	}

	/**
	 * Execute test script
	 */
	private function loadTestscript() {
		$this->includeFile('debug'.DIRECTORY_SEPARATOR.'testscript');
	}

	/**
	 * Add a new site to Piwik if a new blog was requested,
	 * or get its ID by URL
	 */ 
	function addPiwikSite() {
		if (isset($_GET['wpmu_show_stats']) && self::$settings->checkNetworkActivation()) {
			self::$logger->log('Switch blog ID: '.(int) $_GET['wpmu_show_stats']);
			switch_to_blog((int) $_GET['wpmu_show_stats']);
		}
		self::$logger->log('Get the blog\'s site ID by URL: '.get_bloginfo('url'));
		// Check if blog URL already known
		$strURL = '&method=SitesManager.getSitesIdFromSiteUrl';
		$strURL .= '&format=PHP';
		$strURL .= '&token_auth='.self::$settings->getGlobalOption('piwik_token');
		//$aryResult = unserialize($this->getRemoteFile($strURL, get_bloginfo('url')));
		$aryResult[0]['idsite'] = 2;
		if (!empty($aryResult) && isset($aryResult[0]['idsite'])) {
			self::$settings->setOption('site_id', (int) $aryResult[0]['idsite']);
		// Otherwise create new site
		} elseif (self::isConfigured() && !empty($strURL)) {
			self::$logger->log('Blog not known yet - create new site');
			$strName = get_bloginfo('name');
			if (empty($strName)) $strName = get_bloginfo('url');
			self::$settings->setOption('name', $strName);
			$strURL .= '&method=SitesManager.addSite';
			$strURL .= '&siteName='.urlencode($strName).'&urls='.urlencode(get_bloginfo('url'));
			$strURL .= '&format=PHP';
			$strURL .= '&token_auth='.self::$settings->getGlobalOption('piwik_token');
			$strResult = unserialize($this->getRemoteFile($strURL, get_bloginfo('url')));
			if (!empty($strResult)) self::$settings->setOption('site_id', (int) $strResult);
		}
		// Store new data if site created
		if (self::$settings->getOption('site_id')) {
			self::$logger->log('Get the site\'s tracking code');
			self::$settings->setOption('tracking_code', $this->callPiwikAPI('SitesManager.getJavascriptTag'));
		} else self::$settings->getOption('tracking_code', '');
		self::$settings->save();
		if (isset($_GET['wpmu_show_stats']) && self::$settings->checkNetworkActivation()) {
			self::$logger->log('Back to current blog');
			restore_current_blog();
		}
		return array('js' => self::$settings->getOption('tracking_code'), 'id' => self::$settings->getOption('site_id'));
	}
	
		/* TODO: Add post stats
	 * function display_post_unique_column($aryCols) {
	 * 	$aryCols['wp-piwik_unique'] = __('Unique');
	 *		return $aryCols;
	 * }
	 *
	 * function display_post_unique_content($strCol, $intID) {
	 *	if( $strCol == 'wp-piwik_unique' ) {
	 *	}
	 * }
	 */
	
}