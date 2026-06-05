# Changelog

All notable changes to **Angeo_LlmsTxt** are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [3.0.5] — 2026-06-04

Admin-config bugfix. Safe drop-in upgrade from 3.0.x.

### Fixed

* **System Config "Save Config" no longer throws `Cannot read properties of
  undefined (reading 'settings')`.** The `Generate` button `frontend_model`
  template (`generate_button.phtml`) rendered two `<form>` elements *inside*
  the admin system-config form (`#config-edit-form`). Nested forms are invalid
  HTML: the browser re-parents the inner inputs/buttons onto the outer form, so
  on Save the jQuery validator (`jquery.validate.js metadataRules`) iterated an
  orphaned submit button that has no rule metadata and crashed, aborting the
  whole submit. The buttons are now plain `type="button"` elements that POST via
  a JS-built form appended to `<body>` (outside the config form). CSRF
  protection is unchanged — the form key is still submitted.

---

Install-blocking bugfix plus PHP 8.5 support. Safe drop-in upgrade from 3.0.x.

### Fixed

* **`setup:upgrade` no longer fails XSD validation** on `etc/adminhtml/system.xml`.
  Two `<comment>` elements (`cache_ttl_seconds` and `schedule`) contained raw
  `<code>` HTML without a CDATA wrapper. `system_file.xsd` only allows a `model`
  child inside `<comment>`, so the literal markup tripped
  `Element 'code': This element is not expected. Expected is ( model )` and
  aborted module loading. Both comments are now wrapped in `<![CDATA[ … ]]>`,
  matching every other HTML-bearing comment in the file.

### Changed

* **Added PHP 8.5 to the supported range** (`…||~8.5.0`). Intended for Magento
  2.4.9+, which is the first line to support PHP 8.5; on 2.4.8 and earlier,
  PHP 8.4 remains the recommended runtime.

---

Admin-config bugfix. No functional or API changes — safe drop-in upgrade
from 3.0.x.

### Fixed

* **System Config "Save Config" no longer throws a JS `TypeError`.** Three
  numeric fields in `etc/adminhtml/system.xml` declared validation classes
  that are not registered in Magento's `mage/validation` ruleset
  (`validate-greater-than-zero` and `integer`). On 2.4.8-p4 the admin form
  validator (`jquery.validate.js` `metadataRules`) looks up
  `settings` on each rule object; the missing rules resolved to `undefined`,
  producing `Cannot read properties of undefined (reading 'settings')` and
  aborting the entire form submit. Replaced with registered rules:
  * `collection_page_size`: → `validate-digits validate-digits-range digits-range-0-1000000`
  * `product_limit`: → `validate-digits`
  * `cache_ttl_seconds`: → `validate-digits`

---

## [3.0.4] — 2026-06-03

Compatibility patch. No functional or API changes — safe drop-in upgrade
from 3.0.x.

### Changed

* **Lowered the minimum PHP to 8.1** (`~8.1.0||~8.2.0||~8.3.0||~8.4.0`).
  The module uses no PHP 8.2+ only syntax, so it runs on 2.4.5 / 2.4.6 stores
  that are still on PHP 8.1 as well as on 2.4.7 / 2.4.8 (PHP 8.3 / 8.4).
* **Broadened dependency constraints to cover 2.4.5 through 2.4.8.** Every
  Magento dependency in `require` now uses an open lower-bound (`>=`) pinned to
  the major line that shipped with 2.4.5 — e.g. `magento/framework: >=102.0`
  and `magento/module-url-rewrite: >=102.0`. Because these major lines do not
  change between 2.4.5 and 2.4.8, the module installs cleanly across all of
  those minors. This replaces the earlier exact carets (such as the `^101.2`
  on `module-url-rewrite`) that failed on 2.4.8, where that module ships as
  102.x.

---

## [3.0.2] — 2026-06-03

Marketplace-readiness patch. No functional or API changes — safe drop-in
upgrade from 3.0.0.

### Fixed

* **Replaced `md5()` with `hash('sha256', …)`** for ETag generation in the
  file-serving controller. The Magento Coding Standard forbids `md5()`; the
  ETag only needs to be stable and unique, so the switch is behaviour-neutral.
* **Removed error-silencing `@` operators** from filesystem calls
  (`fopen` / `flock` / `fclose`) in the atomic-write lock helper and in the
  validate command. Return values were already checked explicitly, so
  dropping `@` changes no behaviour while clearing the coding-standard errors.

### Changed

* **Dependency constraints pinned to real 2.4.x major lines.** `require` now
  uses caret ranges matching the actual published modules — notably
  `magento/module-url-rewrite: ^102.0` (the 101.2 line never existed). This
  resolves a `composer require` failure on clean 2.4.8 installs.
* Added an explicit `version` field (`3.0.1`) to `composer.json` so the
  package version matches the Marketplace submission form.

---

## [3.0.0] — 2026-05-23

A full rebuild against the architectural review of 2.1.4. This release is
**not drop-in compatible** — see the *Breaking Changes* section below for
migration steps.

### Breaking changes

* **`ProviderInterface::provide()` signature changed** from `string` to
  `iterable<string>`. Custom providers contributed by third-party modules
  must now yield chunks rather than return one concatenated string. This is
  the change that lets the generator stream to disk with bounded memory.
