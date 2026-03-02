<?php
/**
 * NFT SaaS – Manual Sales Panel Template
 * Allows admins to prepare any Media Library image as an NFT (IPFS upload + ready state).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$api_key      = get_option( 'nft_saas_api_key', '' );
$wallet       = get_option( 'nft_saas_authorized_minter', '' );
$platform_url = get_option( 'nft_saas_platform_url', '' );
$nonce        = wp_create_nonce( 'nft_saas_nonce' );
?>
<div class="wrap">
    <h1>🖼️ Manual Sales Panel</h1>
    <p>Select a media image to upload to IPFS and make available for sale.</p>

    <?php if ( ! $api_key || ! $wallet ) : ?>
        <div class="notice notice-error">
            <p>
                <strong>Setup required.</strong>
                Please configure your API Key and Wallet Address in
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=nft-saas-settings' ) ); ?>">NFT SaaS → Settings</a> first.
            </p>
        </div>
    <?php endif; ?>

    <div id="manual-sales-feedback" style="display:none; margin:10px 0; padding:12px; border-radius:4px;"></div>

    <!-- Search + Image Grid -->
    <div style="display:flex; gap:12px; align-items:center; margin:16px 0;">
        <input type="text" id="ms-search" placeholder="Search images…" class="regular-text">
        <button id="ms-search-btn" class="button">Search</button>
    </div>

    <div id="ms-image-grid" style="display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:16px; margin-bottom:20px;"></div>

    <div id="ms-pagination" style="margin:12px 0;"></div>

    <!-- Mint Panel (shown when an image is selected) -->
    <div id="ms-mint-panel" style="display:none; background:#fff; border:1px solid #ddd; border-radius:8px; padding:20px; max-width:480px;">
        <h2 style="margin-top:0;">Prepare NFT</h2>

        <input type="hidden" id="ms-selected-id">

        <table class="form-table" style="margin:0;">
            <tr>
                <th style="padding:8px 10px 8px 0;"><label for="ms-price">Price (POL/ETH)</label></th>
                <td><input type="number" id="ms-price" step="0.001" min="0.001" value="0.01" class="small-text"> <code id="ms-currency">POL</code></td>
            </tr>
            <tr>
                <th style="padding:8px 10px 8px 0;"><label for="ms-wallet-display">Artist Wallet</label></th>
                <td><input type="text" id="ms-wallet-display" value="<?php echo esc_attr( $wallet ); ?>" class="regular-text code" placeholder="0x…"></td>
            </tr>
        </table>

        <p style="margin:16px 0 8px;">
            <button id="ms-prepare-btn" class="button button-primary button-hero" <?php echo ( ! $api_key || ! $wallet ) ? 'disabled' : ''; ?>>
                ⬆️ Upload to IPFS &amp; Prepare
            </button>
            <button id="ms-cancel-btn" class="button" style="margin-left:8px;">Cancel</button>
        </p>

        <div id="ms-result" style="margin-top:12px; display:none; word-break:break-all;"></div>
    </div>

    <!-- Shortcode hint after preparation -->
    <div id="ms-shortcode-hint" style="display:none; background:#f0f4ff; border:1px solid #c0cff8; border-radius:6px; padding:16px; max-width:480px; margin-top:16px;">
        <strong>✅ Ready to sell!</strong> Copy this shortcode into any post or page:<br>
        <code id="ms-shortcode-code" style="display:block; margin-top:8px; padding:8px; background:#fff; border:1px solid #ddd; border-radius:4px; user-select:all;"></code>
    </div>
</div>

<script>
(function($) {
    var currentPage = 1;

    // ── Load Images ───────────────────────────────────────────────────────────
    function loadImages(page) {
        currentPage = page || 1;
        $.post(ajaxurl, {
            action: 'nft_saas_get_images',
            nonce:  '<?php echo $nonce; ?>',
            page:   currentPage,
            search: $('#ms-search').val()
        }, function(res) {
            if (!res.success) return;
            var grid = $('#ms-image-grid').empty();
            $.each(res.data.images, function(_, img) {
                var badge = img.is_minted
                    ? '<span style="background:#28a745;color:#fff;font-size:10px;padding:2px 6px;border-radius:10px;position:absolute;top:6px;right:6px;">IPFS ✓</span>'
                    : '';
                var thumb = img.thumbnail || img.url;
                grid.append(
                    '<div class="ms-thumb-wrap" data-id="' + img.id + '" data-title="' + $('<span>').text(img.title).html() + '" style="position:relative;cursor:pointer;border:2px solid #ddd;border-radius:6px;overflow:hidden;background:#f9f9f9;transition:border-color .15s;" '
                    + 'onmouseover="this.style.borderColor=\'#0073aa\'" onmouseout="this.style.borderColor=\'#ddd\'">'
                    + '<img src="' + thumb + '" style="width:100%;height:140px;object-fit:cover;display:block;">'
                    + badge
                    + '<p style="margin:0;padding:6px;font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + $('<span>').text(img.title).html() + '</p>'
                    + '</div>'
                );
            });

            // Pagination
            var pages = res.data.pages;
            var pag = $('#ms-pagination').empty();
            for (var p = 1; p <= pages; p++) {
                pag.append(
                    $('<button class="button" style="margin-right:4px;"' + (p == currentPage ? ' disabled' : '') + '>')
                        .text(p).data('page', p)
                        .on('click', function() { loadImages($(this).data('page')); })
                );
            }
        });
    }

    loadImages(1);

    $('#ms-search-btn').on('click', function() { loadImages(1); });
    $('#ms-search').on('keypress', function(e) { if (e.which === 13) loadImages(1); });

    // ── Select Image ──────────────────────────────────────────────────────────
    $(document).on('click', '.ms-thumb-wrap', function() {
        var id = $(this).data('id');
        $('#ms-selected-id').val(id);
        $('#ms-mint-panel').show();
        $('#ms-shortcode-hint').hide();
        $('#ms-result').hide();
        $('html, body').animate({ scrollTop: $('#ms-mint-panel').offset().top - 60 }, 300);
    });

    $('#ms-cancel-btn').on('click', function() {
        $('#ms-mint-panel').hide();
        $('#ms-shortcode-hint').hide();
    });

    // ── Prepare (IPFS upload) ─────────────────────────────────────────────────
    $('#ms-prepare-btn').on('click', function() {
        var id     = $('#ms-selected-id').val();
        var price  = $('#ms-price').val();
        var wallet = $('#ms-wallet-display').val().trim();

        if (!id || !price || !wallet) {
            showFeedback('error', 'Please select an image and fill in all fields.');
            return;
        }

        var $btn = $(this).prop('disabled', true).text('Uploading to IPFS…');
        $('#ms-result').hide();

        $.post(ajaxurl, {
            action:   'nft_saas_manual_mint',
            nonce:    '<?php echo $nonce; ?>',
            image_id: id,
            price:    price,
            wallet:   wallet
        }, function(res) {
            if (res.success) {
                $('#ms-result')
                    .css({ background:'#d4edda', color:'#155724', border:'1px solid #c3e6cb', padding:'10px', borderRadius:'4px' })
                    .html('<strong>✅ IPFS upload complete!</strong><br>Token URI: <a href="https://ipfs.io/ipfs/' + res.data.token_uri.replace('ipfs://','') + '" target="_blank">' + res.data.token_uri + '</a>')
                    .show();
                // Show shortcode
                var code = '[nft_buy_button id="' + res.data.image_id + '"]';
                $('#ms-shortcode-code').text(code);
                $('#ms-shortcode-hint').show();
                loadImages(currentPage);
            } else {
                $('#ms-result')
                    .css({ background:'#f8d7da', color:'#721c24', border:'1px solid #f5c6cb', padding:'10px', borderRadius:'4px' })
                    .text('❌ ' + (res.data || 'Error'))
                    .show();
            }
        }).fail(function() {
            $('#ms-result')
                .css({ background:'#f8d7da', color:'#721c24', border:'1px solid #f5c6cb', padding:'10px', borderRadius:'4px' })
                .text('❌ Network error. Check your Platform URL.')
                .show();
        }).always(function() {
            $btn.prop('disabled', false).text('⬆️ Upload to IPFS & Prepare');
        });
    });

    function showFeedback(type, msg) {
        var colors = { success: '#d4edda', error: '#f8d7da' };
        var text   = { success: '#155724', error: '#721c24' };
        $('#manual-sales-feedback')
            .css({ background: colors[type], color: text[type], border: '1px solid', padding: '10px', borderRadius: '4px' })
            .text(msg).show();
        setTimeout(function() { $('#manual-sales-feedback').fadeOut(); }, 5000);
    }
}(jQuery));
</script>
