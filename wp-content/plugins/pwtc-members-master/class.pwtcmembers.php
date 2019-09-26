<?php

class PwtcMembers {

    private static $initiated = false;

	public static function init() {
		if ( !self::$initiated ) {
			self::init_hooks();
		}
    }
    
	private static function init_hooks() {
		self::$initiated = true;

		/* Register script and style enqueue callbacks */

		add_action( 'wp_enqueue_scripts', 
			array( 'PwtcMembers', 'load_report_scripts' ) );

		add_action( 'wp_before_admin_bar_render',
			array( 'PwtcMembers', 'before_admin_bar_render_callback' ) ); 

		add_action( 'admin_init', array( 'PwtcMembers', 'admin_init_callback' ) );

		/* Register woocommerce customization callbacks */

		add_action('woocommerce_checkout_update_user_meta',
			array( 'PwtcMembers', 'checkout_update_user_meta_callback' ) ); 

		// Force pretty format on entered phone numbers.
		add_filter( 'woocommerce_process_myaccount_field_billing_phone',
			'pwtc_members_format_phone_number' );
		add_filter( 'woocommerce_process_checkout_field_billing_phone',
			'pwtc_members_format_phone_number' );

		add_filter( 'wc_memberships_members_area_my_membership_details', 
			array( 'PwtcMembers', 'members_area_membership_details_callback' ), 10, 2 );

		add_action( 'woocommerce_product_query',
			array( 'PwtcMembers', 'product_query_callback' ) );

		add_action( 'wc_memberships_after_user_membership_member_details',
			array( 'PwtcMembers', 'after_user_member_details_callback' ) );

		add_action( 'wc_memberships_grant_membership_access_from_purchase', 
			array( 'PwtcMembers', 'user_membership_granted_callback' ), 10, 2);

		add_action('wc_memberships_user_membership_saved', 
			array('PwtcMembers', 'membership_created_callback'), 10, 2);
		add_action('wc_memberships_user_membership_created', 
			array('PwtcMembers', 'membership_created_callback'), 10, 2);
		add_action('wc_memberships_user_membership_deleted', 
			array('PwtcMembers', 'membership_deleted_callback'));
		add_action('wc_memberships_csv_import_user_membership', 
			array('PwtcMembers', 'membership_updated_callback'));

		add_action('wc_memberships_for_teams_add_team_member', 
			array('PwtcMembers', 'adjust_team_member_data_callback' ), 10, 3);

		add_action('wc_memberships_for_teams_team_created', 
			array('PwtcMembers', 'adjust_team_members_data_callback' ));

		/*
		add_action('woocommerce_before_cart', 
			array('PwtcMembers', 'validate_checkout_callback' ));

		add_action('woocommerce_checkout_process', 
			array('PwtcMembers', 'validate_checkout_callback' ));

		add_filter('wc_memberships_for_teams_team_can_invite_user', 
			array('PwtcMembers', 'validate_family_invitation_callback'), 10, 4);
		*/

		/* Register shortcode callbacks */

		add_shortcode('pwtc_member_directory', 
			array( 'PwtcMembers', 'shortcode_member_directory'));

		add_shortcode('pwtc_member_statistics', 
			array( 'PwtcMembers', 'shortcode_member_statistics'));

		add_shortcode('pwtc_member_families', 
			array( 'PwtcMembers', 'shortcode_member_families'));

		add_shortcode('pwtc_member_new_members', 
			array( 'PwtcMembers', 'shortcode_member_new_members'));

		add_shortcode('pwtc_member_renew_nag', 
			array( 'PwtcMembers', 'shortcode_member_renew_nag'));

		add_shortcode('pwtc_member_accept_release', 
			array( 'PwtcMembers', 'shortcode_member_accept_release'));

		add_shortcode('pwtc_member_leader_contact', 
			array( 'PwtcMembers', 'shortcode_member_leader_contact'));

		/* Register AJAX request/response callbacks */

		add_action( 'wp_ajax_pwtc_member_lookup', 
			array( 'PwtcMembers', 'member_lookup_callback') );

		add_action( 'wp_ajax_pwtc_member_fetch_address', 
			array( 'PwtcMembers', 'member_fetch_address_callback') );

	}

	/*************************************************************/
	/* Script and style enqueue callback functions
	/*************************************************************/

	public static function load_report_scripts() {
        wp_enqueue_style('pwtc_members_report_css', 
			PWTC_MEMBERS__PLUGIN_URL . 'reports-style.css', array(),
			filemtime(PWTC_MEMBERS__PLUGIN_DIR . 'reports-style.css'));
	}

	public static function before_admin_bar_render_callback() {
		global $wp_admin_bar;
		$current_user = wp_get_current_user();
		if ( $current_user->ID > 0 ) {
			if (!in_array('administrator', $current_user->roles)) {
				$wp_admin_bar->remove_menu('edit-profile');
			}
		}	
	}

	public static function admin_init_callback() {
		$current_user = wp_get_current_user();
		if ( $current_user->ID > 0 ) {
			if (!in_array('administrator', $current_user->roles)) {
					  remove_submenu_page('users.php', 'profile.php');
					  remove_menu_page('profile.php');
			}
		}	
	}

	/*************************************************************/
	/* Wordpress/woocommerce customization callback functions
	/*************************************************************/

	// Set "Release Accepted" field in user profile upon checkout.
	public static function checkout_update_user_meta_callback ($customer_id) {
		update_field('release_accepted', true, 'user_'.$customer_id);
	}

	// Fix expiration date display for family members on My Account page.
	public static function members_area_membership_details_callback ($details, $user_membership) {
		if (function_exists('wc_memberships_for_teams_get_user_membership_team')) {
			$team = wc_memberships_for_teams_get_user_membership_team( $user_membership->get_id() );
			if ( $team ) {
				$team_expire = array(
					'expires' => array(
						'label'   => 'Family Expires',
						'content' => date_i18n( wc_date_format(), $team->get_local_membership_end_date( 'timestamp' ) ),
						'class'   => 'my-membership-detail-user-membership-expires',
					),
				);
				if ( array_key_exists( 'expires', $details ) ) {
					$details = array_replace( $details, $team_expire );
				}
			}
		}
		return $details;
	}

	// Exclude membership products from store page.
	public static function product_query_callback ($q) {
		$tax_query = (array) $q->get( 'tax_query' );
		$tax_query[] = array(
			'taxonomy' => 'product_cat',
			'field' => 'slug',
			'terms' => array( 'memberships' ),
			'operator' => 'NOT IN'
		);
		$q->set( 'tax_query', $tax_query );	
	}

	// Add the Rider ID and Release Acceptance flag to the bottom of the Member Details box.
	public static function after_user_member_details_callback ($userid) {
		$rider_id = get_field('rider_id', 'user_'.$userid);
		if (!$rider_id) {
			$rider_id = '';
		}
		$phone = get_user_meta($userid, 'billing_phone', true);
		if (!empty($phone)) {
			$phone = pwtc_members_format_phone_number($phone);
		}
		$release_accepted = get_field('release_accepted', 'user_'.$userid) ? 'yes' : 'no';
		$privacy_requested = get_field('directory_excluded', 'user_'.$userid) ? 'yes' : 'no';
		?>
		<div><?php echo $phone; ?></div>
		<div>Rider ID: <?php echo $rider_id; ?></div>
		<div>Release Accepted: <?php echo $release_accepted; ?></div>
		<div>Privacy Requested: <?php echo $privacy_requested; ?></div>
		<?php
	}

