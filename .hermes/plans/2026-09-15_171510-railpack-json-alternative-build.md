# Railpack.json — alternative build path to the Dockerfile

## Goal

Add a `railpack.json` at the repo root so `railpack build .` produces a
working production image for this Laravel app (php-fpm + Caddy + Horizon +
scheduler, one container, supervisord-managed) as an alternative build path
to the existing `Dockerfile`, without touching or replacing the Dockerfile.

## Current context / assumptions

- This repo already has a hand-rolled multi-stage `Dockerfile` (root) that
  produces a single container running **php-fpm, Caddy, Horizon, and the
  Laravel scheduler** under supervisord. That is documented and understood —
  see `docker/entrypoint.sh`, `docker/supervisord.conf`,
  `docker/supervisor.d/{caddy,php-fpm,horizon,schedule}.conf`,
  `docker/Caddyfile`, `docker/php.ini`, `docker/www.conf`.
- User confirmed: the `railpack.json` build must produce **parity with the
  Dockerfile** — one container running the same 4 processes via supervisord —
  not Railway's idiomatic "one process per service" model. It is a build-path
  alternative, not an architecture change.
- User confirmed: no specific target platform (Railway/Dokploy) yet — the
  deliverable is validated with the standalone `railpack` CLI
  (`railpack build`, `railpack plan`, `railpack build --output`) run locally.
- Railpack's built-in PHP provider (confirmed via `https://schema.railpack.com`
  and `https://railpack.com/languages/php/`) auto-detects Laravel and by
  default runs **FrankenPHP only** (single web process) — it does NOT know
  about Horizon or the scheduler, and its default `deploy.startCommand` is a
  `start-container.sh` script we don't control unless we override it.
  To reach parity we must **override the entire `deploy` section** ourselves:
  bring our own base image or supervisord/Caddy/php-fpm binaries as inputs,
  and set `deploy.startCommand` to run `docker/entrypoint.sh` +
  `/usr/bin/supervisord` exactly like `Dockerfile`'s `ENTRYPOINT`/`CMD` do.
- Railpack config format facts used below (source: `schema.railpack.com` +
  `railpack.com/config/file`, `railpack.com/languages/php`,
  `railpack.com/reference/cli`, fetched and read directly, 2026-09-15):
  - Root fields: `provider`, `buildAptPackages`, `packages` (map of pkg→version,
    resolved via Mise), `caches`, `secrets`, `steps` (map of step name → step),
    `deploy`, `exclude`, `$schema`.
  - A step has `inputs` (layers: `{"step": "..."}`, `{"image": "..."}`,
    `{"local": true}`, or the strings `"."` / `"..."`), `commands` (exec
    strings, `{"cmd":...}`, `{"path":...}`, `{"src":...,"dest":...}` copy, or
    `{"path":...,"name":...,"mode":...}` file-write), `caches`, `secrets`,
    `assets`, `variables`, `deployOutputs`.
  - `deploy` has `base` (a single layer, required to build a runnable image),
    `inputs` (additional layers copied on top of `base`), `startCommand`,
    `variables`, `paths`, `aptPackages`.
  - `packages` values are Mise package specs, e.g. `{"php": "8.4", "node": "24"}`
    — Railpack resolves and installs these via Mise, similar to how the
    Dockerfile pins `php:8.4-fpm-bookworm` / `node:24-bookworm`.
  - `railpack` does **not** read a `Dockerfile`; it is a standalone tool
    (`curl -sSL https://railpack.com/install.sh | sh`) that builds via BuildKit
    from `railpack.json` (or auto-detection if absent). Confirm install
    before starting Step 0.
- `docker/entrypoint.sh` currently does `chown -R www-data:www-data storage
  bootstrap/cache` and hard `php artisan config:cache` etc — reuse it
  unmodified; do not duplicate its logic inside `railpack.json` commands.
- `.dockerignore` (root) already lists what NOT to ship into the Docker build
  context; Railpack has an equivalent `exclude` root field, but since a
  `.dockerignore` already exists Railpack will pick it up automatically per
  `https://railpack.com/config/excluding-files/` — **no duplication needed**,
  do not add a `railpack.json` `exclude` array unless the existing
  `.dockerignore` proves insufficient during Step 6 validation.

## Architecture / proposed approach

