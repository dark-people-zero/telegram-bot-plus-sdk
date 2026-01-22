<?php

namespace DarkPeople\TelegramBot\Support;

/**
 * Base class for cache-safe array serialization.
 *
 * This serializer encodes only public, non-static properties into an array,
 * with support for nested {@see BaseArraySerializable} objects and arrays.
 *
 * Objects of this type are encoded with a class tag to allow safe hydration:
 * [
 *   '@class' => SomeSerializable::class,
 *   '@value' => [ ...serialized public props... ],
 * ]
 *
 * Excluded properties (e.g. volatile runtime references like parent pointers)
 * can be defined via {@see exclude()} and will not be persisted.
 */
abstract class BaseArraySerializable
{
    /**
     * Override to exclude specific public properties from serialization.
     *
     * Typical use cases:
     * - volatile runtime references (e.g. parent pointers)
     * - derived/cache-only properties that must be rebuilt after hydrate
     *
     * @return array<int, string> List of public property names to exclude.
     */
    protected static function exclude(): array { return []; }

    /**
     * Serialize public, non-static properties into an array payload.
     *
     * @return array<string, mixed>
     */
    final public function serialize(): array
    {
        $exclude = array_flip(static::exclude());
        $ref = new \ReflectionObject($this);

        $out = [];
        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            if ($prop->isStatic()) continue;

            $name = $prop->getName();
            if (isset($exclude[$name])) continue;

            $value = $prop->getValue($this);
            $out[$name] = $this->encode($value);
        }

        return $out;
    }

    /**
     * Hook executed after deserialization completes.
     *
     * Use this to rebuild volatile references or normalize derived structures
     * (e.g. relink parent pointers in a tree).
     *
     * @return void
     */
    protected function afterDeserialize(): void
    {
        // no-op
    }

    /**
     * Hydrate an instance from a serialized array payload.
     *
     * Only public, non-static properties are restored.
     * Excluded properties are skipped and should be rebuilt in {@see afterDeserialize()}.
     *
     * @param array<string, mixed> $data
     * @return static
     */
    final public static function deserialize(array $data): static
    {
        $ref = new \ReflectionClass(static::class);
        /** @var static $obj */
        $obj = $ref->newInstanceWithoutConstructor();

        $exclude = array_flip(static::exclude());

        foreach ($data as $key => $value) {
            if (isset($exclude[$key])) continue;
            if (!$ref->hasProperty($key)) continue;

            $prop = $ref->getProperty($key);
            if (!$prop->isPublic() || $prop->isStatic()) continue;

            $prop->setValue($obj, static::decode($value));
        }

        $obj->afterDeserialize();
        return $obj;
    }

    /**
     * Encode a value for serialization.
     *
     * Supported:
     * - null/scalars: returned as-is
     * - arrays: recursively encoded
     * - BaseArraySerializable: tagged object payload
     *
     * @param mixed $value
     * @return mixed
     */
    private function encode(mixed $value): mixed
    {
        if ($value === null || is_scalar($value)) return $value;

        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) $out[$k] = $this->encode($v);
            return $out;
        }

        if ($value instanceof BaseArraySerializable) {
            return [
                '@class' => $value::class,
                '@value' => $value->serialize(),
            ];
        }

        // fallback (kalau ada object lain) - bisa throw kalau mau strict
        return $value;
    }
    
    /**
     * Decode a serialized value.
     *
     * Recognizes tagged object payloads and hydrates subclasses of
     * {@see BaseArraySerializable}. Arrays are decoded recursively.
     *
     * @param mixed $value
     * @return mixed
     */
    private static function decode(mixed $value): mixed
    {
        if ($value === null || is_scalar($value)) return $value;

        if (is_array($value)) {
            // object tagged?
            if (isset($value['@class'], $value['@value']) && is_string($value['@class']) && is_array($value['@value'])) {
                $cls = $value['@class'];
                if (is_subclass_of($cls, BaseArraySerializable::class)) {
                    /** @var class-string<BaseArraySerializable> $cls */
                    return $cls::deserialize($value['@value']);
                }
                // unknown class tag -> return raw
                return $value;
            }

            // normal array -> recurse
            $out = [];
            foreach ($value as $k => $v) $out[$k] = static::decode($v);
            return $out;
        }

        return $value;
    }
}
