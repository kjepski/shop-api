#!/usr/bin/env python3
"""PostToolUse hook: formats the file Claude just edited, picking the tool by extension.

- .php (not Blade)        -> Pint, only this file
- .vue/.ts/.js and kin    -> Prettier, then ESLint --fix, once they are installed in node_modules

Exit 0: formatted, skipped or nothing to do (a skip is reported to the user as a system message).
Exit 2: the file does not parse or the formatter is misconfigured; stderr goes back to Claude.
Remaining lint errors do not block: during multi-step edits the file may be legitimately
unfinished (e.g. an import added before its use), and the full lint runs before the PR.

Runs on the host, which has neither jq nor node, so tools run in the Sail container.
Sail's own pre-command checks are skipped on purpose: when the app container has exited,
they run `docker compose down`, which a formatter must never trigger.
"""

import json
import os
import subprocess
import sys

PHP = {".php"}
FRONTEND = {".vue", ".ts", ".mts", ".cts", ".tsx", ".js", ".mjs", ".cjs", ".jsx"}
SKIPPED_DIRS = ("vendor/", "node_modules/", "public/build/", "storage/", "bootstrap/cache/")
# The Alpine panel is only maintained until the Vue panel replaces it; reformatting it whole
# would turn every small fix into a huge diff.
LEGACY_FILES = {"resources/js/admin.js"}
APP_SERVICE = os.environ.get("APP_SERVICE", "laravel.test")


class Skip(Exception):
    """Formatting could not run; the user gets told, the edit is not blocked."""


def run(root: str, args: list[str], timeout: int) -> subprocess.CompletedProcess[str]:
    try:
        return subprocess.run(
            args,
            cwd=root,
            capture_output=True,
            text=True,
            stdin=subprocess.DEVNULL,
            timeout=timeout,
            env={**os.environ, "SAIL_SKIP_CHECKS": "1"},
        )
    except FileNotFoundError as error:
        raise Skip(f"{args[0]} is not available ({error.filename})") from error
    except subprocess.TimeoutExpired as error:
        raise Skip(f"{' '.join(args[:3])} did not finish within {timeout} s") from error


def sail(root: str, *args: str) -> subprocess.CompletedProcess[str]:
    return run(root, ["./vendor/bin/sail", *args], timeout=60)


def ensure_app_is_running(root: str) -> None:
    result = run(root, ["docker", "compose", "ps", "--status", "running", "--services"], timeout=10)
    if result.returncode != 0 or APP_SERVICE not in result.stdout.split():
        raise Skip("Sail is not running")


def block(message: str) -> None:
    print(message, file=sys.stderr)
    sys.exit(2)


def notify(message: str) -> None:
    print(json.dumps({"systemMessage": message}))


def format_php(root: str, relative: str) -> None:
    # Not -q: on failure the output is the only explanation Claude gets.
    result = sail(root, "bin", "pint", relative)
    if result.returncode != 0:
        block(f"Pint could not format {relative}:\n{result.stdout}{result.stderr}")


def format_frontend(root: str, relative: str, has_prettier: bool, has_eslint: bool) -> None:
    # npm exec --no never downloads a package that is missing from node_modules.
    if has_prettier:
        result = sail(root, "npm", "exec", "--no", "--", "prettier", "--write", "--log-level", "warn", relative)
        if result.returncode != 0:
            block(f"Prettier could not format {relative} (does it parse?):\n{result.stdout}{result.stderr}")

    if has_eslint:
        result = sail(root, "npm", "exec", "--no", "--", "eslint", "--fix", relative)
        output = result.stdout + result.stderr
        if result.returncode == 2:
            block(f"ESLint failed to run on {relative} (configuration problem):\n{output}")
        if result.returncode == 1 and "Parsing error" in output:
            block(f"ESLint could not parse {relative}:\n{output}")


def main() -> None:
    try:
        event = json.load(sys.stdin)
    except json.JSONDecodeError:
        return

    file_path = (event.get("tool_input") or {}).get("file_path")
    if not isinstance(file_path, str):
        return

    root = os.path.realpath(os.environ.get("CLAUDE_PROJECT_DIR") or event.get("cwd") or os.getcwd())
    path = os.path.realpath(file_path)
    if not path.startswith(root + os.sep) or not os.path.isfile(path):
        return

    relative = os.path.relpath(path, root)
    extension = os.path.splitext(relative)[1].lower()
    if relative.startswith(SKIPPED_DIRS) or relative in LEGACY_FILES or relative.endswith(".blade.php"):
        return
    if extension not in PHP | FRONTEND:
        return

    has_prettier = os.path.exists(os.path.join(root, "node_modules/.bin/prettier"))
    has_eslint = os.path.exists(os.path.join(root, "node_modules/.bin/eslint"))
    if extension in FRONTEND and not (has_prettier or has_eslint):
        return

    try:
        ensure_app_is_running(root)
        if extension in PHP:
            format_php(root, relative)
        else:
            format_frontend(root, relative, has_prettier, has_eslint)
    except Skip as reason:
        notify(f"format hook: {relative} was not formatted – {reason}.")


if __name__ == "__main__":
    main()
