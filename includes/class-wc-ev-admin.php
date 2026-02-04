<?php
/**
 * Admin Tools
 * Provides admin actions and UI enhancements
 */

defined( 'ABSPATH' ) || exit;

class WC_EV_Admin {
	
	/**
	 * Single instance
	 */
	private static $instance = null;
	
	/**
	 * Get instance
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	/**
	 * Constructor
	 */
	private function __construct() {
		// User admin actions
		add_filter( 'user_row_actions', array( $this, 'add_user_actions' ), 10, 2 );
		add_action( 'admin_action_wc_ev_mark_verified', array( $this, 'mark_user_verified' ) );
		add_action( 'admin_action_wc_ev_send_verification', array( $this, 'send_verification_admin' ) );
		
		// Order admin actions
		add_filter( 'woocommerce_admin_order_actions', array( $this, 'add_order_actions' ), 10, 2 );
		add_action( 'admin_action_wc_ev_release_order', array( $this, 'release_order' ) );
		
		// Include await-verification orders in admin orders list (HPOS compatibility)
		add_filter( 'woocommerce_order_list_table_prepare_items_query_args', array( $this, 'include_custom_status_in_admin_list' ), 10, 1 );
		
		// Admin notices
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		
		// User profile fields
		add_action( 'show_user_profile', array( $this, 'show_user_verification_status' ) );
		add_action( 'edit_user_profile', array( $this, 'show_user_verification_status' ) );
		
		// Add verification status column to users list
		add_filter( 'manage_users_columns', array( $this, 'add_user_column' ) );
		add_filter( 'manage_users_custom_column', array( $this, 'render_user_column' ), 10, 3 );
	}
	
	/**
	 * Add user actions
	 */
	public function add_user_actions( $actions, $user ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return $actions;
		}
		
		// Only show for users in scope
		if ( ! WC_EV_Scope::instance()->is_user_in_scope( $user->ID ) ) {
			return $actions;
		}
		
		if ( ! WC_EV_Scope::instance()->is_user_verified( $user->ID ) ) {
			$actions['wc_ev_mark_verified'] = sprintf(
				'<a href="%s">%s</a>',
				wp_nonce_url( admin_url( 'admin.php?action=wc_ev_mark_verified&user_id=' . $user->ID ), 'wc_ev_mark_verified_' . $user->ID ),
				__( 'Mark as verified', 'wc-email-verification-gate' )
			);
			
			$actions['wc_ev_send_verification'] = sprintf(
				'<a href="%s">%s</a>',
				wp_nonce_url( admin_url( 'admin.php?action=wc_ev_send_verification&user_id=' . $user->ID ), 'wc_ev_send_verification_' . $user->ID ),
				__( 'Send verification email', 'wc-email-verification-gate' )
			);
		}
		
