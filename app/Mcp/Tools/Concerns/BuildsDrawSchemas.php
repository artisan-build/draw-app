<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Concerns;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\ArrayType;

trait BuildsDrawSchemas
{
    protected function skeletonsSchema(JsonSchema $schema, bool $required = true): ArrayType
    {
        $type = $schema->array()
            ->items($schema->object())
            ->min(1)
            ->max(200)
            ->description('Excalidraw skeletons: rectangle, ellipse, diamond, text, arrow, or frame.');

        return $required ? $type->required() : $type;
    }
}
