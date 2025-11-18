Release draft — Migration to provider-agnostic includes and NameCheap support

Date: 2025-11-18

Summary
- Migrated plugin internals to a provider-agnostic architecture.
- Introduced NameCheap provider support alongside OpenSRS.
- Renamed plugin-level include files from `class-opensrs-*` to `class-domain-manager-*` to separate plugin helpers from provider-specific API wrappers.
- Added connection tester UI, per-product provider selection, and provider-aware checkout validation.
- Added a GitHub Actions workflow to run `php -l` on push/PR and a local `dev-check.ps1` helper.
- Archived legacy files under `archive/includes/` to preserve historical versions.

Upgrade notes
- The plugin now expects the following active includes under `includes/`:
  - `class-domain-manager-settings.php`
  - `class-domain-manager-product-type.php`
  - `class-domain-manager-domain-importer.php`
  - `class-domain-manager-pricing.php`
  - `class-domain-manager-renewals.php`
  - `class-domain-manager-checkout.php`
  - `class-domain-provider.php` (provider facade)
  - Provider API wrappers remain (e.g., `class-opensrs-api.php`, `class-namecheap-api.php`).

- Legacy `class-opensrs-*` helper files have been archived in `archive/includes/`.
- If you keep a backup, no further action is required, but test on a staging site.

Testing
- Run local diagnostics (PowerShell):

```powershell
.\dev-check.ps1
```

- If you have PHP CLI installed, also lint directly:

```powershell
Get-ChildItem -Path . -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

- Verify in Network Admin → OpenSRS Domain Manager settings that the migration notice appears and that the connection tester works for configured providers.

Rollback
- Restore files from your backups or from `archive/includes/` if necessary.

Changelog
- See README.md for more details and instructions.

