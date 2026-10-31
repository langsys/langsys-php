<?php

namespace Langsys\SDK\Messages;

use Langsys\SDK\Exception\LangsysException;

/**
 * The entries a response carries, resolved from wherever they sit (MSG-1).
 *
 * The body is the framework's: Laravel's 422 with the entries attached beside
 * its own `errors` map, a JSON:API document, a house style, or the Langsys
 * API's own error body. With no configuration, every array in the body that
 * carries a template is an entry - found by shape, so a framework's own
 * `message` is never mistaken for one. `key` names where the entries sit, and
 * there an entry with only a message counts too; `pieces` renames an entry's
 * pieces, and `resolver` maps an app's native failures to entries when they
 * carry no entries at all.
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
     * Entries are found wherever they sit in the body - beside a framework's own
     * errors, under any container key - so resolution never depends on the
     * shape around them (MSG-1). `key` narrows it to one place.
     *
     * @param array|string|object|null $body Decoded body, or its JSON
     * @param array $options `key` (dotted path), `resolver` (callable taking the
     *                       body), `pieces` (piece names, as ServerMessage::PIECES)
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

        $pieces = isset($options['pieces']) && is_array($options['pieces']) ? $options['pieces'] : [];

        if (isset($options['resolver']) && is_callable($options['resolver'])) {
            return self::fromIterable(call_user_func($options['resolver'], $body), $pieces);
        }

        $keyed = isset($options['key']) && is_string($options['key']) && $options['key'] !== '';

        if ($keyed) {
            $body = self::dig($body, $options['key']);

            if (!is_array($body)) {
                return new self();
            }
        }

        $found = [];
        self::walk($body, $found, 0, $pieces, !$keyed);

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
    private static function fromIterable($items, array $pieces = [])
    {
        if (!is_array($items) && !($items instanceof \Traversable)) {
            return new self();
        }

        $entries = [];

        foreach ($items as $item) {
            if ($item instanceof ServerMessage) {
                $entries[] = $item;
            } elseif (is_array($item) && ($entry = ServerMessage::fromArray($item, $pieces)) !== null) {
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
    private static function walk(array $node, array &$found, $depth, array $pieces = [], $requireTemplate = true)
    {
        if ($depth > self::MAX_DEPTH) {
            return;
        }

        $entry = ServerMessage::fromArray($node, $pieces, $requireTemplate);
        $paramsKey = isset($pieces['params']) ? $pieces['params'] : ServerMessage::PIECES['params'];

        if ($entry !== null) {
            $found[] = $entry;
        }

        foreach ($node as $key => $child) {
            // An entry's params are its marker values, never more entries.
            if ($entry !== null && $key === $paramsKey) {
                continue;
            }

            if (is_array($child)) {
                self::walk($child, $found, $depth + 1, $pieces, $requireTemplate);
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
