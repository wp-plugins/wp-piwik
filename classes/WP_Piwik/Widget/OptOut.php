<?php

	namespace WP_Piwik\Widget;

	class OptOut extends \WP_Piwik\Widget {
	
		public $className = __CLASS__;
		
		protected function configure($prefix = '', $params = array()) {
			$this->parameter = $params;
		}

		public function show() {
			echo '<iframe frameborder="no" width="'.(isset($this->parameter['width'])?$this->parameter['width']:'').'" height="'.(isset($this->parameter['height'])?$this->parameter['height']:'').'" src="'.self::$settings->getGlobalOption('piwik_url').'index.php?module=CoreAdminHome&action=optOut&language='.(isset($this->parameter['language'])?$this->parameter['language']:'en').'"></iframe>';
		}
		
	}