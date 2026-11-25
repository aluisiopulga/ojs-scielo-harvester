# OJS & SciELO Harvester

[![tests](https://github.com/aluisiopulga/ojs-scielo-harvester/actions/workflows/tests.yml/badge.svg)](https://github.com/aluisiopulga/ojs-scielo-harvester/actions/workflows/tests.yml)
![PHP](https://img.shields.io/badge/PHP-8.1%2B-777bb4)
![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue)

Collects article metadata from scholarly journals running **[OJS](https://pkp.sfu.ca/software/ojs/)** (via its REST API)
and from journals hosted on **[SciELO](https://www.scielo.org/)** (via the public article pages), and writes
everything to **one deduplicated CSV file** that can be imported into Drupal with the
[Feeds](https://www.drupal.org/project/feeds) module, or into any other system.

It is a cleaned-up, generic version of a pipeline I built for a portal that aggregates
journals from a large research institution. In production it keeps
**about 25,000 articles from 9 journals** in sync.

## Why this exists

Journal portals often need to show articles that live in several places: some journals run
their own OJS instance (each one a different version), others are published on SciELO.
Getting clean, consistent metadata from all of them is harder than it looks:

| Problem | How it is handled |
|---|---|
| OJS 3.3 uses `en_US`/`es_ES` locale keys, 3.4+ uses `en`/`es` | `Localized::pick()` tries `pt_BR → en → en_US → es → es_ES`, then any filled locale |
| Empty translations such as `"."` or `"<p></p>"` | Treated as missing, so the next language is used |
| OJS 3.4+ returns keywords as `{"name": ...}` objects | Normalised to plain strings (older versions printed `Array`) |
| OJS caps every page at 100 items, whatever `count` says | All lists are paginated with `offset` until `itemsMax` |
| Future issues already exist in OJS without a publication date | Ignored, so they don't shift the "latest year" |
| OJS answers **403** for a dead token *and* for a missing editor role | `check` reads the error code in the body and explains which one it is |
| Some Apache setups drop the `Authorization` header | Optional `token_in_query` sends the token as `?apiToken=` as well |
| SciELO has no API for this; each article appears under 2–3 links | Links are grouped by article PID; metadata is read from the Portuguese version when available |
| SciELO pages have no clean publication date | `FuzzyDate::find()` uses the `Epub` date from the suggested citation, or a date within one year of the issue |
| SciELO sometimes returns an error page with HTTP 200 | Pages without `citation_title` are retried once, then skipped, so they never overwrite good data in Drupal |

## Requirements

- PHP 8.1+ with `curl`, `dom` and `mbstring`
- Composer
- For OJS: an API token of a user with an **editor or journal manager** role in each journal
  (OJS → your profile → *API Key*)

## Installation

```bash
git clone https://github.com/aluisiopulga/ojs-scielo-harvester.git
cd ojs-scielo-harvester
composer install
cp config/journals.example.php config/journals.php
```

Edit `config/journals.php` with your journals. Keep tokens out of the file by using
environment variables:

```bash
export OJS_TOKEN_JHS="eyJ0eXAiOiJKV1Qi..."
```

`config/journals.php` is git-ignored.

## Usage

Check that every token and site works before harvesting:

```bash
bin/harvest check
```

```
[OK] OJS    jhs      HTTP 200 - 412 published items
[!!] OJS    rde      HTTP 403 - User has no editor/manager role in this journal
[OK] SciELO abc      38 issues found
```

Harvest the latest two years (the default, ideal for a nightly cron job):

```bash
bin/harvest run
```

Other options:

```bash
bin/harvest run --years=1                       # only the latest year
bin/harvest run --from=2018 --to=2025           # a fixed range (first full import)
bin/harvest run --only=ojs --journal=jhs        # a single journal
bin/harvest run --output=/var/feeds/articles.csv
```

## Output

One row per article, UTF-8, comma-separated:

| Column | Example |
|---|---|
| `journal` | Journal of Health Sciences |
| `unique_id` | `jhs-art_12_77_501` (OJS) / `abc-Xk9pQrT3mN` (SciELO) |
| `title`, `abstract`, `keywords`, `authors` | keywords and authors separated by `; ` |
| `published_at` | `2025-03-10 00:00:00` |
| `doi`, `article_url`, `pdf_url`, `references` | |
| `publisher` | |
| `issue_title`, `issue_identification`, `issue_year`, `issue_url` | `v. 31 n. 2 (2025)` |

`unique_id` is stable across runs. In Drupal Feeds, map it to the **GUID** and set the
processor to *update existing content*: the nightly import then updates articles instead of
duplicating them.

### Drupal Feeds setup (Drupal 10/11)

1. Create an *Article* content type with fields for the columns you need.
2. Create a Feed type with the **CSV parser** and the **Node processor**.
3. Map `unique_id` → *Feeds item (GUID)*, marked as unique.
4. Under *Processor settings*, choose **Update existing content**.
5. Point the fetcher to the generated file (or upload it) and schedule the import with cron.

## Running in production

- Run it from the **command line or cron only**. The script refuses to run through a web server,
  and it should never live inside your public web root.
- TLS verification is **on**. On Windows/WampServer, if you get certificate errors, download
  [cacert.pem](https://curl.se/docs/caextract.html) and set `ca_bundle` in the config. Don't turn verification off.
- There is a pause between requests (`delay_seconds`) and automatic retries with backoff on
  network errors, HTTP 429 and 5xx.

## Project structure

```
bin/harvest                 CLI entry point (run, check)
config/journals.example.php Example configuration
src/
  Http/                     cURL client with TLS, throttling and retries
  Ojs/                      OJS REST client, mapper and harvester
  Scielo/                   SciELO HTML parser and harvester
  Output/                   Article value object and CSV writer (deduplicates)
  Support/                  Localized fields, fuzzy dates, year filter, text helpers
tests/                      PHPUnit tests with saved OJS JSON and SciELO HTML fixtures
```

## Tests

```bash
composer test
```

The parsers and mappers are pure functions, so the tests run against saved OJS JSON
responses and SciELO HTML pages, with no network access.

## Roadmap

- Drupal 10/11 module with a Drush command that imports directly into nodes (no CSV step)

## License

GPL-2.0-or-later, the same license as Drupal.

## Author

**Aluisio Ricardo Marques Pulga**: Senior Software Architect, Drupal & Harvard Dataverse.
[LinkedIn](https://www.linkedin.com/in/aluisiopulga) · [Drupal.org](https://www.drupal.org/u/aluisiopulga)
