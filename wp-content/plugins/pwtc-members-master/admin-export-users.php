<div class="wrap">
	<h1><?= esc_html(get_admin_page_title()); ?></h1>
<?php
if (!current_user_can($capability)) {
?> 
	<p><strong>Access Denied</strong> - you do not have the rights to view this page.</p>
<?php   
}
else {
    global $wp_roles;
    $available_roles = array();
    foreach ( $wp_roles->roles as $key => $value ) {
        $available_roles[] = $key;
    }
    $queries = PwtcMembers_Admin::fetch_canned_queries();
?>
<script type="text/javascript">
jQuery(document).ready(function($) { 

	function populate_users_table(users) {
		$('#export-user-section .users-div').empty();
        if (users.length > 0) {
            $('#export-user-section .users-div').append(
                '<table class="pwtc-members-rwd-table"><tr><th>Username</th><th>Email</th><th>First Name</th><th>Last Name</th><th>User ID</th><th>Rider ID</th></tr></table>');
            users.forEach(function(item) {
                $('#export-user-section .users-div table').append(
                    '<tr userid="' + item.userid + '">' + 
                    '<td data-th="Username">' + item.user_login + '</td>' + 
                    '<td data-th="Email">' + item.user_email + '</td>' +
                    '<td data-th="First Name">' + item.first_name + '</td>' +
                    '<td data-th="Last Name">' + item.last_name + '</td>' + 
                    '<td data-th="User ID">' + item.userid + '</td>' + 
                    '<td data-th="Rider ID">' + item.riderid + '</td>' +
                    '</tr>');    
            });
        }
        else {
            $('#export-user-section .users-div').html('No user accounts found.');
        }
    }

	function load_query_cb(response) {
        var res = JSON.parse(response);
        if (res.error) {
            $("#export-user-section .users-div").empty();
            $("#export-user-section .query-div").hide();
            $("#export-user-section .err-msg").html(res.error);
        }
        else {
            $("#export-user-section .users-div").empty();
            $("#export-user-section .err-msg").empty();
            $("#export-user-section .query-frm textarea[name='includes']").val(res.includes);
            $("#export-user-section .query-frm textarea[name='excludes']").val(res.excludes);
            $("#export-user-section .query-frm input[name='file']").val(res.file);
            $("#export-user-section .query-frm #" + res.riderid).prop("checked", true);
            $("#export-user-section .query-frm #" + res.nameset).prop("checked", true);
            $("#export-user-section .query-div").show();
        }
	}   

    function show_users_cb(response) {
        var res = JSON.parse(response);
        if (res.error) {
            $('#export-user-section .users-div').html(res.error);
        }
        else {
            populate_users_table(res.users);
        }
    }

    $('#export-user-section .query-slt').change(function() {
        var query = $('#export-user-section .query-slt').val();
        if (query != 'none') {
            $("#export-user-section .users-div").empty();
            $("#export-user-section .query-div").hide();
            $('#export-user-section .err-msg').html('<i class="fa fa-spinner fa-pulse"></i> Please wait...');
            var action = '<?php echo admin_url('admin-ajax.php'); ?>';
            var data = {
                'action': 'pwtc_members_fetch_query',
                'query': query
            };
            $.post(action, data, load_query_cb);
        }
        else {
            $("#export-user-section .users-div").empty();
            $("#export-user-section .err-msg").empty();
            $("#export-user-section .query-div").hide();
        }
    });

    $('#export-user-section .query-div .query-frm .show-btn').on('click', function(evt) {
        evt.preventDefault();
        var includes = $("#export-user-section .query-frm textarea[name='includes']").val().trim();
        var excludes = $("#export-user-section .query-frm textarea[name='excludes']").val().trim();
        var riderid = $("#export-user-section .query-frm input[name='riderid']:checked").val();
        var nameset = $("#export-user-section .query-frm input[name='nameset']:checked").val();
        $('#export-user-section .users-div').html('<i class="fa fa-spinner fa-pulse"></i> Please wait...');
        var action = '<?php echo admin_url('admin-ajax.php'); ?>';
        var data = {
            'action': 'pwtc_members_show_users',
            'includes': includes,
            'excludes': excludes,
            'riderid': riderid,
            'nameset': nameset
        };
        $.post(action, data, show_users_cb);
});

    $("#export-user-section .query-div").hide();
    $('#export-user-section .query-slt').focus(); 
});
</script>
    <div id="export-user-section">
        <p>Use this page to show or export user accounts selected by a query criteria. Criteria consist of user roles to include or exclude (separated by a space) and whether or not the Rider ID is set. Here is a list of the currently available roles: <code><?php echo implode(' ', $available_roles); ?></code> Use the <strong>CSV File Name</strong> field to specify the name of the exported CSV file. (This file name will be prepended with the current date.) Select the <strong>Include User Details</strong> checkbox to include the user's billing address and phone number in the exported data.</p>
        <p>Canned Queries:&nbsp;
            <select class='query-slt'>
                <option value="none" selected>-- select a query --</option>
                <?php
                foreach ($queries as $name => $query) {
                    $label = $query['label'];
                ?>
                <option value="<?php echo $name; ?>"><?php echo $label; ?></option>
                <?php } ?>
            </select>
            &nbsp;<span class="err-msg"></span>
        </p>
        <div class="pwtc-members-search-sec query-div">
        <form class="pwtc-members-stacked-form query-frm" method="POST">
            <span>Include These Roles</span>
            <textarea name="includes" rows="5" wrap></textarea>
            <span>Exclude These Roles</span>
            <textarea name="excludes" rows="5" wrap></textarea>
            <span>Detect Rider ID</span>
            <span class="pwtc-members-checkbox-wrap">
                <input type="radio" id="off" name="riderid" value="off" checked/>
                <label for="off">Off</label>
            </span>
            <span class="pwtc-members-checkbox-wrap">
                <input type="radio" id="set" name="riderid" value="set"/>
                <label for="set">ID Set</label>
            </span>
            <span class="pwtc-members-checkbox-wrap">
                <input type="radio" id="not_set" name="riderid" value="not_set"/>
                <label for="not_set">ID Not Set</label>
            </span>
            <span>Detect First/Last Name</span>
            <span class="pwtc-members-checkbox-wrap">
                <input type="radio" id="name_off" name="nameset" value="off" checked/>
                <label for="off">Off</label>
            </span>
            <span class="pwtc-members-checkbox-wrap">
                <input type="radio" id="name_set" name="nameset" value="set"/>
                <label for="set">Name Set</label>
            </span>
            <span class="pwtc-members-checkbox-wrap">
                <input type="radio" id="name_not_set" name="nameset" value="not_set"/>
                <label for="not_set">Name Not Set</label>
            </span>
            <span>CSV File Name</span>
            <input type="text" name="file" value="" required/>
            <span>Include User Details</span>
            <span class="pwtc-members-checkbox-wrap">
                <input type="checkbox" name="details"/>
            </span>
            <input type="button" value="Show Users" class="show-btn button button-primary"/>
            <input type="submit" value="Export Users" class="button button-primary"/>
        </form>
        </div>
        <p><div class="users-div"></div></p>
    </div>
<?php
}
?>
</div>
<?php
