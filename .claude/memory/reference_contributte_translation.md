---
name: reference-contributte-translation
description: "contributte/translation API details - this project's translator, underused features (prefixed translator, wrappers, named parameters)"
metadata:
  node_type: memory
  type: reference
  originSessionId: e145a0ea-8e8c-44b5-8222-d6fbbe333721
  modified: 2026-09-23T00:12:04.518Z
---

This project already runs on `contributte/translation` (see [[feedback_translations_required]] for the "always use the translator" rule). Docs: https://contributte.org/packages/contributte/translation.html

**Underused features worth reaching for:**

- **Named parameters, not positional.** The docs' pattern is `$translator->translate('user.name.taken', ['name' => 'Ales'])` with a `%name%` placeholder in the message string, and `{_user.name.taken, [name => "Ales"]}` in Latte. This project's existing code (e.g. `RelationLengthService`, `PersonFilter`) instead passes a bare int as the second arg (`$translator->translate($key, $years)`) relying on `%d`/`%count%` positional/plural substitution — that's a different (also valid) Symfony Translator mechanism for pluralization, not the named-parameter one. Don't confuse the two: use the named-array form for arbitrary dynamic values in a message (e.g. a person's name, a count that isn't driving plural-form selection), and the positional/count form only for actual plural-form selection (`_1`/`_2`/`_5` key suffixes, matching `getPluralKey()` in `RelationLengthService`).
- **`createPrefixedTranslator($domain)`** — returns a translator pre-bound to a domain prefix, so calls become `$prefixed->translate('message')` instead of `$translator->translate('domain.message')` everywhere. Needs the concrete `Contributte\Translation\Translator`, not the base `Nette\Localization\Translator` interface, to be type-hinted for this method to exist.
- **`Contributte\Translation\Wrappers\NotTranslate`** — wrap a string passed to a form select's `setItems()` (or similar) to mark it as *not* needing translation, when mixed with real translation keys. Relevant for this app's various `Select`/`getXOptions()` helpers if any of them mix literal untranslated labels with translation keys.
- **`Contributte\Translation\Wrappers\Message`** — pass a translation key + params to something like `->setOption('description', new Message('form.mail.description', [...]))` so a component can defer/re-render the translation at output time instead of eagerly translating too early (avoids double-translation bugs).
- **`TranslationProviderInterface`** — a `CompilerExtension` can implement this to register its own translation resource directory, useful if a third-party bundled extension/module ever needs to ship its own translation files.

**How to apply:** when a future translation-key task involves a dynamic value that ISN'T selecting a plural form (e.g. inserting a person's name, a town name, a count used only for display), prefer the named-array parameter form over inventing a new sprintf-style key. Reserve the `_1`/`_2`/`_5` plural-key-suffix pattern for genuine count-driven pluralization, matching what `RelationLengthService::getPluralKey()` already does.
