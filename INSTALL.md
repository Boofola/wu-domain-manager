INSTALL AND SETUP — wu-domain-manager (Domain Manager for Ultimate Multisite)

Overview
This document explains how to install, configure, and operate the Domain Manager plugin that supports OpenSRS and NameCheap providers.

Prerequisites
- WordPress Multisite with Ultimate Multisite / WP Ultimo plugin installed and active.
- PHP 7.4+ (8.x recommended).
- cURL extension enabled (used by provider API wrappers).
- Appropriate file permissions to upload plugins and create DB tables.

Installation
1. Copy plugin to the network plugins directory (e.g., `wp-content/mu-plugins/` or `wp-content/plugins/`).
2. Network-activate the plugin from Network Admin > Plugins.
3. After activation, the plugin will create its DB tables (pricing and domains) and schedule cron jobs.

Configuration (Network Admin)
1. Visit: Network Admin → Settings → OpenSRS Domain Manager (this plugin's settings page).
2. Enter provider credentials:
   - OpenSRS: reseller username and API key, choose `test` or `live` mode.
   - NameCheap: ApiUser, ApiKey, UserName, and the authorized Client IP (your server public IP). Choose `sandbox` or `live`.
3. Set the default provider under "Default Domain Provider" (OpenSRS or NameCheap).
4. Use the Provider Connection Test buttons to verify each provider's connection. The UI will show success/error messages.

Product Setup (Per Product)
1. Create a product and set its type to `Domain` (the plugin registers this product type).
2. In the product editor, under Domain Product Settings:
   - Add the allowed TLDs for that product (import TLDs from Settings if needed).
   - Choose `Dynamic` pricing (fetch provider price) or `Fixed` pricing (product price).
   - Toggle default auto-renew and WHOIS privacy options.
   - Optionally override provider selection for the product (choose OpenSRS or NameCheap) to make the product provider-specific.

Importing TLDs and Pricing
- Use Network Admin → Settings → OpenSRS Domain Manager → TLD Management to import available TLDs from the configured provider and to refresh pricing.
- Imported TLDs are stored in the DB table `wp_wu_opensrs_pricing` (or a different prefix if your site uses one).

Checkout Experience
- When customers purchase a Domain product, the checkout shows a domain-search UI and registrant contact fields.
- If NameCheap is selected as provider (per-product or default), registrant contact fields are required. The checkout enforces this server-side via the `wu_setup_checkout` hook and will block completion (or show inline error) if missing.

Renewals and Auto-Renew
- Daily pricing updates, weekly renewal checks, and monthly expiration notification cron hooks are scheduled during activation.
- Auto-renew attempts are performed for domains with `auto_renew` enabled when they are close to expiration.

Developer tools & diagnostics
- `dev-check.ps1`: a PowerShell helper in the repo root that will run `php -l` across PHP files (if PHP CLI is available) and perform a lightweight brace-balance check.

Run locally (PowerShell):
```powershell
# Run the helper
.\dev-check.ps1

# Or lint PHP files directly (requires PHP CLI):
Get-ChildItem -Path . -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

CI
- The repo includes a GitHub Actions workflow `.github/workflows/php-lint.yml` which runs `php -l` on all PHP files for pushes and PRs to `main`.

Troubleshooting
- If provider testing fails, re-check credentials and the API mode (sandbox vs live).
- For NameCheap, confirm that your server IP is authorized in the NameCheap control panel.
- If you see parse/syntax errors, run the `dev-check.ps1` script or `php -l` locally — reboot the server if PHP was recently updated and the CLI is not working.

Rollback
- Backups: removed legacy files were archived under `archive/includes/` in this repository. Restore them if needed.

Support and notes
- The plugin stores provider-specific implementations in `includes/class-opensrs-api.php` and `includes/class-namecheap-api.php`.
- Plugin-level provider-agnostic helpers are in files named `class-domain-manager-*.php`.

If you want, I can also produce a short `UPGRADE.md` documenting step-by-step upgrade actions for pre-existing installations.
