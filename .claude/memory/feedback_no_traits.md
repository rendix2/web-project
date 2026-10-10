---
name: feedback_no_traits
description: No PHP traits in any project (family-tree, alfred, web-project, PQL): use real classes; existing traits are removed gradually, never extended
metadata:
  type: feedback
---

We do not use PHP traits in any project. Do not write a new `trait` and do not add `use SomeTrait;` to a class: use a real class - composition (a constructor-injected collaborator) or inheritance when it really is an "is a". Existing traits are to be removed gradually ("do budoucna se zbavíme traitů", 2026-10-04), not extended.

**Why:** the user's rule for all four projects (stated 2026-10-04 while fixing PQL's `WhereQueryBuilder` trait). Traits hide dependencies and shared state (e.g. PQL's `WhereQueryBuilder` kept `$whereConditions` uninitialised in UPDATE/DELETE and declared `where(): SelectQuery` for builders that are not SelectQuery - BUG-47/48/61), the same objection as static helpers and magic properties, see [[feedback_get_template]] and [[feedback_no_smartobject_magic_properties]].

**How to apply:** also in tests (PQL tests use the base class `Tests\Support\PqlTestCase`, not trait helpers). Known traits left: family-tree `app/ApiModule/Presenters/CheckMethods.php`; PQL `QueryExecutor/LimitExecutor`, `QueryBuilder/{WhereQueryBuilder,LimitQueryBuilder,Offset,From}`, `QueryPrinter/{Where,Limit,OffsetPrinter}`; alfred and web-project have none. When touching code that uses one, fix with the minimum and say it is to be replaced by a class; do not convert unasked. Recorded in each project's AGENTS.md. See [[project_pql_overview]].
