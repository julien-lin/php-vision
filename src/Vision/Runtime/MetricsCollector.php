<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Runtime;

/**
 * Collecteur de métriques de performance
 * 
 * Permet de suivre les performances du moteur de templates
 * pour le monitoring en production.
 */
class MetricsCollector
{
    /**
     * Métriques collectées
     * 
     * @var array<string, int|float>
     */
    private array $metrics = [
        'render_count' => 0,
        'render_time_total' => 0.0,
        'render_time_avg' => 0.0,
        'render_time_min' => PHP_FLOAT_MAX,
        'render_time_max' => 0.0,
        'cache_hits' => 0,
        'cache_misses' => 0,
        'compilation_count' => 0,
        'compilation_time_total' => 0.0,
        'compilation_time_avg' => 0.0,
        'parse_count' => 0,
        'parse_time_total' => 0.0,
        'parse_time_avg' => 0.0,
    ];

    /**
     * Enregistre un rendu avec son temps d'exécution
     * 
     * @param float $time Temps d'exécution en secondes
     * @param bool $cacheHit Si le cache a été utilisé
     * @return void
     */
    public function recordRender(float $time, bool $cacheHit = false): void
    {
        $this->metrics['render_count']++;
        $this->metrics['render_time_total'] += $time;
        $this->metrics['render_time_avg'] = 
            $this->metrics['render_time_total'] / $this->metrics['render_count'];
        
        // Mettre à jour min/max
        if ($time < $this->metrics['render_time_min']) {
            $this->metrics['render_time_min'] = $time;
        }
        if ($time > $this->metrics['render_time_max']) {
            $this->metrics['render_time_max'] = $time;
        }
        
        if ($cacheHit) {
            $this->metrics['cache_hits']++;
        } else {
            $this->metrics['cache_misses']++;
        }
    }

    /**
     * Enregistre une compilation avec son temps d'exécution
     * 
     * @param float $time Temps d'exécution en secondes
     * @return void
     */
    public function recordCompilation(float $time): void
    {
        $this->metrics['compilation_count']++;
        $this->metrics['compilation_time_total'] += $time;
        $this->metrics['compilation_time_avg'] = 
            $this->metrics['compilation_time_total'] / $this->metrics['compilation_count'];
    }

    /**
     * Enregistre un parsing avec son temps d'exécution
     * 
     * @param float $time Temps d'exécution en secondes
     * @return void
     */
    public function recordParse(float $time): void
    {
        $this->metrics['parse_count']++;
        $this->metrics['parse_time_total'] += $time;
        $this->metrics['parse_time_avg'] = 
            $this->metrics['parse_time_total'] / $this->metrics['parse_count'];
    }

    /**
     * Obtient toutes les métriques
     * 
     * @return array<string, int|float> Métriques collectées
     */
    public function getMetrics(): array
    {
        // Corriger render_time_min si aucun rendu n'a été enregistré
        if ($this->metrics['render_count'] === 0) {
            $this->metrics['render_time_min'] = 0.0;
        }
        
        return $this->metrics;
    }

    /**
     * Obtient le taux de cache hit en pourcentage
     * 
     * @return float Taux de cache hit (0-100)
     */
    public function getCacheHitRate(): float
    {
        $total = $this->metrics['cache_hits'] + $this->metrics['cache_misses'];
        return $total > 0 ? ($this->metrics['cache_hits'] / $total) * 100 : 0.0;
    }

    /**
     * Réinitialise toutes les métriques
     * 
     * @return void
     */
    public function reset(): void
    {
        $this->metrics = [
            'render_count' => 0,
            'render_time_total' => 0.0,
            'render_time_avg' => 0.0,
            'render_time_min' => PHP_FLOAT_MAX,
            'render_time_max' => 0.0,
            'cache_hits' => 0,
            'cache_misses' => 0,
            'compilation_count' => 0,
            'compilation_time_total' => 0.0,
            'compilation_time_avg' => 0.0,
            'parse_count' => 0,
            'parse_time_total' => 0.0,
            'parse_time_avg' => 0.0,
        ];
    }

    /**
     * Obtient un résumé des métriques
     * 
     * @return array<string, mixed> Résumé formaté
     */
    public function getSummary(): array
    {
        return [
            'renders' => [
                'count' => $this->metrics['render_count'],
                'avg_time_ms' => round($this->metrics['render_time_avg'] * 1000, 2),
                'min_time_ms' => $this->metrics['render_time_min'] === PHP_FLOAT_MAX 
                    ? 0.0 
                    : round($this->metrics['render_time_min'] * 1000, 2),
                'max_time_ms' => round($this->metrics['render_time_max'] * 1000, 2),
            ],
            'cache' => [
                'hits' => $this->metrics['cache_hits'],
                'misses' => $this->metrics['cache_misses'],
                'hit_rate_percent' => round($this->getCacheHitRate(), 2),
            ],
            'compilation' => [
                'count' => $this->metrics['compilation_count'],
                'avg_time_ms' => round($this->metrics['compilation_time_avg'] * 1000, 2),
            ],
            'parsing' => [
                'count' => $this->metrics['parse_count'],
                'avg_time_ms' => round($this->metrics['parse_time_avg'] * 1000, 2),
            ],
        ];
    }
}
