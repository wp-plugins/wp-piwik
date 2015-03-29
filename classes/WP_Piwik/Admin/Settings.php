<?php

	namespace WP_Piwik\Admin;
	
	class Settings extends \WP_Piwik\Admin {

		public function show() {
			$piwikVersion = 'unknown';		
			?>
			<div id="plugin-options-wrap" class="widefat">
			<?php
				$this->showHeadline(1, 'admin-generic', 'Settings', true);
				printf('<p>%s</p>', __('Thanks for using WP-Piwik!', 'wp-piwik'));
				//$this->showDonation();
				echo '<form method="post" action="options.php">';
				wp_nonce_field('wp-piwik_settings');
				$this->showHeadline(2, 'dashboard', 'Overview');
				if (self::$wpPiwik->isConfigured()) {
					printf(__('WP-Piwik %s is successfully connected to Piwik %s.', 'wp-piwik').' ', self::$wpPiwik->getPluginVersion(), 'x.x');
					_e('It is running on a single WordPress page.');
				} else $this->showBox('error', 'no', sprintf(__('WP-Piwik %s has to be connected to Piwik first. Check the &raquo;Connect to Piwik&laquo; section below.', 'wp-piwik'), self::$wpPiwik->getPluginVersion()));
				
				$this->showHeadline(2, 'admin-plugins', 'Connect to Piwik');
				$this->showBox('updated', 'info', sprintf('%s <a href="%s">%2$s</a>.', __('WP-Piwik is a WordPress plugin to show a selection of Piwik stats in your WordPress admin dashboard and to add and configure your Piwik tracking code. To use WP-Piwik, a running Piwik instance is required. You can get Piwik and its documentation at', 'wp-piwik'), 'http://piwik.org'));

				$this->showHeadline(2, 'chart-pie', 'Show Statistics');

				$this->showHeadline(2, 'location-alt', 'Enable Tracking');
			?>
					
					<p class="submit"><input name="Submit" type="submit" class="button-primary" value="<?php esc_attr_e('Save Changes'); ?>" /></p>
				</form>
			</div>
		<?php
		}

		public function printAdminScripts() {
			wp_enqueue_script('jquery');
		}

		private function showBox($type, $icon, $content) {
			printf('<div class="%s"><p><span class="dashicons dashicons-%s"></span> %s</p></div>', $type, $icon, $content); 
		}

		private function showHeadline($order, $icon, $headline, $addPluginName = false) {
			echo $this->getHeadline($order, $icon, $headline, $addPluginName = false);
		}
		
		private function getHeadline($order, $icon, $headline, $addPluginName = false) {
			printf('<h%d><span class="dashicons dashicons-%s"></span> %s%s</h%1$d>', $order, $icon, ($addPluginName?self::$settings->getGlobalOption('plugin_display_name').' ':''), __($headline, 'wp-piwik'));
		}
		
		private function showDonation() {?>
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
		</div><?php
		}
		
		public function extendAdminHeader() {
			echo '<script type="text/javascript">var $j = jQuery.noConflict();</script>';
			echo '<script type="text/javascript">/* <![CDATA[ */(function() {var s = document.createElement(\'script\');var t = document.getElementsByTagName(\'script\')[0];s.type = \'text/javascript\';s.async = true;s.src = \'//api.flattr.com/js/0.6/load.js?mode=auto\';t.parentNode.insertBefore(s, t);})();/* ]]> */</script>';		
		}
		
	}