		return $actions;
	}
	
	/**
	 * Mark user as verified (admin action)
	 */
	public function mark_user_verified() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'You do not have permission to perform this action.', 'wc-email-verification-gate' ) );
		}
		
		$user_id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
		
		if ( ! $user_id ) {
			wp_die( __( 'Invalid user.', 'wc-email-verification-gate' ) );
		}
		
		check_admin_referer( 'wc_ev_mark_verified_' . $user_id );
		
		// Mark as verified
		WC_EV_Scope::instance()->mark_user_verified( $user_id );
		
		// Release orders
		WC_EV_Order_Gate::instance()->release_orders( $user_id );
		
		WC_EV_Settings::log( sprintf( 'Admin marked user %d as verified', $user_id ) );
		
		wp_safe_redirect( add_query_arg( 'wc_ev_admin_notice', 'user_verified', wp_get_referer() ) );
		exit;
	}
	
	/**
	 * Send verification email (admin action)
	 */
	public function send_verification_admin() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'You do not have permission to perform this action.', 'wc-email-verification-gate' ) );
		}
		
		$user_id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
		
		if ( ! $user_id ) {
			wp_die( __( 'Invalid user.', 'wc-email-verification-gate' ) );
		}
		
		check_admin_referer( 'wc_ev_send_verification_' . $user_id );
		
		// Generate new token
		$tokens = WC_EV_Tokens::instance();
		$token = $tokens->rotate_token( $user_id );
		
		// Send email
		$emails = WC()->mailer()->get_emails();
		if ( isset( $emails['WC_EV_Verification_Email'] ) ) {
			$emails['WC_EV_Verification_Email']->trigger( $user_id, $token );
		}
		
		// Record sent
		$tokens->record_verification_sent( $user_id );
		
		WC_EV_Settings::log( sprintf( 'Admin sent verification email to user %d', $user_id ) );
		
		wp_safe_redirect( add_query_arg( 'wc_ev_admin_notice', 'verification_sent', wp_get_referer() ) );
		exit;
	}
	
	/**
	 * Add order actions
	 */
	public function add_order_actions( $actions, $order ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return $actions;
		}
		
		if ( $order->has_status( 'await-verification' ) ) {
			$actions['wc_ev_release'] = array(
				'url'    => wp_nonce_url( admin_url( 'admin.php?action=wc_ev_release_order&order_id=' . $order->get_id() ), 'wc_ev_release_order_' . $order->get_id() ),
				'name'   => __( 'Release order', 'wc-email-verification-gate' ),
				'action' => 'wc-ev-release',
			);
		}
		
		return $actions;
	}
	
	/**
	 * Release order (admin action)
	 */
	public function release_order() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'You do not have permission to perform this action.', 'wc-email-verification-gate' ) );
		}
		
		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		
		if ( ! $order_id ) {
			wp_die( __( 'Invalid order.', 'wc-email-verification-gate' ) );
		}
		
		check_admin_referer( 'wc_ev_release_order_' . $order_id );
		
		$order = wc_get_order( $order_id );
		
		if ( ! $order ) {
			wp_die( __( 'Invalid order.', 'wc-email-verification-gate' ) );
		}
		
		// Release to processing
		$order->update_status( 'processing', __( 'Order manually released by admin.', 'wc-email-verification-gate' ) );
		
		WC_EV_Settings::log( sprintf( 'Admin manually released order %d', $order_id ) );
		
		wp_safe_redirect( add_query_arg( 'wc_ev_admin_notice', 'order_released', wp_get_referer() ) );
		exit;
	}
	
	/**
	 * Display admin notices
	 */
	public function admin_notices() {
		if ( ! isset( $_GET['wc_ev_admin_notice'] ) ) {
			return;
		}
		
		$notice = sanitize_text_field( $_GET['wc_ev_admin_notice'] );
		
		$messages = array(
			'user_verified'     => __( 'User marked as verified and orders released.', 'wc-email-verification-gate' ),
			'verification_sent' => __( 'Verification email sent.', 'wc-email-verification-gate' ),
			'order_released'    => __( 'Order released to processing.', 'wc-email-verification-gate' ),
		);
		
		if ( isset( $messages[ $notice ] ) ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php echo esc_html( $messages[ $notice ] ); ?></p>
			</div>
			<?php
		}
	}
	
	/**
	 * Show user verification status on profile
	 */
	public function show_user_verification_status( $user ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		
		if ( ! WC_EV_Scope::instance()->is_user_in_scope( $user->ID ) ) {
			return;
		}
		
		$is_verified = WC_EV_Scope::instance()->is_user_verified( $user->ID );
		$verified_at = get_user_meta( $user->ID, 'wc_ev_verified_at', true );
		$created_at = get_user_meta( $user->ID, 'wc_ev_created_at', true );
		
		?>
		<h2><?php esc_html_e( 'Email Verification Status', 'wc-email-verification-gate' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Status', 'wc-email-verification-gate' ); ?></th>
				<td>
					<?php if ( $is_verified ) : ?>
						<span style="color: green;">✓ <?php esc_html_e( 'Verified', 'wc-email-verification-gate' ); ?></span>
					<?php else : ?>
						<span style="color: red;">✗ <?php esc_html_e( 'Not Verified', 'wc-email-verification-gate' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
			<?php if ( $created_at ) : ?>
			<tr>
				<th><?php esc_html_e( 'Account Created', 'wc-email-verification-gate' ); ?></th>
				<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $created_at ) ); ?></td>
			</tr>
			<?php endif; ?>
			<?php if ( $verified_at ) : ?>
			<tr>
				<th><?php esc_html_e( 'Verified At', 'wc-email-verification-gate' ); ?></th>
				<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $verified_at ) ); ?></td>
			</tr>
			<?php endif; ?>
		</table>
		<?php
	}
	
	/**
	 * Include await-verification status in admin orders list
	 * This ensures orders with our custom status are visible in the WP admin orders page
	 * Works with both HPOS and legacy post-based storage
	 */
	public function include_custom_status_in_admin_list( $args ) {
		// Get current screen to ensure we're on the orders page
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		
		// Only modify on the orders list page
		if ( ! $screen || ( 'woocommerce_page_wc-orders' !== $screen->id && 'edit-shop_order' !== $screen->id ) ) {
			return $args;
		}
		
		// If no specific status is set, or if status is 'all', ensure our custom status is included
		if ( ! isset( $args['status'] ) || empty( $args['status'] ) ) {
			// When no status filter is applied, explicitly set to include all statuses
			// This is necessary for HPOS to show custom statuses
			$args['status'] = array_keys( wc_get_order_statuses() );
		} elseif ( 'any' === $args['status'] || 'all' === $args['status'] ) {
			// Get all order statuses
			$statuses = array_keys( wc_get_order_statuses() );
			// Ensure our custom status is in the list
			if ( ! in_array( 'wc-await-verification', $statuses, true ) ) {
				$statuses[] = 'wc-await-verification';
			}
			$args['status'] = $statuses;
		} elseif ( is_array( $args['status'] ) ) {
			// If status is already an array, add our status if not present
			if ( ! in_array( 'await-verification', $args['status'], true ) && ! in_array( 'wc-await-verification', $args['status'], true ) ) {
				$args['status'][] = 'wc-await-verification';
			}
		}
		
		return $args;
	}
	
	/**
	 * Add verification status column
	 */
	public function add_user_column( $columns ) {
		$columns['wc_ev_verified'] = __( 'Email Verified', 'wc-email-verification-gate' );
		return $columns;
	}
	
	/**
	 * Render verification status column
	 */
	public function render_user_column( $output, $column_name, $user_id ) {
		if ( 'wc_ev_verified' === $column_name ) {
			if ( ! WC_EV_Scope::instance()->is_user_in_scope( $user_id ) ) {
				return '—';
			}
			
			if ( WC_EV_Scope::instance()->is_user_verified( $user_id ) ) {
				return '<span style="color: green;">✓</span>';
			} else {
				return '<span style="color: red;">✗</span>';
			}
		}
		
		return $output;
	}
}
