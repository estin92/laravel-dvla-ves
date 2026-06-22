<?php

namespace Estin92\DvlaVes\Data;

/**
 * Normalises the two error envelopes the DVLA VES API returns — the JSON:API
 * `{"errors":[{code,title,detail}]}` shape and the flat `{"message":...}` shape
 * (used for 403s) — into one so every exception reads its reason from one place.
 */
class VesError
{
    /**
     * @param  array<int, array<string, mixed>>|null  $errors  The verbatim `errors` array, when present.
     */
    public function __construct(
        public readonly int $statusCode,
        public readonly ?string $code,
        public readonly ?string $title,
        public readonly ?string $detail,
        public readonly ?array $errors,
    ) {}

    /**
     * @param  array<string, mixed>|null  $body
     */
    public static function fromResponse(int $statusCode, ?array $body): self
    {
        $body ??= [];

        $errors = is_array($body['errors'] ?? null) ? $body['errors'] : null;
        $first = is_array($errors[0] ?? null) ? $errors[0] : [];

        $code = self::stringOrNull($first['code'] ?? null);
        $title = self::stringOrNull($first['title'] ?? null);
        $detail = self::stringOrNull($first['detail'] ?? null) ?? self::stringOrNull($body['message'] ?? null);

        return new self($statusCode, $code, $title, $detail, $errors);
    }

    public function reason(): string
    {
        return $this->detail ?? $this->title ?? 'An unknown error occurred';
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
