<?php
/*
Plugin Name: WP-Piwik

Plugin URI: http://www.braekling.de/wp-piwik-wpmu-piwik-wordpress/

Description: Adds Piwik stats to your dashboard menu and Piwik code to your wordpress footer.

Version: 0.8.0
Author: Andr&eacute; Br&auml;kling
Author URI: http://www.braekling.de

****************************************************************************************** 
	Copyright (C) 2009-2010 Andre Braekling (email: webmaster@braekling.de)

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

class wp_piwik {

	public static $intRevisionId = 19;
	public static $intDashboardID = 6;
	public static $bolWPMU = false;
	public static $bolOverall = false;

	function __construct() {
		if (isset($GLOBALS['wp-piwik_wpmu']) && $GLOBALS['wp-piwik_wpmu']) {
			self::$bolWPMU = true;
			$intCurrentRevision = get_site_option('wpmu-piwik_revision', 0);
		} else $intCurrentRevision = get_option('wp-piwik_revision',0);
		if ($intCurrentRevision < self::$intRevisionId) $this->install();
		$strLocale = get_locale();
		if ( !empty( $strLocale ) ) {
			$strMOfile = ABSPATH . 'wp-content/'.
							(self::$bolWPMU?'mu-':'').'plugins/'.
							basename(dirname(__FILE__)).'/languages/wp-piwik-'.$strLocale.'.mo';
			load_textdomain('wp-piwik', $strMOfile);
		}
		
		register_activation_hook(__FILE__, array($this, 'install'));

		if (!self::$bolWPMU)
			add_filter('plugin_row_meta', array($this, 'set_plugin_meta'), 10, 2);

		if (self::$bolWPMU || (get_option('wp-piwik_addjs') == 1)) 
			add_action('wp_footer', array($this, 'footer'));

		add_action('admin_menu', array($this, 'build_menu'));

		$intDashboardWidget = get_option('wp-piwik_dbwidget', 0);
		if ($intDashboardWidget > 0) 
			add_action('wp_dashboard_setup', array($this, 'extend_wp_dashboard_setup'));
	}

	function install() {
		if (self::$bolWPMU)
			update_site_option('wpmu-piwik_revision', self::$intRevisionId);
		else
			update_option('wp-piwik_revision', self::$intRevisionId);
		delete_option('wp-piwik_disable_gapi');
		$intDisplayTo = get_option('wp-piwik_displayto', 8);
		update_option('wp-piwik_displayto', 'level_'.$intDisplayTo);
	}

	function footer() {
		global $current_user;
		get_currentuserinfo();
		$bolDisplay = true;
		$int404 = get_option('wp-piwik_404');
		if (!empty($current_user->roles)) {
			$aryFilter = (self::$bolWPMU?get_site_option('wpmu-piwik_filter'):get_option('wp-piwik_filter'));
			foreach ($current_user->roles as $strRole)
				if (isset($aryFilter[$strRole]) && $aryFilter[$strRole])
					$bolDisplay = false;
		}
		$strJSCode = get_option('wp-piwik_jscode', '');
		if (self::$bolWPMU && empty($strJSCode)) {
			$aryReturn = $this->create_wpmu_site();
			$strJSCode = $aryReturn['js'];
		}
		if (is_404() and $int404) $strJSCode = str_replace('piwikTracker.trackPageView();', 'piwikTracker.setDocumentTitle(\'404/URL = \'+encodeURIComponent(document.location.pathname+document.location.search) + \'/From = \' + encodeURIComponent(document.referrer));piwikTracker.trackPageView();', $strJSCode);
		if ($bolDisplay) echo $strJSCode;
	}

	function build_menu() {
		if (!self::$bolWPMU) {
			$intDisplayTo = get_option('wp-piwik_displayto', 'administrator');
			$strToken = get_option('wp-piwik_token');
			$strPiwikURL = get_option('wp-piwik_url');
			$bolDashboardWidget = get_option('wp-piwik_dbwidget', false);
		} else {
			$intDisplayTo = 'administrator';
			$strToken = get_site_option('wpmu-piwik_token');
			$strPiwikURL = get_site_option('wpmu-piwik_url');
			$bolDashboardWidget = false;
		}
		if (!empty($strToken) && !empty($strPiwikURL)) {
			$intStatsPage = add_dashboard_page(
				__('Piwik Statistics', 'wp-piwik'), 
				__('WP-Piwik', 'wp-piwik'), 
				$intDisplayTo,
				__FILE__,
				array($this, 'show_stats')
			);
			add_action('admin_print_scripts-'.$intStatsPage, array($this, 'load_scripts'));
			add_action('admin_print_styles-'.$intStatsPage, array($this, 'add_admin_style'));
			add_action('admin_head-'.$intStatsPage, array($this, 'add_admin_header'));
		}
		if (!self::$bolWPMU)
			$intOptionsPage = add_options_page(
				__('WP-Piwik', 'wp-piwik'),
				__('WP-Piwik', 'wp-piwik'), 
				'administrator', 
				__FILE__,
				array($this, 'show_settings')
			);
		elseif (is_site_admin())
			$intOptionsPage = add_options_page(
				__('WPMU-Piwik', 'wpmu-piwik'),
				__('WPMU-Piwik', 'wpmu-piwik'), 
				'administrator', 
				__FILE__,
				array($this, 'show_mu_settings')
			);
		add_action('admin_print_styles-'.$intOptionsPage, array($this, 'add_admin_style'));
	}

	function extend_wp_dashboard_setup() {
		$intDashboardWidget = get_option('wp-piwik_dbwidget');
		$arySub = array(
			1 => __('yesterday', 'wp-piwik'),
			2 => __('today', 'wp-piwik'),
			3 => __('last 30 days', 'wp-piwik')
		);
		$strTitle = __('WP-Piwik', 'wp-piwik').' - '.$arySub[$intDashboardWidget];

		wp_add_dashboard_widget(
			'wp-piwik_dashboard_widget',
			$strTitle,
			array (&$this, 'add_wp_dashboard_widget')
		);
	}

	function add_wp_dashboard_widget() {
		$intDashboardWidget = get_option('wp-piwik_dbwidget');
		$aryDate = array (
			1 => 'yesterday',
			2 => 'today',
			3 => 'last30'
		);
		$arySetup = array(
			'params' => array(
                		'period' => 'day',
                        	'date'   => $aryDate[$intDashboardWidget],
				'limit' => null
			),
			'inline' => true,			
		);
		$this->create_dashboard_widget('overview', $arySetup);
	}

	function set_plugin_meta($strLinks, $strFile) {
		$strPlugin = plugin_basename(__FILE__);
		if ($strFile == $strPlugin) 
			return array_merge(
				$strLinks,
				array(
					sprintf('<a href="options-general.php?page=%s">%s</a>', $strPlugin, __('Settings', 'wp-piwik'))
				)
			); 
		return $strLinks;
	}

	function load_scripts() {
		wp_enqueue_script(
			'wp-piwik',
			$this->get_plugin_url().(self::$bolWPMU?'wp-piwik/':'').'js/wp-piwik.js',
			array('jquery', 'admin-comments', 'postbox')
		);
		wp_enqueue_script(
			'wp-piwik-jqplot',
			$this->get_plugin_url().(self::$bolWPMU?'wp-piwik/':'').'js/jqplot/wp-piwik.jqplot.js',
			array('jquery')
		);
	}

	function add_admin_style() {
		wp_enqueue_style('wp-piwik', $this->get_plugin_url().(self::$bolWPMU?'wp-piwik/':'').'css/wp-piwik.css', array('dashboard'));
	}

	function add_admin_header() {		
		echo '<!--[if IE]><script language="javascript" type="text/javascript" src="'.$this->get_plugin_url().(self::$bolWPMU?'wp-piwik/':'').'js/jqplot/excanvas.min.js"></script><![endif]-->';
		echo '<link rel="stylesheet" href="'.$this->get_plugin_url().(self::$bolWPMU?'wp-piwik/':'').'js/jqplot/jquery.jqplot.min.css" type="text/css"/>';
	}
	
	function get_plugin_url() {
		return trailingslashit(WP_CONTENT_URL.'/'.(self::$bolWPMU?'mu-':'').'plugins/'.plugin_basename(dirname(__FILE__)));
	}

	function get_remote_file($strURL) {
		if (ini_get('allow_url_fopen'))
			$strResult = file_get_contents($strURL);
		elseif (function_exists('curl_init')) {
			$c = curl_init($strURL);
			curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($c, CURLOPT_HEADER, 0);
			$strResult = curl_exec($c);
			curl_close($c);
		} else $strResult = serialize(array(
				'result' => 'error',
				'message' => 'Remote access to Piwik not possible. Enable allow_url_fopen or CURL.'
			));
		return $strResult;
	}

	function call_API($strMethod, $strPeriod='', $strDate='', $intLimit='',$bolExpanded=false) {
		$strKey = $strMethod.'_'.$strPeriod.'_'.$strDate.'_'.$intLimit;
		if (empty($this->aryCache[$strKey])) {
			if (self::$bolWPMU) {
				$strToken = get_site_option('wpmu-piwik_token');
				$strURL = get_site_option('wpmu-piwik_url');
			} else {
				$strToken = get_option('wp-piwik_token');
				$strURL = get_option('wp-piwik_url');
			}
			$intSite = get_option('wp-piwik_siteid');
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
			if (substr($strURL, -1, 1) != '/') $strURL .= '/';
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
		$strToken = get_site_option('wpmu-piwik_token');
		$strURL = get_site_option('wpmu-piwik_url');
		$strJavaScript = '';
		$intSite = NULL;
		if (!empty($strToken) && !empty($strURL)) {
			$intSite = get_option('wp-piwik_siteid');
			if (empty($intSite)) {
				$strName = get_bloginfo('name');
				$strBlogURL = get_bloginfo('url');
				if (substr($strURL, -1, 1) != '/') $strURL .= '/';
				$strURL .= '?module=API&method=SitesManager.addSite';
				$strURL .= '&siteName='.urlencode('WPMU: '.$strName).'&urls='.urlencode($strBlogURL);
				$strURL .= '&format=PHP';
				$strURL .= '&token_auth='.$strToken;
				$strResult = unserialize($this->get_remote_file($strURL));
				if (!empty($strResult)) {
					update_option('wp-piwik_siteid', $strResult);
					$strJavaScript = $this->call_API('SitesManager.getJavascriptTag');
				}
			} else $strJavaScript = $this->call_API('SitesManager.getJavascriptTag');
			update_option('wp-piwik_jscode', $strJavaScript);
		}
		return array('js' => $strJavaScript, 'id' => $intSite);
	}

	function create_dashboard_widget($strFile, $aryConfig) {
		$strDesc = $strID = '';
		foreach ($aryConfig['params'] as $strParam)
			if (!empty($strParam)) {
				$strDesc .= $strParam.', ';
				$strID .= '_'.$strParam;
			}
		$strFile = str_replace('.', '', $strFile);
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

	function show_stats() {
		$strToken = get_option('wp-piwik_token');
		$strPiwikURL = get_option('wp-piwik_url');
		$arySortOrder = get_user_option('meta-box-order_wppiwik');
		$aryClosed = get_user_option('closedpostboxes_wppiwik');
		if (empty($aryClosed)) $aryClosed = array();
		$aryDashboard = array();
		$intCurrentDashboard = get_option('wp-piwik_dashboardid',0);
		if (!$arySortOrder) {
			// Set default configuration
			$arySortOrder = array(
				'side' => 'overview_day_yesterday,pages_day_yesterday,keywords_day_yesterday_10,websites_day_yesterday_10,plugins_day_yesterday',
				'normal' => 'visitors_day_last30,browsers_day_yesterday,screens_day_yesterday,systems_day_yesterday'
			);
			global $current_user;
			get_currentuserinfo();
			update_user_option($current_user->ID, 'meta-box-order_wppiwik', $arySortOrder);
			update_option('wp-piwik_dashboardid', self::$intDashboardID);
		} elseif ($intCurrentDashboard < self::$intDashboardID) {
			if ($intCurrentDashboard < 5) {
				$arySortOrder['normal'] .= ',screens_day_yesterday,systems_day_yesterday';
				$arySortOrder['side'] .= ',plugins_day_yesterday';
			}
			if ($intCurrentDashboard < 6) {
				$arySortOrder['side'] .= ',pages_day_yesterday';
			}
			global $current_user;
            		get_currentuserinfo();
			update_user_option($current_user->ID, 'meta-box-order_wppiwik', $arySortOrder);
			update_option('wp-piwik_dashboardid', self::$intDashboardID);
		}
		foreach ($arySortOrder as $strCol => $strWidgets) {
		$aryWidgets = explode(',', $strWidgets);
			if (is_array($aryWidgets)) foreach ($aryWidgets as $strParams) {
				$aryParams = explode('_', $strParams);
					$aryDashboard[$strCol][$aryParams[0]] = array(
						'params' => array(
							'period' => (isset($aryParams[1])?$aryParams[1]:''),
							'date'   => (isset($aryParams[2])?$aryParams[2]:''),
							'limit'  => (isset($aryParams[3])?$aryParams[3]:'')
						),
						'closed' => (in_array($strParams, $aryClosed))
					);
					if (isset($_GET['date']) && preg_match('/^[0-9]{8}$/', $_GET['date']) && $aryParams[0] != 'visitors')
						$aryDashboard[$strCol][$aryParams[0]]['params']['date'] = $_GET['date'];
			}
		}
/***************************************************************************/ ?>
<script type="text/javascript">var $j = jQuery.noConflict();</script>
<div class="wrap">
	<div id="icon-post" class="icon32"><br /></div>
	<h2><?php _e('Piwik Statistics', 'wp-piwik'); ?></h2>