	public static function user_membership_granted_callback($membership_plan, $args = array()) {
		$user_membership_id = isset($args['user_membership_id']) ? absint($args['user_membership_id']) : null;
		$user_id = isset($args['user_id']) ? absint($args['user_id']) : null;
		$product_id = isset($args['product_id']) ? absint($args['product_id']) : null;

		if (!$user_membership_id) {
			return;
		}
		if (!$user_id) {
			return;
		}

		if (!get_field('send_membership_email', 'option')) {
			return;
		}

		$membership = wc_memberships_get_user_membership($user_membership_id);
		if (!$membership) {
			return;			
		}
		
		$user_data = get_userdata($user_id);
		if (!$user_data) {
			return;			
		}

		$email = self::build_confirmation_email($membership_plan, $user_data, $membership);
		$status = wp_mail($email['to'], $email['subject'], $email['message'], $email['headers']);
		if ($status) {
			$membership->add_note('PWTC Members plugin sent confirmation email to this member, send was successful.');
		}
		else {
			$membership->add_note('PWTC Members plugin sent confirmation email to this member, send failed.');
		}
	}

	public static function membership_created_callback($membership_plan, $args = array()) {
		$user_membership_id = isset($args['user_membership_id']) ? absint($args['user_membership_id']) : null;
		$user_id = isset($args['user_id']) ? absint($args['user_id']) : null;

		if (!$user_membership_id) {
			return;
		}
		if (!$user_id) {
			return;
		}

		$user_membership = wc_memberships_get_user_membership($user_membership_id);
		if (!$user_membership) {
			return;			
		}
		
		$user_data = get_userdata($user_id);
		if (!$user_data) {
			return;			
		}

		if ($user_membership->get_status() == 'auto-draft' or $user_membership->get_status() == 'trash') {
			return;
		}

		if (!in_array('customer', $user_data->roles)) {
			$user_data->add_role('customer');
		}

		if (pwtc_members_is_expired($user_membership)) {
			if (!in_array('expired_member', $user_data->roles)) {
				$user_data->add_role('expired_member');
				$user_membership->add_note('PWTC Members plugin assigned Expired Member role to this member.');
			}
			if (in_array('current_member', $user_data->roles)) {
				$user_data->remove_role('current_member');
			}
		}
		else {
			if (!in_array('current_member', $user_data->roles)) {
				$user_data->add_role('current_member');
				$user_membership->add_note('PWTC Members plugin assigned Current Member role to this member.');
			}
			if (in_array('expired_member', $user_data->roles)) {
				$user_data->remove_role('expired_member');
			}
		}
	}

	public static function membership_updated_callback($user_membership) {
		$user_id = $user_membership->get_user_id();
		$user_data = get_userdata($user_id);
		if (!$user_data) {
			return;			
		}
		if ($user_membership->get_status() == 'auto-draft' or $user_membership->get_status() == 'trash') {
			return;
		}
		if (!in_array('customer', $user_data->roles)) {
			$user_data->add_role('customer');
		}
		if (pwtc_members_is_expired($user_membership)) {
			if (!in_array('expired_member', $user_data->roles)) {
				$user_data->add_role('expired_member');
				$user_membership->add_note('PWTC Members plugin assigned Expired Member role to this member.');
			}
			if (in_array('current_member', $user_data->roles)) {
				$user_data->remove_role('current_member');
			}
		}
		else {
			if (!in_array('current_member', $user_data->roles)) {
				$user_data->add_role('current_member');
				$user_membership->add_note('PWTC Members plugin assigned Current Member role to this member.');
			}
			if (in_array('expired_member', $user_data->roles)) {
				$user_data->remove_role('expired_member');
			}
		}
	}

	public static function membership_deleted_callback($user_membership) {
		$user_id = $user_membership->get_user_id();
		$user_data = get_userdata($user_id);
		if (!$user_data) {
			return;			
		}

		$count = self::count_remaining_memberships('wc_user_membership', $user_id, $user_membership->get_id());
		if ($count == 0) {
			if (in_array('expired_member', $user_data->roles)) {
				$user_data->remove_role('expired_member');
			}
			if (in_array('current_member', $user_data->roles)) {
				$user_data->remove_role('current_member');
			}
		}
	}

	public static function adjust_team_member_data_callback($team_member, $team, $user_membership) {
		$team_end_date = $team->get_membership_end_date('timestamp');
		$user_membership->set_end_date($team_end_date);
		if ($team->is_membership_expired()) {
			$user_membership->update_status('expired');
		}
		else {
			if ($user_membership->is_expired() || $user_membership->is_cancelled()) {
				$user_membership->update_status('active');
			}			
		}
	}

	public static function adjust_team_members_data_callback($team) {
		$user_memberships = $team->get_user_memberships();
		foreach ( $user_memberships as $user_membership ) {
			self::adjust_team_member_data_callback(false, $team, $user_membership);
		}	
	}

	public static function validate_checkout_callback() {
		$membership_cnt = 0;
		if ( sizeof( WC()->cart->get_cart() ) > 0 ) {
			foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {
				$product = wc_get_product( $values['product_id'] );
				if (has_term('memberships', 'product_cat', $product->get_id())) {
					$membership_cnt++;
				}
			}
		}
		if ($membership_cnt > 1) {
			$msg = 'You may not purchase more than one membership product at a time.';
			if (is_cart()) {
				wc_print_notice($msg, 'error');
			} 
			else {
				wc_add_notice($msg, 'error');
			}
		}
		else if ($membership_cnt == 1) {
			$current_user = wp_get_current_user();
			if ( $current_user->ID > 0 ) {
				$active_membership = false;
				$team_owner = false;
				$team_member = false;
				$statuses = array(
					'status' => array( 'active', 'complimentary', 'pending', 'free_trial' ),
				);
				$active_memberships = wc_memberships_get_user_memberships( $current_user->ID, $statuses );
				if ( !empty( $active_memberships ) ) {
					$active_membership = true;
				}
				$teams = wc_memberships_for_teams_get_teams( $current_user->ID, array( 'role' => 'owner' ) );
				if ($teams && !empty( $teams ) ) {
					$team_owner = true;
				}
				else {
					$teams = wc_memberships_for_teams_get_teams( $current_user->ID, array( 'role' => 'member' ) );
					if ($teams && !empty( $teams ) ) {
						$team_member = true;
					}
				}
				if ($team_member) {
					$msg = 'You are a non-owning member of a family; you are not allowed to purchase any membership products.';
					if (is_cart()) {
						wc_print_notice($msg, 'error');
					} 
					else {
						wc_add_notice($msg, 'error');
					}
				}
				else if ($active_membership) {
					$msg = 'You currently have an active membership, are you sure you want to purchase another?';
					if (is_cart()) {
						wc_print_notice($msg, 'notice');
					} 		
				}
			}
		}
	}

