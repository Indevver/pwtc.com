<div class="wrap">
	<h1><?= esc_html(get_admin_page_title()); ?></h1>
<?php
if (!current_user_can($capability)) {
?> 
	<p><strong>Access Denied</strong> - you do not have the rights to view this page.</p>
<?php   
}
else if ($plugin_options['admin_maint_mode'] and !current_user_can('manage_options')) {
?> 
	<p><strong>Access Denied</strong> - the mileage database is maintenance mode.</p>
<?php       
}
else {
?>
<script type="text/javascript" >
jQuery(document).ready(function($) {  

<?php if ($plugin_options['show_ride_ids']) { ?>
	var show_ride_id = true;
<?php } else { ?>
	var show_ride_id = false;
<?php } ?>		

<?php if ($plugin_options['disable_delete_confirm']) { ?>
	var disable_delete_confirm = true;
<?php } else { ?>
	var disable_delete_confirm = false;
<?php } ?>	

	var show_guid = true;	
	var linked_to_ride = false;
	var ridesheet_title;
	var ridesheet_date;
	var confirm_reassoc = true;

	function set_ridesheet_lock(locked) {
		if (locked) {
			$("#ridesheet-sheet-page .leader-div .remove-btn").hide();
			$("#ridesheet-sheet-page .mileage-div .edit-btn").hide();
			$("#ridesheet-sheet-page .mileage-div .remove-btn").hide();
			$("#ridesheet-sheet-page .rename-btn").attr("disabled", "disabled");
			$("#ridesheet-sheet-page .reassoc-btn").attr("disabled", "disabled");
			$("#ridesheet-sheet-page .leader-section .lookup-btn").attr("disabled", "disabled");
			$("#ridesheet-sheet-page .mileage-section .lookup-btn").attr("disabled", "disabled");
		}
		else {
			$("#ridesheet-sheet-page .leader-div .remove-btn").show();
       		$("#ridesheet-sheet-page .mileage-div .edit-btn").show();
       		$("#ridesheet-sheet-page .mileage-div .remove-btn").show();
       		$("#ridesheet-sheet-page .rename-btn").removeAttr("disabled");
       		$("#ridesheet-sheet-page .reassoc-btn").removeAttr("disabled");
       		$("#ridesheet-sheet-page .leader-section .lookup-btn").removeAttr("disabled");
       		$("#ridesheet-sheet-page .mileage-section .lookup-btn").removeAttr("disabled");
		}
	}

	function populate_posts_table(posts) {
		$('#ridesheet-ride-page .posts-div').empty();
		if (posts.length > 0) {
			$('#ridesheet-ride-page .posts-div').append('<table class="rwd-table"></table>');
			if (show_ride_id) {
				$('#ridesheet-ride-page .posts-div table').append(
					'<tr><th>Posted Ride</th><th>Date</th><th>Post ID</th><th>Actions</th></tr>');
			}
			else {
				$('#ridesheet-ride-page .posts-div table').append(
					'<tr><th>Posted Ride</th><th>Date</th><th>Actions</th></tr>');
			}
			var fmt = new DateFormatter();
			posts.forEach(function(post) {
				var fmtdate = getPrettyDate(post[2]);
				guidlink = '';
				if (show_guid) {
					guidlink = '<a title="View this posted ride." href="' + 
						post[3] + '" target="_blank">View</a> ';
				}
				createlink = '<a title="Create a ride sheet for this posted ride." class="create-btn">Create</a>'
				if (show_ride_id) {
					$('#ridesheet-ride-page .posts-div table').append(
						'<tr postid="' + post[0] + '" ridedate="' + post[2] + '"><td data-th="Ride">' +
						post[1] + '</td><td data-th="Date">' + fmtdate + '</td><td data-th="ID">' + post[0] + '</td>' +
						' <td data-th="Actions">' + guidlink + ' ' +
						createlink + '</td></tr>');
				}
				else {
					$('#ridesheet-ride-page .posts-div table').append(
						'<tr postid="' + post[0] + '" ridedate="' + post[2] + '"><td data-th="Ride">' +
						post[1] + '</td><td data-th="Date">' + fmtdate + '</td>' + 
						' <td data-th="Actions">' + guidlink + ' ' +
						createlink + '</td></tr>');
				} 
			});
			$('#ridesheet-ride-page .posts-div .create-btn').on('click', function(evt) {
				evt.preventDefault();
				var action = '<?php echo admin_url('admin-ajax.php'); ?>';
				var data = {
					'action': 'pwtc_mileage_create_ride_from_event',
					'post_id': $(this).parent().parent().attr('postid'),
					'startdate': $(this).parent().parent().attr('ridedate'),
					'title': $(this).parent().parent().find('td').first().html(),
					'nonce': '<?php echo wp_create_nonce('pwtc_mileage_create_ride_from_event'); ?>'
				};
				open_confirm_dialog(
					'Are you sure you want to create a ride sheet for posted ride "' + data.title + '"?', 
					function() {
						$('body').addClass('waiting');
						$.post(action, data, lookup_ridesheet_cb);
					}
				);
			});
		}
		else {
			$('#ridesheet-ride-page .posts-div').append(
				'<span class="empty-tbl">No missing ride sheets.</span>');
		}
	}

	function populate_ridesheet_table(rides) {
		$('#ridesheet-ride-page .rides-div').empty();
		if (rides.length > 0) {
			$('#ridesheet-ride-page .rides-div').append('<table class="rwd-table"></table>');
			if (show_ride_id) {
				$('#ridesheet-ride-page .rides-div table').append(
					'<tr><th>Ride Sheet</th><th>Date</th><th>ID</th><th>Post ID</th><th>Actions</th></tr>'); 
			}
			else {
				$('#ridesheet-ride-page .rides-div table').append(
					'<tr><th>Ride Sheet</th><th>Date</th><th>Actions</th></tr>'); 
			}   
			var fmt = new DateFormatter();
			rides.forEach(function(item) {
				var fmtdate = getPrettyDate(item.date);
				editlink = '<a title="Edit this ride sheet." class="edit-btn">Edit</a>';
				deletelink = '<a title="Delete this ride sheet." class="remove-btn">Delete</a>';
				if (show_ride_id) {
					$('#ridesheet-ride-page .rides-div table').append(
						'<tr rideid="' + item.ID + '" ridedate="' + item.date + '"><td data-th="Ride">' +
						item.title + '</td><td data-th="Date">' + fmtdate + '</td><td data-th="ID">' + 
						item.ID + '</td><td data-th="Post ID">' + 
						item.post_id + '</td><td data-th="Actions">' + editlink + ' ' +
						deletelink + '</td></tr>'); 
				}
				else {
					$('#ridesheet-ride-page .rides-div table').append(
						'<tr rideid="' + item.ID + '" ridedate="' + item.date + '"><td data-th="Ride">' +
						item.title + '</td><td data-th="Date">' + fmtdate + '</td><td data-th="Actions">' + 
						editlink + ' ' + deletelink +
						'</td></tr>'); 
				}   
			});
			$('#ridesheet-ride-page .rides-div .edit-btn').on('click', function(evt) {
				evt.preventDefault();
				var action = '<?php echo admin_url('admin-ajax.php'); ?>';
				var data = {
					'action': 'pwtc_mileage_lookup_ridesheet',
					'ride_id': $(this).parent().parent().attr('rideid')
				};
				$('body').addClass('waiting');
				$.post(action, data, lookup_ridesheet_cb);
			});
			$('#ridesheet-ride-page .rides-div .remove-btn').on('click', function(evt) {
				evt.preventDefault();
				var action = '<?php echo admin_url('admin-ajax.php'); ?>';
				var data = {
					'action': 'pwtc_mileage_remove_ride',
					'ride_id': $(this).parent().parent().attr('rideid'),
					'nonce': '<?php echo wp_create_nonce('pwtc_mileage_remove_ride'); ?>'
				};
				if (disable_delete_confirm) {
					$('body').addClass('waiting');
					$.post(action, data, remove_ride_cb);
				} 
				else {
					open_confirm_dialog(
						'Are you sure you want to delete ride sheet titled "' + 
							$(this).parent().parent().find('td').first().html() + '"?', 
						function() {
							$('body').addClass('waiting');
							$.post(action, data, remove_ride_cb);
						}
					);
				}		
			});
		}
		else {
			$('#ridesheet-ride-page .rides-div').append(
				'<span class="empty-tbl">No ride sheets found.</span>');
		}
	}

	function populate_ride_leader_table(ride_id, leaders) {
		$('#ridesheet-sheet-page .leader-div').empty();
		if (leaders.length) {
			$('#ridesheet-sheet-page .leader-div').append(
				'<table class="rwd-table"><tr><th>ID</th><th>Name</th><th>Actions</th></tr></table>');
			deletelink = '<a title="Delete this ride leader." class="remove-btn">Delete</a>'
			leaders.forEach(function(item) {
				$('#ridesheet-sheet-page .leader-div table').append(
					'<tr rideid="' + ride_id + '" memberid="' + item.member_id + '">' + 
					'<td data-th="ID">' + item.member_id + '</td>' +
					'<td data-th="Name">' + item.first_name + ' ' + item.last_name + '</td>' + 
					'<td data-th="Actions">' + deletelink + '</td></tr>');    
			});
			$('#ridesheet-sheet-page .leader-div .remove-btn').on('click', function(evt) {
				evt.preventDefault();
				var action = '<?php echo admin_url('admin-ajax.php'); ?>';
				var data = {
					'action': 'pwtc_mileage_remove_leader',
					'ride_id': $(this).parent().parent().attr('rideid'),
					'member_id': $(this).parent().parent().attr('memberid'),
					'nonce': '<?php echo wp_create_nonce('pwtc_mileage_remove_leader'); ?>'
				};
				if (disable_delete_confirm) {
					$('body').addClass('waiting');
					$.post(action, data, remove_leader_cb);
				}
				else {
					open_confirm_dialog(
						'Are you sure you want to delete ride leader ID ' + data.member_id + '?', 
						function() {
							$('body').addClass('waiting');
							$.post(action, data, remove_leader_cb);
						}
					);
				}		
			});
		}
		else {
			$('#ridesheet-sheet-page .leader-div').append(
				'<span class="empty-tbl">No leaders entered.</span>');
		}
	}

	function populate_ride_mileage_table(ride_id, mileage) {
		$('#ridesheet-sheet-page .mileage-div').empty();
		if (mileage.length > 0) {
			$('#ridesheet-sheet-page .mileage-div').append(
				'<table class="rwd-table"><tr><th>Line</th><th>ID</th><th>Name</th><th>Mileage</th><th>Actions</th></tr></table>');
			var editlink = '<a title="Edit this rider\'s mileage." class="edit-btn">Edit</a>';
			var deletelink = '<a title="Delete this rider\'s mileage." class="remove-btn">Delete</a>';
			var count = 1;
			mileage.forEach(function(item) {
				$('#ridesheet-sheet-page .mileage-div table').append(
					'<tr rideid="' + ride_id + 
					'" memberid="' + item.member_id + 
					'" lineno="' + item.line_no + '">' + 
					'<td data-th="Line">' + count + '</td>' +
					'<td data-th="ID">' + item.member_id + '</td>' +
					'<td data-th="Name">' + item.first_name + ' ' + item.last_name + '</td>' + 
					'<td data-th="Mileage">' + item.mileage + '</td>' +
					'<td data-th="Actions">' + editlink + ' ' + deletelink + 
					'</td></tr>');   
				count++; 
			});
			$('#ridesheet-sheet-page .mileage-div .edit-btn').on('click', function(evt) {
				evt.preventDefault();
				$("#ridesheet-sheet-page .mileage-section .add-frm input[type='submit']").val('Modify Mileage');
				$("#ridesheet-sheet-page .mileage-section .add-frm input[name='riderid']").val(
					$(this).parent().parent().attr('memberid')
				);
				$("#ridesheet-sheet-page .mileage-section .add-frm input[name='lineno']").val(
					$(this).parent().parent().attr('lineno')
				);
				$("#ridesheet-sheet-page .mileage-section .add-frm input[name='mode']").val('modify');
				$("#ridesheet-sheet-page .mileage-section .add-frm input[name='ridername']").val(
					$(this).parent().parent().find('td').eq(2).html()
				);
				$("#ridesheet-sheet-page .mileage-section .add-frm input[name='mileage']").val(
					$(this).parent().parent().find('td').eq(3).html()
				);
				$("#ridesheet-sheet-page .mileage-section .add-frm input[name='override']").prop("checked", true); 
				$('#ridesheet-sheet-page .mileage-section .lookup-btn').hide('fast', function() {
					$('#ridesheet-sheet-page .mileage-section .add-blk').show('slow');  
					$("#ridesheet-sheet-page .mileage-section .add-frm input[name='mileage']").focus();         
				});
			});
			$('#ridesheet-sheet-page .mileage-div .remove-btn').on('click', function(evt) {
				evt.preventDefault();
				var action = '<?php echo admin_url('admin-ajax.php'); ?>';
				var data = {
					'action': 'pwtc_mileage_remove_mileage',
					'ride_id': $(this).parent().parent().attr('rideid'),
					'member_id': $(this).parent().parent().attr('memberid'),
					'line_no': $(this).parent().parent().attr('lineno'),
					'nonce': '<?php echo wp_create_nonce('pwtc_mileage_remove_mileage'); ?>'
				};
				if (disable_delete_confirm) {
					$('body').addClass('waiting');
					$.post(action, data, remove_mileage_cb);
				}
				else {
					open_confirm_dialog(
						'Are you sure you want to delete the mileage for rider ID ' + data.member_id + '?', 
						function() {
							$('body').addClass('waiting');
							$.post(action, data, remove_mileage_cb);
						}
					);
				}		
			});
		}
		else {
			$('#ridesheet-sheet-page .mileage-div').append(
				'<span class="empty-tbl">No mileage entered.</span>');
		}
	}

	function lookup_posts_cb(response) {
        var res = JSON.parse(response);
		if (res.error) {
			open_error_dialog(res.error);
		}
		else {
			populate_posts_table(res.posts);
		}
		$('body').removeClass('waiting');
	}   

	function lookup_day_posts_cb(response) {
        var res = JSON.parse(response);
		if (res.error) {
			open_error_dialog(res.error);
		}
		else {
			res.posts.forEach(function(post) {
				$('#ridesheet-sheet-page .reassoc-blk .reassoc-frm select').append(
					'<option value="' + post[0] + '">' + post[1] + '</option>');
			});
		}
		$('body').removeClass('waiting');
	}   

	function lookup_rides_cb(response) {
        var res = JSON.parse(response);
		if (res.error) {
			open_error_dialog(res.error);
		}
		else {
			populate_ridesheet_table(res.rides);
		}
		$('body').removeClass('waiting');
	}   

	function show_ridesheet_section(ride_id, startdate, title, post_guid, mileage, leaders) {
		var fmtdate = getPrettyDate(startdate);
		ridesheet_title = title;
		ridesheet_date = startdate;
		$('#ridesheet-sheet-page .sheet-title').html(title);
		$('#ridesheet-sheet-page .sheet-date').html(fmtdate);
		if (show_guid && post_guid) {
			$('#ridesheet-sheet-page .sheet-guid').html(
				'Linked to this <a title="View linked posted ride." href="' + post_guid + '" target="_blank">ride</a>.');
		}
		else {
			$('#ridesheet-sheet-page .sheet-guid').html('Not linked to any ride.');
		}
		$("#ridesheet-sheet-page .rename-blk .rename-frm input[name='rideid']").val(ride_id); 
		$("#ridesheet-sheet-page .leader-section .add-frm input[name='rideid']").val(ride_id); 
		$("#ridesheet-sheet-page .mileage-section .add-frm input[name='rideid']").val(ride_id); 
		$("#ridesheet-sheet-page .reassoc-blk .reassoc-frm input[name='rideid']").val(ride_id); 
		populate_ride_leader_table(ride_id, leaders);
		populate_ride_mileage_table(ride_id, mileage);
		if (post_guid) {
			$("#ridesheet-sheet-page .rename-btn").hide();
			linked_to_ride = true;
		}
		else {
			$("#ridesheet-sheet-page .rename-btn").show();
			linked_to_ride = false;
		}
		$("#ridesheet-sheet-page .rename-blk").hide(); 
		$("#ridesheet-sheet-page .leader-section .lookup-btn").show();
		$("#ridesheet-sheet-page .leader-section .add-blk").hide(); 
		$("#ridesheet-sheet-page .mileage-section .lookup-btn").show();
		$("#ridesheet-sheet-page .mileage-section .add-blk").hide(); 
		$("#ridesheet-sheet-page .reassoc-btn").show();
		$("#ridesheet-sheet-page .reassoc-blk").hide(); 
		set_ridesheet_lock(title.startsWith('['));
		$('#ridesheet-ride-page').hide('fast', function() {
			$('#ridesheet-sheet-page').fadeIn('slow');
			$('#ridesheet-sheet-page .back-btn').focus();
		});
	}

	function return_main_section() {
		$('#ridesheet-sheet-page').hide('fast', function() {
<?php
if ($create_mode) {
?>
			load_posts_without_rides();
<?php
} else {
?>
			load_ride_table();
<?php
}
?>			
			$('#ridesheet-ride-page .add-blk').hide();
			$('#ridesheet-ride-page .add-btn').show();
			$('#ridesheet-ride-page').fadeIn('slow');
		});
	}

	function lookup_ridesheet_cb(response) {
        var res = JSON.parse(response);
		if (res.error) {
			open_error_dialog(res.error);
		}
		else {
			show_ridesheet_section(res.ride_id, res.startdate, res.title, res.post_guid, 
				res.mileage, res.leaders);
			if (history.pushState) {
				var state = {
					'action': 'pwtc_mileage_lookup_ridesheet',
					'ride_id': res.ride_id
				};
				history.pushState(state, '');
			}
		}
		$('body').removeClass('waiting');
	}

	function restore_ridesheet_cb(response) {
        var res = JSON.parse(response);
		if (res.error) {
			open_error_dialog(res.error);
		}
		else {
			show_ridesheet_section(res.ride_id, res.startdate, res.title, res.post_guid, 
				res.mileage, res.leaders);
		}
		$('body').removeClass('waiting');
	}

	function rename_ridesheet_cb(response) {
        var res = JSON.parse(response);
		if (res.error) {
			open_error_dialog(res.error);
		}
		else {
			ridesheet_title = res.title;
			ridesheet_date = res.date;
			$('#ridesheet-sheet-page .sheet-title').html(res.title);
			$('#ridesheet-sheet-page .sheet-date').html(getPrettyDate(res.date));
			$('#ridesheet-sheet-page .sheet-guid').html('Not linked to any ride.');
			$("#ridesheet-sheet-page .rename-btn").show();
			$("#ridesheet-sheet-page .rename-blk").hide(); 
		}
		$('body').removeClass('waiting');
	}

	function associate_ridesheet_cb(response) {
        var res = JSON.parse(response);
		if (res.error) {
			open_error_dialog(res.error);
		}
		else {
			if (res.title && res.date && res.post_url) {
				ridesheet_title = res.title;
				ridesheet_date = res.date;
				$('#ridesheet-sheet-page .sheet-title').html(res.title);
				$('#ridesheet-sheet-page .sheet-date').html(getPrettyDate(res.date));
				if (show_guid) {
					$('#ridesheet-sheet-page .sheet-guid').html(
						'Linked to this <a title="View linked posted ride." href="' + res.post_url + '" target="_blank">ride</a>.');
				}
				$("#ridesheet-sheet-page .rename-btn").hide();
				linked_to_ride = true;
			}
			else {
				$('#ridesheet-sheet-page .sheet-guid').html('Not linked to any ride.');
				$("#ridesheet-sheet-page .rename-btn").show();
				linked_to_ride = false;
			}
			$("#ridesheet-sheet-page .reassoc-btn").show();
			$("#ridesheet-sheet-page .reassoc-blk").hide(); 
		}
		$('body').removeClass('waiting');
	}

	function remove_ride_cb(response) {
		$('body').removeClass('waiting');
		var res = JSON.parse(response);
		if (res.error) {
			open_error_dialog(res.error);
		}
		else {
			load_ride_table();
		}
	}

	function load_ride_table() {
		var title = $("#ridesheet-ride-page .ride-search-frm input[name='title']").val().trim();
		var startdate = $("#ridesheet-ride-page .ride-search-frm input[name='fmtdate']").val().trim();
		var enddate = $("#ridesheet-ride-page .ride-search-frm input[name='tofmtdate']").val().trim();
		if (title.length > 0 || startdate.length > 0 || enddate.length > 0) {
			var action = $('#ridesheet-ride-page .ride-search-frm').attr('action');
			var data = {
				'action': 'pwtc_mileage_lookup_rides',
				'title': title,
				'startdate': startdate,
				'enddate': enddate
			};
			$('body').addClass('waiting');
			$.post(action, data, lookup_rides_cb);
		}		
		else {		
			$('#ridesheet-ride-page .rides-div').empty();		
		}
	}

	function remove_leader_cb(response) {
		var res = JSON.parse(response);
		if (res.error) {
			open_error_dialog(res.error);
		}
		else {
			populate_ride_leader_table(res.ride_id, res.leaders);
		}
		$('body').removeClass('waiting');
	}

	function remove_mileage_cb(response) {
		var res = JSON.parse(response);
		if (res.error) {
			open_error_dialog(res.error);
		}
		else {
			populate_ride_mileage_table(res.ride_id, res.mileage);
		}
		$('body').removeClass('waiting');
	}

	function add_leader_cb(response) {
		var res = JSON.parse(response);
		if (res.error) {
			open_error_dialog(res.error);
		}
		else {
			populate_ride_leader_table(res.ride_id, res.leaders);
			$('#ridesheet-sheet-page .leader-section .add-blk').hide('fast', function() {
				$("#ridesheet-sheet-page .leader-section .lookup-btn").show('slow');
				$("#ridesheet-sheet-page .leader-section .lookup-btn").focus();
			});
		}
		$('body').removeClass('waiting');
	}

	function add_mileage_cb(response) {
		var res = JSON.parse(response);
		if (res.error) {
			open_error_dialog(res.error);
		}
		else {
			populate_ride_mileage_table(res.ride_id, res.mileage);
			$('#ridesheet-sheet-page .mileage-section .add-blk').hide('fast', function() {
				$("#ridesheet-sheet-page .mileage-section .lookup-btn").show('slow');
				$("#ridesheet-sheet-page .mileage-section .lookup-btn").focus();
			});
		}
		$('body').removeClass('waiting');
	}

	function load_posts_without_rides() {
        var action = '<?php echo admin_url('admin-ajax.php'); ?>';
        var data = {
			'action': 'pwtc_mileage_lookup_posts'
		};
		$('body').addClass('waiting');
		$.post(action, data, lookup_posts_cb);
	}

	function init_modify_mode() {
		$('#ridesheet-ride-page .ride-search-frm').on('submit', function(evt) {
			evt.preventDefault();
			load_ride_table();
		});
	
		$('#ridesheet-ride-page .ride-search-frm .reset-btn').on('click', function(evt) {
			evt.preventDefault();
			$("#ridesheet-ride-page .ride-search-frm input[type='text']").val(''); 
			$("#ridesheet-ride-page .ride-search-frm input[type='hidden']").val(''); 
			$('#ridesheet-ride-page .rides-div').empty();
		});

		$("#ridesheet-ride-page .add-btn").on('click', function(evt) {
			open_confirm_dialog(
				'WARNING: this will create a ride sheet that is NOT linked with any posted ride. Do you want to continue?', 
				function() {
					$("#ridesheet-ride-page .add-blk .add-frm input[type='text']").val(''); 
					$("#ridesheet-ride-page .add-blk .add-frm input[type='hidden']").val(''); 
					$("#ridesheet-ride-page .add-btn").hide('fast', function() {
						$('#ridesheet-ride-page .add-blk').show('slow'); 
						$("#ridesheet-ride-page .add-blk .add-frm input[name='title']").focus();          
					});
				}
			);
		});
	
		$("#ridesheet-ride-page .add-blk .cancel-btn").on('click', function(evt) {
			$('#ridesheet-ride-page .add-blk').hide('slow', function() {
				$("#ridesheet-ride-page .add-btn").show('fast');
			});
		});
	
		$('#ridesheet-ride-page .add-blk .add-frm').on('submit', function(evt) {
			evt.preventDefault();
			var action = $('#ridesheet-ride-page .add-blk .add-frm').attr('action');
			var data = {
				'action': 'pwtc_mileage_create_ride',
				'title': $("#ridesheet-ride-page .add-blk .add-frm input[name='title']").val(),
				'startdate': $("#ridesheet-ride-page .add-blk .add-frm input[name='fmtdate']").val(),
				'nonce': '<?php echo wp_create_nonce('pwtc_mileage_create_ride'); ?>'
			};
			$('body').addClass('waiting');
			$.post(action, data, lookup_ridesheet_cb);
		});
	
		$("#ridesheet-ride-page .add-blk .add-frm input[name='date']").datepicker({
			dateFormat: 'D M d yy',
			altField: "#ridesheet-ride-page .add-blk .add-frm input[name='fmtdate']",
			altFormat: 'yy-mm-dd',
			changeMonth: true,
			changeYear: true
		});
	
		function getDate( element ) {
			var date;
			  try {
				date = $.datepicker.parseDate('D M d yy', element.value);
			  } catch( error ) {
				date = null;
			  }
			 return date;
		}
	
		var fromDate = $("#ridesheet-ride-page .ride-search-frm input[name='date']").datepicker({
			dateFormat: 'D M d yy',
			altField: "#ridesheet-ride-page .ride-search-frm input[name='fmtdate']",
			altFormat: 'yy-mm-dd',
			changeMonth: true,
			changeYear: true
		}).on( "change", function() {
			toDate.datepicker("option", "minDate", getDate(this));
			toDate.datepicker("setDate", getDate(this));
		});
	
		var toDate = $("#ridesheet-ride-page .ride-search-frm input[name='todate']").datepicker({
			dateFormat: 'D M d yy',
			altField: "#ridesheet-ride-page .ride-search-frm input[name='tofmtdate']",
			altFormat: 'yy-mm-dd',
			changeMonth: true,
			changeYear: true
		}).on( "change", function() {
			//fromDate.datepicker("option", "maxDate", getDate(this));
		});	

		$("#ridesheet-ride-page .ride-search-frm input[type='text']").val(''); 
		$("#ridesheet-ride-page .ride-search-frm input[type='hidden']").val(''); 
	}

	function init_create_mode() {
		load_posts_without_rides();	
	}

	$("#ridesheet-sheet-page .rename-blk .rename-frm input[name='date']").datepicker({
		dateFormat: 'D M d yy',
		altField: "#ridesheet-sheet-page .rename-blk .rename-frm input[name='fmtdate']",
		altFormat: 'yy-mm-dd',
		changeMonth: true,
		changeYear: true
	});

	$("#ridesheet-sheet-page .reassoc-blk .reassoc-frm input[name='date']").datepicker({
		dateFormat: 'D M d yy',
		altField: "#ridesheet-sheet-page .reassoc-blk .reassoc-frm input[name='fmtdate']",
		altFormat: 'yy-mm-dd',
		changeMonth: true,
		changeYear: true
	}).on( "change", function() {
		var date = $("#ridesheet-sheet-page .reassoc-blk .reassoc-frm input[name='date']").val().trim();
		var fmtdate = $("#ridesheet-sheet-page .reassoc-blk .reassoc-frm input[name='fmtdate']").val();
		$('#ridesheet-sheet-page .reassoc-blk .reassoc-frm select').empty();
		$('#ridesheet-sheet-page .reassoc-blk .reassoc-frm select').append(
			'<option value="0" selected>-- None --</option>');
		if (date.length > 0) {
			var action = '<?php echo admin_url('admin-ajax.php'); ?>';
			var data = {
				'action': 'pwtc_mileage_lookup_posts',
				'startdate': fmtdate,
				'enddate': fmtdate
			};
			$('body').addClass('waiting');
			$.post(action, data, lookup_day_posts_cb);
		}
	});

	$('#ridesheet-sheet-page .back-btn').on('click', function(evt) {
        //evt.preventDefault();
        if (history.pushState) {
			history.back();
		}
		else {
			return_main_section();
		}
	});

    $('#ridesheet-sheet-page .leader-section .add-frm').on('submit', function(evt) {
        evt.preventDefault();
        var action = $('#ridesheet-sheet-page .leader-section .add-frm').attr('action');
        var override = false;
        if ($("#ridesheet-sheet-page .leader-section .add-frm input[name='override']").is(':checked')) {
            override = true;
        }
        var data = {
			'action': 'pwtc_mileage_add_leader',
			'member_id': $("#ridesheet-sheet-page .leader-section .add-frm input[name='riderid']").val(),
			'ride_id': $("#ridesheet-sheet-page .leader-section .add-frm input[name='rideid']").val(),
			'override': override,
			'nonce': '<?php echo wp_create_nonce('pwtc_mileage_add_leader'); ?>'
		};
		$('body').addClass('waiting');
		$.post(action, data, add_leader_cb);
    });

    $('#ridesheet-sheet-page .mileage-section .add-frm').on('submit', function(evt) {
        evt.preventDefault();
        var action = $('#ridesheet-sheet-page .mileage-section .add-frm').attr('action');
        var override = false;
        if ($("#ridesheet-sheet-page .mileage-section .add-frm input[name='override']").is(':checked')) {
            override = true;
        }
        var data = {
			'action': 'pwtc_mileage_add_mileage',
			'member_id': $("#ridesheet-sheet-page .mileage-section .add-frm input[name='riderid']").val(),
			'ride_id': $("#ridesheet-sheet-page .mileage-section .add-frm input[name='rideid']").val(),
			'line_no': $("#ridesheet-sheet-page .mileage-section .add-frm input[name='lineno']").val(),
			'mode': $("#ridesheet-sheet-page .mileage-section .add-frm input[name='mode']").val(),
			'mileage': $("#ridesheet-sheet-page .mileage-section .add-frm input[name='mileage']").val(),
			'override': override,
			'nonce': '<?php echo wp_create_nonce('pwtc_mileage_add_mileage'); ?>'
		};
		$('body').addClass('waiting');
		$.post(action, data, add_mileage_cb);
    });

	$("#ridesheet-sheet-page .leader-section .lookup-btn").on('click', function(evt) {
        lookup_pwtc_riders(function(riderid, name) {
            $("#ridesheet-sheet-page .leader-section .add-frm input[name='riderid']").val(riderid);
            $("#ridesheet-sheet-page .leader-section .add-frm input[name='ridername']").val(name); 
			$("#ridesheet-sheet-page .leader-section .add-frm input[name='override']").prop("checked", false);			
			$("#ridesheet-sheet-page .leader-section .lookup-btn").hide('fast', function() {
				$('#ridesheet-sheet-page .leader-section .add-blk').show('slow'); 
				$("#ridesheet-sheet-page .leader-section .add-frm input[type='submit']").focus();          
			});
        });
    });

	$("#ridesheet-sheet-page .leader-section .add-frm .cancel-btn").on('click', function(evt) {
 		$('#ridesheet-sheet-page .leader-section .add-blk').hide('fast', function() {
			$("#ridesheet-sheet-page .leader-section .lookup-btn").show('slow');          
			$("#ridesheet-sheet-page .leader-section .lookup-btn").focus();
		}); 
    });

	$("#ridesheet-sheet-page .mileage-section .lookup-btn").on('click', function(evt) {
        lookup_pwtc_riders(function(riderid, name) {
			$("#ridesheet-sheet-page .mileage-section .add-frm input[type='submit']").val('Add Mileage');
            $("#ridesheet-sheet-page .mileage-section .add-frm input[name='riderid']").val(riderid);
            $("#ridesheet-sheet-page .mileage-section .add-frm input[name='lineno']").val('');
			$("#ridesheet-sheet-page .mileage-section .add-frm input[name='mode']").val('add');
            $("#ridesheet-sheet-page .mileage-section .add-frm input[name='ridername']").val(name); 
			$("#ridesheet-sheet-page .mileage-section .add-frm input[name='mileage']").val(''); 
			$("#ridesheet-sheet-page .mileage-section .add-frm input[name='override']").prop("checked", false); 
			$("#ridesheet-sheet-page .mileage-section .lookup-btn").hide('fast', function() {
				$('#ridesheet-sheet-page .mileage-section .add-blk').show('slow');  
				$("#ridesheet-sheet-page .mileage-section .add-frm input[name='mileage']").focus();         
			});
        });
    });

	$("#ridesheet-sheet-page .mileage-section .add-frm .cancel-btn").on('click', function(evt) {
 		$('#ridesheet-sheet-page .mileage-section .add-blk').hide('fast', function() {
			$('#ridesheet-sheet-page .mileage-section .lookup-btn').show('slow');           
			$("#ridesheet-sheet-page .mileage-section .lookup-btn").focus();
		});
    });

	$("#ridesheet-sheet-page .rename-btn").on('click', function(evt) {
		$("#ridesheet-sheet-page .rename-blk .rename-frm input[name='title']").val(ridesheet_title); 
		$("#ridesheet-sheet-page .rename-blk .rename-frm input[name='date']").val(getPrettyDate(ridesheet_date)); 
		$("#ridesheet-sheet-page .rename-blk .rename-frm input[name='fmtdate']").val(ridesheet_date); 
		$("#ridesheet-sheet-page .rename-btn").hide('fast', function() {
			$('#ridesheet-sheet-page .rename-blk').show('slow'); 
			$("#ridesheet-sheet-page .rename-blk .rename-frm input[name='title']").focus();          
		});
    });

	$("#ridesheet-sheet-page .rename-blk .cancel-btn").on('click', function(evt) {
		$('#ridesheet-sheet-page .rename-blk').hide('slow', function() {
			$("#ridesheet-sheet-page .rename-btn").show('fast');
		});
    });
	
	$('#ridesheet-sheet-page .rename-blk .rename-frm').on('submit', function(evt) {
        evt.preventDefault();
        var action = $('#ridesheet-sheet-page .rename-blk .rename-frm').attr('action');
        var data = {
			'action': 'pwtc_mileage_rename_ride',
			'ride_id': $("#ridesheet-sheet-page .rename-blk .rename-frm input[name='rideid']").val(),
			'title': $("#ridesheet-sheet-page .rename-blk .rename-frm input[name='title']").val(),
			'date': $("#ridesheet-sheet-page .rename-blk .rename-frm input[name='fmtdate']").val(),
			'nonce': '<?php echo wp_create_nonce('pwtc_mileage_rename_ride'); ?>'
		};
		$('body').addClass('waiting');
		$.post(action, data, rename_ridesheet_cb);
    });

	$("#ridesheet-sheet-page .reassoc-btn").on('click', function(evt) {
		$("#ridesheet-sheet-page .reassoc-blk .reassoc-frm input[type='text']").val(''); 
		$("#ridesheet-sheet-page .reassoc-blk .reassoc-frm input[name='fmtdate']").val(''); 
		$('#ridesheet-sheet-page .reassoc-blk .reassoc-frm select').empty();
		$('#ridesheet-sheet-page .reassoc-blk .reassoc-frm select').append(
			'<option value="0" selected>-- None --</option>');
		$("#ridesheet-sheet-page .reassoc-btn").hide('fast', function() {
			$('#ridesheet-sheet-page .reassoc-blk').show('slow'); 
		});
    });

	$("#ridesheet-sheet-page .reassoc-blk .cancel-btn").on('click', function(evt) {
		$('#ridesheet-sheet-page .reassoc-blk').hide('slow', function() {
			$("#ridesheet-sheet-page .reassoc-btn").show('fast');
		});
    });
	
	$('#ridesheet-sheet-page .reassoc-blk .reassoc-frm').on('submit', function(evt) {
        evt.preventDefault();
		if (confirm_reassoc) {
			var msg;
			var unlink = $("#ridesheet-sheet-page .reassoc-blk .reassoc-frm select").val() == '0';
			if (linked_to_ride) {
				if (unlink) {
					msg = 'WARNING: this ride sheet will be unlinked from its current posted ride. Do you want to continue?'
				}
				else {
					msg = 'WARNING: this ride sheet will be unlinked from its current posted ride and re-linked to the selected one. Do you want to continue?'
				}
			}
			else {
				if (unlink) {
					msg = 'This ride sheet is currently not linked to any posted ride. Do you want to continue?'
				}
				else {
					msg = 'This ride sheet will be linked to the selected posted ride. Do you want to continue?';
				}
			}
            open_confirm_dialog(msg, 
                function() {
                    confirm_reassoc = false;
                    $("#ridesheet-sheet-page .reassoc-blk .reassoc-frm input[name='reassoc']").click();
				});
		}
		else {
			confirm_reassoc = true;
			var action = $('#ridesheet-sheet-page .reassoc-blk .reassoc-frm').attr('action');
			var data = {
				'action': 'pwtc_mileage_associate_ride',
				'ride_id': $("#ridesheet-sheet-page .reassoc-blk .reassoc-frm input[name='rideid']").val(),
				'post_id': $("#ridesheet-sheet-page .reassoc-blk .reassoc-frm select").val(),
				'nonce': '<?php echo wp_create_nonce('pwtc_mileage_associate_ride'); ?>'
			};
			$('body').addClass('waiting');
			$.post(action, data, associate_ridesheet_cb);
		}
    });

    if (history.pushState) {
		$(window).on('popstate', function(evt) {
			var state = evt.originalEvent.state;
			if (state !== null) {
				//console.log("Popstate event, state is " + JSON.stringify(state));
				var action = '<?php echo admin_url('admin-ajax.php'); ?>';
				$('body').addClass('waiting');
				$.post(action, state, restore_ridesheet_cb);
			}
			else {
				//console.log("Popstate event, state is null.");
				return_main_section();
			}
		});
	}
    else {
        //console.log("history.pushState is not supported");
    }

<?php
if ($create_mode) {
?>
	init_create_mode();
<?php
} else {
?>
	init_modify_mode();
<?php
}
?>

});
</script>
<?php
if ($running_jobs > 0) {
?>
    <div class="notice notice-warning"><p><strong>
        A database batch operation is currently running!
    </strong></p></div>
<?php
} else {
?>
	<div id="ridesheet-ride-page">
	<?php
	if ($create_mode) {
	?>
		<p>When you receive a ride sign in sheet from the ride leader, use this page to create a ride sheet and link it to the appropriate posted ride.</p>
		<h2>Posted Rides Without Ride Sheets</h2>
		<div class="posts-div"></div>
	<?php
	} else {
	?>
		<p>When you need to update an already existing ride sheet, use this page to find and edit it.</p>
        <div class='search-sec'>
		<p><strong>Enter search parameters to find ride sheets.</strong>
		<form class="ride-search-frm stacked-form" action="<?php echo admin_url('admin-ajax.php'); ?>" method="post">
			<span>Title</span>
			<input type="text" name="title"/>
			<span>From Date</span>
			<input type="text" name="date" required/>
			<span>To Date</span>
			<input type="text" name="todate" required/>
			<input type="hidden" name="fmtdate"/>
			<input type="hidden" name="tofmtdate"/>
			<input class="button button-primary" type="submit" value="Search"/>
			<input class="reset-btn button button-primary" type="button" value="Reset"/>
		</form></p>	
		</div>

		<p><div><button class="add-btn button button-primary button-large">New</button>
		<span class="add-blk popup-frm initially-hidden">
			<form class="add-frm stacked-form" action="<?php echo admin_url('admin-ajax.php'); ?>" method="post">
				<span>Title</span>
				<input name="title" type="text" required/>
				<span>Date</span>
				<input name="date" type="text" required/>				
				<input type="hidden" name="fmtdate"/>
				<input class="button button-primary" type="submit" value="Create"/>
				<input class="cancel-btn button button-primary" type="button" value="Cancel"/>
			</form>
		</span></div></p>
		<p><div class="rides-div"></div></p>
	<?php
	}
	?>
	</div>
	<div id="ridesheet-sheet-page" class="initially-hidden">
		<p>Use this page to record the ride leader and rider mileages entered on the ride sign in sheet.</p>
		<p><button class="back-btn button button-primary button-large">&lt; Back</button></p>
		<div class='report-sec'>
		<h3>Ride Sheet</h3>
		<h3>
			<span class="sheet-title"></span> - <span class="sheet-date"></span>
		</h3>
		<div><button class="rename-btn button button-primary">Rename</button>
		<span class="rename-blk popup-frm initially-hidden">
			<form class="rename-frm stacked-form" action="<?php echo admin_url('admin-ajax.php'); ?>" method="post">
				<span>Title</span>
				<input name="title" type="text" required/>
				<span>Date</span>
				<input name="date" type="text" required/>
				<input type="hidden" name="rideid"/>
				<input type="hidden" name="fmtdate"/>
				<input class="button button-primary" type="submit" value="Rename"/>
				<input class="cancel-btn button button-primary" type="button" value="Cancel"/>
			</form>
		</span></div>
		</div>
		<div class="leader-section report-sec">
			<h3>Ride Leaders</h3>
			<div><button class="lookup-btn button button-primary">Lookup Leader</button>
				<span class="add-blk popup-frm initially-hidden">
					<form class="add-frm stacked-form" action="<?php echo admin_url('admin-ajax.php'); ?>" method="post">
						<span>ID</span>
						<input name="riderid" type="text" disabled/>
						<span>Name</span>
						<input name="ridername" type="text" disabled/>
						<span>Ignore Expiration</span>
		        		<span class="checkbox-wrap">
			        		<input type="checkbox" name="override"/>
		        		</span>
						<input name="rideid" type="hidden"/>
						<input class="button button-primary" type="submit" value="Add Leader"/>
						<input class="cancel-btn button button-primary" type="button" value="Cancel"/>
					</form>
				</span>
			</div>
			<p><div class="leader-div"></div></p>
		</div>
		<div class="mileage-section report-sec">
			<h3>Rider Mileage</h3>
			<div><button class="lookup-btn button button-primary">Lookup Rider</button>
				<span class="add-blk popup-frm initially-hidden">
					<form class="add-frm stacked-form" action="<?php echo admin_url('admin-ajax.php'); ?>" method="post">
						<span>ID</span>
						<input name="riderid" type="text" disabled/>
						<span>Name</span>
						<input name="ridername" type="text" disabled/>
						<span>Mileage</span>
						<input name="mileage" type="text" required/>
						<span>Ignore Expiration</span>
		        		<span class="checkbox-wrap">
			        		<input type="checkbox" name="override"/>
		        		</span>
						<input name="rideid" type="hidden"/>
						<input name="lineno" type="hidden"/>
						<input name="mode" type="hidden" value="add"/>
						<input class="button button-primary" type="submit" value="Add Mileage"/>
						<input class="cancel-btn button button-primary" type="button" value="Cancel"/>
					</form>
				</span>
			</div>
			<p><div class="mileage-div"></div></p>
		</div>
		<div class='report-sec'>
		<h3>Ride Linkage</h3>
		<p class="sheet-guid"></p>
		<div><button class="reassoc-btn button button-primary">Change</button>
		<span class="reassoc-blk popup-frm initially-hidden">
			<form class="reassoc-frm stacked-form" action="<?php echo admin_url('admin-ajax.php'); ?>" method="post">
				<span>Date</span>
				<input name="date" type="text"/>
				<span>Posted Ride</span>
				<select name="select">
				  	<option value="0" selected>-- None --</option> 
				</select>
				<input type="hidden" name="fmtdate"/>
				<input type="hidden" name="rideid"/>
				<input class="button button-primary" type="submit" name="reassoc" value="Link to Ride"/>
				<input class="cancel-btn button button-primary" type="button" value="Cancel"/>
			</form>
		</span></div>
		</div>
	</div>
<?php
	include('admin-rider-lookup.php');
}
}
?>
</div>
<?php
