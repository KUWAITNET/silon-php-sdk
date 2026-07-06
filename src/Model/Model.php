<?php

declare(strict_types=1);

namespace Silon\Model;

use DateTimeImmutable;

/**
 * Base class for every response model in the SDK.
 *
 * Each concrete model declares its fields as typed public properties (with
 * defaults) and a {@see schema()} mapping wire field -> cast. Hydration only
 * touches fields present and non-null on the wire, so absent fields keep their
 * declared default (mirroring the reference SDK's nullable/defaulted fields).
 *
 * Unknown fields returned by newer server versions are preserved verbatim and
 * readable via `$model->some_new_field` (magic {@see __get()}) instead of
 * raising, so an older SDK keeps working as the API grows. The full decoded
 * payload is always available via {@see toArray()}.
 */
abstract class Model
{
    /**
     * The full decoded wire payload, including any fields not declared as
     * typed properties (forward compatibility).
     *
     * @var array<string,mixed>
     */
    public array $raw = [];

    /**
     * @param array<string,mixed> $data
     */
    public function __construct(array $data = [])
    {
        $this->raw = $data;
        foreach (static::schema() as $field => $cast) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $this->{$field} = self::castValue($data[$field], $cast);
            }
        }
    }

    /**
     * Field -> cast spec. Recognized casts: `string`, `int`, `float`, `bool`,
     * `datetime`, `mixed` (assigned verbatim), a `Model` class-string (nested
     * object), or a class-string with a `[]` suffix (list of nested objects).
     *
     * @return array<string,string>
     */
    protected static function schema(): array
    {
        return [];
    }

    /**
     * Build an instance from a decoded payload, or `null` when given `null`.
     *
     * @param array<string,mixed>|null $data
     */
    public static function from(?array $data): ?static
    {
        return $data === null ? null : new static($data);
    }

    private static function castValue(mixed $value, string $cast): mixed
    {
        switch ($cast) {
            case '':
            case 'mixed':
                return $value;
            case 'string':
                return (string) $value;
            case 'int':
                return (int) $value;
            case 'float':
                return (float) $value;
            case 'bool':
                return (bool) $value;
            case 'datetime':
                return self::parseDatetime($value);
        }

        if (str_ends_with($cast, '[]')) {
            $itemCast = substr($cast, 0, -2);
            $out = [];
            foreach ((array) $value as $item) {
                $out[] = $itemCast === 'datetime'
                    ? self::parseDatetime($item)
                    : $itemCast::from($item);
            }

            return $out;
        }

        /** @var class-string<Model> $cast */
        return $cast::from($value);
    }

    private static function parseDatetime(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Read an undeclared (forward-compat) wire field. Returns `null` when the
     * field is absent.
     */
    public function __get(string $name): mixed
    {
        return $this->raw[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        return array_key_exists($name, $this->raw);
    }

    /**
     * The full decoded wire payload.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return $this->raw;
    }
}
