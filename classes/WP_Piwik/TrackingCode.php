<?php

	namespace WP_Piwik;

	class TrackingCode {
		
		private static $wpPiwik;
		private $trackingCode;
		
		public $is404 = false, $isSearch = false;
		
		public function __construct($wpPiwik) {
			self::$wpPiwik = $wpPiwik;
			if (!self::$wpPiwik->getOption('site_id') || !self::$wpPiwik->getOption('tracking_code'))
				self::$wpPiwik->addPiwikSite();
			if (!self::$wpPiwik->isCurrentTrackingCode()) {
				self::$wpPiwik->updateTrackingCode();
			}
			$this->trackingCode = self::$wpPiwik->getOption('tracking_code');
		}

		public function getTrackingCode() {
			if ($this->is404) $this->apply404Changes();
			if ($this->isSearch) $this->applySearchChanges();
			if (is_single()) $this->addCustomValues();
			return $this->trackingCode;
		}
		
		public static function prepareTrackingCode($code, $settings, $logger) {
			$logger->log('Apply tracking code changes:');
			$settings->setOption('last_tracking_code_update', time());
			// Change code if js/index.php should be used
			if ($settings->getGlobalOption('track_mode') == 'js')
				$code = str_replace(array('piwik.js', 'piwik.php'), 'js/index.php', $code);
			elseif ($settings->getGlobalOption('track_mode') == 'proxy') {
				$code = str_replace('piwik.js', 'piwik.php', $code);
				$url = str_replace(array('https://', 'http://'), '//', $settings->getGlobalOption('piwik_url'));
				$proxy = str_replace(array('https://', 'http://'), '//', plugins_url('wp-piwik').'/proxy').'/';
				$code = str_replace($url, $proxy, $code);
			}
			/*$strCode = str_replace('//";','/"',$strCode);
			if (self::$settings->getGlobalOption('track_cdnurl')||self::$settings->getGlobalOption('track_cdnurlssl')) {
				$strCode = str_replace("var d=doc", "var ucdn=(('https:' == document.location.protocol) ? 'https://".(self::$settings->getGlobalOption('track_cdnurlssl')?self::$settings->getGlobalOption('track_cdnurlssl'):self::$settings->getGlobalOption('track_cdnurl'))."/' : 'http://".(self::$settings->getGlobalOption('track_cdnurl')?self::$settings->getGlobalOption('track_cdnurl'):self::$settings->getGlobalOption('track_cdnurlssl'))."/');\nvar d=doc", $strCode);
				$strCode = str_replace("g.src=u+", "g.src=ucdn+", $strCode);
			}
			if (self::$settings->getGlobalOption('track_post') && self::$settings->getGlobalOption('track_mode') != 2) $strCode = str_replace("_paq.push(['trackPageView']);", "_paq.push(['setRequestMethod', 'POST']);\n_paq.push(['trackPageView']);", $strCode);
			
			/*if (self::$settings->getGlobalOption('track_datacfasync'))
				$strCode = str_replace('<script type', '<script data-cfasync="false" type', $strCode);*/
			if ($settings->getGlobalOption('limit_cookies'))
				$code = str_replace("_paq.push(['trackPageView']);", "_paq.push(['setVisitorCookieTimeout', '".$settings->getGlobalOption('limit_cookies_visitor')."']);\n_paq.push(['setSessionCookieTimeout', '".$settings->getGlobalOption('limit_cookies_session')."']);\n_paq.push(['trackPageView']);", $code);
			$noScript = array();
			preg_match('/<noscript>(.*)<\/noscript>/', $code, $noScript);
			if (isset($noScript[0])) {
				if ($settings->getGlobalOption('track_nojavascript'))
					$noScript[0] = str_replace('?idsite', '?rec=1&idsite', $noScript[0]);
				$noScript = $noScript[0];
			} else $noScript = '';
			$script = preg_replace('/<noscript>(.*)<\/noscript>/', '', $code);
			$script = preg_replace('/\s+(\r\n|\r|\n)/', '$1', $script);
			$logger->log('Finished tracking code: '.$script);
			$logger->log('Finished noscript code: '.$noScript);
			return array('script' => $script, 'noscript' => $noScript);
		}
		
		private function apply404Changes() {
			self::$wpPiwik->log('Apply 404 changes. Blog ID: '.get_current_blog_id().' Site ID: '.self::$wpPiwik->getOption('site_id'));
			$this->trackingCode = str_replace(
				"_paq.push(['trackPageView']);",
				"_paq.push(['setDocumentTitle', '404/URL = '+String(document.location.pathname+document.location.search).replace(/\//g,'%2f') + '/From = ' + String(document.referrer).replace(/\//g,'%2f')]);\n_paq.push(['trackPageView']);",
				$this->trackingCode
			);
		}
	
		private function applySearchChanges() {
			self::$wpPiwik->log('Apply search tracking changes. Blog ID: '.get_current_blog_id().' Site ID: '.self::$wpPiwik->getOption('site_id'));
			$objSearch = new \WP_Query("s=" . get_search_query() . '&showposts=-1'); 
			$intResultCount = $objSearch->post_count;
			$this->trackingCode = str_replace(
				"_paq.push(['trackPageView']);",
				"_paq.push(['trackSiteSearch','".get_search_query()."', false, ".$intResultCount."]);\n_paq.push(['trackPageView']);",
				$this->trackingCode
			);
		}

		private function addCustomValues() {
			$strCustomVars = '';
			for ($i = 1; $i <= 5; $i++) {
				$intID = get_the_ID();
				$strMetaKey = get_post_meta($intID, 'wp-piwik_custom_cat'.$i, true);
				$strMetaVal = get_post_meta($intID, 'wp-piwik_custom_val'.$i, true);
				if (!empty($strMetaKey) && !empty($strMetaVal))
					$strCustomVars .= "_paq.push(['setCustomVariable',".$i.", '".$strMetaKey."', '".$strMetaVal."', 'page']);\n";
			}
			if (!empty($strCustomVars)) 
				$this->trackingCode = str_replace(
					"_paq.push(['trackPageView']);",
					$strCustomVars."_paq.push(['trackPageView']);",
					$this->trackingCode
				);
		}
	}