<?php /************************************************************************/

		if (self::$bolWPMU && function_exists('is_site_admin') && is_site_admin()) {
			if (isset($_POST['wpmu_show_stats']))
				/*if ($_POST['wpmu_show_stats'] == 'all') self::$bolOverall = true;
				else*/ switch_to_blog((int) $_POST['wpmu_show_stats']);
			global $blog_id;
			$aryBlogs = get_blog_list(0, 'all');
			echo '<form method="POST" action="">'."\n";
			echo '<select name="wpmu_show_stats">'."\n";
			// echo '<option value="all">Overall stats</option>';
			foreach ($aryBlogs as $aryBlog) {
				$objBlog = get_blog_details($aryBlog['blog_id'], true);
				echo '<option value="'.$objBlog->blog_id.'"'.($blog_id == $objBlog->blog_id?' selected="selected"':'').'>'.$objBlog->blogname.'</option>'."\n";
			}
			echo '</select><input type="submit" value="'.__('Change').'" />'."\n ";
			if (!self::$bolOverall) echo __('Currently shown stats:').' <a href="'.get_bloginfo('url').'">'.get_bloginfo('name').'</a>'."\n";
			else _e('Current shown stats: <strong>Overall</strong>');
			echo '</form>'."\n";
		}

/***************************************************************************/ ?>
	<div id="dashboard-widgets-wrap">
		<div id="dashboard-widgets" class="metabox-holder">
			<div id="postbox-container" class="wp-piwik-side" style="width:290px; float:left;">
				<div id="side-sortables" class="meta-box-sortables ui-sortable wp-piwik-sortables">
