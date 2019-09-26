<div class="wrap">
	<h1><?= esc_html(get_admin_page_title()); ?></h1>
<?php
if (!current_user_can($capability)) {
?> 
	<p><strong>Access Denied</strong> - you do not have the rights to view this page.</p>
<?php   
}
else {
    $results = PwtcMembers::fetch_users_with_multi_memberships('wc_user_membership');
    $results2 = PwtcMembers::fetch_users_with_multi_memberships('wc_memberships_team');
?>
    <div>
        <p>Use this page to detect all user accounts that have multiple memberships. Any such accounts should be corrected by the membership secretary to have only one membership.</p>
        <?php if (empty($results)) { ?>
        <p>No users with multiple memberships found.</p>
        <?php } else { ?>
        <table class="pwtc-members-rwd-table">
            <caption>Users With Multiple Memberships</caption>
            <tr><th>User ID</th><th>Email</th><th>First Name</th><th>Last Name</th><th>Actions</th></tr>
            <?php
            foreach ($results as $item) {
                $userid = $item[0];
                $user_info = get_userdata( $userid ); 
                if ($user_info) {
                    $edit_url = admin_url('user-edit.php?user_id=' . $userid);       
            ?>
			<tr>
				<td data-th="ID"><?php echo $userid; ?></td>
				<td data-th="Email"><?php echo $user_info->user_email; ?></td>
				<td data-th="First"><?php echo $user_info->first_name; ?></td>
                <td data-th="Last"><?php echo $user_info->last_name; ?></td>
                <td data-th="Actions"><a title="Edit user account profile." href="<?php echo $edit_url; ?>" target="_blank">Edit</a></td>
            </tr>
            <?php 
                }
            } 
            ?>
        </table>
        <?php } ?>
        <?php if (empty($results2)) { ?>
        <p>No users owning multiple family memberships found.</p>
        <?php } else { ?>
        <table class="pwtc-members-rwd-table">
            <caption>Users Owning Multiple Family Memberships</caption>
            <tr><th>User ID</th><th>Email</th><th>First Name</th><th>Last Name</th><th>Actions</th></tr>
            <?php
            foreach ($results2 as $item) {
                $userid = $item[0];
                $user_info = get_userdata( $userid ); 
                if ($user_info) {
                    $edit_url = admin_url('user-edit.php?user_id=' . $userid);       
            ?>
			<tr>
				<td data-th="ID"><?php echo $userid; ?></td>
				<td data-th="Email"><?php echo $user_info->user_email; ?></td>
				<td data-th="First"><?php echo $user_info->first_name; ?></td>
                <td data-th="Last"><?php echo $user_info->last_name; ?></td>
                <td data-th="Actions"><a title="Edit user account profile." href="<?php echo $edit_url; ?>" target="_blank">Edit</a></td>
            </tr>
            <?php 
                }
            } 
            ?>
        </table>
        <?php } ?>
    </div>
<?php
}
?>
</div>
<?php
