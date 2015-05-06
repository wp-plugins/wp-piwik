<?php

namespace WP_Piwik;

/**
 * Abstract widget class
 *
 * @author Andr&eacute; Br&auml;kling
 * @package WP_Piwik
 */
abstract class Widget {
	
	/**
	 *
	 * @var Environment variables
	 */
	protected static $wpPiwik, $settings;
	
	/**
	 *
	 * @var Configuration parameters
	 */
	protected $isShortcode = false, $method = '', $title = '', $context = 'side', $priority = 'core', $parameter = array (), $apiID = array (), $pageId = 'dashboard', $blogId = null, $name = 'Value', $limit = 10;
	
	/**
	 * Widget constructor
	 *
	 * @param WP_Piwik $wpPiwik
	 *        	current WP-Piwik object
	 * @param WP_Piwik\Settings $settings
	 *        	current WP-Piwik settings
	 * @param string $pageId
	 *        	WordPress page ID (default: dashboard)
	 * @param string $context
	 *        	WordPress meta box context (defualt: side)
	 * @param string $priority
	 *        	WordPress meta box priority (default: default)
	 * @param array $params
	 *        	widget parameters (default: empty array)
	 * @param boolean $isShortcode
	 *        	is the widget shown inline? (default: false)
	 */
	public function __construct($wpPiwik, $settings, $pageId = 'dashboard', $context = 'side', $priority = 'default', $params = array(), $isShortcode = false) {
		self::$wpPiwik = $wpPiwik;
		self::$settings = $settings;
		$this->pageId = $pageId;
		$this->context = $context;
		$this->priority = $priority;
		if (self::$settings->checkNetworkActivation () && function_exists ( 'is_super_admin' ) && is_super_admin () && isset ( $_GET ['wpmu_show_stats'] )) {
			switch_to_blog ( ( int ) $_GET ['wpmu_show_stats'] );
			$this->blogId = get_current_blog_id ();
			restore_current_blog ();
		}
		$this->isShortcode = $isShortcode;
		$prefix = ($this->pageId == 'dashboard' ? self::$settings->getGlobalOption ( 'plugin_display_name' ) . ' - ' : '');
		$this->configure ( $prefix, $params );
		if (is_array ( $this->method ))
			foreach ( $this->method as $method ) {
				$this->apiID [$method] = \WP_Piwik\Request::register ( $method, $this->parameter );
				self::$wpPiwik->log ( "Register request: " . $this->apiID [$method] );
			}
		else {
			$this->apiID [$this->method] = \WP_Piwik\Request::register ( $this->method, $this->parameter );
			self::$wpPiwik->log ( "Register request: " . $this->apiID [$this->method] );
		}
		if ($this->isShortcode)
			return;
		add_meta_box ( $this->getName (), $this->title, array (
				$this,
				'show' 
		), $pageId, $this->context, $this->priority );
	}
	
	/**
	 * Conifguration dummy method
	 *
	 * @param string $prefix
	 *        	metabox title prefix (default: empty)
	 * @param array $params
	 *        	widget parameters (default: empty array)
	 */
	protected function configure($prefix = '', $params = array()) {
	}
	
	/**
	 * Default show widget method, handles default Piwik output
	 */
	public function show() {
		$response = self::$wpPiwik->request ( $this->apiID [$this->method] );
		if (! empty ( $response ['result'] ) && $response ['result'] = 'error')
			echo '<strong>' . __ ( 'Piwik error', 'wp-piwik' ) . ':</strong> ' . htmlentities ( $response ['message'], ENT_QUOTES, 'utf-8' );
		else {
			if (isset ( $response [0] ['nb_uniq_visitors'] ))
				$unique = 'nb_uniq_visitors';
			else
				$unique = 'sum_daily_nb_uniq_visitors';
			$tableHead = array (
					'label' => __ ( $this->name, 'wp-piwik' ) 
			);
			$tableHead [$unique] = __ ( 'Unique', 'wp-piwik' );
			if (isset ( $response [0] ['nb_visits'] ))
				$tableHead ['nb_visits'] = __ ( 'Visits', 'wp-piwik' );
			if (isset ( $response [0] ['nb_hits'] ))
				$tableHead ['nb_hits'] = __ ( 'Hits', 'wp-piwik' );
			if (isset ( $response [0] ['nb_actions'] ))
				$tableHead ['nb_actions'] = __ ( 'Actions', 'wp-piwik' );
			$tableBody = array ();
			$count = 0;
			foreach ( $response as $rowKey => $row ) {
				$count ++;
				$tableBody [$rowKey] = array ();
				foreach ( $tableHead as $key => $value )
					$tableBody [$rowKey] [] = isset ( $row [$key] ) ? $row [$key] : '-';
				if ($count == 10)
					break;
			}
			$this->table ( $tableHead, $tableBody, null );
		}
	}
	
