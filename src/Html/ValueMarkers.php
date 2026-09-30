<?php

namespace Langsys\SDK\Html;

use DOMComment;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Value markers (VAR-3): how a rendered value is read back as a placeholder.
 *
 * An emitter marks a value it printed from a variable with a comment pair,
 * `<!--ls:NAME-->VALUE<!--/ls-->`, or, where it cannot write comments, with
 * `<span data-ls-param="NAME">VALUE</span>`. Reading a subtree collapses each
 * run of text and markers under one parent into a single text node holding
 * the run's text with `{NAME}` in place of each value, so every tokenizer and
 * renderer downstream sees one ordinary text node: `Hello {name}, welcome
 * back` is one token, and one phrase for every user.
 *
 * A marker is read only when it is well formed: a valid name (VAR-2's
 * `[a-z][a-z0-9_]*`, never a markup token `m<N>o`/`m<N>c`), text alone
 * between the pair, and a closing comment. Anything else - an element inside
 * the pair, an unclosed pair - is ordinary markup, and ordinary comments split
 * text exactly as they always have, so no id derived from unmarked markup
 * changes.
 *
 * Rendering puts the values back. Where a collapsed node's text is
 * interpolated, params() supplies its values: a name used only as a simple
 * `{NAME}` gets a sentinel that carries the marker, a name an ICU construct
 * uses gets its raw value so plural and select still choose. finish() then
 * restores any collapsed node nothing rewrote and expands every sentinel into
 * the marker it came from, so the output carries the same markers a later
 * reader, such as a browser SDK hydrating the page, reads again.
 */
final class ValueMarkers
{
    const NAME_PATTERN = '/^[a-z][a-z0-9_]*$/';
    const RESERVED_PATTERN = '/^m\d+[oc]$/';
    const PARAM_ATTRIBUTE = 'data-ls-param';

    /**
     * Sentinel delimiters: private-use code points no source text carries,
     * and apart from MarkupTokenizer's U+E000-U+E003, since a phrase host's
     * render carries both kinds at once.
     */
    const OPEN = "\u{E010}";
    const CLOSE = "\u{E011}";

    /** Subtrees whose text is not prose, or holds no comments (RCDATA). */
    const SKIPPED = ['script', 'style', 'template', 'noscript', 'math', 'title', 'textarea', 'head'];

    /**
     * Collapsed runs: each a text node and the markers it replaced.
     *
     * @var array<int, array{node: DOMText, text: string, values: array<string, array{value: string, form: array|null}>}>
     */
    private $runs = [];

    /**
     * Well-formed markers whose name is outside VAR-2's grammar: a value the
     * reader can tell came from a variable but cannot name (VAR-7).
     *
     * @var DOMNode[]
     */
    private $unnamed = [];

    /** @var bool Whether the unnamed-marker notice has been given this process. */
    private static $noticed = false;

    /**
     * Collapse every marker run below $root.
     *
     * @param DOMNode $root
     * @return self
     */
    public static function read(DOMNode $root)
    {
        $markers = new self();
        $markers->collapseWithin($root);

        return $markers;
    }

