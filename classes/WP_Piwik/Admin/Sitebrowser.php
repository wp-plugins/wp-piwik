<?php
	
	namespace WP_Piwik\Admin;

	class Sitebrowser extends WP_List_Table {

		private $data = array();
		
		public function __construct($wpPiwik, $isNetwork = false) {
			$bolCURL = function_exists('curl_init');
			$bolFOpen = ini_get('allow_url_fopen');
			if (!$bolFOpen && !$bolCURL) {
				echo '<table><tr><td colspan="2"><strong>';
				_e('Error: cURL is not enabled and fopen is not allowed to open URLs. WP-Piwik won\'t be able to connect to Piwik.');
				echo '</strong></td></tr></table>';
			} else {
				if (!class_exists('WP_List_Table'))
				require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
				if (isset($_GET['wpmu_show_stats']) && ($_GET['wpmu_show_stats'] == (int) $_GET['wpmu_show_stats'])) {
					$this->addPiwikSite();
				}
				$cnt = $this->prepare_items($isNetwork);
				if ($cnt > 0) $this->display();
				else echo '<p>No site configured yet.</p>';
			}
		}

		private function get_columns(){
  			$columns = array(
				'id'    	=> __('ID','wp-piwik'),
				'name' 		=> __('Title','wp-piwik'),
				'siteurl'   => __('URL','wp-piwik'),
				'piwikid'	=> __('Site ID (Piwik)','wp-piwik')
			);
			return $columns;
		}
	
		private function prepare_items($bolNetwork = false) {
  			$current_page = $this->get_pagenum();
  			$per_page = 10;
  			global $blog_id;
  			global $wpdb;
  			global $pagenow;
  			if (is_plugin_active_for_network('wp-piwik/wp-piwik.php')) {
				$total_items = $wpdb->get_var('SELECT COUNT(*) FROM '.$wpdb->blogs);
				$blogs = $wpdb->get_results($wpdb->prepare('SELECT blog_id FROM '.$wpdb->blogs.' ORDER BY blog_id LIMIT %d,%d',(($current_page-1)*$per_page),$per_page));
				foreach ($blogs as $blog) {
					$blogDetails = get_blog_details($blog->blog_id, true);
					$this->data[] = array(
						'name' => $blogDetails->blogname,
						'id' => $blogDetails->blog_id,
						'siteurl' => $blogDetails->siteurl,
						'piwikid' => WP-Piwik::getSiteID($blogDetails->blog_id)
					);
				}
			} else {
				$blogDetails = get_bloginfo();
				$this->data[] = array(
					'name' => get_bloginfo('name'),
					'id' => '-',
					'siteurl' => get_bloginfo('url'),
					'piwikid' => WP-Piwik::getSiteID()
				);
				$total_items = 1;
			}
			$columns = $this->get_columns();
			$hidden = array();
			$sortable = array();
			$this->_column_headers = array($columns, $hidden, $sortable);
			$this->set_pagination_args(array(
    			'total_items' => $total_items,
				'per_page'    => $per_page
			));
			if ($isNetwork) $pagenow = 'settings.php';
			foreach ($this->data as $key => $dataset) {
				if (empty($dataset['piwikid']) || !is_int($dataset['piwikid']))
					$this->data[$key]['piwikid'] = '<a href="'.admin_url(($pagenow == 'settings.php'?'network/':'')).$pagenow.'?page=wp-piwik/wp-piwik.php&tab=sitebrowser'.($dataset['id'] != '-'?'&wpmu_show_stats='.$dataset['id']:'').'">Create Piwik site</a>';
				if ($isNetwork)
					$this->data[$key]['name'] = '<a href="?page=wp-piwik_stats&wpmu_show_stats='.$dataset['id'].'">'.$dataset['name'].'</a>';	
			}
			$this->items = $this->data;
			return count($this->items);
		}

		function column_default( $item, $column_name ) {
  			switch( $column_name ) {
    			case 'id':
				case 'name':
				case 'siteurl':
				case 'piwikid':
	      			return $item[$column_name];
		  		default:
      				return print_r($item,true);
			}
		}
	}