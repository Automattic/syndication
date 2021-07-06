<?php

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

//@TODO: phpcs - break this file into multiple files, one for each class

/**
 * Class Syndication_Logger_List_Table
 */
class Syndication_Logger_List_Table extends WP_List_Table {

	public $prepared_data = array();

	public $found_data = array();

	public $syndication_logger_table = null;

	protected $_min_date = null;

	protected $_max_date = null;

	/**
	 * Syndication_Logger_List_Table constructor.
	 */
	public function __construct() {
		global $status, $page;

		parent::__construct(
			array(
				'singular' => __( 'log', 'push-syndication' ),
				'plural'   => __( 'logs', 'push-syndication' ),
				'ajax'     => false,
			)
		);

		add_action( 'admin_head', array( $this, 'admin_header' ) );
	}

	public function admin_header() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_page = ( isset( $_GET['page'] ) ) ? (int) $_GET['page'] : false;
		if ( 'syndication_dashboard' != $current_page ) {
			return;
		}

		?>
		<style type="text/css">
			.wp-list-table .column-object_id { width: 5%; }
			.wp-list-table .column-log_id { width: 10%; }
			.wp-list-table .column-time { width: 15%; }
			.wp-list-table .column-msg_type { width: 10%; }
			.wp-list-table .column-message { width: 50%; }
			.wp-list-table .column-status { width: 10%; }
		</style>
		<?php
	}


	public function no_items() {
		esc_html_e( 'No log entries found.', 'push-syndication' );
	}

	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'object_id':
			case 'log_id':
			case 'time':
			case 'msg_type':
			case 'status':
			case 'message':
				return $item[ $column_name ];

			default:
				return print_r( $item, true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		}
	}

	public function get_sortable_columns() {
		$sortable_columns = array(
			'object_id' => array( 'object_id', false ),
			'log_id'    => array( 'log_id', false ),
			'time'      => array( 'time', false ),
			'msg_type'  => array( 'msg_type', false ),
			'message'   => array( 'message', false ),
			'status'    => array( 'status', false ),
		);
		return $sortable_columns;
	}

	public function get_columns() {
		$columns = array(
			'object_id' => __( 'Object ID', 'push-syndication' ),
			'log_id'    => __( 'Log ID', 'push-syndication' ),
			'time'      => __( 'Time', 'push-syndication' ),
			'msg_type'  => __( 'Type', 'push-syndication' ),
			'status'    => __( 'Status', 'push-syndication' ),
			'message'   => __( 'Message', 'push-syndication' ),
		);
		return $columns;
	}

	public function usort_reorder( $a, $b ) {
		$orderby = ( ! empty( $_GET['orderby'] ) ) ? esc_attr( $_GET['orderby'] ) : 'time'; // phpcs:ignore
		$order   = ( ! empty( $_GET['order'] ) ) ? esc_attr( $_GET['order'] ) : 'desc'; // phpcs:ignore
		$result  = strcmp( $a[ $orderby ], $b[ $orderby ] );
		return ( 'asc' === $order ) ? $result : -$result;
	}

	public function column_log_id( $item ) {
		return sprintf( '%1$s', substr( $item['log_id'], 0, 3 ) . '&hellip;' . substr( $item['log_id'], -3 ) );
	}

	public function get_bulk_actions() {
		$actions = array();
		return $actions;
	}

	/**
	 *
	 */
	public function prepare_items() {
		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		$log_id       = ( isset( $_REQUEST['log_id'] ) ) ? esc_attr( $_REQUEST['log_id'] ) : null; // phpcs:ignore
		$msg_type     = null;
		$object_id    = null;
		$object_type  = 'post';
		$log_status   = null;
		$date_start   = null;
		$date_end     = null;
		$message      = null;
		$storage_type = 'object';

		$log_data = Syndication_Logger::instance()->get_messages(
			$log_id,
			$msg_type,
			$object_id,
			$object_type,
			$log_status,
			$date_start,
			$date_end,
			$message,
			$storage_type
		);

		foreach ( $log_data as $site_id => $log_items ) {
			$this->prepared_data = array_merge( $this->prepared_data, $log_items );
		}
		usort( $this->prepared_data, array( $this, 'usort_reorder' ) );

		$per_page     = $this->get_items_per_page( 'per_page' );
		$current_page = $this->get_pagenum();
		$total_items  = count( $this->prepared_data );

		$this->found_data = array_slice( $this->prepared_data, ( ( $current_page - 1 ) * $per_page ), $per_page );


		// Populate min/max dates.
		if ( $this->found_data ) {
			$items_sorted_by_time = $this->found_data;

			usort(
				$items_sorted_by_time,
				function ( $a, $b ) {
					return strtotime( $a['time'] ) - strtotime( $b['time'] );
				}
			);

			$this->_max_date = strtotime( end( $items_sorted_by_time )['time'] );
			$this->_min_date = strtotime( reset( $items_sorted_by_time )['time'] );
		}


		// Filter by month.
		$requested_month = isset( $_REQUEST['month'] ) ? esc_attr( $_REQUEST['month'] ) : null; // phpcs:ignore
		if ( $requested_month ) {
			$this->found_data = array_filter(
				$this->found_data,
				function ( $item ) use ( $requested_month ) {
					return gmdate( 'Y-m', strtotime( $item['time'] ) ) === $requested_month;
				}
			);
		}


		// Filter by type.
		$requested_type = isset( $_REQUEST['type'] ) ? esc_attr( $_REQUEST['type'] ) : null; // phpcs:ignore
		if ( $requested_type ) {
			$this->found_data = array_filter(
				$this->found_data,
				function ( $item ) use ( $requested_type ) {
					return $requested_type === $item['msg_type'];
				}
			);
		}


		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
			)
		);
		$this->items = $this->found_data;
	}

	protected function extra_tablenav( $which ) {
		?>
		<div class="alignleft actions">
			<?php
			if ( 'top' == $which && ! is_singular() ) {
				$this->create_log_id_dropdown();
				$this->create_months_dropdown();
				$this->create_types_dropdown();

				submit_button(
					esc_attr__( 'Filter', 'push-syndication' ),
					'button',
					'filter_action',
					false,
					array( 'id' => 'post-query-submit' )
				);
			}

			?>
		</div>
		<?php
	}

	private function create_log_id_dropdown() {
		$requested_log_id = isset( $_REQUEST['log_id'] ) ? esc_attr( $_REQUEST['log_id'] ) : 0; // phpcs:ignore
		?>
		<label class="screen-reader-text" for="filter-by-log-id"><?php esc_html_e( 'Filter by Log ID', 'push-syndication' ); ?></label>
		<select name="log_id" id="filter-by-log-id">
			<option<?php selected( $requested_log_id, 0 ); ?> value="0"><?php esc_html_e( 'All logs', 'push-syndication' ); ?></option>
			<?php
			$log_ids = array();
			foreach ( $this->prepared_data as $row ) {
				if ( 0 == $row['log_id'] ) {
					continue;
				}

				$log_id = esc_attr( $row['log_id'] );
				if ( ! isset( $log_ids[ $log_id ] ) ) {
					$log_ids[ $log_id ] = sprintf(
						"<option %s value='%s'>%s</option>\n",
						selected( $requested_log_id, $log_id, false ),
						esc_attr( $log_id ),
						esc_attr( $this->column_log_id( $row ) )
					);
				}
			}

			// phpcs:ignore
			echo implode( "\n", $log_ids ); // sanitization happens right above
			?>
		</select>

		<?php
	}

	protected function create_months_dropdown() {
		$requested_month = isset( $_REQUEST['month'] ) ? esc_attr( $_REQUEST['month'] ) : null; // phpcs:ignore
		?>
		<label class="screen-reader-text" for="filter-by-month">Filter by month</label>
		<select name="month" id="filter-by-month">
			<option value="">All dates</option>

			<?php
			if ( $this->_min_date && $this->_max_date ) {
				$month_pointer = new DateTime( '@' . $this->_min_date );
				$max_month     = new DateTime( '@' . $this->_max_date );

				while ( $month_pointer <= $max_month ) {
					?>
					<option	value='<?php echo esc_attr( $month_pointer->format( 'Y-m' ) ); ?>' <?php selected( $requested_month, $month_pointer->format( 'Y-m' ) ); ?>><?php echo esc_html( $month_pointer->format( 'F Y' ) ); ?></option>
					<?php
					$month_pointer->modify( '+1 month' );
				}
			}
			?>
		</select>
		<?php
	}

	protected function create_types_dropdown() {
		$requested_type = isset( $_REQUEST['month'] ) ? esc_attr( $_REQUEST['type'] ) : null; // phpcs:ignore
		?>
		<label class="screen-reader-text" for="filter-by-type">Filter by type</label>
		<select name="type" id="filter-by-type">
			<option value="">All types</option>
			<?php
			foreach ( array(
				'success' => 'Success',
				'info'    => 'Information',
				'error'   => 'Error',
			) as $key => $label ) :
				?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $requested_type, $key ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}
}