    /**
     * An HTML fragment with every marker read: the form a block registers,
     * placeholders in place of the values.
     *
     * @param string $html
     * @return string
     */
    public static function placeholderHtml($html)
    {
        if (!self::mayHold($html)) {
            return $html;
        }

        $doc = self::fragment($html);
        $wrapper = $doc === null ? null : $doc->getElementsByTagName('div')->item(0);
        if ($wrapper === null) {
            return $html;
        }

        self::read($wrapper);

        $out = '';
        foreach ($wrapper->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $out;
    }

    /**
     * Whether a string could hold a marker at all: the cheap test that keeps
     * unmarked content on its existing path.
     *
     * @param string $html
     * @return bool
     */
    public static function mayHold($html)
    {
        return is_string($html) && (strpos($html, '<!--ls:') !== false || strpos($html, self::PARAM_ATTRIBUTE) !== false);
    }

    /**
     * Whether a marker that cannot be named sits at or below $scope, so the
     * unit there registers nothing (VAR-7). Any scope when none is given.
     *
     * @param DOMNode|null $scope
     * @return bool
     */
    public function unnamedWithin(DOMNode $scope = null)
    {
        foreach ($this->unnamed as $node) {
            if ($scope === null || self::within($node, $scope)) {
                return true;
            }
        }

        return false;
    }

    /**
     * True the first time in this process an unnamed marker is reported, so
     * the notice is given once (VAR-7).
     *
     * @return bool
     */
    public static function firstNotice()
    {
        if (self::$noticed) {
            return false;
        }

        return self::$noticed = true;
    }

    /**
     * Whether any run was read.
     *
     * @return bool
     */
    public function any()
    {
        return $this->runs !== [];
    }

    /**
     * Every name read.
     *
     * @return string[]
     */
    public function names()
    {
        $names = [];
        foreach ($this->runs as $run) {
            foreach ($run['values'] as $name => $unused) {
                $names[$name] = true;
            }
        }

        return array_keys($names);
    }

    /**
     * Whether a unit's tokens are made only of markers - placeholders read
     * here, and whitespace - so it has no text of its own and registers
     * nothing (VAR-3).
     *
     * @param string[] $tokens
     * @return bool
     */
    public function markerOnly(array $tokens)
    {
        if ($tokens === [] || !$this->any()) {
            return false;
        }

        $names = $this->names();

        foreach ($tokens as $token) {
            $rest = preg_replace_callback('/\{([a-z][a-z0-9_]*)\}/', function ($m) use ($names) {
                return in_array($m[1], $names, true) ? '' : $m[0];
            }, $token);

            if (Canonical::phrase((string) $rest) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * The params a render of $text inside $scope interpolates with: the values
     * of every run at or below $scope, under $callerParams, which win for any
     * name the caller sets itself.
     *
     * @param DOMNode $scope A collapsed text node, or an element holding some
     * @param string $text The text about to be interpolated
     * @param array $callerParams
     * @return array
     */
    public function params(DOMNode $scope, $text, array $callerParams)
    {
        $params = [];

        foreach ($this->runs as $run) {
            if (!self::within($run['node'], $scope)) {
                continue;
            }

            foreach ($run['values'] as $name => $entry) {
                $params[$name] = self::sentinel($name, $entry);
            }
        }

        return self::forText(array_merge($params, $callerParams), $text);
    }

    /**
     * The values of every run read, each as its sentinel, for a render that
     * has no node to scope them to: a fragment interpolated as one phrase.
     * Where two runs name the same value, the first is used.
     *
     * @return array
     */
    public function sentinels()
    {
        $params = [];
        foreach ($this->runs as $run) {
            foreach ($run['values'] as $name => $entry) {
                if (!isset($params[$name])) {
                    $params[$name] = self::sentinel($name, $entry);
                }
            }
        }

        return $params;
    }

    /**
     * $params as $text should be interpolated with them: a sentinel for a
     * name an ICU construct in $text uses becomes its raw value, so plural
     * and select choose on the value itself.
     *
     * @param array $params
     * @param string $text
     * @return array
     */
    public static function forText(array $params, $text)
    {
        foreach ($params as $name => $value) {
            if (!is_string($value) || strpos($value, self::OPEN) !== 0) {
                continue;
            }

            if (preg_match('/\{\s*' . preg_quote((string) $name, '/') . '\s*,/', (string) $text)) {
                $decoded = json_decode((string) base64_decode(substr($value, strlen(self::OPEN), -strlen(self::CLOSE))), true);
                if (is_array($decoded) && isset($decoded[1])) {
                    $params[$name] = $decoded[1];
                }
            }
        }

        return $params;
    }

    /**
     * Restore every run nothing rewrote, then turn every sentinel below $root
     * back into its marker.
     *
     * @param DOMNode $root
     * @return void
     */
    public function finish(DOMNode $root)
    {
        foreach ($this->runs as $run) {
            $node = $run['node'];
            if ($node->parentNode === null || strpos($node->textContent, self::OPEN) !== false) {
                continue;
            }

            $values = $run['values'];
            $node->nodeValue = preg_replace_callback('/\{([a-z][a-z0-9_]*)\}/', function ($m) use ($values) {
                return isset($values[$m[1]]) ? self::sentinel($m[1], $values[$m[1]]) : $m[0];
            }, $node->textContent);
        }

        self::expand($root);
    }

    /**
     * Turn every sentinel in the text below $root into its marker.
     *
     * @param DOMNode $root
     * @return void
     */
    public static function expand(DOMNode $root)
    {
        $doc = $root instanceof \DOMDocument ? $root : $root->ownerDocument;
        $found = [];
        $collect = function (DOMNode $node) use (&$collect, &$found) {
            if ($node instanceof DOMText) {
                if (strpos($node->nodeValue, self::OPEN) !== false) {
                    $found[] = $node;
                }

                return;
            }
            foreach ($node->childNodes === null ? [] : iterator_to_array($node->childNodes) as $child) {
                $collect($child);
            }
        };
        $collect($root);

        foreach ($found as $node) {
            $parent = $node->parentNode;
            $parts = preg_split('/' . self::OPEN . '([A-Za-z0-9+\/=]*)' . self::CLOSE . '/u', $node->nodeValue, -1, PREG_SPLIT_DELIM_CAPTURE);

            foreach ($parts as $i => $part) {
                if ($i % 2 === 0) {
                    if ($part !== '') {
                        $parent->insertBefore($doc->createTextNode($part), $node);
                    }
                    continue;
                }

                $decoded = json_decode((string) base64_decode($part), true);
                if (!is_array($decoded) || count($decoded) !== 3) {
                    continue;
                }
                list($name, $value, $form) = $decoded;

                if (is_array($form)) {
                    $span = $doc->createElement('span');
                    foreach ($form as $attribute => $attributeValue) {
                        $span->setAttribute($attribute, $attributeValue);
                    }
                    $span->appendChild($doc->createTextNode($value));
                    $parent->insertBefore($span, $node);
                    continue;
                }

                $parent->insertBefore($doc->createComment('ls:' . $name), $node);
                $parent->insertBefore($doc->createTextNode($value), $node);
                $parent->insertBefore($doc->createComment('/ls'), $node);
            }

            $parent->removeChild($node);
        }
    }

    /**
     * @param string $html
     * @return \DOMDocument|null
     */
    private static function fragment($html)
    {
        $internalErrors = libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $doc->encoding = 'UTF-8';
        $loaded = $doc->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        return $loaded ? $doc : null;
    }

    /**
     * @param string $name
     * @param array{value: string, form: array|null} $entry
     * @return string
     */
    private static function sentinel($name, array $entry)
    {
        return self::OPEN . base64_encode((string) json_encode([$name, $entry['value'], $entry['form']])) . self::CLOSE;
    }

    /**
     * @param DOMNode $node
     * @param DOMNode $scope
     * @return bool
     */
    private static function within(DOMNode $node, DOMNode $scope)
    {
        for ($at = $node; $at !== null; $at = $at->parentNode) {
            if ($at === $scope) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param DOMNode $node
     * @return void
     */
    private function collapseWithin(DOMNode $node)
    {
        if ($node instanceof DOMElement && in_array(strtolower($node->nodeName), self::SKIPPED, true)) {
            return;
        }

        if (!$node->hasChildNodes()) {
            return;
        }

        $this->collapseChildren($node);

        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $this->collapseWithin($child);
            }
        }
    }

    /**
     * Merge each run of text and markers among $parent's children into one
     * text node. A run holding no marker is left exactly as it is.
     *
     * @param DOMNode $parent
     * @return void
     */
    private function collapseChildren(DOMNode $parent)
    {
        $children = iterator_to_array($parent->childNodes);
        $count = count($children);
        $i = 0;

        while ($i < $count) {
            $run = [];
            $text = '';
            $values = [];

            while ($i < $count) {
                $child = $children[$i];

                if ($child instanceof DOMText) {
                    $run[] = $child;
                    $text .= $child->nodeValue;
                    $i++;
                    continue;
                }

                $marker = $this->markerAt($children, $i);
                if ($marker === null) {
                    break;
                }

                foreach ($marker['nodes'] as $markerNode) {
                    $run[] = $markerNode;
                }
                $text .= '{' . $marker['name'] . '}';
                if (!isset($values[$marker['name']])) {
                    $values[$marker['name']] = ['value' => $marker['value'], 'form' => $marker['form']];
                }
                $i += count($marker['nodes']);
            }

            if ($values !== []) {
                $collapsed = $parent->ownerDocument->createTextNode($text);
                $parent->insertBefore($collapsed, $run[0]);
                foreach ($run as $replaced) {
                    $parent->removeChild($replaced);
                }
                $this->runs[] = ['node' => $collapsed, 'text' => $text, 'values' => $values];
            }

            // The inner loop stops only at a node that is neither text nor a
            // marker, which belongs to no run.
            if ($i < $count) {
                $i++;
            }
        }
    }

    /**
     * The well-formed marker starting at $children[$i], or null.
     *
     * @param DOMNode[] $children
     * @param int $i
     * @return array{name: string, value: string, form: array|null, nodes: DOMNode[]}|null
     */
    private function markerAt(array $children, $i)
    {
        $child = $children[$i];

        if ($child instanceof DOMElement) {
            if (strtolower($child->nodeName) !== 'span' || !$child->hasAttribute(self::PARAM_ATTRIBUTE)) {
                return null;
            }

            $value = '';
            foreach ($child->childNodes as $inner) {
                if (!$inner instanceof DOMText) {
                    return null;
                }
                $value .= $inner->nodeValue;
            }

            $name = $child->getAttribute(self::PARAM_ATTRIBUTE);
            if (!self::validName($name)) {
                $this->unnamed[] = $child;

                return null;
            }

            $form = [];
            foreach ($child->attributes as $attribute) {
                $form[$attribute->name] = $attribute->value;
            }

            return ['name' => $name, 'value' => $value, 'form' => $form, 'nodes' => [$child]];
        }

        if (!$child instanceof DOMComment || strpos($child->data, 'ls:') !== 0) {
            return null;
        }

        $name = substr($child->data, 3);
        $nodes = [$child];
        $value = '';
        for ($j = $i + 1; $j < count($children); $j++) {
            $next = $children[$j];
            $nodes[] = $next;

            if ($next instanceof DOMText) {
                $value .= $next->nodeValue;
                continue;
            }

            if ($next instanceof DOMComment && $next->data === '/ls') {
                if (!self::validName($name)) {
                    $this->unnamed[] = $child;

                    return null;
                }

                return ['name' => $name, 'value' => $value, 'form' => null, 'nodes' => $nodes];
            }

            // An element or another comment inside the pair voids it.
            return null;
        }

        return null;
    }

    /**
     * @param string $name
     * @return bool
     */
    private static function validName($name)
    {
        return (bool) preg_match(self::NAME_PATTERN, $name) && !preg_match(self::RESERVED_PATTERN, $name);
    }
}
