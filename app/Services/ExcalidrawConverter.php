<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Str;

class ExcalidrawConverter
{
    /**
     * @param  array<int, array<string, mixed>>  $skeletons
     * @return array<int, array<string, mixed>>
     */
    public function convert(array $skeletons, float $offsetX = 0, float $offsetY = 0, ?string $frameId = null): array
    {
        $ids = [];

        foreach ($skeletons as $index => $skeleton) {
            $key = (string) ($skeleton['id'] ?? $index);
            $ids[$key] = $this->id($skeleton['id'] ?? null);
        }

        $elements = [];

        foreach ($skeletons as $index => $skeleton) {
            $type = (string) ($skeleton['type'] ?? 'rectangle');
            $key = (string) ($skeleton['id'] ?? $index);
            $id = $ids[$key];
            $x = (float) ($skeleton['x'] ?? 0) + $offsetX;
            $y = (float) ($skeleton['y'] ?? 0) + $offsetY;
            $width = (float) ($skeleton['w'] ?? $skeleton['width'] ?? ($type === 'text' ? 180 : 220));
            $height = (float) ($skeleton['h'] ?? $skeleton['height'] ?? ($type === 'text' ? 36 : 100));
            $element = $this->base($id, $type, $x, $y, $width, $height, $skeleton, $frameId);

            if ($type === 'text') {
                $elements[] = $this->textElement($element, (string) ($skeleton['text'] ?? $skeleton['label'] ?? 'Text'));

                continue;
            }

            if ($type === 'arrow') {
                $element['points'] = $skeleton['points'] ?? [[0, 0], [$width, $height]];
                $element['startBinding'] = $this->binding($skeleton['startBinding'] ?? $skeleton['start'] ?? null, $ids);
                $element['endBinding'] = $this->binding($skeleton['endBinding'] ?? $skeleton['end'] ?? null, $ids);
                $element['startArrowhead'] = $skeleton['startArrowhead'] ?? null;
                $element['endArrowhead'] = $skeleton['endArrowhead'] ?? 'arrow';
                $elements[] = $element;

                if (isset($skeleton['label'])) {
                    $elements[] = $this->standaloneText(
                        (string) $skeleton['label'],
                        $x + ($width / 2) - 60,
                        $y + ($height / 2) - 14,
                        $frameId,
                    );
                }

                continue;
            }

            if (in_array($type, ['rectangle', 'ellipse', 'diamond', 'frame'], true)) {
                if ($type === 'frame') {
                    $element['name'] = (string) ($skeleton['name'] ?? $skeleton['label'] ?? 'Slide');
                }

                $label = $skeleton['label'] ?? null;
                if (is_string($label) && $label !== '' && $type !== 'frame') {
                    $text = $this->boundText($label, $element, $frameId);
                    $element['boundElements'] = [['type' => 'text', 'id' => $text['id']]];
                    $elements[] = $element;
                    $elements[] = $text;
                } else {
                    $elements[] = $element;
                }
            }
        }

        return $elements;
    }

