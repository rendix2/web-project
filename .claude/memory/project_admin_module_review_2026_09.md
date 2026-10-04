---
name: project_admin_module_review_2026_09
description: web-project Admin (User/Role management) review 2026-09-29 - critical ManyToMany cascade-remove data-loss bug and a batch of translation/dead-code/raw-exception fixes
metadata:
  node_type: memory
  type: project
  originSessionId: e145a0ea-8e8c-44b5-8222-d6fbbe333721
  modified: 2026-09-28T22:06:24.612Z
---

Reviewed `app/UI/Admin/**` (User + Role CRUD, grids) in web-project, the same review-then-fix
pattern as [[project_weblogin_autologin_architecture]] and the earlier password-recovery pass.

**Critical (data loss, now fixed):** `RoleEntity::$users` and `UserEntity::$roles` (the ManyToMany
between them) both carried `cascade: ['persist', 'remove']`. For a ManyToMany, Doctrine's
`cascade: 'remove'` cascades an actual ENTITY remove, not just a join-table row delete - removing
one User cascade-deleted every Role they had, which in turn cascade-deleted every OTHER user who
shared that role (and transitively further). Deleting a single user or role from the Admin grids
could silently wipe out arbitrary unrelated accounts, including other Admins. Confirmed empirically
(not just by reading the mapping) with a throwaway test: `$role->id` throws
"must not be accessed before initialization" after `$em->remove($userWithThatRole); $em->flush();`
because Doctrine unsets a removed entity's identifier post-flush - that crash IS the proof the
cascade fired. Fixed by dropping `'remove'` from both `cascade` arrays (kept `'persist'` only) -
Doctrine already cleans up the `user_role` join-table rows on its own without needing entity-level
cascade. No migration needed (cascade is ORM metadata, not a DB constraint). Regression test:
`tests/Functional/AdminCest.php` (2 tests, both fail loudly pre-fix, pass post-fix). **Lesson:
never put `cascade: ['remove']` on a ManyToMany between two independently-meaningful entities in
this codebase (or any Doctrine project) - it silently means "delete the other side too," not
"delete the association." Only appropriate for true owned child collections (OneToMany, like
UserEntity's passwords/emails/autoLogins).**

**Translations gap:** `admin-role-list.{cs,en}.json` were almost completely empty (only `add-role`,
and the English one had a typo, "ADd Role") despite `RoleDataGrid`/`default.latte` referencing 13+
keys - the whole Role List grid (headers, edit/delete buttons, inline edit, success messages)
rendered as raw untranslated keys. Also found: `Role/List/default.latte` had hardcoded, wrong,
copy-pasted text "Seznam uživatelů" (List of *users*) on the Role list page (should say roles);
`admin-role-create.cs.json`'s success message used placeholder `%role%` but the code passes `name`,
so the Czech flash literally showed "%role%" instead of the role's name (English version was
correct); `UserDataGrid`'s inline-edit isActive select used hardcoded English `no`/`yes`/`select`
instead of the existing `messages.no/yes/select` keys already used by the same grid's own filter;
delete-confirmation dialogs and the User-Edit role-assign grid's add/remove flashes were hardcoded
English sentences, never translated. All fixed; new shared `messages.operationFailed` key added
for the raw-exception-message anti-pattern (see below), reused across every Admin catch block.

**Raw exception messages shown to the user:** found in ~8 places across both Create/Edit presenters
and all three datagrids (Role List, User List, User Edit's role-assign grid) -
`$this->flashMessage($exception->getMessage(), 'danger')`. Same anti-pattern already fixed twice
this session (password recovery, login/logout) - replaced everywhere with `bdump($exception)` +
the new translated `messages.operationFailed` flash.

**Broken query:** `User/Edit/RoleDataGrid`'s role-name filter built `andWhere('_role.name')` with
an unused bound parameter - not valid DQL, filtering the "assign role" grid by name was a no-op/
broken. Fixed to match the working pattern already used in `Role/List/RoleDataGrid`
(`REGEXP(_role.name, :roleName) = true`).

**Dead code deleted (not "fixed into a feature"):** `UserFacade`'s `createPasswordRequest()`,
`changeEmail()`, `changePassword()` and their now-orphaned private helpers were entirely unreachable
(grepped for callers first, per [[feedback_verify_reachability]] - zero hits; the real password-reset
flow lives directly in `RequestPresenter`/`ChangePresenter`). Large commented-out blocks in
`User/Edit/EditPresenter::editFormOnValidate`/`editFormSuccess` were dead leftovers from before the
code moved into `UserFacade::update()` - the password-reuse check they duplicated is already
enforced live by `PasswordFormControl`'s own validation rule (`setHistoryUser()`), so nothing was
lost by deleting them. Also removed: a broken/dead `allowDelete` self-protection stub in
`Role/List/RoleDataGrid` (compared a Role's id to the current User's id - never made sense, was
already commented out), leftover `bdump('OK')`/`bdump('FAIL')` debug calls in two grids' inline-edit
handlers, and a few pre-existing unused imports (`UserRepository` in `UserFacade`,
`UserPasswordEntity`/`UserEmailEntity` cross-imported into the wrong grid classes).

**Gotcha for next time:** `Nette\Application\UI\Component::error()` (not just `Presenter::error()`)
is a real, valid method any Datagrid/Control can call - don't mistake `$this->grid->error(...)` in
a datagrid class for a bug; verified via `ReflectionMethod` before nearly reporting a false
positive.
