<?php

namespace Langsys\SDK\Messages;

use Langsys\SDK\Exception\LangsysException;

/**
 * The entries a response carries, resolved from wherever they sit (MSG-1).
 *
 * The envelope is the app's: the default langsys shape
 * (`error` and `error.errors`), a Laravel body with entries under its own key,
 * a JSON:API document, a house style. By default every array in the body that
 * carries an entry's pieces is an entry, so none of those needs configuring.
 * `key` narrows the search to one dotted path, and `resolver` maps an app's
 * native failures to entries when they carry no entries at all.
 */
final class MessageSet implements \Countable, \IteratorAggregate, \JsonSerializable
{
    /**
     * How deep resolution walks. Error bodies are shallow; the bound only keeps a
     * pathological body from recursing without end.
     */
    const MAX_DEPTH = 16;

    /** @var ServerMessage[] */
    private $entries = [];

    /**
     * @param ServerMessage[] $entries
     */
    public function __construct(array $entries = [])
    {
        foreach ($entries as $entry) {
            if ($entry instanceof ServerMessage) {
                $this->entries[] = $entry;
            }
        }
    }

    /**
     * Resolve entries from a response body.
     *
     * @param array|string|object|null $body Decoded body, or its JSON
     * @param array $options `key` (dotted path) or `resolver` (callable taking the body)
     * @return self
     */
    public static function fromResponse($body, array $options = [])
    {
        if (is_string($body)) {
            $decoded = json_decode($body, true);
            $body = is_array($decoded) ? $decoded : null;
        } elseif (is_object($body)) {
            $decoded = json_decode((string) json_encode($body), true);
            $body = is_array($decoded) ? $decoded : null;
        }

        if (!is_array($body)) {
            return new self();
        }

        if (isset($options['resolver']) && is_callable($options['resolver'])) {
            return self::fromIterable(call_user_func($options['resolver'], $body));
        }

        if (isset($options['key']) && is_string($options['key']) && $options['key'] !== '') {
            $body = self::dig($body, $options['key']);

            if (!is_array($body)) {
                return new self();
            }
        }

        $found = [];
        self::walk($body, $found, 0);

        return new self($found);
    }

    /**
     * The entries an SDK exception's response carried.
     *
     * @param LangsysException $exception
     * @return self
     */
    public static function fromException(LangsysException $exception)
    {
        return self::fromResponse($exception->getResponseData());
    }

    /**
     * Rebuild a set from its array form - a session, a page prop.
     *
     * @param array $entries
     * @return self
     */
    public static function fromArray(array $entries)
    {
        return self::fromIterable($entries);
    }

    /**
     * @return ServerMessage[]
     */
    public function all()
    {
        return $this->entries;
    }

    /**
     * Entries that belong to no field.
     *
     * @return ServerMessage[]
     */
    public function general()
    {
        return array_values(array_filter($this->entries, function (ServerMessage $entry) {
            return $entry->getField() === null;
        }));
    }

    /**
     * Entries for one field, by its dotted path.
     *
     * @param string $field
     * @return ServerMessage[]
     */
    public function forField($field)
    {
        $field = (string) $field;

        return array_values(array_filter($this->entries, function (ServerMessage $entry) use ($field) {
            return $entry->getField() === $field;
        }));
    }

    /**
     * The fields that have entries, once each, in order.
     *
     * @return string[]
     */
    public function fields()
    {
        $fields = [];

        foreach ($this->entries as $entry) {
            if ($entry->getField() !== null && !in_array($entry->getField(), $fields, true)) {
                $fields[] = $entry->getField();
            }
        }

        return $fields;
    }

    /**
     * @return array[]
     */
    public function toArray()
    {
        return array_map(function (ServerMessage $entry) {
            return $entry->toArray();
        }, $this->entries);
    }

    /**
     * @return int
     */
    #[\ReturnTypeWillChange]
    public function count()
    {
        return count($this->entries);
    }

    /**
     * @return \ArrayIterator
     */
    #[\ReturnTypeWillChange]
    public function getIterator()
    {
        return new \ArrayIterator($this->entries);
    }

    /**
     * @return array[]
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * @param mixed $items
     * @return self
     */
    private static function fromIterable($items)
    {
        if (!is_array($items) && !($items instanceof \Traversable)) {
            return new self();
        }

        $entries = [];

        foreach ($items as $item) {
            if ($item instanceof ServerMessage) {
                $entries[] = $item;
            } elseif (is_array($item) && ($entry = ServerMessage::fromArray($item)) !== null) {
                $entries[] = $entry;
            }
        }

        return new self($entries);
    }

    /**
     * @param array $node
     * @param ServerMessage[] $found
     * @param int $depth
     * @return void
     */
    private static function walk(array $node, array &$found, $depth)
    {
        if ($depth > self::MAX_DEPTH) {
            return;
        }

        $entry = ServerMessage::fromArray($node);

        if ($entry !== null) {
            $found[] = $entry;
        }

        foreach ($node as $key => $child) {
            // An entry's params are its marker values, never more entries.
            if ($entry !== null && $key === 'params') {
                continue;
            }

            if (is_array($child)) {
                self::walk($child, $found, $depth + 1);
            }
        }
    }

    /**
     * @param array $body
     * @param string $path
     * @return mixed
     */
    private static function dig(array $body, $path)
    {
        $node = $body;

        foreach (explode('.', $path) as $segment) {
            if (!is_array($node) || !array_key_exists($segment, $node)) {
                return null;
            }

            $node = $node[$segment];
        }

        return $node;
    }
}
