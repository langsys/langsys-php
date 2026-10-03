<?php

namespace Langsys\SDK\Sync;

use Langsys\SDK\Html\Canonical;
use Langsys\SDK\Messages\MessageCatalog;
use Langsys\SDK\Messages\ValueSets;
use Langsys\SDK\Migration\LegacyKeys;
use Langsys\SDK\Migration\LegacyValue;

/**
 * Decides what a sync registers (FRM-2): every phrase an app's translate calls
 * and its base language's files hold.
 *
 * A call's phrase is what the same call registers at runtime: a key the
 * base-language files hold is that key's line, converted by its file's
 * format, under its group; any other literal is source text, uncategorised,
 * converted by the call's own rules - only the placeholders it passes are
 * placeholders, and `trans_choice` reads `|` as a plural. A call whose
 * replacements are not a literal array passes what it names at runtime, so a
 * `compact()` names its keys and anything else passes every placeholder the
 * text holds. A call whose argument is not a literal is reported, unless its
 * key is built inside a literal group whose lines register anyway. A line
 * still holding a label placeholder registers only through the validation
 * listing, and a call inside an app message's template method through the
 * messages listing. With declared value sets (FRM-7), a phrase naming one registers
 * once per value, written in.
 *
 * Given a catalog, each phrase is decided against it - already there, new
 * with the translations the other languages' files hold, or new alone.
 * Without one - offline, in a CI gate with no API key - every phrase is new,
 * and the plan still reports what a strict run would fail on.
 */
final class Planner
{
    /** The catalog's key for a phrase with no category. */
    const UNCATEGORIZED = '__uncategorized__';

    /** @var LegacyKeys|null */
    private $source;

    /** @var ValueSets|null */
    private $valueSets;

    /**
     * @param LegacyKeys|null $source The base language's files
     * @param ValueSets|null $valueSets
     */
    public function __construct(LegacyKeys $source = null, ValueSets $valueSets = null)
    {
        $this->source = $source;
        $this->valueSets = $valueSets;
    }

    /**
     * A plan with no catalog and no other languages: every phrase new, every
     * call that cannot be collected reported. Needs no client and no key.
     *
     * @param array[] $hits SourceScanner hits
     * @param array|null $migration The base language's files, in the `migration` option's shape
     * @param array $valueSets Value-set declarations, as the `value_sets` option takes them
     * @param array $options As plan() takes them
     * @return SyncPlan
     */
    public static function offline(array $hits, array $migration = null, array $valueSets = [], array $options = [])
    {
        $planner = new self($migration === null ? null : new LegacyKeys($migration), $valueSets === [] ? null : new ValueSets($valueSets));

        return $planner->plan($hits, [], null, $options);
    }

