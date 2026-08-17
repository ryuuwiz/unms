# Graph Report - unms  (2026-08-16)

## Corpus Check
- 338 files · ~144,773 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 1998 nodes · 2365 edges · 215 communities (195 shown, 20 thin omitted)
- Extraction: 100% EXTRACTED · 0% INFERRED · 0% AMBIGUOUS
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `df4192af`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- Livewire\Component
- Customer
- User
- scripts
- Issue tracker: GitHub
- PasswordValidationRules.php
- Issue tracker: GitHub
- Issue tracker: GitHub
- Triage
- Triage
- Triage
- Create
- Laravel Boost Guidelines
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
- AuditLogger
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
- Illuminate\Support\Str
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
- PRD #0: Dashboard Shell & UI Foundation
- PRD #1: Users & Roles Management
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
- Security
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
- laravel-best-practices/SKILL.md
- Conventions & Style
- Validation & Forms Best Practices
- Process
- Process
- config
- Illuminate\Database\Seeder
- Process
- Configuration Best Practices
- PRD #2: Customer Management
- security.blade.php
- psr-4
- laravel
- logging.php
- customers/index.blade.php
- users-list.blade.php
- .agents/skills/diagnosing-bugs/scripts/hitl-loop.template.sh
- GLOSSARY.md Format
- .claude/skills/diagnosing-bugs/scripts/hitl-loop.template.sh
- GLOSSARY.md Format
- .kilocode/skills/diagnosing-bugs/scripts/hitl-loop.template.sh
- GLOSSARY.md Format
- autoload-dev
- keywords
- UNMS Domain Context
- console.php
- profile.blade.php
- users/edit.blade.php
- rules/graphify.md
- workflows/graphify.md
- Controller.php
- deleteRole({{ $role->id }})
- 0001-centralized-grouped-navigation-config.md
- header.blade.php
- sidebar.blade.php
- card.blade.php
- simple.blade.php
- split.blade.php
- customers/edit.blade.php
- show.blade.php
- appearance.blade.php
- Package
- PRD #3: Package Management
- FortifyServiceProvider.php
- Logout.php
- Customer Master Identity, Soft Deletes, and Sales Ownership Policy
- AppServiceProvider.php
- bootstrap/app.php
- packages/index.blade.php
- ProfileValidationRules.php
- Edit
- AppServiceProvider
- Package Bandwidth Modeling, Status Lifecycle, and Mikrotik Boundary Policy

## God Nodes (most connected - your core abstractions)
1. `User` - 93 edges
2. `Customer` - 45 edges
3. `AuditLogger` - 35 edges
4. `AuditLog` - 29 edges
5. `Package` - 29 edges
6. `Index` - 15 edges
7. `RolesAndPermissionsSeeder` - 14 edges
8. `scripts` - 13 edges
9. `require-dev` - 12 edges
10. `PRD #0: Dashboard Shell & UI Foundation` - 12 edges

## Surprising Connections (you probably didn't know these)
- `Create` --inherits--> `Livewire\Component`  [EXTRACTED]
  app/Livewire/Customers/Create.php →   _Bridges community 11 → community 0_
- `Edit` --inherits--> `Livewire\Component`  [EXTRACTED]
  app/Livewire/Customers/Edit.php →   _Bridges community 1 → community 0_
- `Index` --inherits--> `Livewire\Component`  [EXTRACTED]
  app/Livewire/Packages/Index.php →   _Bridges community 158 → community 0_
- `Edit` --inherits--> `Livewire\Component`  [EXTRACTED]
  app/Livewire/Roles/Edit.php →   _Bridges community 212 → community 0_
- `Index` --inherits--> `Livewire\Component`  [EXTRACTED]
  app/Livewire/Roles/Index.php →   _Bridges community 26 → community 0_

## Import Cycles
- None detected.

## Communities (215 total, 20 thin omitted)

