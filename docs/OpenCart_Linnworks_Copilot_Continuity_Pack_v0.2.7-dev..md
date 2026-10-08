# OpenCart to Linnworks Integration: Copilot Continuity Pack

**Current working release:** `v0.2.7-dev.1`  
**Active development branch:** `v0.2.7-dev`  
**Protected baseline:** `version_0_2_5_test`  
**Current status:** Working development checkpoint  
**Last updated:** 8 October 2026

## Purpose

Use this document as the project source of truth when continuing development in a new Copilot chat. It records the verified architecture, source-control rules, packaging requirements, schema facts, completed features, defects, migrations, test evidence, safety boundaries, and next release scope.

## 1. Non-negotiable instructions for Copilot

- Start from the real repository source. Never reconstruct the extension from memory.
- Preserve the known-good OpenCart 4.1.0.4 extension structure, namespaces, routes, language resources, permissions and installer behaviour.
- Apply incremental patches only.
- Inspect all affected files before generating a new installer.
- Keep the installer filename exactly `linnworks.ocmod.zip`.
- Store version numbers in `install.json`, release notes, Git tags and GitHub releases, not in the uploaded installer filename.
- Use `DB_PREFIX` in executable SQL. Never hard-code `oc_`.
- Never assume a standard OpenCart catalogue schema.
- Preserve the current read-only or dry-run boundary unless Lee explicitly approves a live-write phase.
- Do not expose API secrets, permanent tokens, session tokens or passwords in code, logs, documentation or chat.
- Lee is not a developer. Provide complete installers, complete files and explicit test instructions rather than partial snippets.

## 2. Project context

- Product: reusable OpenCart 4.1.0.4 to Linnworks integration extension.
- Organisation: Spectrum Brands.
- Current store: GB Appliances employee store.
- Hosting: Windows Server and IIS.
- The integration will later be installed on another OpenCart store.
- Each store should have its own extension installation and configuration.
- OpenCart is the product master for the intended integration design.
- Product variants are not used in the current OpenCart design.
- EAN is the primary product identifier.
- Model is the secondary reference and later matching fallback.
- Business rules should be configurable rather than hard-coded.
- The OpenCart server can make outbound HTTPS calls.
- The OpenCart application is not intended to be publicly exposed for Linnworks callbacks.

## 3. Repository and source of truth

Repository:

`SPB105438/opencart-linnworks-integration`

OneDrive working path:

```text
Documents\GitHub\opencart-linnworks-integration
```

Protected baseline source:

```text
source\version_0_2_5_test
```

Current development source:

```text
source\version_0_2_7-dev
```

Current branch:

```text
v0.2.7-dev
```

Current working release/tag:

```text
v0.2.7-dev.1
```

### Source-control rule

`version_0_2_5_test` remains a protected recovery baseline. New work should continue from the current verified working release, while preserving the ability to compare every change against the v0.2.5 foundation.

For every release, retain:

- Source tree
- `linnworks.ocmod.zip`
- `install.json`
- README and release notes
- Specification or continuity update
- Test evidence
- Git tag and GitHub release

## 4. Known-good extension structure

```text
admin/
├── controller/module/linnworks.php
├── language/en-gb/module/linnworks.php
├── model/module/linnworks.php
└── view/template/module/
    ├── linnworks.twig
    ├── linnworks_scan_results.twig
    ├── linnworks_scan_history.twig
    ├── linnworks_discovered_products.twig
    └── linnworks_discovery_history.twig

catalog/
├── controller/channel.php
└── model/channel.php

install.json
README.md
README.txt
```

Do not add an extra nested `extension/linnworks` directory inside the installer ZIP. OpenCart places the package beneath its extension directory during installation.

## 5. Packaging and OpenCart rules

### Installer filename

The uploaded installer must be named exactly:

```text
linnworks.ocmod.zip
```

A versioned ZIP filename can change the extension code or installed directory and break routes, namespaces, language loading and permissions.

