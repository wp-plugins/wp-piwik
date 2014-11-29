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
		
		private function prepareTrackingCode() {
			self::$logger->log('Apply tracking code changes.');
			self::$settings->setOption('last_tracking_code_update', time());
			$strCode = html_entity_decode($strCode);
			if (self::$settings->getGlobalOption('track_mode') == 1) {
				$strCode = str_replace('piwik.js', 'js/', $strCode);
				$strCode = str_replace('piwik.php', 'js/', $strCode);
			} elseif (self::$settings->getGlobalOption('track_mode') == 2) {
				$strCode = str_replace('piwik.js', 'piwik.php', $strCode);
				$strURL = str_replace('https://', '://', self::$settings->getGlobalOption('piwik_url'));
				$strURL = str_replace('http://', '://', $strURL);
				$strProxy = str_replace('https://', '://', plugins_url('wp-piwik'));
				$strProxy = str_replace('http://', '://', $strProxy);
				$strProxy .= '/';
				$strCode = str_replace($strURL, $strProxy, $strCode);
			}
			$strCode = str_replace('//";','/"',$strCode);
			if (self::$settings->getGlobalOption('track_cdnurl')||self::$settings->getGlobalOption('track_cdnurlssl')) {
				$strCode = str_replace("var d=doc", "var ucdn=(('https:' == document.location.protocol) ? 'https://".(self::$settings->getGlobalOption('track_cdnurlssl')?self::$settings->getGlobalOption('track_cdnurlssl'):self::$settings->getGlobalOption('track_cdnurl'))."/' : 'http://".(self::$settings->getGlobalOption('track_cdnurl')?self::$settings->getGlobalOption('track_cdnurl'):self::$settings->getGlobalOption('track_cdnurlssl'))."/');\nvar d=doc", $strCode);
				$strCode = str_replace("g.src=u+", "g.src=ucdn+", $strCode);
			}
			if (self::$settings->getGlobalOption('track_post') && self::$settings->getGlobalOption('track_mode') != 2) $strCode = str_replace("_paq.push(['trackPageView']);", "_paq.push(['setRequestMethod', 'POST']);\n_paq.push(['trackPageView']);", $strCode);
			if (self::$settings->getGlobalOption('disable_cookies')) $strCode = str_replace("_paq.push(['trackPageView']);", "_paq.push(['disableCookies']);\n_paq.push(['trackPageView']);", $strCode);
			if (self::$settings->getGlobalOption('limit_cookies')) $strCode = str_replace("_paq.push(['trackPageView']);", "_paq.push(['setVisitorCookieTimeout', '".self::$settings->getGlobalOption('limit_cookies_visitor')."']);\n_paq.push(['setSessionCookieTimeout', '".self::$settings->getGlobalOption('limit_cookies_session')."']);\n_paq.push(['trackPageView']);", $strCode);
			$aryNoscript = array();
			preg_match('/<noscript>(.*)<\/noscript>/', $strCode, $aryNoscript);
			if (isset($aryNoscript[0])) {
				if (self::$settings->getGlobalOption('track_nojavascript'))
					$aryNoscript[0] = str_replace('?idsite', '?rec=1&idsite', $aryNoscript[0]);
				self::$settings->setOption('noscript_code', $aryNoscript[0]);
			}
			if (self::$settings->getGlobalOption('track_datacfasync'))
				$strCode = str_replace('<script type', '<script data-cfasync="false" type', $strCode);
			$strCode = preg_replace('/<noscript>(.*)<\/noscript>/', '', $strCode);
			return preg_replace('/\s+(\r\n|\r|\n)/', '$1', $strCode);
		}
		
		private function apply404Changes() {
			$wpPiwik->log('Apply 404 changes. Blog ID: '.self::$blog_id.' Site ID: '.self::$wpPiwik->getOption('site_id'));
			$this->trackingCode = str_replace(
				"_paq.push(['trackPageView']);",
				"_paq.push(['setDocumentTitle', '404/URL = '+String(document.location.pathname+document.location.search).replace(/\//g,'%2f') + '/From = ' + String(document.referrer).replace(/\//g,'%2f')]);\n_paq.push(['trackPageView']);",
				$this->trackingCode
			);
		}
	
		private function applySearchChanges($strTrackingCode) {
			$wpPiwik->log('Apply search tracking changes. Blog ID: '.self::$blog_id.' Site ID: '.self::$wpPiwik->getOption('site_id'));
			$objSearch = new WP_Query("s=" . get_search_query() . '&showposts=-1'); 
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