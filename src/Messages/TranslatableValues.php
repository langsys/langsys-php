<?php

namespace Langsys\SDK\Messages;

/**
 * A finite set of translatable values an app shows in its sentences - a
 * status, a category - declared for the placeholders it fills (FRM-7).
 *
 * A sentence naming one of these values is registered whole, with the value
 * written in as its display word (`The order is Shipped.`), so the translator
 * sees the sentence it has to make agree. The sync command registers every
 * sentence with every value; at runtime a value from the set looks up its
 * written-in sentence. A placeholder no declaration names stays an ordinary
 * placeholder.
 *
 * Implement it on any class - a model reading its rows, a config-backed list.
 * A backed enum needs no implementation: mark it with TranslatesAs.
 */
interface TranslatableValues
{
    /**
     * The values, as the display words a reader sees in the source language,
     * by the placeholder each set fills. Read each time it is needed, so a row
     * added since the last sync counts.
     *
     * @return array<string, string[]> placeholder => display words
     */
    public static function translatableValues();
}
