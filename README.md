# NFT SaaS — CMS Plugins

Sell digital art as NFTs directly from WordPress or Kirby CMS. Buyers pay on-chain — you receive **98% of every sale** automatically. Zero upfront cost.

---

## Plugins

| Plugin | Docs |
|---|---|
| 🔵 WordPress | [docs/WORDPRESS_PLUGIN.md](docs/WORDPRESS_PLUGIN.md) |
| ⚪ Kirby CMS | [docs/KIRBY_PLUGIN.md](docs/KIRBY_PLUGIN.md) |

---

## How It Works

```
You list artwork with a price  →  no gas, nothing on-chain yet
Buyer clicks "Buy Now"         →  MetaMask opens
Buyer pays (price + gas)       →  smart contract mints NFT to buyer
                                   98% → your wallet  (instant)
                                    2% → platform royalty (every resale)
```

- **Lazy minting** — NFT is minted on-chain only at point of sale
- **Buyer pays all gas** — zero cost for the artist
- **Non-custodial** — funds go directly to your wallet, no intermediary

---

## Quick Start

### WordPress

1. Download `wordpress/` folder or grab the latest zip from [Releases](../../releases)
2. Upload via **Plugins → Add New → Upload Plugin**
3. Go to **NFT SaaS → Settings** → click **"Get API Key"**
4. Full guide: [docs/WORDPRESS_PLUGIN.md](docs/WORDPRESS_PLUGIN.md)

### Kirby CMS

1. Copy `kirby/kirby-plugin/` to `site/plugins/nft-saas/`
2. Add config to `site/config/config.php`
3. Full guide: [docs/KIRBY_PLUGIN.md](docs/KIRBY_PLUGIN.md)

---

## Backend

These plugins connect to the NFT SaaS backend API. The backend is hosted at:

```
https://nft-saas-production.up.railway.app
```

To get an API key, use the "Get API Key" button inside the WordPress plugin settings, or POST to `/api/auth/activate` with your email and wallet address.

---

## License

MIT — see [LICENSE](../../blob/main/LICENSE)

Backend infrastructure is proprietary and not included in this repository.
