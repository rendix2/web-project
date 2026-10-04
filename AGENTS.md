# AGENTS.md

Nette 3.2 web project (fork of `nette/web-project`) with user management: registration, activation, login,
password reset, e-mail/password change, admin CRUD of users and roles. PHP 8.3+ (Docker image: `php:8.4-apache`).

## Environment & Execution

- **Docker-only execution:** the host has no PHP/Node. Run everything in the web container (service `web-project-web`):
  ```bash
  docker compose exec web-project-web <command>
  ```
- **Services** (`docker-compose.yml`):
  - Web application: `http://localhost:9180` (docroot `www/`)
  - PostgreSQL 17: `web-project-postgres`, host port 5532 (5432 inside Docker, which is what `config/env/*.neon` uses), DB/user `web-project`, password `password`
  - Redis: `web-project-redis`, port 6379 (db 0 = storage/cache, db 1 = sessions)
  - Adminer: `http://localhost:9182`
- Host ports were shifted (9180 / 5532 / 9182) so the project can run next to `family-tree`, which uses 9080 / 5432 / 9082.
- The project directory is mounted at `/var/www/html` in the container.
- `temp/` and `log/` must be writable (the Makefile `chmod 777`s them).

## Key Commands

```bash
# Console (Contributte Console on top of Bootstrap::runConsoleApplication)
docker compose exec web-project-web php bin/console

# Static analysis (phpstan.neon: level 10, strict rules, doctrine + nette extensions)
docker compose exec web-project-web php vendor/bin/phpstan analyse
docker compose exec web-project-web make phpstan   # NB: the `dirs-build` prerequisite deletes temp/, vendor/, node_modules/!

# Build frontend (Vite, source in assets/, output www/assets - git-ignored)
docker compose exec web-project-web npm run build

# Validate
docker compose exec web-project-web composer validate
docker compose exec web-project-web vendor/bin/latte-lint app/   # if installed
docker compose exec web-project-web find app/ bin/ -name "*.php" -exec php -l {} +
```

### Database & Migrations
```bash
docker compose exec web-project-web php bin/console migrations:migrate --no-interaction
docker compose exec web-project-web php bin/console migrations:generate      # skeleton in app/Database/Migrations/
docker compose exec web-project-web php bin/console doctrine:fixtures:load --no-interaction

# `make db` = create DB + DROP the whole schema + migrate + fixtures (destructive)
docker compose exec web-project-web make db
```
Fixtures: `app/Database/Fixtures/` (`RoleFixture`, `UserFixture`).

### Cache
```bash
# Clear Latte, DI container and Doctrine proxy cache (proxies regenerate themselves)
docker compose exec web-project-web make cached
# or manually: rm -rf temp/web/cache/* temp/console/cache/*
```
Same in all three projects: everything regenerable lives in `temp/<context>/cache` (Doctrine proxies in
`cache/doctrine/orm/proxies`, `autoGenerateProxyClasses` = `FILE_NOT_EXISTS_OR_CHANGED`), so wiping it is always safe and
nothing needs `orm:generate-proxies`.

### Careful with the Makefile
`make` / `make dirs-build` / `make php` / `make phpstan` start with `create-dirs`, which runs
`rm temp -r -f; rm vendor -r -f; rm node_modules -r -f`. Do not run these mid-session unless a full reinstall is intended.

## Architecture & Conventions

- **Framework:** Nette 3.2 (Application, DI, Forms, Security, Latte 3), Doctrine ORM 3 / DBAL 4 via Nettrine 0.10,
  ublaboo/datagrid, Contributte (console, forms-bootstrap, translation, mail, mailing, menu-control, redis, guzzlette),
  `nette/assets` + Vite.
