# Graph Report - unms  (2026-08-16)

## Corpus Check
- 349 files · ~126,352 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 398 nodes · 479 edges · 64 communities (55 shown, 9 thin omitted)
- Extraction: 100% EXTRACTED · 0% INFERRED · 0% AMBIGUOUS
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- Database Illuminate
- Composer Dev
- Ref Composer
- Livewire App
- Package Dependencies
- App Providers
- Agents Skills
- Claude Skills
- Kilocode Skills
- App Fortify
- Database Migrations
- Composer Require
- Testcase Tests
- Settings Resources
- Monolog Handler
- Agents Skills
- Claude Skills
- Kilocode Skills
- Illuminate Foundation
- Settings Resources
- Controller App
- Resources Views
- Resources Views
- Resources Views
- Resources Views
- Resources Views
- Settings Resources

## God Nodes (most connected - your core abstractions)
1. `User` - 23 edges
2. `scripts` - 13 edges
3. `require-dev` - 12 edges
4. `template.sh script` - 11 edges
5. `template.sh script` - 11 edges
6. `template.sh script` - 11 edges
7. `Security` - 11 edges
8. `require` - 9 edges
9. `FortifyServiceProvider` - 8 edges
10. `Profile` - 7 edges

## Surprising Connections (you probably didn't know these)
- None detected - all connections are within the same source files.

## Import Cycles
- None detected.

## Communities (64 total, 9 thin omitted)

### Community 0 - "Database Illuminate"
Cohesion: 0.06
Nodes (22): User, UserFactory, DatabaseSeeder, Illuminate\Auth\Notifications\ResetPassword, Illuminate\Database\Console\Seeds\WithoutModelEvents, Illuminate\Database\Eloquent\Attributes\Fillable, Illuminate\Database\Eloquent\Attributes\Hidden, Illuminate\Database\Eloquent\Factories\Factory (+14 more)

### Community 1 - "Composer Dev"
Cohesion: 0.05
Nodes (42): pestphp/pest-plugin, php-http/discovery, autoload, autoload-dev, psr-4, psr-4, config, allow-plugins (+34 more)

### Community 2 - "Ref Composer"
Cohesion: 0.06
Nodes (37): scripts, ci:check, dev, lint, lint:check, post-autoload-dump, post-create-project-cmd, post-root-package-install (+29 more)

### Community 3 - "Livewire App"
Cohesion: 0.09
Nodes (17): Logout, Appearance, DeleteUserForm, Profile, Security, Flux\Flux, Illuminate\Http\RedirectResponse, Illuminate\Support\Facades\Auth (+9 more)

### Community 4 - "Package Dependencies"
Cohesion: 0.07
Nodes (28): concurrently, @laravel/multiplex, @laravel/passkeys, laravel-vite-plugin, lightningcss-linux-x64-gnu, dependencies, concurrently, @laravel/passkeys (+20 more)

### Community 5 - "App Providers"
Cohesion: 0.10
Nodes (14): AppServiceProvider, FortifyServiceProvider, Carbon\CarbonImmutable, Illuminate\Cache\RateLimiting\Limit, Illuminate\Foundation\Application, Illuminate\Foundation\Configuration\Exceptions, Illuminate\Foundation\Configuration\Middleware, Illuminate\Http\Request (+6 more)

### Community 6 - "Agents Skills"
Cohesion: 0.22
Nodes (16): ask(), ask_secret(), banner(), _clear(), finish(), note(), open_url(), pause() (+8 more)

### Community 7 - "Claude Skills"
Cohesion: 0.22
Nodes (16): ask(), ask_secret(), banner(), _clear(), finish(), note(), open_url(), pause() (+8 more)

### Community 8 - "Kilocode Skills"
Cohesion: 0.22
Nodes (16): ask(), ask_secret(), banner(), _clear(), finish(), note(), open_url(), pause() (+8 more)

### Community 9 - "App Fortify"
Cohesion: 0.17
Nodes (10): CreateNewUser, ResetUserPassword, emailRules(), nameRules(), profileRules(), Illuminate\Contracts\Validation\ValidationRule, Illuminate\Support\Facades\Validator, Illuminate\Validation\Rule (+2 more)

### Community 10 - "Database Migrations"
Cohesion: 0.19
Nodes (3): Illuminate\Database\Migrations\Migration, Illuminate\Database\Schema\Blueprint, Illuminate\Support\Facades\Schema

### Community 11 - "Composer Require"
Cohesion: 0.22
Nodes (9): require, laravel/chisel, laravel/fortify, laravel/framework, laravel/tinker, livewire/blaze, livewire/flux, livewire/livewire (+1 more)

### Community 12 - "Testcase Tests"
Cohesion: 0.29
Nodes (3): Illuminate\Foundation\Testing\RefreshDatabase, Illuminate\Foundation\Testing\TestCase, TestCase

### Community 13 - "Settings Resources"
Cohesion: 0.40
Nodes (4): closeDeleteModal, confirmDelete({{ $passkey[, deletePasskey, partials.settings-heading

### Community 14 - "Monolog Handler"
Cohesion: 0.40
Nodes (4): Monolog\Handler\NullHandler, Monolog\Handler\StreamHandler, Monolog\Handler\SyslogUdpHandler, Monolog\Processor\PsrLogMessageProcessor

### Community 15 - "Agents Skills"
Cohesion: 0.83
Nodes (3): capture(), hitl-loop.template.sh script, step()

### Community 16 - "Claude Skills"
Cohesion: 0.83
Nodes (3): capture(), hitl-loop.template.sh script, step()

### Community 17 - "Kilocode Skills"
Cohesion: 0.83
Nodes (3): capture(), hitl-loop.template.sh script, step()

## Knowledge Gaps
- **89 isolated node(s):** `Controller`, `$schema`, `name`, `type`, `description` (+84 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **9 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `User` connect `Database Illuminate` to `App Fortify`?**
  _High betweenness centrality (0.039) - this node is a cross-community bridge._
- **Why does `scripts` connect `Ref Composer` to `Composer Dev`?**
  _High betweenness centrality (0.031) - this node is a cross-community bridge._
- **Why does `Security` connect `Livewire App` to `Database Illuminate`, `App Fortify`?**
  _High betweenness centrality (0.014) - this node is a cross-community bridge._
- **What connects `Controller`, `$schema`, `name` to the rest of the system?**
  _89 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Database Illuminate` be split into smaller, more focused modules?**
  _Cohesion score 0.06423034330011074 - nodes in this community are weakly interconnected._
- **Should `Composer Dev` be split into smaller, more focused modules?**
  _Cohesion score 0.046511627906976744 - nodes in this community are weakly interconnected._
- **Should `Ref Composer` be split into smaller, more focused modules?**
  _Cohesion score 0.057057057057057055 - nodes in this community are weakly interconnected._