### Community 0 - "Livewire\Component"
Cohesion: 0.20
Nodes (14): Create, Appearance, Flux\Flux, Illuminate\Database\Eloquent\Collection, Illuminate\Support\Collection, Illuminate\Support\Facades\Auth, Illuminate\View\View, Livewire\Attributes\Layout (+6 more)

### Community 1 - "Customer"
Cohesion: 0.09
Nodes (4): Edit, Index, Show, Customer

### Community 2 - "User"
Cohesion: 0.07
Nodes (11): Edit, UsersList, User, CustomerPolicy, PackagePolicy, Illuminate\Database\Eloquent\Attributes\Hidden, Illuminate\Foundation\Auth\User, Illuminate\Notifications\Notifiable (+3 more)

### Community 3 - "scripts"
Cohesion: 0.06
Nodes (37): scripts, ci:check, dev, lint, lint:check, post-autoload-dump, post-create-project-cmd, post-root-package-install (+29 more)

### Community 4 - "Issue tracker: GitHub"
Cohesion: 0.06
Nodes (30): Before exploring, read these, Domain Docs, File structure, Flag ADR conflicts, Use the glossary's vocabulary, Conventions, Issue tracker: GitHub, Pull requests as a triage surface (+22 more)

### Community 5 - "PasswordValidationRules.php"
Cohesion: 0.22
Nodes (6): CreateNewUser, ResetUserPassword, Illuminate\Contracts\Validation\ValidationRule, Illuminate\Support\Facades\Validator, Laravel\Fortify\Contracts\CreatesNewUsers, Laravel\Fortify\Contracts\ResetsUserPasswords

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

### Community 12 - "Laravel Boost Guidelines"
Cohesion: 0.07
Nodes (28): APIs & Eloquent Resources, Application Structure & Architecture, Artisan, Conventions, Deployment, Do Things the Laravel Way, Documentation Files, Foundational Context (+20 more)

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
Cohesion: 0.10
Nodes (3): Illuminate\Database\Migrations\Migration, Illuminate\Database\Schema\Blueprint, Illuminate\Support\Facades\Schema

### Community 21 - "Livewire Development"
Cohesion: 0.08
Nodes (24): Component-Scoped Interceptors, Intercept Messages, Intercept Requests, Interceptor System (v4), Livewire 4 JavaScript Integration, Magic Properties, Alpine & JavaScript, Basic Usage (+16 more)

### Community 22 - "Codebase Design"
Cohesion: 0.09
Nodes (21): 1. In-process, 2. Local-substitutable, 3. Remote but owned (Ports & Adapters), 4. True external (Mock), Deepening, Dependency categories, Seam discipline, Testing strategy: replace, don't layer (+13 more)

### Community 23 - "AuditLogger"
Cohesion: 0.14
Nodes (5): AuditLog, AuditLogger, Role, Illuminate\Database\Eloquent\Relations\BelongsTo, Illuminate\Support\Facades\Request

### Community 24 - "Codebase Design"
Cohesion: 0.09
Nodes (21): 1. In-process, 2. Local-substitutable, 3. Remote but owned (Ports & Adapters), 4. True external (Mock), Deepening, Dependency categories, Seam discipline, Testing strategy: replace, don't layer (+13 more)

### Community 25 - "Codebase Design"
Cohesion: 0.09
Nodes (21): 1. In-process, 2. Local-substitutable, 3. Remote but owned (Ports & Adapters), 4. True external (Mock), Deepening, Dependency categories, Seam discipline, Testing strategy: replace, don't layer (+13 more)

### Community 26 - "UserStatus.php"
Cohesion: 0.11
Nodes (9): Index, Create, Index, RolesAndPermissionsSeeder, Illuminate\Foundation\Testing\RefreshDatabase, Livewire\Livewire, Spatie\Permission\DefaultTeamResolver, Spatie\Permission\Models\Permission (+1 more)

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

