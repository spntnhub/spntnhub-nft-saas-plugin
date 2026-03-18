=== NFT SaaS – Gasless NFT Marketplace ===
Contributors: spntn
Tags: nft, blockchain, polygon, web3, gasless
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 1.3.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sell your artwork as NFTs directly from WordPress. No gas fees for artists — buyers pay at purchase. Accepts POL, USDC, USDT. Platform earns 3% fee.

== Description ==

**NFT SaaS** turns your WordPress site into a gasless NFT marketplace. Artists list their work for free; the NFT is only minted on-chain when a buyer pays — so you never spend a cent to list.

= How it works =

1. Upload your artwork to the Media Library
2. Set a price in POL (Polygon) — or ETH, Base
3. The plugin generates a buy button on your posts/pages
4. A buyer clicks **Buy Now**, connects MetaMask, and pays
5. The NFT is minted on-chain in the same transaction
6. **97% goes to you, 3% goes to the platform — automatically, on-chain**

= Key features =

* **Zero upfront cost** — artists never pay gas to list
* **Lazy minting** — NFT only exists on-chain after a sale
* **Multi-chain** — Polygon Mainnet (default), Ethereum, Base, Sepolia testnet
* **IPFS storage** — artwork & metadata stored on IPFS via Pinata (permanent)
* **MetaMask integration** — seamless buy flow, auto network switch
* **One-click setup** — enter your email + wallet in the plugin settings to get started

= Requirements =

* MetaMask or any EVM-compatible browser wallet (for buyers)
* A free account on the NFT SaaS platform — [github.com/spntnhub/nft-saas-wp](https://github.com/spntnhub/nft-saas-wp)

== Installation ==

1. Upload the `nft-saas` folder to the `/wp-content/plugins/` directory, or install via **Plugins > Add New**.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **NFT SaaS > Settings** in the admin menu.
4. Click **"Don't have one? Click here to generate instantly"** to connect to the platform.
5. Enter your **email** and **wallet address** (where you receive earnings).
6. Click **Get API Key** — your key is filled in automatically.
7. Click **Save Settings**.

You're ready to sell NFTs! Upload artwork via **Media Library**, set a price, and insert the buy button in any post or page.

== Frequently Asked Questions ==

= Do I need to pay anything to list an NFT? =

No. Listing is completely free. The NFT is only minted (and gas is paid by the buyer) when someone purchases it.

= What percentage do I earn per sale? =

97% of every sale goes directly to your wallet address. The platform takes 3% automatically through the smart contract (POL, USDC, or USDT accepted).

= Which wallets are supported? =

Any EVM-compatible wallet works on the buyer side: MetaMask, Coinbase Wallet, Rainbow, etc.

= Is the artwork stored on the blockchain? =

The artwork file and metadata are stored on **IPFS** (via Pinata), which is permanent and decentralized. Only the token ownership is recorded on-chain.

= Which networks are supported? =

Polygon Mainnet is preconfigured and requires no setup. Ethereum Mainnet, Base, and Sepolia testnet can be added in settings if you deploy the contract there.

= What if a buyer doesn't have a wallet? =

The buy button will prompt them to install MetaMask. Wallet-less purchasing is not yet supported.

== External services ==

The NFT SaaS plugin interacts with several external services:

- **NFT SaaS Backend API**
  - URL: https://nft-saas-production.up.railway.app
  - Purpose: Handles authentication, project management, mint signatures, webhook events, and IPFS uploads. All plugin features rely on this API for core operations.

- **Polygon Mainnet**
  - Purpose: NFT minting and verification are performed on Polygon Mainnet via smart contracts. The plugin interacts with Polygon using ethers.js.

- **IPFS**
  - Purpose: NFT metadata and media files are uploaded to IPFS for decentralized storage.

- **Explorer Links**
  - Purpose: Plugin provides links to Polygon block explorers (e.g., Polygonscan) for transaction and token verification.

API keys and sensitive credentials are stored server-side and never exposed to frontend users.

This plugin connects to the NFT SaaS backend and Pinata IPFS to:

* Activate the plugin and manage API keys (`POST /api/auth/activate`)
* Upload artwork and metadata to IPFS via Pinata (`POST /api/v2/nft/upload`)
* Generate backend-signed lazy-mint vouchers (`POST /api/v2/nft/sign`)
* Record on-chain mint events (`POST /api/v2/nft/record`)

The API key is stored in WordPress and all requests are made server-side (PHP). Your artwork is uploaded to IPFS — a permanent, decentralised file network.

**Backend URL:** `https://nft-saas-production.up.railway.app`
**Terms of Use:** https://spntn.com/terms
**Privacy Policy:** https://spntn.com/privacy

**Pinata (IPFS):** artwork files and metadata are pinned via Pinata.
**Privacy policy:** https://www.pinata.cloud/privacy

ethers.js (v6) is loaded from cdnjs.cloudflare.com for buyer wallet interactions.
**Privacy policy:** https://www.cloudflare.com/privacypolicy/

Data sent: artwork files, wallet addresses, transaction hashes.
Data transmitted when: on plugin activation, on NFT listing, on buyer purchase.

== Screenshots ==

1. Admin settings page — connect to the platform in 30 seconds
2. Media Library — set NFT price and description per image
3. Buy button on a post — viewers can purchase with one click
4. MetaMask confirmation — buyer sees exactly what they're paying

== Changelog ==

= 1.0.0 =
* Initial release
* Lazy minting via Polygon Mainnet
* IPFS metadata upload via Pinata
* Multi-chain support (Polygon, ETH, Base, Sepolia)
* One-click plugin activation
* MetaMask buy flow with auto network switch
* Redis-cached signatures for fast buy button load
* 3% on-chain platform fee (POL, USDC, USDT — Polygon Mainnet)

== Upgrade Notice ==

= 1.0.0 =
Initial release.
