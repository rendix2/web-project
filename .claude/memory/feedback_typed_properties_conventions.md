---
name: feedback_typed_properties_conventions
description: Class properties with a native type get no @var annotation, and properties are initialised in the constructor, never at the declaration - slow refactoring of all four projects
metadata:
  type: feedback
---

Two conventions for class properties in family-tree, alfred, web-project and PQL (stated 2026-10-04 while reviewing PQL's `UpdateQuery`/`DeleteQuery` fix in the IDE diff):

1. **No `@var` annotation on a property that has a native type.** "Pomalu refaktorujeme všechny projekty": remove the annotation when you touch the property, do not add new ones, do not mass-edit untouched files.
2. **Initialise in the constructor, not at the declaration**: `private ?IResult $result;` + `$this->result = null;` in `__construct()`, not `private ?IResult $result = null;` (also `= []`, `= false`, `= 0`).

**Why:** the user rewrote my first fix of PQL's BUG-48 (`private ?IResult $result = null;`, trait properties `= []` / `= false`) because it initialised at the declaration. With constructor init the whole object state is visible in one place - this is also how the uninitialised-typed-property bugs (BUG-47/48/61/75) get avoided.

**How to apply:** exceptions where there is no constructor of ours: a PHPUnit/Codeception test (`TestCase::__construct()` is final - initialise in `_before()`), and constants. A trait cannot initialise its own properties: the classes that use it do it in their constructors (until the traits are gone, see [[feedback_no_traits]]). Recorded in each project's AGENTS.md. Related: [[project_pql_overview]], [[feedback_strict_comparison]].
