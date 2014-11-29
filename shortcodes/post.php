<?php
/*********************************
	WP-Piwik::Short:Post
**********************************/
$perPostClass = new WP-Piwik\Template\MetaBoxPerPostStats($this->subClassConfig());
$this->strResult = $perPostClass->getValue($this->aryAttributes['range'], $this->aryAttributes['key']); 