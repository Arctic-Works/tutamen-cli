# Changelog

All notable changes to `tutamen/cli` are documented here. This project follows
[Semantic Versioning](https://semver.org). While on `0.x`, minor releases may
contain breaking changes.

## [Unreleased]

- `tutamen scan --agent` — emit a single JSON envelope (`envelope_version`,
  `prompt_version`, server-managed `prompt`, `scan`, and `findings` with rule
  `fix_md`) for an AI agent to act on. The fix prompt is fetched from the
  server and cached locally for 5 minutes, so a better prompt ships
  server-side without a CLI release. Implies JSON-only output.
- `tutamen skill:install` — install the bundled `tutamen-security` agent skill
  into Claude Code (`.claude/skills`) or Codex (`--agent=codex`, `.codex/skills`),
  per-project or `--global`; `--print` writes it to stdout for any other agent.
  The skill ships with the CLI, so no repo checkout is needed.

## [0.1.0] - 2026-06-14

Initial release.

- `tutamen auth` / `auth:status` / `auth:logout` — store an org-scoped API
  token and server URL in `~/.config/tutamen/config.json` (0600).
- `tutamen scan` — snapshot the working tree (tracked + modified content,
  optional untracked; `.git`/ignored files excluded), upload, wait, and render
  findings with exit codes `0`/`1`/`2`; `--json`, `--fail-on`, `--hook`,
  `--include-untracked`.
- `tutamen hooks:install` / `hooks:uninstall` — manage an append-friendly,
  block-marked pre-push hook (native or Husky) driven by a committed
  `.tutamen.json`.

[Unreleased]: https://github.com/Arctic-Works/tutamen-cli/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/Arctic-Works/tutamen-cli/releases/tag/v0.1.0
