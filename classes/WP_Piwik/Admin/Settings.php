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
					), $description, '$j(\'tr.wp-piwik-mode-option\').addClass(\'hidden\'); $j(\'#wp-piwik-mode-option-\' + $j(\'#piwik_mode\').val()).removeClass(\'hidden\');', false, '', self::$wpPiwik->isConfigured());
									
				// URL/Path/User + Token
				$this->showInput('piwik_url', __('Piwik URL', 'wp-piwik'), 'TODO URL description', self::$settings->getGlobalOption('piwik_mode') != 'http', 'wp-piwik-mode-option', 'http', self::$wpPiwik->isConfigured());
				$this->showInput('piwik_path', __('Piwik path', 'wp-piwik'), 'TODO Path description', self::$settings->getGlobalOption('piwik_mode') != 'php', 'wp-piwik-mode-option', 'php', self::$wpPiwik->isConfigured());
				$this->showInput('piwik_user', __('Piwik user', 'wp-piwik'), 'TODO User description', self::$settings->getGlobalOption('piwik_mode') != 'pro', 'wp-piwik-mode-option', 'pro', self::$wpPiwik->isConfigured());	
				$this->showInput('piwik_token', __('Auth token', 'wp-piwik'), 'TODO Token description', false, '', '', self::$wpPiwik->isConfigured());
				
				// Site configuration
				$piwikSiteId = self::$wpPiwik->isConfigured()?self::$wpPiwik->getPiwikSiteId():false;
				$this->showCheckbox('auto_site_config', __('Auto config', 'wp-piwik'), __('Check this to automatically choose your blog from your Piwik sites by URL. If your blog is not added to Piwik yet, WP-Piwik will add a new site.', 'wp-piwik'), self::$wpPiwik->isConfigured(), '$j(\'tr.wp-piwik-auto-option\').toggle(\'hidden\');'.($piwikSiteId?'$j(\'#site_id\').val('.$piwikSiteId.');':''));
				if (self::$wpPiwik->isConfigured()) {
					$piwikSiteDetails = self::$wpPiwik->getPiwikSiteDetails();
					if (($piwikSiteId == 'n/a'))
						$piwikSiteDescription = 'n/a';
					elseif (!self::$settings->getGlobalOption('auto_site_config'))
						$piwikSiteDescription = __('Save settings to start estimation.', 'wp-piwik');
					else
						$piwikSiteDescription = $piwikSiteDetails[$piwikSiteId]['name'].' ('.$piwikSiteDetails[$piwikSiteId]['main_url'].')';
					echo '<tr class="wp-piwik-auto-option'.(!self::$settings->getGlobalOption('auto_site_config')?' hidden':'').'"><th scope="row">'.__('Determined site', 'wp-piwik').':</th><td>'.$piwikSiteDescription.'</td></tr>';
					if (is_array($piwikSiteDetails))
						foreach ($piwikSiteDetails as $key => $siteData)
							$siteList[$key] = $siteData['name'].' ('.$siteData['main_url'].')';
					$this->showSelect('site_id', __('Select site', 'wp-piwik'), $siteList, 'TODO Choose description', '', self::$settings->getGlobalOption('auto_site_config'), 'wp-piwik-auto-option', true, false);
				}

				echo $submitButton;
				
				if (self::$wpPiwik->isConfigured()) {
					$this->showHeadline(2, 'chart-pie', 'Show Statistics');
					echo $submitButton;	
				}

				// Tracking Configuration
				if (self::$wpPiwik->isConfigured()) {
					$this->showHeadline(2, 'location-alt', 'Enable Tracking');

					$description = sprintf('%s<br /><strong>%s:</strong> %s<br /><strong>%s:</strong> %s<br /><strong>%s:</strong> %s<br /><strong>%s:</strong> %s<br /><strong>%s:</strong> %s',
						__('You can choose between four tracking code modes:', 'wp-piwik'),
						__('Disabled', 'wp-piwik'),
						__('WP-Piwik will not add the tracking code. Use this, if you want to add the tracking code to your template files or you use another plugin to add the tracking code.', 'wp-piwik'),
						__('Default tracking', 'wp-piwik'),
						__('TODO', 'wp-piwik'),
						__('Use js/index.php', 'wp-piwik'),
						__('TODO', 'wp-piwik'),
						__('Use proxy script', 'wp-piwik'),
						__('TODO', 'wp-piwik'),
						__('Enter manually', 'wp-piwik'),
						__('TODO', 'wp-piwik')
					);
					$this->showSelect('track_mode', __('Add tracking code', 'wp-piwik'),
						array(
							'disabled' => __('Disabled', 'wp-piwik'),
							'default' => __('Default tracking', 'wp-piwik'), 
							'js' => __('Use js/index.php', 'wp-piwik'), 
							'proxy' => __('Use proxy script', 'wp-piwik'),
							'manually' => __('Enter manually', 'wp-piwik')
						), $description, '$j(\'tr.wp-piwik-track-option\').addClass(\'hidden\'); $j(\'tr.wp-piwik-track-option-\' + $j(\'#track_mode\').val()).removeClass(\'hidden\'); $j(\'#tracking_code, #noscript_code\').prop(\'readonly\', $j(\'#track_mode\').val() != \'manually\');');
					
					$this->showTextarea('tracking_code', __('Tracking code', 'wp-piwik'), 15, 'TODO tracking code desc', (self::$settings->getGlobalOption('track_mode') == 'disabled'), 'wp-piwik-track-option wp-piwik-track-option-default wp-piwik-track-option-js wp-piwik-track-option-proxy wp-piwik-track-option-manually', true, '', (self::$settings->getGlobalOption('track_mode') != 'manually'), false);

					$this->showSelect('track_codeposition', __('JavaScript code position', 'wp-piwik'),
						array(
							'footer' => __('Footer', 'wp-piwik'),
							'header' => __('Header', 'wp-piwik')
						), __('Choose whether the JavaScript code is added to the footer or the header.', 'wp-piwik'), '', (self::$settings->getGlobalOption('track_mode') == 'disabled'), 'wp-piwik-track-option wp-piwik-track-option-default wp-piwik-track-option-js wp-piwik-track-option-proxy wp-piwik-track-option-manually');
						
					$this->showTextarea('noscript_code', __('Noscript code', 'wp-piwik'), 2, 'TODO noscript code desc', (self::$settings->getGlobalOption('track_mode') == 'disabled'), 'wp-piwik-track-option wp-piwik-track-option-default wp-piwik-track-option-js wp-piwik-track-option-proxy wp-piwik-track-option-manually', true, '', (self::$settings->getGlobalOption('track_mode') != 'manually'), false);
					
					$this->showCheckbox('track_noscript', __('Add &lt;noscript&gt;', 'wp-piwik'), __('Adds the &lt;noscript&gt; code to your footer.', 'wp-piwik').' '.__('Disabled in proxy mode.', 'wp-piwik'), (self::$settings->getGlobalOption('track_mode') == 'disabled' || self::$settings->getGlobalOption('track_mode') == 'proxy'), 'wp-piwik-track-option wp-piwik-track-option-default wp-piwik-track-option-js wp-piwik-track-option-manually');

					$this->showCheckbox('track_nojavascript', __('Add rec parameter to noscript code', 'wp-piwik'), __('Enable tracking for visitors without JavaScript (not recommended).', 'wp-piwik').' '.sprintf(__('See %sPiwik FAQ%s.', 'wp-piwik'),'<a href="http://piwik.org/faq/how-to/#faq_176">','</a>').' '.__('Disabled in proxy mode.', 'wp-piwik'), (self::$settings->getGlobalOption('track_mode') == 'disabled' || self::$settings->getGlobalOption('track_mode') == 'proxy' || self::$settings->getGlobalOption('track_mode') == 'manually'), 'wp-piwik-track-option wp-piwik-track-option-default wp-piwik-track-option-js');

					$this->showCheckbox('track_404', __('Track 404', 'wp-piwik'), __('WP-Piwik can automatically add a 404-category to track 404-page-visits.', 'wp-piwik'), (self::$settings->getGlobalOption('track_mode') == 'disabled' || self::$settings->getGlobalOption('track_mode') == 'manually'), 'wp-piwik-track-option wp-piwik-track-option-default wp-piwik-track-option-js wp-piwik-track-option-proxy');

					$this->showCheckbox('track_search', __('Track search', 'wp-piwik'), __('Use Piwik\'s advanced Site Search Analytics feature.').' '.sprintf(__('See %sPiwik documentation%s.', 'wp-piwik'),'<a href="http://piwik.org/docs/site-search/#track-site-search-using-the-tracking-api-advanced-users-only">','</a>'), (self::$settings->getGlobalOption('track_mode') == 'disabled' || self::$settings->getGlobalOption('track_mode') == 'manually'), 'wp-piwik-track-option wp-piwik-track-option-default wp-piwik-track-option-js wp-piwik-track-option-proxy');

					$this->showCheckbox('disable_cookies', __('Disable cookies', 'wp-piwik'), __('Disable all tracking cookies for a visitor.', 'wp-piwik'), (self::$settings->getGlobalOption('track_mode') == 'disabled' || self::$settings->getGlobalOption('track_mode') == 'manually'), 'wp-piwik-track-option wp-piwik-track-option-default wp-piwik-track-option-js wp-piwik-track-option-proxy');
					
					$this->showCheckbox('limit_cookies', __('Limit cookie lifetime', 'wp-piwik'), __('TODO cookie lifetime desc', 'wp-piwik'), (self::$settings->getGlobalOption('track_mode') == 'disabled' || self::$settings->getGlobalOption('track_mode') == 'manually'), 'wp-piwik-track-option wp-piwik-track-option-default wp-piwik-track-option-js wp-piwik-track-option-proxy', true, '$j(\'tr.wp-piwik-cookielifetime-option\').toggle(\'hidden\');');
					
					$this->showInput('limit_cookies_visitor', __('Visitor timeout (seconds)', 'wp-piwik'), false, self::$settings->getGlobalOption('track_mode') == 'disabled' || self::$settings->getGlobalOption('track_mode') == 'manually' || !self::$settings->getGlobalOption('limit_cookies'), 'wp-piwik-track-option wp-piwik-track-option-default wp-piwik-track-option-js wp-piwik-track-option-proxy wp-piwik-cookielifetime-option');
					
					$this->showInput('limit_cookies_session', __('Session timeout (seconds)', 'wp-piwik'), false, self::$settings->getGlobalOption('track_mode') == 'disabled' || self::$settings->getGlobalOption('track_mode') == 'manually' || !self::$settings->getGlobalOption('limit_cookies'), 'wp-piwik-track-option wp-piwik-track-option-default wp-piwik-track-option-js wp-piwik-track-option-proxy wp-piwik-cookielifetime-option');
					
					$this->showCheckbox('track_across', __('Track visitors across all subdomains', 'wp-piwik'), __('Adds *.-prefix to cookie domain.', 'wp-piwik'), (self::$settings->getGlobalOption('track_mode') == 'disabled' || self::$settings->getGlobalOption('track_mode') == 'manually'), 'wp-piwik-track-option wp-piwik-track-option-default wp-piwik-track-option-js wp-piwik-track-option-proxy');

					$this->showCheckbox('track_across_alias', __('Track visitors across all alias URLs', 'wp-piwik'), __('Adds *.-prefix to tracked domain.', 'wp-piwik'), (self::$settings->getGlobalOption('track_mode') == 'disabled' || self::$settings->getGlobalOption('track_mode') == 'manually'), 'wp-piwik-track-option wp-piwik-track-option-default wp-piwik-track-option-js wp-piwik-track-option-proxy');
					
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
		
		private function showCheckbox($id, $name, $description, $isHidden = false, $groupName = '', $hideDescription = true, $onChange = '') {
			printf('<tr class="'.$groupName.($isHidden?' hidden':'').'"><th scope="row"><label for="%2$s">%s</label>:</th><td><input type="checkbox" value="1"'.(self::$settings->getGlobalOption($id)?' checked="checked"':'').' onchange="$j(\'#%s\').val(this.checked?1:0);%s" /><input id="%2$s" type="hidden" name="wp-piwik[%2$s]" value="'.(int)self::$settings->getGlobalOption($id).'" /> %s</td></tr>', $name, $id, $onChange ,$this->getDescription($id, $description, $hideDescription));
		}

		private function showTextarea($id, $name, $rows, $description, $isHidden, $groupName, $hideDescription = true, $onChange = '', $isReadonly = false, $global = true) {
			printf('<tr class="'.$groupName.($isHidden?' hidden':'').'"><th scope="row"><label for="%2$s">%s</label>:</th><td><textarea cols="80" rows="'.$rows.'" id="%s" name="wp-piwik[%2$s]" onchange="%s"'.($isReadonly?' readonly="readonly"':'').'>'.($global?self::$settings->getGlobalOption($id):self::$settings->getOption($id)).'</textarea> %s</td></tr>', $name, $id, $onChange ,$this->getDescription($id, $description, $hideDescription));
		}
		
		private function showText($text) {
			printf('<tr><td colspan="2"><p>%s</p></td></tr>', $text);
		}

		private function showInput($id, $name, $description, $isHidden = false, $groupName='', $rowName=false, $hideDescription = true) {
			printf('<tr class="%s%s"%s><th scope="row"><label for="%5$s">%s:</label></th><td><input name="wp-piwik[%s]" id="%5$s" value="%s" /> %s</td></tr>', $isHidden?'hidden ':'', $groupName?$groupName:'', $rowName?' id="'.$groupName.'-'.$rowName.'"':'', $name, $id, self::$settings->getGlobalOption($id), $this->getDescription($id, $description, $hideDescription));
		}
		
		private function showSelect($id, $name, $options = array(), $description = '', $onChange = '', $isHidden = false, $groupName = '', $hideDescription = true, $global = true) {
			$optionList = '';
			$default = $global?self::$settings->getGlobalOption($id):self::$settings->getOption($id);
			if (is_array($options))
				foreach ($options as $key => $value)
					$optionList .= sprintf('<option value="%s"'.($key == $default?' selected="selected"':'').'>%s</option>', $key, $value);
			printf ('<tr class="'.$groupName.($isHidden?' hidden':'').'"><th scope="row"><label for="%2$s">%s:</label></th><td><select name="wp-piwik[%s]" id="%2$s" onchange="%s">%s</select> %s</td></tr>', $name, $id, $onChange, $optionList, $this->getDescription($id, $description, $hideDescription));
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