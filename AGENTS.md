# momphp monorepo

The monorepo for the Mom PHP framework, built on Laravel 13 and PHP 8.4.

| Package | Directory | Published as | Read-only split repo |
|---|---|---|---|
| Typed data objects | `packages/data` | `mom/data` | `momphp/data` |
| Application skeleton | `packages/mom` | `mom/mom` | `momphp/mom` |
| `mom new` CLI | `packages/installer` | `mom/installer` | `momphp/installer` |

All development happens here. The split repos are overwritten on every push, so never commit to them directly.

## Commands

```bash
composer install
vendor/bin/pest                                   # all tests
vendor/bin/pest packages/data                     # one package
vendor/bin/pint                                   # code style (pint.json)
vendor/bin/phpstan analyse packages/data/src --level=5
vendor/bin/monorepo-builder validate              # package composer.json files agree with each other
vendor/bin/monorepo-builder merge                 # copy package requirements into the root composer.json
```

Before committing, run Pint, PHPStan and the tests for the package you changed.

## Dependencies (monorepo-builder)

- The root `composer.json` is generated. To add or change a dependency, edit the **package's** `composer.json`, then run `vendor/bin/monorepo-builder merge` and `composer update`.
- monorepo-builder re-indents JSON to 4 spaces and turns empty `{}` into `[]`. Expect that in diffs.
- Root-only tooling (testbench, monorepo-builder, phpstan) lives in the root `require-dev`.

## Code conventions

- `declare(strict_types=1);` in every PHP file. Pint enforces the house style: Yoda-style `null === $x`, `false === …`, `mb_*` string functions and imported global classes.
- Where PHPStan can't understand Laravel's dynamic code, use an end-of-line `// @phpstan-ignore <identifier>`. Pint moves docblock ignores away from the line they belong to.
- `packages/data`:
  - Its tests live in `packages/data/tests/Unit`, using the fixtures under `tests/Unit/User` and `tests/Unit/Role`.
  - The package must keep working when tested on its own (`composer test` inside the split repo), so its tests use its own `tests/TestCase.php` built on Orchestra Testbench.
  - How to use the package is described in `packages/data/README.md` and `packages/data/resources/boost/guidelines/core.blade.php`. Update both when the public API changes.
- `packages/mom` is a stock Laravel 13 skeleton. Its tests are excluded from the root suite in `phpunit.xml`.
- `packages/installer/tests-output` holds generated apps and is excluded from the suite. Don't commit what's in it.

## Releasing

Releases are done only when the maintainer asks.

1. The working tree must be clean. `TagVersionReleaseWorker` runs `git add .`, so stash any local IDE files first.
2. `vendor/bin/monorepo-builder release vX.Y.Z` tags the commit, pushes the tag, then pushes an "open X.Y-dev" commit to `main`. The workers are configured in `monorepo-builder.php`.
3. `.github/workflows/split.yml` splits each package into its own repo on every push to `main` and on every tag.

### Check every split

The split workflow can go green without publishing anything, so after every push or release:

- **Look for errors.** Search the run log for `fatal:`. A failed push still shows a green run, for example when the `ACCESS_TOKEN` secret is missing or expired.
- **Make sure a run exists for the tag.** When `main` and the tag are pushed within seconds of each other, GitHub sometimes skips the tag run. Check with `gh run list --workflow split.yml`. If the run is missing, delete the remote tag and push it again (with the maintainer's approval). There is no manual trigger.
- **Confirm the split repos changed.** Check that `momphp/<package>` has the new commit or tag: `gh api repos/momphp/<package>/tags`.

GitHub Releases are separate from tags. Create them on the split repos with `gh release create` when asked.

## Packagist

The packages are published from the split repos. The package page on packagist.org isn't what Composer reads: Composer reads `https://repo.packagist.org/p2/mom/<package>.json`. Check that file, or run `composer show mom/<package> --all --no-cache`, before saying a version can be installed.