	public static function validate_family_invitation_callback($can_be_managed, $user, $team, $role) {
		if ( $user ) {
			$memberships = wc_memberships_get_user_memberships( $user->ID );
			if ( !empty( $memberships ) ) {
				return false;
			}
		}
		return true;
	}

	/*************************************************************/
	/* Shortcode generation callback functions
	/*************************************************************/

	// Generates the [pwtc_member_directory] shortcode.
	public static function shortcode_member_directory($atts) {
		$a = shortcode_atts(array('limit' => 10, 'mode' => 'readonly', 'privacy' => 'off'), $atts);
		$current_user = wp_get_current_user();
		if ( 0 == $current_user->ID ) {
			return '<div class="callout small warning"><p>Please log in to view the member directory.</p></div>';
		}
		else {
			$can_view_address = current_user_can('manage_options');
			$can_view_leaders = false;
			if ($can_view_address) {
				$a['privacy'] = 'off';
			}
			ob_start();
	?>
	<script type="text/javascript">
		jQuery(document).ready(function($) { 

			<?php if ($can_view_address) { ?>
			function display_user_address_cb(response) {
				$('#pwtc-member-wait-div').foundation('close');
				var res = JSON.parse(response);
				if (res.error) {
					$("#pwtc-member-error-div .error-msg").html(res.error);
					$('#pwtc-member-error-div').foundation('open');
				}
				else {
					$('#pwtc-member-address-div .address-data').empty();
					$('#pwtc-member-address-div .contact-data').empty();
					$('#pwtc-member-address-div .address-data').append(
						'<div>' + res.first_name + ' ' + res.last_name + '</div>');
					$('#pwtc-member-address-div .address-data').append(
						'<div>' + res.street1 + '</div>');
					$('#pwtc-member-address-div .address-data').append(
						'<div>' + res.street2 + '</div>');
					$('#pwtc-member-address-div .address-data').append(
						'<div>' + res.city + ' ' + res.state + ' ' + res.zipcode + '</div>');
					$('#pwtc-member-address-div .contact-data').append(
						'<div>' + res.email + '</div>');
					$('#pwtc-member-address-div .contact-data').append(
						'<div>' + res.phone + '</div>');
					$('#pwtc-member-address-div .contact-data').append(
						'<div>Family:' + res.family + '</div>');
					$('#pwtc-member-address-div .contact-data').append(
						'<div>Rider ID: ' + res.riderid + '</div>');
					if (res.riderid.length > 0 && res.valid_member) {
						$('#pwtc-member-address-div .contact-data').append(
							'<div><a><i class="fa fa-download"></i> download rider card</a>' +
							'<form class="download-frm" method="POST">' +
							'<input type="hidden" name="rider_id" value="' + res.riderid + '"/>' +
							'<input type="hidden" name="user_id" value="' + res.userid + '"/>' +
							'<input type="hidden" name="pwtc_mileage_download_riderid"/>' +
							'</form></div>'
						);
						$('#pwtc-member-address-div a').on('click', function(e) {
							$('#pwtc-member-address-div .download-frm').submit();
						});
					}
					$('#pwtc-member-address-div').foundation('open');
				}
			}
			<?php } ?>

			function populate_members_table(members) {
				var header = '<table class="pwtc-mapdb-rwd-table"><tr><th>Member Name</th><th>Account Email</th><th>Account Phone</th>' +
				<?php if ($can_view_address) { ?>
				'<th>Actions</th>' +
				<?php } ?>
				'</tr></table>';
				$('#pwtc-member-list-div').append(header);
				members.forEach(function(item) {
					var data = '<tr userid="' + item.ID + '">' +
					'<td data-th="Name">' + item.first_name + ' ' + item.last_name + 
					(item.is_expired ? ' <i class="fa fa-exclamation-triangle" title="Membership Expired"></i>' : '') +
					(item.is_ride_leader ? ' <i class="fa fa-bicycle" title="Ride Leader"></i>' : '') + '</td>' + 
					'<td data-th="Email">' + item.email + '</td>' +
					'<td data-th="Phone">' + item.phone + '</td>' +
					<?php if ($can_view_address) { ?>
					'<td data-th="Actions">' +
						'<a class="view_address" title="View member contact information."><i class="fa fa-home"></i></a> ' +	
					'</td>' +
					<?php } ?>
					'</tr>';
					$('#pwtc-member-list-div table').append(data);    
				});
				<?php if ($can_view_address) { ?>
				$('#pwtc-member-list-div table .view_address').on('click', function(e) {
					var userid = $(this).parent().parent().attr('userid');
					var action = "<?php echo admin_url('admin-ajax.php'); ?>";
					var data = {
						'action': 'pwtc_member_fetch_address',
						'userid': userid
					};
					$.post(action, data, display_user_address_cb);
					$('#pwtc-member-wait-div .wait-message').html('Loading member address information.');
					$('#pwtc-member-wait-div').foundation('open');
				});
				<?php } ?>
            }

			function create_paging_form(pagenum, numpages, totalusers) {
				$('#pwtc-member-list-div').append(
					'<form class="page-frm">' +
                    '<input class="prev-btn button" style="margin: 0" type="button" value="< Prev"/>' +
					'<span style="margin: 0 10px">Page ' + pagenum + ' of ' + numpages + 
					' (' + totalusers + ' records)</span>' +
                    '<input class="next-btn button" style="margin: 0" type="button" value="Next >"/>' +
					'<span class="page-msg" style="margin: 0 10px"></span>' +
					'<input name="pagenum" type="hidden" value="' + pagenum + '"/>' +
					'<input name="numpages" type="hidden" value="' + numpages + '"/>' +
					'</form>'
				);
				$('#pwtc-member-list-div .page-frm .prev-btn').on('click', function(evt) {
					evt.preventDefault();
					load_members_table('prev');
				});
				if (pagenum == 1) {
					$('#pwtc-member-list-div .page-frm .prev-btn').attr("disabled", "disabled");
				}
				else {
					$('#pwtc-member-list-div .page-frm .prev-btn').removeAttr("disabled");
				}
				$('#pwtc-member-list-div .page-frm .next-btn').on('click', function(evt) {
					evt.preventDefault();
					load_members_table('next');
				});
				if (pagenum == numpages) {
					$('#pwtc-member-list-div .page-frm .next-btn').attr("disabled", "disabled");
				}
				else {
					$('#pwtc-member-list-div .page-frm .next-btn').removeAttr("disabled");
				}
			}

			function lookup_members_cb(response) {
				var res = JSON.parse(response);
				$('#pwtc-member-list-div').empty();
				if (res.error) {
					$('#pwtc-member-list-div').append(
						'<div class="callout small alert"><p>' + res.error + '</p></div>');
				}
				else {
					if (res.members.length > 0) {
						populate_members_table(res.members);
						if (res.total_pages > 1) {
							create_paging_form(res.page_number, res.total_pages, res.total_users);
						}
					}
					else {
						$('#pwtc-member-list-div').append(
							'<div class="callout small warning"><p>No members found.</p></div>');
					}
				}
			}   

			function load_members_table(mode) {
                var action = "<?php echo admin_url('admin-ajax.php'); ?>";
				var data = {
					'action': 'pwtc_member_lookup',
					'privacy': '<?php echo $a['privacy'] ?>',
					'limit': <?php echo $a['limit'] ?>
				};
				if (mode != 'search') {
					data.role = $("#pwtc-member-search-div .search-frm input[name='role_sav']").val();
					data.email = $("#pwtc-member-search-div .search-frm input[name='email_sav']").val();
					data.last_name = $("#pwtc-member-search-div .search-frm input[name='last_name_sav']").val();
					data.first_name = $("#pwtc-member-search-div .search-frm input[name='first_name_sav']").val();
					if (mode == 'refresh') {
						if ($("#pwtc-member-list-div .page-frm").length != 0) {
							var pagenum = $("#pwtc-member-list-div .page-frm input[name='pagenum']").val();
							data.page_number = parseInt(pagenum);
							$('#pwtc-member-list-div .page-frm .page-msg').html('<i class="fa fa-spinner fa-pulse"></i> Please wait...');	
						}
						else {
							$('#pwtc-member-list-div').html('<i class="fa fa-spinner fa-pulse"></i> Please wait...');
						}
					}
					else {
						var pagenum = $("#pwtc-member-list-div .page-frm input[name='pagenum']").val();
						var numpages = $("#pwtc-member-list-div .page-frm input[name='numpages']").val();
						if (mode == 'prev') {
							data.page_number = parseInt(pagenum) - 1;
						}
						else if (mode == 'next') {
							data.page_number = parseInt(pagenum) + 1;
						}
						$('#pwtc-member-list-div .page-frm .page-msg').html('<i class="fa fa-spinner fa-pulse"></i> Please wait...');
					}
				}
				else {
					data.role = $("#pwtc-member-search-div .search-frm .role").val();
					data.email = $("#pwtc-member-search-div .search-frm input[name='email']").val().trim();
					data.last_name = $("#pwtc-member-search-div .search-frm input[name='last_name']").val().trim();
					data.first_name = $("#pwtc-member-search-div .search-frm input[name='first_name']").val().trim();
					$("#pwtc-member-search-div .search-frm input[name='role_sav']").val(data.role);
					$("#pwtc-member-search-div .search-frm input[name='email_sav']").val(data.email);
					$("#pwtc-member-search-div .search-frm input[name='last_name_sav']").val(data.last_name);
					$("#pwtc-member-search-div .search-frm input[name='first_name_sav']").val(data.first_name);	
					$('#pwtc-member-list-div').html('<i class="fa fa-spinner fa-pulse"></i> Please wait...');
				}

				$.post(action, data, lookup_members_cb); 
			}

			$('#pwtc-member-search-div .search-frm').on('submit', function(evt) {
				evt.preventDefault();
				load_members_table('search');
			});

			$('#pwtc-member-search-div .search-frm .reset-btn').on('click', function(evt) {
				evt.preventDefault();
				$("#pwtc-member-search-div .search-frm input[type='text']").val('');
				$("#pwtc-member-search-div .search-frm .role").val('all'); 
				$('#pwtc-member-list-div').empty();
				load_members_table('search');
			});

			load_members_table('search');
		});
	</script>
	<?php if ($can_view_address) { ?>
	<div id="pwtc-member-error-div" class="small reveal" data-close-on-click="false" data-v-offset="100" data-reveal>
		<form class="profile-frm">
		    <div class="row column">
				<div class="callout alert"><p class="error-msg"></p></div>
			</div>
			<div class="row column clearfix">
				<input class="accent button float-left" type="button" value="Close" data-close/>
			</div>
		</form>
	</div>
	<div id="pwtc-member-wait-div" class="small reveal" data-close-on-click="false" data-v-offset="100" data-reveal>
		<div class="callout warning">
			<p><i class="fa fa-spinner fa-pulse"></i> Please wait...</p>
			<p class="wait-message"></p>
		</div>
	</div>
	<div id="pwtc-member-address-div" class="small reveal" data-close-on-click="false" data-v-offset="100" data-reveal>
		<form class="profile-frm">
			<div class="row column">
				<div class="callout primary">
					<p class="address-data"></p>
					<p class="contact-data"></p>
				</div>
			</div>
			<div class="row column clearfix">
				<input class="accent button float-left" type="button" value="Close" data-close/>
			</div>
		</form>
	</div>
	<?php } ?>
	<div id='pwtc-member-search-div'>
		<ul class="accordion" data-accordion data-allow-all-closed="true">
			<li class="accordion-item" data-accordion-item>
				<a href="#" class="accordion-title"><i class="fa fa-search"></i> Click Here To Search</a>
				<div class="accordion-content" data-tab-content>
					<form class="search-frm">
						<input type="hidden" name="last_name_sav" value=""/>
						<input type="hidden" name="first_name_sav" value=""/>
						<input type="hidden" name="email_sav" value=""/>
						<input type="hidden" name="role_sav" value=""/>
						<?php if (!$can_view_leaders) { ?>
						<input class="role" type="hidden" name="role" value="all"/>
						<?php } ?>
						<div>
							<div class="row">
								<div class="small-12 medium-3 columns">
                        			<label>Last Name
										<input type="text" name="last_name"/>
                        			</label>
                    			</div>
								<div class="small-12 medium-3 columns">
                        			<label>First Name
										<input type="text" name="first_name"/>
                        			</label>
                    			</div>
								<div class="small-12 medium-3 columns">
                        			<label>Email
										<input type="text" name="email"/>
                        			</label>
                    			</div>
								<?php if ($can_view_leaders) { ?>
								<div class="small-12 medium-3 columns">
                                	<label>Show
							        	<select class="role">
											<option value="all" selected>All Members</option>
											<option value="ride_leader">Ride Leaders Only</option>
                                        </select>                                
                                	</label>
                            	</div>
								<?php } ?>
							</div>
							<div class="row column">
								<input class="accent button" type="submit" value="Search"/>
								<input class="reset-btn accent button" type="button" value="Reset"/>
							</div>
						</div>
					</form>
				</div>
			</li>
		</ul>
	</div>
	<div id="pwtc-member-list-div"></div>
	<?php
			return ob_get_clean();
		}
	}

