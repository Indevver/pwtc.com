
function lookup_pwtc_riders(mycb) {
    jQuery('#rider-lookup-results .error-msg').html('');
    jQuery('#rider-lookup-results .lookup-tlb tr').remove();
    jQuery("#rider-lookup-results .lookup-frm input[type='text']").val('');
    jQuery("#rider-lookup-results").dialog('open');
    window.pwtc_mileage_rider_cb = mycb;
} 

function lookup_pwtc_riders_all(mycb) {
    jQuery("#rider-lookup-results .lookup-frm input[name='autoaccept']").val('no');
    jQuery("#rider-lookup-results .lookup-frm input[name='active']").prop('checked', false);
    lookup_pwtc_riders(mycb);
}

function lookup_pwtc_riders_active(mycb) {
    jQuery("#rider-lookup-results .lookup-frm input[name='autoaccept']").val('no');
    jQuery("#rider-lookup-results .lookup-frm input[name='active']").prop('checked', true);
    lookup_pwtc_riders(mycb);
}

function open_confirm_dialog(msg, mycb) {
    window.pwtc_mileage_confirm_cb = mycb;
    jQuery("#confirm-dialog p").html(msg);
    jQuery("#confirm-dialog").dialog('open');
}

// TODO: handle case where error dialog is already open.
function open_error_dialog(msg) {
    jQuery("#error-dialog p").html(msg);
    jQuery("#error-dialog").dialog('open');
}

function getPrettyDate(d) {
 	var fmt = new DateFormatter();
    return fmt.formatDate(fmt.parseDate(d, 'Y-m-d'), 'D M j Y');
}

// TODO: this function does not work for certain dates.
function getInternalDate(d) {
    var fmt = new DateFormatter();
   return fmt.formatDate(fmt.parseDate(d, 'D M j Y'), 'Y-m-d');
}