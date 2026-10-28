<?php

declare(strict_types=1);

namespace App\Mcp;

use Illuminate\Validation\ValidationException;

final class McpSceneInput
{
    public const int MAX_ITEMS = 200;

    public const int MAX_POINTS = 100;

    public const int MAX_TEXT_LENGTH = 4000;

    public const float MAX_COORDINATE = 1_000_000;

    private const array SKELETON_FIELDS = [
        'id', 'type', 'x', 'y', 'w', 'h', 'width', 'height',
        'strokeColor', 'backgroundColor', 'text', 'label', 'name',
        'points', 'startBinding', 'start', 'endBinding', 'end',
        'startArrowhead', 'endArrowhead',
    ];

    private const array PATCH_FIELDS = [
        'id', 'x', 'y', 'width', 'height', 'angle',
        'strokeColor', 'backgroundColor', 'fillStyle', 'strokeWidth',
        'strokeStyle', 'roughness', 'opacity', 'locked', 'link', 'name',
        'text', 'fontSize', 'fontFamily', 'textAlign', 'verticalAlign',
        'points', 'startBinding', 'endBinding', 'startArrowhead',
        'endArrowhead', 'frameId',
    ];

    private const array TYPES = ['rectangle', 'ellipse', 'diamond', 'text', 'arrow', 'frame'];

