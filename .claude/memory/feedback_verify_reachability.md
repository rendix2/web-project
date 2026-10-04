---
name: feedback-verify-reachability
description: "Before fixing a suspected bug in a private method, verify it's actually called — it might be dead code"
metadata:
  node_type: memory
  type: feedback
  originSessionId: e145a0ea-8e8c-44b5-8222-d6fbbe333721
  modified: 2026-09-23T22:16:02.766Z
---

Before patching logic in a `private function` (especially `addInline*`/`onSubmit`/`onControlAdd`-style callback-registering methods on a Grid/Form class), grep for where it's actually invoked from. A method that "looks like" the live code path can be dead code that no `create()`/public entry point ever calls.

**Why:** Spent effort "fixing" `PersonNameGrid::addInlineEdit()`/`addInlineAdd()` (making their `onSubmit` save `genus`/`wedding`/`dateSince`/`dateTo`/`untilNow` instead of just `name`/`surname`) before discovering `create()` never calls either method — the real inline-edit UI on that exact grid comes from `NameFactory::createInlineEdit()`, a separate, already-correct implementation. The bug was real code, just unreachable; the correct fix was deletion, not patching.

**How to apply:** When a user names a specific buggy method to fix, first `grep -n "methodName"` across the class (and callers of the class) to confirm a call site exists before touching the method body. If there's no caller, say so and propose deletion instead of silently "fixing" dead code — patching unreachable code wastes effort and can mislead a future reader into thinking it's the live behavior.
