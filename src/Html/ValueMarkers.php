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
 *
 * A sentinel carries the random nonce of the reader that issued it, and is
 * read as one only while that reader is alive. Text that merely has a
 * sentinel's shape - a caller's param, a catalog translation - stays text: it
 * never becomes a marker, and a plural or select chooses on it as written.
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

    /**
     * A sentinel: the delimiters around its reader's nonce and its base64
     * JSON. Matched as bytes, so text holding invalid UTF-8 still splits.
     */
    const SENTINEL_PATTERN = '/\xEE\x80\x90([0-9a-f]{32})([A-Za-z0-9+\/=]*)\xEE\x80\x91/';

    /** Subtrees whose text is not prose, or holds no comments (RCDATA). */
    const SKIPPED = ['script', 'style', 'template', 'noscript', 'math', 'title', 'textarea', 'head'];

    /**
     * The nonces of the readers alive in this process.
     *
     * @var array<string, true>
     */
    private static $live = [];

    /** @var string The random nonce this reader's sentinels carry. */
    private $nonce;

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

    public function __construct()
    {
        $this->nonce = bin2hex(random_bytes(16));
        self::$live[$this->nonce] = true;
    }

    public function __destruct()
    {
        unset(self::$live[$this->nonce]);
    }

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
        $wrapper = Document::fragmentRoot($doc);
        if ($wrapper === null) {
            return $html;
        }

        self::read($wrapper);

        $out = '';
        foreach ($wrapper->childNodes as $child) {
            $out .= Document::html($child);
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
    public function unnamedWithin(?DOMNode $scope = null)
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
                $params[$name] = $this->sentinel($name, $entry);
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
                    $params[$name] = $this->sentinel($name, $entry);
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

            if (preg_match('/\{\s*' . preg_quote((string) $name, '/') . '\s*,/', (string) $text) && preg_match(self::SENTINEL_PATTERN, $value, $m)) {
                $decoded = self::issued($m[1], $m[2]);
                if ($decoded !== null) {
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

            $node->nodeValue = $this->sealed($run);
        }

        self::expand($root);
    }

    /**
     * A deep clone of $node in which every run read below it holds its
     * values as sentinels again, so the copy renders the markers it was read
     * from: the form an excluded element is kept in, whose content is
     * restored as given rather than rendered (TOK-1).
     *
     * @param DOMNode $node
     * @return DOMNode
     */
    public function sealedClone(DOMNode $node)
    {
        $clone = $node->cloneNode(true);
        $this->sealInto($node, $clone);

        return $clone;
    }

    /**
     * @param DOMNode $from
     * @param DOMNode $to Its deep clone
     * @return void
     */
    private function sealInto(DOMNode $from, DOMNode $to)
    {
        if ($from instanceof DOMText) {
            foreach ($this->runs as $run) {
                if ($run['node'] === $from) {
                    $to->nodeValue = $this->sealed($run);
                }
            }

            return;
        }

        if ($from->childNodes === null) {
            return;
        }

        foreach ($from->childNodes as $i => $child) {
            $this->sealInto($child, $to->childNodes->item($i));
        }
    }

    /**
     * A run's text with each of its placeholders as the sentinel for its value.
     *
     * @param array $run
     * @return string
     */
    private function sealed(array $run)
    {
        $values = $run['values'];

        return (string) preg_replace_callback('/\{([a-z][a-z0-9_]*)\}/', function ($m) use ($values) {
            return isset($values[$m[1]]) ? $this->sentinel($m[1], $values[$m[1]]) : $m[0];
        }, $run['node']->textContent);
    }

    /**
     * The text nodes at or below $node holding a sentinel, in document order.
     *
     * @param DOMNode $node
     * @param DOMText[] $found
     * @return void
     */
    private static function sentinelTexts(DOMNode $node, array &$found)
    {
        if ($node instanceof DOMText) {
            if (strpos($node->nodeValue, self::OPEN) !== false) {
                $found[] = $node;
            }

            return;
        }
        foreach ($node->childNodes === null ? [] : iterator_to_array($node->childNodes) as $child) {
            self::sentinelTexts($child, $found);
        }
    }

    /**
     * Turn every sentinel a live reader issued in the text below $root into
     * its marker. Anything else shaped like one is left as text.
     *
     * @param DOMNode $root
     * @return void
     */
    public static function expand(DOMNode $root)
    {
        $doc = $root instanceof \DOMDocument ? $root : $root->ownerDocument;
        $found = [];
        self::sentinelTexts($root, $found);

        foreach ($found as $node) {
            $parent = $node->parentNode;
            $parts = preg_split(self::SENTINEL_PATTERN, $node->nodeValue, -1, PREG_SPLIT_DELIM_CAPTURE);
            $text = '';

            for ($i = 0; $i < count($parts); $i += 3) {
                $text .= $parts[$i];
                if (!isset($parts[$i + 2])) {
                    break;
                }

                $decoded = self::issued($parts[$i + 1], $parts[$i + 2]);
                if ($decoded === null) {
                    $text .= self::OPEN . $parts[$i + 1] . $parts[$i + 2] . self::CLOSE;
                    continue;
                }
                list($name, $value, $form) = $decoded;

                if ($text !== '') {
                    $parent->insertBefore($doc->createTextNode($text), $node);
                    $text = '';
                }

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

            if ($text !== '') {
                $parent->insertBefore($doc->createTextNode($text), $node);
            }
            $parent->removeChild($node);
        }
    }

    /**
     * $html with every sentinel a live reader issued written as the marker it
     * came from, its value escaped: how a line rendered as a string rather
     * than as nodes, such as a language file's, gives its values back. Inside
     * a tag, where no marker can stand, a sentinel is its escaped value.
     * Anything else shaped like one is left as it is.
     *
     * @param string $html
     * @return string
     */
    public static function expandHtml($html)
    {
        $html = (string) $html;
        if (strpos($html, self::OPEN) === false || !preg_match_all(self::SENTINEL_PATTERN, $html, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return $html;
        }

        $out = '';
        $at = 0;
        foreach ($matches as $match) {
            list($whole, $offset) = $match[0];
            $decoded = self::issued($match[1][0], $match[2][0]);
            if ($decoded === null) {
                continue;
            }
            list($name, $value, $form) = $decoded;

            $before = substr($html, 0, $offset);
            $lt = strrpos($before, '<');
            $gt = strrpos($before, '>');

            if ($lt !== false && ($gt === false || $lt > $gt)) {
                $marker = htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            } elseif (is_array($form)) {
                $marker = '<span';
                foreach ($form as $attribute => $attributeValue) {
                    $marker .= ' ' . $attribute . '="' . htmlspecialchars((string) $attributeValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
                }
                $marker .= '>' . htmlspecialchars((string) $value, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>';
            } else {
                $marker = '<!--ls:' . $name . '-->' . htmlspecialchars((string) $value, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8') . '<!--/ls-->';
            }

            $out .= substr($html, $at, $offset - $at) . $marker;
            $at = $offset + strlen($whole);
        }

        return $out . substr($html, $at);
    }

    /**
     * $text with each sentinel's nonce left out, so the same values read by
     * two readers compare equal: a form for comparing, never for rendering.
     *
     * @param string $text
     * @return string
     */
    public static function comparable($text)
    {
        return strpos((string) $text, self::OPEN) === false ? (string) $text : (string) preg_replace(self::SENTINEL_PATTERN, self::OPEN . '$2' . self::CLOSE, (string) $text);
    }

    /**
     * The name, value and form a sentinel carries, when a reader alive in
     * this process issued it; null for anything else.
     *
     * @param string $nonce
     * @param string $payload
     * @return array{0: string, 1: string, 2: array|null}|null
     */
    private static function issued($nonce, $payload)
    {
        return isset(self::$live[$nonce]) ? json_decode((string) base64_decode($payload), true) : null;
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
        $loaded = Document::loadFragment($doc, $html);
        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        return $loaded ? $doc : null;
    }

    /**
     * @param string $name
     * @param array{value: string, form: array|null} $entry
     * @return string
     */
    private function sentinel($name, array $entry)
    {
        return self::OPEN . $this->nonce . base64_encode((string) json_encode([$name, $entry['value'], $entry['form']])) . self::CLOSE;
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
