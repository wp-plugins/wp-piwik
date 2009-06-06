<?php
/*
Plugin Name: WP-Piwik

Plugin URI: http://dev.braekling.de/wordpress-plugins/dev/wp-piwik/index.html

Description: Adds Piwik stats to your dashboard menu and Piwik code to your wordpress footer.

Version: 0.2.1
Author: Andr&eacute; Br&auml;kling
Author URI: http://www.braekling.de

****************************************************************************************** 
    Copyright (C) 2009    Andre Braekling   (email: webmaster@braekling.de)

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*******************************************************************************************/

class wp_piwik {

	function __construct() {
		register_activation_hook(__FILE__, array($this, 'install'));
		add_action('admin_menu', array($this, 'build_menu'));
		add_action('wp_ajax_meta-box-order', array($this, 'meta_box_order'));
		if (get_option('wp-piwik_addjs') == 1) 
			add_action('wp_footer', array($this, 'footer'));
	}
	
	function install() {
		
	}

	function footer() {
		echo get_option('wp-piwik_jscode');
	}

	function build_menu() {
		$intPage = add_dashboard_page(__('Piwik Statistics'), __('WP-Piwik'), 8, __FILE__, array($this, 'show_stats'));
		add_action('admin_print_scripts-'.$intPage, array($this, 'load_scripts'));
		add_action('admin_head-'.$intPage, array($this, 'add_admin_header'));

		add_options_page(__('WP-Piwik Settings'), __('WP-Piwik Settings'), 8, __FILE__, array($this, 'show_settings'));
	}

	function load_scripts() {
		wp_enqueue_script('wp-piwik', $this->get_plugin_url().'js/wp-piwik.js', array( 'jquery', 'admin-comments', 'postbox' ));
	}

	function add_admin_header() {
		echo '<link rel="stylesheet" href="'.$this->get_plugin_url().'css/wp-piwik.css" type="text/css"/>';
	}
	
	function get_plugin_url() {
		return trailingslashit(WP_CONTENT_URL.'/plugins/'.plugin_basename(dirname(__FILE__)));
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
                	$strResult = file_get_contents($strURL);
                	$this->aryCache[$strKey] = unserialize($strResult);
		}
		return $this->aryCache[$strKey];
	}

	function create_dashboard_widget($strFile, $aryConfig) {
		foreach ($aryConfig as $strParam) {
			$strDesc .= $strParam.', ';
                        $strID .= '_'.$strParam;
                }
		$strFile = str_replace('.', '', $strFile);

                $aryConf = array(
                	'id' => $strFile.$strID,
                       	'desc' => substr($strDesc, 0, -2),
                        'params' => $aryConfig,
                );

		$strRoot = dirname(__FILE__);
		if (file_exists($strRoot.DIRECTORY_SEPARATOR.'dashboard/'.$strFile.'.php'))
                	include($strRoot.DIRECTORY_SEPARATOR.'dashboard/'.$strFile.'.php');
 	}

	function meta_box_order() {
		echo "HALLO";
		return "WORLD";
	}

	function show_stats() {
		$aryDashboard = unserialize(get_option('wp-piwik_dashboard', false));
		if (!$aryDashboard) {
			// Set default configuration
			$aryDashboard = array(
				'normal' => array(
					'graph-visitors' => array('day', 'last30'),
					'table-visitors' => array('day', 'last30')),
				'side' => array(
					'overview' => array('day', 'yesterday'),
					'keywords' => array('day', 'yesterday', 10),
					'websites' => array('day', 'yesterday', 10))
				);
			update_option('wp-piwik_dashboard', serialize($aryDashboard));
		}
?>
<div class="wrap"><div id="icon-tools" class="icon32"><br /></div>
<h2><?php _e('Piwik Statistics'); ?></h2>
<div id="wppiwik-widgets-wrap">
<div id="wppiwik-widgets" class="metabox-holder">
<div id="side-info-column" class="inner-sidebar">
	<div id="side-sortables" class="meta-box-sortables">
<?php
foreach ($aryDashboard['side'] as $strFile => $aryConfig)
$this->create_dashboard_widget($strFile, $aryConfig);
?>
	</div>
</div>
<div id='post-body' class="has-sidebar">
<div id='wppiwik-widgets-main-content' class='has-sidebar-content'>
<div id='normal-sortables' class='meta-box-sortables'>
<?php
foreach ($aryDashboard['normal'] as $strFile => $aryConfig)
$this->create_dashboard_widget($strFile, $aryConfig);
wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );
?>

