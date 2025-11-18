<#
PowerShell helper to create a git tag and a draft GitHub release using the gh CLI.

Usage (run from repo root):
    .\scripts\create-release.ps1 -Tag v1.0.3-migration-2025-11-18 -Message "Migration to provider-agnostic layer"

Requirements:
- git available in PATH
- GitHub CLI (`gh`) installed and authenticated (gh auth login)
#>
param(
    [Parameter(Mandatory=$true)]
    [string]$Tag,

    [string]$Message = "Release $Tag",

    [string]$NotesFile = "RELEASE_BODY_FOR_GITHUB.md",

    [switch]$Draft = $true
)

function Check-Tool {
    param($name)
    $p = Get-Command $name -ErrorAction SilentlyContinue
    if (-not $p) { Write-Error "$name not found in PATH. Please install or configure PATH."; exit 2 }
}

# Ensure tools available
Check-Tool git
Check-Tool gh

# Ensure working directory is repo root (has .git)
if (-not (Test-Path .git)) {
    Write-Error "Current directory does not appear to be a git repository root. Run this script from the repository root."; exit 2
}

# Ensure working tree is clean
$status = git status --porcelain
if ($status) {
    Write-Error "Working tree is not clean. Please commit or stash changes before creating a release.\nStatus:\n$status"; exit 2
}

# Create annotated tag
Write-Host "Creating annotated tag $Tag" -ForegroundColor Cyan
git tag -a $Tag -m "$Message"

Write-Host "Pushing tag to origin" -ForegroundColor Cyan
git push origin $Tag

# Compose gh release command
$ghArgs = @($Tag, '--title', "Domain Manager $Tag - Migration")
if (Test-Path $NotesFile) {
    $ghArgs += @('--notes-file', $NotesFile)
}
if ($Draft) { $ghArgs += '--draft' }

Write-Host "Creating draft release via gh..." -ForegroundColor Cyan
gh release create @ghArgs

Write-Host "Release created (draft). Review on GitHub and publish when ready." -ForegroundColor Green
