---
name: project_weblogin_autologin_architecture
description: "web-project's \"stay logged in\" / autologin cookie architecture, its 2026-09-28 review findings and fixes, and the Nette session-expiration-cap gotcha found along the way"
metadata:
  node_type: memory
  type: project
  originSessionId: e145a0ea-8e8c-44b5-8222-d6fbbe333721
  modified: 2026-09-28T21:40:08.477Z
---

web-project (rendix2-web-project) had "Zůstat přihlášen" (stay logged in) completely non-functional
outside the Admin module. Reviewed and fixed 2026-09-28, mirroring the password-recovery review
([[project_password_recovery_review]] if it exists, else see `ForgetPasswordCest`).

**Root causes found (LoginPresenter/LogoutPresenter/Bootstrap.php):**
1. The autologin cookie was set with `expire === null` (session-only) instead of a real duration
   — it died the moment the browser closed, defeating its entire purpose.
2. `$this->getUser()->setExpiration('2 hours')` was unconditional, even when the user checked
   "stay logged in" — the session identity itself was still capped at 2 hours regardless.
3. **The autologin cookie was never consulted anywhere in the public `WebModule`** — only
   `App\UI\Admin\AdminBasePresenter::startup()` checked it (with its own strict 401/403-throwing
   logic). So even with a valid, correctly-persisted cookie, no public page would ever use it to
   re-authenticate a visitor whose PHP session had expired/been cleared.

**Fix:** a new `App\Core\AutoLoginService::tryLogin(Application, IPresenter)`, wired onto
`Nette\Application\Application::$onPresenter[]` in `Bootstrap::runWebApplication()` (fires for
every presenter right after construction, before `run()`). It skips `AdminBasePresenter` instances
(admin already has its own check), skips already-logged-in users, and silently swallows
`AuthenticationException` from a missing/stale cookie — a public page must never break just
because the auto-login didn't work.

**Nette gotcha hit while fixing #2:** `Nette\Http\SessionSection::setExpiration()` compares its
argument against PHP's `session.gc_maxlifetime` ini value (which Nette's `session: expiration:` in
`config/common.neon` sets — here `14 days`) and calls `trigger_error()` (E_USER_NOTICE) if exceeded
— fatal under this project's `tracy.strictMode` (see [[project_strictmode_deprecations]]). Setting
`setExpiration('30 days')` for "stay logged in" therefore 500'd every login with the checkbox
ticked. Fixed by capping the conditional value at `'14 days'` (matching the config exactly is
safe — the check is `$seconds > $max + 3`). The cookie's own duration (`Response::setCookie(...,
'30 days')`) is unrelated and NOT capped by this — only the *session identity* expiration is.
The real long-term persistence for "stay logged in" comes from the separate autologin
cookie+`UserAutoLoginEntity` row + `AutoLoginService`, not from stretching the session itself.

**Also fixed:** `LogoutPresenter` now calls `$this->getHttpResponse()->deleteCookie(...)` after
removing the DB row (previously only the DB row was removed, leaving a permanently-dead cookie in
the browser); a hard `$this->error(..., 400)` for "already logged out" replaced with a graceful
redirect to the login page; raw `$exception->getMessage()` flashes replaced with translated
generic messages / silent `bdump()` logging, matching the password-recovery fix pattern
([[feedback_translations_required]]).

New test: `tests/Functional/LoginLogoutCest.php` — simulates "closing and reopening the browser"
via `$I->resetCookie('PHPSESSID')` (kills the session cookie, keeps the autologin one) then checks
the login page shows the translated "already logged in" flash, proving the `onPresenter` hook
actually re-authenticates on a public page.
