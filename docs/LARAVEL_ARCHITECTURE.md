# LARAVEL_ARCHITECTURE.md

# Quran Video Engine (QVE)

## Laravel Architecture Guide

Version: 1.0

------------------------------------------------------------------------

# Technology Stack

## Backend

-   Laravel 12
-   PHP 8.2

## Admin Panel

-   Filament 4

## Database

-   MySQL

## Cache & Queue

-   Redis
-   Laravel Queue

## Media

-   FFmpeg

## Storage

-   Laravel Storage

------------------------------------------------------------------------

# Architectural Philosophy

Laravel is the application's brain.

Responsibilities:

-   Authentication
-   Admin Dashboard
-   Database
-   Import Engine
-   Template Management
-   Segment Management
-   Tafsir Review
-   Audio Management
-   Render Queue
-   Job Dispatching

Rendering consumes prepared data and produces video files.

------------------------------------------------------------------------

# Recommended Project Structure

app/

Modules/

-   Shared/
-   Quran/
-   Import/
-   Layout/
-   Template/
-   Audio/
-   Rendering/
-   Content/
-   RenderQueue/

Console/

Providers/

------------------------------------------------------------------------

# Module Responsibilities

## Shared

Common utilities and contracts.

## Quran

Quran models and repositories.

## Import

Import all external resources.

## Layout

Generate and store segments.

## Template

Manage template configuration.

## Audio

Manage Surah MP3 files and timing.

## Content

Prepare translation and tafsir.

## Rendering

Generate videos using prepared data.

## RenderQueue

Dispatch and monitor render jobs.

------------------------------------------------------------------------

# Core Rules

-   Word is the canonical entity.
-   Segments are stored.
-   Layout measures Glyph text.
-   Rendering uses Glyph fonts only.
-   Runtime never calls external APIs.
-   Audio is stored per Surah.
-   Audio clips are generated during rendering.
-   Business logic never contains pixel coordinates.
-   Templates define layout.

------------------------------------------------------------------------

# Rendering Flow

User

↓

Laravel

↓

Queue Job

↓

Rendering Module

↓

FFmpeg

↓

Storage

↓

Finished Video

------------------------------------------------------------------------

# Development Principles

-   SOLID
-   Clean Architecture
-   Single Responsibility
-   Dependency Injection
-   Feature-based Modules
-   Read-only rendering pipeline
-   Import once, read many

------------------------------------------------------------------------

# Future Scalability

The architecture supports:

-   Multiple templates
-   Multiple reciters
-   Multiple languages
-   AI-assisted semantic segmentation
-   AI-assisted tafsir preparation
-   Additional rendering engines

without redesigning the core architecture.