	/**
	 * Display a HTML table
	 *
	 * @param array $thead
	 *        	table header content (array of cells)
	 * @param array $tbody
	 *        	table body content (array of rows)
	 * @param array $tfoot
	 *        	table footer content (array of cells)
	 * @param string $class
	 *        	CSSclass name to apply on table sections
	 * @param string $javaScript
	 *        	array of javascript code to apply on body rows
	 */
	protected function table($thead, $tbody = array(), $tfoot = array(), $class = false, $javaScript = array()) {
		echo '<div class="table"><table class="widefat wp-piwik-table">';
		if ($this->isShortcode && $this->title)
			echo '<tr><th colspan="10">' . $this->title . '</th></tr>';
		if (! empty ( $thead ))
			$this->tabHead ( $thead, $class );
		if (! empty ( $tbody ))
			$this->tabBody ( $tbody, $class, $javaScript );
		else
			echo '<tr><td colspan="10">' . __ ( 'No data available.', 'wp-piwik' ) . '</td></tr>';
		if (! empty ( $tfoot ))
			$this->tabFoot ( $tfoot, $class );
		echo '</table></div>';
	}
	
	/**
	 * Display a HTML table header
	 *
	 * @param array $thead
	 *        	array of cells
	 * @param string $class
	 *        	CSS class to apply
	 */
	private function tabHead($thead, $class = false) {
		echo '<thead' . ($class ? ' class="' . $class . '"' : '') . '><tr>';
		$count = 0;
		foreach ( $thead as $value )
			echo '<th' . ($count ++ ? ' class="right"' : '') . '>' . $value . '</th>';
		echo '</tr></thead>';
	}
	
	/**
	 * Display a HTML table body
	 * 
	 * @param array $tbody
	 *        	array of rows, each row containing an array of cells
	 * @param string $class
	 *        	CSS class to apply
	 * @param unknown $javaScript
	 *        	array of javascript code to apply (one item per row)
	 */
	private function tabBody($tbody, $class = false, $javaScript = array()) {
		echo '<tbody' . ($class ? ' class="' . $class . '"' : '') . '>';
		foreach ( $tbody as $key => $trow )
			$this->tabRow ( $trow, $javaScript [$key] );
		echo '</tbody>';
	}
	
	/**
	 * Display a HTML table footer
	 *
	 * @param array $tfoor
	 *        	array of cells
	 * @param string $class
	 *        	CSS class to apply
	 */
	private function tabFoot($tfoot, $class = false) {
		echo '<tfoot' . ($class ? ' class="' . $class . '"' : '') . '><tr>';
		$count = 0;
		foreach ( $tfoot as $value )
			echo '<td' . ($count ++ ? ' class="right"' : '') . '>' . $value . '</td>';
		echo '</tr></tfoot>';
	}
	
	/**
	 * Display a HTML table row
	 *
	 * @param array $trow
	 *        	array of cells
	 * @param string $javaScript
	 *        	javascript code to apply
	 */
	private function tabRow($trow, $javaScript = '') {
		echo '<tr' . (! empty ( $javaScript ) ? ' onclick="' . $javaScript . '"' : '') . '>';
		$count = 0;
		foreach ( $trow as $tcell )
			echo '<td' . ($count ++ ? ' class="right"' : '') . '>' . $tcell . '</td>';
		echo '</tr>';
	}
	
