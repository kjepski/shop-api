---
name: vue-feature
description: Use when adding or changing a screen or feature in the Vue admin panel (resources/js/admin) – a new resource page, list, filters, form or component.
---

# Nowa funkcja panelu w Vue

Zasady i struktura katalogów: sekcja „Frontend” w AGENTS.md. Tu jest kolejność pracy.

1. **Kontrakt API.** Przeczytaj Resource, FormRequest i trasę po stronie Laravela. Jeśli funkcja wymaga zmiany API, to najpierw backend (z testami Feature), potem frontend – najlepiej osobny etap.
2. **Typy** w `types/`: dokładne odpowiedniki pól Resource (nullable tam, gdzie API zwraca `null`), parametry zapytań, błędy walidacji.
3. **Moduł `api/<zasób>.ts`**: funkcje na endpoint, korzystające ze wspólnego klienta. Tylko tu wolno wołać `fetch`.
4. **Logika**: czyste funkcje do `utils/` (np. pieniądze, query string), stan i efekty do composable (`composables/`). Najpierw sprawdź, czy istniejący composable (lista z paginacją, synchronizacja z URL, formularz) już to robi – rozszerz go zamiast kopiować.
5. **Komponenty**: generyczne do `components/ui/` (bez wiedzy o domenie), domenowe do `features/<zasób>/`. Jeden komponent, jedna odpowiedzialność; propsy w dół, `emit` w górę.
6. **Strony** – każdy ekran osobno, nigdy w modalu (tabela tras w AGENTS.md):
   - `<Zasób>ListPage.vue` – lista; wiersz prowadzi do szczegółów,
   - `<Zasób>ShowPage.vue` – szczegóły; stąd „Edytuj” i „Usuń” (usunięcie potwierdza `ConfirmDialog`),
   - `<Zasób>FormPage.vue` – dodawanie i edycja, pola w `<Zasób>Form.vue`; po zapisie powrót z komunikatem, ostrzeżenie o niezapisanych zmianach,
   - 404 z API → strona „Nie znaleziono”.
   Strona składa composables i komponenty, sama nie zawiera logiki.
7. **Trasy** w `router.ts`: `/<zasób>`, `/<zasób>/:id(\\d+)`, `/<zasób>/new`, `/<zasób>/:id(\\d+)/edit`; `meta.title` obowiązkowo, plus meta uprawnień (wymaga sesji, tylko admin); filtry/sort/strona listy w query URL. Strona ma `<main>` z `<h1 tabindex="-1">`; „Wróć” przez wspólny composable (zasada w AGENTS.md). Trasę Laravela dla panelu (`routes/web.php`) i wejście w `vite.config.js` zmieniasz tylko przy nowym punkcie wejścia aplikacji, nie przy każdej stronie.
8. **Testy**:
   - `utils/` i composables – Vitest, łącznie z przypadkami brzegowymi (puste wartości, 422, 429, spóźniona odpowiedź),
   - komponenty – Vue Test Utils, zachowanie widoczne dla użytkownika,
   - jeśli w projekcie jest Playwright – scenariusz e2e dla głównej ścieżki.
9. **Sprawdzenie**: `./vendor/bin/sail npm run format:check`, `run lint`, `run type-check`, `run test`, `run build`. Gdy zmienił się backend – także testy PHP, Pint i PHPStan.
10. **Review**: subagent `frontend-reviewer` (i `code-reviewer`, gdy zmienił się backend).

Nie używaj `v-html` na danych z API. Nie dodawaj zależności npm bez uzasadnienia w opisie PR.
