# SETTINGS — the typed settings of the organization and of the property

Documentation for task `P1-13` (settings framework).

## 1. What a setting is

A setting is a **typed** value that belongs to the organization or to one
of its properties. Every setting is declared in `config/settings.php`
with its type, its default and its scope:

```php
'currency' => ['type' => 'string', 'default' => 'IDR', 'scope' => 'property'],
'service_charge_percent' => ['type' => 'int', 'default' => 0, 'scope' => 'property'],
'locale' => ['type' => 'string', 'default' => 'id', 'scope' => 'organization', 'choices' => ['id', 'en', 'zh']],
```

- A setting that is **not declared does not exist**: reading it is an
  error, not a null.
- A value that does **not match the type is refused**, and so is a value
  outside the choices.
- A setting of a property **needs a property**: the scope of the
  declaration decides where the value is stored, not the caller.

The types are `string`, `int`, `bool` and `decimal`.

## 2. Reading and writing

```php
$settings = app(Settings::class);

$settings->get('currency', $property->id);          // 'IDR'
$settings->getInt('service_charge_percent', $property->id);
$settings->getBool('breakfast_included', $property->id);
$settings->all($property->id);                        // every setting of the scope

$settings->set('currency', 'USD', $property->id, $actorId);
```

Writing a setting does three things:

1. **validates** the value against the type of the setting;
2. **invalidates the cache**, so the change is visible at once;
3. **audits** the change (`settings.updated`, with the old value, the new
   value and the key).

## 3. The cache

A value is read once and kept for `SETTINGS_CACHE_SECONDS` (five
minutes by default). The time in the cache is only a safety net: a
change **through the settings** invalidates it at once, so the value that
is read after a change is the new one.

A change made **behind** the settings (a script, a migration) is not
seen until the cache expires. That is the price of the cache, and it is
the reason the settings are the only way to write a setting.

## 4. What is not built yet

- There is **no screen**: the settings are read and written through the
  contract. The screen belongs to the module that shows it (the PMS).
- The settings are **not** wired into the modules yet: the modules still
  read their own columns (the timezone and the currency of a property
  are columns of `properties`). Moving them to the settings is a
  migration of the reads, one module at a time.
- There is **no bulk write**: one setting at a time, each with its own
  audit entry.

## 5. Related documents

- `docs/DOMAIN_MODEL.md` — the entities and their invariants.
- `docs/PRODUCTION_READINESS.md` — the readiness items.