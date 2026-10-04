---
name: feedback-use-makefile
description: "Use the project's Makefile shortcuts (via docker compose exec) instead of raw manual commands where one exists"
metadata:
  node_type: memory
  type: feedback
  originSessionId: e145a0ea-8e8c-44b5-8222-d6fbbe333721
  modified: 2026-10-04T01:10:00.000Z
---

Before reaching for a raw manual command, check `Makefile` for an existing shortcut and use `docker compose exec ft-web make <target>` instead.

**Why:** The user explicitly pointed this out after watching this session repeatedly do manual `rm -rf temp/*/cache/* && chmod 777 -R` cache clears, when `make cached` already does exactly that (and fixed a bug in it earlier this session). Running `make cached` directly from the host fails with permission errors (files are owned by the container's user); running it via `docker compose exec ft-web make cached` works cleanly.

**How to apply:**
- Cache clearing → `docker compose exec ft-web make cached` (clears both `temp/web/cache` and `temp/console/cache`), not manual `rm -rf`.
- **Caution:** `make test` / `make functional` / `make unit` / `make acceptance` / `make api` / `make doctrine` all depend on `dirs-build database` first, which is destructive: `rm -rf temp vendor node_modules`, full composer reinstall, and `orm:schema-tool:drop --force --full-database` + fresh migrate + fixture reload. That wipes any test fixture data created during the session and is slow. Do NOT use these for routine "did my change break anything" iteration — run `vendor/bin/codecept run <Suite> [<Test>]` directly instead (as this session has been doing), and reserve the `make` test targets for when a genuinely fresh environment/DB is actually wanted.
- `make migration` / `make migrate` for Doctrine migrations, `make database` for a full drop+migrate+fixture-reload (same destructive rebuild the test targets depend on) — only when that's actually the intent.

**Applies to ALL three projects (user reminder 2026-10-04: "v dockeru používej make, stejně jako ve family-tree a web-projectu"):**
- containers: family-tree `ft-web`, alfred `alfred-web`, web-project `web-project-web`; always `docker compose exec -T <container> make <target>` (never raw `rm -rf temp/...`, never `php vendor/bin/codecept ...` for a run that a target covers).
- alfred: `make cached`, `make lint` (latte-lint, neon-lint, php -l, composer validate - added 2026-10-04), `make phpstan`, `make unit|functional|acceptance|test`, `make cest SUITE=Functional CEST=GridExerciserCest` (one file, added 2026-10-04). Alfred's test targets run `db` first (drop+migrate+fixtures of the tmpfs TEST database) - that is fine and even required there (its tests assume a fresh DB, e.g. CommandsCest, EventCest inline edit), unlike family-tree whose dev DB holds real data (keep avoiding `make test`/`functional`/... there).
- web-project: `make cached`, `make lint`, `make phpstan`, `make unit|functional|acceptance` (test-build first).
