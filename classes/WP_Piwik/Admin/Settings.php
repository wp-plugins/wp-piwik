<?php

	namespace WP_Piwik\Admin;
	
	class Settings extends \WP_Piwik\Admin {

		public function show() {
			if (isset($_POST) && isset($_POST['wp-piwik'])) {
				if (!self::$wpPiwik->isPHPMode() && isset($_POST['wp-piwik']['piwik_mode']) && $_POST['wp-piwik']['piwik_mode'] == 'php')
					\WP_Piwik::definePiwikConstants();
				$this->saveSettings($_POST['wp-piwik']);
			}
			?>
			<div id="plugin-options-wrap" class="widefat">
				<form method="post">
					<input type="hidden" name="wp-piwik[revision]" value="<?php echo self::$settings->getGlobalOption('revision'); ?>" />
					<table class="wp-piwik-form">
						<tbody>
							<tr><th width="150px"></th><td></td></tr>
			<?php
				$this->showHeadline(1, 'admin-generic', 'Settings', true);
				$submitButton = '<tr><td colspan="2"><p class="submit"><input name="Submit" type="submit" class="button-primary" value="'.esc_attr__('Save Changes').'" /></p></td></tr>';
				
				printf('<tr><td colspan="2">%s</td></tr>', __('Thanks for using WP-Piwik!', 'wp-piwik'));
				//$this->showDonation();
				wp_nonce_field('wp-piwik_settings');
				$this->showHeadline(2, 'dashboard', 'Overview');
				if (self::$wpPiwik->isConfigured()) {
					$piwikVersion = self::$wpPiwik->request('global.getPiwikVersion');
					if (!empty($piwikVersion) && !is_array($piwikVersion))
						$this->showText(
							sprintf(__('WP-Piwik %s is successfully connected to Piwik %s.', 'wp-piwik'), self::$wpPiwik->getPluginVersion(), $piwikVersion).' '.
							(!self::$wpPiwik->isNetworkMode()?
								sprintf(__('You are running WordPress %s.', 'wp-piwik'), get_bloginfo('version')):
								sprintf(__('You are running a WordPress %s blog network (WPMU). WP-Piwik will handle your sites as different websites.', 'wp-piwik'), get_bloginfo('version'))
							)
						);
					else {
						$this->showBox('error', 'no', sprintf(__('WP-Piwik %s was not able to connect to Piwik using your configuration. Check the &raquo;Connect to Piwik&laquo; section below.', 'wp-piwik'), self::$wpPiwik->getPluginVersion()));
					}
				} else $this->showBox('error', 'no', sprintf(__('WP-Piwik %s has to be connected to Piwik first. Check the &raquo;Connect to Piwik&laquo; section below.', 'wp-piwik'), self::$wpPiwik->getPluginVersion()));

				$this->showHeadline(2, 'admin-plugins', 'Connect to Piwik');
				
				if (!self::$wpPiwik->isConfigured())
					$this->showBox('updated', 'info', sprintf('%s <a href="%s">%s</a> %s <a href="%s">%s</a>.', __('WP-Piwik is a WordPress plugin to show a selection of Piwik stats in your WordPress admin dashboard and to add and configure your Piwik tracking code. To use this you will need your own Piwik instance. If you do not already have a Piwik setup, you have two simple options: use either', 'wp-piwik'), 'http://piwik.org/', __('Self-hosted', 'wp-piwik'), __('or', 'wp-piwik'), 'http://piwik.org/hosting/', __('Cloud-hosted', 'wp-piwik')));

				if (!function_exists('curl_init') && !ini_get('allow_url_fopen'))
					$this->showBox('error', 'no', __('Neither cURL nor fopen are available. So WP-Piwik can not use the HTTP API and not connect to Piwik Pro.').' '.sprintf('<a href="%s">%s.</a>', 'https://wordpress.org/plugins/wp-piwik/faq/', __('More information', 'wp-piwik')));

				// Piwik mode
				$description = sprintf('%s<br /><strong>%s:</strong> %s<br /><strong>%s:</strong> %s<br /><strong>%s:</strong> %s',
					__('You can choose between three connection methods:', 'wp-piwik'),
					__('Self-hosted (HTTP API, default)', 'wp-piwik'),
					__('This is the default option for a self-hosted Piwik and should work for most configurations. WP-Piwik will connect to Piwk using http(s).', 'wp-piwik'),
					__('Self-hosted (PHP API)', 'wp-piwik'),
					__('Choose this, if your self-hosted Piwik and WordPress are running on the same machine and you know the full server path to your Piwik instance.', 'wp-piwik'),
					__('Cloud-hosted (Piwik Pro)', 'wp-piwik'),
					__('If you are using a cloud-hosted Piwik by Piwik Pro, you can simply use this option.', 'wp-piwik')
				);
				$this->showSelect('piwik_mode', __('Piwik Mode', 'wp-piwik'),
					array(
						'disabled' => __('Disabled (WP-Piwik will not connect to Piwik)', 'wp-piwik'),
						'http' => __('Self-hosted (HTTP API, default)', 'wp-piwik'), 
						'php' => __('Self-hosted (PHP API)', 'wp-piwik'),
						'pro' => __('Cloud-hosted (Piwik Pro)', 'wp-piwik')
					), $description, '$j(\'tr.wp-piwik-mode-option\').addClass(\'hidden\'); $j(\'#wp-piwik-mode-option-\' + $j(\'#piwik_mode\').val()).removeClass(\'hidden\');', self::$wpPiwik->isConfigured());
									
				// URL/Path/User + Token
				$this->showInput('piwik_url', __('Piwik URL', 'wp-piwik'), 'TODO URL description', self::$settings->getGlobalOption('piwik_mode') != 'http', 'wp-piwik-mode-option', 'http', self::$wpPiwik->isConfigured());
				$this->showInput('piwik_path', __('Piwik path', 'wp-piwik'), 'TODO Path description', self::$settings->getGlobalOption('piwik_mode') != 'php', 'wp-piwik-mode-option', 'php', self::$wpPiwik->isConfigured());
				$this->showInput('piwik_user', __('Piwik user', 'wp-piwik'), 'TODO User description', self::$settings->getGlobalOption('piwik_mode') != 'pro', 'wp-piwik-mode-option', 'pro', self::$wpPiwik->isConfigured());	
				$this->showInput('piwik_token', __('Auth token', 'wp-piwik'), 'TODO Token description', false, '', '', self::$wpPiwik->isConfigured());
				
				// Site configuration
				$this->showCheckbox('auto_site_config', __('Auto config', 'wp-piwik'), __('Check this to automatically choose your blog from your Piwik sites by URL. If your blog is not added to Piwik yet, WP-Piwik will add a new site.', 'wp-piwik'), self::$wpPiwik->isConfigured());				
				if (self::$wpPiwik->isConfigured()) {
					echo '<tr><th scope="row">'.__('Determined site', 'wp-piwik').':</th><td>'.self::$wpPiwik->getSiteID().'</td></tr>';
				}

				echo $submitButton;
				
				if (self::$wpPiwik->isConfigured()) {
					$this->showHeadline(2, 'chart-pie', 'Show Statistics');
					echo $submitButton;	
				}

				if (self::$wpPiwik->isConfigured()) {
					$this->showHeadline(2, 'location-alt', 'Enable Tracking');
					echo $submitButton;	
				}
				
				$this->showHeadline(2, 'shield', 'Expert Settings');
				$this->showText(__('Usually, you do not need to change these settings. If you want to do so, you should know what you do or you got an expert\'s advice.', 'wp-piwik'));
								
				// Cache
				$this->showCheckbox('cache', __('Enable cache', 'wp-piwik'), __('Cache API calls, which not contain today\'s values, for a week.', 'wp-piwik'));

				// User agent configuration
				$this->showSelect('piwik_useragent', __('User agent', 'wp-piwik'),
					array(
						'php' => __('Use the PHP default user agent', 'wp-piwik').(ini_get('user_agent')?'('.ini_get('user_agent').')':' ('.__('empty', 'wp-piwik').')'),
						'own' => __('Define a specific user agent', 'wp-piwik') 
					), 'TODO User agent description', '$j(\'tr.wp-piwik-useragent-option\').toggleClass(\'hidden\');'
				);
				$this->showInput('piwik_useragent_string', __('Specific user agent', 'wp-piwik'), 'TODO Specific user agent description', self::$settings->getGlobalOption('piwik_useragent') != 'own', 'wp-piwik-useragent-option');
				
				// Timeout
				$this->showInput('connection_timeout', __('Connection timeout', 'wp-piwik'), 'TODO Connection timeout description');
				

				
				echo $submitButton;
			?>			</tbody>
					</table>
				</form>
			</div>
		<?php
		}

		private function getDescription($id, $description, $hideDescription = true) {
			return sprintf('<span class="dashicons dashicons-editor-help" onclick="$j(\'#%s-desc\').toggleClass(\'hidden\');"></span> <p class="description'.($hideDescription?' hidden':'').'" id="%1$s-desc">%s</p>', $id, $description);
		}
		
		private function showCheckbox($id, $name, $description, $hideDescription = true) {
			printf('<tr><th scope="row"><label for="%2$s">%s</label>:</th><td><input type="checkbox" id="%s" name="wp-piwik[%2$s]" value="1"'.(self::$settings->getGlobalOption($id)?' checked="checked"':'').' /> %s</td></tr>', $name, $id, $this->getDescription($id, $description, $hideDescription));
		}
		
		private function showText($text) {
			printf('<tr><td colspan="2"><p>%s</p></td></tr>', $text);
		}

		private function showInput($id, $name, $description, $isHidden = false, $groupName='', $rowName=false, $hideDescription = true) {
			printf('<tr class="%s%s"%s><th scope="row"><label for="%5$s">%s:</label></th><td><input name="wp-piwik[%s]" id="%5$s" value="%s" /> %s</td></tr>', $isHidden?'hidden ':'', $groupName?$groupName:'', $rowName?' id="'.$groupName.'-'.$rowName.'"':'', $name, $id, self::$settings->getGlobalOption($id), $this->getDescription($id, $description, $hideDescription));
		}
		
		private function showSelect($id, $name, $options = array(), $description = '', $onChange = '', $hideDescription = true) {
			$optionList = '';
			if (is_array($options))
				foreach ($options as $key => $value)
					$optionList .= sprintf('<option value="%s"'.($key == self::$settings->getGlobalOption($id)?' selected="selected"':'').'>%s</option>', $key, $value);
			printf ('<tr><th scope="row"><label for="%2$s">%s:</label></th><td><select name="wp-piwik[%s]" id="%2$s" onchange="%s">%s</select> %s</td></tr>', $name, $id, $onChange, $optionList, $this->getDescription($id, $description, $hideDescription));
		}

		private function showBox($type, $icon, $content) {
			printf('<tr><td colspan="2"><div class="%s"><p><span class="dashicons dashicons-%s"></span> %s</p></div></td></tr>', $type, $icon, $content); 
		}

		private function showHeadline($order, $icon, $headline, $addPluginName = false) {
			echo $this->getHeadline($order, $icon, $headline, $addPluginName = false);
		}
		
		private function getHeadline($order, $icon, $headline, $addPluginName = false) {
			printf('<tr><td colspan="2"><h%d><span class="dashicons dashicons-%s"></span> %s%s</h%1$d></td></tr>', $order, $icon, ($addPluginName?self::$settings->getGlobalOption('plugin_display_name').' ':''), __($headline, 'wp-piwik'));
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
		
		private function saveSettings($in) {
			self::$settings->applyChanges($in);
			$this->showBox('updated', 'yes', __('Changes saved.'));
		}

		public function printAdminScripts() {
			wp_enqueue_script('jquery');
		}
		
		public function extendAdminHeader() {
			echo '<script type="text/javascript">var $j = jQuery.noConflict();</script>';
			echo '<script type="text/javascript">/* <![CDATA[ */(function() {var s = document.createElement(\'script\');var t = document.getElementsByTagName(\'script\')[0];s.type = \'text/javascript\';s.async = true;s.src = \'//api.flattr.com/js/0.6/load.js?mode=auto\';t.parentNode.insertBefore(s, t);})();/* ]]> */</script>';		
		}
		
	}