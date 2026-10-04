---
name: feedback-form-value-objects
description: typed form value classes ($form->getValues(XxxFormValues::class), setMappedType) - convention taken from web-project; how far it is rolled out in family-tree
metadata:
  type: project
---

Convention from `~/rendix2-web-project` (LoginValues etc.) adopted in family-tree (2026-09-26): one final value class per form in `app/controls/forms/Values/`, read via `$form->getValues(X::class)`; forms used in ONE place also `setMappedType(X::class)` in `create()` so `onValidate` handlers get the typed object.

**Done:** PersonForm (PersonFormValues), WeddingForm, RelationForm, NameForm, PersonAddRelation/Name/Husband/Wife/PersonJob forms (share `DatedFormValues`: untilNow, dateSince, dateTo, `#[AllowDynamicProperties]`). **Also done (consumer-level mapping, form NOT globally mapped because modals reuse it):** JobForm (JobFormValues), AddressForm (AddressFormValues), TownForm (TownFormValues), PersonAddSourceForm, HistoryNoteForm (mapped, single context). Consumers read `$form->getValues(XValues::class)`; forms nobody reads values from (Genus, Country, SourceType, Source - only DoctrineFormMapper) need no class. **Also done (2026-09-26):** delete modals (`app/controls/modals/Values/*`), add modals (`getValues(TownFormValues|AddressFormValues::class)`), datagrid inline edit/add rows (`app/controls/Grids/Values/*GridValues`, `setMappedType` in `onControlAdd`; datagrid treats a row with validation errors as a silent cancel, so `GridInlineEditCest` asserts saved values). Nothing left un-typed. Optional text inputs return null -> `?string`; FormValuesMappingCest caught that (gps).

**Gotchas:** Nette writes EVERY non-omitted control into the property of the same name (typeahead text inputs, hidden ids too) - missing property = dynamic property deprecation = 500 under Tracy strictMode; hidden ids can be int (presenter `setValue((int))`) or '' or numeric string -> `int|string|null`; DateInput needs `->setNullable()` to give null instead of ''. Forms reused by modals that add controls (`_townId`) must not be mapped without AllowDynamicProperties. NEVER call `$form->getValues()` inside onValidate (warning 'form is not valid' -> error): use the handler's `$values` argument. `FormValuesMappingCest` submits every form of the add/edit pages and is the safety net.
