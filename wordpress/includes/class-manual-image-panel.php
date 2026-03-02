<?php
/**
 * Manual Image Panel for WordPress
 * 
 * Service for managing manual image-to-NFT minting from WordPress media library
 */

class NFT_SaaS_Manual_Image_Panel {
    
    private $api_handler;
    private $mint_log_file;

    public function __construct( $api_handler = null ) {
        $this->api_handler = $api_handler;
        
        $upload_dir = wp_upload_dir();
        // Check if basedir is set, otherwise default to a temp dir
        $base_dir = isset($upload_dir['basedir']) ? $upload_dir['basedir'] : sys_get_temp_dir();
        $this->mint_log_file = $base_dir . '/nft-saas-mints.log';
    }

    public function init() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        // Make sure to hook AJAX actions properly
        add_action('wp_ajax_nft_saas_get_images', array($this, 'ajax_get_images'));
        add_action('wp_ajax_nft_saas_manual_mint', array($this, 'ajax_manual_mint'));
    }

    public function add_admin_menu() {
        add_submenu_page(
            'nft-saas-settings',
            'Manual Sales',
            'Manual Sales',
            'manage_options',
            'nft-saas-manual-sales',
            array($this, 'render_page')
        );
    }
    
    public function render_page() {
        // Ensure the manual-sales.php file exists
        $template_path = NFT_SAAS_PLUGIN_DIR . 'admin/manual-sales.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<div class="wrap"><h1>Manual Sales Panel</h1><p>Template file missing.</p></div>';
        }
    }

    /**
     * AJAX: Get Images from Media Library
     */
    public function ajax_get_images() {
        // verify nonce in production
        // check_ajax_referer('nft_saas_manual_nonce', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error('Permission denied');
            return;
        }

        $paged = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $args = array(
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'post_status'    => 'inherit',
            'posts_per_page' => 20,
            'paged'          => $paged
        );

        if (!empty($_POST['search'])) {
            $args['s'] = sanitize_text_field($_POST['search']);
        }

        $query = new WP_Query($args);
        $images = array();
        
        if ($query->have_posts()) {
            foreach ($query->posts as $post) {
                // $meta = wp_get_attachment_metadata($post->ID);
                $is_minted = get_post_meta($post->ID, '_nft_minted', true);
                $tx_hash = get_post_meta($post->ID, '_nft_tx_hash', true);
                
                $thumb_url = wp_get_attachment_image_url($post->ID, 'thumbnail');
                $full_url = wp_get_attachment_url($post->ID);
                
                $images[] = array(
                    'id'          => $post->ID,
                    'title'       => $post->post_title,
                    'url'         => $full_url,
                    'thumbnail'   => $thumb_url,
                    'is_minted'   => !empty($is_minted),
                    'tx_hash'     => $tx_hash,
                    'upload_date' => $post->post_date
                );
            }
        }

        wp_send_json_success(array(
            'images' => $images,
            'total'  => $query->found_posts,
            'pages'  => $query->max_num_pages
        ));
    }

    /**
     * AJAX: Manual Mint
     * Uploads image to IPFS, then returns the IPFS tokenURI ready for on-chain minting.
     */
    public function ajax_manual_mint() {
        check_ajax_referer( 'nft_saas_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
            return;
        }

        $image_id = isset( $_POST['image_id'] ) ? intval( $_POST['image_id'] )               : 0;
        $price    = isset( $_POST['price'] )    ? floatval( $_POST['price'] )                : 0;
        $wallet   = isset( $_POST['wallet'] )   ? sanitize_text_field( $_POST['wallet'] )   : '';

        if ( ! $image_id || ! $price || ! $wallet ) {
            wp_send_json_error( 'Missing required fields' );
            return;
        }

        if ( get_post_meta( $image_id, '_nft_minted', true ) ) {
            wp_send_json_error( 'Image already minted' );
            return;
        }

        $this->log_mint( $image_id, $wallet, $price, 'uploading_to_ipfs' );

        // Check for an existing tokenURI (already uploaded)
        $token_uri = get_post_meta( $image_id, '_nft_token_uri', true );

        if ( ! $token_uri ) {
            $result = $this->upload_to_ipfs( $image_id, $price );

            if ( is_wp_error( $result ) ) {
                $this->log_mint( $image_id, $wallet, $price, 'ipfs_error: ' . $result->get_error_message() );
                wp_send_json_error( $result->get_error_message() );
                return;
            }

            $token_uri = $result;
            update_post_meta( $image_id, '_nft_token_uri', $token_uri );
        }

        $this->log_mint( $image_id, $wallet, $price, 'ready' );

        wp_send_json_success( array(
            'message'   => 'Ready to mint',
            'image_id'  => $image_id,
            'token_uri' => $token_uri,
        ) );
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Retrieve a JWT token from the NFT platform backend.
     * The token is cached in a transient for 4 minutes to avoid login spam.
     *
     * @return string|false JWT string or false on failure.
     */
    private function get_jwt_token() {
        $cached = get_transient( 'nft_saas_jwt_token' );
        if ( $cached ) {
            return $cached;
        }

        $api_url = rtrim( get_option( 'nft_saas_platform_url', 'https://nft-saas-production.up.railway.app' ), '/' );
        $api_key = get_option( 'nft_saas_api_key', '' );
        $wallet  = get_option( 'nft_saas_authorized_minter', '' );

        if ( empty( $api_key ) || empty( $wallet ) ) {
            return false;
        }

        $response = wp_remote_post(
            $api_url . '/api/auth/login',
            array(
                'method'  => 'POST',
                'headers' => array(
                    'Content-Type'           => 'application/json',
                    'Bypass-Tunnel-Reminder' => 'true',
                ),
                'body'    => wp_json_encode( array(
                    'walletAddress' => $wallet,
                    'apiKey'        => $api_key,
                ) ),
                'timeout' => 10,
            )
        );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $body['token'] ) ) {
            return false;
        }

        set_transient( 'nft_saas_jwt_token', $body['token'], 4 * MINUTE_IN_SECONDS );
        return $body['token'];
    }

    /**
     * Upload a WordPress attachment to IPFS via the backend /api/ipfs/upload-nft endpoint.
     *
     * @param int   $image_id WordPress attachment ID.
     * @param float $price    Sale price (stored as NFT attribute metadata).
     * @return string|WP_Error IPFS tokenURI (ipfs://...) or WP_Error on failure.
     */
    private function upload_to_ipfs( $image_id, $price ) {
        $file_path = get_attached_file( $image_id );
        if ( ! $file_path || ! file_exists( $file_path ) ) {
            return new WP_Error( 'file_not_found', 'Attachment file not found on disk.' );
        }

        $jwt = $this->get_jwt_token();
        if ( ! $jwt ) {
            return new WP_Error( 'auth_failed', 'Could not authenticate with NFT platform. Please verify your API Key and Wallet settings.' );
        }

        $api_url   = rtrim( get_option( 'nft_saas_platform_url', 'https://nft-saas-production.up.railway.app' ), '/' );
        $mime_type = mime_content_type( $file_path );
        $filename  = basename( $file_path );
        $post      = get_post( $image_id );
        $nft_name  = $post ? $post->post_title : pathinfo( $filename, PATHINFO_FILENAME );
        $desc      = get_post_meta( $image_id, '_nft_description', true ) ?: 'NFT minted via WordPress';

        // Build raw multipart/form-data body
        $boundary = 'NFTSaaS' . wp_generate_uuid4();
        $body     = '';

        // Image file part
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"image\"; filename=\"{$filename}\"\r\n";
        $body .= "Content-Type: {$mime_type}\r\n\r\n";
        $body .= file_get_contents( $file_path ) . "\r\n";

        // Text fields
        foreach ( array( 'nftName' => $nft_name, 'description' => $desc, 'price' => (string) $price ) as $name => $value ) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n";
            $body .= $value . "\r\n";
        }
        $body .= "--{$boundary}--\r\n";

        $response = wp_remote_post(
            $api_url . '/api/ipfs/upload-nft',
            array(
                'method'  => 'POST',
                'headers' => array(
                    'Content-Type'           => "multipart/form-data; boundary={$boundary}",
                    'Authorization'          => "Bearer {$jwt}",
                    'Bypass-Tunnel-Reminder' => 'true',
                ),
                'body'    => $body,
                'timeout' => 60,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $http_code = wp_remote_retrieve_response_code( $response );
        $data      = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $http_code !== 200 || empty( $data['success'] ) ) {
            $err_msg = isset( $data['error'] ) ? $data['error'] : 'IPFS upload failed (HTTP ' . $http_code . ')';
            return new WP_Error( 'ipfs_upload_failed', $err_msg );
        }

        return $data['data']['tokenURI'];
    }

    private function log_mint($image_id, $wallet, $price, $status) {
        $log_entry = sprintf(
            "[%s] ID: %d | Wallet: %s | Price: %s | Status: %s\n",
            date('Y-m-d H:i:s'),
            $image_id,
            $wallet,
            $price,
            $status
        );
        // Ensure directory exists or skip log
        if (file_exists(dirname($this->mint_log_file))) {
            @file_put_contents($this->mint_log_file, $log_entry, FILE_APPEND);
        }
    }
}
