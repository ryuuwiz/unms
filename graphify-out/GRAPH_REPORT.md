# Graph Report - unms  (2026-08-17)

## Corpus Check
- 479 files · ~170,429 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 2610 nodes · 3799 edges · 278 communities (242 shown, 36 thin omitted)
- Extraction: 100% EXTRACTED · 0% INFERRED · 0% AMBIGUOUS · INFERRED: 12 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `982b9dec`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- Illuminate\View\View
- Create
- Invoice
- scripts
- Issue tracker: GitHub
- PasswordValidationRules.php
- Issue tracker: GitHub
- Issue tracker: GitHub
- Triage
- Triage
- Triage
- ProfilBandwidth
- AGENTS.md
- .agents/skills/teach/SKILL.md
- .claude/skills/teach/SKILL.md
- dependencies
- .kilocode/skills/teach/SKILL.md
- Process
- Process
- Process
- Illuminate\Database\Migrations\Migration
- Livewire Development
- Codebase Design
- Illuminate\Database\Eloquent\Model
- Codebase Design
- Codebase Design
- UserStatus.php
- During the session
- During the session
- During the session
- HTML Report Format
- Pest 5 Features
- HTML Report Format
- HTML Report Format
- .agents/skills/wizard/template.sh
- StatusRouter.php
- .claude/skills/wizard/template.sh
- .kilocode/skills/wizard/template.sh
- Laravel Fortify Development
- Ask Matt
- Ask Matt
- Ask Matt
- Diagnosing Bugs
- Laravel\Fortify\Features
- Diagnosing Bugs
- Diagnosing Bugs
- Tailwind CSS Development
- Test-Driven Development
- Process
- .agents/skills/writing-for-agents/SKILL.md
- Test-Driven Development
- Process
- .claude/skills/writing-for-agents/SKILL.md
- 3. Skema Database (Inti)
- Pelanggan
- Test-Driven Development
- Process
- .kilocode/skills/writing-for-agents/SKILL.md
- Detection Checklist
- Process
- Architecture Best Practices
- .agents/skills/wayfinder/SKILL.md
- .claude/skills/wayfinder/SKILL.md
- require-dev
- .kilocode/skills/wayfinder/SKILL.md
- Flux UI Development
- Queue & Job Best Practices
- Security Best Practices
- Advanced Query Patterns
- Database Performance Best Practices
- Events & Notifications Best Practices
- require
- Caching Best Practices
- Eloquent Best Practices
- Migration Best Practices
- .agents/skills/to-spec/SKILL.md
- PaketLayanan
- .claude/skills/to-spec/SKILL.md
- .kilocode/skills/to-spec/SKILL.md
- Process
- Blade & Views Best Practices
- Error Handling Best Practices
- Task Scheduling Best Practices
- Testing Best Practices
- <Questionnaire title>
- Process
- <Questionnaire title>
- composer.json
- Process
- <Questionnaire title>
- Collection Best Practices
- HTTP Client Best Practices
- Mail Best Practices
- Routing & Controllers Best Practices
- LayananPelanggan
- Conventions & Style
- Validation & Forms Best Practices
- Process
- Process
- config
- Router
- Process
- laravel-best-practices/SKILL.md
- Create
- security.blade.php
- psr-4
- laravel
- logging.php
- pelanggan/index.blade.php
- users-list.blade.php
- .agents/skills/diagnosing-bugs/scripts/hitl-loop.template.sh
- GLOSSARY.md Format
- .claude/skills/diagnosing-bugs/scripts/hitl-loop.template.sh
- GLOSSARY.md Format
- .kilocode/skills/diagnosing-bugs/scripts/hitl-loop.template.sh
- GLOSSARY.md Format
- Illuminate\Database\Seeder
- keywords
- UNMS Domain Context
- console.php
- profile.blade.php
- users/edit.blade.php
- rules/graphify.md
- workflows/graphify.md
- InvoicePdfController.php
- deleteRole({{ $role->id }})
- 0001-centralized-grouped-navigation-config.md
- header.blade.php
- sidebar.blade.php
- card.blade.php
- simple.blade.php
- split.blade.php
- Kota
- show.blade.php
- appearance.blade.php
- Index
- PRD Modul: Integrasi Payment Gateway Xendit
- .run
- Kecamatan
- TipePelanggan.php
- Customer Master Identity, Soft Deletes, and Sales Ownership Policy
- Perumahan
- Illuminate\Database\Eloquent\Factories\Factory
- User
- Security
- Promo/Create.php
- LayananPelanggan/Edit.php
- Package Bandwidth Modeling, Status Lifecycle, and Mikrotik Boundary Policy
- Pembayaran
- Create
- IpPool
- PelangganSeeder.php
- web.php
- Illuminate\Support\Str
- FortifyServiceProvider.php
- AppServiceProvider.php
- bootstrap/app.php
- AppServiceProvider
- ProfileValidationRules.php
- Index
- LaporanBillingExport
- router/index.blade.php
- Standardisasi Satuan Mbps pada Profil Bandwidth dan Sinkronisasi RouterOS
- Index
- paket-layanan/index.blade.php
- ProfilBandwidthSeeder.php
- Livewire\Attributes\Title
- CustomerPolicy
- layanan-pelanggan/index.blade.php
- ip-pool/index.blade.php
- profil-bandwidth/index.blade.php
- Billing.php
- Livewire\Component
- Promo
- Edit
- layanan-pelanggan/create.blade.php
- ip-pool/create.blade.php
- ip-pool/edit.blade.php
- Index
- promo/index.blade.php
- ADR 0004: Siklus Hidup Billing, Invoice, dan Pembayaran Manual
- invoice/index.blade.php
- invoice/show.blade.php
- exportExcel
- Index

## God Nodes (most connected - your core abstractions)
1. `User` - 136 edges
2. `Pelanggan` - 61 edges
3. `Invoice` - 54 edges
4. `Router` - 52 edges
5. `LayananPelanggan` - 48 edges
6. `PaketLayanan` - 43 edges
7. `ProfilBandwidth` - 40 edges
8. `Promo` - 37 edges
9. `RolesAndPermissionsSeeder` - 24 edges
10. `IpPool` - 22 edges