* **`/llms-full.txt` now serves a genuinely-different file** (full sanitized
  descriptions inline). Previously, this URL silently aliased to `/llms.txt`,
  which was misleading.
* **llms.txt header is now spec-compliant.** A single blockquote summary line,
  with currency / locale / base-URL moved to a plain markdown paragraph below.
  The 2.x output used four blockquote lines, which broke llmstxt.org-spec
  parsers.
* **Status tracking moved** out of `core_config_data` and into
  `var/angeo_llms/status.json`. Old status rows under `angeo_llms/status/*`
  are no longer read. Drop them via `bin/magento config:set --lock-env angeo_llms/status/... ""` if you want a clean state, but it's harmless to leave them.
* **`media/llms/` is no longer used** as the file output directory; output now
  lives under `media/angeo/llms/`. Old files can be deleted; remove any reverse-proxy rewrites pointing at the old path.
* **Admin "Generate" action moved to POST + CSRF**. If you have any external
  tooling that hit the old GET URL, switch to the CLI command instead.
* **Module namespace unchanged**: still `Angeo\LlmsTxt`. Composer package
  name unchanged.

### Added

* **Page Builder element filter** with four strategies — *preserve*, *exclude*,
  *allow*, *strip* — driven by the element's `data-content-type` attribute.
  Default list of excluded types drops common visual-only elements
  (products carousel, banner, slider, video, map, buttons, block,
  dynamic-block, divider, spacer) so the output focuses on semantic text.
  Configurable per-store at *Stores → Configuration → Angeo → LLMs.txt →
  Content Sanitization*.
* **Streaming generation** via PHP generators. Memory stays bounded at one
  collection page (default 1000 products) regardless of catalog size.
* **Atomic writes**: each file is written to `.tmp`, then renamed. Readers
  never see a half-written file. Generation locks via a separate `.lock` file
  with `flock(LOCK_EX | LOCK_NB)`, so concurrent runs cannot corrupt output.
* **Cursor pagination** by `entity_id ASC > $lastId` instead of skip/limit, so
  products inserted mid-run can neither be duplicated nor skipped.
* **Batch URL resolver** loads every URL rewrite for a store in one query
  (vs. the per-product `getProductUrl()` query that 2.x triggered N times).
* **Real `llms-full.txt`** with full sanitized descriptions inline.
* **`/{url_key}.md` mirrors** — every product, category, and CMS page exposes
  a clean Markdown rendering at its URL with `.md` appended. Generated on the
  fly; no extra disk storage.
* **CMS directive resolution** — `{{widget}}`, `{{block}}`, `{{var}}`, and
  `{{store}}` directives are now rendered via Magento's standard frontend
  filter before being stripped, instead of leaking as literal text.
* **Customer-group-aware pricing** — admin can choose which customer group's
  final price (with special-price and group-price applied) gets exposed.
* **HTTP caching** — `ETag`, `Last-Modified`, `Cache-Control: public, max-age=`,
  `X-Robots-Tag: noindex, follow`, and 304 responses on conditional GETs.
* **Async admin action** — *Schedule (Async)* inserts a `cron_schedule` row for
  the next tick so admins don't have to wait through a synchronous generation.
* **Live admin status panel** polling `/angeo_llms/status/index` every 60s.
* **Three CLI commands**:
  * `bin/magento angeo:llms:generate [--store=…] [--no-jsonl] [--no-llms] [--no-full]`
  * `bin/magento angeo:llms:status`
  * `bin/magento angeo:llms:validate [--store=…]`
* **JSONL JSON-Schema** at `etc/jsonl-schema.json` for downstream pipelines.
* **Events**: `angeo_llms_generation_before`, `angeo_llms_generation_after`,
  `angeo_llms_generation_failed` — for custom hooks.
* **PHPUnit test suite** under `Test/Unit/`.

### Changed

* `frontend_default_meta_description` is now the fallback for the store
  summary, before falling back to the generic stub.
* Multi-store store-code routing handles the last URL path segment, so
  `/de/llms.txt` works on path-based stores.
* Spec compliance: products go under `## Optional` by default (admin
  toggleable) so context-budget-constrained clients can drop them.
* Out-of-stock products excluded by an explicit `StockRegistry` lookup
  (configurable).
* Logger context is now structured: every log line is prefixed
  `[Angeo LlmsTxt]` and includes store/format keys.

### Fixed

* **Pseudo-locking** in 2.x: a `'w'` open truncates the file before the
  `flock()` call, so two concurrent generations both saw an empty file and
  the last writer won unpredictably. 3.0 uses a separate `.lock` file.
* **CSRF-exposed admin generate**: 2.x used a GET URL; 3.0 requires POST with
  the form key.
* **Synchronous admin "Generate" timing out** on large catalogs (now async option).
* **N+1 URL rewrite queries**: now batched.
* **Literal `{{widget}}` text** appearing in 2.x output: now resolved.
* **Stale files** for stores that became inactive or excluded: now cleaned up
  on every generation run.

### Removed

* `media/llms/` legacy directory (see breaking-changes notes).
* GET endpoint for admin generation.
* Documented-but-non-existent config fields from 2.x README.

---

## [2.1.4] — Pre-rebuild baseline

Last release in the 2.x line. See the architectural review document for
the issues that motivated 3.0.0.