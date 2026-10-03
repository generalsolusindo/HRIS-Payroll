<?php

namespace App\Services;

final readonly class FaceDescriptorResult
{
    /**
     * @param  array<int, float>|null  $descriptor
     */
    private function __construct(
        public bool $ok,
        public ?array $descriptor,
        public ?string $error,
    ) {}

    /**
     * @param  array<int, float>  $descriptor
     */
    public static function success(array $descriptor): self
    {
        return new self(true, $descriptor, null);
    }

    /**
     * @param  'no_face'|'multiple_faces'|'invalid_input'|'load_failed'|'process_failed'|'invalid_output'  $error
     */
    public static function failure(string $error): self
    {
        return new self(false, null, $error);
    }
}
