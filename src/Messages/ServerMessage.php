<?php

namespace Langsys\SDK\Messages;

/**
 * One server message entry (MSG-1): `template`, the framework's source sentence
 * before its values are filled, and `params`, the values for its markers - the
 * only pieces translation needs. `message` is the template already filled, the
 * fallback a client shows when it cannot look the template up; an entry with no
 * template is shown as its message and never looked up. `code` and `field` are
 * what the framework itself reports about the failure, carried unchanged: its
 * own identifier, or none, and its field path in its own format - a dotted
 * string, or a list such as Pydantic's `loc`.
 */
final class ServerMessage implements \JsonSerializable
{
    /** The piece names on the wire, overridable per app (MSG-1). */
    const PIECES = ['template' => 'template', 'params' => 'params', 'message' => 'message', 'code' => 'code', 'field' => 'field'];

    /** @var string|array|null */
    private $field;

    /** @var string|int|null */
    private $code;

    /** @var string */
    private $message;

    /** @var string|null */
    private $template;

    /** @var array */
    private $params;

    /**
     * @param string|int|null $code The framework's own identifier, or null
     * @param string|null $message The filled template; filled from params when null
     * @param string|null $template Null for a message that has none
     * @param array $params
     * @param string|array|null $field The framework's field path, as it reports it
     */
    public function __construct($code, $message, $template, array $params = [], $field = null)
    {
        $this->code = ($code === null || $code === '') ? null : $code;
        $this->template = $template === null ? null : (string) $template;
        $this->params = $params;
        $this->message = (($message === null || $message === '') && $this->template !== null)
            ? MessageTemplate::fill($this->template, $params)
            : (string) $message;
        $this->field = ($field === null || $field === '' || $field === []) ? null : (is_array($field) ? $field : (string) $field);
    }

    /**
     * Build an entry from a template and its params, as a server emits it.
     *
     * `message` is the template filled from the params (MSG-4). Params keep only
     * the template's markers, in the order they appear, and are omitted entirely
     * when the template has none. A marker with no param stays out of `params`
     * and stays literal in `message`.
     *
     * @param string|int|null $code The framework's own identifier, or null
     * @param string $template
     * @param array $params
     * @param string|null $field
     * @return self
     */
    public static function make($code, $template, array $params = [], $field = null)
    {
        $kept = [];

        foreach (MessageTemplate::markers($template) as $marker) {
            if (array_key_exists($marker, $params)) {
                $kept[$marker] = $params[$marker];
            }
        }

        return new self($code, MessageTemplate::fill($template, $kept), $template, $kept, $field);
    }

    /**
     * The entry for a failure that arrived as finished text only - a package
     * throwing its own sentence (MSG-9): the text is its template, with no
     * params, and the catalog command reports it so the app can give it an
     * unfilled message.
     *
     * @param string $text
     * @param string|null $field
     * @param string|int|null $code The framework's own identifier, if it has one
     * @return self
     */
    public static function fromText($text, $field = null, $code = null)
    {
        return new self($code, $text, $text, [], $field);
    }

    /**
     * Read an entry from its wire form, or null when it is not one.
     *
     * An entry needs a `template` or a `message` as text. `message` defaults to
     * the template filled from `params`; an entry with only a message is shown
     * as that text and never looked up, since using `message` as a key is the
     * one thing a client must never do (MSG-5). `code` and `field` are kept as
     * the framework reported them.
     *
     * @param array $data
     * @param array $pieces Piece names, overriding PIECES
     * @param bool $requireTemplate Whether a message alone is not enough - for
     *                              finding entries by shape in a framework's body
     * @return self|null
     */
    public static function fromArray(array $data, array $pieces = [], $requireTemplate = false)
    {
        $names = array_merge(self::PIECES, $pieces);

        $template = isset($data[$names['template']]) && is_string($data[$names['template']]) ? $data[$names['template']] : null;
        $message = isset($data[$names['message']]) && is_string($data[$names['message']]) ? $data[$names['message']] : null;

        if ($template === null && ($message === null || $requireTemplate)) {
            return null;
        }

        $code = isset($data[$names['code']]) && (is_string($data[$names['code']]) || is_int($data[$names['code']])) ? $data[$names['code']] : null;
        $field = isset($data[$names['field']]) && (is_string($data[$names['field']]) || is_array($data[$names['field']])) ? $data[$names['field']] : null;

        $params = isset($data[$names['params']]) && is_array($data[$names['params']]) ? $data[$names['params']] : [];

        return new self($code, $message, $template, $params, $field);
    }

    /**
     * @return string|array|null
     */
    public function getField()
    {
        return $this->field;
    }

    /**
     * The framework's own identifier for the failure, or null when it has none.
     *
     * @return string|int|null
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @return string|null Null for an entry that carries only its message
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * The wire form: `field` and `code` only when the framework reported them,
     * `params` only when the template has markers.
     *
     * @return array
     */
    public function toArray()
    {
        $entry = [];

        if ($this->field !== null) {
            $entry['field'] = $this->field;
        }

        if ($this->code !== null) {
            $entry['code'] = $this->code;
        }

        $entry['message'] = $this->message;

        if ($this->template !== null) {
            $entry['template'] = $this->template;
        }

        if ($this->params !== []) {
            $entry['params'] = $this->params;
        }

        return $entry;
    }

    /**
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->toArray();
    }
}
