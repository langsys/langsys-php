# Interpolation and plurals

## `{name}`

```php
$client->translate('Hello, {name}!', null, 'UI', null, ['name' => $user->name]);
$client->translateContentBlock('<p>Welcome back, {name}</p>', 'Home', ['name' => $user->name]);
$client->translatePage($html, 'Home', [], ['name' => $user->name]);
```

The phrase registers with its placeholders, `Hello, {name}!`, once. `{name}` is the form every Langsys SDK stores, so a catalog renders the same from a PHP backend and a JavaScript frontend. A block fills placeholders in its text and in its translatable attributes; a page also fills them in its `<title>` and translated `<meta>` tags.

`%name%` fills a key you pass. In markup (pages, blocks and `translateRich()`) it registers as `{name}`. `translate()` and `resolve()` register a phrase as written, so `translate('Hello %name%')` is a different catalog entry from the `{name}` form a page or a JavaScript frontend registers: write `{name}` there. Only an identifier between the signs counts, so `Save 20% on 5% APR` is left alone. A framework's own syntax (`:name`, `{{name}}`) is not `translate()`'s: convert it with the migration tools ([migration.md](./migration.md)).

## How each value renders

| Value | Renders |
|---|---|
| A key you don't pass, or pass as `null` | Left as written, `{name}`, so missing data stays visible |
| `{ name }` | Whitespace inside the braces is ignored |
| A string | As given: the way to opt out of number formatting, for ids, codes and versions |
| An int or float | Formatted for the locale: `1234.5` is `1.234,5` in `de-de` |
| A bool | `true` or `false` |
| An enum | A backed enum by its value, a plain enum by its name |
| A `DateTimeInterface` | The locale's medium date style |

An untranslated phrase still interpolates on `translate()`, `resolve()`, `translateRich()` and `translateContentBlock()`, so a reader never sees a raw `{name}` for a value you passed. **`translatePage()` doesn't:** a page it can't translate, because no catalog or no locale can be read (a client without credentials, or the API unreachable with nothing cached), comes back exactly as given, `{name}` included. On a page, mark printed values instead ([value-markers.md](./value-markers.md)): a marked value shows its value either way. Interpolation changes only what your code receives, never what registers.

## Plurals and gender: write the sentence flat

**Never write ICU.** Write the sentence as it reads, with the count or the name as a `{placeholder}`, and pass the value as a param:

```php
$client->translate('You have {count} new messages', null, 'Inbox', null, ['count' => $count]);
```

Langsys makes the plural and gender forms each language needs, the base language included: English renders `You have 1 new message`, Russian picks among its four forms and Arabic among its six. Every request asks the API for those forms as ICU (`X-Langsys-Capabilities: icu`), and `translate()` renders the one the count selects. Your code passes the same params in every language.

- **Keep a count and the noun it inflects in one phrase**, or no plural form can reach the noun: on a page, keep them together with `data-langsys-phrase` ([primitives.md](./primitives.md)).
- **A sentence that says something different at zero is its own phrase**, chosen in code:

```php
$text = $count === 0
    ? $client->translate('Ready to import the rest of the plan.', null, 'Import')
    : $client->translate('Ready to import {count} new events.', null, 'Import', null, ['count' => $count]);
```

**Gender.** In a language with grammatical gender, Langsys can turn `{name}` into `{name_gender, select, …}` in the translation. Pass `name_gender` beside `name` when you know it (`female`, `male`); without it the neutral form renders:

```php
$client->translate('{name} replied', null, 'Inbox', null, ['name' => $user->name, 'name_gender' => 'female']);
```

## Rendering what the catalog sends

A translation in ICU syntax renders with the locale's CLDR rules.

- `#` in a plural branch is the number; in a `select` branch it is text.
- A `{d}` holding a date renders as a date in a phrase with ICU in it too.
- A missing `{v, number}`, `{d, date}` or `{t, time}` value shows as `{v}`, `{d}` or `{t}`.
- Malformed ICU renders by simple substitution instead of throwing.

ICU needs the `intl` extension, which Composer requires. If `intl` is disabled at runtime, the SDK warns once, naming the phrase, and renders without it: a `select` still picks its branch, a plural picks an exact `=N` branch, else `one` at 1, else `other`; a number is written as given and a date as `Y-m-d`.

## Declared value sets

A sentence that names a status or a kind reads best with the word written in, so a translator sees the whole sentence and can inflect around it. Declare the values a placeholder takes, and the sentence registers once per value:

```php
use Langsys\SDK\Messages\TranslatesAs;

#[TranslatesAs('status')]
enum OrderStatus: string
{
    case Shipped = 'shipped';

    public function label(): string { return 'Shipped'; }   // the word readers see
}

$client = new Client($key, $project, ['value_sets' => [OrderStatus::class]]);
$client->translate('The order is {status}.', null, 'Orders', null, ['status' => OrderStatus::Shipped]);
// looks up "The order is Shipped."
```

A backed enum's word is its `label()`, else its `value`, so define `label()` when the value is a slug. Any class can declare instead by implementing `Langsys\SDK\Messages\TranslatableValues`, whose static `translatableValues()` returns `['status' => ['Shipped', 'Delivered']]`; that works on PHP 7.4, where attributes and enums don't exist.

`planSync()` registers every sentence with every value. At runtime, a value added since the last sync registers its sentence after the response, `runtime_registration` off or not. A value no declaration names for its placeholder never registers.
