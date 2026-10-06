@AGENTS.md

## Claude Code
- Przy nietrywialnych zadaniach najpierw przedstaw plan i poczekaj na akceptację.
- Nowe moduły CRUD buduj według skilla `laravel-module` (gdy istnieje w `.claude/skills/`).
- Etap od planu do PR prowadź według skilla `ship-stage`.
- Nową funkcję panelu w Vue buduj według skilla `vue-feature`.
- Po zakończeniu funkcji uruchom review na zmianach względem `main`: `code-reviewer` dla backendu i harnessu (`.claude/`, AGENTS.md, CLAUDE.md, CI), `frontend-reviewer` dla `resources/js`/`resources/views`; gdy diff dotyka obu, oba równolegle.
- Hook `.claude/hooks/format.py` formatuje plik po każdej edycji (PHP: Pint; Vue/TS/JS: ESLint i Prettier, gdy są zainstalowane). Nie formatuj ręcznie w trakcie pracy, ale przed zakończeniem sprawdź, że całość przechodzi. Import dodawaj w tym samym Edicie co jego użycie – inaczej formatter usunie go jako nieużywany.
