<?php

class WP_Piwik {

	private static
		$intRevisionId = 99904,
		$strVersion = '0.10.beta.1',
		$blog_id,
		$intDashboardID = 30,
		$strPluginBasename = NULL,
		$bolJustActivated = false,
		$logger,
		$settings,
		$request;
				
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
		if (!$this->isInstalled())
			$this->installPlugin();		
		elseif ($this->isUpdated())
			$this->updatePlugin();
		if ($this->isConfigSubmitted())
			$this->applySettings();
		if ($this->isPHPMode())
			self::definePiwikConstants();
	}
	
	private function addActions() {
		add_action('admin_notices', array($this, 'showNotices'));
		add_action('admin_init', array('WP_Piwik\Settings', 'registerSettings'));
		add_action('admin_menu', array($this, 'buildAdminMenu'));
		add_action('admin_post_save_wp-piwik_stats', array(&$this, 'onStatsPageSaveChanges'));
		add_action('load-post.php', array(&$this, 'addPostMetaboxes'));
		add_action('load-post-new.php', array(&$this, 'addPostMetaboxes'));
		if ($this->isNetworkMode()) {
			add_action('network_admin_menu', array($this, 'buildNetworkAdminMenu'));
			add_action('update_site_option_blogname', array(&$this, 'onBlogNameChange'));
			add_action('update_site_option_siteurl', array(&$this, 'onSiteUrlChange'));
		} else {
			add_action('update_option_blogname', array(&$this, 'onBlogNameChange'));
			add_action('update_option_siteurl', array(&$this, 'onSiteUrlChange'));
		}
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
	
	private function installPlugin($isUpdate = false) {
		self::$logger->log('Running WP-Piwik installation');
		if (!$isUpdate)
			$this->addNotice('install', sprintf(__('%s %s installed.', 'wp-piwik'), self::$settings->getGlobalOption('plugin_display_name'), self::$strVersion), __('Next you should connect to Piwik','wp-piwik'));			
		self::$settings->setGlobalOption('revision', self::$intRevisionId);
		self::$settings->setGlobalOption('last_settings_update', time());
	}

	public static function uninstallPlugin() {
		self::$logger->log('Running WP-Piwik uninstallation');
		if (!defined('WP_UNINSTALL_PLUGIN'))
			exit();
		delete_option('wp-piwik_notices');
		self::$settings->resetSettings(true);
	}

	private function updatePlugin() {
		self::$logger->log('Upgrade WP-Piwik to '.self::$strVersion);
		$patches = glob(dirname(__FILE__).DIRECTORY_SEPARATOR.'update'.DIRECTORY_SEPARATOR.'*.php');
		if (is_array($patches)) {
			sort($patches);
			foreach ($patches as $patch) {
				$patchVersion = (int) pathinfo($patch, PATHINFO_FILENAME);
				if ($patchVersion && self::$settings->getGlobalOption('revision') < $patchVersion)
					self::includeFile('update'.DIRECTORY_SEPARATOR.$patchVersion);
			} 
		}
		$this->addNotice('update', sprintf(__('%s updated to %s.', 'wp-piwik'), self::$settings->getGlobalOption('plugin_display_name'), self::$strVersion), __('Please validate your configuration','wp-piwik'));
		$this->installPlugin(true);	  
	}
	
	private function addNotice($type, $subject, $text, $stay = false) {
		$notices = get_option('wp-piwik_notices', array());
		$notices[$type] = array('subject' => $subject, 'text' => $text, 'stay' => $stay);
		update_option('wp-piwik_notices', $notices);
	}

	public function showNotices() {
		$link = sprintf('<a href="'.$this->getSettingsURL().'">%s</a>', __('Settings', 'wp-piwik'));
		if ($notices = get_option('wp-piwik_notices')) {
			foreach ($notices as $type => $notice) {
				printf('<div class="updated fade"><p>%s <strong>%s:</strong> %s: %s</p></div>', $notice['subject'], __('Important', 'wp-piwik'), $notice['text'], $link);
				if (!$notice['stay']) unset($notices[$type]);
			}
    	}
    	update_option('wp-piwik_notices', $notices);
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
	}
		
	public function addNoscriptCode() {
		if (self::$settings->getGlobalOption('track_mode') == 'proxy')
			return;
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
			$statsPage = new WP_Piwik\Admin\Statistics($this, self::$settings);
			$pageID = add_dashboard_page(__('Piwik Statistics', 'wp-piwik'), self::$settings->getGlobalOption('plugin_display_name'), 'wp-piwik_read_stats', 'wp-piwik_stats', array($statsPage, 'show'));
			$statsPage->add($pageID);
		}
		if (!self::$settings->checkNetworkActivation()) {
			$optionsPage = new WP_Piwik\Admin\Settings($this, self::$settings);
			$optionsPageID = add_options_page(self::$settings->getGlobalOption('plugin_display_name'), self::$settings->getGlobalOption('plugin_display_name'), 'activate_plugins', __FILE__, array($optionsPage, 'show'));
			$optionsPage->add($optionsPageID);
			add_action('admin_head-'.$optionsPageID, array($optionsPage, 'extendAdminHeader'));
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
			&& (self::$settings->getGlobalOption('piwik_mode') != 'disabled')
			&& (
				(
					(self::$settings->getGlobalOption('piwik_mode') == 'http') && (self::$settings->getGlobalOption('piwik_url'))
				) || (
					(self::$settings->getGlobalOption('piwik_mode') == 'php') && (self::$settings->getGlobalOption('piwik_path'))
				)|| (
					(self::$settings->getGlobalOption('piwik_mode') == 'pro') && (self::$settings->getGlobalOption('piwik_user'))
				)
			)
		);
	}
		
	private function isUpdated() {
		return self::$settings->getGlobalOption('revision') && self::$settings->getGlobalOption('revision') < self::$intRevisionId;
	}
	
	private function isInstalled() {
		return self::$settings->getGlobalOption('revision');
	}
	
	private function isConfigSubmitted() {
		return isset($_POST['action']) && $_POST['action'] == 'save_wp-piwik_settings';
	}
	
	public function isPHPMode() {
		return self::$settings->getGlobalOption('piwik_mode') && self::$settings->getGlobalOption('piwik_mode') == 'php';
	}
	
	public function isNetworkMode() {
		return self::$settings->checkNetworkActivation();
	}
	
	private function isDashboardActive() {
		return self::$settings->getGlobalOption('dashboard_widget') || self::$settings->getGlobalOption('dashboard_chart') || self::$settings->getGlobalOption('dashboard_seo');
	}
	
	private function isToolbarActive() {
		return self::$settings->getGlobalOption('toolbar');
	}
	
	private function isTrackingActive() {
		return self::$settings->getGlobalOption('track_mode') != 'disabled';
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

	public static function definePiwikConstants() {
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
		self::$settings = new WP_Piwik\Settings($this, self::$logger);
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
	
	private function loadTestscript() {
		$this->includeFile('debug'.DIRECTORY_SEPARATOR.'testscript');
	}

	private static function showErrorMessage($message) {
		echo '<strong class="wp-piwik-error">'.__('An error occured', 'wp-piwik').':</strong> '.$message.' [<a href="'.(self::$settings->checkNetworkActivation()?'network/settings':'options-general').'.php?page=wp-piwik/classes/WP_Piwik.php&tab=support">'.__('Support','wp-piwik').'</a>]';
	}
	
	public function request($id) {
		if (!isset(self::$request))
			self::$request = (
				self::$settings->getGlobalOption('piwik_mode') == 'http' || self::$settings->getGlobalOption('piwik_mode') == 'pro'?
				new WP_Piwik\Request\Rest($this, self::$settings):
				new WP_Piwik\Request\Php($this, self::$settings)
			);
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
		new \WP_Piwik\Shortcode($attributes);
	}
		
	public function getPiwikSiteId($blogId = null) {
		$result = self::$settings->getOption('site_id', $blogId);
		return (!empty($result) && !self::$settings->getGlobalOption('auto_site_config')?$result:$this->requestPiwikSiteId($blogId));
	}
	
	public function getPiwikSiteDetails() {
		$id = WP_Piwik\Request::register('SitesManager.getAllSites', array());			
		$piwikSiteDetails = $this->request($id);
		return $piwikSiteDetails;
	}
	
	private function requestPiwikSiteId($blogId = null) {
		$isCurrent = !self::$settings->checkNetworkActivation() || empty($blogId);		
		if (self::$settings->getGlobalOption('auto_site_config')) {
			$id = WP_Piwik\Request::register('SitesManager.getSitesIdFromSiteUrl', array(
				'url' => $isCurrent?get_bloginfo('url'):get_blog_details($blogId)->siteurl
			));			
			$result = $this->request($id);
			if (empty($result) || !isset($result[0]))
				$result = null; //$this->addPiwikSite($blogId);
			else 
				$result = $result[0]['idsite'];			
		} else $result = null;
		self::$logger->log('Get Piwik ID: WordPress site '.($isCurrent?get_bloginfo('url'):get_blog_details($blogId)->$siteurl).' = Piwik ID '.$result);
		if ($result !== null) {
			self::$settings->setOption('site_id', $result, $blogId);
			if (self::$settings->getGlobalOption('track_mode') != 'disabled' && self::$settings->getGlobalOption('track_mode') != 'manually') {
				$code = $this->updateTrackingCode($result, $blogId);			
				self::$settings->setOption('tracking_code', $code['script'], $blogId);
				self::$settings->setOption('noscript_code', $code['noscript'], $blogId);
			}
			$this::$settings->save();
			return $result;
		} return 'n/a';
	}

	private function addPiwikSite($blogId = null) {
		$isCurrent = !self::$settings->checkNetworkActivation() || empty($blogId);
		$id = WP_Piwik\Request::register('SitesManager.addSite', array(
			'urls' => $isCurrent?get_bloginfo('url'):get_blog_details($blogId)->$siteurl,
			'siteName' => $isCurrent?get_bloginfo('name'):get_blog_details($blogId)->$blogname
		));
		$result = $this->request($id);
		self::$logger->log('Get Piwik ID: WordPress site '.($isCurrent?get_bloginfo('url'):get_blog_details($blogId)->$siteurl).' = Piwik ID '.$result);
		if (empty($result) || !isset($result[0]))
			return null;
		else 
			return $result[0]['idsite'];
	}
	
	private function updatePiwikSite($siteId, $blogId = null) {
		$isCurrent = !self::$settings->checkNetworkActivation() || empty($blogId);
		$id = WP_Piwik\Request::register('SitesManager.updateSite', array(
			'idSite' => $siteId,
			'urls' => $isCurrent?get_bloginfo('url'):get_blog_details($blogId)->$siteurl,
			'siteName' => $isCurrent?get_bloginfo('name'):get_blog_details($blogId)->$blogname
		));
		$result = $this->request($id);
		self::$logger->log('Update Piwik site: WordPress site '.($isCurrent?get_bloginfo('url'):get_blog_details($blogId)->$siteurl));
	}

	public function updateTrackingCode($siteId = false, $blogId = null) {
		if (!$siteId)
			$siteId = $this->getPiwikSiteId();
		$id = WP_Piwik\Request::register('SitesManager.getJavascriptTag', array(
			'idSite' => $siteId,
		));
		$result = html_entity_decode($this->request($id));
		self::$logger->log('Delivered tracking code: '.$result);
		$result = WP_Piwik\TrackingCode::prepareTrackingCode($result, self::$settings, self::$logger);
		return $result;
	}

	public function onBlogNameChange($oldValue, $newValue) {
		$this->updatePiwikSite(self::$settings->getOption('site_id'));
	}

	public function onSiteUrlChange($oldValue, $newValue) {
		$this->updatePiwikSite(self::$settings->getOption('site_id'));
	}
	 	
	public function onloadStatsPage($statsPageId) {
		wp_enqueue_script('common');
		wp_enqueue_script('wp-lists');
		wp_enqueue_script('postbox');
		wp_enqueue_script('wp-piwik', $this->getPluginURL().'js/wp-piwik.js', array(), self::$strVersion, true);
		wp_enqueue_script('wp-piwik-jqplot',$this->getPluginURL().'js/jqplot/wp-piwik.jqplot.js', array('jquery'), self::$strVersion);
		/*$defaultOrder = array(
			'side' => array(				
				'seo' => (self::$settings->getGlobalOption('stats_seo')?array('title' => __('SEO', 'wp-piwik'), 'period' => 'day', 'date' => 'yesterday'):false),
				'pages' => array('title' => __('Pages', 'wp-piwik'), 'period' => 'day', 'date' => 'yesterday'),
				'keywords' => array('title' => __('Keywords', 'wp-piwik'), 'period' => 'day', 'date' => 'yesterday', 'limit' => 10),
				'websites' => array('title' => __('Websites', 'wp-piwik'), 'period' => 'day', 'date' => 'yesterday', 'limit' => 10),
				'plugins' => array('title' => __('Plugins', 'wp-piwik'), 'period' => 'day', 'date' => 'yesterday'),
				'search' => array('title' => __('Site Search Keywords', 'wp-piwik'), 'period' => 'day', 'date' => 'yesterday', 'limit' => 10),
				'noresult' => array('title' => __('Site Search without Results', 'wp-piwik'), 'period' => 'day', 'date' => 'yesterday', 'limit' => 10),
			),
			'normal' => array(
				'browsers' => array('title' => __('Browser', 'wp-piwik'), 'period' => 'day', 'date' => 'yesterday'),
				'browserdetails' => array('title' => __('Browser Details', 'wp-piwik'), 'period' => 'day', 'date' => 'yesterday'),
				'screens' => array('title' => __('Resolution', 'wp-piwik'), 'period' => 'day', 'date' => 'yesterday'),
				'systems' => array('title' => __('Operating System', 'wp-piwik'), 'period' => 'day', 'date' => 'yesterday')
			)
		);
		*/
		new \WP_Piwik\Widget\Chart($this, self::$settings, $statsPageId);
		new \WP_Piwik\Widget\Visitors($this, self::$settings, $statsPageId);
		new \WP_Piwik\Widget\Overview($this, self::$settings, $statsPageId);
	}	
	
	/* Stats page changes by POST submit
	   seen in Heiko Rabe's metabox demo plugin 
	   http://tinyurl.com/5r5vnzs */
	function onStatsPageSaveChanges() {
		if ( !current_user_can('manage_options') )
			wp_die( __('Cheatin&#8217; uh?') );			
		check_admin_referer('wp-piwik_stats');
		wp_redirect($_POST['_wp_http_referer']);		
	}

}