Do NOT rely on Railpack's PHP-provider auto-detection (it only builds a
FrankenPHP single-process image and has no concept of Horizon/scheduler). 
Instead write an explicit `railpack.json` with: a `vendor` step (composer
install, mirrors `Dockerfile` stage 1), a `frontend` step (npm ci + vite
build, mirrors stage 2), and a `deploy` section whose `base` is the official
`php:8.4-fpm-bookworm` image with supervisor/Caddy/PHP-extensions installed
via `buildAptPackages`/apt commands in a dedicated `runtime-deps` step, whose
`inputs` copy in vendor output + frontend build + app source + all
`docker/*` config files, and whose `startCommand` is
`/usr/local/bin/entrypoint.sh /usr/bin/supervisord -c /etc/supervisor/supervisord.conf`
— i.e. line-for-line parity with the Dockerfile's `ENTRYPOINT`/`CMD`. This
keeps `docker/entrypoint.sh`, `docker/Caddyfile`, `docker/supervisor.d/*`,
`docker/php.ini`, `docker/www.conf` as the single source of truth reused
by both build paths, so there's nothing to keep in sync but the
`railpack.json` step wiring itself.

## Step-by-step tasks

### Step 0 — Install the Railpack CLI locally (prerequisite, not committed)

```bash
curl -sSL https://railpack.com/install.sh | sh
railpack --version
```
Expected output: a version string (e.g. `railpack version 0.x.y`), no error.
If `railpack` isn't on `PATH` afterward, the installer prints the actual
install dir — add it to `PATH` for this shell session before continuing
(`export PATH="$HOME/.local/bin:$PATH"` is the documented default with
`curl | sh`, no flags).

This step installs a tool, not a repo file — nothing to commit.

### Step 1 — Confirm BuildKit is available for `railpack build`

Railpack builds run through BuildKit under the hood.

```bash
docker version --format '{{.Server.Version}}'
```
Expected: a Docker Engine version string. If this fails, `railpack build`
will fail too — install/start Docker first (out of scope for this plan; stop
and tell the user if this step fails).

### Step 2 — Create `railpack.json` at repo root: root fields + vendor step

Create `railpack.json` (new file, repo root, next to `Dockerfile` and
`composer.json`). Start with the root config and the Composer-install step —
this mirrors `Dockerfile` lines 19-66 (the `vendor` stage).

