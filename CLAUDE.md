@AGENTS.md

## Claude Code
- Przy nietrywialnych zadaniach najpierw przedstaw plan i poczekaj na akceptację.
- Nowe moduły CRUD buduj według skilla `laravel-module` (gdy istnieje w `.claude/skills/`).
- Po zakończeniu funkcji uruchom subagenta `code-reviewer` (gdy istnieje w `.claude/agents/`) na diffie względem `main`.
- Pint uruchamia się automatycznie hookiem po edycji pliku, nie musisz robić tego ręcznie w trakcie pracy, ale przed zakończeniem sprawdź, że przechodzi.
