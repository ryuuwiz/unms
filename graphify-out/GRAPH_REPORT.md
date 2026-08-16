# Graph Report - unms  (2026-08-16)

## Corpus Check
- 276 files · ~129,108 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 425 nodes · 518 edges · 67 communities (58 shown, 9 thin omitted)
- Extraction: 100% EXTRACTED · 0% INFERRED · 0% AMBIGUOUS
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `19da625c`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- User
- composer.json
- scripts
- Security.php
- dependencies
- FortifyServiceProvider.php
- .agents/skills/wizard/template.sh
- .claude/skills/wizard/template.sh
- .kilocode/skills/wizard/template.sh
- PasswordValidationRules.php
- 0001_01_01_000000_create_users_table.php
- require-dev
- Laravel\Fortify\Features
- security.blade.php
- logging.php
- .agents/skills/diagnosing-bugs/scripts/hitl-loop.template.sh
- .claude/skills/diagnosing-bugs/scripts/hitl-loop.template.sh
- .kilocode/skills/diagnosing-bugs/scripts/hitl-loop.template.sh
- console.php
- profile.blade.php
- Controller.php
- header.blade.php
- partials.head
- card.blade.php
- simple.blade.php
- split.blade.php
- appearance.blade.php
- Database\Factories\UserFactory
- users-list.blade.php

## God Nodes (most connected - your core abstractions)
1. `User` - 30 edges
2. `scripts` - 13 edges
3. `require-dev` - 12 edges
4. `Security` - 11 edges
5. `template.sh script` - 11 edges
6. `template.sh script` - 11 edges
7. `template.sh script` - 11 edges
8. `require` - 10 edges
9. `UsersList` - 8 edges
10. `FortifyServiceProvider` - 8 edges

## Surprising Connections (you probably didn't know these)
- `UsersList` --inherits--> `Livewire\Component`  [EXTRACTED]
  app/Livewire/UsersList.php →   _Bridges community 0 → community 3_

## Import Cycles
- None detected.

## Communities (67 total, 9 thin omitted)

### Community 0 - "User"
Cohesion: 0.08
Nodes (20): UsersList, User, DatabaseSeeder, RolesAndPermissionsSeeder, Illuminate\Database\Console\Seeds\WithoutModelEvents, Illuminate\Database\Eloquent\Attributes\Fillable, Illuminate\Database\Eloquent\Attributes\Hidden, Illuminate\Database\Eloquent\Factories\HasFactory (+12 more)

### Community 1 - "composer.json"
Cohesion: 0.05
Nodes (40): pestphp/pest-plugin, php-http/discovery, autoload, autoload-dev, psr-4, psr-4, config, allow-plugins (+32 more)

### Community 2 - "scripts"
Cohesion: 0.06
Nodes (37): scripts, ci:check, dev, lint, lint:check, post-autoload-dump, post-create-project-cmd, post-root-package-install (+29 more)

### Community 3 - "Security.php"
Cohesion: 0.09
Nodes (17): Logout, Appearance, DeleteUserForm, Profile, Security, Flux\Flux, Illuminate\Http\RedirectResponse, Illuminate\Support\Facades\Auth (+9 more)

### Community 4 - "dependencies"
Cohesion: 0.07
Nodes (28): concurrently, @laravel/multiplex, @laravel/passkeys, laravel-vite-plugin, lightningcss-linux-x64-gnu, dependencies, concurrently, @laravel/passkeys (+20 more)

### Community 5 - "FortifyServiceProvider.php"
Cohesion: 0.10
Nodes (14): AppServiceProvider, FortifyServiceProvider, Carbon\CarbonImmutable, Illuminate\Cache\RateLimiting\Limit, Illuminate\Foundation\Application, Illuminate\Foundation\Configuration\Exceptions, Illuminate\Foundation\Configuration\Middleware, Illuminate\Http\Request (+6 more)

### Community 6 - ".agents/skills/wizard/template.sh"
Cohesion: 0.22
Nodes (16): ask(), ask_secret(), banner(), _clear(), finish(), note(), open_url(), pause() (+8 more)

### Community 7 - ".claude/skills/wizard/template.sh"
Cohesion: 0.22
Nodes (16): ask(), ask_secret(), banner(), _clear(), finish(), note(), open_url(), pause() (+8 more)