<?php /************************************************************************/
		foreach ($aryDashboard['side'] as $strFile => $aryConfig)
		$this->create_dashboard_widget($strFile, $aryConfig);
/***************************************************************************/ ?>
				</div>
			</div>
			<div id="postbox-container" class="" style="width:520px; float:left; ">
				<div id="wppiwik-widgets-main-content" class="has-sidebar-content">
					<div id="normal-sortables" class="meta-box-sortables ui-sortable wp-piwik-sortables">
<?php /************************************************************************/
		foreach ($aryDashboard['normal'] as $strFile => $aryConfig)
			$this->create_dashboard_widget($strFile, $aryConfig);
		wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false);
		wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false);
/***************************************************************************/ ?>
						<div class="clear"></div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<?php /************************************************************************/
		if (self::$bolWPMU && function_exists('is_site_admin') && is_site_admin()) {
			restore_current_blog(); self::$bolOverall = false;
		}
	}

	function save_settings() {
		update_option('wp-piwik_token', $_POST['wp-piwik_token'],'');
		update_option('wp-piwik_url', $_POST['wp-piwik_url'],'');
		update_option('wp-piwik_siteid', $_POST['wp-piwik_siteid'],'');
		update_option('wp-piwik_addjs', $_POST['wp-piwik_addjs'],'');
		if (isset($_POST['wp-piwik_filter']))
			update_option('wp-piwik_filter', $_POST['wp-piwik_filter'],'');
		else
			update_option('wp-piwik_filter', array(),'');
		if (isset($_POST['wp-piwik_displayto'])) 
			update_option('wp-piwik_displayto', $_POST['wp-piwik_displayto']);
		else 
			update_option('wp-piwik_displayto', array('administrator'));
		update_option('wp-piwik_dbwidget', $_POST['wp-piwik_dbwidget'], 0);
		update_option('wp-piwik_piwiklink', $_POST['wp-piwik_piwiklink'], 0);
		update_option('wp-piwik_404', $_POST['wp-piwik_404'], 0);
	}

	function show_settings() { 
		
		if (isset($_POST['action']) && $_POST['action'] == 'save_settings')
			$this->save_settings();

		$strToken = get_option('wp-piwik_token');
		$strURL = get_option('wp-piwik_url');
		$intSite = get_option('wp-piwik_siteid');
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
				if (empty($intSite))
					update_option('wp-piwik_siteid', $aryData[0]['idsite']);
				$intSite = get_option('wp-piwik_siteid');
				$int404 = get_option('wp-piwik_404');
				$intAddJS = get_option('wp-piwik_addjs');
				$intDashboardWidget = get_option('wp-piwik_dbwidget');
				$intShowLink = get_option('wp-piwik_piwiklink');
				$strJavaScript = $this->call_API('SitesManager.getJavascriptTag');
				if ($intAddJS)
					update_option('wp-piwik_jscode', $strJavaScript);
/***************************************************************************/ ?>
<div><input type="submit" name="Submit" value="<?php _e('Save settings', 'wp-piwik') ?>" /></div>
					</div>
				</div>
				<div class="postbox wp-piwik-settings" >
					<h3 class='hndle'><span><?php _e('Tracking settings', 'wp-piwik'); ?></span></h3>
					<div class="inside">
<?php /************************************************************************/
				echo '<h4><label for="wp-piwik_jscode">JavaScript:</label></h4>'.
					'<div class="input-text-wrap"><textarea id="wp-piwik_jscode" name="wp-piwik_jscode" readonly="readonly" rows="17" cols="55">'.
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
				$aryFilter = get_option('wp-piwik_filter');
				foreach($wp_roles->role_names as $strKey => $strName)  {
					echo '<input type="checkbox" '.(isset($aryFilter[$strKey]) && $aryFilter[$strKey]?'checked="checked" ':'').'value="1" name="wp-piwik_filter['.$strKey.']" /> '.$strName.' &nbsp; ';
				}
				echo '</div>';
				echo '<div class="wp-piwik_desc">'.
					__('Choose users by user role you do <strong>not</strong> want to track.'.
					' Requires enabled &quot;Add script to wp_footer()&quot;-functionality.','wp-piwik').'</div>';
				/***************************************************************************/ ?>
<div><input type="submit" name="Submit" value="<?php _e('Save settings', 'wp-piwik') ?>" /></div>
						</div>
					</div>
					<div class="postbox wp-piwik-settings" >
						<h3 class='hndle'><span><?php _e('Statistic view settings', 'wp-piwik'); ?></span></h3>
						<div class="inside">
	<?php
				echo '<h4><label for="wp-piwik_dbwidget">'.__('Dashboard', 'wp-piwik').':</label></h4>'.
						'<div class="input-wrap"><select id="wp-piwik_dbwidget" name="wp-piwik_dbwidget">'.
						'<option value="0"'.($intDashboardWidget == 0?' selected="selected"':'').'>'.__('No', 'wp-piwik').'</option>'.
						'<option value="1"'.($intDashboardWidget == 1?' selected="selected"':'').'>'.__('Yes','wp-piwik').' ('.__('yesterday', 'wp-piwik').').</option>'.
						'<option value="2"'.($intDashboardWidget == 2?' selected="selected"':'').'>'.__('Yes','wp-piwik').' ('.__('today', 'wp-piwik').').</option>'.
						'<option value="3"'.($intDashboardWidget == 3?' selected="selected"':'').'>'.__('Yes','wp-piwik').' ('.__('last 30 days','wp-piwik').').</option>'.
						'</select></div>';
				echo '<div class="wp-piwik_desc">'.
					__('Display a dashboard widget to your WordPress dashboard.', 'wp-piwik').'</div>';
				echo '<h4><label for="wp-piwik_piwiklink">'.__('Shortcut', 'wp-piwik').':</label></h4>'.
						'<div class="input-wrap"><input type="checkbox" value="1" name="wp-piwik_piwiklink" id="wp-piwik_piwiklink" '.
						($intShowLink?' checked="checked"':"").'/></div>';
				echo '<div class="wp-piwik_desc">'.
					__('Display a shortcut to Piwik itself.', 'wp-piwik').'</div>';
				echo '<h4><label>'.__('Display to', 'wp-piwik').':</label></h4>';
				echo '<div class="input-wrap">';
				echo '<select name="wp-piwik_displayto">';
				$intDisplayTo = get_option('wp-piwik_displayto', 'level_8');
				foreach($wp_roles->role_names as $strKey => $strName) {
						$role = get_role($strKey);
						$intLevel = array_reduce( array_keys( $role->capabilities ), array( 'WP_User', 'level_reduction' ), 0 );
						echo '<option value="level_'.$intLevel.'"'.($intDisplayTo == 'level_'.$intLevel?' selected="selected"':'').'>'.$strName.'</option>';
				}
				echo '</select>';
				echo '</div><div class="wp-piwik_desc">'.
						__('Choose minimum role required to see the statistics page. (This setting will <strong>not</strong> affect the dashboard widget.)', 'wp-piwik').
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

	function save_mu_settings() {
		update_site_option('wpmu-piwik_token', $_POST['wp-piwik_token'],'');
		update_site_option('wpmu-piwik_url', $_POST['wp-piwik_url'],'');
		update_site_option('wpmu-piwik_filter', $_POST['wp-piwik_filter'],'');
	}

	function show_mu_settings() { 
		
		if (isset($_POST['action']) && $_POST['action'] == 'save_settings')
			$this->save_mu_settings();

		$strToken = get_site_option('wpmu-piwik_token');
		$strURL = get_site_option('wpmu-piwik_url');
/***************************************************************************/ ?>
<div class="wrap">
	<h2><?php _e('WPMU-Piwik Settings', 'wp-piwik') ?></h2>
	<?php $this->donate(); ?>
	<div class="inside">
		<form method="post" action="">
			<table class="form-table">
				<tr><td colspan="2"><h3><?php _e('Account settings', 'wp-piwik'); ?></h3></td></tr>
				<tr>
					<td><?php _e('Piwik URL', 'wp-piwik'); ?>:</td>
					<td><input type="text" name="wp-piwik_url" id="wp-piwik_url" value="<?php echo $strURL; ?>" /></td>
				</tr>
				<tr>
					<td><?php _e('Auth token', 'wp-piwik'); ?>:</td>
					<td><input type="text" name="wp-piwik_token" id="wp-piwik_token" value="<?php echo $strToken; ?>" /></td>
				</tr>
				<tr><td></td><td><span class="setting-description">
				<?php _e(
						'To enable Piwik statistics, please enter your Piwik'.
						' base URL (like http://mydomain.com/piwik) and your'.
						' personal authentification token. You can get the token'.
						' on the API page inside your Piwik interface. It looks'.
						' like &quot;1234a5cd6789e0a12345b678cd9012ef&quot;.'
						, 'wp-piwik'
				); ?><br />
				<?php _e(
						'<strong>Important note:</strong> You have to choose a token which provides administration access. WPMU-Piwik will create new Piwik sites for each blog if it is shown the first time and it is not added yet. All users can access their own statistics only, while site admins can access all statistics. To avoid conflicts, you should use a clean Piwik installation without other sites added. The provided themes should use wp_footer, because it adds the Piwik javascript code to each page.'
				);
				?>
				</span></td></tr>
<?php /************************************************************************/
		if (!empty($strToken) && !empty($strURL)) { 
			global $wp_roles;
			?><tr><td colspan="2"><h3><?php _e('Tracking settings', 'wp-piwik'); ?></h3></td></tr><?php
			echo '<tr><td>'.__('Tracking filter', 'wp-piwik').':</td><td>';
			$aryFilter = get_site_option('wpmu-piwik_filter');
			foreach($wp_roles->role_names as $strKey => $strName)  {
				echo '<input type="checkbox" '.(isset($aryFilter[$strKey]) && $aryFilter[$strKey]?'checked="checked" ':'').'value="1" name="wp-piwik_filter['.$strKey.']" /> '.$strName.' &nbsp; ';
			}
			echo '</td></tr>';
			echo '<tr><td></td><td><span class="setting-description">'.
					__('Choose users by user role you do <strong>not</strong> want to track.', 'wp-piwik').
					'</span></td></tr>';
		}
/***************************************************************************/ ?>
			</table>
			<input type="hidden" name="action" value="save_settings" />
			<p class="submit">
				<input type="submit" name="Submit" value="<?php _e('Save settings', 'wp-piwik') ?>" />
			</p>
		</form>
	</div>
	<?php $this->credits(); ?>
</div>
<?php /************************************************************************/
	}

	function donate() {
/***************************************************************************/ ?>
	<div class="wp-piwik-sidebox">
	<strong>Donate</strong>
	<p><?php _e('If you like WP-Piwik, you can support its development by a donation:'); ?></p>
	<div>
<script type="text/javascript">
	var flattr_url = 'http://www.braekling.de/wp-piwik-wpmu-piwik-wordpress';
</script>
<script src="http://api.flattr.com/button/load.js" type="text/javascript"></script>
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
		<a href="http://www.amazon.de/gp/registry/wishlist/111VUJT4HP1RA?reveal=unpurchased&amp;filter=all&amp;sort=priority&amp;layout=standard&amp;x=12&amp;y=14"><?php _e('My Amazon.de wishlist (German)'); ?></a>
	</div>
	</div>
<?php /************************************************************************/
	}

	function credits() {
/***************************************************************************/ ?>
	<h2 style="clear:left;">Credits</h2>
	<div class="inside">
		<p>Graphs powered by <a href="http://www.jqplot.com/">jqPlot</a>, an open source project by Chris Leonello. Give it a try! (License: GPL 2.0 and MIT)</p>
		<p>Thank you very much, <a href="http://blogu.programeshqip.org/">Besnik Bleta</a>, <a href="http://www.fatcow.com/">FatCow</a>, <a href="http://www.pamukkaleturkey.com/">Rene</a>, Fab and <a href="http://ezbizniz.com/">EzBizNiz</a> for your translation work!</p>
		<p>Thank you very much, all users who send me mails containing criticism, commendation, feature requests and bug reports! You help me to make WP-Piwik much better.</p>
		<p>Thank <strong>you</strong> for using my plugin. It is the best commendation if my piece of code is really used!</p>
	</div>
<?php /************************************************************************/
	}
}

if (class_exists('wp_piwik'))
	$GLOBALS['wp_piwik'] = new wp_piwik();

/* EOF */
