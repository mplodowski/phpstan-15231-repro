# Reproduction for phpstan/phpstan#15231

`return.missing` reported for methods that clearly return a value, because the file is analysed
from a body-stripped AST (the `CleaningVisitor` output meant for files that are *not* analysed).

## Run

```
phpstan analyse -c phpstan.neon
```

Actual (2.2.10 and later, checked up to 2.2.14):

```
 ------ ----------------------------------------------------------------------
  Line   Zeta.php
 ------ ----------------------------------------------------------------------
  7      Method Demo\Zeta::name() should return string but return statement is missing.
  12     Method Demo\Zeta::size() should return int but return statement is missing.
 ------ ----------------------------------------------------------------------
```

Expected (and what 2.2.9 reports): no errors.

## What the three files do

| File | Role |
| --- | --- |
| `src/Zeta.php` | analysed (inside `paths`) |
| `lib/Zeta.php` | **byte-identical copy**, outside `paths`, reachable only through the autoloader |
| `src/Alpha.php` | analysed, references `Demo\Zeta`, sorts before `Zeta.php` |
| `autoload.php` | `spl_autoload_register` that loads `Demo\Zeta` from `lib/` |

All four conditions are needed:

1. two copies of the same class file with **identical contents** — one analysed, one only autoloadable;
2. an analysed file that references the class and is processed **before** the analysed copy
   (hence `Alpha` / `Zeta`), so the autoloader is asked for the class first;
3. the autoloaded copy is parsed through the "not analysed" route (`CleaningParser`), which strips
   method bodies;
4. `defaultAnalysisParser: CachedParser(originalParser: pathRoutingParser)` — the AST cache sits
   *outside* `PathRoutingParser` and is keyed by the file's source code with no record of which
   parser produced the entry, so the analysed copy gets the cleaned AST back.

Changing a single byte in either copy (e.g. appending a newline) makes the errors disappear, because
the two files then no longer share a cache entry.

## Versions

- reproduces: 2.2.10, 2.2.11–2.2.14
- clean: 2.2.9
- PHP 8.2.32, 8.3.32, 8.4.23, 8.5.8 (macOS arm64) — identical result on all four
- no Composer, no extensions, no bootstrap beyond the three-line `autoload.php`