### Extension identity

```text
HPC Linnworks Integration
```

### Base admin route

```text
extension/linnworks/module/linnworks
```

### OpenCart 4.1 action routes

```text
extension/linnworks/module/linnworks|test
extension/linnworks/module/linnworks|locations
extension/linnworks/module/linnworks|diagnostics
extension/linnworks/module/linnworks|scan
extension/linnworks/module/linnworks|scanResults
extension/linnworks/module/linnworks|scanHistory
extension/linnworks/module/linnworks|exportScan
extension/linnworks/module/linnworks|discoverProducts
extension/linnworks/module/linnworks|discoveredProducts
extension/linnworks/module/linnworks|discoveryHistory
extension/linnworks/module/linnworks|exportDiscovery
```

### Controller error property

The admin controller must retain:

```php
private array $error = [];
```

### Directly routed controller actions

Any routed action that uses the model before calling a helper must first load it:

```php
$this->load->model('extension/linnworks/module/linnworks');
```

This is now present in:

- `scanResults()`
- `scanHistory()`
- `discoveredProducts()`
- `discoveryHistory()`
- `exportScan()`
- `exportDiscovery()`

## 6. Existing settings to preserve

```text
status
application_id
application_secret
token
auth_url
timeout
public_base_url
channel_name
channel_friendly_name
dry_run
stock_location_id
price_authority
price_field
discovery_page_size
discovery_max_pages
discovery_stock_levels
discovery_composite_parents
discovery_variation_parents
```

OpenCart setting names use the `module_linnworks_` prefix.

## 7. Architecture decision

The current architecture uses outbound Linnworks API calls rather than depending on a native Linnworks Channel Integration callback flow.

```text
Internal OpenCart 4.1.0.4
        |
        | outbound HTTPS
        v
Linnworks API
        |
        v
SAP S/4 downstream integration
```

Consequences:

- Do not require inbound firewall access for current features.
- Do not publicly expose OpenCart admin or storefront for Linnworks callbacks.
- Treat old catalog channel endpoints as legacy compatibility code unless Lee explicitly changes the architecture.
- Keep Linnworks and SAP writes disabled during the current development phase.

## 8. Authentication and API behaviour

The working connection uses:

- Application ID
- Application Secret
- Permanent Token
- Authentication endpoint
- Timeout

The authentication response supplies the regional server and temporary token used by subsequent API requests.

Preserve the working methods and behaviour around:

```text
authorize()
locations()
test()
diagnostics()
```

Never log:

- Application Secret
- Permanent Token
- Temporary session token
- Authentication headers
- Passwords

A previously exposed live secret must be treated as compromised and regenerated before production use.

## 9. Confirmed OpenCart catalogue schema

### Product table

```text
{DB_PREFIX}product
```

Confirmed relevant fields include:

```text
product_id
model
price
quantity
status
```

Do not query `product.ean`.

### Product-code table

```text
{DB_PREFIX}product_code
```

Confirmed relevant fields:

```text
product_code_id
product_id
code
value
```

EAN is represented as:

```text
code = EAN
value = actual EAN value
```

Correct pattern:

```sql
LEFT JOIN {DB_PREFIX}product_code pc
  ON pc.product_id = p.product_id

CASE
  WHEN UPPER(pc.code) = 'EAN'
  THEN TRIM(pc.value)
END
```

Permanent rule:

```text
Never query {DB_PREFIX}product.ean.
```

## 10. Product identity strategy

### Primary identifier

```text
OpenCart: product_code.value where UPPER(code) = 'EAN'
Linnworks: barcode field returned by the inventory API
```

### Secondary identifier

```text
OpenCart: product.model
Linnworks: SKU or item number, subject to the mapping design
```

### Proposed matching sequence for v0.2.8-dev

```text
1. Exact EAN match
2. Normalised model fallback
3. Manual review
4. Explicit approval
```

