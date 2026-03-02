<?php
/**
 * Integates NFT Minting into standard WordPress Media Library
 */
class NFT_SaaS_Media_Integration {

    private $api_handler;

    public function __construct( $api_handler = null ) {
        $this->api_handler = $api_handler;
    }

    public function init() {
        // Add fields to media uploader
        add_filter( 'attachment_fields_to_edit', array( $this, 'add_nft_attachment_fields' ), 10, 2 );

        // Save fields
        add_filter( 'attachment_fields_to_save', array( $this, 'save_nft_attachment_fields' ), 10, 2 );

        // Frontend Content Filter to show "Buy NFT" button
        add_filter( 'the_content', array( $this, 'add_buy_button_to_content' ) );

        // Register Shortcode
        add_shortcode( 'nft_buy_button', array( $this, 'render_buy_button_shortcode' ) );

        // Register Gutenberg Block
        add_action( 'init', array( $this, 'register_nft_block' ) );

        // AJAX: mark NFT as sold after on-chain purchase (logged-in and guests)
        add_action( 'wp_ajax_nft_saas_mark_as_sold',        array( $this, 'ajax_mark_as_sold' ) );
        add_action( 'wp_ajax_nopriv_nft_saas_mark_as_sold', array( $this, 'ajax_mark_as_sold' ) );
    }

    // -------------------------------------------------------------------------
    // Attachment Fields – Add & Save
    // -------------------------------------------------------------------------

    /**
     * Add NFT-specific fields to the Media Library attachment edit form.
     *
     * @param array   $form_fields Existing fields.
     * @param WP_Post $post        The attachment post.
     * @return array
     */
    public function add_nft_attachment_fields( $form_fields, $post ) {
        // Is For Sale
        $is_for_sale = get_post_meta( $post->ID, '_nft_is_for_sale', true );
        $form_fields['nft_is_for_sale'] = array(
            'label' => __( 'List as NFT', 'nft-saas' ),
            'input' => 'html',
            'html'  => '<input type="checkbox" name="attachments[' . $post->ID . '][nft_is_for_sale]" value="1"' . checked( $is_for_sale, '1', false ) . '> Enable Buy Button',
            'helps' => 'Show a "Buy Now" button for this image.',
        );

        // Price
        $price = get_post_meta( $post->ID, '_nft_price', true ) ?: '0.01';
        $form_fields['nft_price'] = array(
            'label' => __( 'Price (POL/ETH)', 'nft-saas' ),
            'input' => 'text',
            'value' => esc_attr( $price ),
            'helps' => 'Sale price in native chain currency (e.g. 0.01).',
        );

        // Blockchain / Chain
        $chain         = get_post_meta( $post->ID, '_nft_chain', true ) ?: 'polygon';
        $chain_options = array(
            'polygon'  => 'Polygon (POL) — Mainnet',
            'ethereum' => 'Ethereum (ETH) — Mainnet',
            'base'     => 'Base (ETH) — Mainnet',
            'sepolia'  => 'Sepolia (ETH) — Testnet',
        );
        $chain_html = '<select name="attachments[' . $post->ID . '][nft_chain]">';
        foreach ( $chain_options as $value => $label ) {
            $chain_html .= '<option value="' . esc_attr( $value ) . '"' . selected( $chain, $value, false ) . '>' . esc_html( $label ) . '</option>';
        }
        $chain_html .= '</select>';
        $form_fields['nft_chain'] = array(
            'label' => __( 'Blockchain', 'nft-saas' ),
            'input' => 'html',
            'html'  => $chain_html,
            'helps' => 'Chain where the NFT will be minted.',
        );

        // Description
        $description = get_post_meta( $post->ID, '_nft_description', true );
        $form_fields['nft_description'] = array(
            'label' => __( 'NFT Description', 'nft-saas' ),
            'input' => 'textarea',
            'value' => esc_textarea( $description ),
            'helps' => 'Stored in IPFS metadata.',
        );

        // Read-only: IPFS tokenURI (if already uploaded)
        $token_uri = get_post_meta( $post->ID, '_nft_token_uri', true );
        if ( $token_uri ) {
            $form_fields['nft_token_uri'] = array(
                'label' => __( 'IPFS Token URI', 'nft-saas' ),
                'input' => 'html',
                'html'  => '<input type="text" readonly value="' . esc_attr( $token_uri ) . '" style="width:100%; font-family:monospace; font-size:0.8em;">',
                'helps' => 'Auto-generated. Used as the on-chain tokenURI.',
            );
        }

        return $form_fields;
    }

