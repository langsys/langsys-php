# Server messages (MSG) in the PHP core

**Spec:** langsys2 `70320628`, `docs/sdk-spec.mdx` blob `f8ff6e1e46668120cb6ceb662414574275bb4a1c`
(8.1.0, MSG-1..MSG-12, additive: the 79 existing rules are unchanged, measured by diffing every
rule's title, Profiles line and body against `5c5c0723`). Unreviewed at the time of writing.
**Reference:** langsys4 `refactor/907_error_codes_and_failure_modes` — `ErrorTemplate`, `ApiError`,
`RuleWording`, `FieldError`, `ErrorTemplateCatalogService`, `errors:templates`.
**Split:** this repo is the framework-agnostic core. The Laravel binding owns the validator
normalizer and its wording table (MSG-9), labels (MSG-10), the 422 placement key, the Inertia
hand-off (MSG-12), Blade and the artisan wrapper, built on the API below. The API is agreed with the
Laravel lane directly. At its request: `forBound()` instead of framework rule names, the full
`:placeholder` check, a field on `add()`, `collect()`/`register()` beside `run()`, params never
coerced, and the no-translation fallback equal to `message` byte for byte.

## API (`Langsys\SDK\Messages`)

| Piece | What it does | Rule |
|---|---|---|
| `MessageTemplate::markers()` / `fill()` | Port of 907's `ErrorTemplate`: `/\{([a-z][a-z0-9_]*)\}/`, a missing param stays its literal marker | MSG-3, MSG-4 |
| `ServerMessage` | The entry `{field?, code, message, template, params?}`: `make()` fills `message` and drops `params` when there are no markers; `fromText()` is the `invalid` fallback; `fromArray()` rejects an entry missing a piece | MSG-1, MSG-4, MSG-9 |
| `MessageSet` | Resolves entries from any body — the default langsys envelope, foreign container keys, a configured `key`, or an app `resolver` — and from an SDK exception | MSG-1 |
| `MessageCodes` | The shared vocabulary, slug check, and the size-code split by type: `forBound(lower\|upper, type)`, with the reference's `min`/`max`/`gt` as `forSize()` | MSG-2 |
| `MessageCatalog`, `MessageSource`, `ErrorClassSource` | Collects templates with their source and field, and reports what can't be listed (bad braces, any unfilled framework placeholder, a marker with nothing to fill it, label-like markers) | MSG-3, MSG-7, MSG-11 |
| `MessageCatalogCommand` + `bin/langsys-messages` | `collect()` and `register()` (registered and skipped counts) for a framework console; `run()` lists templates, exits 1 naming each problem as source, field, issue and fix, and with `--register` registers only what the catalog lacks | MSG-7 |
| `Client::translateMessage()` | `translate(template, locale, messages category, params)`; the entry's `message` when there is no translation; never `message` as a key | MSG-5, MSG-6 |
| `Client::emitMessage()` | Queues a template the catalog lacks on the existing flush path; nothing sent during the request; the write gate applies | MSG-8 |
| `messages_category` config (`Errors`) | One category for registration, runtime registration and rendering | MSG-6 |
| Exceptions: `getErrorCode()`, `getServerMessages()` | The SDK's own API errors expose their entries; fixes the TypeError when `error` is an object | MSG-1 |

## Rows

MSG-1, 2, 4, 6 are core behaviour. MSG-3, 7, 8, 11 are shared between the core's checks and each
app's declarations. MSG-5 is profile `browser, binding` (the core ships the helper bindings delegate
to). MSG-9, 10 and 12 need a validator, a label facility or a redirect, which a framework-agnostic
core does not have. Every grade is decided on evidence at the end, not here.

## Tests

Red first. The shared `server-message-vectors.json` is not authored yet (the TS core owns it), so
tests use the spec's examples and 907's wire examples (`docs/plans/907_server_message_translation_sdk_plan.md`
§3.1, `tests/Unit/ApiErrorTest.php`), and the rows say the fixture is pending. Mutation batteries run
in a git worktree, never in this tree, which the Laravel binding loads through a symlink.
