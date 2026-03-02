<?php
/**
 * Plugin Name:       NFT SaaS – Gasless NFT Marketplace
 * Plugin URI:        https://github.com/spntnhub/nft-saas
 * Description:       Sell your artwork as NFTs directly from WordPress — no gas fees for artists. Buyers pay gas at purchase. Platform earns 2% commission.
 * Version:           1.0.0
 * Author:            spntn
 * Author URI:        https://spntn.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       nft-saas
 * Requires at least: 6.0
 * Requires PHP:      8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'NFT_SAAS_VERSION', '1.0.0' );
define( 'NFT_SAAS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'NFT_SAAS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'NFT_SAAS_PLUGIN_FILE', __FILE__ );

// ── Load includes ──────────────────────────────────────────────────────────
require_once NFT_SAAS_PLUGIN_DIR . 'includes/class-platform-settings.php';
require_once NFT_SAAS_PLUGIN_DIR . 'includes/class-media-integration.php';
require_once NFT_SAAS_PLUGIN_DIR . 'includes/class-manual-image-panel.php';

// ── Boot ────────────────────────────────────────────────────────────────────
function nft_saas_boot() {
    $settings = new NFT_SaaS_Platform_Settings();
    $settings->init();

    $media = new NFT_SaaS_Media_Integration();
    $media->init();

    $panel = new NFT_SaaS_Manual_Image_Panel();
    $panel->init();
}
add_action( 'plugins_loaded', 'nft_saas_boot' );

// ── Activation ──────────────────────────────────────────────────────────────
register_activation_hook( __FILE__, 'nft_saas_activate' );
function nft_saas_activate() {
    // Store the activation time; first-run setup notice handled in admin
    if ( ! get_option( 'nft_saas_activated_at' ) ) {
        update_option( 'nft_saas_activated_at', time() );
        update_option( 'nft_saas_show_setup_notice', 1 );
    }
}

// ── Deactivation ────────────────────────────────────────────────────────────
register_deactivation_hook( __FILE__, 'nft_saas_deactivate' );
function nft_saas_deactivate() {
    // Clean up transients, keep settings (user can reactivate)
    delete_transient( 'nft_saas_jwt_token' );
}

// ── First-run setup notice ───────────────────────────────────────────────────
add_action( 'admin_notices', 'nft_saas_setup_notice' );
function nft_saas_setup_notice() {
    if ( ! get_option( 'nft_saas_show_setup_notice' ) ) return;
    if ( ! current_user_can( 'manage_options' ) ) return;

    $settings_url = admin_url( 'admin.php?page=nft-saas-settings' );
    ?>
    <div class="notice notice-info is-dismissible" id="nft-saas-setup-notice">
        <p>
            <strong>🎨 NFT SaaS activated!</strong>
            <?php if ( ! get_option( 'nft_saas_api_key' ) ): ?>
                You're almost ready — <a href="<?php echo esc_url( $settings_url ); ?>"><strong>click here to connect to the platform (30 seconds)</strong></a>.
            <?php else: ?>
                Everything is set up. <a href="<?php echo esc_url( $settings_url ); ?>">Manage settings →</a>
            <?php endif; ?>
        </p>
    </div>
    <script>
    jQuery(document).on('click', '#nft-saas-setup-notice .notice-dismiss', function() {
        jQuery.post(ajaxurl, { action: 'nft_saas_dismiss_notice', _wpnonce: '<?php echo wp_create_nonce("nft_saas_dismiss"); ?>' });
    });
    </script>
    <?php
}

// Dismiss setup notice AJAX handler
add_action( 'wp_ajax_nft_saas_dismiss_notice', function() {
    check_ajax_referer( 'nft_saas_dismiss', '_wpnonce' );
    delete_option( 'nft_saas_show_setup_notice' );
    wp_send_json_success();
} );

// Save API key validation timestamp AJAX handler
add_action( 'wp_ajax_nft_saas_save_validation', function() {
    check_ajax_referer( 'nft_saas_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }
    update_option( 'nft_saas_api_validated_at', time() );
    wp_send_json_success();
} );
