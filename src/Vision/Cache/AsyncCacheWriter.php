<?php

namespace JulienLinard\Vision\Cache;

use SplQueue;

/**
 * Gestionnaire d'écritures asynchrones pour le cache
 * 
 * Permet d'écrire dans le cache sans bloquer le rendu de la réponse.
 * Utilise soit pcntl_fork() si disponible, soit register_shutdown_function() comme fallback.
 */
class AsyncCacheWriter
{
    private SplQueue $writeQueue;
    private bool $workerRunning = false;
    private bool $enabled = true;

    public function __construct(bool $enabled = true)
    {
        $this->writeQueue = new SplQueue();
        $this->enabled = $enabled;
    }

    /**
     * Ajoute une écriture à la queue
     * 
     * @param string $filepath Chemin du fichier à écrire
     * @param string $content Contenu à écrire
     */
    public function enqueueWrite(string $filepath, string $content): void
    {
        if (!$this->enabled) {
            // Mode synchrone : écrire directement
            $this->writeSync($filepath, $content);
            return;
        }

        $this->writeQueue->enqueue([
            'file' => $filepath,
            'content' => $content,
            'timestamp' => time(),
        ]);

        if (!$this->workerRunning) {
            $this->startWorker();
        }
    }

    /**
     * Démarre le worker asynchrone
     */
    private function startWorker(): void
    {
        $this->workerRunning = true;

        // Essayer d'utiliser pcntl_fork() si disponible
        if (function_exists('pcntl_fork') && function_exists('posix_getpid')) {
            $pid = @pcntl_fork();
            
            if ($pid === -1) {
                // Échec du fork, utiliser fallback
                $this->registerShutdownHandler();
            } elseif ($pid === 0) {
                // Processus enfant : traiter la queue
                $this->processQueue();
                exit(0);
            }
            // Processus parent : continuer normalement
        } else {
            // Fallback: traitement en fin de requête
            $this->registerShutdownHandler();
        }
    }

    /**
     * Enregistre le handler de shutdown pour traiter la queue
     */
    private function registerShutdownHandler(): void
    {
        register_shutdown_function(function () {
            $this->processQueue();
        });
    }

    /**
     * Traite la queue d'écriture
     */
    private function processQueue(): void
    {
        while (!$this->writeQueue->isEmpty()) {
            try {
                $item = $this->writeQueue->dequeue();
                
                // Vérifier que l'item n'est pas trop ancien (max 60 secondes)
                if (time() - $item['timestamp'] > 60) {
                    continue; // Ignorer les items trop anciens
                }
                
                $this->writeSync($item['file'], $item['content']);
            } catch (\Throwable $e) {
                // Ignorer les erreurs silencieusement pour ne pas bloquer
                // En production, on pourrait logger ces erreurs
                continue;
            }
        }
        
        $this->workerRunning = false;
    }

    /**
     * Écriture synchrone (utilisée en fallback ou si désactivé)
     * 
     * @param string $filepath Chemin du fichier
     * @param string $content Contenu à écrire
     */
    private function writeSync(string $filepath, string $content): void
    {
        // Créer le répertoire parent si nécessaire
        $dir = dirname($filepath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        // Écrire avec verrouillage exclusif
        $fp = @fopen($filepath, 'c');
        if ($fp === false) {
            return;
        }

        try {
            // Verrouillage exclusif avec timeout
            $startTime = time();
            $locked = false;
            $timeout = 2; // 2 secondes max

            while (!$locked && (time() - $startTime) < $timeout) {
                $locked = @flock($fp, LOCK_EX | LOCK_NB);
                if (!$locked) {
                    usleep(100000); // Attendre 100ms
                }
            }

            if (!$locked) {
                return; // Timeout, abandonner
            }

            // Tronquer et écrire
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, $content);
            flock($fp, LOCK_UN);
        } finally {
            fclose($fp);
        }
    }

    /**
     * Active ou désactive l'écriture asynchrone
     * 
     * @param bool $enabled
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    /**
     * Vérifie si l'écriture asynchrone est activée
     * 
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Vide la queue d'écriture
     */
    public function clearQueue(): void
    {
        while (!$this->writeQueue->isEmpty()) {
            $this->writeQueue->dequeue();
        }
    }

    /**
     * Obtient le nombre d'éléments en attente dans la queue
     * 
     * @return int
     */
    public function getQueueSize(): int
    {
        return $this->writeQueue->count();
    }
}
