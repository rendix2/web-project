---
name: feedback_verify_datagrid_docs_by_running
description: datagrid (and similar library) docs are old/unclear - verify against the library code, the app code and the real result in a browser; do not use web-project as a template
metadata:
  node_type: memory
  type: feedback
---

User (2026-10-04): the datagrid documentation was always bad, unclear and outdated - check documentation against the REAL state (the datagrid's code, the application's code and the actual result), and "web-project may be wrong, do not copy from a wrong source as a template".

**Why:** the docs/UPGRADE.md said `Contributte\Datagrid\DataGrid` and "contributte/datagrid", real Packagist package is `ublaboo/datagrid` 7.2.0 with class `Datagrid`; forms-bootstrap renders BS4 until `switchBootstrapVersion(V5)`; Bootstrap 5's smooth scrolling broke Selenium clicks - none of this was in any doc, all found by running.
**How to apply:** after any change to datagrid/forms/Bootstrap run GridExerciserCest + Acceptance + look at screenshots (`makeScreenshot`, tests/_output/debug), read the vendor source for names (`grep` the exports), and pick the reference implementation by checking it works, not by assuming web-project is right.
