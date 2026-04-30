# NFT SaaS – Gasless NFT Marketplace

![Version](https://img.shields.io/badge/version-1.3.0-blue)
![License](https://img.shields.io/badge/license-GPL--2.0-green)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4)
![Chain](https://img.shields.io/badge/chain-Polygon-8247e5)

Sell your artwork as NFTs directly from WordPress. No gas fees for artists — the NFT is minted only when a buyer pays.

---

## How it works

```
Artist uploads artwork to Media Library and sets a price
        ↓
Plugin stores a listing — nothing on-chain yet
        ↓
Buyer clicks "Buy Now" → MetaMask opens
        ↓
Buyer pays (price + gas) → smart contract mints NFT to buyer
        ↓
97% → artist wallet (instant, on-chain)
 3% → platform fee (automatic)
```

- **Zero upfront cost** — artists never pay gas to list
- **Lazy minting** — NFT only exists on-chain after a sale
- **Non-custodial** — funds go directly to your wallet, no intermediary

---

## Quick start

### 1. Install

Upload the plugin folder to `/wp-content/plugins/` and activate it in **Plugins → Installed Plugins**.

### 2. Get an API key

**NFT SaaS → Settings** → click **"Don't have one? Click here to generate instantly"** → enter your email and wallet address → click **Get API Key**. The key is filled in automatically.

Click **Save Settings**.

### 3. List artwork

Open any image in the **Media Library**. Set a price (in POL) and a description. The listing is ready — nothing happens on-chain until a buyer pays.

### 4. Add a buy button

In any post or page, add the **NFT Buy Button** block from the Gutenberg block inserter. Configure the image and price in the block settings.

---

## Buyer flow

1. Visitor clicks **Buy Now**.
2. MetaMask opens and prompts wallet connection.
3. If the wallet is on the wrong network, MetaMask switches automatically.
4. Buyer confirms the transaction — the NFT is minted in the same transaction.
5. 97% of the sale goes to the artist's wallet instantly. 3% goes to the platform.

---

## Supported chains

| Chain | Native token | ERC-20 |
|---|---|---|
| Polygon Mainnet | POL | USDC, USDT |
| Base | ETH | USDC |
| Arbitrum One | ETH | USDC |

Polygon is the default and requires no configuration. Other chains require contract deployment — contact info@spntn.com.

---

## Pricing

| | |
|---|---|
| Plugin | Free |
| API key | Free |
| Listing | Free (no gas) |
| Platform fee | 3% per sale (on-chain, automatic) |
| Artist receives | 97% of each sale |

---

## Requirements

* WordPress 6.0 or higher
* PHP 8.0 or higher
* HTTPS on the site (required for MetaMask)
* Buyers need MetaMask or a compatible EVM wallet

---

## External services

The plugin connects to:

* **NFT SaaS backend** (`https://nft-saas-production.up.railway.app`) — authentication, mint signature generation, IPFS upload, purchase recording.
* **Polygon Mainnet** (and Base, Arbitrum) — mint transactions are submitted by the buyer's wallet via MetaMask.
* **IPFS via Pinata** — artwork and metadata stored permanently.

API keys are stored server-side and never sent to the browser.

---

## Support

Email: info@spntn.com
GitHub: https://github.com/spntnhub/nft-saas-wp

---

## License

GPL v2 or later — https://www.gnu.org/licenses/gpl-2.0.html
