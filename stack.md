# Tech Stack

## MCP Servers — Always Utilise During Development

MCP usage is a first-class part of the workflow, not an afterthought. Every development task should check available MCP context before proceeding.

- **Laravel Boost** — application-level intelligence: PHP/Laravel versions, installed packages, Eloquent models, full DB schema access, query execution
- **Laravel Herd MCP** — infrastructure layer: local environment state, sites, PHP versions, services (MySQL, Redis, Reverb), debug data (queries, jobs, logs)
- **GitHub MCP** — repo state, branches, issues, PRs, commits (used via Claude Code, not this chat)

### Rules

1. **Always check MCP context before making assumptions** — don't guess at local env state, repo state, or existing code structure when an MCP server can confirm it directly.
2. **Commit meaningful, atomic commits via GitHub MCP** — one logical change per commit, clear messages, no bundling unrelated work.
3. Treat MCP servers as the source of truth over stale local context or memory of the project.

## Initial Setup

- Remove ALL Flux-related files and references (not used in this project)
- Replace any Flux components with native Livewire + Blade components + Tailwind equivalents

## Core

- Laravel (latest)
- PHP 8.4+
- MySQL 8.0

## Frontend

- Livewire 4 (no Volt) — Single File Components, Blaze compiler, Islands, Component Slots, Teleport, Lazy Loading, Smarter Loading States
- Reusable UI primitives (buttons, modals, selects, dropdowns, inputs, badges) built as custom Blade components (`resources/views/components/*`) with Tailwind CSS & Alpine.js
- Tailwind CSS 4
- Alpine.js 3

## Webserver

- Nginx

## Database Conventions

- ULIDs for primary keys
- Indexes for columns used in lookups, joins, filtering, and sorting

## Laravel Conventions

- Enum classes over DB enums
- DRY via service classes
- Soft deletes where applicable

## Livewire Conventions

- Single File Components
- Blaze Performance Engine
- Islands architecture
- Component Slots
- Smarter Loading States
- PHP 8.4 integration
- Lazy Loading
- Teleport

## Query Conventions

- Eager load relationships (avoid N+1)
- Use query scopes for reusable filters
- Paginate large datasets

## Code Quality

- Laravel Pint (code formatting)
- Pest (testing)
- Laravel Telescope (debugging, local only)

## Package Suggestions

- Spatie Laravel Permission (roles & permissions — HR vs admin)
- Spatie Laravel Activity Log (alternative to manual asset_logs)
- Spatie Laravel Media Library (device photos, receipts)

## Dev Tooling (installed)

- Laravel Herd (local dev)
- TablePlus (DB GUI)
