# wu-domain-manager — Domain Manager for Ultimate Multisite

This repository contains the Domain Manager plugin for Ultimate Multisite.

## Recent changes
- Introduced provider-agnostic layer and NameCheap support.
- Renamed several includes from `class-opensrs-*` to `class-domain-manager-*` to clarify which files are plugin-level helpers vs provider-specific API wrappers.
- Added CI: GitHub Actions workflow to run `php -l` on push and pull requests.

## New files of interest
- `includes/class-domain-manager-settings.php` — plugin-level settings and connection tester UI
- `includes/class-domain-manager-product-type.php` — domain product type & product-level settings
- `includes/class-domain-manager-domain-importer.php` — TLD import/refresh UI and handlers
- `includes/class-domain-manager-pricing.php` — pricing storage and helpers
- `includes/class-domain-manager-renewals.php` — renewals/expiration checks and auto-renew processor
- `includes/class-domain-manager-checkout.php` — checkout UI and provider-aware validation/registration
- `includes/class-namecheap-api.php` — NameCheap API wrapper
- `includes/class-opensrs-api.php` — OpenSRS API wrapper (provider-specific)
- `includes/class-domain-provider.php` — provider facade routing calls to the selected provider

## Files removed
The following legacy files were removed (their behavior has been ported to `class-domain-manager-*` replacements). Backups are available — do not hesitate to restore if needed.
- `includes/class-opensrs-settings.php`
- `includes/class-opensrs-product-type.php`
- `includes/class-opensrs-domain-importer.php`
- `includes/class-opensrs-pricing.php`
- `includes/class-opensrs-renewals.php`
- `includes/class-opensrs-checkout.php`

## Development checks
A PowerShell helper `dev-check.ps1` was added to the repository root to run quick local diagnostics (PHP lint and brace balance). To run it locally (Windows PowerShell):

```powershell
# run the helper script
.\dev-check.ps1
```

Or run php linting across the repository (requires PHP CLI installed):

```powershell
Get-ChildItem -Path . -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

## CI
A GitHub Actions workflow at `.github/workflows/php-lint.yml` runs `php -l` on all PHP files for pushes and pull requests to `main`.

## Rollback / Backup
You indicated you have backups; the removed files are safe to restore from your backups if you change your mind.

## Next steps
- Test the plugin on a staging environment and run `.\dev-check.ps1` to ensure no syntax regressions.
- If everything is good, consider deleting backups or archiving them externally.

If you want, I can also:
- Create a small migration note in the plugin admin page.
- Run a search to ensure there are no remaining references to the deleted filenames in the codebase.

---
Generated on November 18, 2025.
