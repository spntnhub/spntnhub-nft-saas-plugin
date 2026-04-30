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

define( 'NFTSAAS_VERSION',     '1.3.0' );
define( 'NFTSAAS_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'NFTSAAS_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'NFTSAAS_PLUGIN_FILE', __FILE__ );

// ── Load includes ──────────────────────────────────────────────────────────
require_once NFTSAAS_PLUGIN_DIR . 'includes/class-platform-settings.php';
require_once NFTSAAS_PLUGIN_DIR . 'includes/class-media-integration.php';
require_once NFTSAAS_PLUGIN_DIR . 'includes/class-manual-image-panel.php';

// ── Boot ────────────────────────────────────────────────────────────────────
function nftsaas_boot() {
    $settings = new NFT_SaaS_Platform_Settings();
    $settings->init();

    $media = new NFT_SaaS_Media_Integration();
    $media->init();

    $panel = new NFT_SaaS_Manual_Image_Panel();
    $panel->init();
}
add_action( 'plugins_loaded', 'nftsaas_boot' );

// ── Activation ──────────────────────────────────────────────────────────────
register_activation_hook( __FILE__, 'nftsaas_activate' );
function nftsaas_activate() {
    if ( ! get_option( 'nftsaas_activated_at' ) ) {
        update_option( 'nftsaas_activated_at', time() );
        update_option( 'nftsaas_show_setup_notice', 1 );
    }
}

// ── Deactivation ────────────────────────────────────────────────────────────
register_deactivation_hook( __FILE__, 'nftsaas_deactivate' );
function nftsaas_deactivate() {
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
            <strong><?php esc_html_e( 'NFT SaaS activated!', 'nft-saas' ); ?></strong>
            <?php if ( ! get_option( 'nft_saas_api_key' ) ) : ?>
                <?php printf(
                    /* translators: %s: settings page URL */
                    wp_kses( __( 'You\'re almost ready &mdash; <a href="%s"><strong>click here to connect to the platform (30 seconds)</strong></a>.', 'nft-saas' ), [ 'a' => [ 'href' => [] ], 'strong' => [] ] ),
                    esc_url( $settings_url )
                ); ?>
            <?php else : ?>
                <?php printf(
                    /* translators: %s: settings page URL */
                    wp_kses( __( 'Everything is set up. <a href="%s">Manage settings &rarr;</a>', 'nft-saas' ), [ 'a' => [ 'href' => [] ] ] ),
                    esc_url( $settings_url )
                ); ?>
            <?php endif; ?>
        </p>
    </div>
    <?php
}

// ── Admin enqueue: notice dismiss script ─────────────────────────────────────
add_action( 'admin_enqueue_scripts', 'nftsaas_enqueue_notice_script' );
function nftsaas_enqueue_notice_script() {
    if ( ! get_option( 'nft_saas_show_setup_notice' ) ) return;
    wp_register_script( 'nftsaas-notice', false, [ 'jquery' ], NFTSAAS_VERSION, true );
    wp_enqueue_script( 'nftsaas-notice' );
    wp_add_inline_script( 'nftsaas-notice', sprintf(
        'jQuery(document).on("click","#nft-saas-setup-notice .notice-dismiss",function(){jQuery.post(ajaxurl,{action:"nft_saas_dismiss_notice",_wpnonce:%s});});',
        wp_json_encode( wp_create_nonce( 'nft_saas_dismiss' ) )
    ) );
}

// ── Dismiss setup notice AJAX handler ────────────────────────────────────────
add_action( 'wp_ajax_nft_saas_dismiss_notice', function () {
    check_ajax_referer( 'nft_saas_dismiss', '_wpnonce' );
    delete_option( 'nft_saas_show_setup_notice' );
    wp_send_json_success();
} );

// ── Save API key validation timestamp AJAX handler ───────────────────────────
add_action( 'wp_ajax_nft_saas_save_validation', function () {
    check_ajax_referer( 'nft_saas_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }
    update_option( 'nft_saas_api_validated_at', time() );
    wp_send_json_success();
} );

// ── Gutenberg block registration ──────────────────────────────────────────────
add_action( 'enqueue_block_editor_assets', 'nftsaas_register_block_assets' );
function nftsaas_register_block_assets() {
    wp_enqueue_script(
        'nftsaas-block-editor',
        NFTSAAS_PLUGIN_URL . 'assets/nft-block.js',
        [ 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-element' ],
        NFTSAAS_VERSION,
        true
    );
    if ( file_exists( NFTSAAS_PLUGIN_DIR . 'assets/nft-block.css' ) ) {
        wp_enqueue_style(
            'nftsaas-block-editor',
            NFTSAAS_PLUGIN_URL . 'assets/nft-block.css',
            [],
            NFTSAAS_VERSION
        );
    }
}
