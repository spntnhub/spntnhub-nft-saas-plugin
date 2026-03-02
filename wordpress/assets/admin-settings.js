/* NFT SaaS – Admin Settings JS
 * Handles:
 *   1. Registration modal (auto-activate: email + wallet → API key)
 *   2. API Key validation button
 *   3. Wallet auto-sync from backend key-info endpoint
 */
(function ($) {
    'use strict';

    // ── Auto-sync wallet on page load ───────────────────────────────────────
    // If an API key is already saved, silently fetch the wallet from the backend
    // and update the (readonly) wallet field + save it via AJAX if it changed.
    $(function () {
        var existingKey = $('#api_key').val();
        var platformUrl = $('#platform_url').val().trim();
        if (existingKey && platformUrl) {
            syncWalletFromKey(platformUrl, existingKey);
        }
    });

    function syncWalletFromKey(platformUrl, apiKey) {
        var badge = $('#wallet-sync-badge').show();
        $.ajax({
            url:     platformUrl.replace(/\/$/, '') + '/api/auth/key-info',
            method:  'GET',
            timeout: 8000,
            headers: { 'X-API-Key': apiKey },
        })
        .done(function (res) {
            if (res.success && res.walletAddress) {
                var $field = $('#authorized_minter');
                $field.val(res.walletAddress);
                $('#wallet-warning').hide();

                // Persist silently if the value differs from what's saved
                $.post(nftSaasAdmin.ajaxUrl, {
                    action: 'nft_saas_sync_wallet',
                    nonce:  nftSaasAdmin.nonce,
                    wallet: res.walletAddress,
                });
            }
        })
        .always(function () { badge.hide(); });
    }

    // ── Modal open / close ──────────────────────────────────────────────────
    $(document).on('click', '#open-register-modal', function (e) {
        e.preventDefault();
        // Pre-fill siteUrl if not already entered
        if (!$('#platform_url').val()) {
            $('#platform_url').val(window.location.origin);
        }
        $('#nft-modal-overlay, #nft-register-modal').fadeIn(200);
    });

    $(document).on('click', '#close-reg-modal, #nft-modal-overlay', function () {
        $('#nft-modal-overlay, #nft-register-modal').fadeOut(200);
    });

    // ── Activate (get API key) ──────────────────────────────────────────────
    $(document).on('click', '#do-register', function () {
        const email      = $('#reg-email').val().trim();
        const wallet     = $('#reg-wallet').val().trim();
        const platformUrl = $('#platform_url').val().trim() || window.location.origin;
        const siteUrl    = platformUrl;

        if (!email) {
            showFeedback('error', 'Please enter your email address.');
            return;
        }
        if (!wallet) {
            showFeedback('error', 'Wallet address is required — this is where you receive your NFT sale earnings.');
            return;
        }
        if (!platformUrl) {
            showFeedback('error', 'Please fill in the Platform URL field first.');
            return;
        }

        const $btn = $(this).prop('disabled', true).text('Connecting…');
        clearFeedback();

        $.ajax({
            url:         platformUrl.replace(/\/$/, '') + '/api/auth/activate',
            method:      'POST',
            contentType: 'application/json',
            data:        JSON.stringify({ email, walletAddress: wallet, siteUrl }),
            timeout:     15000,
        })
        .done(function (res) {
            if (res.apiKey) {
                // Inject key + wallet into form fields
                $('#api_key').val(res.apiKey);
                // Use wallet returned by server (authoritative), fall back to modal input
                var serverWallet = (res.user && res.user.walletAddress) ? res.user.walletAddress : wallet;
                if (serverWallet) {
                    $('#authorized_minter').val(serverWallet);
                    $('#wallet-warning').hide();
                    // Persist silently
                    $.post(nftSaasAdmin.ajaxUrl, {
                        action: 'nft_saas_sync_wallet',
                        nonce:  nftSaasAdmin.nonce,
                        wallet: serverWallet,
                    });
                }
                showFeedback('success',
                    '✅ Connected! Your API Key has been generated. ' +
                    '<strong>Click "Save Settings" below to save it.</strong>'
                );
                // Highlight the Save button
                $('input[type="submit"]').first()
                    .addClass('button-hero')
                    .css('background', '#00a32a')
                    .focus();
            } else {
                showFeedback('error', 'Unexpected response from platform.');
            }
        })
        .fail(function (xhr) {
            const msg = (xhr.responseJSON && xhr.responseJSON.error)
                ? xhr.responseJSON.error
                : 'Could not connect to platform. Check the Platform URL.';
            showFeedback('error', '❌ ' + msg);
        })
        .always(function () {
            $btn.prop('disabled', false).text('✨ Get API Key');
        });
    });

    // ── Validate existing API Key ───────────────────────────────────────────
    $(document).on('click', '#validate-api-btn', function () {
        const platformUrl = $('#platform_url').val().trim();
        const apiKey      = $(this).data('api-key') || $('#api_key').val().trim();

        if (!platformUrl || !apiKey) {
            alert('Platform URL and API Key are required.');
            return;
        }

        const $btn = $(this).prop('disabled', true).text('Validating…');

        $.ajax({
            url:     platformUrl.replace(/\/$/, '') + '/health',
            method:  'GET',
            timeout: 10000,
            headers: { 'X-API-Key': apiKey },
        })
        .done(function (res) {
            if (res.status === 'ok') {
                // Save validated timestamp via WordPress AJAX
                $.post(nftSaasAdmin.ajaxUrl, {
                    action: 'nft_saas_save_validation',
                    nonce:  nftSaasAdmin.nonce,
                }, function () {
                    // Sync wallet before reload so the readonly field is current
                    syncWalletFromKey(platformUrl, apiKey);
                    setTimeout(function () { location.reload(); }, 600);
                });
            } else {
                alert('Platform is reachable but reported status: ' + res.status);
            }
        })
        .fail(function () {
            alert('❌ Could not reach platform. Check the URL and your API key.');
        })
        .always(function () {
            $btn.prop('disabled', false).text('Validate API Key');
        });
    });

    // ── Helpers ─────────────────────────────────────────────────────────────
    function showFeedback(type, html) {
        const colors = { success: '#d4edda', error: '#f8d7da' };
        const borders = { success: '#28a745', error: '#dc3545' };
        $('#reg-feedback')
            .css({ background: colors[type], border: '1px solid ' + borders[type], padding: '10px', borderRadius: '4px' })
            .html(html)
            .show();
    }

    function clearFeedback() {
        $('#reg-feedback').hide().html('');
    }

}(jQuery));