    /**
     * Save NFT fields from Media Library attachment form.
     *
     * @param array $post       Post data.
     * @param array $attachment Submitted attachment form data.
     * @return array
     */
    public function save_nft_attachment_fields( $post, $attachment ) {
        $id = $post['ID'];

        // Is for sale
        $is_for_sale = ! empty( $attachment['nft_is_for_sale'] ) ? '1' : '';
        update_post_meta( $id, '_nft_is_for_sale', $is_for_sale );

        // Price
        if ( isset( $attachment['nft_price'] ) ) {
            $price = floatval( $attachment['nft_price'] );
            update_post_meta( $id, '_nft_price', max( 0, $price ) );
        }

        // Chain
        $allowed_chains = array( 'polygon', 'ethereum', 'base', 'sepolia' );
        if ( isset( $attachment['nft_chain'] ) && in_array( $attachment['nft_chain'], $allowed_chains, true ) ) {
            update_post_meta( $id, '_nft_chain', sanitize_text_field( $attachment['nft_chain'] ) );
        }

        // Description
        if ( isset( $attachment['nft_description'] ) ) {
            update_post_meta( $id, '_nft_description', sanitize_textarea_field( $attachment['nft_description'] ) );
        }

        return $post;
    }

    // -------------------------------------------------------------------------
    // AJAX: Mark as Sold
    // -------------------------------------------------------------------------

    /**
     * AJAX handler called from the frontend buy button JS after a successful
     * on-chain purchase receipt.  Marks the attachment as sold in WordPress.
     */
    public function ajax_mark_as_sold() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';
        if ( ! wp_verify_nonce( $nonce, 'nft_saas_buy_nonce' ) ) {
            wp_send_json_error( 'Invalid nonce' );
            return;
        }

        $attachment_id = isset( $_POST['id'] )      ? intval( $_POST['id'] )                    : 0;
        $tx_hash       = isset( $_POST['tx_hash'] ) ? sanitize_text_field( $_POST['tx_hash'] ) : '';

        if ( ! $attachment_id ) {
            wp_send_json_error( 'Missing attachment ID' );
            return;
        }

        update_post_meta( $attachment_id, '_nft_is_sold', '1' );
        update_post_meta( $attachment_id, '_nft_tx_hash', $tx_hash );
        update_post_meta( $attachment_id, '_nft_sold_at', current_time( 'mysql' ) );

