<?php
/**
 * NFT SaaS – Kirby Buy Button Snippet
 *
 * Usage in template:
 *   <?php snippet('nft-buy-button', ['file' => $image]) ?>
 *
 * The $file must use the 'files/nft-image' blueprint.
 */

if ( ! isset( $file ) || $file->nft_is_for_sale()->isFalse() ) {
    return;
}

// ── Sold state ────────────────────────────────────────────────────────────────
if ( $file->nft_is_sold()->isTrue() ) {
    $txHash   = $file->nft_tx_hash()->value();
    $explorer = $txHash
        ? ' <a href="https://polygonscan.com/tx/' . htmlspecialchars( $txHash ) . '" target="_blank" style="font-size:0.75em;color:#155724;">View on-chain ↗</a>'
        : '';
    echo '<div class="nft-sold-badge" style="background:#d4edda;color:#155724;padding:10px;text-align:center;border-radius:4px;margin:20px 0;">✅ This NFT has been collected.' . $explorer . '</div>';
    return;
}

// ── Config ────────────────────────────────────────────────────────────────────
$apiUrl        = rtrim( option( 'nft-saas.api_url',       'https://nft-saas-production.up.railway.app' ), '/' );
$artistAddress = option( 'nft-saas.artist_wallet',        '' );
$price         = $file->nft_price()->toFloat() ?: 0.01;
$chain         = $file->nft_chain()->value()   ?: 'polygon';
$tokenURI      = $file->nft_token_uri()->isNotEmpty()
    ? $file->nft_token_uri()->value()
    : $file->url();
$uid           = 'nft-' . substr( md5( $file->id() ), 0, 10 );

if ( ! $artistAddress ) {
    echo '<div style="color:orange;font-size:0.85em;border:1px solid orange;padding:6px 10px;border-radius:4px;">⚠️ Artist wallet not configured. Add <code>nft-saas.artist_wallet</code> to your config.php.</div>';
    return;
}

// ── Chain lookup ──────────────────────────────────────────────────────────────
$chains = [
    'polygon'  => [ 'chainId' => '0x89',    'chainName' => 'Polygon Mainnet', 'rpcUrl' => 'https://polygon-rpc.com',    'currency' => 'POL', 'explorerUrl' => 'https://polygonscan.com',     'contractAddress' => option( 'nft-saas.contract_address',          '0x1AFd1b0D36Db1bb8E9Cc0f359e37A76313270837' ) ],
    'ethereum' => [ 'chainId' => '0x1',     'chainName' => 'Ethereum Mainnet','rpcUrl' => 'https://cloudflare-eth.com','currency' => 'ETH', 'explorerUrl' => 'https://etherscan.io',         'contractAddress' => option( 'nft-saas.contract_address_ethereum', '' ) ],
    'base'     => [ 'chainId' => '0x2105',  'chainName' => 'Base Mainnet',    'rpcUrl' => 'https://mainnet.base.org',  'currency' => 'ETH', 'explorerUrl' => 'https://basescan.org',         'contractAddress' => option( 'nft-saas.contract_address_base',     '' ) ],
    'sepolia'  => [ 'chainId' => '0xaa36a7','chainName' => 'Sepolia Testnet', 'rpcUrl' => 'https://rpc.sepolia.org',   'currency' => 'ETH', 'explorerUrl' => 'https://sepolia.etherscan.io', 'contractAddress' => option( 'nft-saas.contract_address_sepolia',  '' ) ],
];
$cfg      = $chains[ $chain ] ?? $chains['polygon'];
$currency = htmlspecialchars( $cfg['currency'] );

if ( empty( $cfg['contractAddress'] ) ) {
    echo '<div style="color:orange;font-size:0.85em;">Contract not configured for ' . htmlspecialchars( $cfg['chainName'] ) . '.</div>';
    return;
}

