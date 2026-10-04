---
name: feedback-get-template
description: never use the magic ->template on presenters/controls; call getTemplate() (also in ~/rendix2-web-project, which is the shared project template)
metadata:
  type: feedback
---

Use `$this->getTemplate()->xxx = $yyy;` instead of `$this->template->xxx = $yyy;` (same for `$presenter->template`, `$control->template`, `->template->setFile()/render()`).

**Why:** user's rule (2026-09-26), same family as the ban on `->presenter`/`->values`/`->name` SmartObject magic. Done in both `family-tree` (179 places) and `rendix2-web-project` (7 places).

**How to apply:** `getTemplate()` returns the generic `Nette\Application\UI\Template` interface, so for Latte-only methods (`setTranslator()`, `setFile()`) first check `instanceof Nette\Bridges\ApplicationLatte\Template` (see BasePresenter::beforeRender). `~/rendix2-web-project` is the TEMPLATE project: new practices found in other projects are returned there (keep its AGENTS.md in sync). See [[feedback_no_smartobject_magic_properties]].
