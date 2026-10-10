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
  ublaboo/datagrid, Contributte (console, forms-bootstrap, translation, mail, mailing, redis, guzzlette),
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
  - Auth hardening (2026-10-09): the "stay logged in" cookie holds a token, the DB only its SHA-256 hash (`UserAutoLoginEntity::$tokenHash`, unique, `validUntil` 30 days, rotated on every use - `Core/AutoLoginTokenService`); `Core/AutoLoginService` (on `Application::onPresenter`) logs out a user that was deactivated/deleted at the very next request and deletes his tokens. Failed-login limits are case-insensitive per username; a successful login clears only the attempts of that username, never of the IP. Usernames and e-mails are case-insensitive (`UserRepository::findOneByUsername/findOneByEmail`, e-mails stored lower case; no DB index on lower(username)). The e-mail of an account changes in two steps (`Service/UserEmailChangeService`: confirmation link to the NEW address, hashed token, warning to the old one, `ConfirmEmail` presenter).
  - `Forms/IconBootstrapForm` + `Forms/Renderer/IconBootstrapRenderer` - every form is an `IconBootstrapForm`; an icon is added with `->setOption(IconBootstrapRenderer::ICON, 'fas fa-user')` on any control (input -> Bootstrap 5 input group with the icon addon, submit/button -> `<button>` with the icon before the caption). Do not write `getControl()` wrappers or an `IconSubmit` class. `IconInputGroupCest` (Unit) and `FormIconsCest` (Functional) guard it.
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
- **Every Doctrine join is selected:** `->addSelect('_town')->leftJoin('_person.birthTown', '_town')`, never a bare `join()`/`innerJoin()`/`leftJoin()`. A join without `addSelect` only filters: the joined entity stays a lazy proxy and each read of it costs a query (N+1). Exempt are queries that select scalars/aggregates (`select('_town.name')`, `COUNT(...)`) and a join that only filters a COLLECTION (selecting it would load just the filtered rows into the collection) - mark it `@join-filter-only` in the statement, or better use `:x MEMBER OF e.collection` (no join). `tests/Unit/JoinsAreSelectedCest` scans `app/` and fails on a violation (PQL has its own SQL engine and no Doctrine: not applicable).
- **Form <-> entity mapping is an `XFormMapper`, not code in the presenter:** a form that is shown from / saved to an entity has a mapper in `app/Forms/Mapper/` (picked up by `search:` in `config/services.neon`, `*Mapper`): `fill(Container $form, XEntity $entity)` sets the defaults (`setDefaults()`, so a submitted form is left alone), `apply(XValues $values, ?XEntity $entity = null): XEntity` writes the typed values onto the entity (a new one when none is given). The mapper maps FIELDS only: a password (hash, history, auto-logins), an e-mail history row, timestamps and flash messages are domain rules and stay in the facade / presenter, which call `apply()` for the rest (`UserFacade::update()` decides "the e-mail changed" BEFORE calling it). Every mapper has a Unit test (`tests/Unit/Forms/Mapper/*Cest`, a recording `DefaultsContainer`, no database) and the pages are pinned by a Functional test (`AdminFormMappingCest`); `FormMappingConventionCest` guards both. A new form gets both.
- **No floats - flex:** the layout of a card (header, body, footer) is a flex row (`d-flex justify-content-between align-items-center gap-2`, the right part `ms-auto`), never `float-*` / `pull-*` / `clearfix` / an inline `float:` (a floated block is not part of its parent's height). `tests/Unit/NoFloatsCest` scans `app/` and `assets/` and fails on a violation (same test in family-tree and alfred; `Bootstrap5MarkupCest` guards the card headers).
- **No traits:** we do not use PHP traits in any project (family-tree, alfred, web-project, PQL). Do not write a new `trait` and do not add `use SomeTrait;` to a class: use a real class (composition: a constructor-injected collaborator, or inheritance when it really is an "is a"). Existing traits are to be removed gradually, not extended (family-tree: `app/ApiModule/Presenters/CheckMethods.php`; PQL: `LimitExecutor`, `WhereQueryBuilder`, `LimitQueryBuilder`, `Offset`, `From`, `QueryPrinter/{Where,Limit,OffsetPrinter}`; alfred and web-project have none).
- **No `@var` on typed properties:** a class property that has a native type does not get a `@var` annotation (it only repeats the type). We are refactoring all projects towards this slowly: remove the annotation whenever you touch such a property, do not add new ones, do not mass-edit untouched files. (`@var` stays only where the native type cannot say it: untyped legacy properties, or `array` contents when no real type exists yet.)
- **Initialise properties in the constructor, never at the declaration:** `private array $items;` + `$this->items = [];` in `__construct()`, not `private array $items = [];` (same for `null`, `false`, `0`). Exceptions are the places that have no constructor of ours: a PHPUnit/Codeception test (`__construct()` is final - initialise in `_before()`), and a PHP constant/enum case.
- **Page title = `<h1>` in the main card header, with an icon:** the first card of every page has the `<h1>` + `<i class="fas fa-…">` in its `.card-header` (list pages: the add button sits in the same header with `ms-auto`); further cards use h2 + icon. `CardHeaderTitleIconCest` (Unit) guards it, incl. that every `fa-*` class exists in the bundled Font Awesome 7.
- **No `Tables` class of table-name constants:** the name of a table is the literal in `#[Table(name: 'chat')]` of its entity (one place) and a literal in a migration (a migration is history: a constant would follow later renames and change what an old migration does). Raw SQL asks `$em->getClassMetadata(Entity::class)->getTableName()` or lists the tables from the database (see `DatabaseOptimizer`).
- **Names of indexes and foreign keys in migrations (web-project):** `K__<Table>__<columns>` (index), `UK__<Table>__<columns>` (unique index), `FK__<Table>__<columns>` (foreign key) - `<Table>` is the real table name with a capital first letter (`User_auto_login`, `Users`), `<columns>` the columns with a capital first letter joined by `_` (`Email_created_at`). Always pass the name explicitly (`addIndex(['user_id'], 'K__User_email__User_id')`, `addForeignKeyConstraint(..., name: 'FK__User_email__User_id')`), never let DBAL generate `IDX_/UNIQ_/FK_` names.
- **Migrations use the Schema API, never hand-written DDL:** `$schema->createTable()`, `getTable()->addColumn()/dropColumn()/addUniqueIndex()/addForeignKeyConstraint()`, `dropTable()`, `PrimaryKeyConstraintEditor` for primary keys - not `$this->addSql('CREATE TABLE ...')` and not a paste of what `migrations:diff` printed (that output is a hint only). `addSql()` is for DATA (an UPDATE/DELETE that runs before the schema changes) and for what DBAL cannot express (`CREATE EXTENSION`, an expression index like `lower(username)`, a `USING` conversion), each with a comment saying why. A conditional step reads the live schema (`$schema->hasTable()`, the column type). Verify a rewrite with a scratch database: migrate before and after, `pg_dump -s` and diff (never touch the real data DB). web-project is not deployed anywhere, so its old migrations may be edited (ids and foreign keys are BIGINT from the start, there is no widening migration).
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
