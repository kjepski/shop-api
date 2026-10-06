---
name: laravel-module
description: Use when adding a new CRUD module (model, migration, factory, controller, request, resource, policy, tests) to this Laravel API.
---

# Nowy moduł CRUD

Kolejność:
1. Migracja (`sail artisan make:migration`), indeksy i klucze obce.
2. Model z `$fillable`/casts, relacje, fabryka, seeder.
3. FormRequest (store/update), API Resource.
4. Policy (jeśli zasób ma właściciela) i rejestracja.
5. Kontroler API + routing w `routes/api.php`.
6. Testy Feature: sukces, walidacja, autoryzacja (401/403), paginacja.
7. Uruchom: testy, Pint, PHPStan.

Nie używaj float dla pieniędzy. Zawsze eager loading relacji zwracanych w Resource.
