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
# Console (Contributte Console on top of Bootstrap->runConsoleApplication())
docker compose exec web-project-web php bin/console

# Static analysis (phpstan.neon: level 10, strict rules, doctrine + nette extensions)
docker compose exec web-project-web php vendor/bin/phpstan analyse
docker compose exec web-project-web make phpstan   # NB: the `dirs-build` prerequisite deletes temp/, vendor/, node_modules/!

# Coding standard (ruleset.xml, contributte/qa adjusted; see "Coding standard (phpcs)")
docker compose exec web-project-web make qa       # parallel-lint + phpcs
docker compose exec web-project-web make cs-fix   # phpcbf

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
  - `Service/GeoIpService` - IP geolocation (`https://ipwho.is/` via Guzzle, no key, cached in DB for 7 days; private/loopback/reserved addresses are never sent out). Until 2026-11-18 it was ip-api.com, whose free plan has no HTTPS - do not go back to it. `GeoIpServiceCest` (Integration) pins the HTTPS base URI.
  - `Service/BrowserNameService` - the browser name of the current request (`null` without a `User-Agent`; `UserAgentParser` throws on an empty one, which used to be a 500 of the login).
  - `Forms/` - reusable form controls (`UsernameFormControl`, `PasswordFormControl`, `EmailFormControl`).
  - Auth hardening (2026-10-09): the "stay logged in" cookie holds a token, the DB only its SHA-256 hash (`UserAutoLoginEntity::$tokenHash`, unique, `validUntil` 30 days, rotated on every use - `Core/AutoLoginTokenService`); `Core/AutoLoginService` (on `Application::onPresenter`) logs out a user that was deactivated/deleted at the very next request and deletes his tokens. Failed-login limits are case-insensitive per username; a successful login clears only the attempts of that username, never of the IP. Usernames and e-mails are case-insensitive (`UserRepository::findOneByUsername/findOneByEmail`, e-mails stored lower case; no DB index on lower(username)). The e-mail of an account changes in two steps (`Service/UserEmailChangeService`: confirmation link to the NEW address, hashed token, warning to the old one, `ConfirmEmail` presenter).
  - `Forms/IconBootstrapForm` + `Forms/Renderer/IconBootstrapRenderer` - every form is an `IconBootstrapForm`; an icon is added with `->setOption(IconBootstrapRenderer::ICON, 'fas fa-user')` on any control (input -> Bootstrap 5 input group with the icon addon, submit/button -> `<button>` with the icon before the caption). Do not write `getControl()` wrappers or an `IconSubmit` class. `IconInputGroupCest` (Unit) and `FormIconsCest` (Functional) guard it.
  - `ConsoleModule/Commands/`, `DI/ServicesExtension`, `PHPStan/object-manager.php` (used by phpstan-doctrine).
- **Routing** (`Core/RouterFactory`, a normal service: `router: @routerFactory::create()`, `routing: cache: false`): `<presenter>/<action>/<uuid>`, `<presenter>/<action>/<id \d+>`,
  `<presenter>/<action>`; default `Web:Home:default`.
- **DI registration:** RobotLoader indexes `app/`; `config/services.neon` auto-registers classes ending in
  `*Factory`, `*Facade`, `*Grid`, `*Service`. Other services/extensions are declared in `config/common.neon`,
  `config/ext/*.neon` (per package) and `app/DI/ServicesExtension.php`.
  Environment configs: `config/env/{dev,test,prod}.neon`; `config/local.neon` is git-ignored.
- **Persistence:** entities use attribute mapping, public typed properties, `Collection<int, X>` PHPDoc on associations.
  Every id is a `BIGINT` read into an `int` property (DBAL 4 returns int; until 2026-11-18 `UserEntity`/`MailEntity`/`GeoIpCacheEntity` had `string`); users are also addressed by `uuid`.
