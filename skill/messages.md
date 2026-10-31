# Server messages

Validation errors and other messages a server sends are translated like any phrase, without changing how your framework reports them. Each message travels as an **entry**:

| Piece | What it is |
|---|---|
| `template` | Your framework's own sentence before its values are filled: the key |
| `params` | Those values |
| `message` | The filled sentence, shown until a translation exists |
| `code`, `field` | Your framework's rule name or error id, and its field path, carried unchanged or absent |

A translator sees one whole sentence, so gender and number come out right.

## Build entries

```php
use Langsys\SDK\Messages\ServerMessage;

$entry = ServerMessage::make('min', 'The password field must be at least {min} characters.', ['min' => 12], 'password');
$entry->getMessage();   // "The password field must be at least 12 characters."
$entry->toArray();      // field, code, message, template, params
```

Write the field's label into the template where it names the field: Laravel's `The :attribute field is required.` becomes `The password field is required.`. A `{name}` marker is only for a value that isn't translatable, such as a number, a date or what the user typed. Attach the entries to your framework's own error response under a key you choose. The response otherwise stays exactly as your framework writes it.

When your server sends an entry, pass it through `$client->emitMessage($entry)`: a template the catalog lacks is registered after the response.

## Render entries

```php
use Langsys\SDK\Messages\MessageSet;

$messages = MessageSet::fromResponse($responseBody);   // finds entries wherever they sit
// or name where they are: MessageSet::fromResponse($responseBody, ['key' => 'translatable', 'pieces' => ['template' => 'msgid']]);

foreach ($messages->forField('password') as $entry) {
    echo htmlspecialchars($client->translateMessage($entry));   // translated, or the server's message until it is
}
```

`translateMessage()` looks the template up under the messages category (`Errors`, or the `messages_category` option) and never uses the filled message as a key. `general()` gives the entries with no field, and `MessageSet::fromException($e)` reads them from a `ValidationException` the SDK threw.

## Register every template ahead of time

A message doesn't exist until something fails, so no page shows it for discovery. List them from your code:

```php
// langsys-messages.php
use Langsys\SDK\Client;
use Langsys\SDK\Messages\ErrorClassSource;

return [
    'sources' => [new ErrorClassSource([App\Errors\QuotaExceeded::class])],
    'client' => function () { return new Client(); },
];
```

```bash
vendor/bin/langsys-messages             # counts the templates, and reports each one it can't list
vendor/bin/langsys-messages -v          # lists each template and where it comes from
vendor/bin/langsys-messages --register  # registers the ones the catalog doesn't have yet
vendor/bin/langsys-messages --strict    # exits 1 when any message can't be listed
```

`--config=path` names another file. A message the command can't list still registers the first time your server sends it, so it is reported, not failed. Add `--strict` in CI if no error should ever show untranslated. A template that still holds a Laravel label placeholder (`:attribute`, `:other`, `:values`) is refused: write the label in.

**An error class** declares a `MESSAGE` constant, a public property for each marker, and optionally a `CODE`, passed through as the entry's code.

**A message your app defines** declares its sentence and code with `HasAppMessageTemplate`:

```php
use Langsys\SDK\Messages\HasAppMessageTemplate;

final class QuotaExceeded implements HasAppMessageTemplate
{
    public $limit;
    public function __construct(int $limit) { $this->limit = $limit; }

    public function template() { return 'You have used all {limit} of this month\'s requests.'; }
    public function code() { return 'quota_exceeded'; }
}

$entry = ServerMessage::fromApp(new QuotaExceeded(500));   // what you send at runtime
```

Each `{name}` is filled from the public property of the same name. Listing a class builds it without its constructor, so `template()` must return the sentence itself, not build it from constructor arguments.

**A validation rule object** hands the validator a finished message. Let it state its sentence instead with `HasMessageTemplate`, whose `template()` returns `'The :attribute may not have more than {max} words.'`. `RuleTemplate::forField($rule, $label)` gives the template with the label written in and its params for the runtime entry, and `MessageCatalog::addRule()` lists it once per field. A rule without the interface is listed from its filled message and reported, which fails `--strict`.

A source of your own implements `Langsys\SDK\Messages\MessageSource`, whose `collect(MessageCatalog $catalog)` calls `add()`, `addMessage()` or `addRule()`. It reports what it can't list with `problem()`, and advice that never fails the command, such as a field with no declared label, with `advise()`.
