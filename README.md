# Tutamen CLI

Scan your Laravel working tree for security issues **before you push**.

`tutamen` snapshots your local code, uploads it to [Tutamen](https://tutamen.io),
and the server runs the same sandboxed scanners as a repository scan — secrets
(gitleaks), dependency CVEs (composer audit), SAST (opengrep) and Laravel-aware
AST rules. Results are returned to you with a meaningful exit code, so a
pre-push git hook can block risky pushes. Nothing is scanned locally and no
rule binaries are distributed — there is nothing to keep up to date but this
package.

## Requirements

- PHP 8.3+
- `git` and `tar` on your `PATH`
- A Tutamen account and an API token (Settings → API tokens)

## Install

```bash
composer global require tutamen/cli
```

Make sure Composer's global bin directory is on your `PATH` (typically
`~/.composer/vendor/bin` or `~/.config/composer/vendor/bin`). Then:

```bash
tutamen --version
```

## Authenticate

Create an org-scoped token in the dashboard under **Settings → API tokens**
(it is shown once), then:

```bash
tutamen auth --server=https://app.tutamen.io
# paste your token when prompted (input is hidden)

tutamen auth:status   # show the server and a masked token
tutamen auth:logout   # forget stored credentials
```

Credentials are stored in `~/.config/tutamen/config.json` with `0600`
permissions. This file is personal — never commit it. The committed
`.tutamen.json` (below) holds hook settings only and must never contain a
token; the CLI refuses to read it if it looks like it does.

## Scan

From anywhere inside your repository:

```bash
tutamen scan                      # tracked + locally-modified files
tutamen scan --include-untracked  # also untracked, non-ignored files
tutamen scan --fail-on=high       # only fail on high/critical findings
tutamen scan --json               # machine-readable envelope
tutamen scan --agent              # one JSON envelope for an AI agent (see below)
```

The snapshot includes your tracked files at their **current** working-tree
content (so a secret you just edited but have not committed is still caught)
and excludes anything git ignores — `vendor/`, `node_modules/` and `.git/` are
never uploaded. Results are ephemeral: they are returned to you and never
merged into your repository's dashboard findings.

### Fix with your own AI (`--agent`)

`tutamen scan --agent` emits a single JSON envelope an AI agent can act on:

```jsonc
{
  "envelope_version": 1,
  "prompt_version": 1,
  "prompt": "…server-managed instructions for the agent…",
  "scan": { "id": "…", "status": "completed", "stats": { … } },
  "findings": [ { "rule_id": "…", "fix_md": "…", … } ]
}
```

The `prompt` is fetched from the server (and cached locally for 5 minutes), so
the behaviour an agent follows improves server-side without a CLI release. Each
finding carries its rule's `fix_md` remediation guidance. The agent reads
findings and edits locally on **your** AI subscription — your code never goes
to a third-party model by us.

### Install the agent skill

The `tutamen-security` skill ships with this CLI. It tells your agent to run
`tutamen scan --agent`, then follow the server-managed prompt in the output.
Install it into your agent's skills directory:

```bash
tutamen skill:install                 # Claude Code, this project (.claude/skills)
tutamen skill:install --global        # Claude Code, all projects (~/.claude/skills)
tutamen skill:install --agent=codex   # Codex CLI (.codex/skills) — add --global too if you like
tutamen skill:install --print         # print SKILL.md to stdout (any other agent)
```

Both Claude Code and Codex auto-discover the skill; just ask your agent to
"scan this repo for security issues". Re-running `tutamen skill:install` after
a `composer global update tutamen/cli` refreshes the installed skill.

### Exit codes

| Code | Meaning |
|------|---------|
| `0`  | Clean — no findings at or above your threshold |
| `1`  | Findings at or above the threshold (the push is blocked when run from a hook) |
| `2`  | The scan could not run (not authenticated, network error, server error) |

The threshold is set with `--fail-on=critical|high|medium|low|any` (default
`any`) or in `.tutamen.json`. An explicit flag always wins.

## Pre-push hooks

```bash
tutamen hooks:install                                   # native .git/hooks/pre-push
tutamen hooks:install --husky                           # .husky/pre-push instead
tutamen hooks:install --branches='^(main|release/.*)$' --fail-on=high
tutamen hooks:uninstall                                 # remove the tutamen hook
```

`--branches` and `--fail-on` are written into a committed `.tutamen.json` so
your whole team shares them:

```json
{
  "hooks": {
    "branches": "^(main|release/.*)$",
    "failOn": "high"
  }
}
```

The hook only scans branches matching `branches` (omit it to scan every
branch). It is installed as a clearly marked block, so an existing hook is
appended to — never clobbered — and `hooks:uninstall` removes only Tutamen's
part. A blocked push shows the findings and reminds you that
`git push --no-verify` bypasses the hook once.

## Continuous integration

The exit codes make `tutamen scan` usable in any CI pipeline without a
dedicated integration:

```bash
tutamen scan --server="$TUTAMEN_SERVER" --token="$TUTAMEN_TOKEN" --fail-on=high
```

## License

MIT © Arctic Works. See [LICENSE](LICENSE).
