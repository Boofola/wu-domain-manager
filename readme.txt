Possible future improvements:
- Add test buttons next to each provider field in the settings UI for more granular diagnostics.
Domain Manager for Ultimate Multisite

This add-on allows selling and managing domains through Ultimate Multisite.

NameCheap integration
- The plugin now supports NameCheap as an additional domain provider alongside OpenSRS.
- Configure NameCheap credentials at Network Admin → Settings → OpenSRS Domain Manager.
- Provide `ApiUser`, `ApiKey`, `UserName`, and `ClientIp` (authorized server IP) in the settings.

Notes and limitations
- TLD import/pricing is only available via OpenSRS. NameCheap does not expose a direct pricing import endpoint in this integration; you should set product prices manually or keep OpenSRS as the pricing source.
- NameCheap domain registrations require full contact details. The checkout now includes a contact section — populate those fields when using NameCheap.
- Use the "Test Connection" button in settings to validate the selected default provider.

If you renamed classes/files while experimenting, this plugin attempts to preserve the original class names. If you find fatal errors, revert local renames and ensure files use the plugin's original class names (e.g. `WU_OpenSRS_API`, `WU_OpenSRS_Settings`).