    /**
     * @param array[] $hits SourceScanner hits
     * @param array<string, LegacyKeys> $readers locale => that locale's files
     * @param array|null $catalog category => entries, or null when there is none to decide against
     * @param array $options `covered_groups`: groups whose base-language lines
     *                       register another way, so a key built at runtime in
     *                       one is covered (Laravel's validation lines);
     *                       `message_classes`: the app-message classes the
     *                       binding found, by fully-qualified name - one that
     *                       inherits the contract names it nowhere a scan
     *                       can see; `listed_templates`: the templates the
     *                       messages listing lists (MessageCatalog::templates()),
     *                       whose sentences a call anywhere is covered by
     * @return SyncPlan
     */
    public function plan(array $hits, array $readers = [], array $catalog = null, array $options = [])
    {
        $coveredGroups = isset($options['covered_groups']) && is_array($options['covered_groups']) ? array_map('strval', $options['covered_groups']) : [];
        $messageClasses = isset($options['message_classes']) && is_array($options['message_classes'])
            ? array_map(function ($class) {
                return ltrim((string) $class, '\\');
            }, $options['message_classes'])
            : [];
        $listed = [];
        foreach (isset($options['listed_templates']) && is_array($options['listed_templates']) ? $options['listed_templates'] : [] as $template) {
            $listed[Canonical::phrase((string) $template)] = true;
        }
        $items = [];
        $reported = [];
        $skipped = [];
        $viaValidation = [];
        $covered = [];
        $viaMessageListing = [];

        $add = function ($phrase, $category, array $translations, $origin) use (&$items, &$viaValidation) {
            $phrase = Canonical::phrase($phrase);
            if ($phrase === '') {
                return;
            }

            // A line still holding a label placeholder registers only through
            // the validation listing, once per field (FRM-2, MSG-3).
            foreach (MessageCatalog::LABEL_PLACEHOLDERS as $placeholder) {
                $name = substr($placeholder, 1);
                if (preg_match('/(?<![\w:])' . preg_quote($placeholder, '/') . '(?![\w])|\{' . $name . '\}/', $phrase)) {
                    $viaValidation[] = ['phrase' => $phrase, 'placeholder' => $placeholder, 'origin' => $origin];

                    return;
                }
            }

            $id = json_encode([$category, $phrase]);
            if (!isset($items[$id])) {
                $items[$id] = ['phrase' => $phrase, 'category' => $category, 'status' => 'new', 'translations' => [], 'origins' => []];
            }
            $items[$id]['translations'] += $translations;
            $items[$id]['origins'][] = $origin;
        };

        foreach ($hits as $hit) {
            $origin = $hit['file'] . ':' . $hit['line'];

            // A call inside an app message's template method is that
            // message's sentence, which its listing registers under the
            // messages category (MSG-7).
            if (isset($hit['method']) && $hit['method'] === 'template'
                && (in_array('HasAppMessageTemplate', isset($hit['implements']) ? $hit['implements'] : [], true)
                    || (isset($hit['class_fqcn']) && in_array($hit['class_fqcn'], $messageClasses, true)))) {
                $viaMessageListing[] = ['file' => $hit['file'], 'line' => $hit['line'], 'entry_point' => $hit['entry_point'], 'class' => $hit['class'], 'phrase' => $hit['text']];
                continue;
            }

            if ($hit['text'] === null) {
                // A key built at runtime inside a literal group is covered by
                // that group's base-language lines, which register anyway.
                if (isset($hit['group']) && $hit['group'] !== null
                    && (in_array($hit['group'], $coveredGroups, true) || $this->groupRegisters($hit['group']))) {
                    $covered[] = ['file' => $hit['file'], 'line' => $hit['line'], 'entry_point' => $hit['entry_point'], 'group' => $hit['group']];
                    continue;
                }

                $reported[] = ['file' => $hit['file'], 'line' => $hit['line'], 'entry_point' => $hit['entry_point']];
                continue;
            }

            // A key registers as a lookup of it does, whichever function
            // called it; only a literal no file holds follows the call's rules.
            $entry = $this->source === null ? null : $this->source->resolve($hit['text']);

            if ($entry !== null) {
                $phrase = $entry['phrase'];
                $category = $entry['category'];
                $translations = self::lineTranslations($hit['text'], $readers, $skipped);
            } else {
                $replace = array_fill_keys(self::passedKeys($hit), '');
                $phrase = LegacyValue::fromCall($hit['text'], $replace, $hit['kind'], $hit['kind'] === 'trans_choice' ? 1 : null)['text'];
                $category = null;
                $translations = self::lineTranslations($hit['text'], $readers, $skipped, $hit['kind'], $replace);
            }

            // A sentence the messages listing lists is covered by it,
            // wherever the call sits, and never registers uncategorised
            // beside it (FRM-2).
            if (isset($listed[Canonical::phrase($phrase)])) {
                $viaMessageListing[] = ['file' => $hit['file'], 'line' => $hit['line'], 'entry_point' => $hit['entry_point'], 'class' => isset($hit['class']) ? $hit['class'] : null, 'phrase' => Canonical::phrase($phrase)];
                continue;
            }

            $sentences = $this->valueSets === null ? [$phrase] : $this->valueSets->sentences($phrase);

            if ($sentences === [$phrase]) {
                $add($phrase, $category, $translations, $origin);
                continue;
            }

            foreach ($sentences as $sentence) {
                $add($sentence, $category, [], $origin);
            }
        }

        // A key a call already used merges into the same item: one phrase.
        foreach ($this->source === null ? [] : $this->source->keys() as $key) {
            $entry = $this->source->resolve($key);
            if ($entry !== null) {
                $add($entry['phrase'], $entry['category'], self::lineTranslations($key, $readers, $skipped), $key);
            }
        }

        foreach ($items as $id => $item) {
            $category = ($item['category'] === null || $item['category'] === '') ? self::UNCATEGORIZED : $item['category'];
            if ($catalog !== null && isset($catalog[$category]) && is_array($catalog[$category]) && array_key_exists($item['phrase'], $catalog[$category])) {
                $items[$id]['status'] = 'in_catalog';
            } elseif ($item['translations'] !== []) {
                $items[$id]['status'] = 'with_translations';
            }
        }

        return new SyncPlan(array_values($items), $reported, $skipped, $viaValidation, $covered, $viaMessageListing);
    }

