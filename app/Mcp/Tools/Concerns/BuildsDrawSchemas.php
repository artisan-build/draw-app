<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Concerns;

use App\Mcp\McpSceneInput;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\ArrayType;
use Illuminate\JsonSchema\Types\NumberType;
use Illuminate\JsonSchema\Types\ObjectType;
use Illuminate\JsonSchema\Types\StringType;

trait BuildsDrawSchemas
{
    protected function skeletonsSchema(JsonSchema $schema, bool $required = true, int $minimum = 1): ArrayType
    {
        $type = $schema->array()
            ->items($this->skeletonSchema($schema))
            ->min($minimum)
            ->max(McpSceneInput::MAX_ITEMS)
            ->description('Excalidraw skeletons: rectangle, ellipse, diamond, text, arrow, or frame.');

        return $required ? $type->required() : $type;
    }

    protected function patchesSchema(JsonSchema $schema): ArrayType
    {
        return $schema->array()
            ->items($this->patchSchema($schema))
            ->min(1)
            ->max(McpSceneInput::MAX_ITEMS)
            ->description('Bounded non-destructive element patches with stable ids.')
            ->required();
    }

    private function skeletonSchema(JsonSchema $schema): ObjectType
    {
        return $schema->object([
            'id' => $schema->string()->max(255),
            'type' => $schema->string()->enum(['rectangle', 'ellipse', 'diamond', 'text', 'arrow', 'frame']),
            'x' => $this->coordinate($schema),
            'y' => $this->coordinate($schema),
            'w' => $this->dimension($schema),
            'h' => $this->dimension($schema),
            'width' => $this->dimension($schema),
            'height' => $this->dimension($schema),
            'strokeColor' => $schema->string()->max(64),
            'backgroundColor' => $schema->string()->max(64),
            'text' => $schema->string()->max(McpSceneInput::MAX_TEXT_LENGTH),
            'label' => $schema->string()->max(1000),
            'name' => $schema->string()->max(120),
            'points' => $this->points($schema),
            'startBinding' => $this->binding($schema),
            'start' => $this->binding($schema),
            'endBinding' => $this->binding($schema),
            'end' => $this->binding($schema),
            'startArrowhead' => $this->arrowhead($schema),
            'endArrowhead' => $this->arrowhead($schema),
        ])->withoutAdditionalProperties();
    }

    private function patchSchema(JsonSchema $schema): ObjectType
    {
        return $schema->object([
            'id' => $schema->string()->max(255)->required(),
            'x' => $this->coordinate($schema),
            'y' => $this->coordinate($schema),
            'width' => $this->dimension($schema),
            'height' => $this->dimension($schema),
            'angle' => $schema->number()->min(-6.284)->max(6.284),
            'strokeColor' => $schema->string()->max(64),
            'backgroundColor' => $schema->string()->max(64),
            'fillStyle' => $schema->string()->enum(['hachure', 'cross-hatch', 'solid', 'zigzag']),
            'strokeWidth' => $schema->number()->min(1)->max(10),
            'strokeStyle' => $schema->string()->enum(['solid', 'dashed', 'dotted']),
            'roughness' => $schema->number()->min(0)->max(2),
            'opacity' => $schema->number()->min(0)->max(100),
            'locked' => $schema->boolean(),
            'link' => $schema->string()->max(2048),
            'name' => $schema->string()->max(120),
            'text' => $schema->string()->max(McpSceneInput::MAX_TEXT_LENGTH),
            'fontSize' => $schema->number()->min(1)->max(200),
            'fontFamily' => $schema->integer()->min(1)->max(100),
            'textAlign' => $schema->string()->enum(['left', 'center', 'right']),
            'verticalAlign' => $schema->string()->enum(['top', 'middle', 'bottom']),
            'points' => $this->points($schema),
            'startBinding' => $this->binding($schema),
            'endBinding' => $this->binding($schema),
            'startArrowhead' => $this->arrowhead($schema),
            'endArrowhead' => $this->arrowhead($schema),
            'frameId' => $schema->string()->max(255),
        ])->withoutAdditionalProperties();
    }

    private function coordinate(JsonSchema $schema): NumberType
    {
        return $schema->number()->min(-McpSceneInput::MAX_COORDINATE)->max(McpSceneInput::MAX_COORDINATE);
    }

    private function dimension(JsonSchema $schema): NumberType
    {
        return $schema->number()->min(0)->max(McpSceneInput::MAX_COORDINATE);
    }

    private function points(JsonSchema $schema): ArrayType
    {
        return $schema->array()
            ->items($schema->array()->items($this->coordinate($schema))->min(2)->max(2))
            ->min(2)
            ->max(McpSceneInput::MAX_POINTS);
    }

    private function binding(JsonSchema $schema): ObjectType
    {
        return $schema->object([
            'id' => $schema->string()->max(255),
            'elementId' => $schema->string()->max(255),
            'focus' => $schema->number()->min(-1)->max(1),
            'gap' => $schema->number()->min(0)->max(10_000),
        ])->withoutAdditionalProperties();
    }

    private function arrowhead(JsonSchema $schema): StringType
    {
        return $schema->string()->enum([
            'arrow', 'bar', 'dot', 'circle', 'circle_outline', 'triangle',
            'triangle_outline', 'diamond', 'diamond_outline', 'crowfoot_one',
            'crowfoot_many', 'crowfoot_one_or_many',
        ]);
    }
}
