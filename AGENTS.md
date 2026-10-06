# shop-api

REST API sklepu (produkty, kategorie, użytkownicy). Laravel (wersja z composer.json), Eloquent, Sanctum (tokeny), MySQL, Redis. Środowisko lokalne: Laravel Sail (Docker).
To projekt do nauki pracy z agentami AI: liczy się czytelność kodu i małe, łatwe do przejrzenia zmiany.

## Środowisko
- Wszystkie komendy PHP, Composer i Artisan uruchamiaj przez Sail: `./vendor/bin/sail ...`.
- Start: `./vendor/bin/sail up -d`, stop: `./vendor/bin/sail stop`.
- Nie instaluj PHP, Composera ani pakietów systemowych. Jeśli Sail nie działa, napisz o tym zamiast obchodzić problem.
- Nie instaluj nowych pakietów Composera bez uzasadnienia w opisie PR.

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

## Testy
- Każdy endpoint ma testy Feature: sukces, walidacja, 401 i 403 (gdzie dotyczy), paginacja (dla list).
- Każdy model ma fabrykę.
- Testuj zachowanie, nie implementację. Test, który nie może się nie powieść, jest bezwartościowy.

## Zasady pracy
- Pracuj na branchu `feat/...`, nigdy bezpośrednio na `main`.
- Jeden PR to jeden etap lub funkcja. Nie rozszerzaj zakresu poza to, o co poproszono.
- Przed zakończeniem pracy testy, Pint i PHPStan muszą przechodzić.
- Commity: po angielsku, krótkie, w trybie rozkazującym. PR otwieraj przez `gh pr create`, w opisie napisz co i dlaczego zmieniono oraz jak to przetestowano.
- Nie dotykaj `.env` ani sekretów. Nowe zmienne środowiskowe dopisuj do `.env.example`.
- Bez pytania nie używaj: `git push --force`, `git reset --hard`, `rm -rf`.
- Gdy wymagania są niejasne, zapytaj zamiast zgadywać.
- Nie twórz plików, o które nie poproszono (dodatkowa dokumentacja, skrypty pomocnicze).
