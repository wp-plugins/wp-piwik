<?php

	class WP_Piwik_TrackingCode {
		
		private static $logger, $settings, $wpPiwik, $trackingCode;
		
		public $is404 = false, $isSearch = false;
		
		public function __construct($config) {
			self::$logger = $config['logger'];
			self::$settings = $config['settings'];
			self::$wpPiwik = $config['wp_piwik'];
			if (!self::$settings->getOption('site_id') || !self::$settings->getOption('tracking_code'))
				self::$wpPiwik->addPiwikSite();
			if (!self::$wpPiwik->isCurrentTrackingCode()) {
				self::$settings->setOption('tracking_code', self::$wpPiwik->callPiwikAPI('SitesManager.getJavascriptTag'));
			self::$settings->save();
		}

			$this->trackingCode = self::$settings->getOption('tracking_code');
		}

		public function getTrackingCode() {
			if ($this->is404) $this->apply404Changes();
			if ($this->isSearch) $this->applySearchChanges();
			if (is_single()) $this->addCustomValues();
		}
		
		private function apply404Changes() {
			self::$logger->log('Apply 404 changes. Blog ID: '.self::$blog_id.' Site ID: '.self::$settings->getOption('site_id'));
			$this->trackingCode = str_replace(
				"_paq.push(['trackPageView']);",
				"_paq.push(['setDocumentTitle', '404/URL = '+String(document.location.pathname+document.location.search).replace(/\//g,'%2f') + '/From = ' + String(document.referrer).replace(/\//g,'%2f')]);\n_paq.push(['trackPageView']);",
				$this->trackingCode
			);
		}
	
		private function applySearchChanges($strTrackingCode) {
			self::$logger->log('Apply search tracking changes. Blog ID: '.self::$blog_id.' Site ID: '.self::$settings->getOption('site_id'));
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