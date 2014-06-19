<?php

	class WP_Piwik_Logger_Dummy extends WP_Piwik_Logger {

		public function loggerOutput($loggerTime, $loggerMessage) {}
		
    }