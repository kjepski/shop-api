# shop-api

REST API sklepu (produkty, kategorie, użytkownicy). Laravel (wersja z composer.json), Eloquent, Sanctum (tokeny), MySQL, Redis. Środowisko lokalne: Laravel Sail (Docker).
To projekt do nauki pracy z agentami AI: liczy się czytelność kodu i małe, łatwe do przejrzenia zmiany.

## Środowisko
- Wszystkie komendy PHP, Composer i Artisan uruchamiaj przez Sail: `./vendor/bin/sail ...`.
- Start: `./vendor/bin/sail up -d`, stop: `./vendor/bin/sail stop`.
- Nie instaluj PHP, Composera ani pakietów systemowych. Jeśli Sail nie działa, napisz o tym zamiast obchodzić problem.
- Nie instaluj nowych pakietów Composera ani npm bez uzasadnienia w opisie PR.
- Komendy Node/npm też przez Sail: `./vendor/bin/sail npm ...`, `./vendor/bin/sail npx ...`.

## Komendy
- Testy: `./vendor/bin/sail artisan test`
- Pojedynczy test: `./vendor/bin/sail artisan test --filter=NazwaTestu`
- Formatowanie (Pint): `./vendor/bin/sail bin pint`
- Analiza statyczna (PHPStan/Larastan): `./vendor/bin/sail bin phpstan analyse --memory-limit=1G`
- Migracje: `./vendor/bin/sail artisan migrate`
- Lista tras: `./vendor/bin/sail artisan route:list --path=api`
- Framework testów (Pest lub PHPUnit): sprawdź composer.json i trzymaj się tego, który jest w użyciu.

## Architektura i konwencje
- Trasy API w `routes/api.php`, chronione przez `auth:sanctum` (poza rejestracją i logowaniem).
- Kontrolery są cienkie (`app/Http/Controllers/Api`). Logika biznesowa w klasach Action (`app/Actions`).
- Walidacja wyłącznie w FormRequest (`app/Http/Requests`), nigdy w kontrolerze.
- Odpowiedzi zawsze przez API Resources (`app/Http/Resources`). Błędy walidacji to 422, brak autoryzacji 401, brak uprawnień 403.
- Autoryzacja przez Policy (`app/Policies`).
- Eloquent: jawne `$fillable`, `casts`, relacje z typami zwracanymi, filtry jako lokalne scope'y.
- Lazy loading traktuj jako błąd (N+1): relacje zawsze ładuj przez `with()`. Nie wykonuj zapytań w pętlach.
- Pieniądze jako integer w groszach, nigdy float.
- Migracje: nigdy nie edytuj zmergowanych, twórz nowe. Każda ma poprawne `down()`, klucze obce i indeksy.
- Redis: cache z tagami, prefiks kluczy, jawny TTL, inwalidacja po zmianie danych. Testy nie łączą się z prawdziwym Redisem.

## Frontend (panel `/admin`)
Panel to SPA, które rozmawia wyłącznie z `/api/*`; uprawnienia egzekwuje API, UI tylko ukrywa niedostępne akcje.

- Uwierzytelnienie nowego panelu: Sanctum SPA (sesja w ciasteczku HttpOnly + CSRF przez `/sanctum/csrf-cookie`). Panel nie przechowuje tokenu w JS. Tokeny Bearer zostają dla innych klientów API (i dla starego panelu do czasu jego usunięcia).

- Stack: Vue 3 (`<script setup lang="ts">`, Composition API), TypeScript (`strict: true`, sprawdzanie przez `vue-tsc`), Vue Router, Pinia, Tailwind, budowanie przez Vite.
- Formatowanie: Prettier odpowiada za styl, ESLint (`eslint-plugin-vue`, `typescript-eslint`) za poprawność; reguły stylistyczne ESLinta wyłączone przez `@vue/eslint-config-prettier`, żeby narzędzia się nie przepychały.
- Migracja: do przełączenia `/admin` na Vue stary panel w Alpine (`resources/js/admin.js`, `resources/views/admin.blade.php`) tylko utrzymujemy, nie dodajemy do niego funkcji. Nowy panel rośnie obok pod `/admin-next`.
- Komendy (od wprowadzenia toolingu Vue): `./vendor/bin/sail npm run lint`, `run type-check`, `run test`, `run build`.