## Surprising Connections (you probably didn't know these)
- `InvoicePdfController` --inherits--> `Controller`  [EXTRACTED]
  app/Http/Controllers/InvoicePdfController.php → app/Http/Controllers/Controller.php
- `Show` --references--> `Invoice`  [EXTRACTED]
  app/Livewire/Invoice/Show.php → app/Models/Invoice.php
- `Edit` --references--> `Promo`  [EXTRACTED]
  app/Livewire/Promo/Edit.php → app/Models/Promo.php

## Import Cycles
- None detected.

## Communities (278 total, 36 thin omitted)

### Community 0 - "Illuminate\View\View"
Cohesion: 0.13
Nodes (6): Show, Create, Edit, Create, Edit, Illuminate\View\View

### Community 1 - "Create"
Cohesion: 0.13
Nodes (7): CheckExpiredInvoicesCommand, GenerateInvoicesCommand, Create, BillingService, Carbon, Command, Illuminate\Console\Command

### Community 2 - "Invoice"
Cohesion: 0.09
Nodes (5): Show, Invoice, LogOptions, PromoPenggunaan, Illuminate\Database\Eloquent\Relations\BelongsTo

### Community 3 - "scripts"
Cohesion: 0.06
Nodes (37): scripts, ci:check, dev, lint, lint:check, post-autoload-dump, post-create-project-cmd, post-root-package-install (+29 more)

### Community 4 - "Issue tracker: GitHub"
Cohesion: 0.06
Nodes (30): Before exploring, read these, Domain Docs, File structure, Flag ADR conflicts, Use the glossary's vocabulary, Conventions, Issue tracker: GitHub, Pull requests as a triage surface (+22 more)

### Community 5 - "PasswordValidationRules.php"
Cohesion: 0.15
Nodes (9): ResetUserPassword, Logout, DeleteUserForm, Illuminate\Contracts\Validation\ValidationRule, Illuminate\Http\RedirectResponse, Illuminate\Support\Facades\Session, Illuminate\Support\Facades\Validator, Laravel\Fortify\Contracts\ResetsUserPasswords (+1 more)

### Community 6 - "Issue tracker: GitHub"
Cohesion: 0.06
Nodes (30): Before exploring, read these, Domain Docs, File structure, Flag ADR conflicts, Use the glossary's vocabulary, Conventions, Issue tracker: GitHub, Pull requests as a triage surface (+22 more)

### Community 7 - "Issue tracker: GitHub"
Cohesion: 0.06
Nodes (30): Before exploring, read these, Domain Docs, File structure, Flag ADR conflicts, Use the glossary's vocabulary, Conventions, Issue tracker: GitHub, Pull requests as a triage surface (+22 more)

### Community 8 - "Triage"
Cohesion: 0.06
Nodes (29): Bad agent brief, Behavioral, not procedural, Complete acceptance criteria, Durability over precision, Examples, Explicit scope boundaries, Good agent brief (bug), Good agent brief (enhancement) (+21 more)

### Community 9 - "Triage"
Cohesion: 0.06
Nodes (29): Bad agent brief, Behavioral, not procedural, Complete acceptance criteria, Durability over precision, Examples, Explicit scope boundaries, Good agent brief (bug), Good agent brief (enhancement) (+21 more)

### Community 10 - "Triage"
Cohesion: 0.06
Nodes (29): Bad agent brief, Behavioral, not procedural, Complete acceptance criteria, Durability over precision, Examples, Explicit scope boundaries, Good agent brief (bug), Good agent brief (enhancement) (+21 more)

### Community 11 - "ProfilBandwidth"
Cohesion: 0.16
Nodes (3): LogOptions, ProfilBandwidth, ProfilBandwidthPolicy

### Community 12 - "AGENTS.md"
Cohesion: 0.07
Nodes (29): APIs & Eloquent Resources, Application Structure & Architecture, Artisan, Conventions, Deployment, Do Things the Laravel Way, Documentation Files, Foundational Context (+21 more)

### Community 13 - ".agents/skills/teach/SKILL.md"
Cohesion: 0.07
Nodes (25): Learning Record Format, Numbering, Optional sections, Supersession, Template, What does _not_ qualify, When to write a learning record, MISSION.md Format (+17 more)

### Community 14 - ".claude/skills/teach/SKILL.md"
Cohesion: 0.07
Nodes (25): Learning Record Format, Numbering, Optional sections, Supersession, Template, What does _not_ qualify, When to write a learning record, MISSION.md Format (+17 more)

### Community 15 - "dependencies"
Cohesion: 0.07
Nodes (28): concurrently, @laravel/multiplex, @laravel/passkeys, laravel-vite-plugin, lightningcss-linux-x64-gnu, dependencies, concurrently, @laravel/passkeys (+20 more)

### Community 16 - ".kilocode/skills/teach/SKILL.md"
Cohesion: 0.07
Nodes (25): Learning Record Format, Numbering, Optional sections, Supersession, Template, What does _not_ qualify, When to write a learning record, MISSION.md Format (+17 more)

### Community 17 - "Process"
Cohesion: 0.07
Nodes (25): 1. State the question, 2. Isolate the logic in a portable module, 3. Build the shareable HTML file, 4. Hand it over, 5. Capture the answer and the prototype, Anti-patterns, Logic Prototype, Process (+17 more)

### Community 18 - "Process"
Cohesion: 0.07
Nodes (25): 1. State the question, 2. Isolate the logic in a portable module, 3. Build the shareable HTML file, 4. Hand it over, 5. Capture the answer and the prototype, Anti-patterns, Logic Prototype, Process (+17 more)

### Community 19 - "Process"
Cohesion: 0.07
Nodes (25): 1. State the question, 2. Isolate the logic in a portable module, 3. Build the shareable HTML file, 4. Hand it over, 5. Capture the answer and the prototype, Anti-patterns, Logic Prototype, Process (+17 more)

### Community 20 - "Illuminate\Database\Migrations\Migration"
Cohesion: 0.05
Nodes (3): Illuminate\Database\Migrations\Migration, Illuminate\Database\Schema\Blueprint, Illuminate\Support\Facades\Schema