- **Layout of `app/`:**
  - `Bootstrap.php` - builds the `Configurator` for context `web` or `console` (`Bootstrap::boot()`).
  - `UI/Web/...`, `UI/Admin/...`, `UI/Error/...` - presenters grouped **by feature**: each feature folder holds its
    `*Presenter.php`, `default.latte`, value objects for form data (`LoginValues`, `CreateUserValues`, ...),
    datagrids and a `Translations/` folder. Presenter mapping: `*: App\UI\*\**Presenter` (see `config/common.neon`).
  - `Admin` presenters extend `UI\Admin\AdminBasePresenter` (requires login, tries the auto-login cookie, needs role `Admin`).
  - `Core/` - `UsernameAndPasswordAuthenticator`, `AutoLoginAuthenticator`, `RouterFactory`.
  - `Model/Entity/` - Doctrine entities (`UserEntity`, `RoleEntity`, `User*Entity`, `GeoIpCacheEntity`, `MailEntity`),
    `Model/Repository/`, `Model/Facade/UserFacade` (user use-cases, wrapped in transactions).
  - `Database/` - `EntityManagerDecorator`, custom DBAL type `IpAddressType`, `Migrations/`, `Fixtures/`.
  - `Service/GeoIpService` - IP geolocation (ip-api.com via Guzzle, cached in DB for 7 days).
  - `Forms/` - reusable form controls (`UsernameFormControl`, `PasswordFormControl`, `EmailFormControl`).
  - `ConsoleModule/Commands/`, `DI/ServicesExtension`, `PHPStan/object-manager.php` (used by phpstan-doctrine).
- **Routing** (`Core/RouterFactory`): `<presenter>/<action>/<uuid>`, `<presenter>/<action>/<id \d+>`,
  `<presenter>/<action>`; default `Web:Home:default`.
- **DI registration:** RobotLoader indexes `app/`; `config/services.neon` auto-registers classes ending in
  `*Factory`, `*Facade`, `*Grid`, `*Service`. Other services/extensions are declared in `config/common.neon`,
  `config/ext/*.neon` (per package) and `app/DI/ServicesExtension.php`.
  Environment configs: `config/env/{dev,test,prod}.neon`; `config/local.neon` is git-ignored.
- **Persistence:** entities use attribute mapping, public typed properties, `Collection<int, X>` PHPDoc on associations.
  `UserEntity::$id` is a `BIGINT` exposed as `string`; users are also addressed by `uuid`.
- **Security features to keep intact when touching auth code:** login attempt throttling
  (`UserLoginAttemptCheckService`: 5 attempts / 900 s per IP and per username), password history
  (`UserPasswordEntity`), pwned-password check (Cilician), account activation keys, auto-login tokens.
- **Translations:**
  - `contributte/translation`, JSON files `<domain>.<locale>.json` **next to the feature** (e.g.
    `UI/Web/User/Login/Translations/web-user-login.cs.json`); domain = file prefix
    (`web-user-login.form.username.label`).
  - Locales: whitelist `en, cs, sk`, default and fallback `cs`, resolved from the router.
  - Always add matching keys in both `.cs.json` and `.en.json` when adding user-facing text; never hardcode UI strings
    (mail templates have per-locale files, e.g. `registration.cs.latte` / `registration.en.latte`).
- **Frontend:** `assets/app.js` is the Vite entry, output goes to `www/assets` (`nette/assets` mapper `default: type vite`).
  Latte config: `strictParsing`, `strictTypes`.
- **Mail:** dev mails are written to `temp/.../mails` by `FileMailer` (`config/env/dev.neon`); templates via Contributte Mailing.

### Coding conventions
- Do not use `empty()`. Use explicit checks (`=== null`, `=== ''`, `=== []`, ...).
- Always compare with strict operators (`===` / `!==`), never `==` / `!=` (DQL strings excepted).
- Do not use nested ternary operators or the short ternary `?:`; use `match`, if/elseif or a helper.
- Do not use SmartObject magic properties (`$presenter->name`, `$component->presenter`, `$form->values`,
  `$this->template`); call `getName()`, `getPresenter()`, `getValues()`, `getTemplate()`. Assign template variables as
  `$this->getTemplate()->xxx = $yyy;`.
- Do not use `static` methods for anything that needs collaborators (entity manager, repositories, translator);
  make it a constructor-injected service. Stateless, dependency-free helpers are the only exception.
