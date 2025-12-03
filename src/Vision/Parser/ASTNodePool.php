<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Parser;

/**
 * Object Pool pour ASTNode
 * 
 * OPTIMISATION: Réutilise les nœuds avec les mêmes type+value.
 * Même si les propriétés readonly empêchent la modification, on peut réutiliser
 * les instances existantes qui ont déjà les bonnes valeurs.
 * 
 * Cette approche fonctionne car :
 * - Les nœuds avec les mêmes type+value sont identiques
 * - On nettoie les children/metadata avant réutilisation
 * - On limite la taille du pool pour éviter les fuites mémoire
 */
class ASTNodePool
{
    /**
     * Pool d'objets ASTNode indexé par type+value
     * 
     * @var array<string, array<ASTNode>>
     */
    private array $pool = [];

    /**
     * Compteur d'objets créés vs réutilisés
     */
    private int $created = 0;
    private int $reused = 0;

    /**
     * Taille maximale du pool par combinaison type+value
     */
    private const MAX_POOL_SIZE_PER_KEY = 50;
    private const MAX_TOTAL_POOL_SIZE = 1000;

    /**
     * Acquiert un ASTNode depuis le pool ou en crée un nouveau
     * 
     * Réutilise les nœuds avec les mêmes type+value si disponibles.
     * 
     * @param NodeType $type Type du nœud
     * @param string $value Valeur associée au nœud
     * @return ASTNode
     */
    public function acquire(NodeType $type, string $value = ''): ASTNode
    {
        $key = $this->getKey($type, $value);
        
        // Chercher un nœud disponible dans le pool
        if (isset($this->pool[$key]) && !empty($this->pool[$key])) {
            $node = array_pop($this->pool[$key]);
            
            // S'assurer que le nœud est propre (déjà fait lors du release)
            // Mais on le refait par sécurité
            $node->children = [];
            $node->metadata = [];
            
            $this->reused++;
            return $node;
        }
        
        // Créer un nouveau nœud si aucun disponible
        $this->created++;
        return new ASTNode($type, $value);
    }

    /**
     * Libère un ASTNode dans le pool pour réutilisation
     * 
     * Nettoie les références et ajoute le nœud au pool s'il y a de la place.
     * 
     * @param ASTNode $node Nœud à libérer
     * @return void
     */
    public function release(ASTNode $node): void
    {
        // Nettoyer les enfants et métadonnées pour libérer les références
        $node->children = [];
        $node->metadata = [];
        
        // Ajouter au pool si on n'a pas atteint la limite
        $key = $this->getKey($node->type, $node->value);
        
        if (!isset($this->pool[$key])) {
            $this->pool[$key] = [];
        }
        
        // Vérifier les limites
        $totalSize = $this->getTotalPoolSize();
        if (count($this->pool[$key]) < self::MAX_POOL_SIZE_PER_KEY && 
            $totalSize < self::MAX_TOTAL_POOL_SIZE) {
            $this->pool[$key][] = $node;
        }
    }

    /**
     * Génère une clé unique pour type+value
     * 
     * @param NodeType $type
     * @param string $value
     * @return string
     */
    private function getKey(NodeType $type, string $value): string
    {
        return $type->value . ':' . md5($value);
    }

    /**
     * Obtient la taille totale du pool
     * 
     * @return int
     */
    private function getTotalPoolSize(): int
    {
        $total = 0;
        foreach ($this->pool as $nodes) {
            $total += count($nodes);
        }
        return $total;
    }

    /**
     * Vide complètement le pool
     * 
     * @return void
     */
    public function clear(): void
    {
        $this->pool = [];
    }

    /**
     * Obtient les statistiques du pool
     * 
     * @return array{
     *     total_nodes: int,
     *     pool_size: int,
     *     created: int,
     *     reused: int,
     *     reuse_rate: float
     * }
     */
    public function getStats(): array
    {
        $totalNodes = $this->getTotalPoolSize();
        $total = $this->created + $this->reused;
        $reuseRate = $total > 0 ? ($this->reused / $total) * 100 : 0.0;

        return [
            'total_nodes' => $totalNodes,
            'pool_size' => count($this->pool),
            'created' => $this->created,
            'reused' => $this->reused,
            'reuse_rate' => round($reuseRate, 2),
        ];
    }

    /**
     * Réinitialise les compteurs (mais garde le pool)
     */
    public function resetStats(): void
    {
        $this->created = 0;
        $this->reused = 0;
    }
}
