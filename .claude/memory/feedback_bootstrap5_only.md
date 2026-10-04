---
name: bootstrap5-only
description: Both family-tree and web-project use exclusively Bootstrap 5 - never write Bootstrap 4 markup or BS4-only plugins
metadata:
  type: feedback
---

Both projects (family-tree, ~/rendix2-web-project) use ONLY Bootstrap 5.3. No `btn-block`/`btn-default`/`btn-xs`/`badge-*`/`thead-light`/`ml-`/`text-left`/`form-group`/`custom-*`/`data-toggle`, no bootstrap-select (replaced by tom-select on `select.selectpicker`).

**Why:** the BS5 upgrade silently broke several pages (person pickers, table headers, statistic cards); the user asked for a full sweep and wants it recorded.

**How to apply:** new templates/grids use BS5 classes; `tests/Unit/Bootstrap5MarkupCest` guards it in both projects. See [[project_frontend_js_bootstrap5]].