    private const array ARROWHEADS = [
        'arrow', 'bar', 'dot', 'circle', 'circle_outline', 'triangle',
        'triangle_outline', 'diamond', 'diamond_outline', 'crowfoot_one',
        'crowfoot_many', 'crowfoot_one_or_many',
    ];

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, array<string, mixed>>
     */
    public function skeletons(array $items): array
    {
        $this->list($items, 'elements');

        return array_map(
            fn (mixed $item, int $index): array => $this->skeleton($item, "elements.{$index}"),
            $items,
            array_keys($items),
        );
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, array<string, mixed>>
     */
    public function patches(array $items): array
    {
        $this->list($items, 'elements');

        return array_map(
            fn (mixed $item, int $index): array => $this->patch($item, "elements.{$index}"),
            $items,
            array_keys($items),
        );
    }

    /** @return array<string, mixed> */
    private function skeleton(mixed $value, string $path): array
    {
        $input = $this->object($value, self::SKELETON_FIELDS, $path);
        $output = [];

        $this->copyIdentifier($input, $output, 'id', $path, false);
        $this->copyEnum($input, $output, 'type', self::TYPES, $path);
        $this->copyCoordinate($input, $output, 'x', $path);
        $this->copyCoordinate($input, $output, 'y', $path);
        foreach (['w', 'h', 'width', 'height'] as $field) {
            $this->copyNumber($input, $output, $field, 0, self::MAX_COORDINATE, $path);
        }
        foreach (['strokeColor', 'backgroundColor'] as $field) {
            $this->copyString($input, $output, $field, 64, $path);
        }
        $this->copyString($input, $output, 'text', self::MAX_TEXT_LENGTH, $path);
        $this->copyString($input, $output, 'label', 1000, $path);
        $this->copyString($input, $output, 'name', 120, $path);
        $this->copyPoints($input, $output, 'points', $path);
        foreach (['startBinding', 'start', 'endBinding', 'end'] as $field) {
            $this->copyBinding($input, $output, $field, $path);
        }
        $this->copyArrowhead($input, $output, 'startArrowhead', $path);
        $this->copyArrowhead($input, $output, 'endArrowhead', $path);

        return $output;
    }

    /** @return array<string, mixed> */
    private function patch(mixed $value, string $path): array
    {
        $input = $this->object($value, self::PATCH_FIELDS, $path);
        $output = [];

        $this->copyIdentifier($input, $output, 'id', $path, true);
        $this->copyCoordinate($input, $output, 'x', $path);
        $this->copyCoordinate($input, $output, 'y', $path);
        $this->copyNumber($input, $output, 'width', 0, self::MAX_COORDINATE, $path);
        $this->copyNumber($input, $output, 'height', 0, self::MAX_COORDINATE, $path);
        $this->copyNumber($input, $output, 'angle', -6.284, 6.284, $path);
        foreach (['strokeColor', 'backgroundColor'] as $field) {
            $this->copyString($input, $output, $field, 64, $path);
        }
        $this->copyEnum($input, $output, 'fillStyle', ['hachure', 'cross-hatch', 'solid', 'zigzag'], $path);
        $this->copyNumber($input, $output, 'strokeWidth', 1, 10, $path);
        $this->copyEnum($input, $output, 'strokeStyle', ['solid', 'dashed', 'dotted'], $path);
        $this->copyNumber($input, $output, 'roughness', 0, 2, $path);
        $this->copyNumber($input, $output, 'opacity', 0, 100, $path);
        $this->copyBoolean($input, $output, 'locked', $path);
        $this->copyNullableString($input, $output, 'link', 2048, $path);
        $this->copyString($input, $output, 'name', 120, $path);
        $this->copyString($input, $output, 'text', self::MAX_TEXT_LENGTH, $path);
        if (array_key_exists('text', $output)) {
            $output['originalText'] = $output['text'];
        }
        $this->copyNumber($input, $output, 'fontSize', 1, 200, $path);
        $this->copyInteger($input, $output, 'fontFamily', 1, 100, $path);
        $this->copyEnum($input, $output, 'textAlign', ['left', 'center', 'right'], $path);
        $this->copyEnum($input, $output, 'verticalAlign', ['top', 'middle', 'bottom'], $path);
        $this->copyPoints($input, $output, 'points', $path);
        $this->copyBinding($input, $output, 'startBinding', $path);
        $this->copyBinding($input, $output, 'endBinding', $path);
        $this->copyArrowhead($input, $output, 'startArrowhead', $path);
        $this->copyArrowhead($input, $output, 'endArrowhead', $path);
        $this->copyNullableIdentifier($input, $output, 'frameId', $path);

        if (count($output) === 1) {
            $this->fail($path, 'Each update must include at least one updateable field.');
        }

        return $output;
    }

    /** @param array<int, mixed> $value */
    private function list(array $value, string $path): void
    {
        if (! array_is_list($value) || count($value) > self::MAX_ITEMS) {
            $this->fail($path, 'Must be a list containing at most '.self::MAX_ITEMS.' items.');
        }
    }

    /**
     * @param  list<string>  $allowed
     * @return array<string, mixed>
     */
    private function object(mixed $value, array $allowed, string $path): array
    {
        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            $this->fail($path, 'Must be an object.');
        }

        foreach (array_keys($value) as $key) {
            if (! is_string($key) || ! in_array($key, $allowed, true)) {
                $this->fail($path, 'Contains an unsupported field.');
            }
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $output
     */
    private function copyIdentifier(array $input, array &$output, string $field, string $path, bool $required): void
    {
        if (! array_key_exists($field, $input)) {
            if ($required) {
                $this->fail("{$path}.{$field}", 'Is required.');
            }

            return;
        }

        $value = $input[$field];
        if (! is_string($value) || $value === '' || strlen($value) > 255 || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            $this->fail("{$path}.{$field}", 'Must be a bounded identifier.');
        }
        $output[$field] = $value;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $output
     */
    private function copyNullableIdentifier(array $input, array &$output, string $field, string $path): void
    {
        if (! array_key_exists($field, $input)) {
            return;
        }
        if ($input[$field] === null) {
            $output[$field] = null;

            return;
        }
        $this->copyIdentifier($input, $output, $field, $path, true);
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $output
     */
    private function copyCoordinate(array $input, array &$output, string $field, string $path): void
    {
        $this->copyNumber($input, $output, $field, -self::MAX_COORDINATE, self::MAX_COORDINATE, $path);
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $output
     */
    private function copyNumber(array $input, array &$output, string $field, float $min, float $max, string $path): void
    {
        if (! array_key_exists($field, $input)) {
            return;
        }
        $value = $input[$field];
        if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value) || $value < $min || $value > $max) {
            $this->fail("{$path}.{$field}", "Must be a number between {$min} and {$max}.");
        }
        $output[$field] = $value;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $output
     */
    private function copyInteger(array $input, array &$output, string $field, int $min, int $max, string $path): void
    {
        if (! array_key_exists($field, $input)) {
            return;
        }
        $value = $input[$field];
        if (! is_int($value) || $value < $min || $value > $max) {
            $this->fail("{$path}.{$field}", "Must be an integer between {$min} and {$max}.");
        }
        $output[$field] = $value;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $output
     */
    private function copyBoolean(array $input, array &$output, string $field, string $path): void
    {
        if (! array_key_exists($field, $input)) {
            return;
        }
        if (! is_bool($input[$field])) {
            $this->fail("{$path}.{$field}", 'Must be a boolean.');
        }
        $output[$field] = $input[$field];
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $output
     */
    private function copyString(array $input, array &$output, string $field, int $max, string $path): void
    {
        if (! array_key_exists($field, $input)) {
            return;
        }
        $value = $input[$field];
        if (! is_string($value) || mb_strlen($value) > $max || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value) === 1) {
            $this->fail("{$path}.{$field}", "Must be a string of at most {$max} characters.");
        }
        $output[$field] = $value;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $output
     */
    private function copyNullableString(array $input, array &$output, string $field, int $max, string $path): void
    {
        if (! array_key_exists($field, $input)) {
            return;
        }
        if ($input[$field] === null) {
            $output[$field] = null;

            return;
        }
        $this->copyString($input, $output, $field, $max, $path);
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $output
     * @param  list<string>  $allowed
     */
    private function copyEnum(array $input, array &$output, string $field, array $allowed, string $path): void
    {
        if (! array_key_exists($field, $input)) {
            return;
        }
        if (! is_string($input[$field]) || ! in_array($input[$field], $allowed, true)) {
            $this->fail("{$path}.{$field}", 'Contains an unsupported value.');
        }
        $output[$field] = $input[$field];
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $output
     */
    private function copyPoints(array $input, array &$output, string $field, string $path): void
    {
        if (! array_key_exists($field, $input)) {
            return;
        }
        $points = $input[$field];
        if (! is_array($points) || ! array_is_list($points) || count($points) < 2 || count($points) > self::MAX_POINTS) {
            $this->fail("{$path}.{$field}", 'Must contain between 2 and '.self::MAX_POINTS.' coordinate pairs.');
        }
        $projected = [];
        foreach ($points as $index => $point) {
            if (! is_array($point) || ! array_is_list($point) || count($point) !== 2) {
                $this->fail("{$path}.{$field}.{$index}", 'Must be one coordinate pair.');
            }
            $pair = [];
            foreach ($point as $coordinate) {
                if ((! is_int($coordinate) && ! is_float($coordinate))
                    || ! is_finite((float) $coordinate)
                    || abs($coordinate) > self::MAX_COORDINATE) {
                    $this->fail("{$path}.{$field}.{$index}", 'Contains an invalid coordinate.');
                }
                $pair[] = $coordinate;
            }
            $projected[] = $pair;
        }
        $output[$field] = $projected;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $output
     */
    private function copyBinding(array $input, array &$output, string $field, string $path): void
    {
        if (! array_key_exists($field, $input)) {
            return;
        }
        if ($input[$field] === null) {
            $output[$field] = null;

            return;
        }
        $binding = $this->object($input[$field], ['id', 'elementId', 'focus', 'gap'], "{$path}.{$field}");
        $target = $binding['elementId'] ?? $binding['id'] ?? null;
        if (! is_string($target) || $target === '' || strlen($target) > 255) {
            $this->fail("{$path}.{$field}", 'Must name one bounded target id.');
        }
        $projected = ['elementId' => $target];
        $this->copyNumber($binding, $projected, 'focus', -1, 1, "{$path}.{$field}");
        $this->copyNumber($binding, $projected, 'gap', 0, 10_000, "{$path}.{$field}");
        $output[$field] = $projected;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $output
     */
    private function copyArrowhead(array $input, array &$output, string $field, string $path): void
    {
        if (! array_key_exists($field, $input)) {
            return;
        }
        if ($input[$field] === null) {
            $output[$field] = null;

            return;
        }
        $this->copyEnum($input, $output, $field, self::ARROWHEADS, $path);
    }

    private function fail(string $path, string $message): never
    {
        throw ValidationException::withMessages([$path => $message]);
    }
}