No mapping write or operational sync should occur merely because a suggested match exists.

## 11. v0.2.6-dev.1 Catalogue Scanner

### Status

Working and verified.

### Capability

- Read-only OpenCart catalogue analysis
- Products analysed
- Ready count
- Warning count
- Critical count
- EAN coverage
- Missing EAN detection
- Duplicate EAN detection
- Invalid EAN-format detection
- Missing-model detection
- Price checks
- Negative-stock checks
- Disabled-product information
- Readiness scoring
- Detailed results
- Scan history
- CSV export

### Scanner tables

```text
{DB_PREFIX}linnworks_scan
{DB_PREFIX}linnworks_scan_product
{DB_PREFIX}linnworks_scan_issue
```

### Latest visible scanner evidence

```text
Products analysed: 669
Ready: 668
EAN coverage: 99.9%
```

The screenshots provided during testing show these values. Treat future figures as live data that may change with the catalogue.

## 12. Scanner schema migration lesson

A previous scanner table used `identifier_value` instead of `ean`.

`CREATE TABLE IF NOT EXISTS` did not alter the existing table, which caused:

```text
Unknown column 'ean' in 'field list'
```

The repair added an idempotent migration that:

1. Inspects the existing columns.
2. Adds `ean` when missing.
3. Copies compatible values from `identifier_value`.
4. Preserves existing scan records and history.

Permanent rule:

```text
CREATE TABLE IF NOT EXISTS is not a complete upgrade strategy.
```

Every new schema change must inspect and migrate existing installations before querying new columns.

## 13. Stock Location persistence

### Status

Fixed and visually verified.

The selected location now survives save and reload. The visible selected value during testing was:

```text
SAP Location
```

### Setting key

```text
module_linnworks_stock_location_id
```

### Required behaviour

- Save the selected location ID.
- Normalise the location ID to a string.
- Compare the saved value with each option value.
- Apply `selected` to the match.
- Retain the saved value if the location temporarily disappears.
- Do not silently substitute another warehouse.

## 14. v0.2.7-dev.1 Product Discovery

### Status

Working and verified.

### Safety boundary

Read-only against Linnworks and OpenCart products.

### Endpoint

```text
POST /api/Stock/GetStockItemsFull
```

The implementation:

- Uses the regional server from authentication.
- Uses the temporary authenticated token.
- Requests pages of up to 200 records.
- Supports stock-level data requirements.
- Uses a maximum-page guard.
- Stores a local cache.
- Uses `StockItemId` as the unique Linnworks item key.
- Uses a payload hash to identify changed data.
- Preserves the previous cache when a run fails.
- Records each discovery run.

### Discovery tables

```text
{DB_PREFIX}linnworks_discovery_run
{DB_PREFIX}linnworks_discovery_item
```

### Discovery item fields

```text
discovery_item_id
discovery_run_id
stock_item_id
stock_item_int_id
sku
item_title
barcode
purchase_price
retail_price
quantity
available_quantity
is_composite_parent
is_variation_parent
linnworks_last_update
payload_hash
date_discovered
date_modified
```

### Discovery-run fields

```text
discovery_run_id
started_at
completed_at
status
pages_processed
items_received
items_inserted
items_updated
error_count
error_message
```

## 15. Product Discovery test evidence

The admin page showed:

```text
Cached: 602
Last run: completed
Received: 602
```

Discovery History showed:

```text
Run #1
Status: completed
Pages: 4
Received: 602
Inserted: 602
Updated: 0

Run #2
Status: completed
Pages: 4
Received: 602
Inserted: 0
Updated: 0

Run #3
Status: completed
Pages: 4
Received: 602
Inserted: 0
Updated: 32
```

This proves the tested environment completed initial caching, an unchanged repeat run and a later changed-item update run.

## 16. v0.2.7-dev controller defect and repair

### Original defect

Direct page actions accessed:

```php
$this->model_extension_linnworks_module_linnworks
```

