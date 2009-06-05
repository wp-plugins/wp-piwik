<?php
/*

Plugin Name: WP-Piwik

Plugin URI: http://dev.braekling.de/wordpress-plugins/dev/wp-piwik/index.html

Description: Adds Piwik stats to your dashboard menu and Piwik code to your wordpress footer.

Version: 0.2.0
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
		if (get_option('wp-piwik_addjs') == 1) 
			add_action('wp_footer', array($this, 'footer'));
	}
	
	function install() {
		
	}

	function footer() {
		echo get_option('wp-piwik_jscode');
	}

	function build_menu() {
		add_dashboard_page(__('Piwik Statistics'), __('WP-Piwik'), 8, __FILE__, array($this, 'show_stats'));
		add_options_page(__('WP-Piwik Settings'), __('WP-Piwik Settings'), 8, __FILE__, array($this, 'show_settings'));
	}
	
	function call_API($strMethod, $strPeriod='', $strDate='', $intLimit='') {
                $strToken = get_option('wp-piwik_token');
                $strURL = get_option('wp-piwik_url');
		$intSite = get_option('wp-piwik_siteid');
		if (empty($strToken) || empty($strURL)) return array('result' => 'error', 'message' => 'Piwik base URL or auth token not set.');
                if (substr($strURL, -1, 1) != '/') $strURL .= '/';
                $strURL .= '?module=API&method='.$strMethod;
                $strURL .= '&idSite='.$intSite.'&period='.$strPeriod.'&date='.$strDate;
                $strURL .= '&format=PHP&filter_limit='.$intLimit;
                $strURL .= '&token_auth='.$strToken;
                $strResult = file_get_contents($strURL);
                $aryData = unserialize($strResult);
		return $aryData;
	}

	function show_stats() {
		$aryError = array();

		$aryVisitors = $this->call_API('VisitsSummary.getVisits', 'day', 'last30');	
		if ($aryVisitors['result'] == 'error')
			$aryError[] = $aryVisitors['message'];

		$aryUVisitors = $this->call_API('VisitsSummary.getUniqueVisitors', 'day', 'last30');	
		if ($aryUVisitors['result'] == 'error')
			$aryError[] = $aryUVisitors['message'];

		$aryOverview = $this->call_API('VisitsSummary.get', 'day', 'yesterday');
		if ($aryOverview['result'] == 'error')
			$aryError[] = $aryOverview['message'];
		
		$aryKeywords = $this->call_API('Referers.getKeywords', 'day', 'yesterday', 10);
		if ($aryKeywords['result'] == 'error')
			$aryError[] = $aryKeywords['message'];

		$aryWebsites = $this->call_API('Referers.getWebsites', 'day', 'yesterday', 10);
		if ($aryWebsites['result'] == 'error')
			$aryError[] = $aryWebsites['message'];

		echo '<div class="wrap">';
            echo '<h2>'.__('Piwik Statistics').'</h2>';
			echo '<div class="inside">';

		if (!empty($aryError)) {
			foreach ($aryError as $strEMessage)
				echo '<p><strong>'.__('An error occured').': </strong> '.$strError.'</p>';
			echo '<p><a href="options-general.php?page=wp-piwik/wp-piwik.php">'.__('Settings').'</a></p>';
		} else {
			echo '<table class="layout">';
			echo '<tr><td>';
				$strValues = $strLabels = $strValuesU = '';
				$intMax = max($aryVisitors);
				while ($intMax % 10 != 0 || $intMax == 0) $intMax++;
				$intStep = $intMax / 5;
				while ($intStep % 10 != 0 && $intStep != 1) $intStep--;

				foreach ($aryVisitors as $strDate => $intValue) {
					$strValues .= round($intValue/($intMax/100),2).',';
					if (isset($aryUVisitors[$strDate])) $strValuesU .= round($aryUVisitors[$strDate]/($intMax/100),2).',';
					$strLabels .= '|'.substr($strDate,-2);
				}
				$strValues = substr($strValues, 0, -1);
				$strValuesU = substr($strValuesU, 0, -1);
				$strGraph  = 'http://chart.apis.google.com/chart?';
				$strGraph .= 'cht=lc&';
				$strGraph .= 'chg=0,'.round($intStep/($intMax/100),2).',2,2&';
				$strGraph .= 'chs=450x220&';
				$strGraph .= 'chd=t:'.$strValues.'|'.$strValuesU.'&';
				$strGraph .= 'chxl=0:'.$strLabels.'&';
				$strGraph .= 'chco=90AAD9,A0BAE9&';
				$strGraph .= 'chm=B,D4E2ED,0,1,0|B,E4F2FD,1,2,0&';
				$strGraph .= 'chxt=x,y&';
				$strGraph .= 'chxr=1,0,'.$intMax.','.$intStep;
				echo '<img src="'.$strGraph.'" width="450" height="220" alt="Visits graph" /><br /><br />';
				echo '<table class="widefat">';
					echo '<thead><tr><th>'.__('Date').'</th><th>'.__('Visits').'</th><th>'.__('Unique').'</th></tr></thead>';
					echo '<tbody>';
					$aryTmp = array_reverse($aryVisitors);
					foreach ($aryTmp as $strDate => $intValue)
						echo '<tr><td>'.$strDate.'</td><td>'.$intValue.'</td><td>'.$aryUVisitors[$strDate].'</td></tr>';
					unset($aryTmp);
				echo '</tbody></table>';
			echo '</td><td style="width:10px;"></td><td>';		
				echo '<table class="widefat">';
					echo '<thead><tr><th colspan="2">'.__('Overview').'</th></tr></thead>';
					$strTime = floor($aryOverview['sum_visit_length']/3600).'h '.floor(($aryOverview['sum_visit_length'] % 3600)/60).'m '.floor(($aryOverview['sum_visit_length'] % 3600) % 60).'s';
					echo '<tbody>';
					echo '<tr><td>'.__('Visitors').':</td><td>'.$aryOverview['nb_visits'].'</td></tr>';
					echo '<tr><td>'.__('Unique visitors').':</td><td>'.$aryOverview['nb_uniq_visitors'].'</td></tr>';
					echo '<tr><td>'.__('Page views').':</td><td>'.$aryOverview['nb_actions'].'</td></tr>';
					echo '<tr><td>'.__('Max. page views in one visit').':</td><td>'.$aryOverview['max_actions'].'</td></tr>';
					echo '<tr><td>'.__('Total time spent by the visitors').':</td><td>'.$strTime.'</td></tr>';
					echo '<tr><td>'.__('Bounce count').':</td><td>'.$aryOverview['bounce_count'].'</td></tr>';
    			echo '</tbody></table><br />';
				echo '<table class="widefat">';
					echo '<thead><tr><th>'.__('Keyword').'</th><th>'.__('Unique visitors').'</th></tr></thead>';
					echo '<tbody>';
					foreach ($aryKeywords as $aryValues)
						echo '<tr><td>'.$aryValues['label'].'</td><td>'.$aryValues['nb_uniq_visitors'].'</td></tr>';
				echo '</tbody></table><br />';
				echo '<table class="widefat">';
					echo '<thead><tr><th>'.__('Website').'</th><th>'.__('Unique visitors').'</th></tr></thead>';
					echo '<tbody>';
					foreach ($aryWebsites as $aryValues)
						echo '<tr><td>'.$aryValues['label'].'</td><td>'.$aryValues['nb_uniq_visitors'].'</td></tr>';
					echo '</tbody></table>';
			echo '</td></tr>';
			echo '</table>';
		}
		echo '</div>';
		echo '</div>';
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
