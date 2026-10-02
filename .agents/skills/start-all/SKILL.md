---
name: start-all
description: >-
  Starts ALL development services together for Quran Video Maker: Laravel backend API (php artisan serve),
  queue worker (php artisan queue:work --timeout=0), and Next.js frontend (npm run dev inside frontend).
  Use this skill whenever the user asks to start/run the entire project or all services ("شغل كله", "start all",
  "شغل المشروع كامل", "run all", "start-all", "تشغيل كل السيرفرات").
---

# Start All Services (Full Environment)

This skill launches and verifies all necessary services to run the **Quran Video Maker** application locally.

## Services Architecture

1. **Laravel Backend API**:
   - Directory: Project Root (`d:\work\PROGRAMMIG\Prj for me\quran-video-maker`)
   - Command: `php artisan serve`
   - Port: `8000` (URL: `http://localhost:8000`)

2. **Queue Worker**:
   - Directory: Project Root (`d:\work\PROGRAMMIG\Prj for me\quran-video-maker`)
   - Command: `php artisan queue:work --timeout=0`
   - Purpose: Processes video rendering and generation jobs asynchronously with no timeout limit.

3. **Next.js Frontend**:
   - Directory: `frontend/` (`d:\work\PROGRAMMIG\Prj for me\quran-video-maker\frontend`)
   - Command: `npm run dev` (or `npm --prefix frontend run dev`)
   - Port: `3000` (URL: `http://localhost:3000`)

---

## Agent Execution Runbook

When invoked, the agent should follow these steps strictly:

### Step 1: Pre-Flight Check (Port Availability)
Check if ports `8000` (Laravel) and `3000` (Next.js) are already in use:
```powershell
Get-NetTCPConnection -LocalPort 8000, 3000 -ErrorAction SilentlyContinue | Select-Object LocalAddress, LocalPort, State, OwningProcess
```
- If a port is already active in `Listen` state, inform the user or keep the existing service running instead of failing on duplicate binds.

### Step 2: Start Backend Server (`php artisan serve`)
Run the Laravel development server as a background daemon process using `run_command`:
- **`CommandLine`**: `php artisan serve`
- **`Cwd`**: `d:\work\PROGRAMMIG\Prj for me\quran-video-maker`
- **`IsDaemon`**: `true`
- **`WaitMsBeforeAsync`**: `2000`

### Step 3: Start Queue Worker (`php artisan queue:work --timeout=0`)
Run the Laravel queue worker as a background daemon process using `run_command`:
- **`CommandLine`**: `php artisan queue:work --timeout=0`
- **`Cwd`**: `d:\work\PROGRAMMIG\Prj for me\quran-video-maker`
- **`IsDaemon`**: `true`
- **`WaitMsBeforeAsync`**: `2000`

### Step 4: Start Frontend (`npm run dev`)
Run the Next.js development server as a background daemon process using `run_command`:
- **`CommandLine`**: `npm run dev`
- **`Cwd`**: `d:\work\PROGRAMMIG\Prj for me\quran-video-maker\frontend`
- **`IsDaemon`**: `true`
- **`WaitMsBeforeAsync`**: `3000`

### Step 5: Verify & Report to User
1. Verify listeners:
```powershell
Get-NetTCPConnection -LocalPort 8000, 3000 -State Listen -ErrorAction SilentlyContinue | Select-Object LocalAddress, LocalPort, State
```
2. Present a clear, formatted summary to the user:
   - **Frontend URL**: `http://localhost:3000`
   - **Backend API URL**: `http://localhost:8000`
   - **Queue Worker**: Running in background (`--timeout=0`)
   - Background Task IDs for monitoring via `manage_task`