        wp_send_json_success( array( 'message' => 'NFT marked as sold' ) );
    }

    /**
     * Register Gutenberg Block
     */
    public function register_nft_block() {
        // Register the script
        wp_register_script(
            'nft-saas-block',
            plugins_url( '../assets/nft-block.js', __FILE__ ),
            array( 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n' ),
            '1.0'
        );

        // Register the block type
        register_block_type( 'nft-saas/buy-button', array(
            'editor_script' => 'nft-saas-block',
            'render_callback' => array( $this, 'render_nft_block_callback' ),
            'attributes' => array(
                'mediaID' => array( 'type' => 'number', 'default' => 0 ),
                'price' => array( 'type' => 'string', 'default' => '0.01' )
            )
        ) );
    }

    /**
     * Render callback for Gutenberg Block
     */
    public function render_nft_block_callback( $attributes ) {
        if ( empty( $attributes['mediaID'] ) ) {
            return '<p style="color:red; border:1px dashed red; padding:5px;">Please select an image for the NFT Block.</p>';
        }

        // Ideally, we force the meta price to match what's in the block if we want override?
        // Or we just use the logic from generate_buy_button_html.
        // For now, let's trust the ID.
        
        return $this->generate_buy_button_html( $attributes['mediaID'] );
    }

    /**
     * Shortcode Handler: [nft_buy_button id="123"]
     */
    public function render_buy_button_shortcode( $atts ) {
        $a = shortcode_atts( array(
            'id' => 0,
        ), $atts );

        if ( empty( $a['id'] ) ) return '';
        
        return $this->generate_buy_button_html( $a['id'] );
    }

    /**
     * Frontend: Add Buy Button after NFT images embedded in post content.
     *
     * Scans for <img class="wp-image-{ID}"> tags that have _nft_is_for_sale set,
     * then injects the buy button HTML right after the closing </figure> (or <img>).
     * Works on all public pages/posts — not just single/attachment.
     */
    public function add_buy_button_to_content( $content ) {
        if ( is_admin() || ! is_main_query() ) {
            return $content;
        }

        // Find every occurrence of wp-image-{ID} in the content
        if ( ! preg_match_all( '/wp-image-(\d+)/', $content, $matches ) ) {
            return $content;
        }

        $seen = array();
        foreach ( array_unique( $matches[1] ) as $attachment_id ) {
            $attachment_id = intval( $attachment_id );
            if ( isset( $seen[ $attachment_id ] ) ) continue;
            $seen[ $attachment_id ] = true;

            $is_for_sale = get_post_meta( $attachment_id, '_nft_is_for_sale', true );
            $is_sold     = get_post_meta( $attachment_id, '_nft_is_sold',     true );
            if ( ! $is_for_sale ) continue;

            $buy_html = $this->generate_buy_button_html( $attachment_id );

            // Try to insert after the wrapping <figure> block that contains this image.
            // Gutenberg wraps images in <figure class="wp-block-image ...">...</figure>
            $pattern = '/(<figure[^>]*class="[^"]*wp-block-image[^"]*"[^>]*>(?:(?!<\/figure>).)*wp-image-' . $attachment_id . '(?:(?!<\/figure>).)*<\/figure>)/si';
            if ( preg_match( $pattern, $content ) ) {
                $content = preg_replace( $pattern, '$1' . $buy_html, $content, 1 );
            } else {
                // Classic editor: insert after the <img> tag itself
                $img_pattern = '/(<img[^>]*class="[^"]*wp-image-' . $attachment_id . '[^"]*"[^>]*\/?>)/i';
                $content = preg_replace( $img_pattern, '$1' . $buy_html, $content, 1 );
            }
        }

        return $content;
    }

    /**
     * Generates the HTML for the Buy Button
     */
    private function generate_buy_button_html( $target_id ) {
        $is_sold = get_post_meta( $target_id, '_nft_is_sold', true );

        if ( $is_sold ) {
            $tx_hash = get_post_meta( $target_id, '_nft_tx_hash', true );
            $explorer = $tx_hash
                ? ' <a href="https://polygonscan.com/tx/' . esc_attr( $tx_hash ) . '" target="_blank" style="font-size:0.75em; color:#155724;">View on-chain ↗</a>'
                : '';
            return '<div class="nft-sold-badge" style="background:#d4edda; color:#155724; padding:10px; text-align:center; border-radius:4px; margin:20px 0;">✅ This NFT has been collected.' . $explorer . '</div>';
        }

        $price = get_post_meta( $target_id, '_nft_price', true );
        if ( ! $price ) $price = '0.01';

        // Prefer IPFS tokenURI, fall back to attachment URL
        $token_uri = get_post_meta( $target_id, '_nft_token_uri', true );
        if ( ! $token_uri ) {
            $token_uri = wp_get_attachment_url( $target_id );
        }

        // Get Creator Wallet
        $author_id      = get_post_field( 'post_author', $target_id );
        $creator_wallet = get_user_meta( $author_id, 'nft_wallet_address', true );
        if ( ! $creator_wallet ) {
            $creator_wallet = get_option( 'nft_saas_authorized_minter', '' );
            if ( ! $creator_wallet ) {
                return '<div class="nft-error" style="color:red; font-size:0.8em;">Creator wallet missing. Cannot buy.</div>';
            }
        }

        // Chain configuration
        $chain = get_post_meta( $target_id, '_nft_chain', true ) ?: 'polygon';
        $chain_configs = array(
            'polygon'  => array(
                'chainId'         => '0x89',
                'chainName'       => 'Polygon Mainnet',
                'rpcUrl'          => 'https://polygon-rpc.com',
                'currency'        => 'POL',
                'explorerUrl'     => 'https://polygonscan.com',
                'contractAddress' => '0x1AFd1b0D36Db1bb8E9Cc0f359e37A76313270837',
            ),
            'ethereum' => array(
                'chainId'         => '0x1',
                'chainName'       => 'Ethereum Mainnet',
                'rpcUrl'          => 'https://cloudflare-eth.com',
                'currency'        => 'ETH',
                'explorerUrl'     => 'https://etherscan.io',
                'contractAddress' => get_option( 'nft_saas_contract_ethereum', '' ),
            ),
            'base'     => array(
                'chainId'         => '0x2105',
                'chainName'       => 'Base Mainnet',
                'rpcUrl'          => 'https://mainnet.base.org',
                'currency'        => 'ETH',
                'explorerUrl'     => 'https://basescan.org',
                'contractAddress' => get_option( 'nft_saas_contract_base', '' ),
            ),
            'sepolia'  => array(
                'chainId'         => '0xaa36a7',
                'chainName'       => 'Sepolia Testnet',
                'rpcUrl'          => 'https://rpc.sepolia.org',
                'currency'        => 'ETH',
                'explorerUrl'     => 'https://sepolia.etherscan.io',
                'contractAddress' => get_option( 'nft_saas_contract_sepolia', '' ),
            ),
        );
        $cfg = isset( $chain_configs[ $chain ] ) ? $chain_configs[ $chain ] : $chain_configs['polygon'];

        if ( empty( $cfg['contractAddress'] ) ) {
            return '<div class="nft-error" style="color:orange; font-size:0.8em;">Contract not configured for ' . esc_html( $cfg['chainName'] ) . '.</div>';
        }

        // Enqueue Web3
        wp_enqueue_script( 'nft-web3-buy', 'https://cdn.jsdelivr.net/npm/web3@1.10.0/dist/web3.min.js', array(), '1.0', true );

        // API URL
        $api_url = rtrim( get_option( 'nft_saas_platform_url', 'https://nft-saas-production.up.railway.app' ), '/' );

        // Buy data for JS (includes AJAX details for sold state)
        $buy_data = htmlspecialchars( json_encode( array(
            'id'              => $target_id,
            'price'           => $price,
            'creator'         => $creator_wallet,
            'uri'             => $token_uri,
            'contractAddress' => $cfg['contractAddress'],
            'apiUrl'          => $api_url,
            'apiKey'          => get_option( 'nft_saas_api_key', '' ),
            'chain'           => $cfg,
            'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
            'nonce'           => wp_create_nonce( 'nft_saas_buy_nonce' ),
        ) ), ENT_QUOTES, 'UTF-8' );

        $currency = esc_html( $cfg['currency'] );

        $button = '
        <div id="nft-buy-container-' . $target_id . '" class="nft-buy-container" style="margin:20px 0; padding:20px; border:1px solid #eee; border-radius:8px; text-align:center; background:#f9f9f9;">
            <h3 style="margin-top:0;">💎 Collect this Digital Art</h3>
            <p style="font-size:1.2rem; font-weight:bold;">Price: ' . esc_html( $price ) . ' ' . $currency . '</p>
            <p style="font-size:0.75em; color:#888; margin:0 0 6px;" class="nft-chain-badge">
                Network: ' . esc_html( $cfg['chainName'] ) . '
                <span class="nft-network-status" style="margin-left:6px;"></span>
            </p>

            <button class="button button-primary nft-buy-btn" onclick=\'buyNft(' . $buy_data . ')\' style="font-size:1.1rem; padding:10px 30px; height:auto; cursor:pointer;">
                Buy Now (Mint)
            </button>

            <p style="font-size:0.8em; color:#666; margin-top:10px;">
                Instant delivery to your wallet. You pay gas fees.
            </p>
            <div class="nft-status-msg" style="margin-top:10px; font-weight:bold; min-height:20px;"></div>
            <div class="nft-tx-link" style="margin-top:10px;"></div>
        </div>

        <script>
        (function() {
            // ─── Signature prefetch cache (sessionStorage) ─────────────────────────────
            // The signature only depends on artistAddress + tokenURI + price — NOT the
            // buyer. We prefetch it as soon as the button is visible so it is ready the
            // moment the user clicks.

            var _NFT_SIG_CACHE = {};

            function _sigCacheKey(data) {
                return "nft_sig:" + data.creator + "|" + data.uri + "|" + data.price;
            }

            function _prefetchSignature(data) {
                var key = _sigCacheKey(data);
                if (_NFT_SIG_CACHE[key]) return; // already fetched
                try {
                    var stored = sessionStorage.getItem(key);
                    if (stored) { _NFT_SIG_CACHE[key] = stored; return; }
                } catch(e) {}

                var endpoint = data.apiUrl;
                if (!endpoint.startsWith("http")) endpoint = "https://" + endpoint;

                var web3tmp = typeof Web3 !== "undefined" ? new Web3() : null;
                var priceWei = web3tmp ? web3tmp.utils.toWei(data.price.toString(), "ether") : "0";

                fetch(endpoint + "/api/mint/signature", {
                    method:  "POST",
                    headers: { "Content-Type": "application/json", "Bypass-Tunnel-Reminder": "true", "X-API-Key": data.apiKey },
                    body:    JSON.stringify({ artistAddress: data.creator, tokenURI: data.uri, priceWei: priceWei })
                })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (d.success && d.data && d.data.signature) {
                        _NFT_SIG_CACHE[key] = d.data.signature;
                        try { sessionStorage.setItem(key, d.data.signature); } catch(e) {}
                    }
                })
                .catch(function() {}); // silent — just a prefetch
            }

            // ─── Network pre-check ─────────────────────────────────────────────────────
            function _checkNetwork(targetChainId, statusEl) {
                if (!window.ethereum) return;
                window.ethereum.request({ method: "eth_chainId" })
                    .then(function(currentChainId) {
                        if (currentChainId.toLowerCase() !== targetChainId.toLowerCase()) {
                            statusEl.innerHTML = " ⚠️ <span style=\'color:#b45309;font-size:0.85em;\'>Wrong network — will switch on buy</span>";
                        } else {
                            statusEl.innerHTML = " <span style=\'color:#16a34a;font-size:0.85em;\'>✓ Correct network</span>";
                        }
                    })
                    .catch(function() {});
            }

            // ─── Run prefetch + network check when button enters viewport ──────────────
            var _containers = document.querySelectorAll ? document.querySelectorAll(".nft-buy-container") : [];
            if (_containers.length && "IntersectionObserver" in window) {
                var _observer = new IntersectionObserver(function(entries) {
                    entries.forEach(function(entry) {
                        if (!entry.isIntersecting) return;
                        _observer.unobserve(entry.target);
                        // Data is encoded in the onclick attribute
                        var btn = entry.target.querySelector(".nft-buy-btn");
                        if (!btn) return;
                        try {
                            var match = btn.getAttribute("onclick").match(/buyNft\(({.*})\)/);
                            if (!match) return;
                            var data = JSON.parse(match[1].replace(/&amp;/g,"&").replace(/&#039;/g,"\'").replace(/&quot;/g,"\\""));
                            _prefetchSignature(data);
                            var statusEl = entry.target.querySelector(".nft-network-status");
                            if (statusEl) _checkNetwork(data.chain.chainId, statusEl);
                        } catch(e) {}
                    });
                }, { threshold: 0.1 });
                for (var i = 0; i < _containers.length; i++) _observer.observe(_containers[i]);
            }

            // ─── Main buy function ─────────────────────────────────────────────────────
            if (typeof window.buyNft === "undefined") {
                window.buyNft = async function(data) {
                    const container = document.getElementById("nft-buy-container-" + data.id);
                    const btn       = container.querySelector(".nft-buy-btn");
                    const msg       = container.querySelector(".nft-status-msg");
                    const linkDiv   = container.querySelector(".nft-tx-link");

                    if (typeof window.ethereum === "undefined") {
                        alert("Please install MetaMask to buy NFTs!");
                        return;
                    }

                    try {
                        btn.disabled    = true;
                        btn.innerText   = "Connecting...";
                        msg.innerText   = "Please confirm in MetaMask...";
                        msg.style.color = "#666";
                        linkDiv.innerHTML = "";

                        let endpoint = data.apiUrl;
                        if (!endpoint.startsWith("http")) endpoint = "https://" + endpoint;

                        const web3     = new Web3(window.ethereum);
                        const priceWei = web3.utils.toWei(data.price.toString(), "ether");

                        // ── Step 1 & 2 run in PARALLEL ─────────────────────────────────
                        // Signature does not need the buyer address → fetch now, do not wait
                        const sigCacheKey = _sigCacheKey(data);
                        const cachedSig   = _NFT_SIG_CACHE[sigCacheKey] || (() => {
                            try { return sessionStorage.getItem(sigCacheKey); } catch(e) { return null; }
                        })();

                        const sigPromise = cachedSig
                            ? Promise.resolve(cachedSig)
                            : fetch(endpoint + "/api/mint/signature", {
                                method:  "POST",
                                headers: { "Content-Type": "application/json", "Bypass-Tunnel-Reminder": "true", "X-API-Key": data.apiKey },
                                body:    JSON.stringify({ artistAddress: data.creator, tokenURI: data.uri, priceWei: priceWei })
                              }).then(r => r.json()).then(d => {
                                  if (!d.success) throw new Error(d.error || "Signature failed");
                                  const sig = d.data.signature;
                                  _NFT_SIG_CACHE[sigCacheKey] = sig;
                                  try { sessionStorage.setItem(sigCacheKey, sig); } catch(e) {}
                                  return sig;
                              });

                        const walletPromise = window.ethereum.request({ method: "eth_requestAccounts" });

                        // Both run simultaneously; we await together
                        const [accounts, signature] = await Promise.all([walletPromise, sigPromise]);
                        const buyer = accounts[0];

                        // ── Step 3: network switch (only if needed) ─────────────────────
                        const currentChain = await window.ethereum.request({ method: "eth_chainId" });
                        if (currentChain.toLowerCase() !== data.chain.chainId.toLowerCase()) {
                            msg.innerText = "Switching network...";
                            try {
                                await window.ethereum.request({
                                    method: "wallet_switchEthereumChain",
                                    params: [{ chainId: data.chain.chainId }]
                                });
                            } catch (switchErr) {
                                if (switchErr.code === 4902) {
                                    await window.ethereum.request({
                                        method: "wallet_addEthereumChain",
                                        params: [{ chainId: data.chain.chainId, chainName: data.chain.chainName,
                                            rpcUrls: [data.chain.rpcUrl],
                                            nativeCurrency: { name: data.chain.currency, symbol: data.chain.currency, decimals: 18 },
                                            blockExplorerUrls: [data.chain.explorerUrl] }]
                                    });
                                } else { throw switchErr; }
                            }
                        }

                        // ── Step 4: contract call ───────────────────────────────────────
                        btn.innerText = "Confirm payment...";
                        msg.innerText = "Please confirm payment in MetaMask...";

                        const abi      = [{ "inputs":[{"internalType":"address","name":"artist","type":"address"},{"internalType":"string","name":"tokenURI","type":"string"},{"internalType":"uint256","name":"price","type":"uint256"},{"internalType":"bytes","name":"signature","type":"bytes"}],"name":"buyAndMint","outputs":[],"stateMutability":"payable","type":"function" }];
                        const contract = new web3.eth.Contract(abi, data.contractAddress);

                        await contract.methods.buyAndMint(data.creator, data.uri, priceWei, signature)
                            .send({ from: buyer, value: priceWei })
                            .on("transactionHash", function(hash) {
                                msg.innerText = "Sent! Waiting for confirmation...";
                                linkDiv.innerHTML = "<a href=\'" + data.chain.explorerUrl + "/tx/" + hash + "\' target=\'_blank\'>View on " + data.chain.chainName + " ↗</a>";
                            })
                            .on("receipt", async function(receipt) {
                                btn.innerText   = "✅ Purchased!";
                                msg.innerText   = "Successfully minted!";
                                msg.style.color = "green";
                                // Clear cached sig (NFT is sold, no point keeping it)
                                delete _NFT_SIG_CACHE[sigCacheKey];
                                try { sessionStorage.removeItem(sigCacheKey); } catch(e) {}
                                // Notify WordPress
                                try {
                                    const fd = new FormData();
                                    fd.append("action",  "nft_saas_mark_as_sold");
                                    fd.append("nonce",   data.nonce);
                                    fd.append("id",      data.id);
                                    fd.append("tx_hash", receipt.transactionHash || "");
                                    await fetch(data.ajaxUrl, { method: "POST", body: fd });
                                } catch(e) {}
                                setTimeout(function() {
                                    container.innerHTML = "<div style=\'background:#d4edda;color:#155724;padding:10px;text-align:center;border-radius:4px;\'>✅ This NFT has been collected.</div>";
                                }, 2000);
                            });

                    } catch (err) {
                        console.error(err);
                        msg.innerText   = "Error: " + (err.message || err);
                        msg.style.color = "red";
                        btn.disabled    = false;
                        btn.innerText   = "Try Again";
                    }
                };
            }
        })();
        </script>
        ';

        return $button;
    }
}
