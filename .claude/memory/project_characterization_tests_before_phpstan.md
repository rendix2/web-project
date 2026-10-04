---
name: project-characterization-tests-before-phpstan
description: user wants characterization tests per area BEFORE fixing PHPStan findings; suite layout, test-infra gotchas and the known bugs pinned in tests
metadata:
  type: project
---

Plan (user, 2026-09-26): phpstan (`vendor/bin/phpstan`, `phpstan.neon` level 10 + strict rules, `app/PHPStan/object-manager.php`) is installed; FIRST write tests pinning today's behaviour per area, ONLY THEN fix PHPStan findings area by area keeping tests green.

**Why:** big refactor-ish fixes (empty(), ternaries, deprecations, property.notFound...) must not silently change behaviour; coverage was thin (Api suite empty, 4 Unit tests).

**How to apply:** tests live in `tests/Functional/WebPagesSmokeCest`, `ApiEndpointsSmokeCest`, `PersonFormCest`, `tests/Functional/Services/*`, `tests/Unit/Model/HelpersCest`; build data with `tests/Support/FamilyFactory` (unique surname per family; DB has a unique (husband,wife) wedding constraint). Bugs found while writing them were FIXED 2026-09-26 (IdHelper for '' ids, person.* translations, PersonTree mapping, getTownsByName, Gedcom import, Malecot kinship) and their tests turned into regression tests; only dead code FromDays.php / NameRenderer::renderAddress*/Town*/Country* remains open (delete, don't patch). Details: technicky-dluh.md item 000. Pattern for pinning a bug: name the test `...Currently...`, fix later, flip it.

Infra gotchas: full Functional run needs `tests/Support/Helper/CloseDatabaseConnection` (Postgres max_connections=100, new DI container per test); StatisticManager must stay ONE test (1 GB memory); never leave a stale `codecept` process running (concurrent runs corrupt `temp/codeception/cache`); GedcomService::import calls updateAll() over all persons (minutes) - stub RecalculateRelationsService in tests. RelationInbreedingGridCest fails pre-existing (data accumulation/pagination). See [[feedback_test_coverage]], [[feedback_verify_reachability]].

**PHPStan progress (2026-09-26):** levels 0-6 are clean (0 errors) with tests green (Unit 29, Functional 195, Acceptance). Levels 7-10 remain (206 / 418 / ? errors), mostly nullable handling - behaviour-affecting, do area by area. Tricks that worked: drive automated rewrites from `--error-format=json` (identifier + message), apply per line, re-run phpstan, revert lines that produce `alwaysTrue/False`; `ignoreErrors` with `identifier:` for dynamic Nette form methods. A wrong `/** @var X $var */` above `find()` hides null checks (PHPStan then reports 'always true'). See technicky-dluh.md item 000.

**Test data cleanup (2026-09-26):** `tests/Support/Helper/CleanupTestData` deletes what each test created (listener on postPersist + `$I->trackCreatedEntity()` for rows made by the web server); it must be listed AFTER NetteDIModule in the suite yml, and keeps the test's EM from `_before` (NetteDIModule discards the container before `_after`). Old accumulated test rows in the dev DB (surnames Fam######, Test####, Person####, Gedtest, Chart, FormAdd) still need a manual delete (the auto-mode classifier blocks mass DELETE via psql) - until then /web.statistic/ is ~85 MB and the WebPagesSmokeCest runs out of memory.

**PHPStan level 8 clean (2026-09-26):** 210 -> 0. Most were `$x = $repo->find($id)` (X|null) used unchecked -> added `if ($x === null) { return; }` (126 places by script) and `return;` after 'not found' flash blocks that fell through. LESSON: a script-inserted null guard can flip semantics where null is a valid state (e.g. duplicate-relation validation in PersonPresenter::relationFormValidate) - always audit inserted guards for later `=== null` / `!== null` checks of the same variable. Level 9 (~420) and 10 (~910) remain (mixed / dynamic Doctrine props).

**PHPStan level 9 clean (2026-09-26):** 208 -> 0. Biggest win: stub `phpstan/stubs/doctrine-form-mapper.php` (outside app/ - RobotLoader would index it) makes `DoctrineFormMapper::save()` generic instead of mixed (-130 errors). Real bug found: `SourceTypeAddSourceModal` mapped the form into SourceTypeEntity instead of SourceEntity. Level 10 remains (~470).

**PHPStan level 10 clean (2026-09-26):** 473 -> 0; `make phpstan` passes at level 10. Typed form input goes through `FormValue::string|stringOrNull|bool|intOrNull` (+ `IdHelper`, `DateTimeHelper::toImmutableOrNull(mixed)`), never `(string)`/`(int)` casts of mixed. `phpstan.neon` ignoreErrors deliberately cover only: dynamic Nette form methods/controls reached by path (`$this['form-field']` is mixed for phpstan-nette), Nette event property types, datagrid `$sort` arrays, Google API client magic. Test-suite caveat unchanged: WebPagesSmokeCest needs the bloated dev DB cleaned.
