# NFT SaaS — WordPress Plugin Guide

Sell your digital art as NFTs directly from your WordPress site. Buyers pay on‑chain with their own wallet — you receive **97% of every sale** automatically. Zero upfront cost for you.

---

## Requirements

| Item | Minimum |
|---|---|
| WordPress | 5.8+ |
| PHP | 7.4+ |
| MetaMask (buyer side) | Latest |

---

## 1. Install the Plugin

1. Download `nft-saas.zip` from the [Releases](https://github.com/spntnhub/nft-saas/releases) page.
2. WordPress Admin → **Plugins → Add New → Upload Plugin**.
3. Select the zip file → **Install Now → Activate**.

The **NFT SaaS** menu will appear in your WordPress sidebar.

---

## 2. Connect to the Platform

Go to **NFT SaaS → Settings**.

### Step 1 — Platform URL

Leave the default:
```
https://nft-saas-production.up.railway.app
```
Only change this if you are self-hosting the backend.

### Step 2 — Get an API Key

Click **"Don't have one? Click here to generate instantly"**.

Fill in the popup:
- **Email** — your contact email (used for account recovery)
- **Earnings Wallet** *(optional, recommended)* — the Polygon/EVM wallet address where you want to receive sale proceeds

Click **✨ Get API Key**. Your key is pasted automatically and your earnings wallet is synced.

> Already have a key? Paste it in the **API Key** field and click **Validate API Key**.

### Step 3 — Save

Click **Save Settings**. Done — you are connected.

---

## 3. List an NFT for Sale

Every post/page supports NFT sales via a Gutenberg block or shortcode.

### Gutenberg Block

1. Edit any post or page.
2. Add block → search **NFT Buy Button**.
3. Fill in:
   - **Price** (in POL/ETH)
   - **Token URI** — IPFS URI to your artwork metadata (`ipfs://Qm…`)
   - **Chain** — Polygon (default), Ethereum, Base, or Sepolia

### Shortcode

```
[nft_buy_button price="0.1" token_uri="ipfs://QmXxx..." chain="polygon"]
```

Available attributes:

| Attribute | Default | Description |
|---|---|---|
| `price` | `0.01` | Sale price in native token |
| `token_uri` | *(required)* | IPFS URI of the NFT metadata JSON |
| `chain` | `polygon` | `polygon` / `ethereum` / `base` / `sepolia` |

---

## 4. How a Sale Works

```
Buyer clicks "Buy Now"
  ↓
Plugin requests a signature from the NFT SaaS backend
  ↓
Backend verifies your API key → checks artist wallet matches → signs
  ↓
MetaMask opens → buyer pays (price + gas)
  ↓
Smart contract mints NFT to buyer's wallet
  ↓
97% of sale → your Earnings Wallet  (instant, on-chain)
 3% fee     → platform  (on every sale)
 3% royalty → platform  (on every resale via ERC-2981)
```

No intermediary holds funds. The smart contract transfers directly.

---

## 5. NFT Metadata Format

Your `token_uri` must point to a JSON file on IPFS:

```json
{
  "name": "My Artwork #1",
  "description": "A short description of the piece.",
  "image": "ipfs://QmImageHash...",
  "attributes": [
    { "trait_type": "Medium", "value": "Digital" },
    { "trait_type": "Edition", "value": "1 of 1" }
  ]
}
```

Recommended IPFS pinning services: [Pinata](https://pinata.cloud) (free tier available).

---

## 6. Supported Chains

| Chain | Currency | Contract |
|---|---|---|
| Polygon Mainnet | POL, USDC, USDT | `0xF912D97BB2fF635c3D432178e46A16930B5Af51A` |
| Ethereum Mainnet | ETH | *(deploy your own)* |
| Base Mainnet | ETH | *(deploy your own)* |
| Sepolia Testnet | ETH | *(for testing)* |

---

## 7. Frequently Asked Questions

**Do I need cryptocurrency to list an NFT?**  
No. Listing is free — no gas required. Buyers pay all on-chain costs.

**When is the NFT minted?**  
At the exact moment of sale (lazy minting). Nothing is on-chain until someone buys.

**Where do I see my earnings?**  
Directly in your wallet. The contract sends funds in the same transaction as the mint.

**Can a buyer fake the price?**  
No. The price is signed by the NFT SaaS backend with your API key. The smart contract rejects any transaction where the sent amount is less than the signed price.

**What if MetaMask is not installed?**  
The buy button shows a clear error asking buyers to install MetaMask.

**Can I change my earnings wallet?**  
Update it in the NFT SaaS dashboard. The WordPress plugin syncs automatically on the next page load.

---

## 8. Troubleshooting

| Symptom | Fix |
|---|---|
| `API key required` error on buy | Your API Key is not saved. Go to Settings → re-enter and Save. |
| `artistAddress does not match` | Your registered wallet doesn't match the one signing. Validate API Key to re-sync. |
| `Price too low` error | Minimum price on Polygon is 1 POL / 5 USDC / 5 USDT. Increase the listing price. |
| Buy button not showing | Ensure the NFT block/shortcode is added and settings are saved. |
| Wrong network badge on button | Buyer is on a different chain. MetaMask will prompt to switch automatically. |

---

## 9. Security Notes

- **API Key is secret.** It is stored in WordPress options (not visible in source code).
- The key is sent as `X-API-Key` header with every signature request — never in the URL.
- The backend validates that the `artistAddress` matches your registered wallet on every sale. Even a leaked API key cannot be used to sign for a different artist.
- Rate limiting is applied per IP and per API key.