	/**
	 * Get the current request's Piwik time settings
	 *
	 * @return array time settings: period => Piwik period, date => requested date, description => time description to show in widget title
	 */
	protected function getTimeSettings() {
		switch (self::$settings->getGlobalOption ( 'default_date' )) {
			case 'today' :
				$period = 'day';
				$date = 'today';
				$description = 'today';
				break;
			case 'current_month' :
				$period = 'month';
				$date = 'today';
				$description = 'current month';
				break;
			case 'last_month' :
				$period = 'month';
				$date = date ( "Y-m-d", strtotime ( "last day of previous month" ) );
				$description = 'last month';
				break;
			case 'current_week' :
				$period = 'week';
				$date = 'today';
				$description = 'current week';
				break;
			case 'last_week' :
				$period = 'week';
				$date = date ( "Y-m-d", strtotime ( "-1 week" ) );
				$description = 'last week';
				break;
			case 'yesterday' :
				$period = 'day';
				$date = 'yesterday';
				$description = 'yesterday';
				break;
			default :
				break;
		}
		return array (
				'period' => $period,
				'date' => isset ( $_GET ['date'] ) ? ( int ) $_GET ['date'] : $date,
				'description' => isset ( $_GET ['date'] ) ? $this->dateFormat ( $_GET ['date'], $period ) : $description 
		);
	}
	
	/**
	 * Format a date to show in widget
	 *
	 * @param string $date
	 *        	date string
	 * @param string $period
	 *        	Piwik period
	 * @return string formatted date
	 */
	protected function dateFormat($date, $period = 'day') {
		$prefix = '';
		switch ($period) {
			case 'week' :
				$prefix = __ ( 'week', 'wp-piwik' ) . ' ';
				$format = 'W/Y';
				break;
			case 'short_week' :
				$format = 'W';
				break;
			case 'month' :
				$format = 'F Y';
				$date = date ( 'Y-m-d', strtotime ( $date ) );
				break;
			default :
				$format = get_option ( 'date_format' );
		}
		return $prefix . date_i18n ( $format, strtotime ( $date ) );
	}
	
	/**
	 * Format time to show in widget
	 *
	 * @param int $time
	 *        	time in seconds
	 * @return string formatted time
	 */
	protected function timeFormat($time) {
		return floor ( $time / 3600 ) . 'h ' . floor ( ($time % 3600) / 60 ) . 'm ' . floor ( ($time % 3600) % 60 ) . 's';
	}
	
	/**
	 * Convert Piwik range into meaningful text
	 *
	 * @return string range description
	 */
	public function rangeName() {
		switch ($this->parameter ['date']) {
			case 'last30' :
				return 'last 30 days';
			case 'last12' :
				return 'last 12 ' . $this->parameter ['period'] . 's';
			default :
				return $this->parameter ['date'];
		}
	}
	
	/**
	 * Get the widget name
	 *
	 * @return string widget name
	 */
	public function getName() {
		return str_replace ( '\\', '-', get_called_class () );
	}
	
	/**
	 * Display a pie chart
	 *
	 * @param
	 *        	array chart data array(array(0 => name, 1 => value))
	 */
	public function pieChart($data) {
		echo '<div id="wp-piwik_stats_' . $this->getName () . '_graph" style="height:310px;width:100%"></div>';
		echo '<script type="text/javascript">$plotBrowsers = $j.jqplot("wp-piwik_stats_' . $this->getName () . '_graph", [[';
		$list = '';
		foreach ( $data as $dataSet )
			$list .= '["' . $dataSet [0] . '", ' . $dataSet [1] . '],';
		echo substr ( $list, 0, - 1 );
		echo ']], {seriesDefaults:{renderer:$j.jqplot.PieRenderer, rendererOptions:{sliceMargin:8}},legend:{show:true}});</script>';
	}
	
	/**
	 * Return an array value by key, return '-' if not set
	 *
	 * @param array $array
	 *        	array to get a value from
	 * @param string $key
	 *        	key of the value to get from array
	 * @return string found value or '-' as a placeholder
	 */
	protected function value($array, $key) {
		return (isset ( $array [$key] ) ? $array [$key] : '-');
	}
}