	// Generates the [pwtc_member_statistics] shortcode.
	public static function shortcode_member_statistics($atts) {
		$current_user = wp_get_current_user();
		if ( 0 == $current_user->ID ) {
			return '<div class="callout small warning"><p>Please log in to view the membership statistics.</p></div>';
		}
		else {
			$today = date('F j Y', current_time('timestamp'));
			self::count_membership('all'); //TODO: this is a hack!
			$total = self::count_membership(['wcm-active','wcm-expired','wcm-delayed','wcm-complimentary','wcm-paused','wcm-cancelled']);
			$active = self::count_membership('wcm-active');
			$expired = self::count_membership('wcm-expired');
			$delayed = self::count_membership('wcm-delayed');
			$complimentary = self::count_membership('wcm-complimentary');
			$paused = self::count_membership('wcm-paused');
			$cancelled = self::count_membership('wcm-cancelled');
			$multimembers = self::fetch_users_with_multi_memberships('wc_user_membership', true);
			ob_start();
			?>
			<div>Membership statistics as of <?php echo $today; ?>:<br>
			<?php echo $total; ?> total members<ul>
			<li><?php echo $active; ?> active members</li>
			<li><?php echo $expired; ?> expired members</li>
			<li><?php echo $complimentary; ?> complimentary members</li>
			<li><?php echo $delayed; ?> delayed members</li>
			<li><?php echo $paused; ?> paused members</li>
			<li><?php echo $cancelled; ?> cancelled members</li>
			</ul><?php echo $multimembers; ?> users with multiple memberships</div>
			<?php
			return ob_get_clean();
		}
	}
	