/**
 * Class Syndication_Logger_Viewer
 */
class Syndication_Logger_Viewer {

	public $syndication_logger_table;

	/**
	 * Syndication_Logger_Viewer constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_items' ) );
	}

	public function add_menu_items() {
		$hook = add_submenu_page( 'edit.php?post_type=syn_site', 'Logs', 'Logs', 'activate_plugins', 'syndication_dashboard', array( $this, 'render_list_page' ) );
		add_action( "load-$hook", array( $this, 'initialize_list_table' ) );
	}

	public function initialize_list_table() {
		// phpcs:ignore
		if ( ! empty( $_POST['log_id'] ) && ( empty( $_GET['log_id'] ) || esc_attr( $_GET['log_id'] ) != esc_attr( $_POST['log_id'] ) ) ) {
			// phpcs:ignore
			wp_safe_redirect( add_query_arg( array( 'log_id' => esc_attr( $_REQUEST['log_id'] ) ), wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
			exit;
		}
		$this->syndication_logger_table = new Syndication_Logger_List_Table();
	}

	public function render_list_page() {
		?>
		<div class="wrap"><h2><?php _e( 'Syndication Logs', 'push-syndication' ); ?></h2>
			<?php
			$this->syndication_logger_table->prepare_items();
			?>
			<form method="get" action="">
				<input type="hidden" name="post_type" value="syn_site">
				<input type="hidden" name="page" value="syndication_dashboard">
				<?php
				$this->syndication_logger_table->search_box( 'search', 'search_id' );

				$this->syndication_logger_table->display();
				?>
			</form>

			<div id="ajax-response"></div>
			<br class="clear" />
		</div>
		<?php
	}
}
