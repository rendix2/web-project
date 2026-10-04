---
name: feedback-test-coverage
description: "After any code change, create or update tests for it — ideally Unit, Functional, and Acceptance"
metadata: 
  node_type: memory
  type: feedback
  originSessionId: e145a0ea-8e8c-44b5-8222-d6fbbe333721
  modified: 2026-09-22T23:37:08.858Z
---

After every code change (bug fix, new feature, refactor), create or update tests covering it — ideally across all three Codeception suites: Unit, Functional, and Acceptance.

**Why:** The user explicitly asked for this as a standing workflow rule, not a one-off request. It matches the pattern already established in this project's sessions: every fix (cycle-guard, self-pairing guard, `untilNow` duplicate guards for Name/Wedding/Relation) got a matching `*Cest.php` test, verified passing via `docker compose exec ft-web vendor/bin/codecept run <Suite>` before being reported done.

**How to apply:**
- Default to writing a Functional test (real HTTP via PhpBrowser) for form/validation logic — this has been the most reliable suite in this project (Unit suite has a known limitation: `newContainerForEachTest: true` breaks on presenters with many singleton-registered Card controls when a single test dispatches 2+ requests, e.g. `Person:edit`).
- Add a Unit test too when the logic can be exercised without that singleton-controls pitfall.
- Add/extend an Acceptance (Selenium) test when the behavior depends on JS (Ublaboo inline-edit AJAX, client-side validation, datepicker interactions) — this is the one category most likely to be skipped if not explicitly prompted, so don't drop it by default.
- If a suite is genuinely not applicable (e.g. no reasonable way to hit the code from real HTTP), say so explicitly rather than silently skipping it.
- Always run the modified/new test plus the full suite it lives in before reporting a task done, and clean up any throwaway verification scripts afterward.
- **Gotcha:** in the Functional suite, `$I->see($text)` failed with `strip_tags(): Argument #1 ($string) must be of type string, null given` on a large (~1.8MB) rendered page, even though the identical URL worked fine via plain `curl` — no existing test in the project used `$I->see()` before this (all asserted DB state after `submitForm` instead). When asserting on rendered page *content* (not just DB state), use `$I->grabPageSource()` + `$I->assertStringContainsString(...)` instead of `$I->see()` — confirmed reliable where `see()` wasn't.
- **Gotcha:** the literal string `"Žádné položky nenalezeny"` ("No items found") appears on *every* page in this app regardless of any Ublaboo grid's actual content — it belongs to the global search widget's JS-embedded no-results template, not to a specific grid's empty state. `assertStringNotContainsString('Žádné položky nenalezeny', ...)` as a "grid has data" check is a no-op that always passes/fails independent of the grid. Assert on specific expected data instead (e.g. a unique test-fixture surname), never on the absence/presence of that generic phrase.

**Test FIRST, then change (user, 2026-10-03, applies to family-tree, rendix2-web-project AND alfred):** before changing existing behaviour (refactor, library removal, "fix"), first write/extend a characterization test, run it against the UNCHANGED code and see it pass (or pin the known bug on purpose); only then change the code and re-run the same test. Never rely on "I checked it by hand once" - hand-made curl/Guzzle scripts are fine for exploring, but the result must end up as a Cest. This lifts the earlier "no tests for Alfred for now" decision (2026-10-03: the user asked to check/extend tests before the FreezyBee removal in alfred). All three projects should look the same: Functional + Acceptance (Selenium) suites, same step-actor pattern (Root/PodZatec style testers), same `AGENTS.md` wording. web-project was thought to lack Selenium (its compose does have a `selenium-chrome` container) - check what its Acceptance suite really has and bring it level with family-tree.


**Status 2026-10-03 (late) - "every page answers 200" Functional smoke test now exists in ALL three projects** (the user: extremely important, Acceptance/Selenium tests do not notice a 500/404 behind a redirect or flash): family-tree `tests/Functional/WebPagesSmokeCest` (+ `ApiEndpointsSmokeCest` for the JSON API; `/web.rodokmen-import/` added, `TreePresenter` is signal-only so no page), rendix2-web-project `tests/Functional/WebPagesSmokeCest` (new: public + logged-in + admin pages, 14 URLs; Functional 33/33), alfred has its own per-page Functional Cests. web-project ALREADY had Selenium (compose `selenium-chrome`, Acceptance FrontendSmokeCest + LoginCest 3/3 green) - nothing to set up. Router gotcha in web-project: URLs with an id/uuid are `/<presenter>/<action>/<id>`, so `/admin.role.edit/default/1`, not `/admin.role.edit/1` (that is a 404 "No route"/"Action name is not valid", i.e. a test bug, not an app bug). Alfred Unit grew to 69 (GeminiModelBlocklistCest, WebAuthenticator case-insensitive lookup). Acceptance suites assume a fresh DB (`make db`), alfred EventCest inline edit/add fails on a second run by design.
