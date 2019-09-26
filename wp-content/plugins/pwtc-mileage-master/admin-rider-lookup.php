<?php
?>
<script type="text/javascript">
jQuery(document).ready(function($) { 

    function populate_riders_table(members) {
        $('#rider-lookup-results .error-msg').html('');
		$('#rider-lookup-results .lookup-tlb tr').remove();
        members.forEach(function(item) {
            $('#rider-lookup-results .lookup-tlb').append(
				'<tr memberid="' + item.member_id + '">' + 
				'<td>' + item.member_id + '</td>' +
				'<td>' + item.first_name + ' ' + item.last_name + '</td></tr>');    
		});
        $('#rider-lookup-results .lookup-tlb tr').on('click', function(evt) {
            $("#rider-lookup-results").dialog('close');
            if (window.pwtc_mileage_rider_cb) {
                window.pwtc_mileage_rider_cb($(this).attr('memberid'), 
                    $(this).find('td').first().next().html());
                delete window.pwtc_mileage_rider_cb;
            }
        });
        return members.length;
    }

    function lookup_riders_cb(response) {
        var res = JSON.parse(response);
        var num_riders = populate_riders_table(res.members);
        var autoaccept = $("#rider-lookup-results .lookup-frm input[name='autoaccept']").val();
        if (num_riders == 1 && autoaccept == 'yes') {
            $("#rider-lookup-results").dialog('close');
            if (window.pwtc_mileage_rider_cb) {
                window.pwtc_mileage_rider_cb(res.members[0].member_id, 
                    res.members[0].first_name + ' ' + res.members[0].last_name);
                delete window.pwtc_mileage_rider_cb;
            }
        }
        else if (num_riders == 0) {
            $('#rider-lookup-results .lookup-tlb tr').remove();
            $('#rider-lookup-results .error-msg').html('No riders found!');
        }
    } 

    $('#rider-lookup-results .lookup-frm').on('submit', function(evt) {
        evt.preventDefault();
        var action = $('#rider-lookup-results .lookup-frm').attr('action');
        var active = false;
        if ($("#rider-lookup-results .lookup-frm input[name='active']").is(':checked')) {
            active = true;
        }
        var data = {
			'action': 'pwtc_mileage_lookup_riders',
			'memberid': $("#rider-lookup-results .lookup-frm input[name='riderid']").val(),
			'lastname': $("#rider-lookup-results .lookup-frm input[name='lastname']").val(),
            'firstname': $("#rider-lookup-results .lookup-frm input[name='firstname']").val(),
            'active': active
		};
        $('#rider-lookup-results .error-msg').html('<i class="fa fa-spinner fa-pulse"></i> Please wait...');
        $('#rider-lookup-results .lookup-tlb tr').remove();
        $.post(action, data, lookup_riders_cb);
    });

    $( "#rider-lookup-results" ).dialog({
        autoOpen: false,
        height: 400,
        width: 250,
        modal: true
    });

    $("#confirm-dialog").dialog({
        autoOpen: false,
        height: "auto",
        width: 250,
        modal: true,
        buttons: [
            {
                text: "Cancel",
                click: function() {
                    $(this).dialog("close");
                }
            },
            {
                text: "OK",
                click: function() {
                    $(this).dialog("close");
                    if (window.pwtc_mileage_confirm_cb) {
                        window.pwtc_mileage_confirm_cb();
                        delete window.pwtc_mileage_confirm_cb;
                    }
                }
            }
        ]
    });

    $("#error-dialog").dialog({
        autoOpen: false,
        height: "auto",
        width: 250,
        modal: true,
        buttons: {
            "OK": function() {
                $(this).dialog("close");
            }
        }
    });

});
</script>
<div class="loading"><div><i class="fa fa-spinner fa-pulse"></i> Please wait...</div></div>
<div id="rider-lookup-results" title="Lookup Riders">
	<form class="lookup-frm stacked-form" action="<?php echo admin_url('admin-ajax.php'); ?>" method="post">
        <input type="hidden" name="autoaccept" value="yes"/>
        <span>ID</span>
        <input type="text" name="riderid"/>
        <span>First Name</span>
        <input type="text" name="firstname"/>
        <span>Last Name</span>
        <input type="text" name="lastname"/>
        <span>Active Riders Only</span>
        <span class="checkbox-wrap">
            <input type="checkbox" name="active" checked/>
        </span>
        <input class="button button-primary" type="submit" value="Lookup"/>       
    </form>
    <p>
        <div class='error-msg'></div>
        <table class="lookup-tlb"></table>
    </p>
</div>
<div id="confirm-dialog" title="Confirm">
    <p></p>   
</div>
<div id="error-dialog" title="Error">
    <p></p>   
</div>
<?php
