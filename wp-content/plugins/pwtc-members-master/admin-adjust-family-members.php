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

	function adjust_this_cb(response) {
        var res = JSON.parse(response);
        $('#adjust-families-section .msg-div').html(res.status);
    }

    $('#adjust-families-section .adjust-frm').on('submit', function(evt) {
        evt.preventDefault();
        $('#adjust-families-section .msg-div').html('<i class="fa fa-spinner fa-pulse"></i> Please wait...');
        var action = $('#adjust-families-section .adjust-frm').attr('action');
        var data = {
            'action': 'pwtc_members_adjust_family_members',
            'nonce': '<?php echo wp_create_nonce('pwtc_members_adjust_family_members'); ?>'
        };
        $.post(action, data, adjust_this_cb);
    });

});
</script>
    <div id="adjust-families-section">
        <p>Use this page to adjust all family memberships so that their members have the same expiration date as the family membership.</p>
        <div>
        <form class="adjust-frm" action="<?php echo admin_url('admin-ajax.php'); ?>" method="POST">
            <input type="submit" value="Adjust" class="button button-primary button-large"/>
        </form>
        </div>
        <p><div class="msg-div"></div></p>
    </div>
<?php
}
?>
</div>
<?php
