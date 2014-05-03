<?php

		if (!defined('PIWIK_INCLUDE_PATH'))
			return;
		if (PIWIK_INCLUDE_PATH === FALSE)
			return serialize(array('result' => 'error', 'message' => __('Could not resolve','wp-piwik').' &quot;'.htmlentities(self::$settings->getGlobalOption('piwik_path')).'&quot;: '.__('realpath() returns false','wp-piwik').'.'));
		if (file_exists(PIWIK_INCLUDE_PATH . "/index.php"))
			require_once PIWIK_INCLUDE_PATH . "/index.php";
		if (file_exists(PIWIK_INCLUDE_PATH . "/core/API/Request.php"))
			require_once PIWIK_INCLUDE_PATH . "/core/API/Request.php";
		if (class_exists('Piwik\FrontController'))
			Piwik\FrontController::getInstance()->init();
		else serialize(array('result' => 'error', 'message' => __('Class Piwik\FrontController does not exists.','wp-piwik')));
		if (class_exists('Piwik\API\Request'))
			$objRequest = new Piwik\API\Request($strParams);
		else serialize(array('result' => 'error', 'message' => __('Class Piwik\API\Request does not exists.','wp-piwik')));