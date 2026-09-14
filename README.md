# Reproduction for phpstan/phpstan#15231

`return.missing` reported for methods that clearly return a value, because the file is analysed
from a body-stripped AST (the `CleaningVisitor` output meant for files that are *not* analysed).

Two variants, each self-contained: no Composer, no extensions, `phpstan analyse -c phpstan.neon`.

## `duplicate-file/` — portable, works on any filesystem

| File | Role |
| --- | --- |
| `src/Zeta.php` | analysed (inside `paths`) |
| `lib/Zeta.php` | **byte-identical copy**, outside `paths`, reachable only through the autoloader |
| `src/Alpha.php` | analysed, references `Demo\Zeta`, sorts before `Zeta.php` |
| `autoload.php` | three-line `spl_autoload_register` loading `Demo\Zeta` from `lib/` |

```
$ cd duplicate-file && phpstan analyse -c phpstan.neon
  7      Method Demo\Zeta::name() should return string but return statement is missing.
  12     Method Demo\Zeta::size() should return int but return statement is missing.
```

Expected (and what 2.2.9 reports): no errors.

## `case-insensitive-path/` — the real-world case, needs a case-insensitive filesystem (macOS, Windows)

No duplicate file at all. One file, reached through **two spellings of the same path**: PHPStan
analyses `src/Analysis/Zeta.php`, the autoloader requires `src/analysis/Zeta.php`.

```
$ cd case-insensitive-path && phpstan analyse -c phpstan.neon
  7      Method Demo\Analysis\Zeta::name() should return string but return statement is missing.
  12     Method Demo\Analysis\Zeta::size() should return int but return statement is missing.
```

This is how it shows up in a real project. October CMS builds plugin class paths by lowercasing the
directory segments and tries that spelling first
(`October\Rain\Composer\ClassLoader::normalizeClass()` / `loadUpperOrLower()`), so on macOS every
class under `classes/Analysis/` is required as `classes/analysis/`. In a ~200-file plugin this
produced 27 phantom `return.missing` errors, all of them in the one directory whose name is not
lowercase. On Linux the lowercase spelling does not resolve, so nothing autoloads that way and the
bug does not appear — which is why it can look unreproducible.

## Why it happens

1. the class is reached through a path that is not in `paths`, so it is parsed by the "not analysed"
   route and `CleaningVisitor` strips the method bodies;
2. the analysed file that references it is processed first (hence `Alpha` / `Zeta`), so that parse
   happens before the class's own file is analysed;
3. `defaultAnalysisParser: CachedParser(originalParser: pathRoutingParser)` — the AST cache sits
   *outside* `PathRoutingParser` and is keyed by the file's source code, with no record of which
   parser produced the entry, so the analysed file gets the cleaned AST back.

In the `duplicate-file` variant, changing a single byte in either copy makes the errors disappear,
because the two files no longer share a cache entry. In the `case-insensitive-path` variant there is
only one file, so its contents always collide with themselves.

## Versions

- reproduces: 2.2.10, 2.2.11–2.2.14
- clean: 2.2.9
- PHP 8.2.32, 8.3.32, 8.4.23, 8.5.8 (macOS arm64) — identical result on all four
