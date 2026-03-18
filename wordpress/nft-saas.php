<?php
/**
 * Plugin Name:       NFT SaaS – Gasless NFT Marketplace
 * Plugin URI:        https://github.com/spntnhub/nft-saas-wp
 * Description:       Sell your artwork as NFTs directly from WordPress — no gas fees for artists. Buyers pay gas at purchase. Platform earns 3% fee (POL, USDC, USDT).
 * Version:           1.3.0
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

define( 'NFTSAAS_VERSION', '1.3.0' );
define( 'NFTSAAS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'NFTSAAS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'NFTSAAS_PLUGIN_FILE', __FILE__ );

// ── Load includes ──────────────────────────────────────────────────────────
require_once NFTSAAS_PLUGIN_DIR . 'includes/class-platform-settings.php';
require_once NFTSAAS_PLUGIN_DIR . 'includes/class-media-integration.php';
require_once NFTSAAS_PLUGIN_DIR . 'includes/class-manual-image-panel.php';

// ── Boot ────────────────────────────────────────────────────────────────────
function nftsaas_boot() {
    $settings = new NFTSAAS_Platform_Settings();
    $settings->init();

    $media = new NFTSAAS_Media_Integration();
    $media->init();

    $panel = new NFTSAAS_Manual_Image_Panel();
    $panel->init();
}
add_action( 'plugins_loaded', 'nftsaas_boot' );

// ── Activation ──────────────────────────────────────────────────────────────
register_activation_hook( __FILE__, 'nftsaas_activate' );
function nftsaas_activate() {
    // Store the activation time; first-run setup notice handled in admin
    if ( ! get_option( 'nftsaas_activated_at' ) ) {
        update_option( 'nftsaas_activated_at', time() );
        update_option( 'nftsaas_show_setup_notice', 1 );
    }
}

// ── Deactivation ────────────────────────────────────────────────────────────
register_deactivation_hook( __FILE__, 'nftsaas_deactivate' );
function nftsaas_deactivate() {
    // Clean up transients, keep settings (user can reactivate)
    delete_transient( 'nftsaas_jwt_token' );
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
        jQuery.post(ajaxurl, { action: 'nft_saas_dismiss_notice', _wpnonce: '<?php echo esc_attr( wp_create_nonce("nft_saas_dismiss" ) ); ?>' });
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

add_action( 'wp_enqueue_scripts', 'nftsaas_enqueue_assets' );
function nftsaas_enqueue_assets() {
    wp_enqueue_style(
        'nftsaas-admin-settings',
        NFTSAAS_PLUGIN_URL . 'assets/admin-settings.css',
        [],
        NFTSAAS_VERSION
    );
    wp_enqueue_style(
        'nftsaas-block',
        NFTSAAS_PLUGIN_URL . 'assets/nft-block.css',
        [],
        NFTSAAS_VERSION
    );
    wp_enqueue_script(
        'nftsaas-web3',
        NFTSAAS_PLUGIN_URL . 'assets/web3.min.js',
        [],
        NFTSAAS_VERSION,
        true
    );
    wp_enqueue_script(
        'nftsaas-admin-settings',
        NFTSAAS_PLUGIN_URL . 'assets/admin-settings.js',
        [],
        NFTSAAS_VERSION,
        true
    );
}