### Community 35 - "Illuminate\Support\Str"
Cohesion: 0.08
Nodes (12): AuthenticateUser, CustomerFactory, static, PackageFactory, static, static, UserFactory, Illuminate\Database\Eloquent\Factories\Factory (+4 more)

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
Cohesion: 0.15
Nodes (5): Illuminate\Auth\Notifications\ResetPassword, Illuminate\Foundation\Testing\TestCase, Illuminate\Support\Facades\Notification, Laravel\Fortify\Features, TestCase

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

### Community 53 - "PRD #0: Dashboard Shell & UI Foundation"
Cohesion: 0.15
Nodes (12): 10. Dependencies, 11. Acceptance Criteria, 1. Ringkasan, 2. Tujuan, 3. Role & Akses Modul Ini, 4. User Stories, 5. Functional Requirements, 6. Data Model (+4 more)

### Community 54 - "PRD #1: Users & Roles Management"
Cohesion: 0.15
Nodes (12): 10. Dependencies, 11. Acceptance Criteria, 1. Ringkasan, 2. Tujuan, 3. Role & Akses Modul Ini, 4. User Stories, 5. Functional Requirements, 6. Data Model (+4 more)

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
Cohesion: 0.18
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
Cohesion: 0.20
Nodes (10): require, laravel/chisel, laravel/fortify, laravel/framework, laravel/tinker, livewire/blaze, livewire/flux, livewire/livewire (+2 more)

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

### Community 76 - "Security"
Cohesion: 0.12
Nodes (9): App\Livewire\Customers, App\Livewire\Packages, App\Livewire\Roles, Profile, Security, App\Livewire\Users, Illuminate\Support\Facades\Route, Laravel\Fortify\Actions\DisableTwoFactorAuthentication (+1 more)

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
Cohesion: 0.25
Nodes (7): description, license, minimum-stability, name, prefer-stable, $schema, type

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

### Community 94 - "laravel-best-practices/SKILL.md"
Cohesion: 0.29
Nodes (5): Consistency First, Decision Rules, How to Apply, Laravel Best Practices, Rule Index

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

### Community 100 - "Illuminate\Database\Seeder"
Cohesion: 0.38
Nodes (3): CustomerSeeder, DatabaseSeeder, Illuminate\Database\Seeder

### Community 101 - "Process"
Cohesion: 0.29
Nodes (6): 1. Scope the procedure, 2. Map each stage's journey, 3. Author the wizard, 4. Verify and hand off, Process, Wizard

### Community 102 - "Configuration Best Practices"
Cohesion: 0.33
Nodes (5): Configuration Best Practices, `env()` Only in Config Files, Use `App::environment()` for Environment Checks, Use Constants and Language Files, Use Encrypted Env or External Secrets

### Community 103 - "PRD #2: Customer Management"
Cohesion: 0.15
Nodes (12): 10. Dependencies, 11. Acceptance Criteria, 1. Ringkasan, 2. Tujuan, 3. Role & Akses Modul Ini, 4. User Stories, 5. Functional Requirements, 6. Data Model (+4 more)

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

