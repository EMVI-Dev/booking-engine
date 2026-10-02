# Agent Delegation Rules

1. **Backend & Business Logic (Owner: Claude via CLI):**
   - All Service classes, Actions, Eloquent models, migrations, DB transactions, controllers, routes, and comprehensive audits belong strictly to Claude.
   - Claude is invoked directly via CLI (`~/.local/bin/claude`).

2. **Frontend, UI/UX & Components (Owner: Gemini / Antigravity):**
   - Blade templates, Livewire 4 SFC markup, Alpine.js reactivity, Tailwind CSS v4 styling, component layouts, and responsive designs.
   - Strictly consumes what Claude has created. Never write or alter backend business logic files directly.

3. **Inter-Agent Collaboration:**
   - When a frontend feature requires a new backend method, property, API, or service, request it from Claude via the CLI (`~/.local/bin/claude -p "..."`) rather than implementing backend logic directly.
   - Only implement UI components once the corresponding backend endpoints/contracts are established by Claude.
