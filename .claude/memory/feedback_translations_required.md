---
name: feedback-translations-required
description: "All user-facing text must go through the translator (contributte/translation), never hardcoded strings"
metadata: 
  node_type: memory
  type: feedback
  originSessionId: e145a0ea-8e8c-44b5-8222-d6fbbe333721
  modified: 2026-09-22T21:55:11.861Z
---

Every piece of user-facing text (labels, flash messages, validation errors, badges, headings, button text) must go through the translator — never a hardcoded Czech/English string literal in PHP or Latte.

**Why:** The user explicitly called this out as a standing rule. The project already runs on `contributte/translation` with JSON domain files under `app/Translations/<domain>.<locale>.json` (cs default, en supported), and the established pattern for new features has always been: add matching keys to both `<domain>.cs.json` and `<domain>.en.json`, then reference them via `{_domain.key}` in Latte or `$translator->translate('domain.key')` in PHP.

**How to apply:**
- When adding any new UI text (a new card, a new validation message, a new badge/label), add the key to both the `.cs.json` and `.en.json` files for the relevant domain before writing the template/PHP that uses it — never inline a literal string like `'Svatba'` or `"Item not found"` directly in a `.latte` file or as a hardcoded PHP string shown to the user.
- If a suitable domain doesn't exist yet, create a new `<domain>.cs.json`/`<domain>.en.json` pair (translation.neon globs `%translationsDir%`, so new domain files are picked up automatically with no extra config) — see `couple_timeline.cs.json`/`.en.json` for a precedent.
- Reuse an existing key across domains when the text is generic (e.g. `person.child` reused for a "child" role label in the couple timeline) rather than duplicating the same string in multiple domains.
- This applies even to internal/admin-only strings and flash messages, not just prominent page text — the whole app is meant to work in both cs and en.
