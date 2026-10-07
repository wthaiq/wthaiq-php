<?php

declare(strict_types=1);

namespace Wthaiq\Resource;

use Wthaiq\HttpClient\HttpResponse;

/**
 * An immutable API object.
 *
 * Fields are read as properties (`$request->status`) or array keys (`$request['status']`).
 * Nested JSON objects are themselves `WthaiqObject` instances, JSON arrays stay PHP lists,
 * and unknown fields added by future API versions remain readable. Reading a field that is
 * absent returns null; writing any field throws.
 *
 * @property-read string|null $object The API object type, e.g. `signature_request`
 *
 * @implements \ArrayAccess<array-key, mixed>
 */
class WthaiqObject implements \ArrayAccess, \JsonSerializable
{
    /** The API `object` value this class represents (null for generic objects). */
    public const OBJECT_NAME = null;

    /**
     * @param array<array-key, mixed> $values Already converted values
     */
    final public function __construct(private array $values = [], private ?HttpResponse $lastResponse = null)
    {
    }

    /**
     * Re-types another object as this class, keeping its values and response metadata.
     */
    final public static function fromObject(self $source): static
    {
        return new static($source->values, $source->lastResponse);
    }

    /**
     * Builds an object from plain PHP data (nested arrays become objects or lists).
     *
     * @param array<array-key, mixed> $values
     */
    final public static function constructFrom(array $values, ?HttpResponse $lastResponse = null): static
    {
        $converted = ObjectFactory::convert(ObjectFactory::arrayToStd($values));

        return $converted instanceof self ? static::fromObject($converted)->withResponse($lastResponse) : new static([], $lastResponse);
    }

    /**
     * @internal
     */
    final public function withResponse(?HttpResponse $response): static
    {
        $copy = clone $this;
        $copy->lastResponse = $response;

        return $copy;
    }

    public function __get(string $name): mixed
    {
        return $this->values[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        return isset($this->values[$name]);
    }

    public function __set(string $name, mixed $value): void
    {
        throw new \LogicException(sprintf('Wthaiq objects are read-only; cannot set "%s". Send changes through the matching service method.', $name));
    }

    public function __unset(string $name): void
    {
        throw new \LogicException(sprintf('Wthaiq objects are read-only; cannot unset "%s".', $name));
    }

    public function offsetExists(mixed $offset): bool
    {
        return \array_key_exists($offset, $this->values);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->values[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('Wthaiq objects are read-only.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('Wthaiq objects are read-only.');
    }

    /** True when the field is present, even if its value is null. */
    public function has(string $key): bool
    {
        return \array_key_exists($key, $this->values);
    }

    /**
     * @return list<array-key>
     */
    public function keys(): array
    {
        return array_keys($this->values);
    }

    /**
     * The object as plain PHP arrays (recursively).
     *
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        return self::plain($this->values);
    }

    public function toJson(int $flags = JSON_PRETTY_PRINT): string
    {
        return json_encode($this, $flags | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public function jsonSerialize(): mixed
    {
        return $this->values === [] ? new \stdClass() : $this->values;
    }

    /**
     * The HTTP response that produced this object (status, headers, request id), when it came
     * directly from an API call.
     */
    public function getLastResponse(): ?HttpResponse
    {
        return $this->lastResponse;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function __debugInfo(): array
    {
        return $this->toArray();
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return array<array-key, mixed>
     */
    private static function plain(array $values): array
    {
        $out = [];
        foreach ($values as $k => $v) {
            if ($v instanceof self) {
                $out[$k] = $v->toArray();
            } elseif (\is_array($v)) {
                $out[$k] = self::plain($v);
            } else {
                $out[$k] = $v;
            }
        }

        return $out;
    }
}
