<?php

namespace Langsys\SDK\Sync;

/**
 * What a sync would register (FRM-2), decided against the catalog: each
 * phrase the app's source calls and its base language's files hold, with its
 * status -
 *
 * - `in_catalog`: nothing to do;
 * - `with_translations`: not in the catalog, but the framework's language
 *   files translate it; registered with those translations (MIG-9);
 * - `new`: in neither; registered alone -
 *
 * and the calls that could not be collected, because their argument is not a
 * literal, with their file and line. A dry run is this plan; Client::applySync()
 * carries it out.
 */
final class SyncPlan
{
    /**
     * @var array<int, array{phrase: string, category: string|null, status: string, translations: array<string, string>, origins: string[]}>
     */
    public $items;

    /**
     * @var array<int, array{file: string, line: int, entry_point: string}>
     */
    public $reported;

    /**
     * Lines that still hold a label placeholder (`:attribute`, `:other`,
     * `:values`): they register only through the validation listing, once
     * per field with its label written in, never on their own. Not a failure.
     *
     * @var array<int, array{phrase: string, placeholder: string, origin: string}>
     */
    public $viaValidation = [];

    /**
     * Calls whose key is built at runtime inside a literal group the
     * base-language files hold (`__("validation.$key")`): covered by that
     * group's lines, which register anyway. Not a failure.
     *
     * @var array<int, array{file: string, line: int, entry_point: string, group: string}>
     */
    public $covered = [];

    /**
     * Calls inside an app message's template method (MSG-7): its sentence,
     * registered by the messages listing under the messages category, never
     * as an uncategorised literal. Not a failure.
     *
     * @var array<int, array{file: string, line: int, entry_point: string, class: string|null}>
     */
    public $viaMessageListing = [];

    /**
     * Translations that could not be taken from a language file.
     *
     * @var array<int, array{key: string, locale: string, reason: string}>
     */
    public $skipped;

    /**
     * @param array $items
     * @param array $reported
     * @param array $skipped
     */
    public function __construct(array $items, array $reported, array $skipped, array $viaValidation = [], array $covered = [], array $viaMessageListing = [])
    {
        $this->viaMessageListing = $viaMessageListing;
        $this->covered = $covered;
        $this->items = $items;
        $this->reported = $reported;
        $this->skipped = $skipped;
        $this->viaValidation = $viaValidation;
    }

    /**
     * The items a sync registers.
     *
     * @return array[]
     */
    public function toRegister()
    {
        return array_values(array_filter($this->items, function ($item) {
            return $item['status'] !== 'in_catalog';
        }));
    }

    /**
     * How many items have each status.
     *
     * @return array{in_catalog: int, with_translations: int, new: int}
     */
    public function counts()
    {
        $counts = ['in_catalog' => 0, 'with_translations' => 0, 'new' => 0];
        foreach ($this->items as $item) {
            $counts[$item['status']]++;
        }

        return $counts;
    }

    /**
     * Whether a strict run fails: any call was reported (FRM-2's --strict).
     *
     * @return bool
     */
    public function failsStrict()
    {
        return $this->reported !== [];
    }
}
