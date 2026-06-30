# Storage & External Binaries Migration Documentation

This document describes where external binaries and generated media are stored, how to modify their locations, and how to restore default Laravel storage behavior.

---

## 1. Directory Locations

### External Binaries (FFmpeg / FFprobe)
All external media binaries are located under the dedicated tools folder:
`D:\work\PROGRAMMIG\Prj for me\tools\ffmpeg\`

Executables inside this directory:
- `ffmpeg.exe` (Main transcoder engine)
- `ffprobe.exe` (Audio metadata probe tool)

### Generated Media Data Root
All heavy/generated files are written to:
`D:\work\PROGRAMMIG\Prj for me\quran-video-maker\quran-video-data\`

Subdirectories under the storage root:
- `audio/` (Source recitation MP3 files)
- `rendered_segments/` (Intermediate segment frame PNG images)
- `videos/` (Final CFR MP4 vertical reel files)
- `debug/` (Segments validation timing JSON reports)
- `temp/` (Concat demuxer helper files)
- `backgrounds/` (Default canvas layout backgrounds)

---

## 2. Environment Configuration

The binary paths and storage root are configured via your `.env` file:
```env
FFMPEG_PATH="D:/work/PROGRAMMIG/Prj for me/tools/ffmpeg/ffmpeg.exe"
FFPROBE_PATH="D:/work/PROGRAMMIG/Prj for me/tools/ffmpeg/ffprobe.exe"
QURAN_STORAGE_PATH="D:/work/PROGRAMMIG/Prj for me/quran-video-maker/quran-video-data"
```

---

## 3. How to Move the Data Directory in the Future

If you want to move the heavy generated data directory to another drive or directory (e.g., `E:\quran-data`):
1. Copy the entire contents of `D:\work\PROGRAMMIG\Prj for me\quran-video-maker\quran-video-data\` to the new location `E:\quran-data\`.
2. Update the `.env` file to point to the new path:
   ```env
   QURAN_STORAGE_PATH="E:\quran-data"
   ```
3. Run `php artisan optimize:clear` to flush config and environment cache. The application will instantly begin writing new files to the new location.

---

## 4. How to Restore Default Laravel Storage Behavior

To restore default Laravel storage behavior (writing directly inside the project's own `storage/` folder):
1. Delete or comment out the `QURAN_STORAGE_PATH` key in your `.env` file:
   ```env
   # QURAN_STORAGE_PATH=
   ```
2. Flush the configuration cache:
   ```bash
   php artisan optimize:clear
   ```
3. The dynamic path resolver will automatically fall back to the default local Laravel paths:
   - `storage/app/quran/audio/`
   - `storage/app/rendered_segments/`
   - `storage/app/output/` (for videos)
   - `storage/app/debug/`
   - `storage/app/temp/`
   - `storage/app/backgrounds/`
