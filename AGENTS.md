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
- Komendy: `./vendor/bin/sail npm run lint` (ESLint, bez ostrzeżeń), `run type-check` (`vue-tsc`), `run test` (Vitest), `run build`, `run format` / `run format:check` (Prettier dla `resources/js/admin` i plików konfiguracyjnych).
- Laravel serwuje tę samą powłokę (`resources/views/admin-next.blade.php`) dla każdej ścieżki `/admin-next/*`; o tym, co pokazać, decyduje Vue Router (`createWebHistory('/admin-next/')`).

Każdy ekran to osobna trasa z własnym URL – działa po odświeżeniu i z bezpośredniego linku, przycisk „wstecz” działa:

| Ekran | Ścieżka | Komponent strony |
|---|---|---|
| Lista (filtry, sort, strona w query) | `/<zasób>` | `<Zasób>ListPage.vue` |
| Szczegóły | `/<zasób>/:id(\\d+)` | `<Zasób>ShowPage.vue` |
| Dodawanie | `/<zasób>/new` | `<Zasób>FormPage.vue` |
| Edycja | `/<zasób>/:id(\\d+)/edit` | `<Zasób>FormPage.vue` |

- Szczegółów, dodawania i edycji nie robimy w modalach. Modal (`ConfirmDialog`) służy wyłącznie do potwierdzeń, np. usunięcia.
- `<Zasób>FormPage.vue` pobiera dane (edycja), zapisuje i wraca na listę lub szczegóły; pola formularza są w `<Zasób>Form.vue`, wspólnym dla dodawania i edycji.
- Formularz z niezapisanymi zmianami pyta przed opuszczeniem trasy (`onBeforeRouteLeave`).
- 404 z API na stronie szczegółów lub edycji pokazuje stronę „Nie znaleziono”, nie pusty formularz.
- `:id` tylko z cyframi (`(\\d+)`), żeby `/products/abc` od razu dawało „Nie znaleziono” zamiast żądania do API.
- Każda trasa ma `meta.title` (wymusza to typ `RouteMeta`) – router ustawia z niego tytuł karty.
- Każda strona renderuje `<main>` z jednym `<h1 tabindex="-1">`; router przenosi na niego focus po zmianie ekranu (nie przy pierwszym wejściu) i przewija na górę, a „wstecz” przywraca pozycję.
- „Wróć” ze szczegółów lub formularza: jeśli poprzedni wpis historii (`history.state.back`) to lista tego zasobu, `router.back()` – lista wraca z tymi samymi filtrami, sortem i stroną z query; w przeciwnym razie (wejście z linku) przejście na listę bez filtrów. Logika w jednym composable, nie w każdej stronie.
- Router po wdrożeniu nowej wersji sam przeładowuje stronę, gdy brakuje starego pliku ekranu (leniwe ładowanie tras).

Struktura `resources/js/admin/`:
- `main.ts` (start aplikacji), `App.vue` (layout i `<RouterView>`), `router.ts` (trasy i strażnicy), `env.d.ts`.
- Testy obok testowanego pliku: `<Plik>.test.ts`, z `enableAutoUnmount(afterEach)`. Import z aliasem `@admin/...`.
- ESLint sprawdza z informacją o typach (`recommendedTypeChecked`): niezaawaitowany promise to błąd – `await` albo jawne `void`.
- `api/` – jedyne miejsce z `fetch`: klient (ciasteczka i CSRF, 401, `Retry-After`) i moduł na zasób (`products.ts` itd.).
- `types/` – typy odpowiedzi API (odpowiedniki API Resources), `Paginated<T>`, błędy walidacji.
- `stores/` – Pinia tylko dla stanu globalnego (sesja, komunikaty). Stan list żyje w composables.
- `composables/` – logika wielokrotnego użytku: listy z paginacją i sekwencjonowaniem żądań, filtry/sort/strona zsynchronizowane z URL, formularze.
- `utils/` – czyste funkcje (pieniądze, query string), testowane jednostkowo.
- `components/ui/` – generyczne komponenty bez wiedzy o domenie (przycisk, pole, tabela, paginacja, dialog potwierdzenia).
- `components/layout/` – nagłówek, nawigacja.
- `features/<zasób>/` – strony i komponenty jednego zasobu (`ProductListPage.vue`, `ProductShowPage.vue`, `ProductFormPage.vue`, `ProductForm.vue`, `ProductTable.vue`, `ProductFilters.vue`).

Zasady:
- Komponent nie woła `fetch` ani modułów `api/` bezpośrednio; dane przez composable lub store.
- Logika (pieniądze, budowanie zapytań, kolejność żądań, obsługa 422/429) nie siedzi w szablonach ani w komponentach `ui/`.
- Pieniądze w UI: wpis w złotych, do API zawsze integer w groszach, konwersja tylko w `utils/money.ts`. Parsowanie wpisu na grosze bez floatów; wyświetlanie przez `Intl.NumberFormat` jest dozwolone.
- Nigdy `v-html` na danych z API.
- Propsy tylko do odczytu, zmiany przez `emit` albo `defineModel` (dla `v-model`); typowane `defineProps`/`defineEmits`.
- Komponent powyżej ok. 200 linii albo z więcej niż jedną odpowiedzialnością dziel na mniejsze.
- Typy w `types/` muszą odpowiadać API Resources; zmiana Resource to zmiana typu w tym samym PR.
- Dostępność: każde pole ma etykietę, po zmianie trasy focus trafia na nagłówek strony, dialog potwierdzenia łapie focus i zamyka się Escape, akcje działają z klawiatury.

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