Struktura `resources/js/admin/`:
- `main.ts` (start aplikacji), `App.vue` (layout i `<RouterView>`), `router.ts` (trasy i strażnicy).
- `api/` – jedyne miejsce z `fetch`: klient (ciasteczka i CSRF, 401, `Retry-After`) i moduł na zasób (`products.ts` itd.).
- `types/` – typy odpowiedzi API (odpowiedniki API Resources), `Paginated<T>`, błędy walidacji.
- `stores/` – Pinia tylko dla stanu globalnego (sesja, komunikaty). Stan list żyje w composables.
- `composables/` – logika wielokrotnego użytku: listy z paginacją i sekwencjonowaniem żądań, filtry/sort/strona zsynchronizowane z URL, formularze.
- `utils/` – czyste funkcje (pieniądze, query string), testowane jednostkowo.
- `components/ui/` – generyczne komponenty bez wiedzy o domenie (przycisk, pole, modal, tabela, paginacja).
- `components/layout/` – nagłówek, nawigacja.
- `features/<zasób>/` – strona i komponenty jednego zasobu (`ProductsPage.vue`, `ProductTable.vue`, `ProductFormModal.vue`).

Zasady:
- Komponent nie woła `fetch` ani modułów `api/` bezpośrednio; dane przez composable lub store.
- Logika (pieniądze, budowanie zapytań, kolejność żądań, obsługa 422/429) nie siedzi w szablonach ani w komponentach `ui/`.
- Pieniądze w UI: wpis w złotych, do API zawsze integer w groszach, konwersja tylko w `utils/money.ts`. Parsowanie wpisu na grosze bez floatów; wyświetlanie przez `Intl.NumberFormat` jest dozwolone.
- Nigdy `v-html` na danych z API.
- Propsy tylko do odczytu, zmiany przez `emit` albo `defineModel` (dla `v-model`); typowane `defineProps`/`defineEmits`.
- Komponent powyżej ok. 200 linii albo z więcej niż jedną odpowiedzialnością dziel na mniejsze.
- Typy w `types/` muszą odpowiadać API Resources; zmiana Resource to zmiana typu w tym samym PR.
- Dostępność: każde pole ma etykietę, modal łapie focus i zamyka się Escape, akcje działają z klawiatury.

## Testy
- Każdy endpoint ma testy Feature: sukces, walidacja, 401 i 403 (gdzie dotyczy), paginacja (dla list).
- Każdy model ma fabrykę.
- Testuj zachowanie, nie implementację. Test, który nie może się nie powieść, jest bezwartościowy.
- Testy backendu muszą przechodzić na MySQL (CI, produkcja) i na SQLite: unikaj zachowań zależnych od bazy (np. domyślny znak ESCAPE w LIKE).
- Frontend: `utils/` i composables testami jednostkowymi (Vitest), komponenty przez Vue Test Utils (zachowanie widoczne dla użytkownika, nie wewnętrzny stan).

## Zasady pracy
- Pracuj na branchu `feat/...`, nigdy bezpośrednio na `main`.
- Jeden PR to jeden etap lub funkcja. Nie rozszerzaj zakresu poza to, o co poproszono.
- Przed zakończeniem pracy testy, Pint i PHPStan muszą przechodzić; przy zmianach frontendu także lint, type-check, testy i build.
- Commity: po angielsku, krótkie, w trybie rozkazującym. PR otwieraj przez `gh pr create`, w opisie napisz co i dlaczego zmieniono oraz jak to przetestowano.
- Nie dotykaj `.env` ani sekretów. Nowe zmienne środowiskowe dopisuj do `.env.example`.
- Bez pytania nie używaj: `git push --force`, `git reset --hard`, `rm -rf`.
- Gdy wymagania są niejasne, zapytaj zamiast zgadywać.
- Nie twórz plików, o które nie poproszono (dodatkowa dokumentacja, skrypty pomocnicze).
