# PWTC Members
This is a Wordpress plugin used to provide additional shortcodes, admin pages and customizations to enhance the WooCommerce membership plugins for the [Portland Bicycling Club](https://portlandbicyclingclub.com).

## Installation
Download this distribution as a zip file, login to the portlandbicyclingclub.com Wordpress website as admin and upload this zip file as a new plugin. This plugin will be named **PWTC Members**, activate it from the Plugins management page.

### Plugin Uninstall
Deactivate and then delete the **PWTC Members** plugin from the Plugins management page.

## Plugin Shortcodes
These shortcodes allow users to add plugin specific content into Wordpress
pages. For example, if you place the following text string into your page content, it will 
render as a form that allows users to search the membership directory, limiting the number
of maps returned to 10 per page:

`[pwtc_member_directory limit="10"]`

### Membership Directory Shortcodes

`[pwtc_member_directory]` *form that allow search and access of the membership directory*

Argument|Description|Values|Default
--------|-----------|------|-------
limit|limit the number of members shown per page|(number)|0 (unlimited)
privacy|specify the member's privacy mode|off, exclude, hide|off

### Membership Statistics Shortcodes

`[pwtc_member_statistics]` *lists the membership statistics*

`[pwtc_member_families]` *lists the family membership statistics*

`[pwtc_member_new_members]` *lists the names of new members*

Argument|Description|Values|Default
--------|-----------|------|-------
lookback|the number of months to look back for new members|(number)|0

### Membership Nag Shortcodes

`[pwtc_member_renew_nag]` *nags the logged in user to renew their membership if expired*

Argument|Description|Values|Default
--------|-----------|------|-------
renewonly|display nag only if membership is expired|no, yes|no

`[pwtc_member_accept_release]` *nags the logged in user to accept the club's release statement*

### Ride Leader Shortcodes

`[pwtc_member_leader_contact]` *form that allow the logged in user to set their ride leader contact information*

## Member Tools Admin Pages
This menu group is added to the Wordpress admin menu bar. Users with the **administrator** roles will have the right to access these menu pages.
### Lookup Users
Use this page to lookup user accounts by their first/last name, email or rider ID.
### Export Users
Use this page to show or export (as a CSV file) user accounts selected by a query criteria.
### Multiple Memberships
Use this page to detect all user accounts that have multiple memberships. Any such accounts should be corrected by the membership secretary to have only one membership.
### Invalid Membership Roles
Use this page to detect any user accounts that do not have a membership but are erroneously marked with the Current Member or Expired Member role. If any are found, you are given the option to fix these records.
### Missing Membership Roles
Use this page to detect any user accounts that have a membership but are missing the proper Current Member or Expired Member role. If any are found, you are given the option to fix these records.
### Test Confirmation Email
Use this page to test the membership confirmation email mechanism.

## WooCommerce Membership Customizations
Customize the WooCommerce Membership plugin to support these additional features:
- Send a confirmation email when a new individual or family membership is purchased
- Set the **Release Accepted** checkbox field in a user's profile upon purchase checkout
- Remove access to the user profile page for users with non-admin roles
- Convert phone numbers entered on the **My Account** and **Checkout** pages to a pretty format
- Fix the expiration date display for family members on the **My Account** page
- Exclude membership products from the **Store** page
- Add the rider ID, release accepted flag and billing phone number to a member's details box

## Package Files Used By This Plugin
- `README.md` *this file*
- `pwtc-members.php` *plugin definition file*
- `acf-hooks.php` *Advanced Custom Fields hooks file*
- `pwtc-members-hooks.php` *plugin hooks file*
- `class.pwtcmembers.php` *PHP class with server-side logic*
- `class.pwtcmembers-admin.php` *PHP class with admin server-side logic*
- `admin-export-user.php` *client-side logic for Export Users admin page*
- `admin-invalid-members.php` *client-side logic for Invalid Membership Roles admin page*
- `admin-lookup-users.php` *client-side logic for Lookup Users admin page*
- `admin-missing-members.php` *client-side logic for Missing Membership Roles admin page*
- `admin-multi-members.php` *client-side logic for Multiple Memberships admin page*
- `admin-test-email.php` *client-side logic for Test Confirmation Email admin page*
- `reports-style.css` *stylesheet for report shortcodes*