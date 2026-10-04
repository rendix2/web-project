---
name: feedback_uniform_cache_convention
description: "Cache/temp/proxy handling must be as simple as possible and IDENTICAL in family-tree, rendix2-web-project and alfred (user, 2026-10-03)"
metadata:
  type: feedback
---

User: cache behaviour must be as simple as possible and, above all, the same across all projects (family-tree, rendix2-web-project, alfred).

**The one rule (implemented 2026-10-03 in all three, uncommitted):** everything regenerable lives in `temp/<context>/cache`; Doctrine proxies use nettrine's default dir (`%tempDir%/cache/doctrine/orm/proxies`, NO `proxyDir`/`proxiesDir` param anywhere) with `autoGenerateProxyClasses: Doctrine\ORM\Proxy\ProxyFactory::AUTOGENERATE_FILE_NOT_EXISTS_OR_CHANGED` (not debug-dependent). `make cached` is the same target in all three (`rm -rf temp/{web,console}/cache; mkdir -p; chmod 777`) and wiping `temp/*/cache` is always safe. No `orm:generate-proxies` anywhere (removed from alfred Makefile + `MigrationPresenter` deploy hook, family-tree `prod.Dockerfile`).

**Why:** the three projects had three different layouts (family-tree proxies outside cache + pre-generated in the prod image; web-project/alfred nested in cache, auto-generation only in debug, alfred's `make cached` was a no-op because `cd` in a recipe line does not persist) and a hand wipe of `temp/` gave 500 "Failed opening required .../__CG__...php" in tests (debug off).

**How to apply:** when touching cache/temp/proxy/Makefile/Bootstrap in one project, change all three the same way and keep the "Cache" section of each `AGENTS.md` word-for-word alike. Related: [[project_alfred_known_issues]].