	// Generates the [pwtc_member_families] shortcode.
	public static function shortcode_member_families($atts) {
		$current_user = wp_get_current_user();
		if ( 0 == $current_user->ID ) {
			return '<div class="callout small warning"><p>Please log in to view the family member statistics.</p></div>';
		}
		else {
			$today = date('F j Y', current_time('timestamp'));
			$families = self::count_family_memberships();
			$family_members = self::count_family_members();
			$multifamilies = self::fetch_users_with_multi_memberships('wc_memberships_team', true);
			ob_start();
			?>
			<div>Family member statistics as of <?php echo $today; ?>:<br>
			<?php echo $families; ?> family memberships with a total of <?php echo $family_members; ?> family members<br><?php echo $multifamilies; ?> users owning multiple family memberships
			</div>
			<?php
			return ob_get_clean();
		}
	}

	// Generates the [pwtc_member_new_members] shortcode.
	public static function shortcode_member_new_members($atts) {
		$a = shortcode_atts(array('lookback' => 0), $atts);
		$current_user = wp_get_current_user();
		if ( 0 == $current_user->ID ) {
			return '<div class="callout small warning"><p>Please log in to view the new members.</p></div>';
		}
		else {
			$timezone = new DateTimeZone(pwtc_get_timezone_string());
			$month = new DateTime(date('Y-m-01', current_time('timestamp')), $timezone);
			$lookback = $a['lookback'];
			if ($lookback > 0) {
				$month->sub(new DateInterval('P' . $lookback . 'M'));
			}
			$query_args = [
				'nopaging'    => true,
				'post_status' => 'any',
				'post_type'   => 'wc_user_membership',
				'meta_key'    => '_start_date',
				'orderby'     => 'meta_value',
				'order'       => 'DESC',
			];			
			$the_query = new WP_Query($query_args);
			if (empty($the_query)) {
				return '<div class="callout small warning"><p>Cannot access new members.</p></div>';
			}
			if ( $the_query->have_posts() ) {
				ob_start();
				?>
				<div>New members since <?php echo $month->format('F Y'); ?>:<ul>
				<?php
				while ( $the_query->have_posts() ) {
					$the_query->the_post();
					$start = new DateTime(get_post_meta(get_the_ID(), '_start_date', true), $timezone);
					if ($start->getTimestamp() < $month->getTimestamp()) {
						break;
					}
					?>
					<li><?php echo get_the_author(); ?> (<?php echo $start->format('M j'); ?>)</li>
					<?php						
				}
				?>
				</ul></div>
				<?php						
				wp_reset_postdata();
				return ob_get_clean();
			} 
			else {
				ob_start();
				?>
				<div>No new members since <?php echo $month->format('F Y'); ?></div>
				<?php						
				return ob_get_clean();
			}
		}
	}

	// Generates the [pwtc_member_renew_nag] shortcode.
	public static function shortcode_member_renew_nag($atts) {
		$a = shortcode_atts(array('renewonly' => 'no'), $atts);
		$current_user = wp_get_current_user();
		if ( 0 == $current_user->ID ) {
			return '';
		}
		if (!function_exists('wc_memberships_get_user_memberships')) {
			return '';
		}
		$memberships = wc_memberships_get_user_memberships($current_user->ID);
		if (empty($memberships)) {
			if ($a['renewonly'] == 'yes') {
				return '';
			}
			else {
				ob_start();
				?>
				<div class="callout success"><p>You have no membership</p></div>		
				<?php
				return ob_get_clean();
			}
		}
		if (count($memberships) > 1) {
			ob_start();
			?>
			<div class="callout alert"><p>You have multiple memberships, please notify website admin to resolve</p></div>		
			<?php
			return ob_get_clean();
		}
		$membership = $memberships[0];
		$team = false;
		if (function_exists('wc_memberships_for_teams_get_user_membership_team')) {
			$team = wc_memberships_for_teams_get_user_membership_team($membership->get_id());
		}
		if ($team) {
			if ($team->is_user_owner($current_user->ID)) {
				$count = self::count_remaining_memberships('wc_memberships_team', $current_user->ID, $team->get_id());
				if ($count > 0) {
					ob_start();
					?>
					<div class="callout alert"><p>You own multiple family memberships, please notify website admin to resolve</p></div>		
					<?php
					return ob_get_clean();		
				}
				else if ($team->is_membership_expired()) {
					ob_start();
					?>
					<div class="callout warning"><p>Your family membership "<?php echo $team->get_name(); ?>" expired on <?php echo date('F j, Y', $team->get_local_membership_end_date('timestamp')); ?>. <a href="<?php echo $team->get_renew_membership_url(); ?>">Click here to renew</a></p></div>		
					<?php
					return ob_get_clean();
				}
				else {
					if ($a['renewonly'] == 'yes') {
						return '';
					}			
					ob_start();
					?>
					<div class="callout success"><p>Your family membership "<?php echo $team->get_name(); ?>" will expire on <?php echo date('F j, Y', $team->get_local_membership_end_date('timestamp')); ?></p></div>		
					<?php
					return ob_get_clean();
				}
			}
			else {
				if ($team->is_membership_expired()) {
					ob_start();
					?>
					<div class="callout warning"><p>Your family membership "<?php echo $team->get_name(); ?>" expired on <?php echo date('F j, Y',$team->get_local_membership_end_date('timestamp')); ?>, please ask the membership owner to renew</p></div>		
					<?php
					return ob_get_clean();	
				}
				else {
					if ($a['renewonly'] == 'yes') {
						return '';
					}			
					ob_start();
					?>
					<div class="callout success"><p>Your family membership "<?php echo $team->get_name(); ?>" will expire on <?php echo date('F j, Y',$team->get_local_membership_end_date('timestamp')); ?></p></div>		
					<?php
					return ob_get_clean();
				}
			}
		}
		else {
			if ($membership->is_expired()) {
				ob_start();
				?>
				<div class="callout warning"><p>Your individual membership expired on <?php echo date('F j, Y', $membership->get_local_end_date('timestamp')); ?>. <a href="<?php echo $membership->get_renew_membership_url(); ?>">Click here to renew</a></p></div>		
				<?php
				return ob_get_clean();
			}
			else {
				if ($a['renewonly'] == 'yes') {
					return '';
				}		
				if ($membership->has_end_date()) {
					ob_start();
					?>
					<div class="callout success"><p>Your individual membership will expire on <?php echo date('F j, Y', $membership->get_local_end_date('timestamp')); ?></p></div>		
					<?php
					return ob_get_clean();
				}
				else {
					ob_start();
					?>
					<div class="callout success"><p>Your individual membership will never expire</p></div>		
					<?php
					return ob_get_clean();
				}
			}
		}
	}	

