<?php
/*
Plugin Name: WP-Piwik

Plugin URI: http://dev.braekling.de/wordpress-plugins/dev/wp-piwik/index.html

Description: Adds Piwik stats to your dashboard menu and Piwik code to your wordpress footer.

Version: 0.3.2
Author: Andr&eacute; Br&auml;kling
Author URI: http://www.braekling.de

****************************************************************************************** 
	Copyright (C) 2009 Andre Braekling (email: webmaster@braekling.de)

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

	function __construct() {
		$strLocale = get_locale();
		if ( !empty( $strLocale ) ) {
			$strMOfile = ABSPATH . 'wp-content/plugins/'.basename(dirname(__FILE__)).'/languages/wp-piwik-'.$strLocale.'.mo';
			load_textdomain('wp-piwik', $strMOfile);
		}
		register_activation_hook(__FILE__, array($this, 'install'));
		add_action('admin_menu', array($this, 'build_menu'));
		add_filter('plugin_row_meta', array($this, 'set_plugin_meta'), 10, 2);
		if (get_option('wp-piwik_addjs') == 1) 
			add_action('wp_footer', array($this, 'footer'));
	}

	function install() {
		// not used
	}

	function footer() {
		echo get_option('wp-piwik_jscode');
	}

	function build_menu() {
		$intStatsPage = add_dashboard_page(
			__('Piwik Statistics', 'wp-piwik'), 
			__('WP-Piwik', 'wp-piwik'), 
			8,
			__FILE__,
			array($this, 'show_stats')
		);
		add_action('admin_print_scripts-'.$intStatsPage, array($this, 'load_scripts'));
		add_action('admin_head-'.$intStatsPage, array($this, 'add_admin_header'));

		add_options_page(
			__('WP-Piwik', 'wp-piwik'),
			__('WP-Piwik', 'wp-piwik'), 
			8, 
			__FILE__,
			array($this, 'show_settings')
		);
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
			$this->get_plugin_url().'js/wp-piwik.js',
			array('jquery', 'admin-comments', 'postbox')
		);
	}

	function add_admin_header() {
		echo '<link rel="stylesheet" href="'.$this->get_plugin_url().'css/wp-piwik.css" type="text/css"/>';
	}
	
	function get_plugin_url() {
		return trailingslashit(WP_CONTENT_URL.'/plugins/'.plugin_basename(dirname(__FILE__)));
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

	function call_API($strMethod, $strPeriod='', $strDate='', $intLimit='') {
		$strKey = $strMethod.'_'.$strPeriod.'_'.$strDate.'_'.$intLimit;
		if (empty($this->aryCache[$strKey])) {
			$strToken = get_option('wp-piwik_token');
			$strURL = get_option('wp-piwik_url');
			$intSite = get_option('wp-piwik_siteid');
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

			$strResult = $this->get_remote_file($strURL);
			$this->aryCache[$strKey] = unserialize($strResult);
		}
		return $this->aryCache[$strKey];
	}

	function create_dashboard_widget($strFile, $aryConfig) {
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

	function show_stats() {
		$arySortOrder = get_user_option('meta-box-order_wppiwik');
		$aryClosed = get_user_option('closedpostboxes_wppiwik');
		if (empty($aryClosed)) $aryClosed = array();
		$aryDashboard = array();
		if (!$arySortOrder) {
			// Set default configuration
			$arySortOrder = array(
				'side' => 'overview_day_yesterday,keywords_day_yesterday_10,websites_day_yesterday_10',
				'normal' => 'visitors_day_last30,browsers_day_yesterday'
			);
			update_user_option($GLOBALS['current_user']->ID, 'meta-box-order_wppiwik', $arySortOrder);
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
			}
		}
/***************************************************************************/ ?>
<div class="wrap">
	<div id="icon-post" class="icon32"><br /></div>
	<h2><?php _e('Piwik Statistics', 'wp-piwik'); ?></h2>
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
	}

	function show_settings() { 
		$strToken = get_option('wp-piwik_token');
		$strURL = get_option('wp-piwik_url');
		$intSite = get_option('wp-piwik_siteid');
/***************************************************************************/ ?>
<div class="wrap">
	<h2><?php _e('WP-Piwik Settings', 'wp-piwik') ?></h2>
	<div class="inside">
		<form method="post" action="options.php">
		<?php wp_nonce_field('update-options'); ?>
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
				<tr><td colspan="2"><span class="setting-description">
				<?php _e(
						'To enable Piwik statistics, please enter your Piwik'.
						' base URL (like http://mydomain.com/piwik) and your'.
						' personal authentification token. You can get the token'.
						' on the API page inside your Piwik interface. It looks'.
						' like &quot;1234a5cd6789e0a12345b678cd9012ef&quot;.'
						, 'wp-piwik'
				); ?>
				</span></td></tr>
<?php /************************************************************************/
		if (!empty($strToken) && !empty($strURL)) { 
			$aryData = $this->call_API('SitesManager.getSitesWithAtLeastViewAccess');
			if (empty($aryData)) {
				echo '<tr><td colspan="2"><p><strong>'.__('An error occured', 'wp-piwik').': </strong>'.
					__('Please check URL and auth token. You need at least view access to one site.', 'wp-piwik').
					'</p></td></tr>';
			} elseif ($aryData['result'] == 'error') {
				echo '<tr><td colspan="2"><p><strong>'.__('An error occured', 'wp-piwik').
					': </strong>'.$aryData['message'].'</p></td></tr>';
			} else {
				echo '<tr><td>'.__('Choose site', 'wp-piwik').
					':</td><td><select name="wp-piwik_siteid" id="wp-piwik_siteid">';
				foreach ($aryData as $arySite)
					echo '<option value="'.$arySite['idsite'].
						'"'.($arySite['idsite']==$intSite?' selected':'').
						'>'.htmlentities($arySite['name'], ENT_QUOTES, 'utf-8').
						'</option>';
				echo '</select></td></tr>';
				if (empty($intSite))
					update_option('wp-piwik_siteid', $aryData[0]['idsite']);
				$intSite = get_option('wp-piwik_siteid');
				$intAddJS = get_option('wp-piwik_addjs');
				$strJavaScript = $this->call_API('SitesManager.getJavascriptTag');
				if ($intAddJS)
					update_option('wp-piwik_jscode', $strJavaScript);
				echo '<tr><td>JavaScript:</td><td><textarea readonly rows="17" cols="80">'.
						($strJavaScript).'</textarea></td></tr>';
				echo '<tr><td>'.__('Add script to wp_footer()', 'wp-piwik').
						':</td><td><input type="checkbox" value="1" name="wp-piwik_addjs" '.
						($intAddJS?' checked':'').'/></td></tr>';
				echo '<tr><td colspan="2"><span class="setting-description">'.
						__('If your template uses wp_footer(), WP-Piwik can automatically'.
							' add the Piwik javascript code to your blog.', 'wp-piwik').
						'</span></td></tr>';
			}
		}
/***************************************************************************/ ?>
			</table>
			<input type="hidden" name="action" value="update" />
			<input type="hidden" name="page_options" value="wp-piwik_token,wp-piwik_url,wp-piwik_siteid,wp-piwik_addjs" />
			<p class="submit">
				<input type="submit" name="Submit" value="<?php _e('Save settings', 'wp-piwik') ?>" />
			</p>
		</form>
	</div>
</div>
<?php /************************************************************************/
	}
}

if (class_exists('wp_piwik'))
	$GLOBALS['wp_piwik'] = new wp_piwik();

/* EOF */
