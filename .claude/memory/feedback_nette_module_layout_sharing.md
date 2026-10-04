---
name: feedback_nette_module_layout_sharing
description: "Making a new Nette module share the main app's @layout.latte needs two explicit fixes, not just \"extends BasePresenter\" - found building WikiTreeModule in family-tree."
metadata:
  node_type: memory
  type: feedback
  originSessionId: e145a0ea-8e8c-44b5-8222-d6fbbe333721
  modified: 2026-09-28T20:04:05.827Z
---

When a new Nette presenter module (own namespace/directory, e.g. `app/WikiTreeModule/`) should
share the site's existing layout (menu, CSS, header) instead of getting its own blank one, TWO
separate things are needed - "just extend the other module's BasePresenter" is not enough, and the
failure is silent (no error, just an unstyled bare page with no menu/CSS - easy to ship by mistake,
which happened here).

**Why:** `Nette\Application\UI\Presenter::formatLayoutTemplateFiles()` looks for `@layout.latte`
based on the CONCRETE presenter class's own file location and the module-name colon-count in its
presenter name - NOT the PHP class inheritance chain. `WikiTree\Presenters\ImportPresenter extends
Web\Presenters\BasePresenter` still only searches inside `WikiTreeModule/templates/` (then generic
parent dirs), never `WebModule/templates/`, regardless of what it extends.

**How to apply:**
1. Give the new module its own `Presenters\BasePresenter` (extending the shared one) whose
   `startup()` calls `$this->setLayout(__DIR__ . '/../../OtherModule/templates/@layout.latte')` -
   an absolute path (contains `/`) is returned as-is by Nette, skipping the directory search.
2. That alone still isn't enough if the shared layout contains its own `{link Xxx:yyy}` calls
   (nav menus almost always do) - those resolve against the CURRENT PRESENTER's module at render
   time, not the template file's origin. Rendered from the new module, every bare
   `{link Homepage:default}` becomes `NewModule:Homepage` and crashes ("presenter not found").
   The shared layout must have every one of its own internal links written as fully absolute
   (`{link :OriginalModule:Xxx:yyy}`), never bare presenter names - do this once, for the whole
   file, the moment it's shared across more than one module.

Verify by actually loading a page from the new module and checking for the menu/CSS in the raw
HTML (`curl | grep`) - a green HTTP 200 is not enough evidence, the broken version also returned 200
with a fully valid but bare, unstyled page.
