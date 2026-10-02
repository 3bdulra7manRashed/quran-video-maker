# PowerShell helper script to launch all Quran Video Maker services
$rootDir = (Resolve-Path "$PSScriptRoot\..\..\..").Path
$frontendDir = Join-Path $rootDir "frontend"

Write-Host "=================================================" -ForegroundColor Cyan
Write-Host " Starting Quran Video Maker Development Services " -ForegroundColor Cyan
Write-Host "=================================================" -ForegroundColor Cyan

# 1. Start Laravel Backend (php artisan serve)
Write-Host "[1/3] Starting Backend API (http://localhost:8000)..." -ForegroundColor Yellow
Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd '$rootDir'; php artisan serve"

# 2. Start Laravel Queue Worker (php artisan queue:work --timeout=0)
Write-Host "[2/3] Starting Queue Worker (--timeout=0)..." -ForegroundColor Yellow
Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd '$rootDir'; php artisan queue:work --timeout=0"

# 3. Start Next.js Frontend (npm run dev)
Write-Host "[3/3] Starting Next.js Frontend (http://localhost:3000)..." -ForegroundColor Yellow
Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd '$frontendDir'; npm run dev"

Write-Host "`nAll services have been launched in separate terminal windows." -ForegroundColor Green
Write-Host "Frontend:    http://localhost:3000" -ForegroundColor White
Write-Host "Backend API: http://localhost:8000" -ForegroundColor White
Write-Host "Queue:       Active (--timeout=0)" -ForegroundColor White
