# Platform module

Tenancy, identity, RBAC, audit log, SaaS billing gateway wiring, settings.

## Allowed dependencies

- Nothing else. Platform is the dependency root.

## Sub-folders

| Folder | Purpose |
|---|---|
| `Domain/` | Tenancy, Identity, RBAC, Audit, Billing state-machine value objects. Pure PHP, no Laravel dependencies. |
| `Application/` | Use cases (`ProvisionOrganization`, `InviteUser`, `AssignRole`, ...), DTOs, queries. |
| `Infrastructure/` | Eloquent models, repositories, `BelongsToTenant` trait, gate / policy bindings. |
| `Http/` | Controllers (Filament + Inertia), FormRequests, API Resources. |
| `Contracts/` | Public interfaces other modules consume (`OrganizationContext`, `RoleChecker`, ...). |
| `Database/` | Migrations, seeders, factories scoped to Platform. |
| `Tests/` | Unit, Feature, Arch tests for Platform. |