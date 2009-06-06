<?php
$aryConf['data']['Visitors'] = $this->call_API(
                        'VisitsSummary.getVisits',
                        isset($aryConf['params'][0])?$aryConf['params'][0]:'',
                        isset($aryConf['params'][1])?$aryConf['params'][1]:'',
                        isset($aryConf['params'][2])?$aryConf['params'][2]:''
                );
$aryConf['data']['Unique'] = $this->call_API(
                        'VisitsSummary.getUniqueVisitors',
                        isset($aryConf['params'][0])?$aryConf['params'][0]:'',
                        isset($aryConf['params'][1])?$aryConf['params'][1]:'',
                        isset($aryConf['params'][2])?$aryConf['params'][2]:''
                );
$aryConf['title'] = __('Visitors');
include('header.php');
$strValues = $strLabels = $strValuesU = '';
$intMax = max($aryConf['data']['Visitors']);
while ($intMax % 10 != 0 || $intMax == 0) $intMax++;
$intStep = $intMax / 5;
while ($intStep % 10 != 0 && $intStep != 1) $intStep--;

foreach ($aryConf['data']['Visitors'] as $strDate => $intValue) {
        $strValues .= round($intValue/($intMax/100),2).',';
        if (isset($aryConf['data']['Unique'][$strDate])) $strValuesU .= round($aryConf['data']['Unique'][$strDate]/($intMax/100),2).',';
                $strLabels .= '|'.substr($strDate,-2);
}
$strValues = substr($strValues, 0, -1);
$strValuesU = substr($strValuesU, 0, -1);
$strGraph  = 'http://chart.apis.google.com/chart?';
$strGraph .= 'cht=lc&';
$strGraph .= 'chg=0,'.round($intStep/($intMax/100),2).',2,2&';
$strGraph .= 'chs=500x220&';
$strGraph .= 'chd=t:'.$strValues.'|'.$strValuesU.'&';
$strGraph .= 'chxl=0:'.$strLabels.'&';
$strGraph .= 'chco=90AAD9,A0BAE9&';
$strGraph .= 'chm=B,D4E2ED,0,1,0|B,E4F2FD,1,2,0&';
$strGraph .= 'chxt=x,y&';
$strGraph .= 'chxr=1,0,'.$intMax.','.$intStep;
?>
<img src="<?php echo $strGraph; ?>" width="500" height="220" alt="Visits graph" />
<br class="clear" />
<?php include ('footer.php');
