# Changelog

All notable changes to `tutamen/cli` are documented here. This project follows
[Semantic Versioning](https://semver.org). While on `0.x`, minor releases may
contain breaking changes.

## [Unreleased]

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