// ── JS data bundle ────────────────────────────────────────────────────────────
$jsData = htmlspecialchars( json_encode([
    'uid'             => $uid,
    'price'           => (string) $price,
    'creator'         => $artistAddress,
    'uri'             => $tokenURI,
    'contractAddress' => $cfg['contractAddress'],
    'apiUrl'          => $apiUrl,
    'apiKey'          => option( 'nft-saas.api_key', '' ),
    'chain'           => $cfg,
    'kirbyToken'      => csrf(),
    'fileApiUrl'      => $file->panel()->url(),
], JSON_UNESCAPED_SLASHES ), ENT_QUOTES, 'UTF-8' );
?>
<div id="nft-container-<?= $uid ?>" class="nft-buy-widget" style="border:1px solid #ddd;padding:1.2rem;border-radius:8px;max-width:320px;margin:1rem 0;font-family:sans-serif;text-align:center;">
    <h3 style="margin:0 0 8px;font-size:1rem;">💎 Collect this Digital Art</h3>
    <p style="font-size:1.2rem;font-weight:700;margin:0 0 4px;"><?= $price ?> <?= $currency ?></p>
    <p style="font-size:0.75em;color:#888;margin:0 0 12px;">
        Network: <?= htmlspecialchars( $cfg['chainName'] ) ?>
        <span id="nft-net-<?= $uid ?>"></span>
    </p>
    <button id="nft-btn-<?= $uid ?>" class="nft-buy-btn"
        data-nft="<?= $jsData ?>"
        style="background:#000;color:#fff;border:none;padding:.6rem 1.4rem;border-radius:4px;cursor:pointer;font-size:1rem;width:100%;">
        Buy Now
    </button>
    <p style="font-size:0.78em;color:#888;margin:10px 0 0;">Gas fees paid by buyer.</p>
    <div id="nft-msg-<?= $uid ?>" style="margin-top:10px;font-weight:600;min-height:18px;font-size:0.9em;"></div>
    <div id="nft-tx-<?= $uid ?>"  style="margin-top:6px;font-size:0.8em;"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/web3@1.10.0/dist/web3.min.js"></script>
