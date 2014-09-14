<?php
self::$settings->setGlobalOption('piwik_url', self::$settings->getGlobalOption('piwik_url').((substr($strURL, -1, 1) != '/' && substr($strURL, -10, 10) != '/index.php')?'/':''));