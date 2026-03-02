=== NFT SaaS – Gasless NFT Marketplace ===
Contributors: spntn
Tags: nft, blockchain, polygon, web3, gasless, marketplace, digital art
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sell your artwork as NFTs directly from WordPress. No gas fees for artists — buyers pay at purchase. Platform earns 2% commission.

== Description ==

**NFT SaaS** turns your WordPress site into a gasless NFT marketplace. Artists list their work for free; the NFT is only minted on-chain when a buyer pays — so you never spend a cent to list.

= How it works =

1. Upload your artwork to the Media Library
2. Set a price in POL (Polygon) — or ETH, Base
3. The plugin generates a buy button on your posts/pages
4. A buyer clicks **Buy Now**, connects MetaMask, and pays
5. The NFT is minted on-chain in the same transaction
6. **98% goes to you, 2% goes to the platform — automatically, on-chain**

= Key features =

* **Zero upfront cost** — artists never pay gas to list
* **Lazy minting** — NFT only exists on-chain after a sale
* **Multi-chain** — Polygon Mainnet (default), Ethereum, Base, Sepolia testnet
* **IPFS storage** — artwork & metadata stored on IPFS via Pinata (permanent)
* **MetaMask integration** — seamless buy flow, auto network switch
* **One-click setup** — enter your email + wallet in the plugin settings to get started

= Requirements =

* MetaMask or any EVM-compatible browser wallet (for buyers)
* A free account on the NFT SaaS platform — [get your API key here](https://github.com/spntnhub/nft-saas)

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

98% of every sale goes directly to your wallet address. The platform takes 2% automatically through the smart contract.

= Which wallets are supported? =

Any EVM-compatible wallet works on the buyer side: MetaMask, Coinbase Wallet, Rainbow, etc.

= Is the artwork stored on the blockchain? =

The artwork file and metadata are stored on **IPFS** (via Pinata), which is permanent and decentralized. Only the token ownership is recorded on-chain.

= Which networks are supported? =

Polygon Mainnet is preconfigured and requires no setup. Ethereum Mainnet, Base, and Sepolia testnet can be added in settings if you deploy the contract there.

= What if a buyer doesn't have a wallet? =

The buy button will prompt them to install MetaMask. Wallet-less purchasing is not yet supported.

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
* 2% on-chain platform commission (ERC2981)

== Upgrade Notice ==

= 1.0.0 =
Initial release.