```json
{
  "$schema": "https://schema.railpack.com",
  "provider": "php",
  "packages": {
    "php": "8.4"
  },
  "buildAptPackages": [
    "libicu-dev",
    "libzip-dev",
    "libpng-dev",
    "libjpeg62-turbo-dev",
    "libfreetype6-dev",
    "libonig-dev",
    "libxml2-dev"
  ],
  "steps": {
    "vendor": {
      "inputs": [{ "local": true, "include": ["composer.json", "composer.lock", "database"] }],
      "commands": [
        "curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer",
        "install-php-extensions pdo_mysql mysqli mbstring exif pcntl bcmath gd zip intl opcache redis sockets",
        "composer install --no-dev --no-interaction --no-progress --no-scripts --optimize-autoloader --prefer-dist"
      ]
    }
  }
}
```
NOTE: the `provider: php` root field plus `packages.php` triggers Railpack's
own PHP+Mise install of PHP 8.4 and `install-php-extensions` binary
automatically per `https://railpack.com/languages/php/` ("PHP Extensions ...
automatically installed based on requirements in composer.json"), so the
explicit `install-php-extensions` line above is very likely redundant/wrong
syntax for a Railpack-provided step — **this will only be confirmed empirically
in Step 6** (`railpack plan .`). Treat this JSON as a first draft to be
corrected against the actual generated plan, not as gospel — see Step 6.

Verification (syntax only, no build yet):
```bash
python3 -m json.tool railpack.json > /dev/null && echo VALID_JSON
```
Expected: `VALID_JSON`.

Commit: `git add railpack.json && git commit -m "railpack: add composer vendor step"`

### Step 3 — Add the frontend (Vite) step

Add a `frontend` step to the `steps` map in `railpack.json`, mirroring
`Dockerfile` lines 71-81 (the `frontend` stage: `npm ci` + `npm run build`,
needs `vendor/` present for Vite/Laravel plugin resolution — see
`Dockerfile:80` `COPY --from=vendor /app/vendor ./vendor`).

```json
    "frontend": {
      "inputs": [
        { "step": "vendor", "include": ["."] },
        { "local": true, "include": ["package.json", "package-lock.json", "resources", "vite.config.js", "vite.config.ts"] }
      ],
      "commands": [
        "npm ci",
        "npm run build"
      ]
    }
```
Adjust the `vite.config.*` filename to whichever actually exists — check
first:
```bash
ls vite.config.*
```
Expected: exactly one match (`vite.config.js` or `.ts`); use that name only
in the `include` list, drop the other.

Verification:
```bash
python3 -m json.tool railpack.json > /dev/null && echo VALID_JSON
```

Commit: `git add railpack.json && git commit -m "railpack: add frontend vite build step"`

### Step 4 — Add the runtime-deps step (Caddy, supervisor, PHP extensions, config files)

Add a `runtime` step that mirrors `Dockerfile` lines 86-199 (system packages,
Caddy binary, PHP extensions, php.ini/www.conf/Caddyfile/supervisord config
copy-in, non-root UID/GID, `livewire:publish`, permissions,
`docker/entrypoint.sh`).

```json
    "runtime": {
      "inputs": [
        { "step": "vendor", "include": ["."] },
        { "local": true, "include": ["."] }
      ],
      "commands": [
        "apt-get update && apt-get install -y --no-install-recommends supervisor bash curl tzdata && ln -sf /usr/share/zoneinfo/Asia/Jakarta /etc/localtime && rm -rf /var/lib/apt/lists/*",
        { "cmd": "install-php-extensions pdo_mysql mysqli mbstring exif pcntl bcmath gd zip intl opcache redis sockets", "customName": "Install PHP extensions" },
        { "image": "caddy:2-alpine", "src": "/usr/bin/caddy", "dest": "/usr/local/bin/caddy" },
        { "src": "docker/php.ini", "dest": "/usr/local/etc/php/conf.d/99-app.ini" },
        { "src": "docker/www.conf", "dest": "/usr/local/etc/php-fpm.d/www.conf" },
        { "src": "docker/Caddyfile", "dest": "/etc/caddy/Caddyfile" },
        "caddy validate --config /etc/caddy/Caddyfile --adapter caddyfile",
        { "src": "docker/supervisord.conf", "dest": "/etc/supervisor/supervisord.conf" },
        { "src": "docker/supervisor.d", "dest": "/etc/supervisor/conf.d" },
        "usermod -u 1000 www-data && groupmod -g 1000 www-data",
        { "cmd": "php artisan livewire:publish --assets && chown -R www-data:www-data public/vendor", "customName": "Publish Livewire assets" },
        "mkdir -p storage/framework/cache storage/framework/sessions storage/framework/testing storage/framework/views storage/logs bootstrap/cache && chown -R www-data:www-data storage bootstrap/cache && chmod -R 775 storage bootstrap/cache",
        { "src": "docker/entrypoint.sh", "dest": "/usr/local/bin/entrypoint.sh" },
        "chmod +x /usr/local/bin/entrypoint.sh"
      ]
    }
```
This step's `{ "local": true, "include": ["."] }` input copies the whole app
source in (Railpack applies `.dockerignore`/`exclude` automatically — see
"Current context" notes — so `vendor/`, `node_modules/`, `.git`, etc are
already excluded, same as the Dockerfile's `COPY --chown=www-data:www-data . .`
at line 177 combined with `.dockerignore`).

Verification:
```bash
python3 -m json.tool railpack.json > /dev/null && echo VALID_JSON
```

Commit: `git add railpack.json && git commit -m "railpack: add runtime step (caddy, supervisor, php exts, config)"`

### Step 5 — Add the `deploy` section (final image assembly + start command)

Add the root-level `deploy` object, mirroring `Dockerfile` lines 172-207
(`ENV APP_ENV=production ...`, final `COPY --from=vendor/frontend`,
`EXPOSE 80`, `HEALTHCHECK`, `ENTRYPOINT`/`CMD`).

```json
  "deploy": {
    "base": { "step": "runtime", "include": ["."] },
    "inputs": [
      { "step": "frontend", "include": ["/app/public/build"] }
    ],
    "variables": {
      "APP_ENV": "production",
      "APP_DEBUG": "false",
      "LOG_CHANNEL": "stderr"
    },
    "startCommand": "/usr/local/bin/entrypoint.sh /usr/bin/supervisord -c /etc/supervisor/supervisord.conf"
  }
```
Note: Railpack's schema (confirmed via `schema.railpack.com`) has no
`EXPOSE`/`HEALTHCHECK`/`WORKDIR` equivalents in `deploy` — BuildKit images
built by Railpack don't carry a documented port-expose or healthcheck
directive the way a Dockerfile does. This is an accepted gap versus the
Dockerfile (documented under Risks below); do not invent undocumented
schema fields to work around it.

Verification:
```bash
python3 -m json.tool railpack.json > /dev/null && echo VALID_JSON
```

Commit: `git add railpack.json && git commit -m "railpack: add deploy section with supervisord start command"`

### Step 6 — Validate the plan Railpack actually generates (this WILL surface JSON/schema mistakes from steps 2-5)

```bash
railpack plan . --out /tmp/railpack-plan.json
cat /tmp/railpack-plan.json | python3 -m json.tool | less
```
Expected: a fully-resolved plan with `vendor`, `frontend`, `runtime` steps
and a `deploy` section reflecting what was written, with no error printed to
stderr. Read it closely for:
- Whether the built-in `provider: php` step auto-injected commands (e.g. its
  own composer/PHP-extension install) that now duplicate/conflict with the
  explicit `vendor`/`runtime` steps above — if so, either drop `provider: php`
  entirely (use no provider / rely purely on hand-written steps) or drop the
  duplicated custom commands, whichever the plan shows is actually redundant.
- Whether `{"image": "caddy:2-alpine", "src": ..., "dest": ...}` resolves
  correctly as a copy-command inside `commands` (this file-copy-from-image
  command form is documented for the root `commands` schema at
  `schema.railpack.com`; confirm it also works inside a step's command list
  the way it's used here — the docs' only real example is inside `deploy`).
- Whether `deploy.inputs[].include` paths need to be relative to the deploy
  filesystem root or to the source step's filesystem — adjust
  `"/app/public/build"` to whatever the plan output shows is correct.

If this step reveals structural problems, fix `railpack.json` directly (not
a new file) and re-run `railpack plan .` until it produces a plan with no
errors and the four expected steps + deploy section. Commit each meaningful
fix separately with a message describing what was wrong
(e.g. `git commit -m "railpack: fix deploy.inputs include path"`).

### Step 7 — Build the image with Railpack

```bash
railpack build . --name unms-railpack:test --show-plan
```
Expected: BuildKit build output ending in an image tagged
`unms-railpack:test` with no error. `--show-plan` prints the same plan as
Step 6 first — use it to sanity check before the (slower) actual build runs.

If the build fails on a missing binary/package inside a step's `commands`
(e.g. `install-php-extensions` not present in the base image at that point),
that means the step's `inputs` are missing a prior step's output or a
Railpack-provided base tool — fix the `inputs` list for that step in
`railpack.json` and re-run this command. Do not work around a missing tool
by hand-installing it a different way than the Dockerfile does; find why the
step's `inputs` didn't inherit it and fix the input chain instead.

Commit any fixes made here the same way as Step 6.

### Step 8 — Run the built image and confirm all 4 processes start

```bash
docker run --rm -d --name unms-railpack-test \
  -e APP_KEY="base64:$(openssl rand -base64 32)" \
  -e DB_CONNECTION=sqlite \
  -e DB_DATABASE=/tmp/test.sqlite \
  -e CACHE_STORE=array \
  -e SESSION_DRIVER=array \
  -e QUEUE_CONNECTION=sync \
  -p 8080:80 \
  unms-railpack:test
sleep 5
docker logs unms-railpack-test 2>&1 | tail -n 60
```
Expected in the logs: the same supervisord startup sequence seen in the
Dockerfile-built image — `spawned: 'php-fpm'`, `spawned: 'caddy'`,
`spawned: 'horizon'`, `spawned: 'schedule'`, each followed by `entered
RUNNING state`. If Horizon/schedule immediately fail because the throwaway
sqlite/array config above doesn't match a real Redis-backed setup, that's
expected for this smoke test — what matters is that supervisord found and
attempted to launch all four programs, proving `docker/supervisor.d/*.conf`
and `docker/entrypoint.sh` were copied in and are executable in the
Railpack-built image exactly as in the Dockerfile-built one.

```bash
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8080/up
```
Expected: `200` (or a `500` with a body you can inspect via
`curl -s http://127.0.0.1:8080/up`, matching whatever a Dockerfile-built
image running the exact same throwaway env would also return — the point is
parity, not that this exact fake config produces a healthy app).

```bash
docker stop unms-railpack-test
```

### Step 9 — Document the alternative build path

Add a short note near the top of the existing `Dockerfile` (a comment, 2-3
lines) pointing to `railpack.json` as an alternative build path, and add an
equally short comment at the top of `railpack.json` pointing back — so a
future reader of either file knows the other exists and that
`docker/entrypoint.sh` + `docker/supervisor.d/*` + `docker/Caddyfile` +
`docker/php.ini` + `docker/www.conf` are the single shared source of truth
consumed by both.

```
# Dockerfile:1 — add after the existing syntax/note comment block (after line 14)
# Alternative build path: railpack.json (repo root) builds an equivalent
# image via `railpack build .` instead of `docker build .`. Both consume the
# same docker/entrypoint.sh, docker/supervisor.d/*, docker/Caddyfile,
# docker/php.ini, docker/www.conf — edit those once, not per build path.
```
```
# railpack.json — add as the very first line's sibling, i.e. keep valid
# JSON by NOT adding a JS-style comment (Railpack does allow comments per
# railpack.com/config/file "Railpack allows comments in railpack.json" —
# confirm this in Step 6's `railpack plan` output before relying on it; if
# comments genuinely parse, add one identical in spirit to the Dockerfile
# note above, right after "$schema"). If comments do NOT actually parse
# cleanly through `railpack plan`, skip this and put the note in this plan
# file / a README instead — do not risk breaking the JSON for a comment.
```

Commit: `git add Dockerfile railpack.json && git commit -m "docs: cross-reference Dockerfile and railpack.json as alternative build paths"`

## Tests / validation

This is infra/build tooling, not application code — there is no Pest/PHPUnit
test to write. The validation loop IS the TDD cycle here, using the tool's
own linting/planning steps as the "red/green" signal:

1. `python3 -m json.tool railpack.json` after every edit (Steps 2-5) — must
   exit 0 before moving to the next step. This is the fast, cheap check.
2. `railpack plan . --out /tmp/railpack-plan.json` (Step 6) — the first real
   "red" test; iterate until it's green (plan generated, no stderr errors).
3. `railpack build . --name unms-railpack:test` (Step 7) — the second real
   "red" test; iterate until the image builds successfully.
4. `docker run` + log inspection + `curl /up` (Step 8) — final acceptance
   check: does the built image behave the same as the Dockerfile-built one.

Do not skip straight to Step 7/8 after writing the whole file — validate
incrementally (Step 6 before Step 7) so a mistake in, say, the `runtime` step
is caught before spending time on a full BuildKit build.

## Risks, tradeoffs, and open questions

- **Two build definitions to keep in sync.** `Dockerfile` and `railpack.json`
  both hand-encode the same process list (php-fpm, Caddy, Horizon, schedule)
  and the same package/extension list. A future change to
  `docker/supervisor.d/*` or PHP extensions needs updating in both places'
  *step wiring* even though the actual config files
  (`docker/entrypoint.sh`, `docker/Caddyfile`, etc.) are shared. Mitigate by
  keeping `railpack.json`'s `buildAptPackages`/extension list a literal copy
  of `Dockerfile`'s `PHP_EXT_PACKAGES` ARG and extension list, and note this
  coupling in Step 9's cross-reference comments.
- **Unverified schema usage.** The `{"image": ..., "src": ..., "dest": ...}`
  copy-from-image command form, and whether `deploy.inputs[].include` paths
  are step-relative or absolute-in-final-filesystem, are taken from the one
  documented example each on `railpack.com` — Step 6 (`railpack plan`) is
  where these get proven right or wrong. Budget real iteration time here;
  do not assume the first draft in Steps 2-5 is correct.
- **No EXPOSE/HEALTHCHECK equivalent found in the Railpack schema.** The
  Dockerfile's `HEALTHCHECK --interval=30s ... CMD curl -f http://127.0.0.1/up`
  has no discovered Railpack equivalent. If the target platform (Railway,
  Dokploy, etc., whenever one is chosen later) needs a container-level
  healthcheck, that will need to be configured at the platform level instead
  of baked into the image — flag this to the user before relying on
  Railpack-built images in any platform that expects Docker-native
  `HEALTHCHECK`.
- **Non-root UID/GID mapping (`usermod -u 1000 www-data`) baked at build
  time**, same limitation the Dockerfile already has (see its own comment at
  line 160: "adjust for Dokploy if needed") — carried over unchanged,
  not a new risk introduced by this plan.
- **Open question for the user, not blocking this plan:** once a real
  deploy target is chosen (Railway/Dokploy/other), re-check whether that
  platform's Railpack integration expects/ignores a `deploy.base` pointing
  at a custom-built step chain like this (vs. Railway's normal "provider
  auto-detects everything" flow) — Railway specifically documents deploying
  supervisord-style multi-process containers as atypical for their platform,
  so confirm it's actually supported before committing to Railpack as the
  primary build path for that platform, if one is chosen later.
