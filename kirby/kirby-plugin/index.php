<?php
/**
 * NFT SaaS – Kirby CMS Plugin
 *
 * Adds NFT listing fields to the Panel and renders a buy button snippet.
 *
 * Installation:
 *   1. Copy the `nft-saas` folder to `site/plugins/nft-saas/`.
 *   2. Go to your Kirby Panel → System → Blueprint and configure the fields.
 *   3. In your template use: <?php snippet('nft-buy-button', ['file' => $image]) ?>
 *
 * Configuration (site/config/config.php):
 *   'nft-saas.api_url'         => 'https://nft-saas-production.up.railway.app',
 *   'nft-saas.api_key'         => 'nfts_your_api_key_here',
 *   'nft-saas.contract_address'=> '0x1AFd1b0D36Db1bb8E9Cc0f359e37A76313270837',
 *   'nft-saas.artist_wallet'   => '0xYourWalletAddress',
 */

Kirby::plugin('nft-saas/nft-saas', [

    // ── Blueprints ────────────────────────────────────────────────────────────
    'blueprints' => [
        // File blueprint — adds NFT fields to any image in the Panel
        'files/nft-image' => __DIR__ . '/blueprints/files/nft-image.yml',
    ],

    // ── Snippets ──────────────────────────────────────────────────────────────
    'snippets' => [
        'nft-buy-button' => __DIR__ . '/snippets/nft-buy-button.php',
    ],

    // ── Routes (API proxy — optional, for server-side signature caching) ──────
    'routes' => [],

    // ── Hooks ─────────────────────────────────────────────────────────────────
    'hooks' => [
        // Sync sold status: when a file is updated with nft_is_sold=true, lock it
        'file.update:after' => function ($newFile, $oldFile) {
            // Nothing server-side needed — the JS snippet handles sold state via
            // the nft_is_sold field update through the Kirby Panel.
        },
    ],
]);