    /** @return array<int, array<string, mixed>> */
    public function mermaid(string $mermaid, float $offsetX = 0, float $offsetY = 0): array
    {
        $source = preg_replace('/^\s*(?:flowchart|graph)\s+(?:TD|TB|LR|RL|BT)\s*;?/i', '', trim($mermaid)) ?? '';
        $statements = preg_split('/[;\n]+/', $source, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $nodes = [];
        $edges = [];

        foreach ($statements as $statement) {
            if (preg_match('/^\s*([A-Za-z0-9_-]+)(?:\[([^\]]+)\]|\{([^}]+)\}|\(([^)]+)\))?\s*-+(?:\|([^|]+)\|)?\s*>\s*([A-Za-z0-9_-]+)(?:\[([^\]]+)\]|\{([^}]+)\}|\(([^)]+)\))?/u', trim($statement), $match)) {
                $from = $match[1];
                $to = $match[6];
                $nodes[$from] = [
                    'label' => $this->firstLabel($match, 2, $from),
                    'type' => $match[3] !== '' ? 'diamond' : 'rectangle',
                ];
                $nodes[$to] = [
                    'label' => $this->firstLabel($match, 7, $to),
                    'type' => ($match[8] ?? '') !== '' ? 'diamond' : 'rectangle',
                ];
                $edgeLabel = trim($match[5]);
                $edges[] = [$from, $to, $edgeLabel === '' ? null : $edgeLabel];
            } elseif (preg_match('/^\s*([A-Za-z0-9_-]+)(?:\[([^\]]+)\]|\{([^}]+)\}|\(([^)]+)\))/u', trim($statement), $match)) {
                $nodes[$match[1]] = [
                    'label' => $this->firstLabel($match, 2, $match[1]),
                    'type' => isset($match[3]) && $match[3] !== '' ? 'diamond' : 'rectangle',
                ];
            }
        }

        if ($nodes === []) {
            return $this->convert([[
                'type' => 'text',
                'text' => $mermaid,
                'x' => $offsetX,
                'y' => $offsetY,
                'w' => 600,
            ]]);
        }

        $skeletons = [];
        $positions = [];
        foreach (array_keys($nodes) as $index => $key) {
            $x = $offsetX + (($index % 3) * 320);
            $y = $offsetY + (intdiv($index, 3) * 220);
            $positions[$key] = [$x, $y];
            $skeletons[] = [
                'id' => $key,
                'type' => $nodes[$key]['type'],
                'label' => $nodes[$key]['label'],
                'x' => $x,
                'y' => $y,
                'w' => 230,
                'h' => 110,
            ];
        }

        foreach ($edges as [$from, $to, $label]) {
            [$fromX, $fromY] = $positions[$from];
            [$toX, $toY] = $positions[$to];
            $skeletons[] = [
                'type' => 'arrow',
                'x' => $fromX + 230,
                'y' => $fromY + 55,
                'w' => $toX - $fromX - 230,
                'h' => $toY - $fromY,
                'start' => ['id' => $from],
                'end' => ['id' => $to],
                'label' => $label,
            ];
        }

        return $this->convert($skeletons);
    }

    /**
     * @param  array<string, mixed>  $skeleton
     * @return array<string, mixed>
     */
    private function base(
        string $id,
        string $type,
        float $x,
        float $y,
        float $width,
        float $height,
        array $skeleton,
        ?string $frameId,
    ): array {
        return [
            'id' => $id,
            'type' => $type,
            'x' => $x,
            'y' => $y,
            'width' => $width,
            'height' => $height,
            'angle' => 0,
            'strokeColor' => $skeleton['strokeColor'] ?? '#1e1e1e',
            'backgroundColor' => $skeleton['backgroundColor'] ?? 'transparent',
            'fillStyle' => 'solid',
            'strokeWidth' => 2,
            'strokeStyle' => 'solid',
            'roughness' => 1,
            'opacity' => 100,
            'groupIds' => [],
            'frameId' => $frameId,
            'roundness' => in_array($type, ['rectangle', 'arrow'], true) ? ['type' => 3] : null,
            'seed' => random_int(1, 2_147_483_646),
            'version' => 1,
            'versionNonce' => random_int(1, 2_147_483_646),
            'isDeleted' => false,
            'boundElements' => [],
            'updated' => now()->getTimestampMs(),
            'link' => null,
            'locked' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    private function textElement(array $base, string $text): array
    {
        return [
            ...$base,
            'fontSize' => 24,
            'fontFamily' => 5,
            'text' => $text,
            'originalText' => $text,
            'textAlign' => 'left',
            'verticalAlign' => 'top',
            'containerId' => null,
            'autoResize' => true,
            'lineHeight' => 1.25,
        ];
    }

    /**
     * @param  array<string, mixed>  $container
     * @return array<string, mixed>
     */
    private function boundText(string $text, array $container, ?string $frameId): array
    {
        $base = $this->base(
            $this->id(),
            'text',
            (float) $container['x'] + 16,
            (float) $container['y'] + ((float) $container['height'] / 2) - 16,
            (float) $container['width'] - 32,
            32,
            [],
            $frameId,
        );

        return [
            ...$this->textElement($base, $text),
            'textAlign' => 'center',
            'verticalAlign' => 'middle',
            'containerId' => $container['id'],
        ];
    }

    /** @return array<string, mixed> */
    private function standaloneText(string $text, float $x, float $y, ?string $frameId): array
    {
        return $this->textElement($this->base($this->id(), 'text', $x, $y, 120, 32, [], $frameId), $text);
    }

    /**
     * @param  array<string, string>  $ids
     * @return array<string, mixed>|null
     */
    private function binding(mixed $binding, array $ids): ?array
    {
        if (! is_array($binding)) {
            return null;
        }

        $target = $binding['elementId'] ?? $binding['id'] ?? null;
        if (! is_string($target)) {
            return null;
        }

        return [
            'elementId' => $ids[$target] ?? $target,
            'focus' => (float) ($binding['focus'] ?? 0),
            'gap' => (float) ($binding['gap'] ?? 8),
        ];
    }

    private function id(mixed $candidate = null): string
    {
        return is_string($candidate) && preg_match('/^[0-9a-f-]{36}$/i', $candidate)
            ? $candidate
            : (string) Str::uuid7();
    }

    /** @param array<int, string> $match */
    private function firstLabel(array $match, int $start, string $fallback): string
    {
        for ($i = $start; $i < $start + 3; $i++) {
            if (isset($match[$i]) && trim($match[$i]) !== '') {
                return trim($match[$i], " \t\n\r\0\x0B\"");
            }
        }

        return $fallback;
    }
}