### Community 108 - "customers/index.blade.php"
Cohesion: 0.40
Nodes (4): confirmDelete({{ $customer->id }}), deleteCustomer, $set(, toggleStatus({{ $customer->id }})

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

### Community 116 - "autoload-dev"
Cohesion: 0.67
Nodes (3): autoload-dev, psr-4, Tests\\

### Community 117 - "keywords"
Cohesion: 0.67
Nodes (3): keywords, framework, laravel

### Community 158 - "Package"
Cohesion: 0.07
Nodes (9): Index, Package, PackageSeeder, Illuminate\Database\Eloquent\Attributes\Fillable, Illuminate\Database\Eloquent\Builder, Illuminate\Database\Eloquent\Factories\HasFactory, Illuminate\Database\Eloquent\Model, Illuminate\Database\Eloquent\SoftDeletes (+1 more)

### Community 204 - "PRD #3: Package Management"
Cohesion: 0.15
Nodes (12): 10. Dependencies, 11. Acceptance Criteria, 1. Ringkasan, 2. Tujuan, 3. Role & Akses Modul Ini, 4. User Stories, 5. Functional Requirements, 6. Data Model (+4 more)

### Community 205 - "FortifyServiceProvider.php"
Cohesion: 0.25
Nodes (5): FortifyServiceProvider, Illuminate\Cache\RateLimiting\Limit, Illuminate\Support\Facades\RateLimiter, Illuminate\Support\ServiceProvider, Laravel\Fortify\Fortify

### Community 206 - "Logout.php"
Cohesion: 0.31
Nodes (5): Logout, DeleteUserForm, Illuminate\Http\RedirectResponse, Illuminate\Support\Facades\Session, Livewire\Features\SupportRedirects\Redirector

### Community 207 - "Customer Master Identity, Soft Deletes, and Sales Ownership Policy"
Cohesion: 0.50
Nodes (3): Context, Customer Master Identity, Soft Deletes, and Sales Ownership Policy, Decisions

### Community 208 - "AppServiceProvider.php"
Cohesion: 0.24
Nodes (7): RecordLastLoginAt, Carbon\CarbonImmutable, Illuminate\Auth\Events\Login, Illuminate\Support\Facades\Date, Illuminate\Support\Facades\DB, Illuminate\Support\Facades\Event, Illuminate\Validation\Rules\Password

### Community 209 - "bootstrap/app.php"
Cohesion: 0.25
Nodes (7): Illuminate\Foundation\Application, Illuminate\Foundation\Configuration\Exceptions, Illuminate\Foundation\Configuration\Middleware, Illuminate\Http\Request, Spatie\Permission\Middleware\PermissionMiddleware, Spatie\Permission\Middleware\RoleMiddleware, Spatie\Permission\Middleware\RoleOrPermissionMiddleware

### Community 210 - "packages/index.blade.php"
Cohesion: 0.29
Nodes (6): confirmDelete({{ $package->id }}), deletePackage, openCreateModal, openEditModal({{ $package->id }}), $set(, toggleStatus({{ $package->id }})

### Community 211 - "ProfileValidationRules.php"
Cohesion: 0.53
Nodes (5): emailRules(), nameRules(), phoneRules(), profileRules(), Illuminate\Validation\Rule

### Community 214 - "Package Bandwidth Modeling, Status Lifecycle, and Mikrotik Boundary Policy"
Cohesion: 0.50
Nodes (3): Context, Decisions, Package Bandwidth Modeling, Status Lifecycle, and Mikrotik Boundary Policy

## Knowledge Gaps
- **1040 isolated node(s):** `Controller`, `$schema`, `name`, `type`, `description` (+1035 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **20 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `User` connect `User` to `Livewire\Component`, `Customer`, `Illuminate\Support\Str`, `Illuminate\Database\Seeder`, `PasswordValidationRules.php`, `Laravel\Fortify\Features`, `Security`, `ProfileValidationRules.php`, `AuditLogger`, `UserStatus.php`, `Package`?**
  _High betweenness centrality (0.013) - this node is a cross-community bridge._
- **Why does `Customer` connect `Customer` to `Livewire\Component`, `User`, `Illuminate\Support\Str`, `Illuminate\Database\Seeder`, `Create`, `AuditLogger`, `UserStatus.php`, `Package`?**
  _High betweenness centrality (0.003) - this node is a cross-community bridge._
- **Why does `Index` connect `Package` to `Livewire\Component`?**
  _High betweenness centrality (0.002) - this node is a cross-community bridge._
- **What connects `Controller`, `$schema`, `name` to the rest of the system?**
  _1040 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Customer` be split into smaller, more focused modules?**
  _Cohesion score 0.09401709401709402 - nodes in this community are weakly interconnected._
- **Should `User` be split into smaller, more focused modules?**
  _Cohesion score 0.07439024390243902 - nodes in this community are weakly interconnected._
- **Should `scripts` be split into smaller, more focused modules?**
  _Cohesion score 0.057057057057057055 - nodes in this community are weakly interconnected._