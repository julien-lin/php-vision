<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Filters;

/**
 * Interface pour les filtres Vision
 */
interface FilterInterface
{
    /**
     * Applique le filtre à une valeur
     *
     * @param mixed $value La valeur à filtrer
     * @param array $params Les paramètres optionnels du filtre
     * @return mixed La valeur filtrée
     */
    public function apply(mixed $value, array $params = []): mixed;

    /**
     * Retourne le nom du filtre
     *
     * @return string
     */
    public function getName(): string;
}
