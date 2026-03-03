# NFT SaaS — Kirby CMS Plugin Guide

Sell your digital art as NFTs directly from your Kirby site. Buyers pay on-chain with their own wallet — you receive **97% of every sale** automatically.

---

## Requirements

| Item | Minimum |
|---|---|
| Kirby CMS | 3.6+ |
| PHP | 7.4+ |
| Composer | Optional |

---

## 1. Install the Plugin

Copy the `nft-saas` folder into your Kirby `site/plugins/` directory:

```
site/
  plugins/
    nft-saas/        ← plugin folder goes here
      index.php
      snippets/
      blueprints/
```

The plugin folder is included in the [repository](https://github.com/spntnhub/nft-saas/tree/main/plugins/kirby/kirby-plugin).

---

## 2. Configure

Add to `site/config/config.php`:

```php
return [
    // NFT SaaS backend URL
    'nft-saas.api_url'         => 'https://nft-saas-production.up.railway.app',

    // Your secret API key (get one at the platform URL above → register)
    'nft-saas.api_key'         => 'nfts_your_api_key_here',

    // Smart contract address on Polygon Mainnet (pre-configured)
    'nft-saas.contract_address' => '0xF912D97BB2fF635c3D432178e46A16930B5Af51A',

    // Your EVM wallet address — where 97% of each sale is sent
    'nft-saas.artist_wallet'   => '0xYourWalletAddress',
];
```

### Getting an API Key

1. Visit `https://nft-saas-production.up.railway.app/api/auth/activate` (POST) with your email and wallet to get a key automatically, **or**
2. Use the WordPress plugin's "Get API Key" flow if you have a WP site, then copy the key here.

---

## 3. Add NFT Fields to Images

Apply the `files/nft-image` blueprint to any image in your Panel blueprints:

```yaml
# site/blueprints/pages/artwork.yml
sections:
  images:
    type: files
    template: nft-image   # ← this enables all NFT fields
```

Available fields added to each image:

| Field | Description |
|---|---|
| `nft_is_for_sale` | Toggle — show/hide the buy button |
| `nft_price` | Sale price (in native token) |
| `nft_chain` | `polygon` / `ethereum` / `base` / `sepolia` |
| `nft_token_uri` | IPFS URI of NFT metadata (optional — defaults to file URL) |
| `nft_is_sold` | Auto-set to `true` after a sale |
| `nft_tx_hash` | On-chain transaction hash (auto-filled after sale) |

---

## 4. Render the Buy Button

In any template where `$image` is a Kirby file object:

```php
<?php snippet('nft-buy-button', ['file' => $image]) ?>
```

The snippet automatically:
- Hides if `nft_is_for_sale` is `false`
- Shows a "sold" badge if `nft_is_sold` is `true`
- Prefetches the signature when the button scrolls into view (faster UX)
- Switches the buyer's MetaMask to the correct network if needed

---

## 5. How a Sale Works

```
Buyer clicks "Buy Now"
  ↓
Snippet requests a signature from NFT SaaS backend (X-API-Key header)
  ↓
Backend verifies API key → checks artist wallet matches → signs
  ↓
MetaMask opens → buyer pays (price + gas)
  ↓
Smart contract mints NFT to buyer's wallet
  ↓
97% of sale → your artist_wallet  (instant, on-chain)
 3% fee     → platform  (on every sale)
 3% royalty → platform  (on every resale via ERC-2981)
  ↓
Snippet calls Kirby Panel API to set nft_is_sold = true
```

---

## 6. NFT Metadata Format

Point `nft_token_uri` to a JSON file on IPFS:

```json
{
  "name": "Artwork Title",
  "description": "A description of the piece.",
  "image": "ipfs://QmImageHash...",
  "attributes": [
    { "trait_type": "Medium", "value": "Photography" }
  ]
}
```

If `nft_token_uri` is left blank, the file's Kirby URL is used as the token URI.

---

## 7. Supported Chains

| Chain | Currency | Contract |
|---|---|---|
| Polygon Mainnet | POL, USDC, USDT | `0xF912D97BB2fF635c3D432178e46A16930B5Af51A` |
| Ethereum Mainnet | ETH | Configure `nft-saas.contract_address_ethereum` |
| Base Mainnet | ETH | Configure `nft-saas.contract_address_base` |
| Sepolia Testnet | ETH | Configure `nft-saas.contract_address_sepolia` |

---

## 8. Troubleshooting

| Symptom | Fix |
|---|---|
| Buy button doesn't appear | Check that `nft_is_for_sale` is toggled on in the Panel. |
| `API key required` on purchase | Ensure `nft-saas.api_key` is set in `config.php`. |
| `artistAddress does not match` | The `nft-saas.artist_wallet` must match the wallet registered with your API key. |
| `Price too low` | Minimum on Polygon is 1 POL / 5 USDC / 5 USDT. Increase `nft_price` in the Panel. |
| Sold state not persisting | Kirby Panel API must be accessible. Check Panel auth and CSRF settings. |

---

## 9. Security Notes

- The API key is stored in `config.php` (not public). Never commit `config.php` to a public repo — add it to `.gitignore`.
- The backend validates that your `artist_wallet` matches the API key owner on every signature request.
- The buy button sends `X-API-Key` as a request header (never visible in the page HTML).
