<div class="wrap">
	<h1><?= esc_html(get_admin_page_title()); ?></h1>
<?php
if (!current_user_can($capability)) {
?> 
	<p><strong>Access Denied</strong> - you do not have the rights to view this page.</p>
<?php   
}
else {
?>
<script type="text/javascript">
jQuery(document).ready(function($) { 

    function fix_user_roles_cb(response) {
        var res = JSON.parse(response);
        if (res.error) {
            $('#user-lookup-section .fix-msg-div').html(res.error);
        }
        else {
            load_user_table();
        }
    }

	function populate_users_table(users) {
		$('#user-lookup-section .users-div').empty();
        if (users.length > 0) {
           $('#user-lookup-section .users-div').append('<table class="pwtc-members-rwd-table">' +
                '<tr><th>First Name</th><th>Last Name</th><th>Email</th><th>Role</th><th>Rider ID</th><th>Expiration Date</th><th>Note</th><th>Actions</th></tr>' +
                '</table>');
            users.forEach(function(item) {
                var edit_link = '';
                if (item.editurl) {
                    edit_link = '<a title="Edit user account profile." target="_blank" href="' + item.editurl + '">Edit</a>';
                }
                var fix_link = '';
                if (item.fix_roles) {
                    fix_link = '<a title="Fix user membership roles." class="fix-btn">Fix</a>';
                }
                $('#user-lookup-section .users-div table').append(
                    '<tr userid="' + item.userid + '">' + 
                    '<td data-th="First Name">' + item.first_name + '</td>' +
                    '<td data-th="Last Name">' + item.last_name + '</td>' + 
                    '<td data-th="Email">' + item.email + '</td>' +
                    '<td data-th="Role">' + item.role + '</td>' + 
                    '<td data-th="Rider ID">' + item.riderid + '</td>' +
                    '<td data-th="Expiration">' + item.expir_date + '</td>' + 
                    '<td data-th="Note">' + item.note + '</td>' + 
                    '<td data-th="Actions">' + edit_link + ' ' + fix_link + '</td>' +
                    '</tr>');    
            });
            $('#user-lookup-section .users-div .fix-btn').on('click', function(evt) {
                evt.preventDefault();
                $('#user-lookup-section .fix-msg-div').html('<i class="fa fa-spinner fa-pulse"></i> Please wait...');
                var action = '<?php echo admin_url('admin-ajax.php'); ?>';
                var data = {
                    'action': 'pwtc_members_fix_user_roles',
                    'nonce': '<?php echo wp_create_nonce('pwtc_members_fix_user_roles'); ?>',
                    'userid': $(this).parent().parent().attr('userid')
                };
                $.post(action, data, fix_user_roles_cb);
            });
        }
        else {
            $('#user-lookup-section .users-div').html('No user accounts found.');
        }
    }
    
	function lookup_users_cb(response) {
        var res = JSON.parse(response);
        if (res.error) {
            $('#user-lookup-section .users-div').html(res.error);
        }
        else {
            $("#user-lookup-section .search-frm input[name='memberid']").val(res.memberid);
            $("#user-lookup-section .search-frm input[name='firstname']").val(res.firstname);
            $("#user-lookup-section .search-frm input[name='lastname']").val(res.lastname);
            $("#user-lookup-section .search-frm input[name='email']").val(res.email);
            populate_users_table(res.users);
        }
    }

    function load_user_table() {
        $('#user-lookup-section .fix-msg-div').empty();
        var memberid = $("#user-lookup-section .search-frm input[name='memberid']").val().trim();
        var firstname = $("#user-lookup-section .search-frm input[name='firstname']").val().trim();
        var lastname = $("#user-lookup-section .search-frm input[name='lastname']").val().trim();
        var email = $("#user-lookup-section .search-frm input[name='email']").val().trim();
        var exact = false;
        if ($("#user-lookup-section .search-frm input[name='exact']").is(':checked')) {
            exact = true;
        }
        if (memberid.length > 0 || lastname.length > 0 || firstname.length > 0 || email.length > 0) {
            $('#user-lookup-section .users-div').html('<i class="fa fa-spinner fa-pulse"></i> Please wait...');
            var action = $('#user-lookup-section .search-frm').attr('action');
            var data = {
                'action': 'pwtc_members_lookup_users',
                'memberid': memberid,
                'firstname': firstname,
                'lastname': lastname,
                'email': email,
                'exact': exact
            };
            $.post(action, data, lookup_users_cb);
        }
        else {
            $('#user-lookup-section .users-div').empty();
        } 
    }

    $('#user-lookup-section .search-frm').on('submit', function(evt) {
        evt.preventDefault();
        load_user_table();
    });

    $('#user-lookup-section .search-frm .reset-btn').on('click', function(evt) {
        evt.preventDefault();
        $("#user-lookup-section .search-frm input[type='text']").val(''); 
        $('#user-lookup-section .users-div').empty();
        $('#user-lookup-section .fix-msg-div').empty();
    });

    $("#user-lookup-section .search-frm input[type='text']").val('');   
    $("#user-lookup-section .search-frm input[name='memberid']").focus();
});
</script>
    <div id="user-lookup-section">
        <p>Use this page to lookup user accounts by their first/last name, email or rider ID.</p>
        <div class='pwtc-members-search-sec'>
        	<form class="search-frm pwtc-members-stacked-form" action="<?php echo admin_url('admin-ajax.php'); ?>" method="post">
                <span>Rider ID</span>
                <input name="memberid" type="text"/>
                <span>First Name</span>
                <input name="firstname" type="text"/>
                <span>Last Name</span>
                <input name="lastname" type="text"/>
                <span>Email</span>
                <input name="email" type="text"/>
		        <span>Exact Match</span>
		        <span class="pwtc-members-checkbox-wrap">
			        <input type="checkbox" name="exact" checked/>
		        </span>
				<input class="button button-primary" type="submit" value="Search"/>
				<input class="reset-btn button button-primary" type="button" value="Reset"/>
			</form>
        </div>
        <p><div class="users-div"></div></p>
        <p><div class="fix-msg-div"></div></p>
    </div>
<?php
}
?>
</div>
<?php
