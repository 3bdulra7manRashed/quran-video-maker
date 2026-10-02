@echo off
title Quran Video Maker Launcher
echo ===================================================
echo  Starting Quran Video Maker Services
echo ===================================================

cd /d "%~dp0"

echo [1/3] Launching Laravel Server (php artisan serve)...
start "Laravel Server" cmd /k "php artisan serve"

echo [2/3] Launching Queue Worker (php artisan queue:work --timeout=0)...
start "Queue Worker" cmd /k "php artisan queue:work --timeout=0"

echo [3/3] Launching Next.js Frontend (npm run dev)...
start "Next.js Frontend" cmd /k "cd frontend && npm run dev"

echo.
echo All services launched!
echo - Frontend:    http://localhost:3000
echo - Backend API: http://localhost:8000
echo - Queue:       Running with --timeout=0