- Every `find()` / `findOneBy()` result is nullable: check it (and stop the request) before use.
- **Bootstrap 5 only:** the UI uses exclusively Bootstrap 5 (5.3). Do not write Bootstrap 4 markup (`btn-block`, `btn-default`, `btn-xs`, `badge-*`, `thead-light`, `ml-`/`mr-`, `text-left`, `form-group`, `custom-*`, `data-toggle`/`data-target`/`data-dismiss`, `font-weight-*`, ...) or use Bootstrap 4 only plugins. `Bootstrap5MarkupCest` fails on such leftovers, `FrontendSmokeCest` (Acceptance) on broken JS.
- **Page title = `<h1>` in the main card header, with an icon:** the first card of every page has the `<h1>` + `<i class="fas fa-…">` in its `.card-header` (list pages: the add button sits in the same header with `ms-auto`); further cards use h2 + icon. `CardHeaderTitleIconCest` (Unit) guards it, incl. that every `fa-*` class exists in the bundled Font Awesome 7.
- Form values are typed: one `final` value class per form next to its presenter (`LoginValues`, `RegistrationValues`, ...), read with `$form->getValues(XValues::class)`; facades take the value class, not `ArrayHash`. Nette writes EVERY non-omitted control into the property of the same name (text inputs return `string`, selects `int|string|null`, checkboxes `bool`). Datagrid inline edit rows use a value class too (`setMappedType(XInlineEditValues::class)` in `onControlAdd`, `#[\AllowDynamicProperties]` because the datagrid adds `_id` and the submit buttons to the same container); a row with validation errors is silently treated as *cancel*.
- PHPStan runs at level 10 with strict rules; keep it green and prefer fixing the code over adding `ignoreErrors`.

## Testing
- `nette/tester` is required in `composer.json` (script `composer tester` -> `tester tests -s`), but there is **no `tests/` directory yet**.
  Add tests for behaviour before refactoring anything security-related (login, registration, password reset).

## Known caveats (observed, not fixed)
- `Bootstrap::boot()` calls `$configurator->setDebugMode(true)` unconditionally, after the IP/secret based mode was set,
  so Tracy and debug mode are always on. The `test.` environment branch (`REQUEST_URI` starting with `https://test.`) can never match.
- Login returns different messages for unknown user / inactive user / wrong password (account enumeration).
- `GeoIpService` calls `http://ip-api.com` (plain HTTP).
- `composer.lock` and `package-lock.json` are untracked; `composer.lock` is owned by `root` (created inside the container).
- The autologin cookie handling is duplicated in `AdminBasePresenter` and `LoginPresenter`.

## Tests (Codeception, same setup as family-tree)

- Suites: `tests/Unit` (NetteDIModule + NetteApplicationModule), `tests/Functional` (PhpBrowser against `http://web-project-web`), `tests/Acceptance` (WebDriver -> `selenium-chrome`, Chrome; VNC 5901, noVNC 7901, Selenium 4544). Config `codeception.yml`, `tests/*.suite.yml`, container config `config/codeception/{common,local}.neon`.
- Run inside the web container: `docker compose exec web-project-web vendor/bin/codecept run Unit|Functional|Acceptance` (or `make unit|functional|acceptance|test`).
- `tests/Support/Helper/CleanupTestData.php` deletes what a test created (entities persisted through the test's EntityManager are recorded automatically; rows created by the web server are registered with `$I->trackCreatedEntity(UserEntity::class, $id)`). It must be listed AFTER `NetteDIModule` in the suite yml. The tests run against the dev database and use the fixture admin `TestUsername` / `secret`.
- `tests/Support/DI/HttpExtension` + `Http/Request` add `isFrom()` (nette/application 3.3) to the Codeception request. Ajax requests need the headers `Sec-Fetch-Site: same-origin` and `X-Requested-With: XMLHttpRequest`.
- `config/services.neon` includes the `ext/*.neon` files relative (not via `%configDir%`) so the test container can load it.
- Datagrid inline edit is submitted as `?do=grid-filter-submit` with `inline_edit[...]` fields; a row with validation errors is silently treated as cancel, so assert the saved value.

- **Lint:** `docker compose exec web-project-web make lint` (latte-lint, neon-lint, `php -l`, `composer validate`); CI (`.github/workflows/ci.yml`) runs it together with PHPStan and the front-end build.