<script>
(function () {
    // ── Signature prefetch cache ───────────────────────────────────────────────
    window._NFT_SIG = window._NFT_SIG || {};

    function sigKey(d) { return 'sig:' + d.creator + '|' + d.uri + '|' + d.price; }

    function prefetch(d) {
        var k = sigKey(d);
        if (window._NFT_SIG[k]) return;
        try { var s = sessionStorage.getItem(k); if (s) { window._NFT_SIG[k] = s; return; } } catch(e) {}
        if (typeof Web3 === 'undefined') return;
        var priceWei = new Web3().utils.toWei(d.price.toString(), 'ether');
        fetch(d.apiUrl + '/api/mint/signature', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Bypass-Tunnel-Reminder': 'true', 'X-API-Key': d.apiKey },
            body: JSON.stringify({ artistAddress: d.creator, tokenURI: d.uri, priceWei: priceWei })
        }).then(function(r){ return r.json(); }).then(function(res){
            if (res.success && res.data && res.data.signature) {
                window._NFT_SIG[k] = res.data.signature;
                try { sessionStorage.setItem(k, res.data.signature); } catch(e) {}
            }
        }).catch(function(){});
    }

    function checkNet(d) {
        if (!window.ethereum) return;
        window.ethereum.request({ method: 'eth_chainId' }).then(function(c) {
            var el = document.getElementById('nft-net-' + d.uid);
            if (!el) return;
            el.innerHTML = c.toLowerCase() === d.chain.chainId.toLowerCase()
                ? ' <span style="color:#16a34a;">✓</span>'
                : ' <span style="color:#b45309;">⚠ wrong network</span>';
        }).catch(function(){});
    }

    function initObserver() {
        if (!('IntersectionObserver' in window)) return;
        var obs = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (!entry.isIntersecting) return;
                obs.unobserve(entry.target);
                var btn = entry.target.querySelector('.nft-buy-btn');
                if (!btn) return;
                try { var d = JSON.parse(btn.dataset.nft); prefetch(d); checkNet(d); } catch(e) {}
            });
        }, { threshold: 0.1 });
        document.querySelectorAll('.nft-buy-widget').forEach(function(el){ obs.observe(el); });
    }

    function wireButtons() {
        document.querySelectorAll('.nft-buy-btn').forEach(function(btn) {
            if (btn.dataset.wired) return;
            btn.dataset.wired = '1';
            btn.addEventListener('click', function() {
                try { nftSaasBuy(JSON.parse(btn.dataset.nft)); } catch(e) { console.error(e); }
            });
        });
        initObserver();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', wireButtons);
    } else {
        wireButtons();
    }

    // ── Buy function ───────────────────────────────────────────────────────────
    window.nftSaasBuy = async function(data) {
        var btn  = document.getElementById('nft-btn-'       + data.uid);
        var msg  = document.getElementById('nft-msg-'       + data.uid);
        var txEl = document.getElementById('nft-tx-'        + data.uid);
        var ctr  = document.getElementById('nft-container-' + data.uid);

        if (typeof window.ethereum === 'undefined') {
            alert('Please install MetaMask to purchase NFTs.');
            return;
        }

        try {
            btn.disabled    = true;
            btn.innerText   = 'Connecting…';
            msg.innerText   = 'Opening MetaMask…';
            msg.style.color = '#666';
            txEl.innerHTML  = '';

            var web3     = new Web3(window.ethereum);
            var priceWei = web3.utils.toWei(data.price.toString(), 'ether');

            // ── 1. Parallel: wallet + signature ──────────────────────────────
            var k      = sigKey(data);
            var cached = window._NFT_SIG[k];
            if (!cached) { try { cached = sessionStorage.getItem(k); } catch(e) {} }

            var sigPromise = cached ? Promise.resolve(cached) :
                fetch(data.apiUrl + '/api/mint/signature', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Bypass-Tunnel-Reminder': 'true', 'X-API-Key': data.apiKey },
                    body: JSON.stringify({ artistAddress: data.creator, tokenURI: data.uri, priceWei: priceWei })
                }).then(function(r){ return r.json(); }).then(function(res){
                    if (!res.success) throw new Error(res.error || 'Signature request failed');
                    var sig = res.data.signature; // ← { success, data: { signature } }
                    window._NFT_SIG[k] = sig;
                    try { sessionStorage.setItem(k, sig); } catch(e) {}
                    return sig;
                });

            var walletPromise = window.ethereum.request({ method: 'eth_requestAccounts' });
            var results       = await Promise.all([walletPromise, sigPromise]);
            var buyer         = results[0][0];
            var signature     = results[1];

            // ── 2. Switch network if needed ───────────────────────────────────
            var currentChain = await window.ethereum.request({ method: 'eth_chainId' });
            if (currentChain.toLowerCase() !== data.chain.chainId.toLowerCase()) {
                msg.innerText = 'Switching to ' + data.chain.chainName + '…';
                try {
                    await window.ethereum.request({
                        method: 'wallet_switchEthereumChain',
                        params: [{ chainId: data.chain.chainId }]
                    });
                } catch (switchErr) {
                    if (switchErr.code === 4902) {
                        await window.ethereum.request({
                            method: 'wallet_addEthereumChain',
                            params: [{
                                chainId:         data.chain.chainId,
                                chainName:       data.chain.chainName,
                                rpcUrls:         [data.chain.rpcUrl],
                                nativeCurrency:  { name: data.chain.currency, symbol: data.chain.currency, decimals: 18 },
                                blockExplorerUrls: [data.chain.explorerUrl]
                            }]
                        });
                    } else { throw switchErr; }
                }
            }

            // ── 3. Send transaction ───────────────────────────────────────────
            btn.innerText = 'Confirm in MetaMask…';
            msg.innerText = 'Approving transaction…';

            var abi = [{
                inputs: [
                    { internalType: 'address', name: 'artist',    type: 'address' },
                    { internalType: 'string',  name: 'tokenURI',  type: 'string'  },
                    { internalType: 'uint256', name: 'price',     type: 'uint256' },
                    { internalType: 'bytes',   name: 'signature', type: 'bytes'   }
                ],
                name: 'buyAndMint', outputs: [], stateMutability: 'payable', type: 'function'
            }];
            var contract = new web3.eth.Contract(abi, data.contractAddress);

            await contract.methods
                .buyAndMint(data.creator, data.uri, priceWei, signature)
                .send({ from: buyer, value: priceWei })
                .on('transactionHash', function(hash) {
                    msg.innerText  = 'Submitted — waiting for confirmation…';
                    txEl.innerHTML = '<a href="' + data.chain.explorerUrl + '/tx/' + hash + '" target="_blank" style="color:#0070f3;">View on ' + data.chain.chainName + ' ↗</a>';
                })
                .on('receipt', async function(receipt) {
                    msg.innerText   = '🎉 Successfully minted!';
                    msg.style.color = 'green';

                    delete window._NFT_SIG[k];
                    try { sessionStorage.removeItem(k); } catch(e) {}

                    // Persist sold state in Kirby Panel via API
                    try {
                        await fetch(data.fileApiUrl, {
                            method: 'PATCH',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF': data.kirbyToken },
                            body: JSON.stringify({ nft_is_sold: 'true', nft_tx_hash: receipt.transactionHash || '' })
                        });
                    } catch(e) { console.warn('[NFT SaaS] Kirby Panel update failed:', e); }

                    // Replace widget with sold badge
                    setTimeout(function() {
                        if (ctr) ctr.innerHTML = '<div style="background:#d4edda;color:#155724;padding:12px;text-align:center;border-radius:4px;">✅ This NFT has been collected. <a href="' + data.chain.explorerUrl + '/tx/' + (receipt.transactionHash || '') + '" target="_blank" style="font-size:0.8em;color:#155724;">View on-chain ↗</a></div>';
                    }, 1800);
                });

        } catch (err) {
            console.error('[NFT SaaS]', err);
            var errMsg = err.message || String(err);
            if (/user denied|user rejected/i.test(errMsg)) errMsg = 'Transaction cancelled.';
            msg.innerText   = '❌ ' + errMsg;
            msg.style.color = 'red';
            btn.disabled    = false;
            btn.innerText   = 'Try Again';
        }
    };
}());
</script>
