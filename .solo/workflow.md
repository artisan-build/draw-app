# Workflow - Draw

Draw is a pre-launch Built for Cloud team whiteboard. Laravel, Livewire, and Flux own the application pages; Vite builds the React islands that host Excalidraw. Multi-team support, comments, version history, folders, presence cursors, in-app AI, share editing, migration tooling, and custom domains are outside the MVP.

## Phase And Mode

- phase: `pre-launch`
- default mode: `A-autonomous`
- merge policy: `merge on green CI; no human PR review (Ed, 2026-10-27)`
- merge method: `gh pr merge --squash --delete-branch`
- security review: every PR involving authorization or sharing, signed links, or MCP requires the full independent quality reviewer and acceptance judge pair before merge.

## Role Resolution

Resolve implementer, quality reviewer, and acceptance judge roles at runtime from `~/Herd/brain/agents.json` by following `~/Herd/brain/playbooks/resolve-agent-role.md`. Do not pin agent tools or model lineages in this repository.

## Hard Gate

- command: `composer ready`
- order: IDE helper generation, Rector, Pint, PHPStan/Larastan, the PostgreSQL Pest suite, Composer audit, `npm ci`, frontend lint, and the Vite production build.
- extra suites: none in the scaffold.
- monorepo: no.

The coordinator runs the complete gate once on the clean, committed candidate. Implementers run only focused tests for changed code, then static analysis and lint once before handoff.

## CI

- workflows: `.github/workflows/tests.yml` and `.github/workflows/lint.yml`
- exact required contexts: `server (8.5)` and `frontend`
- `server (8.5)` runs Rector, Pint check mode, PHPStan/Larastan, Pest, and Composer audit on PHP 8.5 with PostgreSQL 18.
- `frontend` runs `npm ci`, ESLint, and the Vite production build on Node 22.

Both workflows run on pushes and pull requests to `main`. Required context names are literal; do not rename the jobs without coordinating the matching branch-protection change. This repository profile records the contexts but does not authorize agents to set branch protection.

## Dependency Install

- PHP: `composer install --no-interaction --prefer-dist`
- frontend: `npm ci`
- local setup: copy `.env.example` to `.env`, run `php artisan key:generate`, create the local PostgreSQL databases `draw` and `draw_app_test`, then run `php artisan migrate`.
- tests: `phpunit.xml` fixes the application lane to PostgreSQL database `draw_app_test` at `127.0.0.1`; it never silently substitutes SQLite. CI creates the same database through its PostgreSQL service.

Cloud-provisioned resource values are injected by Laravel Cloud. Never add installer-supplied environment values for the database, queue, cache, or future WebSockets resource.

## Branches, Worktrees, And Shipping

- PR target: `artisan-build/draw-app`, branch `main`.
- ordinary branches: `feat/<slug>` for features, `fix/<slug>` for fixes, and `chore/<slug>` for maintenance.
- fetch and confirm the checkout is not behind `origin/main` before branching.
- use one worktree per branch and never share a mutable worktree between agents.
- the initial empty-repository scaffold is the sole exception allowed directly on `main`; every later change uses a branch and worktree.
- do not push, merge, deploy, tag, publish the repository, or modify Scalpels catalog data unless the active brief authorizes that exact action.

## Plan And Coordination

- authoritative plan: `/Users/edgrosvenor/Herd/brain/projects/draw-app/PRD.md`, with section 2 locked.
- Solo project: `draw-app`, id `126`.
- run log: Solo scratchpad `642`, `Draw MVP build — run log`.
- decision trail: `/Users/edgrosvenor/Herd/brain/projects/draw-app/decisions-mvp-build.tsv`.
- mutation debt: `/Users/edgrosvenor/Herd/brain/projects/draw-app/mutation-debt.tsv`.

## Built For Cloud App

- app role: a customer-owned team whiteboard installed through Scalpels and deployed to Laravel Cloud.
- catalog resource manifest: `built-for-cloud.json`.
- application UI manifest: `config/built-for-cloud.php`.
- base resources: PostgreSQL and one managed queue. Do not add a `websockets` resource until the frozen Scalpels shape exists.
- installer environment keys: none.
- build: Scalpels’ standard Composer build triggers the root `post-install-cmd`, which runs `npm ci` and `npm run build`; the manifest adds no wrapper command.

## Browser Verification

User-visible work needs live verification in a real browser against a local or disposable environment after the test gate. The editor round trip and Excalidraw tab UI must be driven, and the delegated MCP flow must update an open viewer in under two seconds. Record a harness stamp with the candidate SHA, browser and version, base URL, PostgreSQL database, queue driver, test identities, observed states, console or page errors, and teardown result. Never use a person's active browser profile or a production environment for this rung.

The final Scalpels install remains `verifier-blocked` until the managed WebSockets resource exists. A blocked rung is not a pass.
