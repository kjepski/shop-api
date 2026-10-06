---
name: ship-stage
description: Use to run one project stage end to end – from the plan the user accepts, through implementation, verification and review, to a pull request with green CI. Invoke at the start of every new stage or feature.
---

# Etap od planu do PR

Jeden etap to jeden PR (AGENTS.md). Odpowiadaj po polsku, commity po angielsku.

## 1. Plan
- Sprawdź `git status --porcelain`. Jeśli są niezacommitowane zmiany, zatrzymaj się i zapytaj użytkownika – nie używaj `stash`, `switch -f` ani `--discard-changes` bez jego zgody.
- Zacznij od aktualnego `main`: `git switch main && git pull --ff-only`, potem `git switch -c feat/<nazwa>`.
- Przeczytaj kod, którego dotyczy etap. Przedstaw plan: zmiany w API (parametry, kody odpowiedzi), implementacja (pliki), cache/limity/indeksy, panel, testy, co świadomie poza zakresem.
- Decyzje, które należą do użytkownika, przedstaw z rekomendacją. **Czekaj na akceptację** – bez niej nie piszesz kodu.

## 2. Implementacja
- Backend: konwencje z AGENTS.md, nowe moduły CRUD według skilla `laravel-module`.
- Frontend: według skilla `vue-feature`.
- Migracja: uruchom (`migrate`). Tylko jeśli się udała i `migrate:status` pokazuje nową migrację jako ostatnią, wycofaj ją (`migrate:rollback --step=1`) i uruchom ponownie, żeby sprawdzić `down()`. Jeśli `migrate` padło w połowie, nie wycofuj niczego (na MySQL DDL nie jest transakcyjny, rollback zdjąłby poprzednią, zmergowaną migrację) – zatrzymaj się i zapytaj użytkownika.
- Assety panelu przebuduj (`./vendor/bin/sail npm run build`), żeby użytkownik mógł od razu klikać.

## 3. Weryfikacja
- Testy na MySQL: `./vendor/bin/sail artisan test`.
- Testy na SQLite: `./vendor/bin/sail exec -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: laravel.test php artisan test`.
- `./vendor/bin/sail bin pint --test` i `./vendor/bin/sail bin phpstan analyse --memory-limit=1G`; przy frontendzie także lint, type-check, testy i build.
- **Mutacje**: dla każdego kluczowego warunku (autoryzacja, walidacja, escapowanie, klucze cache, limity) na chwilę zepsuj kod, sprawdź, że pada właściwy test, i przywróć plik z kopii w scratchpadzie. Mutację, która przeżywa, albo napraw testem, albo uzasadnij (np. równoważna na MySQL). Po mutacjach przejrzyj `git diff`, żeby żadna nie została w kodzie.
- **Smoke test** na lokalnym API przez curl: tylko odczyty lub żądania, które API odrzuci. Loguj się wyłącznie kontami z seedera (`admin@example.com`, `test@example.com`, hasło z `UserFactory`); jeśli ich nie ma, zapytaj użytkownika – nie twórz kont i nie szukaj haseł. Testowe tokeny odwołaj (`POST /api/logout`). Nie uruchamiaj `migrate:fresh`, `db:wipe` ani `tinker` na lokalnej bazie bez zgody.

## 4. Review
- Uruchom `code-reviewer` (backend) i/lub `frontend-reviewer` (frontend) w tle; w zleceniu podaj cel, ustalenia z planu, status testów i mutacji.
- Popraw realne błędy i tanie drobiazgi, dopisz brakujące testy, powtórz weryfikację. Decyzje projektowe z review przedstaw użytkownikowi z rekomendacją.

## 5. Przekazanie
- Napisz użytkownikowi: co działa, jak to sprawdzono, czego nie dało się sprawdzić (np. JS bez przeglądarki), co zostaje świadomie. Poproś o przeklikanie.
- Commit i PR dopiero po potwierdzeniu użytkownika.

## 6. Commit i PR
- `git add` tylko plików etapu – nie dodawaj plików, których nie widziałeś (np. `.env.example`, do którego nie masz dostępu); powiedz o nich użytkownikowi.
- Commit po angielsku, krótko, tryb rozkazujący, z linią atrybucji z bieżącej konfiguracji sesji.
- `git push -u origin <branch>`, potem `gh pr create --base main` z opisem po polsku według szablonu:

```markdown
## Co i dlaczego
<problem i rozwiązanie; nowe parametry/endpointy (tabela, jeśli kilka); implementacja w punktach; panel>

## Znane ograniczenia
<świadome kompromisy i rzeczy poza zakresem>

## Jak przetestowano
- testy (pliki, co sprawdzają),
- mutacje (co wywraca testy; mutanty, które przeżywają, z uzasadnieniem),
- wyniki: `artisan test` (liczba) na MySQL i SQLite, Pint, PHPStan (+ frontend),
- ręcznie: smoke test, migracje, przeklikanie przez użytkownika.
```

- Poczekaj na CI w tle (nie w pętli na pierwszym planie): `gh pr checks <nr> --watch --fail-fast`. Jeśli zaraz po utworzeniu PR odpowiada „no checks reported”, poczekaj chwilę na start workflow i uruchom ponownie; jeśli checków dalej nie ma, napisz o tym użytkownikowi. Przy porażce przeczytaj log (`gh run view <run> --log-failed`), napraw na tym samym branchu i powtórz.
- Na koniec: link do PR, stan CI, co zostaje na kolejne etapy.
