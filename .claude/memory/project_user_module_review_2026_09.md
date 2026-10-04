---
name: project_user_module_review_2026_09
description: web-project public User module review 2026-09-29 (Activation/ChangeEmail/ChangePassword/Edit/Registration/View) - auth-check-in-wrong-lifecycle-method bug plus translation/dead-code fixes
metadata:
  node_type: memory
  type: project
  originSessionId: e145a0ea-8e8c-44b5-8222-d6fbbe333721
  modified: 2026-09-28T22:56:30.658Z
---

Reviewed `app/UI/Web/User/{Activation,ChangeEmail,ChangePassword,Edit,Registration,View}` in
web-project, continuing the same review-then-fix pass as
[[project_weblogin_autologin_architecture]] and [[project_admin_module_review_2026_09]].

**Most important finding:** `ChangePasswordPresenter` checked `$this->getUser()->isLoggedIn()`
only in `renderDefault()`. Nette's `Presenter::run()` order is: `startup()` → `action<X>()` →
signal handling (`processSignal()`, which fires a form's `onValidate`/`onSuccess`) → rendering
(confirmed by reading `vendor/nette/application/.../Presenter.php:run()` directly, not assumed) -
so a check placed in `renderDefault()` runs strictly AFTER the form's own submit handler already
ran. It happened to fail safe here (`getUser()->getId()` returns null when logged out, and
`findOneBy(['id' => null])` finds nothing, so it hit `$this->error('user not found')` anyway) but
that was incidental, not an explicit authorization check - a future refactor of the lookup could
easily have made it exploitable. Fixed by moving the check into `actionDefault()` (matching the
already-correct pattern in `ChangeEmailPresenter`/`Edit\EditPresenter`), which runs before signal
handling. General lesson: **an auth check belongs in `actionDefault()`/`startup()`, never only in
`render*()`** - Nette calls signal handlers before rendering, so a render-only check never
protects the form's own submit path, only what gets displayed after.

**Other fixes:** `ActivationPresenter` showed the literal untranslated string `"success"` as its
flash message - the `web-user-activation.{cs,en}.json` files were entirely empty (`{}`), so this
key was never even attempted; also had a pointless `$em->persist($x); $em->remove($x);` pair right
next to each other (persist is a no-op on an already-managed entity, so it did nothing - removed),
and reused the exact same `'User not found'` error message for two different failure reasons
(missing user vs. missing/wrong activation key) - the second one now says `'Invalid activation
key'`. Raw exception messages (same anti-pattern as the Admin module and password-recovery
reviews) fixed in ChangeEmail/ChangePassword/Edit/Registration; `RegistrationPresenter` additionally
had three redundant catches (`DbalException`, `SmtpException`, `Throwable`) all doing the same
thing - merged into one `catch (Throwable)` (the first two are subsumed by it). Translation typos
fixed: `web-user-registration.en.json` had `"Regustration"` and, worse, `"mail.subject": "Registrace"`
(Czech text in the English file, so English users got a Czech email subject); `web-user-changePassword.en.json`
had `"Password histor"`; a dead, unused `mail.success` key (never referenced anywhere) was deleted
rather than fixed. `Edit/default.latte` had two hardcoded Czech-only strings (subtitle + a "back"
link) now translated. Several unused `App\Model\Repository\UserRepository` imports removed
(ChangeEmailPresenter, ViewPresenter, and earlier UserFacade) - it's never actually referenced,
just habitually imported.

New regression tests: `tests/Functional/UserAccountCest.php` (3 tests) - anonymous access to
change-password/change-email is rejected (500 in this always-debug-mode project, see
[[project_strictmode_deprecations]], but asserts `see('403')` for the real intended code), and a
full activation-flow test asserting the translated success message plus that the user really
became active and the activation row was really deleted.
