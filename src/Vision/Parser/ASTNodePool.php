<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Parser;

/**
 * Object Pool pour ASTNode
 * 
 * LIMITATION ACTUELLE: Cette optimisation est limitée car ASTNode utilise
 * des propriétés readonly (type, value) qui empêchent la réutilisation directe
 * des instances. Le pool est implémenté pour préparer une future optimisation
 * si ASTNode est modifié pour permettre la réutilisation.
 * 
 * Pour une vraie optimisation, il faudrait :
 * - Soit modifier ASTNode pour permettre la réinitialisation (perdre l'immutabilité)
 * - Soit utiliser un système de clonage (mais cela crée de nouvelles instances)
 * 
 * Pour l'instant, cette classe sert de placeholder et nettoie les références
 * pour aider le garbage collector.
 */
class ASTNodePool
{
    /**
     * Pool d'objets ASTNode (préparé pour future optimisation)
     * 
     * @var array<string, array<ASTNode>>
     */
    private array $pool = [];

    /**
     * Taille maximale du pool par type
     */
    private const MAX_POOL_SIZE = 1000;

    /**
     * Acquiert un ASTNode depuis le pool ou en crée un nouveau
     * 
     * Note: Avec les propriétés readonly, on ne peut pas réutiliser les instances.
     * Cette méthode crée toujours un nouveau nœud mais prépare le terrain pour
     * une future optimisation si ASTNode est modifié.
     * 
     * @param NodeType $type Type du nœud
     * @param string $value Valeur associée au nœud
     * @return ASTNode
     */
    public function acquire(NodeType $type, string $value = ''): ASTNode
    {
        // Créer un nouveau nœud (les propriétés readonly empêchent la réutilisation)
        // TODO: Si ASTNode est modifié pour permettre la réutilisation, implémenter
        // la logique de pool ici
        return new ASTNode($type, $value);
    }

    /**
     * Libère un ASTNode dans le pool pour réutilisation
     * 
     * Nettoie les références pour aider le garbage collector.
     * 
     * @param ASTNode $node Nœud à libérer
     * @return void
     */
    public function release(ASTNode $node): void
    {
        // Nettoyer les enfants et métadonnées pour libérer les références
        // Cela aide le garbage collector même si on ne peut pas réutiliser l'instance
        $node->children = [];
        $node->metadata = [];
        
        // TODO: Si ASTNode est modifié pour permettre la réutilisation,
        // ajouter le nœud au pool ici
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
     * @return array{total_nodes: int, pool_size: int}
     */
    public function getStats(): array
    {
        $totalNodes = 0;
        foreach ($this->pool as $nodes) {
            $totalNodes += count($nodes);
        }

        return [
            'total_nodes' => $totalNodes,
            'pool_size' => count($this->pool),
        ];
    }
}