### Community 21 - "Livewire Development"
Cohesion: 0.08
Nodes (24): Component-Scoped Interceptors, Intercept Messages, Intercept Requests, Interceptor System (v4), Livewire 4 JavaScript Integration, Magic Properties, Alpine & JavaScript, Basic Usage (+16 more)

### Community 22 - "Codebase Design"
Cohesion: 0.09
Nodes (21): 1. In-process, 2. Local-substitutable, 3. Remote but owned (Ports & Adapters), 4. True external (Mock), Deepening, Dependency categories, Seam discipline, Testing strategy: replace, don't layer (+13 more)

### Community 23 - "Illuminate\Database\Eloquent\Model"
Cohesion: 0.29
Nodes (9): Illuminate\Database\Eloquent\Attributes\Fillable, Illuminate\Database\Eloquent\Collection, Illuminate\Database\Eloquent\Factories\HasFactory, Illuminate\Database\Eloquent\Model, Illuminate\Database\Eloquent\Relations\HasMany, Illuminate\Database\Eloquent\SoftDeletes, Illuminate\Support\Carbon, Spatie\Activitylog\Models\Concerns\LogsActivity (+1 more)

### Community 24 - "Codebase Design"
Cohesion: 0.09
Nodes (21): 1. In-process, 2. Local-substitutable, 3. Remote but owned (Ports & Adapters), 4. True external (Mock), Deepening, Dependency categories, Seam discipline, Testing strategy: replace, don't layer (+13 more)

### Community 25 - "Codebase Design"
Cohesion: 0.09
Nodes (21): 1. In-process, 2. Local-substitutable, 3. Remote but owned (Ports & Adapters), 4. True external (Mock), Deepening, Dependency categories, Seam discipline, Testing strategy: replace, don't layer (+13 more)

### Community 26 - "UserStatus.php"
Cohesion: 0.20
Nodes (7): RolesAndPermissionsSeeder, Illuminate\Foundation\Testing\RefreshDatabase, Illuminate\Support\Facades\Crypt, Illuminate\Support\Facades\DB, Livewire\Livewire, Spatie\Activitylog\Models\Activity, Spatie\Permission\PermissionRegistrar

### Community 27 - "During the session"
Cohesion: 0.09
Nodes (19): ADR Format, Numbering, Optional sections, Template, What qualifies, When to offer an ADR, CONTEXT.md Format, Rules (+11 more)

### Community 28 - "During the session"
Cohesion: 0.09
Nodes (19): ADR Format, Numbering, Optional sections, Template, What qualifies, When to offer an ADR, CONTEXT.md Format, Rules (+11 more)

### Community 29 - "During the session"
Cohesion: 0.09
Nodes (19): ADR Format, Numbering, Optional sections, Template, What qualifies, When to offer an ADR, CONTEXT.md Format, Rules (+11 more)

