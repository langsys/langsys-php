<?php

namespace Langsys\SDK\Messages;

/**
 * Every template an app can send, listed from its code, and every message that
 * cannot be listed (MSG-7).
 *
 * A template is checked as it is added, because the checks are what make a
 * listed template translatable at all: a brace that is not a marker, a label
 * placeholder the framework never filled, or a label carried in a marker would
 * each reach a translator as a broken sentence (MSG-3, MSG-11). Such a template
 * is reported and not listed - registering it would put the defect into every
 * language.
 */
final class MessageCatalog
{
    /**
     * A framework placeholder left in the text (`:attribute`, `:min`, `:input`...).
     * Each is text the framework was meant to replace, so one that survives
     * reaches a translator, and then a reader, as a literal. The lookbehind keeps
     * a time (`10:30`), a URL (`https://`) and `word:word` out of it.
     */
    const PLACEHOLDER_PATTERN = '/(?<![\w:]):[a-z][a-z_]*/';

    /**
     * Marker names that can only hold a translatable label. A heuristic: a
     * marker's value cannot be proven non-translatable from its name, so this
     * catches the names a label reaches a marker under, not every translatable
     * value an author could put in one.
     */
    const LABEL_MARKERS = ['attribute', 'field', 'label', 'other', 'values'];

    /** @var array<string, string> template => first source */
    private $templates = [];

    /** @var string[] */
    private $problems = [];

    /**
     * List a template, or report why it cannot be listed.
     *
     * @param string $template
     * @param string $source The file, class or declaration it came from
     * @param string|null $field The field it belongs to, when there is one
     * @return bool Whether it was listed
     */
    public function add($template, $source, $field = null)
    {
        $source = (string) $source;
        $where = $source . (($field === null || $field === '') ? '' : '.' . $field);

        if (!is_string($template) || trim($template) === '') {
            $this->problem($source, 'has an empty template', 'give it a whole sentence in the source language', $field);

            return false;
        }

        $listable = true;

        preg_match_all(self::PLACEHOLDER_PATTERN, $template, $placeholders);

        foreach (array_unique($placeholders[0]) as $placeholder) {
            $this->problem($source, "leaves the placeholder $placeholder unfilled in \"$template\"", "write the field's label or the value's marker into the sentence", $field);
            $listable = false;
        }

        $withoutMarkers = preg_replace(MessageTemplate::MARKER_PATTERN, '', $template);

        if (strpos($withoutMarkers, '{') !== false || strpos($withoutMarkers, '}') !== false) {
            $this->problem($source, "has a brace that is not a {name} marker in \"$template\"", 'markers are lowercase snake_case names in braces, like {min}; write anything else as text', $field);
            $listable = false;
        }

        foreach (array_intersect(MessageTemplate::markers($template), self::LABEL_MARKERS) as $marker) {
            $this->problem($source, "puts a translatable label in the marker {{$marker}} in \"$template\"", 'write the label into the sentence and list one template per label', $field);
            $listable = false;
        }

        if ($listable && !array_key_exists($template, $this->templates)) {
            $this->templates[$template] = $where;
        }

        return $listable;
    }

    /**
     * Report a message that cannot be listed: where it is, what is wrong, and the
     * fix - one line an agent or a person can act on.
     *
     * @param string $source
     * @param string $issue
     * @param string $fix
     * @param string|null $field
     * @return void
     */
    public function problem($source, $issue, $fix, $field = null)
    {
        $where = (string) $source . (($field === null || $field === '') ? '' : '.' . $field);
        $line = $where . ': ' . $issue . ' — ' . $fix;

        if (!in_array($line, $this->problems, true)) {
            $this->problems[] = $line;
        }
    }

    /**
     * The listed templates, sorted, each with the first source that declared it.
     *
     * @return array<int, array{template: string, source: string}>
     */
    public function templates()
    {
        $templates = $this->templates;
        ksort($templates, SORT_STRING);

        $out = [];

        foreach ($templates as $template => $source) {
            $out[] = ['template' => (string) $template, 'source' => $source];
        }

        return $out;
    }

    /**
     * @return string[]
     */
    public function problems()
    {
        return $this->problems;
    }

    /**
     * @return bool
     */
    public function hasProblems()
    {
        return $this->problems !== [];
    }
}
