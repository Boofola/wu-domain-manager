# dev-check.ps1
# Quick developer checks for this repository
# - Runs PHP lint across all PHP files
# - Reports files where '{' and '}' counts differ (common source of parse errors)

param(
    [string]$Path = "$(Get-Location)"
)

Write-Host "Running PHP syntax check (php -l) on PHP files in: $Path"

# Find php executable
$php = Get-Command php -ErrorAction SilentlyContinue
if (-not $php) {
    Write-Host "PHP CLI not found in PATH. Please install PHP to run this script." -ForegroundColor Yellow
} else {
    Get-ChildItem -Path $Path -Recurse -Filter *.php | ForEach-Object {
        $file = $_.FullName
        $result = & php -l $file 2>&1
        if ($LASTEXITCODE -ne 0) {
            Write-Host "[SYNTAX ERROR] $file" -ForegroundColor Red
            Write-Host $result
        } else {
            Write-Host "[OK] $file"
        }
    }
}

Write-Host "\nChecking brace balance (counts of '{' vs '}')"
Get-ChildItem -Path $Path -Recurse -Filter *.php | ForEach-Object {
    $c = Get-Content $_.FullName -Raw -ErrorAction SilentlyContinue
    if ($null -ne $c) {
        $opens = ([regex]::Matches($c,'\{')).Count
        $closes = ([regex]::Matches($c,'\}')).Count
        if ($opens -ne $closes) {
            Write-Host "[BRACE MISMATCH] $($_.FullName) -> opens=$opens closes=$closes" -ForegroundColor Yellow
        }
    }
}

Write-Host "\nDev checks completed."