<?php

namespace Langsys\SDK\Messages;

/**
 * One server message entry: `{ field?, code, message, template, params? }` (MSG-1).
 *
 * The key names are fixed across every SDK; the envelope around entries is the
 * app's own. `code` is the slug an app branches on, `template` the source
 * sentence a client translates, `params` the values for its markers, `message`
 * the template already filled, and `field` a dotted path for a field failure.
 */
final class ServerMessage implements \JsonSerializable
{
    /**
     * The code for a failure that arrived with text but no rule (MSG-9).
     */
    const INVALID = 'invalid';

    /** @var string|null */
    private $field;

    /** @var string */
    private $code;

    /** @var string */
    private $message;

    /** @var string */
    private $template;

    /** @var array */
    private $params;

    /**
     * @param string $code
     * @param string $message
     * @param string $template
     * @param array $params
     * @param string|null $field
     */
    public function __construct($code, $message, $template, array $params = [], $field = null)
    {
        $this->code = (string) $code;
        $this->message = (string) $message;
        $this->template = (string) $template;
        $this->params = $params;
        $this->field = ($field === null || $field === '') ? null : (string) $field;
    }

    /**
     * Build an entry from a template and its params, as a server emits it.
     *
     * `message` is the template filled from the params (MSG-4). Params keep only
     * the template's markers, in the order they appear, and are omitted entirely
     * when the template has none. A marker with no param stays out of `params`
     * and stays literal in `message`.
     *
     * @param string $code
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
     * The entry for a failure that arrived with text only - a package throwing
     * its own message (MSG-9). Its text becomes the template, under `invalid`,
     * so it still renders and the catalog command can report it for a real one.
     *
     * @param string $text
     * @param string|null $field
     * @return self
     */
    public static function fromText($text, $field = null)
    {
        return new self(self::INVALID, $text, $text, [], $field);
    }

    /**
     * Read an entry from its wire form, or null when it is not one.
     *
     * An entry needs `code`, `message` and `template` as text. Anything missing
     * one of them is not rendered from: without `template` there is nothing to
     * look up, and falling back to `message` as a key is the one thing a client
     * must never do (MSG-5).
     *
     * @param array $data
     * @return self|null
     */
    public static function fromArray(array $data)
    {
        foreach (['code', 'message', 'template'] as $piece) {
            if (!isset($data[$piece]) || !is_string($data[$piece])) {
                return null;
            }
        }

        $params = isset($data['params']) && is_array($data['params']) ? $data['params'] : [];
        $field = isset($data['field']) && is_string($data['field']) ? $data['field'] : null;

        return new self($data['code'], $data['message'], $data['template'], $params, $field);
    }

    /**
     * @return string|null
     */
    public function getField()
    {
        return $this->field;
    }

    /**
     * @return string
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
     * @return string
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
     * The wire form, in the reference's key order.
     *
     * @return array
     */
    public function toArray()
    {
        $entry = [];

        if ($this->field !== null) {
            $entry['field'] = $this->field;
        }

        $entry['code'] = $this->code;
        $entry['message'] = $this->message;
        $entry['template'] = $this->template;

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