before the controller loaded the model, causing:

```text
Could not call registry key model_extension_linnworks_module_linnworks
```

The discovery AJAX action still completed because its JSON helper loaded the model.

### Fixed in v0.2.7-dev.1

The controller now explicitly loads the model at the start of:

```text
scanResults()
scanHistory()
discoveredProducts()
discoveryHistory()
exportScan()
exportDiscovery()
```

### Verified result

- View Products opens.
- Discovery History opens.
- Discovery History displays completed runs.
- Existing cached discovery data remains available.

## 17. Current verified feature status

### Working

- Extension installation and registration
- Correct module name
- Administrator permissions
- Settings persistence
- Stock Location persistence
- API credentials and endpoint configuration
- Test Connection
- Regional authentication
- Discover Locations
- Diagnostics
- Catalogue Scanner
- Scan Results
- Scan History
- Scanner CSV export
- Read-only Linnworks Product Discovery
- Product cache
- Repeat discovery
- Changed-item update detection
- View Products
- Discovery History
- Discovery CSV export controller path

### Not yet implemented or not approved for live use

- Automatic product mapping
- Mapping approval workflow
- OpenCart product updates
- Linnworks product creation or update
- Inventory synchronisation
- Price synchronisation
- Order export
- Dispatch import
- Tracking import
- SAP writeback
- Automated scheduling

## 18. Current release state

```text
Protected recovery baseline:
version_0_2_5_test

Stable scanner checkpoint:
v0.2.6-dev.1

Active development branch:
v0.2.7-dev

Latest working release:
v0.2.7-dev.1

Current status:
Working development checkpoint
```

## 19. Failed or untrusted builds

Do not use reconstructed historical packages as a development baseline.

Previously observed failure symptoms included:

```text
linnworks_heading_title
You do not have permission to access this page
```

If either symptom returns:

1. Stop adding patches.
2. Compare packaging, namespaces, language files, routes and permissions with the protected baseline.
3. Do not attempt to repair registration by layering guesses onto a rebuilt extension.

## 20. Known issues and watch items

### No confirmed blocking defect in v0.2.7-dev.1

The latest controller contains the explicit model loads required by directly routed pages.

### Watch item: discovery field availability

Linnworks response fields can differ by endpoint response shape or configured data requirements. Do not invent missing values. Store unavailable fields as empty or `NULL` and confirm the response before changing field mappings.

### Watch item: current-cache semantics

The discovery item table represents the latest cached state keyed by `StockItemId`. Discovery-run history records the run counters, but the current implementation is not a historical row-per-item snapshot for every run.

### Watch item: removals

A missing item in a later discovery run must not automatically be treated as deleted. A complete successful-run reconciliation design is required before introducing inactive or removed status.

## 21. Next planned release: v0.2.8-dev

### Scope

Product Mapping Engine only.

### Proposed capabilities

- Compare OpenCart scanner products with cached Linnworks items.
- Exact EAN matching.
- Normalised-model fallback.
- Match confidence.
- Conflict detection.
- Manual review queue.
- Explicit mapping approval.
- Mapping history or audit evidence.
- Search and filtering.
- CSV export of mapping results.

### Out of scope

```text
Inventory writes
Price writes
Product writes
Order export
Dispatch import
Tracking import
SAP writeback
Scheduled automation
```

### Acceptance boundary

No operational synchronisation should rely on a mapping until a person explicitly approves it.

## 22. Proposed v0.2.8-dev data model

Use the existing mapping table only after inspecting its current schema and confirming whether it can support:

```text
OpenCart product ID
Linnworks StockItemId
OpenCart EAN
Linnworks barcode
OpenCart model
Linnworks SKU
match method
confidence
status
conflict reason
approved by
approved date
created date
modified date
```

Do not alter or replace the table without an idempotent migration plan.

Suggested mapping statuses:

```text
suggested
conflict
manual_review
approved
rejected
stale
```