	// Generates the [pwtc_member_accept_release] shortcode.
	public static function shortcode_member_accept_release($atts) {
		$current_user = wp_get_current_user();
		if ( 0 == $current_user->ID ) {
			return '';
		}
		$userid = $current_user->ID;
		if (isset($_POST['pwtc_membership_accept_release'])) {
			update_field('release_accepted', true, 'user_'.$userid);
		}
		if (get_field('release_accepted', 'user_'.$userid)) {
			return '';
		}
		ob_start();
		?>
		<div class="callout warning"><p>Please read and accept the Club's <a href="/terms-and-conditions" target="_blank">terms and conditions</a>.<form method="POST"><button class="button" type="submit" name="pwtc_membership_accept_release">I Accept</button></form></p></div>		
		<?php
		return ob_get_clean();
	}

	// Generates the [pwtc_member_leader_contact] shortcode.
	public static function shortcode_member_leader_contact($atts) {
		$current_user = wp_get_current_user();
		if ( 0 == $current_user->ID ) {
			ob_start();
			?>
			<div class="callout warning"><p>You must be logged in to edit your ride leader contact information.</p></div>		
			<?php
			return ob_get_clean();
		}
		$userid = $current_user->ID;
		$user_info = get_userdata( $userid );
		if (!in_array('ride_leader', $user_info->roles)) {
			ob_start();
			?>
			<div class="callout warning"><p>You must be a ride leader to edit your contact information.</p></div>		
			<?php
			return ob_get_clean();
		}
		if (isset($_POST['use_contact_email'])) {
			if ($_POST['use_contact_email'] == 'yes') {
				update_field('use_contact_email', true, 'user_'.$userid);
			}
			else {
				update_field('use_contact_email', false, 'user_'.$userid);
			}
		}
		if (isset($_POST['contact_email'])) {
			update_field('contact_email', sanitize_email($_POST['contact_email']), 'user_'.$userid);
		}
		if (isset($_POST['voice_phone'])) {
			update_field('cell_phone', pwtc_members_format_phone_number($_POST['voice_phone']), 'user_'.$userid);
		}
		if (isset($_POST['text_phone'])) {
			update_field('home_phone', pwtc_members_format_phone_number($_POST['text_phone']), 'user_'.$userid);
		}
		$voice_phone = pwtc_members_format_phone_number(get_field('cell_phone', 'user_'.$userid));
		$text_phone = pwtc_members_format_phone_number(get_field('home_phone', 'user_'.$userid));
		$contact_email = get_field('contact_email', 'user_'.$userid);
		$use_contact_email = get_field('use_contact_email', 'user_'.$userid);
		ob_start();
		?>
		<div class="callout">
			<form method="POST">
				<div class="row">
					<div class="small-12 medium-6 columns">
						<label>Use Contact Email?
							<select name="use_contact_email">
								<option value="no" <?php echo $use_contact_email ? '': 'selected'; ?>>No, use account email instead</option>
								<option value="yes"  <?php echo $use_contact_email ? 'selected': ''; ?>>Yes</option>
							</select>
						</label>
					</div>
					<div class="small-12 medium-6 columns">
						<label><i class="fa fa-envelope"></i> Contact Email
							<input type="text" name="contact_email" value="<?php echo $contact_email; ?>"/>
						</label>
					</div>
					<div class="small-12 medium-6 columns">
						<label><i class="fa fa-phone"></i> Contact Voice Phone
							<input type="text" name="voice_phone" value="<?php echo $voice_phone; ?>"/>
						</label>
					</div>
					<div class="small-12 medium-6 columns">
						<label><i class="fa fa-mobile"></i> Contact Text Phone
							<input type="text" name="text_phone" value="<?php echo $text_phone; ?>"/>
						</label>
					</div>
				</div>
				<div class="row column clearfix">
					<input class="button float-left" type="submit" value="Submit"/>
				</div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/*************************************************************/
	/* AJAX request/response callback functions
	/*************************************************************/

    public static function member_lookup_callback() {
		$current_user = wp_get_current_user();
		if ( 0 == $current_user->ID ) {
			$response = array(
				'error' => 'Member fetch failed - user access denied.'
			);		
		}
		else if (isset($_POST['limit'])) {
			$exclude = false;
			$hide = false;
			if (isset($_POST['privacy'])) {
				if ($_POST['privacy'] == 'exclude') {
					$exclude = true;
				}
				else if ($_POST['privacy'] == 'hide') {
					$hide = true;
				}
			}
			$query_args = self::get_user_query_args($exclude);

			$limit = intval($_POST['limit']);
			$query_args['number'] = $limit;

			$page_number = 1;
			if (isset($_POST['page_number'])) {
				$page_number = intval($_POST['page_number']);
			}
			if ($page_number == 1) {
				$offset = 0;  
			}
			else {
				$offset = ($page_number-1)*$limit;
			}
			$query_args['offset'] = $offset;

			$member_names = [];
			$user_query = new WP_User_Query( $query_args );
			$members = $user_query->get_results();
			if ( !empty($members) ) {
				foreach ( $members as $member ) {
					$member_info = get_userdata( $member->ID );
					if ($hide and get_field('directory_excluded', 'user_'.$member->ID)) {
						$email = '*****';
						$phone = '*****';
					}
					else {
						if (!empty($member_info->user_email)) {
							$email = '<a href="mailto:' . $member_info->user_email . '">' . $member_info->user_email . '</a>';
						}
						else {
							$email = '';
						}
						if (!empty($member_info->billing_phone)) {
							$phone = '<a href="tel:' . 
								pwtc_members_strip_phone_number($member_info->billing_phone) . 
								'">' . 
								pwtc_members_format_phone_number($member_info->billing_phone) .
								'</a>';
						}
						else {
							$phone = '';
						}
					}
					$member_names[] = [
						'ID' => $member->ID,
						'first_name' => $member_info->first_name,
						'last_name' => $member_info->last_name,
						'email' => $email,
						'phone' => $phone,
						'is_expired' => in_array('expired_member', $member_info->roles),
						'is_ride_leader' => in_array('ride_leader', $member_info->roles)
					];
				}
			}
			
			$total_users = $user_query->total_users;
			$total_pages = ceil($user_query->total_users/$limit);

			$response = array(
				'members' => $member_names,
				'total_pages' => $total_pages,
				'page_number' => $page_number,
				'total_users' => $total_users
			);
		}
		else {
			$response = array(
				'error' => 'Member fetch failed - AJAX arguments missing.'
			);		
		}
        echo wp_json_encode($response);
        wp_die();
	}

	public static function get_user_query_args($exclude = false) {
        $query_args = [
            'meta_key' => 'last_name',
            'orderby' => 'meta_value',
			'order' => 'ASC',
			'role__in' => ['current_member', 'expired_member']
		];

		if ($exclude) {
			if (!isset($query_args['meta_query'])) {
				$query_args['meta_query'] = [];
			}
			$query_args['meta_query'][] = [
				'relation' => 'OR',
				[
					'key'     => 'directory_excluded',
					'value'   => '0',
					'compare' => '='   	
				],
				[
					'key'     => 'directory_excluded',
					'compare' => 'NOT EXISTS'   	
				]
			];
		}

		if (isset($_POST['first_name'])) {
			if (!isset($query_args['meta_query'])) {
				$query_args['meta_query'] = [];
			}
			$query_args['meta_query'][] = [
				'key'     => 'first_name',
				'value'   => $_POST['first_name'],
				'compare' => 'LIKE'   
			];
		}

		if (isset($_POST['last_name'])) {
			if (!isset($query_args['meta_query'])) {
				$query_args['meta_query'] = [];
			}
			$query_args['meta_query'][] = [
				'key'     => 'last_name',
				'value'   => $_POST['last_name'],
				'compare' => 'LIKE'   
			];
		}

		if (isset($_POST['email'])) {
			$query_args['search'] = '*' . esc_attr($_POST['email']) . '*';
			$query_args['search_columns'] = array( 'user_email' );
		}	

		if (isset($_POST['role'])) {
			$role = $_POST['role'];
			if ($role != 'all') {
				if (substr($role, 0, 1) === "!") {
					$not_role = substr($role, 1, strlen($role)-1);
					if (isset($query_args['role__not_in'])) {
						$query_args['role__not_in'][] = $not_role;
					}
					else {
						$query_args['role__not_in'] = [$not_role];
					}
				}
				else {
					$query_args['role__in'] = [$role];
				}
			}
		}

		return $query_args;
	}

	public static function member_fetch_address_callback() {
		if (!current_user_can('manage_options')) {
			$response = array(
				'error' => 'Address fetch failed - user access denied.'
			);		
		}
		else if (isset($_POST['userid'])) {
			$userid = intval($_POST['userid']);
			$member_info = get_userdata($userid);
			if ($member_info === false) {
				$response = array(
					'userid' => $userid,
					'error' => 'Address fetch failed - user ID ' . $userid . ' not valid.'
				);
			}
			else {
				$family = '';
				if (function_exists('wc_memberships_for_teams_get_teams')) {
					$teams = wc_memberships_for_teams_get_teams($userid);
					if ($teams && !empty($teams)) {
						foreach ( $teams as $team ) {
							$family .= ' ' . $team->get_name();
							if ($team->is_user_owner($userid)) {
								$family .= ' (owner)';
							}
						}
					}
				}
				$valid_member = false;
				if (function_exists('wc_memberships_get_user_memberships')) {
					$memberships = wc_memberships_get_user_memberships($userid);
					if (!empty($memberships)) {
						if (function_exists('pwtc_mileage_get_rider_card_info')) {
							$valid_member = true;
						}
					}
				}		
				$riderid = get_field('rider_id', 'user_'.$userid);
				if (!$riderid) {
					$riderid = '';
				}
				$phone = get_user_meta($userid, 'billing_phone', true);
				if (!empty($phone)) {
					$phone = pwtc_members_format_phone_number($phone);
				}
				$response = array(
					'userid' => $userid,
					'first_name' => $member_info->first_name,
					'last_name' => $member_info->last_name,
					'email' => $member_info->user_email,
					'riderid' => $riderid,
					'street1' => get_user_meta($userid, 'billing_address_1', true),
					'street2' => get_user_meta($userid, 'billing_address_2', true), 
					'city' => get_user_meta($userid, 'billing_city', true), 
					'state' => get_user_meta($userid, 'billing_state', true), 
					'country' => get_user_meta($userid, 'billing_country', true), 
					'zipcode' => get_user_meta($userid, 'billing_postcode', true), 
					'phone' => $phone,
					'family' => $family,
					'valid_member' => $valid_member
				);
			}
		}
		else {
			$response = array(
				'error' => 'Address fetch failed - AJAX arguments missing.'
			);		
		}
		echo wp_json_encode($response);
        wp_die();
	}

	/*************************************************************/
	/* Utility functions.
	/*************************************************************/

	public static function count_membership($status) {
		$query_args = [
			'nopaging'    => true,
			'post_status' => $status,
			'post_type' => 'wc_user_membership',
			'fields' => 'ids',
			'cache_results'  => false,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,		
		];			
		$the_query = new WP_Query($query_args);
		if (empty($the_query)) {
			return 'unknown';
		}
		return '' . $the_query->found_posts;
	}

	public static function count_family_memberships() {
		$query_args = [
			'nopaging'    => true,
			'post_status' => 'any',
			'post_type' => 'wc_memberships_team',
			'fields' => 'ids',
			'cache_results'  => false,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,		
		];			
		$the_query = new WP_Query($query_args);
		if (empty($the_query)) {
			return 'unknown';
		}
		return '' . $the_query->found_posts;
	}

	public static function count_family_members() {
		$query_args = [
			'nopaging'    => true,
			'post_status' => 'any',
			'post_type' => 'wc_memberships_team',
		];			
		$the_query = new WP_Query($query_args);
		if (empty($the_query)) {
			return 'unknown';
		}
		$count = 0;
		if ( $the_query->have_posts() ) {
			while ( $the_query->have_posts() ) {
				$the_query->the_post();
				$members = get_post_meta(get_the_ID(), '_member_id', false);
				$count += count($members);
			}
			wp_reset_postdata();
		}
		return '' . $count;
	}

	public static function count_remaining_memberships($post_type, $userid, $postid) {
		global $wpdb;
		$stmt = $wpdb->prepare(
			"select count(ID) from " . $wpdb->posts . 
			" where post_type = %s" . 
			" and post_status not in ('auto-draft', 'trash')" . 
			" and post_author = %d and ID <> %d", $post_type, $userid, $postid);
		$results = $wpdb->get_var($stmt);
		return $results;
	}

	public static function fetch_users_with_multi_memberships($post_type, $count_only = false) {
		global $wpdb;
		$select_item = 'distinct a.post_author';
		if ($count_only) {
			$select_item = 'count(' . $select_item . ')';
		}
		$stmt = $wpdb->prepare(
			"select " . $select_item .
			" from " . $wpdb->posts . " as a inner join " . $wpdb->posts . " as b" . 
			" where a.post_author = b.post_author and a.ID <> b.ID" . 
			" and a.post_type = %s and b.post_type = %s" . 
			" and a.post_status not in ('auto-draft', 'trash')" . 
			" and b.post_status not in ('auto-draft', 'trash')", $post_type, $post_type);
		if ($count_only) {
			$results = $wpdb->get_var($stmt);
		}
		else {
			$results = $wpdb->get_results($stmt, ARRAY_N);
		}
		return $results;
	}

	public static function fetch_users_with_no_memberships() {
		global $wpdb;
		$stmt = "select ID from " . $wpdb->users . 
			" where ID not in (select post_author from " . $wpdb->posts . 
			" where post_type = 'wc_user_membership' and" . 
			" post_status not in ('auto-draft', 'trash')) order by user_email";
		$results = $wpdb->get_results($stmt, ARRAY_N);
		return $results;
	}

	public static function fetch_users_with_memberships() {
		global $wpdb;
		$stmt = "select ID from " . $wpdb->users . 
			" where ID in (select post_author from " . $wpdb->posts . 
			" where post_type = 'wc_user_membership' and" . 
			" post_status not in ('auto-draft', 'trash')) order by user_email";
		$results = $wpdb->get_results($stmt, ARRAY_N);
		return $results;
	}

	public static function build_confirmation_email($membership_plan, $user_data, $membership, $test_email = '') {
		$member_email = $user_data->user_email;
		$member_name = $user_data->first_name . ' ' . $user_data->last_name;

		$member_riderid = get_field('rider_id', 'user_'.$user_data->ID);
		if (!$member_riderid) {
			$member_riderid = '';
		}

		$member_type = $membership_plan->get_name(); 

		$member_cost = 'N/A';
		$product = $membership->get_product(true);
		if ($product) {
			$price = $product->get_price();
			$member_cost = '$' . $price;
		}

		$team = false;
		if (function_exists('wc_memberships_for_teams_get_user_membership_team')) {
			$team = wc_memberships_for_teams_get_user_membership_team( $membership->get_id() );
		}

		$team_not_owner = false;
		if ( $team ) {
			if ($team->get_owner_id() != $user_data->ID) {
				$team_not_owner = true;
			}
			$message = get_field('family_membership_email', 'option');
			$member_expires = date('F j, Y', $team->get_local_membership_end_date('timestamp'));
			$member_starts = date('F j, Y', $team->get_local_date('timestamp'));
			$member_type = 'Family ' . $member_type;
		}
		else {
			$message = get_field('individual_membership_email', 'option');
			$member_expires = date('F j, Y', $membership->get_local_end_date('timestamp'));
			$member_starts = date('F j, Y', $membership->get_local_start_date('timestamp'));
			$member_type = 'Individual ' . $member_type;
		}
		
		if (!$message) {
			$message = '';
		}

		$membersec_email = get_field('membership_captain_email', 'option');
		$membersec_name = get_field('membership_captain_name', 'option');

		$message = str_replace('{riderid}', $member_riderid, $message);
		$message = str_replace('{expires}', $member_expires, $message);
		$message = str_replace('{starts}', $member_starts, $message);
		$message = str_replace('{email}', $member_email, $message);
		$message = str_replace('{name}', $member_name, $message);
		$message = str_replace('{type}', $member_type, $message);
		$message = str_replace('{cost}', $member_cost, $message);

		$subject = get_field('membership_email_subject', 'option');
		if (!$subject) {
			$subject = '';
		}

		if (empty($test_email)) {
			$to = $member_email;
		}
		else {
			$to = $test_email;
		}
		$headers = array();
		$headers[] = 'Content-type: text/html';
		if (get_field('bcc_membership_secretary', 'option')) {
			$headers[] = 'Bcc: ' . $membersec_email;
		}
		return array(
			'team_not_owner' => $team_not_owner,
			'to' => $to,
			'subject' => $subject,
			'message' => $message,
			'headers' => $headers
		);
	}

	public static function lookup_user_memberships($memberid, $lastname = '', $firstname = '', $email = '', $exact = true) {
		$add_edit_link = current_user_can('manage_options');
		$users = array();
		$profiles = pwtc_members_lookup_user($memberid, $lastname, $firstname, $email, $exact);
		foreach ($profiles as $profile) {
			$fix_roles = false;
			$info = get_userdata($profile->ID);
			$note = '';
			$expir_date = '';
			if (function_exists('wc_memberships_get_user_memberships')) {
				$memberships = wc_memberships_get_user_memberships($profile->ID);
				if (empty($memberships)) {
					$note = 'no membership';
					if (in_array('expired_member', $info->roles) or 
						in_array('current_member', $info->roles)) {
						$fix_roles = true;
					}
				}
				else if (count($memberships) > 1) {
					$note = 'multiple memberships';
				}
				else {
					$expir_date = pwtc_members_get_expiration_date($memberships[0]);
					$expir_date = date('D M j Y', strtotime($expir_date));
					$is_expired = pwtc_members_is_expired($memberships[0]);
					if ($is_expired and !in_array('expired_member', $info->roles)) {
						$fix_roles = true;
					}
					if (!$is_expired and !in_array('current_member', $info->roles)) {
						$fix_roles = true;
					}
					if (in_array('expired_member', $info->roles) and 
						in_array('current_member', $info->roles)) {
						$fix_roles = true;
					}
				}
			}
			else {
				$note = 'cannot access membership';
			}
			$role = implode(", ", $info->roles);
			$riderid = get_field('rider_id', 'user_'.$profile->ID);
            if (!$riderid) {
                $riderid = '';
            }
			$item = array(
				'userid' => $profile->ID,
				'first_name' => trim($info->first_name),
				'last_name' => trim($info->last_name),
				'email' => trim($info->user_email),
				'expir_date' => $expir_date,
				'note' => $note,
				'role' => $role,
				'riderid' => $riderid,
				'fix_roles' => $fix_roles
			);
			if ($add_edit_link) {
				$href = admin_url('user-edit.php?user_id=' . $profile->ID);
				$item['editurl'] = $href;
			}
			$users[] = $item;
		}
		return $users;
	}

	/*************************************************************/
	/* Plugin options access functions
	/*************************************************************/

	public static function create_default_plugin_options() {
		$data = array(
			'plugin_menu_label' => 'Member Tools',
			'plugin_menu_location' => 50);
		add_option('pwtc_members_options', $data);
	}

	public static function get_plugin_options() {
		return get_option('pwtc_members_options');
	}

	public static function delete_plugin_options() {
		delete_option('pwtc_members_options');
	}

	public static function update_plugin_options($data) {
		update_option('pwtc_members_options', $data);
	}

    /*************************************************************/
    /* Plugin installation and removal functions.
    /*************************************************************/

	public static function plugin_activation() {
		self::write_log( 'PWTC Members plugin activated' );
		if ( version_compare( $GLOBALS['wp_version'], PWTC_MEMBERS__MINIMUM_WP_VERSION, '<' ) ) {
			deactivate_plugins(plugin_basename(__FILE__));
			wp_die('PWTC Members plugin requires Wordpress version of at least ' . PWTC_MEMBERS__MINIMUM_WP_VERSION);
		}
		if (self::get_plugin_options() === false) {
			self::create_default_plugin_options();
		}
    }
    
	public static function plugin_deactivation( ) {
		self::write_log( 'PWTC Members plugin deactivated' );
    }
    
	public static function plugin_uninstall() {
		self::write_log( 'PWTC Members plugin uninstall' );	
		self::delete_plugin_options();
    }
    
    public static function write_log ( $log )  {
        if ( true === WP_DEBUG ) {
            if ( is_array( $log ) || is_object( $log ) ) {
                error_log( print_r( $log, true ) );
            } else {
                error_log( $log );
            }
        }
    }

}