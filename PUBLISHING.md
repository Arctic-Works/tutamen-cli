# Publishing `tutamen/cli`

Development happens here in the monorepo (`cli/`). For distribution, the
directory is **subtree-split** into a standalone, read-only mirror repo that
Packagist watches. Packagist requires `composer.json` at a repo root, so it
cannot read `cli/` directly — the mirror is what makes the package installable
with a plain `composer global require tutamen/cli`.

```
monorepo (Tutamen)/cli   --- git subtree split on tag --->   Arctic-Works/tutamen-cli   --- webhook --->   Packagist
```

## One-time setup

1. **Create the mirror repo** `Arctic-Works/tutamen-cli` on GitHub (empty, public).
   If you use a different org/name, update `.github/workflows/cli-split.yml`
   (`repository_organization` / `repository_name`) and the URLs in
   `cli/composer.json` and `cli/CHANGELOG.md`.

2. **Add a push token.** Create a Personal Access Token (or fine-grained token)
   with `contents: write` on the mirror repo, and add it to **this** repo's
   Actions secrets as `CLI_SPLIT_TOKEN`. The split workflow uses it to push the
   branch and tags to the mirror.

3. **Submit the mirror to Packagist.** On <https://packagist.org>, "Submit"
   `https://github.com/Arctic-Works/tutamen-cli`, then enable the GitHub
   auto-update hook Packagist offers (so new tags publish automatically).

## Cutting a release

Releases are driven by **prefixed tags** on the monorepo so they don't collide
with other packages here. The workflow strips the prefix when pushing to the
mirror, so `cli-v0.1.0` becomes `v0.1.0` on the mirror and on Packagist.

```bash
# from the monorepo root, on main, with cli/ changes committed
git tag cli-v0.1.0
git push origin cli-v0.1.0
```

The `Split CLI to publish repo` workflow then pushes `v0.1.0` to the mirror and
Packagist publishes it. Verify with:

```bash
composer global require tutamen/cli
tutamen --version   # -> 0.1.0
```

## Release checklist

- [ ] `cd cli && vendor/bin/pest` is green.
- [ ] `composer validate --strict` passes.
- [ ] `CHANGELOG.md` has an entry for the version.
- [ ] Version bump follows semver (breaking changes allowed in `0.x` minors).
- [ ] Tag is `cli-vX.Y.Z` (note the prefix).

## Notes

- The `--version` string comes from the installed Composer metadata
  (`InstalledVersions`), so it always matches the published tag — there is no
  version constant to bump by hand.
- `.gitattributes` keeps `tests/`, `phpunit.xml` and these docs out of the
  distributed archive.
