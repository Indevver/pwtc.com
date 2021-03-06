<?php
/**
 * Teams for WooCommerce Memberships
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@skyverge.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Teams for WooCommerce Memberships to newer
 * versions in the future. If you wish to customize Teams for WooCommerce Memberships for your
 * needs please refer to https://docs.woocommerce.com/document/teams-woocommerce-memberships/ for more information.
 *
 * @author    SkyVerge
 * @category  Templates
 * @copyright Copyright (c) 2017-2018, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

/**
 * Team membership ended email
 *
 * @type string $email_heading email heading
 * @type \SkyVerge\WooCommerce\Memberships\Teams\Team $team the team instance
 *
 * @version 1.0.0
 * @since 1.0.0
 */

$owner = $team->get_owner();
$plan  = $team->get_plan();

$site_title          = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
$membership_end_date = date_i18n( wc_date_format(), $team->get_local_membership_end_date( 'timestamp' ) );

echo "= " . $email_heading . " =\n\n";

printf( esc_html__( 'Hey %s', 'woocommerce-memberships-for-teams' ), $owner->display_name );

echo "\r\n\r\n";

printf( esc_html__( 'Oh no, your family membership access at %s has now ended!', 'woocommerce-memberships-for-teams' ), $site_title ) . "\r\n";

printf( esc_html__( 'If you would like to continue having access to %s, please renew your membership.', 'woocommerce-memberships-for-teams' ), $plan->get_name() );

echo "\r\n\r\n";

esc_html_e( 'Use the link below to log in and renew your team membership now.', 'woocommerce-memberships-for-teams' ) . "\r\n";

echo esc_url( $team->get_renew_membership_url() ) . "\r\n";

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) );
