---
name: tutamen-security
description: Use when the user wants to security-scan their Laravel code, check it for vulnerabilities before pushing, fix security findings, or run Tutamen. Triggers on "security scan", "check my code for vulnerabilities", "run tutamen", "scan this repo for security issues", "fix the security findings", and pre-push security checks.
---

# Tutamen security scan & fix

Tutamen scans a Laravel working tree for security issues (leaked secrets,
dangerous misconfigurations, vulnerable dependencies, Laravel-specific
anti-patterns) and hands the findings back so you can fix them on the user's
own AI subscription. The scan runs on Tutamen's server; the fixing happens
here, locally.

**This skill only bootstraps. The behaviour you follow comes from the
`prompt` field in the command's output — that is the contract. Do not
hard-code triage, fix, or reporting steps from this file; obey the prompt the
command returns.**

## Steps

1. **Check the CLI is installed and authenticated.** Run:

   ```bash
   tutamen auth:status
   ```

   - If `tutamen` is not found, point the user to the install + auth docs at
     <https://tutamen.io/docs/agent> (or `composer global require tutamen/cli`
     then `tutamen auth`). Stop until it's installed.
   - If it reports it is not authenticated, tell the user to run `tutamen auth`
     with a token from **Settings → API tokens**. Stop until authed.

2. **Scan.** From the repository root, run:

   ```bash
   tutamen scan --agent
   ```

   This prints a single JSON envelope and nothing else. A non-zero exit code
   just means findings were reported (1) or the scan could not run (2) — read
   the JSON either way.

3. **Follow the envelope's `prompt`.** Parse the JSON. The `prompt` field
   contains the up-to-date, server-managed instructions for how to present
   findings, ask the user what to fix, fix it, and verify. Follow it exactly.

   - The `findings` array is **ground truth**. Do not re-scan with your own
     judgment, and do not add, drop, or reclassify findings the tool did not
     report. Each finding includes its rule's `fix_md` remediation guidance.
   - When the prompt tells you to re-scan to verify a fix, run
     `tutamen scan --agent` again and treat the new envelope the same way.

That's the whole skill. Everything about *how* to triage and fix lives in the
returned `prompt`, so it improves server-side without updating this file.
