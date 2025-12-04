<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Filters;

/**
 * Batch filter - Group array items into chunks
 * 
 * Usage: {{ items|batch(2) }} to group into batches of 2
 * Usage: {{ items|batch(3, 'fill') }} to pad with 'fill'
 */
class BatchFilter implements FilterInterface
{
    public function getName(): string
    {
        return 'batch';
    }

    public function apply(mixed $value, array $args = []): mixed
    {
        if (!is_array($value) && !($value instanceof \Traversable)) {
            return $value;
        }

        if ($value instanceof \Traversable) {
            $value = iterator_to_array($value, false);
        }

        $size = isset($args[0]) ? (int)$args[0] : 1;
        if ($size <= 0) {
            $size = 1;
        }

        $fill = $args[1] ?? null;
        $batches = [];

        for ($i = 0; $i < count($value); $i += $size) {
            $batch = array_slice($value, $i, $size);
            
            // Pad the batch if fill is provided and batch is smaller than size
            if ($fill !== null && count($batch) < $size) {
                $batch = array_pad($batch, $size, $fill);
            }

            $batches[] = $batch;
        }

        return $batches;
    }
}
