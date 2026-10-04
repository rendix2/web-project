---
name: project-strictmode-deprecations
description: tracy.strictMode:true turns every PHP deprecation notice into a fatal error — smoke-test key pages after any composer update
metadata:
  node_type: memory
  type: project
  originSessionId: e145a0ea-8e8c-44b5-8222-d6fbbe333721
  modified: 2026-09-23T22:34:14.269Z
---

`config/common.neon` sets `tracy: strictMode: true`, which converts *any* PHP deprecation notice (`E_USER_DEPRECATED`, `E_DEPRECATED`) into a fatal bluescreen/500, app-wide, regardless of which unrelated class triggered it.

**Why this matters:** After the user ran a broad `composer update`, every single page returned HTTP 500. Root cause: the updated `nette/component-model` added a deprecation warning for calling `Component::monitor($type)` with only one argument (the old `attached()`/`detached()`-override pattern) — and 10 unrelated classes across the app (`PersonAllSelect`, `PersonMaleSelect`, `PersonFemaleSelect`, `TownSelect`, `AddressSelect`, `WeddingSelect`, `CountrySelect`, `GenusSelect`, `SourceTypeSelect`, `TownForm`) called it that way. None of them actually overrode `attached()`/`detached()` anywhere in their inheritance chain (confirmed empty in `nette/forms` and `contributte/forms-bootstrap` too), so the calls were pure no-ops — fixed by deleting them outright, not by patching to the 3-arg form.

**How to apply:**
- After any `composer update` (even a narrow one), smoke-test several representative pages via `curl` (not just the automated test suite — Codeception's Unit/Functional suites didn't happen to exercise every one of the 10 affected classes, so a page-by-page curl check caught it faster) before trusting the app works.
- If a page suddenly 500s after a dependency bump, check the Tracy error title first — `User Deprecated: ...` in the title means this strictMode amplification, not a real logic break. Find the deprecated call, and check whether it's a genuine behavioral dependency (fix properly) or dead/no-op code left over from an old API pattern (just delete it, matching [[feedback_verify_reachability]]).