### Community 8 - ".kilocode/skills/wizard/template.sh"
Cohesion: 0.22
Nodes (16): ask(), ask_secret(), banner(), _clear(), finish(), note(), open_url(), pause() (+8 more)

### Community 9 - "PasswordValidationRules.php"
Cohesion: 0.17
Nodes (10): CreateNewUser, ResetUserPassword, emailRules(), nameRules(), profileRules(), Illuminate\Contracts\Validation\ValidationRule, Illuminate\Support\Facades\Validator, Illuminate\Validation\Rule (+2 more)

### Community 10 - "0001_01_01_000000_create_users_table.php"
Cohesion: 0.16
Nodes (3): Illuminate\Database\Migrations\Migration, Illuminate\Database\Schema\Blueprint, Illuminate\Support\Facades\Schema

### Community 11 - "require-dev"
Cohesion: 0.17
Nodes (12): require-dev, fakerphp/faker, larastan/larastan, laravel/boost, laravel/pail, laravel/pao, laravel/pint, laravel/sail (+4 more)

### Community 12 - "Laravel\Fortify\Features"
Cohesion: 0.11
Nodes (8): Illuminate\Auth\Notifications\ResetPassword, Illuminate\Foundation\Testing\RefreshDatabase, Illuminate\Foundation\Testing\TestCase, Illuminate\Support\Facades\Hash, Illuminate\Support\Facades\Notification, Laravel\Fortify\Features, Livewire\Livewire, TestCase

### Community 13 - "security.blade.php"
Cohesion: 0.40
Nodes (4): closeDeleteModal, confirmDelete({{ $passkey[, deletePasskey, partials.settings-heading

### Community 14 - "logging.php"
Cohesion: 0.40
Nodes (4): Monolog\Handler\NullHandler, Monolog\Handler\StreamHandler, Monolog\Handler\SyslogUdpHandler, Monolog\Processor\PsrLogMessageProcessor

### Community 15 - ".agents/skills/diagnosing-bugs/scripts/hitl-loop.template.sh"
Cohesion: 0.83
Nodes (3): capture(), hitl-loop.template.sh script, step()

### Community 16 - ".claude/skills/diagnosing-bugs/scripts/hitl-loop.template.sh"
Cohesion: 0.83
Nodes (3): capture(), hitl-loop.template.sh script, step()

### Community 17 - ".kilocode/skills/diagnosing-bugs/scripts/hitl-loop.template.sh"
Cohesion: 0.83
Nodes (3): capture(), hitl-loop.template.sh script, step()

### Community 64 - "Database\Factories\UserFactory"
Cohesion: 0.21
Nodes (6): Database\Factories\UserFactory, UserFactory, Illuminate\Database\Eloquent\Factories\Factory, Illuminate\Support\Str, Pdo\Mysql, static

### Community 65 - "users-list.blade.php"
Cohesion: 0.40
Nodes (4): confirmDelete({{ $user->id }}), deleteUser, save, $set(

## Knowledge Gaps
- **94 isolated node(s):** `$schema`, `name`, `type`, `description`, `laravel` (+89 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **9 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `User` connect `User` to `Database\Factories\UserFactory`, `PasswordValidationRules.php`, `Laravel\Fortify\Features`?**
  _High betweenness centrality (0.054) - this node is a cross-community bridge._
- **Why does `scripts` connect `scripts` to `composer.json`?**
  _High betweenness centrality (0.028) - this node is a cross-community bridge._
- **Why does `Security` connect `Security.php` to `PasswordValidationRules.php`, `Laravel\Fortify\Features`?**
  _High betweenness centrality (0.014) - this node is a cross-community bridge._
- **What connects `$schema`, `name`, `type` to the rest of the system?**
  _94 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `User` be split into smaller, more focused modules?**
  _Cohesion score 0.07957957957957958 - nodes in this community are weakly interconnected._
- **Should `composer.json` be split into smaller, more focused modules?**
  _Cohesion score 0.04878048780487805 - nodes in this community are weakly interconnected._
- **Should `scripts` be split into smaller, more focused modules?**
  _Cohesion score 0.057057057057057055 - nodes in this community are weakly interconnected._