### Community 30 - "HTML Report Format"
Cohesion: 0.10
Nodes (18): Call-graph collapse, Candidate card, Cross-section (good for layered shallowness), Diagram patterns, Hand-built boxes-and-arrows (when Mermaid's layout fights you), Header, HTML Report Format, Mass diagram (good for "interface as wide as implementation") (+10 more)

### Community 31 - "Pest 5 Features"
Cohesion: 0.10
Nodes (19): Architecture Testing, Assertions, Basic Test Structure, Basic Usage, Browser Test Example, Common Pitfalls, Creating Tests, Datasets (+11 more)

### Community 32 - "HTML Report Format"
Cohesion: 0.10
Nodes (18): Call-graph collapse, Candidate card, Cross-section (good for layered shallowness), Diagram patterns, Hand-built boxes-and-arrows (when Mermaid's layout fights you), Header, HTML Report Format, Mass diagram (good for "interface as wide as implementation") (+10 more)

### Community 33 - "HTML Report Format"
Cohesion: 0.10
Nodes (18): Call-graph collapse, Candidate card, Cross-section (good for layered shallowness), Diagram patterns, Hand-built boxes-and-arrows (when Mermaid's layout fights you), Header, HTML Report Format, Mass diagram (good for "interface as wide as implementation") (+10 more)

### Community 34 - ".agents/skills/wizard/template.sh"
Cohesion: 0.22
Nodes (16): ask(), ask_secret(), banner(), _clear(), finish(), note(), open_url(), pause() (+8 more)

### Community 36 - ".claude/skills/wizard/template.sh"
Cohesion: 0.22
Nodes (16): ask(), ask_secret(), banner(), _clear(), finish(), note(), open_url(), pause() (+8 more)

### Community 37 - ".kilocode/skills/wizard/template.sh"
Cohesion: 0.22
Nodes (16): ask(), ask_secret(), banner(), _clear(), finish(), note(), open_url(), pause() (+8 more)

### Community 38 - "Laravel Fortify Development"
Cohesion: 0.12
Nodes (16): Available Features, Best Practices, Custom Authentication Logic, Documentation, Email Verification Setup, Key Endpoints, Laravel Fortify Development, Passkeys Setup (+8 more)

### Community 39 - "Ask Matt"
Cohesion: 0.12
Nodes (14): Phase boundaries, Primary and secondary sources, The five options, The tree, These are judgement calls, Ask Matt, Codebase health, Context hygiene (+6 more)

### Community 40 - "Ask Matt"
Cohesion: 0.12
Nodes (14): Phase boundaries, Primary and secondary sources, The five options, The tree, These are judgement calls, Ask Matt, Codebase health, Context hygiene (+6 more)

### Community 41 - "Ask Matt"
Cohesion: 0.12
Nodes (14): Phase boundaries, Primary and secondary sources, The five options, The tree, These are judgement calls, Ask Matt, Codebase health, Context hygiene (+6 more)

### Community 42 - "Diagnosing Bugs"
Cohesion: 0.13
Nodes (14): Completion criterion — a tight loop that goes red, Diagnosing Bugs, Minimise, Non-deterministic bugs, Phase 1 — Build a feedback loop, Phase 2 — Reproduce + minimise, Phase 3 — Hypothesise, Phase 4 — Instrument (+6 more)

### Community 43 - "Laravel\Fortify\Features"
Cohesion: 0.10
Nodes (8): AuthenticateUser, Illuminate\Auth\Notifications\ResetPassword, Illuminate\Foundation\Testing\TestCase, Illuminate\Support\Facades\Hash, Illuminate\Support\Facades\Notification, Illuminate\Validation\ValidationException, Laravel\Fortify\Features, TestCase

### Community 44 - "Diagnosing Bugs"
Cohesion: 0.13
Nodes (14): Completion criterion — a tight loop that goes red, Diagnosing Bugs, Minimise, Non-deterministic bugs, Phase 1 — Build a feedback loop, Phase 2 — Reproduce + minimise, Phase 3 — Hypothesise, Phase 4 — Instrument (+6 more)

### Community 45 - "Diagnosing Bugs"
Cohesion: 0.13
Nodes (14): Completion criterion — a tight loop that goes red, Diagnosing Bugs, Minimise, Non-deterministic bugs, Phase 1 — Build a feedback loop, Phase 2 — Reproduce + minimise, Phase 3 — Hypothesise, Phase 4 — Instrument (+6 more)

### Community 46 - "Tailwind CSS Development"
Cohesion: 0.14
Nodes (13): Basic Usage, Common Patterns, Common Pitfalls, CSS-First Configuration, Dark Mode, Documentation, Flexbox Layout, Grid Layout (+5 more)

### Community 47 - "Test-Driven Development"
Cohesion: 0.15
Nodes (10): Designing for Mockability, When to Mock, Anti-patterns, Rules of the loop, Seams — where tests go, Test-Driven Development, What a good test is, Bad Tests (+2 more)

### Community 48 - "Process"
Cohesion: 0.15
Nodes (12): 1. Gather context, 2. Explore the codebase (optional), 3. Draft vertical slices, 4. Quiz the user, 5. Publish the tickets to the configured tracker, Acceptance criteria, Blocked by, <NN> — <Ticket title> (+4 more)

### Community 49 - ".agents/skills/writing-for-agents/SKILL.md"
Cohesion: 0.15
Nodes (11): Context pointers, Information hierarchy, Leading words, Invocation, Router skills, Skill mechanics, Splitting by invocation, Pruning (+3 more)

### Community 50 - "Test-Driven Development"
Cohesion: 0.15
Nodes (10): Designing for Mockability, When to Mock, Anti-patterns, Rules of the loop, Seams — where tests go, Test-Driven Development, What a good test is, Bad Tests (+2 more)

### Community 51 - "Process"
Cohesion: 0.15
Nodes (12): 1. Gather context, 2. Explore the codebase (optional), 3. Draft vertical slices, 4. Quiz the user, 5. Publish the tickets to the configured tracker, Acceptance criteria, Blocked by, <NN> — <Ticket title> (+4 more)

### Community 52 - ".claude/skills/writing-for-agents/SKILL.md"
Cohesion: 0.15
Nodes (11): Context pointers, Information hierarchy, Leading words, Invocation, Router skills, Skill mechanics, Splitting by invocation, Pruning (+3 more)

### Community 53 - "3. Skema Database (Inti)"
Cohesion: 0.07
Nodes (28): 1. Ringkasan & Batasan Sistem, 2. Role & Hak Akses, 3.1 Wilayah & Lokasi, 3.2 Pelanggan, 3.3 Perangkat Jaringan, 3.4 Layanan & Billing, 3.5 Payment Gateway (Xendit), 3.6 Promo (+20 more)

### Community 54 - "Pelanggan"
Cohesion: 0.06
Nodes (6): Edit, Pelanggan, LogOptions, AkunPelangganFactory, Illuminate\Database\Eloquent\Builder, Illuminate\Database\Eloquent\Relations\HasOne

### Community 55 - "Test-Driven Development"
Cohesion: 0.15
Nodes (10): Designing for Mockability, When to Mock, Anti-patterns, Rules of the loop, Seams — where tests go, Test-Driven Development, What a good test is, Bad Tests (+2 more)

### Community 56 - "Process"
Cohesion: 0.15
Nodes (12): 1. Gather context, 2. Explore the codebase (optional), 3. Draft vertical slices, 4. Quiz the user, 5. Publish the tickets to the configured tracker, Acceptance criteria, Blocked by, <NN> — <Ticket title> (+4 more)

### Community 57 - ".kilocode/skills/writing-for-agents/SKILL.md"
Cohesion: 0.15
Nodes (11): Context pointers, Information hierarchy, Leading words, Invocation, Router skills, Skill mechanics, Splitting by invocation, Pruning (+3 more)

### Community 58 - "Detection Checklist"
Cohesion: 0.17
Nodes (11): A. Validation & HTTP input, B. Controllers & routing, C. Authorization, D. Eloquent & models, Detection Checklist, E. Architecture & organization, F. Frontend & views, G. Database & migrations (+3 more)

### Community 59 - "Process"
Cohesion: 0.17
Nodes (11): Edge cases, Glob mapping, Ground Rules (read before you start), Infer Conventions, Process, Step 0: Orient, Step 1: Predefined sweep, Step 2: Open-ended pass (+3 more)

### Community 60 - "Architecture Best Practices"
Cohesion: 0.17
Nodes (11): Architecture Best Practices, Code to Interfaces, Convention Over Configuration, Default Sort by Descending, Single-Purpose Action Classes, Use Atomic Locks for Race Conditions, Use `Concurrency::run()` for Parallel Execution, Use `Context` for Request-Scoped Data (+3 more)

### Community 61 - ".agents/skills/wayfinder/SKILL.md"
Cohesion: 0.17
Nodes (11): Chart the map, Fog of war, Invocation, Out of scope, Plan, don't do, Refer by name, The Map, The map body (+3 more)

### Community 62 - ".claude/skills/wayfinder/SKILL.md"
Cohesion: 0.17
Nodes (11): Chart the map, Fog of war, Invocation, Out of scope, Plan, don't do, Refer by name, The Map, The map body (+3 more)

### Community 63 - "require-dev"
Cohesion: 0.17
Nodes (12): require-dev, fakerphp/faker, larastan/larastan, laravel/boost, laravel/pail, laravel/pao, laravel/pint, laravel/sail (+4 more)

### Community 64 - ".kilocode/skills/wayfinder/SKILL.md"
Cohesion: 0.17
Nodes (11): Chart the map, Fog of war, Invocation, Out of scope, Plan, don't do, Refer by name, The Map, The map body (+3 more)

### Community 65 - "Flux UI Development"
Cohesion: 0.18
Nodes (10): Available Components (Free Edition), Basic Usage, Common Patterns, Common Pitfalls, Documentation, Flux UI Development, Form Fields, Icons (+2 more)

### Community 66 - "Queue & Job Best Practices"
Cohesion: 0.18
Nodes (10): Always Implement `failed()`, Batch Related Jobs, Implement `ShouldBeUnique`, Queue & Job Best Practices, Rate Limit External API Calls in Jobs, `retryUntil()` Needs `$tries = 0`, Set `retry_after` Greater Than `timeout`, Use Exponential Backoff (+2 more)

### Community 67 - "Security Best Practices"
Cohesion: 0.17
Nodes (11): Audit Dependencies, Authorize Every Action, CSRF Protection, Encrypt Sensitive Database Fields, Escape Output to Prevent XSS, Keep Secrets Out of Code, Mass Assignment Protection, Prevent SQL Injection (+3 more)

### Community 68 - "Advanced Query Patterns"
Cohesion: 0.20
Nodes (9): Advanced Query Patterns, Create Dynamic Relationships via Subquery FK, Prefer `whereIn` + Subquery Over `whereHas`, Sometimes Two Simple Queries Beat One Complex Query, Use `addSelect()` Subqueries for Single Values from Has-Many, Use Compound Indexes Matching `orderBy` Column Order, Use Conditional Aggregates Instead of Multiple Count Queries, Use Correlated Subqueries for Has-Many Ordering (+1 more)

### Community 69 - "Database Performance Best Practices"
Cohesion: 0.20
Nodes (9): Add Database Indexes, Always Eager Load Relationships, Chunk Large Datasets, Database Performance Best Practices, No Queries in Blade Templates, Prevent Lazy Loading in Development, Select Only Needed Columns, Use `cursor()` for Memory-Efficient Iteration (+1 more)

### Community 70 - "Events & Notifications Best Practices"
Cohesion: 0.20
Nodes (9): Always Queue Notifications, Events & Notifications Best Practices, Implement `HasLocalePreference` on Notifiable Models, Rely on Event Discovery, Route Notification Channels to Dedicated Queues, Run `event:cache` in Production Deploy, Use `afterCommit()` on Notifications in Transactions, Use On-Demand Notifications for Non-User Recipients (+1 more)

### Community 71 - "require"
Cohesion: 0.13
Nodes (15): require, barryvdh/laravel-dompdf, evilfreelancer/routeros-api-php, laravel/chisel, laravel/fortify, laravel/framework, laravel/tinker, livewire/blaze (+7 more)

### Community 72 - "Caching Best Practices"
Cohesion: 0.22
Nodes (8): Caching Best Practices, Configure Failover Cache Stores in Production, Use `Cache::add()` for Atomic Conditional Writes, Use `Cache::flexible()` for Stale-While-Revalidate, Use `Cache::memo()` to Avoid Redundant Hits Within a Request, Use `Cache::remember()` Instead of Manual Get/Put, Use Cache Tags to Invalidate Related Groups, Use `once()` for Per-Request Memoization

### Community 73 - "Eloquent Best Practices"
Cohesion: 0.22
Nodes (8): Apply Global Scopes Sparingly, Avoid Hardcoded Table Names in Queries, Cast Date Columns Properly, Define Attribute Casts, Eloquent Best Practices, Use Correct Relationship Types, Use Local Scopes for Reusable Queries, Use `whereBelongsTo()` for Relationship Queries

### Community 74 - "Migration Best Practices"
Cohesion: 0.22
Nodes (8): Add Indexes in the Migration, Generate Migrations with Artisan, Keep Migrations Focused, Migration Best Practices, Mirror Defaults in Model `$attributes`, Never Modify Deployed Migrations, Use `constrained()` for Foreign Keys, Write Reversible `down()` Methods by Default

### Community 75 - ".agents/skills/to-spec/SKILL.md"
Cohesion: 0.22
Nodes (8): Further Notes, Implementation Decisions, Out of Scope, Problem Statement, Process, Solution, Testing Decisions, User Stories

### Community 76 - "PaketLayanan"
Cohesion: 0.06
Nodes (7): Create, Create, Edit, Index, PaketLayanan, LogOptions, PaketLayananPolicy

### Community 77 - ".claude/skills/to-spec/SKILL.md"
Cohesion: 0.22
Nodes (8): Further Notes, Implementation Decisions, Out of Scope, Problem Statement, Process, Solution, Testing Decisions, User Stories

### Community 78 - ".kilocode/skills/to-spec/SKILL.md"
Cohesion: 0.22
Nodes (8): Further Notes, Implementation Decisions, Out of Scope, Problem Statement, Process, Solution, Testing Decisions, User Stories

### Community 79 - "Process"
Cohesion: 0.25
Nodes (7): 1. Pin the fixed point, 2. Identify the spec source, 3. Identify the standards sources, 4. Spawn both sub-agents in parallel, 5. Aggregate, Process, Why two axes

### Community 80 - "Blade & Views Best Practices"
Cohesion: 0.25
Nodes (7): Blade & Views Best Practices, Prefer Blade Components Over `@include`, Use `$attributes->merge()` in Component Templates, Use `@aware` for Deeply Nested Component Props, Use Blade Fragments for Partial Re-Renders (htmx/Turbo), Use `@pushOnce` for Per-Component Scripts, Use View Composers for Shared View Data

### Community 81 - "Error Handling Best Practices"
Cohesion: 0.25
Nodes (7): Add Context to Exception Classes, Enable `dontReportDuplicates()`, Error Handling Best Practices, Exception Reporting and Rendering, Force JSON Error Rendering for API Routes, Throttle High-Volume Exceptions, Use `ShouldntReport` for Exceptions That Should Never Log

### Community 82 - "Task Scheduling Best Practices"
Cohesion: 0.25
Nodes (7): Task Scheduling Best Practices, Use `environments()` to Restrict Tasks, Use `onOneServer()` on Multi-Server Deployments, Use `runInBackground()` for Concurrent Long Tasks, Use Schedule Groups for Shared Configuration, Use `takeUntilTimeout()` for Time-Bounded Processing, Use `withoutOverlapping()` on Variable-Duration Tasks

### Community 83 - "Testing Best Practices"
Cohesion: 0.25
Nodes (7): Call `Event::fake()` After Factory Setup, Testing Best Practices, Use `Exceptions::fake()` to Assert Exception Reporting, Use Factory States and Sequences, Use `LazilyRefreshDatabase` Over `RefreshDatabase`, Use Model Assertions Over Raw Database Assertions, Use `recycle()` to Share Relationship Instances Across Factories

### Community 84 - "<Questionnaire title>"
Cohesion: 0.25
Nodes (7): Anything else?, Context, Document structure, How to answer, <Questionnaire title>, <Theme heading>, What load is the system expected to handle at launch?

### Community 85 - "Process"
Cohesion: 0.25
Nodes (7): 1. Pin the fixed point, 2. Identify the spec source, 3. Identify the standards sources, 4. Spawn both sub-agents in parallel, 5. Aggregate, Process, Why two axes

### Community 86 - "<Questionnaire title>"
Cohesion: 0.25
Nodes (7): Anything else?, Context, Document structure, How to answer, <Questionnaire title>, <Theme heading>, What load is the system expected to handle at launch?

### Community 87 - "composer.json"
Cohesion: 0.18
Nodes (10): autoload-dev, psr-4, description, license, minimum-stability, name, prefer-stable, Tests\\ (+2 more)

### Community 88 - "Process"
Cohesion: 0.25
Nodes (7): 1. Pin the fixed point, 2. Identify the spec source, 3. Identify the standards sources, 4. Spawn both sub-agents in parallel, 5. Aggregate, Process, Why two axes

### Community 89 - "<Questionnaire title>"
Cohesion: 0.25
Nodes (7): Anything else?, Context, Document structure, How to answer, <Questionnaire title>, <Theme heading>, What load is the system expected to handle at launch?

### Community 90 - "Collection Best Practices"
Cohesion: 0.29
Nodes (6): Choose `cursor()` vs. `lazy()` Correctly, Collection Best Practices, Use `#[CollectedBy]` for Custom Collection Classes, Use Higher-Order Messages for Simple Operations, Use `lazyById()` When Updating Records While Iterating, Use `toQuery()` for Bulk Operations on Collections

### Community 91 - "HTTP Client Best Practices"
Cohesion: 0.29
Nodes (6): Always Set Explicit Timeouts, Fake HTTP Calls in Tests, Handle Errors Explicitly, HTTP Client Best Practices, Use Request Pooling for Concurrent Requests, Use Retry with Backoff for External APIs

### Community 92 - "Mail Best Practices"
Cohesion: 0.29
Nodes (6): Implement `ShouldQueue` on the Mailable Class, Mail Best Practices, Separate Content Tests from Sending Tests, Use `afterCommit()` on Mailables Inside Transactions, Use `assertQueued()` Not `assertSent()` for Queued Mailables, Use Markdown Mailables for Transactional Emails

### Community 93 - "Routing & Controllers Best Practices"
Cohesion: 0.29
Nodes (6): Keep Controllers Thin, Routing & Controllers Best Practices, Type-Hint Form Requests, Use Implicit Route Model Binding, Use Resource Controllers, Use Scoped Bindings for Nested Resources

### Community 94 - "LayananPelanggan"
Cohesion: 0.07
Nodes (7): Edit, Index, LayananPelanggan, LogOptions, LayananPelangganPolicy, InvoiceFactory, static

### Community 95 - "Conventions & Style"
Cohesion: 0.29
Nodes (6): Conventions & Style, Follow Laravel Naming Conventions, No Inline JS/CSS in Blade, No Unnecessary Comments, Prefer Shorter Readable Syntax, Use Laravel String & Array Helpers

### Community 96 - "Validation & Forms Best Practices"
Cohesion: 0.29
Nodes (6): Always Use `validated()`, Array vs. String Notation for Rules, Use Form Request Classes, Use `Rule::when()` for Conditional Validation, Use the `after()` Method for Custom Validation, Validation & Forms Best Practices

### Community 97 - "Process"
Cohesion: 0.29
Nodes (6): 1. Scope the procedure, 2. Map each stage's journey, 3. Author the wizard, 4. Verify and hand off, Process, Wizard

### Community 98 - "Process"
Cohesion: 0.29
Nodes (6): 1. Scope the procedure, 2. Map each stage's journey, 3. Author the wizard, 4. Verify and hand off, Process, Wizard

### Community 99 - "config"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 100 - "Router"
Cohesion: 0.09
Nodes (5): Edit, Index, LogOptions, Router, RouterPolicy

### Community 101 - "Process"
Cohesion: 0.29
Nodes (6): 1. Scope the procedure, 2. Map each stage's journey, 3. Author the wizard, 4. Verify and hand off, Process, Wizard

### Community 102 - "laravel-best-practices/SKILL.md"
Cohesion: 0.17
Nodes (10): Configuration Best Practices, `env()` Only in Config Files, Use `App::environment()` for Environment Checks, Use Constants and Language Files, Use Encrypted Env or External Secrets, Consistency First, Decision Rules, How to Apply (+2 more)

### Community 104 - "security.blade.php"
Cohesion: 0.40
Nodes (4): closeDeleteModal, confirmDelete({{ $passkey[, deletePasskey, partials.settings-heading

### Community 105 - "psr-4"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 106 - "laravel"
Cohesion: 0.40
Nodes (5): extra, laravel, post-create-project, dont-discover, installer

### Community 107 - "logging.php"
Cohesion: 0.40
Nodes (4): Monolog\Handler\NullHandler, Monolog\Handler\StreamHandler, Monolog\Handler\SyslogUdpHandler, Monolog\Processor\PsrLogMessageProcessor

### Community 108 - "pelanggan/index.blade.php"
Cohesion: 0.40
Nodes (4): confirmDelete({{ $pelanggan->id }}), deleteCustomer, $set(, toggleStatus({{ $pelanggan->id }})

### Community 109 - "users-list.blade.php"
Cohesion: 0.40
Nodes (4): confirmDelete({{ $user->id }}), deleteUser, $set(, save

### Community 110 - ".agents/skills/diagnosing-bugs/scripts/hitl-loop.template.sh"
Cohesion: 0.83
Nodes (3): capture(), hitl-loop.template.sh script, step()

### Community 111 - "GLOSSARY.md Format"
Cohesion: 0.50
Nodes (3): GLOSSARY.md Format, Rules, Structure

### Community 112 - ".claude/skills/diagnosing-bugs/scripts/hitl-loop.template.sh"
Cohesion: 0.83
Nodes (3): capture(), hitl-loop.template.sh script, step()

### Community 113 - "GLOSSARY.md Format"
Cohesion: 0.50
Nodes (3): GLOSSARY.md Format, Rules, Structure

### Community 114 - ".kilocode/skills/diagnosing-bugs/scripts/hitl-loop.template.sh"
Cohesion: 0.83
Nodes (3): capture(), hitl-loop.template.sh script, step()

### Community 115 - "GLOSSARY.md Format"
Cohesion: 0.50
Nodes (3): GLOSSARY.md Format, Rules, Structure

### Community 116 - "Illuminate\Database\Seeder"
Cohesion: 0.19
Nodes (4): DatabaseSeeder, PaketLayananSeeder, PromoSeeder, Illuminate\Database\Seeder

### Community 117 - "keywords"
Cohesion: 0.67
Nodes (3): keywords, framework, laravel

### Community 119 - "console.php"
Cohesion: 0.50
Nodes (3): Illuminate\Foundation\Inspiring, Illuminate\Support\Facades\Artisan, Illuminate\Support\Facades\Schedule

### Community 124 - "InvoicePdfController.php"
Cohesion: 0.29
Nodes (5): Controller, InvoicePdfController, Barryvdh\DomPDF\Facade\Pdf, Illuminate\Http\Response, Illuminate\Support\Facades\Gate

### Community 132 - "Kota"
Cohesion: 0.12
Nodes (4): Create, Edit, Index, Kota

### Community 192 - "PRD Modul: Integrasi Payment Gateway Xendit"
Cohesion: 0.13
Nodes (14): 1. Tujuan Modul, 2. Konfigurasi & Environment, 3. Struktur Kode, 4.1 `XenditPaymentService`, 4.2 `XenditWebhookVerifier`, 4.3 Route & middleware, 4.4 `XenditVirtualAccountWebhookController::handle()`, 4.5 `XenditQrisWebhookController::handle()` (+6 more)

### Community 204 - ".run"
Cohesion: 0.24
Nodes (6): AkunPelanggan, PelangganSeeder, Carbon, Illuminate\Foundation\Auth\User, MetodePembayaran, StatusInvoice

### Community 205 - "Kecamatan"
Cohesion: 0.17
Nodes (4): Index, Kecamatan, Kelurahan, WilayahSeeder

### Community 207 - "Customer Master Identity, Soft Deletes, and Sales Ownership Policy"
Cohesion: 0.50
Nodes (3): Context, Customer Master Identity, Soft Deletes, and Sales Ownership Policy, Decisions

### Community 208 - "Perumahan"
Cohesion: 0.09
Nodes (7): Index, Odp, OdpPort, Perumahan, OdpFactory, OdpPortFactory, OdpSeeder

### Community 209 - "Illuminate\Database\Eloquent\Factories\Factory"
Cohesion: 0.08
Nodes (13): IpPoolFactory, LayananPelangganFactory, static, PaketLayananFactory, static, PembayaranFactory, ProfilBandwidthFactory, PromoFactory (+5 more)

### Community 210 - "User"
Cohesion: 0.06
Nodes (12): Edit, UsersList, User, InvoicePolicy, PelangganPolicy, PembayaranPolicy, WilayahPolicy, Illuminate\Database\Eloquent\Attributes\Hidden (+4 more)

### Community 211 - "Security"
Cohesion: 0.18
Nodes (5): Profile, Security, Illuminate\Support\Facades\Route, Laravel\Fortify\Actions\DisableTwoFactorAuthentication, Laravel\Passkeys\Actions\DeletePasskey

### Community 214 - "Package Bandwidth Modeling, Status Lifecycle, and Mikrotik Boundary Policy"
Cohesion: 0.50
Nodes (3): Context, Decisions, Package Bandwidth Modeling, Status Lifecycle, and Mikrotik Boundary Policy

### Community 217 - "IpPool"
Cohesion: 0.07
Nodes (7): Create, Edit, Index, IpPool, IpPoolPolicy, IpNetworkHelper, IpPoolSeeder

### Community 219 - "web.php"
Cohesion: 0.14
Nodes (13): App\Livewire\Invoice, App\Livewire\IpPool, App\Livewire\Laporan, App\Livewire\LayananPelanggan, App\Livewire\PaketLayanan, App\Livewire\Pelanggan, App\Livewire\Pembayaran, App\Livewire\ProfilBandwidth (+5 more)

### Community 220 - "Illuminate\Support\Str"
Cohesion: 0.09
Nodes (11): Create, Edit, Index, Create, Illuminate\Support\Str, Livewire\Attributes\Validate, Pdo\Mysql, Role (+3 more)

### Community 221 - "FortifyServiceProvider.php"
Cohesion: 0.25
Nodes (5): FortifyServiceProvider, Illuminate\Cache\RateLimiting\Limit, Illuminate\Support\Facades\RateLimiter, Illuminate\Support\ServiceProvider, Laravel\Fortify\Fortify

### Community 222 - "AppServiceProvider.php"
Cohesion: 0.28
Nodes (6): RecordLastLoginAt, Carbon\CarbonImmutable, Illuminate\Auth\Events\Login, Illuminate\Support\Facades\Date, Illuminate\Support\Facades\Event, Illuminate\Validation\Rules\Password

### Community 223 - "bootstrap/app.php"
Cohesion: 0.25
Nodes (7): Illuminate\Foundation\Application, Illuminate\Foundation\Configuration\Exceptions, Illuminate\Foundation\Configuration\Middleware, Illuminate\Http\Request, Spatie\Permission\Middleware\PermissionMiddleware, Spatie\Permission\Middleware\RoleMiddleware, Spatie\Permission\Middleware\RoleOrPermissionMiddleware

### Community 225 - "ProfileValidationRules.php"
Cohesion: 0.31
Nodes (7): CreateNewUser, emailRules(), nameRules(), phoneRules(), profileRules(), Illuminate\Validation\Rule, Laravel\Fortify\Contracts\CreatesNewUsers

### Community 227 - "LaporanBillingExport"
Cohesion: 0.27
Nodes (6): LaporanBillingExport, Illuminate\Support\Collection, Maatwebsite\Excel\Concerns\FromCollection, Maatwebsite\Excel\Concerns\ShouldAutoSize, Maatwebsite\Excel\Concerns\WithHeadings, Maatwebsite\Excel\Concerns\WithMapping

### Community 228 - "router/index.blade.php"
Cohesion: 0.40
Nodes (4): confirmDelete({{ $router->id }}), deleteRouter, $set(, toggleStatus({{ $router->id }})

### Community 229 - "Standardisasi Satuan Mbps pada Profil Bandwidth dan Sinkronisasi RouterOS"
Cohesion: 0.33
Nodes (5): Consequences, Context, Decisions, Standardisasi Satuan Mbps pada Profil Bandwidth dan Sinkronisasi RouterOS, Status

### Community 231 - "paket-layanan/index.blade.php"
Cohesion: 0.40
Nodes (4): confirmDelete({{ $paket->id }}), deletePaket, $set(, toggleStatus({{ $paket->id }})

### Community 233 - "Livewire\Attributes\Title"
Cohesion: 0.17
Nodes (7): Index, Flux\Flux, Illuminate\Contracts\Pagination\LengthAwarePaginator, Livewire\Attributes\Layout, Livewire\Attributes\Title, Livewire\Attributes\Url, Livewire\WithPagination

### Community 235 - "layanan-pelanggan/index.blade.php"
Cohesion: 0.50
Nodes (3): confirmDelete({{ $layanan->id }}), deleteLayanan, $set(

### Community 236 - "ip-pool/index.blade.php"
Cohesion: 0.50
Nodes (3): confirmDelete({{ $pool->id }}), deleteIpPool, $set(

### Community 237 - "profil-bandwidth/index.blade.php"
Cohesion: 0.50
Nodes (3): confirmDelete({{ $profil->id }}), deleteProfilBandwidth, $set(

### Community 239 - "Billing.php"
Cohesion: 0.33
Nodes (3): Billing, Maatwebsite\Excel\Facades\Excel, Symfony\Component\HttpFoundation\BinaryFileResponse

### Community 240 - "Livewire\Component"
Cohesion: 0.13
Nodes (6): Create, Appearance, Create, Edit, Illuminate\Support\Facades\Auth, Livewire\Component

### Community 241 - "Promo"
Cohesion: 0.11
Nodes (5): Edit, Index, LogOptions, Promo, PromoPolicy

### Community 268 - "promo/index.blade.php"
Cohesion: 0.40
Nodes (4): confirmDelete({{ $promo->id }}), deletePromo, $set(, toggleStatus({{ $promo->id }})

### Community 269 - "ADR 0004: Siklus Hidup Billing, Invoice, dan Pembayaran Manual"
Cohesion: 0.40
Nodes (4): ADR 0004: Siklus Hidup Billing, Invoice, dan Pembayaran Manual, Keputusan yang Diambil, Konsekuensi, Konteks

### Community 270 - "invoice/index.blade.php"
Cohesion: 0.50
Nodes (3): confirmDelete({{ $inv->id }}), deleteInvoice, $set(

## Knowledge Gaps
- **1066 isolated node(s):** `$schema`, `name`, `type`, `description`, `laravel` (+1061 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **36 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `User` connect `User` to `Create`, `PasswordValidationRules.php`, `ProfilBandwidth`, `Illuminate\Database\Eloquent\Model`, `UserStatus.php`, `Laravel\Fortify\Features`, `Pelanggan`, `.run`, `PaketLayanan`, `TipePelanggan.php`, `IpPool`, `PelangganSeeder.php`, `Illuminate\Support\Str`, `LayananPelanggan`, `ProfileValidationRules.php`, `Router`, `Livewire\Attributes\Title`, `CustomerPolicy`, `Promo`?**
  _High betweenness centrality (0.031) - this node is a cross-community bridge._
- **Why does `Invoice` connect `Invoice` to `Create`, `LaporanBillingExport`, `UserStatus.php`, `Livewire\Attributes\Title`, `.run`, `Billing.php`, `Illuminate\Database\Eloquent\Factories\Factory`, `User`, `Pelanggan`, `Illuminate\Database\Eloquent\Model`, `LayananPelanggan`, `PelangganSeeder.php`, `InvoicePdfController.php`, `Index`?**
  _High betweenness centrality (0.015) - this node is a cross-community bridge._
- **Why does `Router` connect `Router` to `StatusRouter.php`, `UserStatus.php`, `Create`, `Livewire\Attributes\Title`, `PaketLayanan`, `.run`, `Illuminate\Database\Eloquent\Factories\Factory`, `LayananPelanggan/Edit.php`, `Pelanggan`, `Illuminate\Database\Eloquent\Model`, `IpPool`, `PelangganSeeder.php`, `LayananPelanggan`?**
  _High betweenness centrality (0.012) - this node is a cross-community bridge._
- **What connects `$schema`, `name`, `type` to the rest of the system?**
  _1066 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Illuminate\View\View` be split into smaller, more focused modules?**
  _Cohesion score 0.12631578947368421 - nodes in this community are weakly interconnected._
- **Should `Create` be split into smaller, more focused modules?**
  _Cohesion score 0.13157894736842105 - nodes in this community are weakly interconnected._
- **Should `Invoice` be split into smaller, more focused modules?**
  _Cohesion score 0.08620689655172414 - nodes in this community are weakly interconnected._