## 23. v0.2.8-dev matching requirements

### Exact EAN

- Trim whitespace.
- Compare string values exactly.
- Do not cast EAN values to integers.
- Preserve leading zeroes.
- Flag duplicate EAN values as conflicts.

### Normalised model fallback

Normalisation should be explicit, testable and conservative. Candidate operations may include:

- Uppercase conversion
- Trimming
- Removing spaces
- Removing selected separators

Do not apply unverified prefix removal or aggressive transformations without test evidence.

### Manual review

Manual review must show both sides of the proposed match and why the match was suggested.

## 24. Testing checklist for the current release

Before starting the mapping engine, reconfirm:

```text
[ ] Extension displays as HPC Linnworks Integration
[ ] Existing settings remain populated
[ ] Stock Location remains selected after save and reload
[ ] Test Connection succeeds
[ ] Discover Locations succeeds
[ ] Diagnostics succeeds
[ ] Catalogue Scanner completes
[ ] Scan Results opens
[ ] Scan History opens
[ ] Scanner CSV exports
[ ] Discover Linnworks Products completes
[ ] Cached item count displays
[ ] View Products opens
[ ] Discovery History opens
[ ] Discovery CSV exports
[ ] Repeated discovery creates no duplicate StockItemId records
[ ] No OpenCart product data changes
[ ] No Linnworks product data changes
```

## 25. Release process

1. Start from the current verified source.
2. Create or use the release branch.
3. Apply only the agreed change set.
4. Inspect all modified files.
5. Validate PHP syntax.
6. Validate Twig routes and variables.
7. Validate `install.json`.
8. Confirm the package root structure.
9. Build the ZIP as `linnworks.ocmod.zip`.
10. Test installation in OpenCart.
11. Run the regression checklist.
12. Commit the tested files.
13. Create the version tag.
14. Publish release notes and test evidence.
15. Update this Continuity Pack.

## 26. Security and operational safeguards

- Do not include credentials in Git.
- Do not include credentials in the Continuity Pack.
- Do not return authentication tokens in normal UI success responses.
- Redact secrets in logs.
- Retain TLS certificate verification.
- Keep API timeouts bounded.
- Keep pagination bounded.
- Preserve the last valid cache after a failed discovery run.
- Do not drop production tables automatically.
- Do not silently change the selected stock location.
- Do not enable live writes without an explicit release decision and rollback plan.

## 27. New-chat starter prompt

```text
Use the attached OpenCart-to-Linnworks Continuity Pack as the project
source of truth.

Current project state:

Protected baseline:
version_0_2_5_test

Active branch:
v0.2.7-dev

Latest verified working release:
v0.2.7-dev.1

Completed:
- API authentication and location discovery
- Catalogue Scanner
- Scan Results and Scan History
- Scanner CSV export
- Stock Location persistence
- Read-only Linnworks Product Discovery
- 602-item discovery cache in the tested environment
- View Products
- Discovery History
- Discovery controller model-loading repair

Critical rules:
1. Patch the real repository source incrementally.
2. Do not reconstruct the extension from memory.
3. Preserve packaging, language, permissions, routes and namespaces.
4. Keep the installer filename exactly linnworks.ocmod.zip.
5. Read OpenCart EAN from product_code.code/value.
6. Never query product.ean.
7. Inspect and migrate existing schemas before querying new columns.
8. Preserve working API settings, scanner and discovery behaviour.
9. Keep all OpenCart, Linnworks and SAP writes disabled unless explicitly approved.
10. The next planned release is v0.2.8-dev, limited to the Product Mapping Engine.
```

## 28. Final reminder

The next development task is not inventory synchronisation, pricing, order export or SAP integration.

The next bounded task is:

```text
v0.2.8-dev
Product Mapping Engine
```

Build it on top of `v0.2.7-dev.1`, preserve every verified feature, and keep the release read-only until mapping suggestions and approvals are proven reliable.
