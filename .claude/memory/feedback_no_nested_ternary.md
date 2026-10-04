---
name: feedback-no-nested-ternary
description: "Never use nested ternary operators — use match, if/elseif, or a helper method instead"
metadata:
  node_type: memory
  type: feedback
  originSessionId: e145a0ea-8e8c-44b5-8222-d6fbbe333721
  modified: 2026-09-22T23:44:23.670Z
---

Do not write nested ternary operators (`$a ? $b : ($c ? $d : $e)`). Use `match`, an if/elseif chain, or a small helper method instead.

**Why:** The user explicitly called this out as a standing rule, prompted by a nested ternary in `WeddingInbreedingGrid`/`RelationInbreedingGrid`'s badge-class renderer (`$blood >= 25 ? 'badge-danger bg-danger' : ($blood >= 6 ? 'badge-warning bg-warning text-dark' : 'badge-info bg-info')`). Nesting hides the branching structure and is easy to misread at a glance, especially once a third or fourth tier gets added later.

**How to apply:**
- A single flat ternary (`$a ? $b : $c`) is still fine — this rule is about *nesting* one ternary inside another's branch, not ternaries in general.
- For 3+ tiers of a scalar-result decision (like a threshold-based CSS class or badge), prefer PHP 8's `match (true) { $blood >= 25 => ..., $blood >= 6 => ..., default => ... }` or an explicit if/elseif chain.
- Also documented in this project's `AGENTS.md` under "Coding conventions" (alongside the `empty()` and `static` rules), so it's visible to any Claude Code session working in this repo directly, not just recalled from memory.
