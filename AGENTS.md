## Cursor Cloud specific instructions

This is a PHP 8.5 CLI application using the Tempest framework. There is no web server, database, or Docker setup — it's a pure CLI tool.

### System dependencies

- **PHP 8.5** is required (`php: ^8.5` in `composer.json`). Install from the `ppa:ondrej/php` PPA with extensions: `php8.5-cli php8.5-common php8.5-mbstring php8.5-xml php8.5-curl php8.5-zip php8.5-bcmath php8.5-bz2 php8.5-gd php8.5-gmp php8.5-intl php8.5-ldap php8.5-mysql php8.5-odbc php8.5-pgsql php8.5-sqlite3 php8.5-soap php8.5-tidy php8.5-igbinary php8.5-sockets php8.5-readline`.
- **Composer** is required for dependency management.

### Key commands (see README.md for full details)

- `composer install` — install dependencies
- `php tempest data:generate --force` — generate 1M-row test CSV (use `--force` to skip the interactive confirmation prompt)
- `php tempest data:parse` — run the parser on generated data
- `php tempest data:validate` — validate parser output against expected results (runs on the small test dataset)

### Gotchas

- Interactive Tempest commands (like `data:generate`) require `--force` to skip confirmation prompts in non-TTY environments.
- There are no automated tests (no PHPUnit test files or `phpunit.xml`).
- There is no linter configuration (no PHP-CS-Fixer, PHPStan, or Psalm config).
- `app/Parser.php` is a TODO template for challenge participants. The `data:parse` and `data:validate` commands will fail until a parser is implemented.