    /**
     * The replacement keys a call passes: a literal array's keys, a
     * `compact()`'s names, or - for any other replacements argument, whose
     * keys only the running call knows - every placeholder the text holds,
     * which is what such a call passes in practice.
     *
     * @param array $hit
     * @return string[]
     */
    private static function passedKeys(array $hit)
    {
        if (is_array($hit['replace_keys'])) {
            return $hit['replace_keys'];
        }

        $replaceIndex = $hit['kind'] === 'trans_choice' ? 2 : 1;
        if (empty($hit['replace_dynamic']) || $hit['arg_count'] <= $replaceIndex) {
            return [];
        }

        preg_match_all(MessageCatalog::PLACEHOLDER_PATTERN, $hit['text'], $found);

        return array_values(array_unique(array_map(function ($placeholder) {
            return substr($placeholder, 1);
        }, $found[0])));
    }

    /**
     * Whether the base-language files hold lines of a group, which a sync
     * registers whatever key a call builds in it.
     *
     * @param string $group
     * @return bool
     */
    private function groupRegisters($group)
    {
        if ($this->source === null) {
            return false;
        }

        foreach ($this->source->keys() as $key) {
            if (strpos($key, $group . '.') === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Each target locale's translation of a key, converted as its phrase was;
     * what is missing, empty or does not convert is listed instead.
     *
     * @param string $key
     * @param array<string, LegacyKeys> $readers
     * @param array $skipped
     * @param string|null $kind The call's rules, or null for a file line
     * @param array $replace
     * @return array<string, string>
     */
    public static function lineTranslations($key, array $readers, array &$skipped, $kind = null, array $replace = [])
    {
        $translations = [];

        foreach ($readers as $locale => $reader) {
            $raw = $reader->raw($key);
            $converted = $raw === null ? null : self::linePhrase($raw['value'], $raw['format'], $kind, $replace);
            $reason = $converted === null ? 'missing'
                : (Canonical::phrase($converted['text']) === '' ? 'empty'
                : (!$converted['recognised'] ? 'not_converted' : null));

            if ($reason !== null) {
                $skipped[] = ['key' => $key, 'locale' => $locale, 'reason' => $reason];
                continue;
            }

            $translations[$locale] = Canonical::phrase($converted['text']);
        }

        return $translations;
    }

    /**
     * A language-file value as a phrase: by the call's rules where a Laravel
     * call reads a Laravel file, else by the file's format.
     *
     * @param string|array $value
     * @param string $format
     * @param string|null $kind
     * @param array $replace
     * @return array{text: string, recognised: bool}
     */
    private static function linePhrase($value, $format, $kind, array $replace)
    {
        if (is_array($value)) {
            return LegacyValue::fromPluralForms($value);
        }

        if ($kind !== null && $format === 'laravel') {
            return LegacyValue::fromCall($value, $replace, $kind, $kind === 'trans_choice' ? 1 : null);
        }

        return LegacyValue::convert($value, $format);
    }
}
