<?php
/**
 * NFT SaaS - Hybrid Model Admin Settings Panel
 * WordPress admin panelinde "NFT SaaS Settings" menüsü oluştur
 */

class NFT_SaaS_Platform_Settings {
    
    public function __construct() {
        // Constructor does nothing, actions are hooked in init()
    }

    public function init() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);

        // Suppress other plugins' admin notices on our settings pages (keeps the header clean)
        add_action('in_admin_header', function() {
            $screen = get_current_screen();
            if ( $screen && strpos( $screen->id, 'nft-saas' ) !== false ) {
                remove_all_actions('admin_notices');
                remove_all_actions('all_admin_notices');
            }
        }, 99 );

        // AJAX: silently save the wallet address that was fetched from the backend
        add_action('wp_ajax_nft_saas_sync_wallet', [$this, 'ajax_sync_wallet']);
    }
    
    /**
     * Admin menüye ekle
     */
    public function add_admin_menu() {
        add_menu_page(
            'NFT SaaS Settings',                    // Page title
            'NFT SaaS',                              // Menu title
            'manage_options',                        // Capability
            'nft-saas-settings',                    // Menu slug
            [$this, 'render_settings_page'],        // Callback
            'dashicons-image',                       // Icon
            100                                      // Position
        );
        
        add_submenu_page(
            'nft-saas-settings',
            'Platform Settings',
            'Settings',
            'manage_options',
            'nft-saas-settings',
            [$this, 'render_settings_page']
        );
        
        add_submenu_page(
            'nft-saas-settings',
            'Minted NFTs',
            'My NFTs',
            'read',
            'nft-saas-mints',
            [$this, 'render_mints_page']
        );
        
        add_submenu_page(
            'nft-saas-settings',
            'Analytics',
            'Analytics',
            'manage_options',
            'nft-saas-analytics',
            [$this, 'render_analytics_page']
        );
    }
    
    /**
     * Ayarları register et
     */
    public function register_settings() {
        register_setting('nft_saas_group', 'nft_saas_platform_url');
        register_setting('nft_saas_group', 'nft_saas_api_key');
        register_setting('nft_saas_group', 'nft_saas_authorized_minter');
        register_setting('nft_saas_group', 'nft_saas_min_price');
        register_setting('nft_saas_group', 'nft_saas_min_price_currency');
        register_setting('nft_saas_group', 'nft_saas_daily_limit');
        register_setting('nft_saas_group', 'nft_saas_enable_marketplace');
        register_setting('nft_saas_group', 'nft_saas_marketplace_commission');
        // Multi-chain contract addresses
        register_setting('nft_saas_group', 'nft_saas_contract_ethereum');
        register_setting('nft_saas_group', 'nft_saas_contract_base');
        register_setting('nft_saas_group', 'nft_saas_contract_sepolia');
        // Button appearance
        register_setting('nft_saas_group', 'nft_saas_btn_bg',       ['sanitize_callback' => 'sanitize_hex_color', 'default' => '#111111']);
        register_setting('nft_saas_group', 'nft_saas_btn_color',    ['sanitize_callback' => 'sanitize_hex_color', 'default' => '#ffffff']);
        register_setting('nft_saas_group', 'nft_saas_btn_label',    ['sanitize_callback' => 'sanitize_text_field', 'default' => 'Buy this NFT — {price} {currency}']);
        register_setting('nft_saas_group', 'nft_saas_btn_show_network', ['sanitize_callback' => 'absint', 'default' => 1]);
        register_setting('nft_saas_group', 'nft_saas_btn_position', ['sanitize_callback' => 'sanitize_text_field', 'default' => 'below']);
        register_setting('nft_saas_group', 'nft_saas_btn_css_class',['sanitize_callback' => 'sanitize_html_class', 'default' => '']);
        register_setting('nft_saas_group', 'nft_saas_btn_radius',   ['sanitize_callback' => 'absint', 'default' => 6]);
    }

    /**
     * AJAX: save wallet synced from backend API key info (readonly field)
     */
    public function ajax_sync_wallet() {
        check_ajax_referer( 'nft_saas_nonce', 'nonce' );
        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error('Unauthorized');
        }
        $wallet = sanitize_text_field( wp_unslash( $_POST['wallet'] ?? '' ) );
        if ( $wallet ) {
            update_option( 'nft_saas_authorized_minter', $wallet );
        }
        wp_send_json_success();
    }
    
    /**
     * Admin scriptler yükle
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'nft-saas') === false) {
            return;
        }
        
        wp_enqueue_style('wp-jquery-ui-dialog');
        wp_enqueue_script('jquery-ui-dialog');
        
        $asset_url = defined('NFT_SAAS_PLUGIN_URL') ? NFT_SAAS_PLUGIN_URL : plugin_dir_url(__FILE__) . '../';

        wp_enqueue_style(
            'nft-saas-admin',
            $asset_url . 'assets/admin-settings.css',
            [],
            NFT_SAAS_VERSION
        );
        
        wp_enqueue_script(
            'nft-saas-admin',
            $asset_url . 'assets/admin-settings.js',
            ['jquery'],
            NFT_SAAS_VERSION,
            true
        );
        
        wp_localize_script('nft-saas-admin', 'nftSaasAdmin', [
            'nonce' => wp_create_nonce('nft_saas_nonce'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'platformUrl' => get_option('nft_saas_platform_url', 'https://nft-saas-production.up.railway.app')
        ]);
    }
    
    /**
     * Ana Settings page'i render et
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $platform_url = get_option('nft_saas_platform_url', 'https://nft-saas-production.up.railway.app');
        $api_key = get_option('nft_saas_api_key', '');
        $api_validated = get_option('nft_saas_api_validated_at', 0);
        $authorized_minter = get_option('nft_saas_authorized_minter', '');
        $min_price = get_option('nft_saas_min_price', '5');
        $min_price_currency = get_option('nft_saas_min_price_currency', 'POL');
        $daily_limit = get_option('nft_saas_daily_limit', '10');
        $enable_marketplace = get_option('nft_saas_enable_marketplace', 0);
        $marketplace_commission = get_option('nft_saas_marketplace_commission', '2');
        
        ?>
        <div class="wrap nft-saas-settings-wrap">
            <div class="nft-saas-header">
                <h1>🎨 NFT SaaS - Hybrid Model Settings</h1>
                <p class="subtitle">Manage, control, and earn 3% platform fee</p>
            </div>
            
            <div class="nft-saas-container">
                <!-- Left Panel: Settings -->
                <div class="nft-saas-main">
                    <form method="post" action="options.php" class="nft-saas-form">
                        <?php settings_fields('nft_saas_group'); ?>
                        
                        <!-- Platform URL -->
                        <div class="form-section">
                            <h2>📌 Platform Configuration</h2>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="platform_url">Platform URL</label>
                                    </th>
                                    <td>
                                        <input 
                                            type="url" 
                                            id="platform_url" 
                                            name="nft_saas_platform_url" 
                                            value="<?php echo esc_attr($platform_url); ?>"
                                            placeholder="https://your-platform.com"
                                            required
                                            class="regular-text"
                                        >
                                        <p class="description">The NFT SaaS backend URL. Keep the default unless you are self-hosting.</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="api_key">API Key</label>
                                    </th>
                                    <td>
                                        <input 
                                            type="password" 
                                            id="api_key" 
                                            name="nft_saas_api_key" 
                                            value="<?php echo esc_attr($api_key); ?>"
                                            placeholder="Paste your API key here"
                                            class="regular-text"
                                        >
                                        <p class="description">
                                            Your secret API key — links this site to your NFT SaaS account.
                                            <br><a href="#" id="open-register-modal" style="font-weight:bold; text-decoration:none;">🔑 Don't have one? Click here to generate instantly</a>
                                        </p>
                                        
                                        <!-- API Key Status -->
                                        <?php if ($api_key): ?>
                                            <div class="api-key-status">
                                                <?php if ($api_validated): ?>
                                                    <span class="status-badge status-valid">
                                                        ✓ Valid (Last checked: <?php echo date('M d, H:i', $api_validated); ?>)
                                                    </span>
                                                <?php else: ?>
                                                    <span class="status-badge status-warning">
                                                        ⚠ Not validated yet
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <button 
                                                type="button" 
                                                id="validate-api-btn" 
                                                class="button button-primary"
                                                data-api-key="<?php echo esc_attr($api_key); ?>"
                                            >
                                                Validate API Key
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="authorized_minter">Earnings Wallet</label>
                                    </th>
                                    <td>
                                        <input 
                                            type="text" 
                                            id="authorized_minter" 
                                            name="nft_saas_authorized_minter" 
                                            value="<?php echo esc_attr($authorized_minter); ?>"
                                            placeholder="Auto-synced from your account…"
                                            class="regular-text code"
                                            readonly
                                            style="background:#f6f6f6; cursor:default;"
                                        >
                                        <p class="description">
                                            Your wallet where <strong>97% of every NFT sale</strong> is sent automatically.
                                            Synced from your account — to change it, update your wallet in the NFT SaaS dashboard.
                                            <span id="wallet-sync-badge" style="display:none; margin-left:8px; color:#2271b1; font-size:0.85em;">⟳ syncing…</span>
                                        </p>
                                        <?php if ( ! $authorized_minter && $api_key ) : ?>
                                            <p id="wallet-warning" style="color:#d63638; font-weight:600;">⚠️ Wallet not set — buyers cannot purchase your NFTs until you add this.</p>
                                        <?php else : ?>
                                            <p id="wallet-warning" style="color:#d63638; font-weight:600; display:none;">⚠️ Wallet not set — buyers cannot purchase your NFTs until you add this.</p>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <!-- Mint Settings -->
                        <div class="form-section">
                            <h2>⚙️ Mint Settings</h2>
                            
                            <div class="info-box" style="background:#e6f7ff; border-left:4px solid #1890ff; padding:15px; margin-bottom:20px;">
                                <h4 style="margin-top:0;">🚀 Gasless Marketplace (Lazy Minting)</h4>
                                <p>This platform uses a <strong>Lazy Minting</strong> model.</p>
                                <ul>
                                    <li>❌ <strong>Artists pay $0 fees</strong> to list their work.</li>
                                    <li>✅ <strong>Buyers pay the gas fees</strong> when they purchase the NFT.</li>
                                    <li>⚡ The NFT is minted on-chain only at the moment of sale.</li>
                                </ul>
                            </div>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="min_price">Minimum Price</label>
                                    </th>
                                    <td>
                                        <input 
                                            type="number" 
                                            id="min_price" 
                                            name="nft_saas_min_price" 
                                            value="<?php echo esc_attr($min_price); ?>"
                                            step="0.001"
                                            min="0"
                                            class="small-text"
                                        >
                                        
                                        <select id="min_price_currency" name="nft_saas_min_price_currency" class="small-text">
                                            <option value="POL" <?php selected($min_price_currency, 'POL'); ?>>POL</option>
                                            <option value="ETH" <?php selected($min_price_currency, 'ETH'); ?>>ETH</option>
                                            <option value="USDC" <?php selected($min_price_currency, 'USDC'); ?>>USDC</option>
                                            <option value="USDT" <?php selected($min_price_currency, 'USDT'); ?>>USDT</option>
                                        </select>
                                        
                                        <p class="description">
                                            Minimum price at which users can list NFTs
                                        </p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="daily_limit">Daily Mint Limit (Free Tier)</label>
                                    </th>
                                    <td>
                                        <input 
                                            type="number" 
                                            id="daily_limit" 
                                            name="nft_saas_daily_limit" 
                                            value="<?php echo esc_attr($daily_limit); ?>"
                                            min="1"
                                            class="small-text"
                                        >
                                        <p class="description">
                                            How many NFTs can be minted per day (free tier users)
                                        </p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="enable_marketplace">Enable Built-in Marketplace</label>
                                    </th>
                                    <td>
                                        <label>
                                            <input 
                                                type="checkbox" 
                                                id="enable_marketplace" 
                                                name="nft_saas_enable_marketplace" 
                                                value="1"
                                                <?php checked($enable_marketplace, 1); ?>
                                            >
                                            Enable NFT sales on the site
                                        </label>
                                        <p class="description">
                                            If enabled, users can sell NFTs directly on the site
                                        </p>
                                    </td>
                                </tr>
                                
                                <?php if ($enable_marketplace): ?>
                                <tr>
                                    <th scope="row">
                                        <label for="marketplace_commission">Marketplace Commission (%)</label>
                                    </th>
                                    <td>
                                        <input 
                                            type="number" 
                                            id="marketplace_commission" 
                                            name="nft_saas_marketplace_commission" 
                                            value="<?php echo esc_attr($marketplace_commission); ?>"
                                            step="0.1"
                                            min="0"
                                            max="100"
                                            class="small-text"
                                        >
                                        <p class="description">
                                            Commission to be taken from sales made on the site
                                        </p>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                        
                        <!-- Royalty Info -->
                        <div class="form-section info-box">
                            <h2>💰 Royalty Information</h2>
                            <p>
                                <strong>Platform Fee: 3%</strong><br>
                                With every NFT sale, a 3% commission is automatically sent to your platform wallet.
                                Collected on-chain at the moment of purchase (POL, USDC, or USDT).
                            </p>
                            <ul>
                                <li>✓ On every sale: 3% fee goes to the platform instantly</li>
                                <li>✓ 97% goes directly to the artist wallet</li>
                                <li>✓ Accepted currencies: POL, USDC, USDT (Polygon Mainnet)</li>
                                <li>✓ Contract verified on PolygonScan (trustless)</li>
                            </ul>
                        </div>
                        
                        <!-- Blockchain Contract Addresses -->
                        <div class="form-section">
                            <h2>⛓️ Blockchain Contract Addresses</h2>
                            <p class="description">Polygon Mainnet contract is pre-configured. Add contract addresses for additional chains to enable multi-chain minting.</p>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">Polygon Mainnet</th>
                                    <td>
                                        <code>0xF912D97BB2fF635c3D432178e46A16930B5Af51A</code>
                                        <p class="description">✅ Active — pre-configured (MultiToken v2: POL + USDC + USDT, 3% fee)</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="contract_ethereum">Ethereum Mainnet</label></th>
                                    <td>
                                        <input type="text" id="contract_ethereum"
                                            name="nft_saas_contract_ethereum"
                                            value="<?php echo esc_attr( get_option('nft_saas_contract_ethereum', '') ); ?>"
                                            placeholder="0x…"
                                            class="regular-text code">
                                        <p class="description">Leave blank if not deployed.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="contract_base">Base Mainnet</label></th>
                                    <td>
                                        <input type="text" id="contract_base"
                                            name="nft_saas_contract_base"
                                            value="<?php echo esc_attr( get_option('nft_saas_contract_base', '') ); ?>"
                                            placeholder="0x…"
                                            class="regular-text code">
                                        <p class="description">Leave blank if not deployed.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="contract_sepolia">Sepolia Testnet</label></th>
                                    <td>
                                        <input type="text" id="contract_sepolia"
                                            name="nft_saas_contract_sepolia"
                                            value="<?php echo esc_attr( get_option('nft_saas_contract_sepolia', '') ); ?>"
                                            placeholder="0x…"
                                            class="regular-text code">
                                        <p class="description">For testing purposes.</p>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <!-- Button Appearance -->
                        <div class="form-section">
                            <h2>🎨 Button Appearance</h2>
                            <p class="description">Global defaults for all buy buttons. Individual images can override these in the Media Library.</p>
                            <p class="description"><strong>Label placeholders:</strong> <code>{price}</code> → sale price, <code>{currency}</code> → POL / ETH / USDC</p>

                            <?php
                            $btn_bg           = get_option('nft_saas_btn_bg',           '#111111');
                            $btn_color        = get_option('nft_saas_btn_color',        '#ffffff');
                            $btn_label        = get_option('nft_saas_btn_label',        'Buy this NFT — {price} {currency}');
                            $btn_show_network = get_option('nft_saas_btn_show_network', 1);
                            $btn_position     = get_option('nft_saas_btn_position',     'below');
                            $btn_css_class    = get_option('nft_saas_btn_css_class',    '');
                            $btn_radius       = get_option('nft_saas_btn_radius',       6);
                            ?>

                            <table class="form-table">
                                <tr>
                                    <th scope="row"><label for="btn_label">Button Label</label></th>
                                    <td>
                                        <input type="text" id="btn_label" name="nft_saas_btn_label"
                                            value="<?php echo esc_attr($btn_label); ?>"
                                            class="regular-text"
                                            placeholder="Buy this NFT — {price} {currency}">
                                        <p class="description">Use <code>{price}</code> and <code>{currency}</code> as placeholders.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Button Colors</th>
                                    <td style="display:flex;gap:24px;align-items:center;padding-top:10px;">
                                        <label>
                                            Background<br>
                                            <input type="color" name="nft_saas_btn_bg"
                                                id="btn_bg" value="<?php echo esc_attr($btn_bg); ?>"
                                                style="width:48px;height:32px;padding:2px;cursor:pointer;border:1px solid #ccc;border-radius:4px;">
                                        </label>
                                        <label>
                                            Text<br>
                                            <input type="color" name="nft_saas_btn_color"
                                                id="btn_color" value="<?php echo esc_attr($btn_color); ?>"
                                                style="width:48px;height:32px;padding:2px;cursor:pointer;border:1px solid #ccc;border-radius:4px;">
                                        </label>
                                        <div id="btn-preview-wrap" style="margin-left:16px;">
                                            <span style="font-size:0.75rem;color:#888;display:block;margin-bottom:4px;">Preview</span>
                                            <button id="btn-preview" disabled
                                                style="background:<?php echo esc_attr($btn_bg); ?>;color:<?php echo esc_attr($btn_color); ?>;border:none;border-radius:<?php echo esc_attr($btn_radius); ?>px;padding:8px 16px;font-size:0.82rem;font-weight:600;cursor:default;opacity:0.9;">
                                                <?php echo esc_html(str_replace(['{price}','{currency}'], ['10','POL'], $btn_label)); ?>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="btn_radius">Border Radius (px)</label></th>
                                    <td>
                                        <input type="number" id="btn_radius" name="nft_saas_btn_radius"
                                            value="<?php echo esc_attr($btn_radius); ?>"
                                            min="0" max="50" class="small-text">
                                        <p class="description">0 = square, 6 = default, 24 = pill shape.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="btn_show_network">Show Network Label</label></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" id="btn_show_network" name="nft_saas_btn_show_network"
                                                value="1" <?php checked($btn_show_network, 1); ?>>
                                            Show "Polygon Mainnet" label next to the button
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="btn_position">Default Position</label></th>
                                    <td>
                                        <select id="btn_position" name="nft_saas_btn_position">
                                            <option value="below"   <?php selected($btn_position,'below');   ?>>Below image (default)</option>
                                            <option value="above"   <?php selected($btn_position,'above');   ?>>Above image</option>
                                            <option value="overlay" <?php selected($btn_position,'overlay'); ?>>Overlay (bottom-right corner of image)</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="btn_css_class">Custom CSS Class</label></th>
                                    <td>
                                        <input type="text" id="btn_css_class" name="nft_saas_btn_css_class"
                                            value="<?php echo esc_attr($btn_css_class); ?>"
                                            class="regular-text"
                                            placeholder="e.g. my-nft-btn">
                                        <p class="description">Extra class added to every buy button — useful for theme CSS integration.</p>
                                    </td>
                                </tr>
                            </table>

                            <script>
                            (function(){
                                function updatePreview(){
                                    var bg  = document.getElementById('btn_bg').value;
                                    var tc  = document.getElementById('btn_color').value;
                                    var lbl = document.getElementById('btn_label').value
                                                .replace('{price}','10').replace('{currency}','POL');
                                    var r   = document.getElementById('btn_radius').value || 6;
                                    var btn = document.getElementById('btn-preview');
                                    btn.style.background    = bg;
                                    btn.style.color         = tc;
                                    btn.style.borderRadius  = r + 'px';
                                    btn.textContent         = lbl;
                                }
                                ['btn_bg','btn_color','btn_label','btn_radius'].forEach(function(id){
                                    var el = document.getElementById(id);
                                    if(el) el.addEventListener('input', updatePreview);
                                });
                            })();
                            </script>
                        </div>

                        <?php submit_button('Save Settings', 'primary', 'submit', true); ?>
                    </form>
                </div>
                
                <!-- Right Panel: Quick Stats -->
                <div class="nft-saas-sidebar">
                    <div class="sidebar-box">
                        <h3>📊 Quick Stats</h3>
                        <?php $this->render_quick_stats(); ?>
                    </div>
                    
                    <div class="sidebar-box">
                        <h3>🔗 Quick Links</h3>
                        <ul>
                            <li><a href="<?php echo esc_url(admin_url('admin.php?page=nft-saas-mints')); ?>">
                                View All NFTs
                            </a></li>
                            <li><a href="<?php echo esc_url(admin_url('admin.php?page=nft-saas-analytics')); ?>">
                                Analytics Dashboard
                            </a></li>
                            <li><a href="https://github.com/spntnhub/nft-saas/blob/main/docs/WORDPRESS_PLUGIN.md" target="_blank">
                                Plugin Documentation
                            </a></li>
                        </ul>
                    </div>
                    
                    <div class="sidebar-box info">
                        <h3>ℹ️ Support</h3>
                        <p>
                            Having trouble? Check the <a href="https://github.com/spntnhub/nft-saas/blob/main/docs/WORDPRESS_PLUGIN.md" target="_blank">Documentation</a>.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Registration Modal -->
        <div id="nft-register-modal" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); background:white; padding:30px; border-radius:8px; z-index:10000; box-shadow:0 10px 25px rgba(0,0,0,0.2); width:400px; max-width:90%;">
            <h2 style="margin-top:0;">🚀 Connect to NFT SaaS</h2>
            <p>Enter your details below. Your API Key will be generated instantly — no coding required.</p>
            
            <div id="reg-feedback" style="margin:10px 0; padding:10px; display:none;"></div>
            
            <p>
                <label style="font-weight:bold;">Email Address:</label><br>
                <input type="email" id="reg-email" class="large-text" placeholder="artist@example.com" style="width:100%; margin-top:5px;">
            </p>
            
            <p>
                <label style="font-weight:bold;">Earnings Wallet <span style="color:#888;font-weight:400;">(optional — can add later)</span>:</label><br>
                <input type="text" id="reg-wallet" class="large-text" placeholder="0x…" style="width:100%; margin-top:5px;">
                <small style="color:#666;">The wallet address where your NFT sale proceeds (98%) will be sent.</small>
            </p>
            
            <div style="text-align:right; margin-top:20px;">
                <button type="button" class="button" id="close-reg-modal">Cancel</button>
                <button type="button" class="button button-primary button-hero" id="do-register">✨ Get API Key</button>
            </div>
        </div>
        <div id="nft-modal-overlay" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:9999;"></div>
        <?php
    }
    
    /**
     * NFT'ler listesini göster
     */
    public function render_mints_page() {
        if (!current_user_can('read')) {
            wp_die('Unauthorized');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'nft_mints';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            echo '<div class="wrap"><h1>No NFTs yet</h1><p>You haven\'t minted any NFTs yet.</p></div>';
            return;
        }
        
        // Get user's NFTs
        $user_id = get_current_user_id();
        $mints = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE user_id = %d ORDER BY created_at DESC",
                $user_id
            )
        );
        
        ?>
        <div class="wrap">
            <h1>🎨 My NFTs</h1>
            
            <?php if (empty($mints)): ?>
                <p>You haven't minted any NFTs yet.</p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Token ID</th>
                            <th>Name</th>
                            <th>Min Price</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mints as $mint): ?>
                        <tr>
                            <td><?php echo esc_html($mint->token_id); ?></td>
                            <td><?php echo esc_html($mint->name); ?></td>
                            <td><?php echo esc_html($mint->min_price . ' ' . $mint->currency); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo esc_attr($mint->status); ?>">
                                    <?php echo esc_html(ucfirst($mint->status)); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html(date('M d, Y', strtotime($mint->created_at))); ?></td>
                            <td>
                                <a href="#" class="button button-small view-nft" 
                                   data-token-id="<?php echo esc_attr($mint->token_id); ?>">
                                    View
                                </a>
                                <a href="<?php echo esc_attr($mint->tx_hash); ?>" 
                                   target="_blank" 
                                   class="button button-small">
                                    TX
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Analytics page'ini render et
     */
    public function render_analytics_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        ?>
        <div class="wrap">
            <h1>📈 Analytics Dashboard</h1>
            
            <div class="nft-saas-analytics">
                <div class="stat-card">
                    <h3>Total NFTs Minted</h3>
                    <div class="stat-value"><?php echo $this->get_total_mints(); ?></div>
                </div>
                
                <div class="stat-card">
                    <h3>Total Royalty Earned</h3>
                    <div class="stat-value"><?php echo $this->get_total_royalty(); ?></div>
                </div>
                
                <div class="stat-card">
                    <h3>Active Users</h3>
                    <div class="stat-value"><?php echo $this->get_active_users(); ?></div>
                </div>
                
                <div class="stat-card">
                    <h3>This Month</h3>
                    <div class="stat-value"><?php echo $this->get_month_mints(); ?></div>
                </div>
            </div>
            
            <div id="analytics-chart"></div>
        </div>
        <?php
    }
    
    /**
     * Quick stats render et
     */
    private function render_quick_stats() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'nft_mints';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
            $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            $today = $wpdb->get_var(
                "SELECT COUNT(*) FROM $table_name WHERE DATE(created_at) = CURDATE()"
            );
            
            echo '<p><strong>Total NFTs:</strong> ' . $total . '</p>';
            echo '<p><strong>Today\'s Mints:</strong> ' . $today . '</p>';
        }
    }
    
    private function get_total_mints() {
        global $wpdb;
        return $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->prefix . "nft_mints") ?? 0;
    }
    
    private function get_total_royalty() {
        // Platform'dan fetch et
        return '3% of every sale';
    }
    
    private function get_active_users() {
        global $wpdb;
        return $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) FROM " . $wpdb->prefix . "nft_mints"
        ) ?? 0;
    }
    
    private function get_month_mints() {
        global $wpdb;
        return $wpdb->get_var(
            "SELECT COUNT(*) FROM " . $wpdb->prefix . "nft_mints 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        ) ?? 0;
    }
}

// Initialize
new NFT_SaaS_Platform_Settings();
?>
