# Start local publisher bridge proxy (reads hub-host .env when found).
$ErrorActionPreference = 'Stop'
$here = Split-Path -Parent $MyInvocation.MyCommand.Path

function Read-BackendUrlFromEnvFile([string]$path) {
  if (-not (Test-Path $path)) { return $null }
  foreach ($line in Get-Content $path) {
    if ($line -match '^\s*VITE_APPHUB_BACKEND_URL\s*=\s*(.+)\s*$') {
      return $Matches[1].Trim().Trim('"').Trim("'")
    }
  }
  return $null
}

$candidates = @(
  (Join-Path $here '..\..\..\____TEST\test\apphub-host-starter\.env'),
  (Join-Path $here '..\..\hub-host-starter\.env'),
  (Join-Path $here '.env')
)

$backend = $env:APPHUB_BACKEND_URL
if (-not $backend) {
  foreach ($file in $candidates) {
    $backend = Read-BackendUrlFromEnvFile $file
    if ($backend) {
      Write-Host "Using APPHUB_BACKEND_URL from $file"
      break
    }
  }
}

if (-not $backend) {
  Write-Host 'Set APPHUB_BACKEND_URL or create .env with VITE_APPHUB_BACKEND_URL'
  Write-Host 'Example: http://localhost:8000/api/jmm/zz/apphub'
  exit 1
}

if (-not $env:PORT) { $env:PORT = '51732' }
$env:APPHUB_BACKEND_URL = $backend.TrimEnd('/')

Write-Host "Publisher bridge proxy: http://localhost:$($env:PORT)"
Write-Host "Forwarding to: $($env:APPHUB_BACKEND_URL)"
node (Join-Path $here 'server.mjs')
