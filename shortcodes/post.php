<?php
/*********************************
	WP-Piwik::Short:Post
**********************************/
$perPostClass = new WP_Piwik_Template_MetaBoxPerPostStats($this->subClassConfig());
$this->strResult = $perPostClass->getValue($this->aryAttributes['range'], $this->aryAttributes['key']); 