- **The entities describe the database completely** (ported from alfred 2026-11-18): column comments (`options: ['comment' => ...]`, the same text the migration sets), indexes the migrations create (`#[Index(name: 'K__...')]`), lengths, the primary key order of join tables. Every `#[ManyToOne]` has `#[JoinColumn]` and `cascade: ['persist']`. Guarded by `tests/Integration/Schema/*` (`SchemaInSyncCest` = `orm:schema-tool:update --dump-sql` must be empty, `SchemaNamingCest`, `SchemaKeysAndIndexesCest`, `SchemaTypesCest`, `SchemaAssociationsCest`) and `tests/Integration/ForeignKeyOnDeleteCest` (ON DELETE CASCADE of everything a user owns). `config/ext/nettrine.neon` maps `inet: ip_address`, otherwise every diff proposes `ALTER ... TYPE INET`.
- **Migrations are checked:** `tests/Unit/MigrationConventionsCest` (nikic/php-parser, reads the source: Schema API, `K__/UK__/FK__` names, `down()` not empty and reverting the same tables, one table per migration; no exceptions - web-project is not deployed, a broken migration is fixed) and `tests/Integration/MigrationsDownUpCest` (inside one rolled-back transaction: all migrations after the first go down and up again, the schema must be identical).
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
- **No static calls, never:** no new `static` method, no `Class::method()` call of our own code, no static factory / helper / "stateless utility" - not even when it needs no collaborators. Every behaviour is an instance method of a service that is constructor-injected (registered in the DI container), so it can be mocked, replaced and read from the constructor. Fine: class constants and enum cases (`Foo::BAR`), `new`, and the static API of a vendor library we cannot change (`Nette\Utils\Strings`, `Money::of()`); there is no own static method in `app/` (the router factory is a service, Bootstrap is `new Bootstrap()` in the entry points); `tests/Unit/NoStaticMethodsOrTraitsCest` fails on a static method or a trait in `app/` (a static closure is fine). In tests a Codeception data provider may stay static. The existing static helpers are legacy: do not extend them, do not copy them, turn one into an injected service when you touch it.
- Every `find()` / `findOneBy()` result is nullable: check it (and stop the request) before use.
- **Bootstrap 5 only:** the UI uses exclusively Bootstrap 5 (5.3). Do not write Bootstrap 4 markup (`btn-block`, `btn-default`, `btn-xs`, `badge-*`, `thead-light`, `ml-`/`mr-`, `text-left`, `form-group`, `custom-*`, `data-toggle`/`data-target`/`data-dismiss`, `font-weight-*`, ...) or use Bootstrap 4 only plugins. `Bootstrap5MarkupCest` fails on such leftovers, `FrontendSmokeCest` (Acceptance) on broken JS.
- **Every Doctrine join is selected:** `->addSelect('_town')->leftJoin('_person.birthTown', '_town')`, never a bare `join()`/`innerJoin()`/`leftJoin()`. A join without `addSelect` only filters: the joined entity stays a lazy proxy and each read of it costs a query (N+1). Exempt are queries that select scalars/aggregates (`select('_town.name')`, `COUNT(...)`) and a join that only filters a COLLECTION (selecting it would load just the filtered rows into the collection) - mark it `@join-filter-only` in the statement, or better use `:x MEMBER OF e.collection` (no join). `tests/Unit/JoinsAreSelectedCest` scans `app/` and fails on a violation (PQL has its own SQL engine and no Doctrine: not applicable).
- **Form <-> entity mapping is an `XFormMapper`, not code in the presenter:** a form that is shown from / saved to an entity has a mapper in `app/Forms/Mapper/` (picked up by `search:` in `config/services.neon`, `*Mapper`): `fill(Container $form, XEntity $entity)` sets the defaults (`setDefaults()`, so a submitted form is left alone), `apply(XValues $values, ?XEntity $entity = null): XEntity` writes the typed values onto the entity (a new one when none is given). The mapper maps FIELDS only: a password (hash, history, auto-logins), an e-mail history row, timestamps and flash messages are domain rules and stay in the facade / presenter, which call `apply()` for the rest (`UserFacade::update()` decides "the e-mail changed" BEFORE calling it). Every mapper has a Unit test (`tests/Unit/Forms/Mapper/*Cest`, a recording `DefaultsContainer`, no database) and the pages are pinned by a Functional test (`AdminFormMappingCest`); `FormMappingConventionCest` guards both. A new form gets both.
- **No floats - flex:** the layout of a card (header, body, footer) is a flex row (`d-flex justify-content-between align-items-center gap-2`, the right part `ms-auto`), never `float-*` / `pull-*` / `clearfix` / an inline `float:` (a floated block is not part of its parent's height). `tests/Unit/NoFloatsCest` scans `app/` and `assets/` and fails on a violation (same test in family-tree and alfred; `Bootstrap5MarkupCest` guards the card headers).
- **Form toggles: one id, one form:** `->toggle('some-id')` shows / hides EVERY element with that id (`querySelectorAll('#some-id')`), and the id of an element is `->setOption('id', 'some-id')` on its control: an id is unique per form, defined in the file that toggles it, never set on an inline-edit container. No form toggles anything yet; `tests/Unit/FormToggleIdsCest` guards the first one (same test in family-tree, where `Functional/ToggleTargetsAreUniqueCest` checks the rendered pages).
- **Codeception temp dir per suite:** `NetteDIModule` (with `newContainerForEachTest`) DELETES its whole `tempDir` between tests, so suites that shared `temp/codeception` crashed each other when run at the same time ("No such file or directory" in `cache/`). Every suite has its own (`tempDir: ../../temp/codeception/<suite>` in `tests/<Suite>.suite.yml`), so different suites can run in parallel. The SAME suite twice at once still shares its directory: give the second run another one, e.g. `-o "modules: config: Contributte\Codeception\Module\NetteDIModule: tempDir: ../../temp/codeception/unit2"`.
- **Flash messages are objects:** `flashMessage()` never gets a bare string, it gets a `\stdClass` with `place` (`'general'` = the page, or the area of the page the message belongs to), `type` (success / danger / info / warning) and `message`, all assigned by hand (`$flash = new stdClass(); $flash->place = ...; $flash->type = ...; $flash->message = ...; $this->flashMessage($flash);`) - no helper class (no static calls, see above). Nette uses the object as the flash itself, so the templates keep reading `$flash->type` / `$flash->message`. Guarded by `tests/Unit/FlashMessageIsAnObjectCest` (the same test in all three Nette projects). Also: the authenticators throw translation KEYS (`web-user-login.error.*`), the login presenter translates the message of the exception, never a raw `$exception->getMessage()` (guarded by the same test).
- **One look for every card:** the `.card` rule in `assets/styles.css` gives every card the same corners and the same shadow, and a `.card-group` is a row of SEPARATE cards. A card carries no `shadow*` / `rounded*` class of its own (those utilities are `!important`). A hover lift of a card is a state, not its look. `tests/Acceptance/CardLookCest` measures the computed corners and shadow of every card in a browser (same test in family-tree and alfred).
- **Acceptance paths are written with `Locator`, never as a raw XPath string:** `Locator::find('button', ['name' => 'login'])`, `Locator::firstElement('table > tbody > tr')` / `lastElement()` / `elementAt()` (a CSS selector inside is fine), `Locator::combine($a, $b)` for alternatives, `Locator::contains()`, `Locator::href()`. Not `'//button[@name="login"]'`. (`Locator::find('x', [])` makes an invalid `//x[]`: give it an attribute, or use CSS.) The DomCrawler adapters of the Functional tests (`tests/Support/Grid`) are not an Acceptance path. Guarded by `tests/Unit/AcceptancePathsUseLocatorCest` (same test in alfred, family-tree and web-project).
- **No traits:** we do not use PHP traits in any project (family-tree, alfred, web-project, PQL). Do not write a new `trait` and do not add `use SomeTrait;` to a class: use a real class (composition: a constructor-injected collaborator, or inheritance when it really is an "is a"). Existing traits are to be removed gradually, not extended (family-tree: `app/ApiModule/Presenters/CheckMethods.php`; PQL: `LimitExecutor`, `WhereQueryBuilder`, `LimitQueryBuilder`, `Offset`, `From`, `QueryPrinter/{Where,Limit,OffsetPrinter}`; alfred and web-project have none).
- **Libraries are aligned with web-project (the reference), changes go to all three Nette projects:** the same major of `nette/*`, `latte`, `tracy`, `doctrine/*`, `nettrine/*`, `contributte/*`, `codeception/*`, `phpstan/*`; the lower bounds in `composer.json` are web-project's. `composer frontline` proposes the latest major of everything: take it, then put back what web-project deliberately holds: `symfony/cache` stays `^7.4` and `symfony/var-exporter` is pinned `^7.0` (Doctrine ORM LazyGhost does not support var-exporter 8: "Symfony LazyGhost is not available" at the first entity proxy). Known consequences of the current majors: `Brick\Math\RoundingMode::HalfUp` (not `HALF_UP`), `ConfigurableProvider::builder()`, `Money::formatToLocale()`; `doctrine/collections` 3 reads RAW PROPERTIES only (`Criteria` cannot sort by `messages.count` any more: sort in PHP) and `Order::Ascending` is deprecated for PHP 8.6's `SortDirection` (baselined; remove with PHP 8.6); the `deepseek-chat` alias is gone (`Models::V4_FLASH`); `Codeception` memory limit is 2048M (`codeception.yml`, the Functional suite runs in one process).
- **No SmartObject magic of Nette components (`->presenter`, `->template`):** use `getPresenter()` / `getTemplate()`. `tracy.strictMode` turns the nette/utils deprecation into a 500 that only a BROWSER request hits - the Functional suite runs with strictMode off and stays green (this is how `$grid->presenter` broke every inline edit of alfred in the Acceptance suite). `tests/Unit/NoSmartObjectMagicCest` scans `app/` (same test in all three Nette projects).
- **`Html::text()` / `Html::html()`, not `Html::fromText()` / `Html::fromHtml()`:** the `from*` factories are deprecated in nette/utils 4.1 (the class docblock says "use Html::text()" / "use Html::html()"; under `tracy.strictMode` a deprecation is a 500). `Html::text($string)` is a static factory through `Html::__callStatic()`; phpstan-nette reads it as an instance method, so alfred's `phpstan.neon` has one `ignoreErrors` entry (`method.staticCall` for `Html::text|html`). Guarded by `tests/Unit/NoDeprecatedHtmlFactoriesCest` (same test in all three Nette projects).
- **No `@var` on typed properties:** a class property that has a native type does not get a `@var` annotation (it only repeats the type). We are refactoring all projects towards this slowly: remove the annotation whenever you touch such a property, do not add new ones, do not mass-edit untouched files. (`@var` stays only where the native type cannot say it: untyped legacy properties, or `array` contents when no real type exists yet.)
- **Initialise properties in the constructor, never at the declaration:** `private array $items;` + `$this->items = [];` in `__construct()`, not `private array $items = [];` (same for `null`, `false`, `0`). Exceptions are the places that have no constructor of ours: a PHPUnit/Codeception test (`__construct()` is final - initialise in `_before()`), and a PHP constant/enum case.
- **Page title = `<h1>` in the main card header, with an icon:** the first card of every page has the `<h1>` + `<i class="fas fa-…">` in its `.card-header` (list pages: the add button sits in the same header with `ms-auto`); further cards use h2 + icon. `CardHeaderTitleIconCest` (Unit) guards it, incl. that every `fa-*` class exists in the bundled Font Awesome 7.
- **No `Tables` class of table-name constants:** the name of a table is the literal in `#[Table(name: 'chat')]` of its entity (one place) and a literal in a migration (a migration is history: a constant would follow later renames and change what an old migration does). Raw SQL asks `$em->getClassMetadata(Entity::class)->getTableName()` or lists the tables from the database (see `DatabaseOptimizer`).
- **Names of indexes and foreign keys in migrations (web-project):** `K__<Table>__<columns>` (index), `UK__<Table>__<columns>` (unique index), `FK__<Table>__<columns>` (foreign key) - `<Table>` is the real table name with a capital first letter (`User_auto_login`, `Users`), `<columns>` the columns with a capital first letter joined by `_` (`Email_created_at`). Always pass the name explicitly (`addIndex(['user_id'], 'K__User_email__User_id')`, `addForeignKeyConstraint(..., name: 'FK__User_email__User_id')`), never let DBAL generate `IDX_/UNIQ_/FK_` names.
- **Migrations use the Schema API, never hand-written DDL:** `$schema->createTable()`, `getTable()->addColumn()/dropColumn()/addUniqueIndex()/addForeignKeyConstraint()`, `dropTable()`, `PrimaryKeyConstraintEditor` for primary keys - not `$this->addSql('CREATE TABLE ...')` and not a paste of what `migrations:diff` printed (that output is a hint only). `addSql()` is for DATA (an UPDATE/DELETE that runs before the schema changes) and for what DBAL cannot express (`CREATE EXTENSION`, an expression index like `lower(username)`, a `USING` conversion), each with a comment saying why. A conditional step reads the live schema (`$schema->hasTable()`, the column type). Verify a rewrite with a scratch database: migrate before and after, `pg_dump -s` and diff (never touch the real data DB). web-project is not deployed anywhere, so its old migrations may be edited (ids and foreign keys are BIGINT from the start, there is no widening migration).
- Form values are typed: one `final` value class per form next to its presenter (`LoginValues`, `RegistrationValues`, ...), read with `$form->getValues(XValues::class)`; facades take the value class, not `ArrayHash`. Nette writes EVERY non-omitted control into the property of the same name (text inputs return `string`, selects `int|string|null`, checkboxes `bool`). Datagrid inline edit rows use a value class too (`setMappedType(XInlineEditValues::class)` in `onControlAdd`, `#[\AllowDynamicProperties]` because the datagrid adds `_id` and the submit buttons to the same container); a row with validation errors is silently treated as *cancel*.
- PHPStan runs at level 10 with strict rules over `app/` AND `tests/` (the actors are generated by `codecept build` first: `make phpstan`, CI); keep it green and prefer fixing the code over adding `ignoreErrors` (no baseline). Own extensions in `app/PHPStan`: `GrabServiceReturnTypeExtension` (`$I->grabService(X::class)` returns X - no `@var` needed), `IpAddressTypeDescriptor` (the DBAL type `ip_address` for phpstan-doctrine), the rule `FlashMessageStdClassRule`. The migrations are analysed too; only `method.deprecated` (the mutable Schema API) is ignored there.
- **web-project is the template of alfred and family-tree:** a guard, a test pattern or a convention is made here first and taken over from here (the files say "From web-project"); what is project-specific stays in the project.
- **Every grid is a `SecuredDatagrid`** (`app/UI/Grid`, `new SecuredDatagrid()`, never `new Datagrid()` - `tests/Unit/GridsAreSecuredCest`): the row conditions (`allowRowsAction()`, `allowRowsInlineEdit()`) only hide buttons in the plain datagrid, here the same condition is checked again when the signal arrives (403), a malformed id or a row the grid would not show is a 404 before any handler runs, an unknown action is a 404, a forged `inline_edit[_primary_where_column]` is ignored. Pinned by `tests/Functional/GridRowPermissionsCest` and `GridSignalIdsCest`. An ID column filter is `->setFilterText()->setCondition(new IdFilterCondition('_alias.id'))` (anything but a whole number matches nothing; `GridIdFilterCest`). An `onControlAdd` that looks a row up by the POSTed `inline_edit[_id]` validates it first (a malformed uuid was a 500).
- **Tests use the attributes of Codeception** (`#[DataProvider]`, `#[Group]`, ...), never the docblock annotations (a typo in an annotation is silently ignored) - `tests/Unit/TestsUseAttributesCest`. An `Example` is `@param Example<int, string> $example` and the value is narrowed (`$page = $example[0]; assert(is_string($page));`).
- **Nothing outside `www/` is served:** `tests/Functional/PrivateFilesAreNotServedCest` asks for config, source, logs, temp, vendor, git (and `../` tricks): never 2xx/3xx, never the file itself (same test in alfred and family-tree).

- **Queries go through the QueryBuilder, never hand-written DQL/SQL:** `$em->createQueryBuilder()` or `$repository->createQueryBuilder('_alias')` (aliases start with `_`), also for bulk `delete()` / `update()`. No `createQuery('...')`, `createNativeQuery()`, `$connection->executeQuery()/executeStatement()/fetch*()`. Exempt: migrations. Guarded by `tests/Unit/QueriesUseTheQueryBuilderCest` (same rule as alfred).
- **Login never reveals whether an account exists:** an unknown username, a wrong password and a wrong password of an inactive account all throw the same `web-user-login.error.invalidCredentials` (an unknown username still hashes the password, so the timing is the same); "not active" is said only after the correct password. Pinned by `tests/Integration/LoginDoesNotRevealAccountsCest`.
- **The "stay logged in" cookie is handled in ONE place:** `Core/AutoLoginService::remember()` (login: issue the token, set the cookie) and `::forget()` (logout: revoke the token, delete the cookie). Presenters never touch the cookie or `AutoLoginTokenService` directly.

### Coding standard (phpcs)
- `ruleset.xml` = `contributte/qa` (PHP 8.4) adjusted to our style. It is ONE file shared by alfred, web-project and family-tree; the master copy is the one in the root of web-project - change it here and copy it to the other two.
- `make qa` (= `composer qa`: `parallel-lint app tests` + `phpcs --standard=ruleset.xml app tests`) must be clean (0 errors, 0 warnings); CI runs it (job `checks`, step "Coding standard"). `make cs-fix` (= `composer cs-fix`, phpcbf) fixes what it can, the rest is fixed by hand. Run both in the container: `docker compose exec web-project-web make qa`.
- The style: 4 spaces, never tabs; the first line of every PHP file is exactly `<?php declare(strict_types=1);`; a space before the colon of a return type (`) : void`); the `{` of a class on its own line, no empty line after it and none before the closing `}`.
- Method names are strict camelCase, no two capitals in a row (`everyCardHasTheSameCornersAndShadow`, not `...AndAShadow`; `Xpath`, not `XPath`): rename the method. Only a method that overrides a library API and cannot be renamed (Doctrine `Type::convertToPHPValue()`, `getSQLDeclaration()`, `requiresSQLCommentHint()`) gets `// phpcs:ignore Generic.NamingConventions.CamelCapsFunctionName` on the line above it.
- No references (`use (&$x)` in a closure, `array &$x` parameters): a closure returns its value (`Crawler::each()` returns the list of what the closure returned), a method returns what it collected. No superglobals (`$_SERVER`, ...), no empty function bodies (an empty `actionDefault()` / `renderDefault()` of a presenter is not needed at all), no empty `catch` without a comment why, one class / interface per file.

## Dependencies held back (`composer.json` `conflict`)
- `latte/latte >=3.1.8`: 3.1.8 deprecates "positional argument after named" and ublaboo/datagrid's `datagrid.latte` (line 302) does exactly that; under `tracy.strictMode` every admin grid was a 500. Remove the conflict once datagrid is fixed.
- `contributte/codeception 1.6.1`: its `isFrom()` signature is incompatible with our `tests/Support/Http/Request` (`ErrorException` in every test). 1.6.0 works.
- `composer.lock` and `package-lock.json` are **never committed** in web-project (both in `.gitignore`): every install takes the newest versions the constraints allow, so a version that breaks something is excluded here with `conflict` (composer) and CI uses `npm install`, not `npm ci`.

## Tests (Codeception, same setup as family-tree and alfred)

- Suites:
  - `tests/Unit` - **isolated**: only `Asserts`, no DI container, no database, no network (source scans and pure classes). `UnitTestsStayIsolatedCest` fails when a Unit test uses `NetteDIModule`, `grabService`, the EntityManager or a `*Tester` of another suite.
  - `tests/Integration` - the DI container (`NetteDIModule`, `newContainerForEachTest`) + `CleanupTestData`, runs against the dev database (services, facades, repositories).
  - `tests/Functional` (PhpBrowser against `http://web-project-web`), `tests/Acceptance` (WebDriver -> `selenium-chrome`, Chrome; VNC 5901, noVNC 7901, Selenium 4544).
  - Config `codeception.yml`, `tests/*.suite.yml`, container config `config/codeception/{common,local}.neon`.
- Run inside the web container: `docker compose exec web-project-web php vendor/bin/codecept run Unit|Integration|Functional|Acceptance` (or `make unit|integration|functional|acceptance|test`; `make test` runs them one after another).
- Write tests for behaviour before refactoring anything security-related (login, registration, password reset).
- `tests/Support/Helper/CleanupTestData.php` deletes what a test created (entities persisted through the test's EntityManager are recorded automatically; rows created by the web server are registered with `$I->trackCreatedEntity(UserEntity::class, $id)`). It must be listed AFTER `NetteDIModule` in the suite yml. The tests run against the dev database and use the fixture admin `TestUsername` / `secret`.
- `tests/Support/DI/HttpExtension` + `Http/Request` add `isFrom()` (nette/application 3.3) to the Codeception request. Ajax requests need the headers `Sec-Fetch-Site: same-origin` and `X-Requested-With: XMLHttpRequest`.
- `config/services.neon` includes the `ext/*.neon` files relative (not via `%configDir%`) so the test container can load it.
- Datagrid inline edit is submitted as `?do=grid-filter-submit` with `inline_edit[...]` fields; a row with validation errors is silently treated as cancel, so assert the saved value.
- The actor `FunctionalTester` has the shared steps: `loginAs(FunctionalTester::ROLE_ANONYMOUS|ROLE_USER|ROLE_ADMIN)` (the plain user, role Normal, is created for the test - the fixtures have only the admin), `login($username, $password)`, `seeRefused($code)` (a BadRequestException with that code: in the dev environment Tracy answers it as a 500 bluescreen / plain text for AJAX, the code is checked in it) and `seePageIsOk()`.
- **Every page for every role:** `tests/Functional/WebPagesSmokeCest` has the table of pages x roles (anonymous / User / Admin) with the expected answer (200 or the code of the refusal: 401 / 403). A new page goes into that table.
- **Page contracts:** `tests/Functional/PageContractCest` keeps golden files `tests/Support/Data/page-contracts/*.json` of what every page promises (title, headings, breadcrumb, navigation, form fields, buttons, grid columns / filters / row actions - no markup, `tests/Support/Grid/PageContract`). A changed label or a lost control fails it; an intended change is written with `UPDATE_PAGE_CONTRACTS=1 php vendor/bin/codecept run Functional PageContractCest` (after `make db`) and reviewed in the diff.

- **Lint:** `docker compose exec web-project-web make lint` (latte-lint, neon-lint, `php -l`, `composer validate`); CI (`.github/workflows/ci.yml`) runs it together with PHPStan and the front-end build (job `checks`), and all Codeception suites in the docker compose containers (job `tests`: `.github/docker-compose.codeception.yml`, `make all` + `make test`). `docker-publish.yml` builds and publishes the image only after the whole CI passed on main (`workflow_run`).
