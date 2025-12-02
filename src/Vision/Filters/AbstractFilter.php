<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Filters;

/**
 * Classe abstraite de base pour les filtres
 */
abstract class AbstractFilter implements FilterInterface
{
    /**
     * Retourne le nom du filtre
     *
     * @return string
     */
    abstract public function getName(): string;

    /**
     * Applique le filtre à une valeur
     *
     * @param mixed $value La valeur à filtrer
     * @param array $params Les paramètres optionnels du filtre
     * @return mixed La valeur filtrée
     */
    abstract public function apply(mixed $value, array $params = []): mixed;
}
