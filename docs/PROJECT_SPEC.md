# PROJECT_SPEC.md

# Quran Video Engine (QVE)

## Vision

Build a professional engine that generates Quran videos while preserving
authentic Mushaf appearance, accurate recitation timing, translation,
and tafsir.

## MVP

-   Laravel backend
-   One template (1080x1920)
-   One background
-   One reciter
-   Sahih International translation
-   Tafsir Al-Muyassar
-   Glyph-based Mushaf rendering
-   Word-level timings
-   Dynamic video rendering

## Data Sources

-   Quran Foundation: Quran, words, translation, tafsir
-   QUL (Tarteel): Surah MP3 + word timings
-   Mushaf Glyph Fonts: glyph text + 604 page fonts

## Core Architecture

Import Engine → Database → Layout Engine → Segment Storage → Rendering
Engine → Video

## Core Decisions

-   Word is the canonical entity.
-   Segments are stored.
-   Layout measures Glyph text.
-   Rendering uses Glyph text only.
-   Audio stored per Surah.
-   Audio clips generated at runtime.
-   Runtime never calls external APIs.
-   AI is not used during rendering.
-   Templates own all coordinates.
-   Future AI may improve semantic segmentation only.
