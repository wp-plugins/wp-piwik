<?php

		if (class_exists('Piwik\FrontController'))
			Piwik\FrontController::getInstance()->init();
		else serialize(array('result' => 'error', 'message' => __('Class Piwik\FrontController does not exists.','wp-piwik')));
		if (class_exists('Piwik\API\Request'))
			$objRequest = new Piwik\API\Request($strParams);
		else serialize(array('result' => 'error', 'message' => __('Class Piwik\API\Request does not exists.','wp-piwik')));