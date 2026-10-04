---
name: project_migrations_primarykey_pattern
description: "Doctrine DBAL 4's Table::setPrimaryKey() is deprecated - the replacement pattern (from web-project/alfred) uses PrimaryKeyConstraintEditor. Applied to all 18 family-tree migrations 2026-09-28."
metadata:
  node_type: memory
  type: project
  originSessionId: e145a0ea-8e8c-44b5-8222-d6fbbe333721
  modified: 2026-09-28T19:35:03.787Z
---

`Doctrine\DBAL\Schema\Table::setPrimaryKey(array $columns)` is deprecated in DBAL 4.x (all three
projects - family-tree, web-project, alfred - are on DBAL 4). The replacement pattern, confirmed
from web-project's and alfred's own migrations (both already used it before family-tree did):

```php
use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraintEditor;

// single column
$primaryKey = new PrimaryKeyConstraintEditor();
$primaryKey->setIsClustered(false);
$primaryKey->setColumnNames(new UnqualifiedName(Identifier::unquoted('id')));

$table->addPrimaryKeyConstraint($primaryKey->create());

// composite key: setColumnNames takes multiple UnqualifiedName args
$primaryKey->setColumnNames(
    new UnqualifiedName(Identifier::unquoted('personId')),
    new UnqualifiedName(Identifier::unquoted('siblingId')),
);
```

`addPrimaryKeyConstraint()` returns the `Table` again (fluent), so alfred chains
`->setComment(...)` on it; family-tree's existing style calls `$table->setComment(...)` as its own
line before this block instead - kept that convention rather than copying alfred's chaining
verbatim.

**Verified** (not just syntax-checked) by instantiating each migration class directly, calling
`up()` against a fresh in-memory `Schema`, and comparing `$schema->toSql($platform)` output -
produces byte-identical `PRIMARY KEY (id)` / `PRIMARY KEY (personId, siblingId)` SQL to what
`setPrimaryKey()` used to generate, for both single-column and composite cases.

**Fixed 2026-09-28**: all 18 family-tree migrations using `setPrimaryKey(` (`Database/Migrations/*.php`).
**web-project needed no fix** - already used this pattern in every migration, confirmed via grep
finding zero `setPrimaryKey(` occurrences.
