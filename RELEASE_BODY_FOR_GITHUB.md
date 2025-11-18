Domain Manager — Migration release (Provider-agnostic + NameCheap)

Release date: 2025-11-18

Summary
- Migration to a provider-agnostic architecture; added NameCheap provider support.
- Renamed plugin-level includes from `class-opensrs-*` to `class-domain-manager-*`.
- Added per-product provider selection, provider connection tester, provider-aware checkout validation, and improved renewals handling.
- Added CI linting (`.github/workflows/php-lint.yml`) and a local `dev-check.ps1` helper.

Details
- Provider facade: `includes/class-domain-provider.php` routes domain operations to either OpenSRS or NameCheap.
- Provider API wrappers: `includes/class-opensrs-api.php` and `includes/class-namecheap-api.php` (provider-specific).
- Plugin-level helpers (migrated from `class-opensrs-*`):
  - `includes/class-domain-manager-settings.php`
  - `includes/class-domain-manager-product-type.php`
  - `includes/class-domain-manager-domain-importer.php`
  - `includes/class-domain-manager-pricing.php`
  - `includes/class-domain-manager-renewals.php`
  - `includes/class-domain-manager-checkout.php`

Upgrade notes
- Legacy helper files were archived to `archive/includes/` — no action needed if you have backups.
- Test on staging: verify provider connections, TLD import, product settings, and checkout flows (especially NameCheap contact validation).

How to publish this release (example)
1. Make sure your working tree is clean and pushed to `origin/main`.
2. Create a tag and push it:
   ```pwsh
   git tag -a v1.0.3-migration-2025-11-18 -m "Migration to provider-agnostic layer + NameCheap support"
   git push origin v1.0.3-migration-2025-11-18
   ```
3. Create a draft GitHub release using the `gh` CLI:
   ```pwsh
   gh release create v1.0.3-migration-2025-11-18 --title "Domain Manager 1.0.3 — Migration (2025-11-18)" --notes-file RELEASE_BODY_FOR_GITHUB.md --draft
   ```

Files changed in this release
- See `README.md` and `INSTALL.md` in repository root for full details and developer checks.

Notes
- If you need help publishing the release, send me the output of the `git` or `gh` commands and I can advise next steps.
