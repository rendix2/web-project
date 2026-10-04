---
name: feedback_always_real_form
description: "Every form-like UI element in family-tree (and web-project) must be a real registered BootstrapForm class, never a raw <form> in Latte."
metadata:
  node_type: memory
  type: feedback
  originSessionId: e145a0ea-8e8c-44b5-8222-d6fbbe333721
  modified: 2026-09-28T12:24:35.021Z
---

Never take the shortcut of writing a raw `<form method="get">...</form>` directly in a Latte
template with manual `<select>`/`{foreach}` option loops. This happened in `RelationshipPresenter`
(and, by copy-paste, `ComparisonPresenter`/`LineagePresenter`/`FamilyBranchPresenter` too) and the
user called it out as a forbidden state: *"Nevytvořil si vůbec BootstrapForm... To je zakázaný stav.
Vždycky musí existovat formulář."*

**Why:** every other form in the app (`PersonForm`, `WeddingForm`, `CountryForm`, ...) is a real
`Contributte\FormsBootstrap\BootstrapForm`-returning class in `app/controls/forms/`, registered in
`DI/FamilyTreeExtension.php` (the project's default place for new services - see AGENTS.md), wired via
`createComponentXxxForm()` on the presenter. A raw `<form>` bypasses Nette's validation, translation,
CSRF/signal handling, and the project's whole form-value-DTO convention
([[feedback_form_value_objects]]) - it's an inconsistent, untestable one-off.

**How to apply:** even for a trivial GET, bookmarkable "picker" form (e.g. choose a person and submit
via query string), still create a real `XxxForm` class with a `create(): BootstrapForm` method
(`setMethod(Form::Get)`, `BootstrapForm::switchBootstrapVersion(BootstrapVersion::V5)`), register it in
`FamilyTreeExtension.php`, add `createComponentXxxForm()` to the presenter, and render it in the
template with `{form xxxForm}` / `{input fieldName}` (not `{control}`, when the existing custom
two-column/icon layout must be preserved exactly). Tom-select (`class="selectpicker"`, `setPrompt()`
for the placeholder) still works fine through `{input}` - see `app/controls/forms/ComparisonForm.php`,
`RelationshipForm.php`, `LineageForm.php`, `FamilyBranchForm.php` for the pattern.

**Gotcha found while fixing this:** a `Nette\Application\UI\Form` component (unlike a plain
`<form>`) adds a hidden `do=<name>-submit` field. On GET forms this makes Nette redirect once to
strip the `do=` param after processing the signal, *in addition* to any pre-existing
default-value-canonicalization redirect (e.g. a select with `setDefaultValue()` matching the render
method's own default argument). Two chained redirects instead of one is enough to break a Selenium
`waitForElement(...)` + immediate `seeInCurrentUrl(...)` assertion (the element it waits for already
existed pre-navigation, so it doesn't actually wait for the redirect chain to finish) - use
`$I->waitForJS("return window.location.search.indexOf('foo=123') !== -1;", 10)` instead when a GET
form test checks the resulting URL. See `tests/Acceptance/PersonSelectFormsCest.php`.

**Second gotcha, found 2026-09-28 building WikiTreeSearchForm:** a "read values from the URL in the
presenter, not from the form" GET form (Comparison/Relationship/Lineage/FamilyBranch/WikiTreeSearch
all do this) still needs a **real `onSuccess` (or `onSubmit`) handler on the Form object itself**,
even though it does nothing. `Nette\Forms\Form::fireEvents()` throws `trigger_error("... no
associated handlers ...")` - fatal under this project's `tracy.strictMode: true` - unless
`onSuccess`/`onSubmit` is non-empty OR the submit button has its own `onClick`. The 4 earlier forms
never hit this only by accident: their templates render a hand-written `<button type="submit">`
with no `name="send"`, so Nette can't identify which button submitted and falls back to a
`submittedBy = true` shortcut that also satisfies the check. The moment a form like this is
rendered via `{control formName}` (real submit button, real name) instead of manual `{input}`
fields, the accident stops covering for it and it crashes. Fix: always add a no-op
`$form->onSuccess[] = static function () : void {};` in `create()` for any such form, regardless of
which way it gets rendered - all 5 existing ones now have it.