<div class="clear"></div>
</div><!-- wppiwik-widgets-wrap -->

</div><!-- wrap -->


<div class="clear"></div></div><!-- wpbody-content -->
<div class="clear"></div></div><!-- wpbody -->
<div class="clear"></div></div><!-- wpcontent -->
</div>
</div>
<?php
	}

	function show_settings() { 
		$strToken = get_option('wp-piwik_token');
		$strURL = get_option('wp-piwik_url');
		$intSite = get_option('wp-piwik_siteid');
?>
<div class="wrap">
<h2><?php _e('WP-Piwik Settings') ?></h2>
<div class="inside">
<form method="post" action="options.php">
	<?php wp_nonce_field('update-options'); ?>
<table class="form-table">
	<tr><td colspan="2"><h3><?php _e('Account settings'); ?></h3></td></tr>
	<tr>
		<td><?php _e('Piwik URL'); ?>:</td>
		<td><input type="text" name="wp-piwik_url" id="wp-piwik_url" value="<?php echo $strURL; ?>" /></td>
	</tr>
	<tr>
		<td><?php _e('Auth token'); ?>:</td>
		<td><input type="text" name="wp-piwik_token" id="wp-piwik_token" value="<?php echo $strToken; ?>" /></td>
	</tr>

	<tr><td colspan="2"><span class="setting-description"><?php _e('To enable Piwik statistics, please enter your Piwik base URL (like http://mydomain.com/piwik) and your personal authentification token. You can get the token on the API page inside your Piwik interface. It looks like &quot;1234a5cd6789e0a12345b678cd9012ef&quot;.'); ?></span></td></tr>
	<?php
		if (!empty($strToken) && !empty($strURL)) { 
			$aryData = $this->call_API('SitesManager.getSitesWithAtLeastViewAccess');
			if (empty($aryData)) {
				echo '<tr><td colspan="2"><p><strong>'.__('An error occured').': </strong>'.__('Please check URL and auth token. You need at least view access to one site.').'</p></td></tr>';
			} elseif ($aryData['result'] == 'error') {
                        	echo '<tr><td colspan="2"><p><strong>'.__('An error occured').': </strong>'.$aryData['message'].'</p></td></tr>';
                	} else {
				echo '<tr><td>Choose site:</td><td><select name="wp-piwik_siteid" id="wp-piwik_siteid">';
				foreach ($aryData as $arySite) {
					echo '<option value="'.$arySite['idsite'].'"'.($arySite['idsite']==$intSite?' selected':'').'>'.htmlentities($arySite['name'], ENT_QUOTES, 'utf-8').'</option>';
				}
				echo '</select></td></tr>';
				if (empty($intSite)) update_option('wp-piwik_siteid', $aryData[0]['idsite']);
				$intSite = get_option('wp-piwik_siteid');
				$intAddJS = get_option('wp-piwik_addjs');
				$strJavaScript = $this->call_API('SitesManager.getJavascriptTag');
				if ($intAddJS) update_option('wp-piwik_jscode', $strJavaScript);
				echo '<tr><td>JavaScript:</td><td><textarea readonly rows="17" cols="80">'.($strJavaScript).'</textarea></td></tr>';
				echo '<tr><td>Add script to wp_footer():</td><td><input type="checkbox" value="1" name="wp-piwik_addjs" '.($intAddJS?' checked':'').'/></td></tr>';
				echo '<tr><td colspan="2"><span class="setting-description">'.__('If your template uses wp_footer(), WP-Piwik can automatically add the Piwik javascript code to your blog.').'</span></td></tr>';
			}
                }
	?>
</table>
<input type="hidden" name="action" value="update" />
<input type="hidden" name="page_options" value="wp-piwik_token,wp-piwik_url,wp-piwik_siteid,wp-piwik_addjs" />
<p class="submit">
	<input type="submit" name="Submit" value="<?php _e('Save settings') ?>" />
</p>
</form>
</div>
</div>
<?php
	}
}

if (class_exists('wp_piwik')) {
	$GLOBALS['wp_piwik'] = new wp_piwik();
}
