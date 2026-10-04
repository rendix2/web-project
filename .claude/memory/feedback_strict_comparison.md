---
name: feedback-strict-comparison
description: always compare with === / !== in PHP (and JS), never == / !=
metadata:
  type: feedback
---

Always use strict comparison (`===`, `!==`), never loose `==` / `!=`.

**Why:** the user's explicit rule (2026-09-26); loose comparison hides type bugs (same family as the ban on `empty()` and nested ternaries, and the `''` vs int id bug that broke saving persons).

**How to apply:** in every new or touched line of code, tests and JS included. When an existing `==` is encountered while editing, convert it (check the operand types first - a number-vs-numeric-string comparison needs an explicit cast, e.g. via IdHelper::toIntOrNull()). Listed with the other conventions in AGENTS.md. See [[feedback_no_nested_ternary]].
