<?php

declare(strict_types=1);

namespace JulienLinard\Vision;

use JulienLinard\Vision\Exception\InvalidFilterException;
use JulienLinard\Vision\Exception\TemplateNotFoundException;
use JulienLinard\Vision\Exception\VisionException;
use JulienLinard\Vision\Filters\FilterInterface;
use JulienLinard\Vision\Filters\FilterManager;
use JulienLinard\Vision\Filters\UpperFilter;
use JulienLinard\Vision\Filters\LowerFilter;
use JulienLinard\Vision\Filters\TrimFilter;
use JulienLinard\Vision\Filters\EscapeFilter;
use JulienLinard\Vision\Filters\DefaultFilter;
use JulienLinard\Vision\Filters\DateFormatFilter;
use JulienLinard\Vision\Filters\NumberFormatFilter;
use JulienLinard\Vision\Filters\LengthFilter;
use JulienLinard\Vision\Filters\JsonFilter;
use JulienLinard\Vision\Filters\FirstFilter;
use JulienLinard\Vision\Filters\LastFilter;
use JulienLinard\Vision\Filters\SliceFilter;
use JulienLinard\Vision\Filters\JoinFilter;
use JulienLinard\Vision\Filters\SortFilter;
use JulienLinard\Vision\Filters\ReverseFilter;
use JulienLinard\Vision\Filters\BatchFilter;
use JulienLinard\Vision\Filters\FilterFilter;
use JulienLinard\Vision\Filters\MapFilter;
use JulienLinard\Vision\Parser\TemplateParser;
use JulienLinard\Vision\Compiler\TemplateCompiler;
use JulienLinard\Vision\Compiler\InheritanceResolver;
use JulienLinard\Vision\Cache\CacheManager;
use JulienLinard\Vision\Cache\FragmentCache;
use JulienLinard\Vision\Cache\FileStatsCache;
use JulienLinard\Vision\Runtime\VariableResolver;
use JulienLinard\Vision\Runtime\ControlStructureProcessor;
use JulienLinard\Vision\Runtime\SafeString;
use JulienLinard\Vision\Runtime\Sandbox;
use JulienLinard\Vision\Runtime\MetricsCollector;
use JulienLinard\Vision\Runtime\VisionLoggerInterface;
use JulienLinard\Vision\Runtime\VisionLogger;

/**
 * Moteur de template Vision
 */
class Vision
{
    /**
     * Patterns regex précompilés pour améliorer les performances
     * Optimisés pour éviter ReDoS (limiter backtracking)
     */
    private const PATTERN_VARIABLE = '/\{\{-?\s*([a-zA-Z0-9_.|:"\'()\s,\-\/+*%?&#{}!=<>\\\\]+?)\s*-?\}\}/';
    private const PATTERN_CONDITION_OPERATOR = '/^(\w+(?:\.\w+){0,10})\s*(==|!=|>=|<=|>|<)\s*([\w\s"\'.-]{0,200})$/';
    private const PATTERN_CONDITION_VARIABLE = '/^(\w+(?:\.\w+){0,10})$/';
    private const PATTERN_CONDITION_NEGATION = '/^!\s*(\w+(?:\.\w+){0,10})$/';
    private const PATTERN_QUOTED_STRING = '/^["\'](.{0,1000})["\']$/';

    /**
     * Cache des patterns regex validés (évite validation répétitive)
     * Les patterns constants sont déjà optimisés par PHP, ce cache valide une fois
     * Utile pour des patterns dynamiques futurs
     */
    private static array $validatedPatterns = [];

    /**
     * Valide et met en cache un pattern regex
     * 
     * Note: Les patterns constants sont déjà optimisés par PHP.
     * Cette méthode est utile pour valider des patterns dynamiques.
     * 
     * @param string $pattern Pattern regex à valider
     * @return string Pattern validé
     * @throws VisionException Si le pattern est invalide
     */
    private static function getValidatedPattern(string $pattern): string
    {
        // Cache hit
        if (isset(self::$validatedPatterns[$pattern])) {
            return self::$validatedPatterns[$pattern];
        }

        // Valider le pattern (une seule fois)
        if (@preg_match($pattern, '') === false) {
            $error = error_get_last();
            throw new VisionException("Invalid regex pattern: {$pattern}. " . ($error['message'] ?? ''));
        }

        // Mettre en cache
        self::$validatedPatterns[$pattern] = $pattern;

        return $pattern;
    }

    /**
     * Limites de sécurité
     */
    private const MAX_RECURSION_DEPTH = 50;
    private const MAX_TEMPLATE_SIZE = 10485760; // 10MB


    /**
     * @var string Chemin vers le répertoire des templates
     */
    private string $templateDir;

    /**
     * Gestionnaire de filtres
     */
    private FilterManager $filterManager;

    /**
     * @var array<string, callable> Fonctions personnalisées
     */
    private array $functions = [];

    /**
     * @var bool Activer l'échappement automatique HTML
     */
    private bool $autoEscape = true;

    /**
     * @var bool Activer le cache
     */
    private bool $cacheEnabled = false;

    /**
     * @var string|null Répertoire pour le cache
     */
    private ?string $cacheDir = null;

    /**
     * @var int Durée de validité du cache en secondes (TTL)
     */
    private int $cacheTTL = 3600; // 1 heure par défaut


    /**
     * Intégrations optionnelles (Parser/Compiler/CacheManager)
     */
    private ?TemplateParser $parser = null;
    private ?TemplateCompiler $compiler = null;
    private ?CacheManager $cacheManager = null;
    private ?FragmentCache $fragmentCache = null;
    private VariableResolver $resolver;

    /**
     * Cache des statistiques de fichiers pour réduire les appels système
     */
    private ?FileStatsCache $fileStatsCache = null;

    /**
     * Singleton ControlStructureProcessor pour éviter les allocations répétées
     */
    private ?ControlStructureProcessor $structureProcessor = null;

    /**
     * Cache du chemin de base des templates (rarement change)
     */
    private ?string $cachedRealBasePath = null;

    /**
     * Cache des chemins de templates résolus (limité à 500 entrées)
     */
    private array $templatePathCache = [];

    private const MAX_TEMPLATE_PATH_CACHE_SIZE = 500;

    /**
     * Mode sandbox pour templates non-fiables
     */
    private ?Sandbox $sandbox = null;

    /**
     * Collecteur de métriques de performance
     */
    private ?MetricsCollector $metricsCollector = null;

    /**
     * Logger pour les événements
     */
    private ?VisionLoggerInterface $logger = null;

    /**
     * Constructeur
     *
     * @param string $templateDir Chemin vers le répertoire des templates
     * @param bool $autoEscape Activer l'échappement automatique HTML
     */
    public function __construct(string $templateDir = '', bool $autoEscape = true)
    {
        $this->templateDir = rtrim($templateDir, '/');
        $this->autoEscape = $autoEscape;
        $this->filterManager = new FilterManager();
        $this->resolver = new VariableResolver();
        $this->registerDefaultFilters();
        $this->registerDefaultFunctions();
    }

    /**
     * Enregistre les filtres par défaut
     */
    private function registerDefaultFilters(): void
    {
        $this->registerFilter(new UpperFilter());
        $this->registerFilter(new LowerFilter());
        $this->registerFilter(new TrimFilter());
        $this->registerFilter(new EscapeFilter());
        $this->registerFilter(new DefaultFilter());
        $this->registerFilter(new DateFormatFilter());
        $this->registerFilter(new NumberFormatFilter());
        $this->registerFilter(new LengthFilter());
        $this->registerFilter(new JsonFilter());
        // Filtres avancés
        $this->registerFilter(new FirstFilter());
        $this->registerFilter(new LastFilter());
        $this->registerFilter(new SliceFilter());
        $this->registerFilter(new JoinFilter());
        $this->registerFilter(new SortFilter());
        $this->registerFilter(new ReverseFilter());
        // Filtres pour tableaux
        $this->registerFilter(new BatchFilter());
        $this->registerFilter(new FilterFilter());
        $this->registerFilter(new MapFilter());
    }

    /**
     * Enregistre les fonctions par défaut (template/include/component)
     */
    private function registerDefaultFunctions(): void
    {
        // Rendre un sous-template avec des variables explicites
        // Usage: {{ template("partials/header", vars) }}
        $this->registerFunction('template', function (string $name, array $vars = []) {
            return new SafeString($this->render($name, $vars));
        });

        // Alias pratique: include(name, vars)
        $this->registerFunction('include', function (string $name, array $vars = []) {
            return new SafeString($this->render($name, $vars));
        });

        // Composants réutilisables (convention: templates/components/)
        // Usage: {{ component("Button", { label: "Save", variant: "primary" }) }}
        $this->registerFunction('component', function (string $name, array $props = []) {
            // Convention: chercher dans components/ si pas de chemin explicite
            if (!str_contains($name, '/')) {
                $name = 'components/' . $name;
            }

            // Fragment caching: vérifier le cache avant render
            if ($this->fragmentCache !== null && $this->fragmentCache->isEnabled()) {
                $cacheKey = $this->fragmentCache->generateKey($name, $props);
                $cached = $this->fragmentCache->get($cacheKey);

                if ($cached !== null) {
                    return new SafeString($cached);
                }

                // Render et mettre en cache
                $rendered = $this->render($name, $props);
                $this->fragmentCache->set($cacheKey, $rendered);

                return new SafeString($rendered);
            }

            // Pas de cache: render classique
            return new SafeString($this->render($name, $props));
        });

        // Slots: passer des contenus nommés (remplace extends/blocks)
        // Usage: {{ slot("header", headerContent) }}
        // Note: le contenu doit être pré-rendu (via template/component)
        // Le slot retourne le contenu tel quel, sans échappement (déjà rendu)
        $this->registerFunction('slot', function (string $name, string $content = '') {
            // Slots sont gérés via variables explicites, pas de capture magique
            // On retourne le contenu brut car il a déjà été rendu/échappé
            return new SafeString($content);
        });

        // Date formatting function with named parameters
        // Usage: {{ date(format="Y-m-d") }} or {{ date(format="Y-m-d", timezone="UTC") }}
        $this->registerFunction('date', function (...$args) {
            $format = 'Y-m-d H:i:s';

            // Handle positional and named parameters
            $namedParams = [];
            if (!empty($args) && is_array($args[count($args) - 1])) {
                $lastArg = $args[count($args) - 1];
                // Check if it's an associative array (named params)
                if (!array_is_list($lastArg)) {
                    $namedParams = array_pop($args);
                }
            }

            // Get format from positional or named parameters
            if (!empty($args)) {
                $format = (string)$args[0];
            } elseif (isset($namedParams['format'])) {
                $format = (string)$namedParams['format'];
            }

            $value = time(); // Default to current time
            if (isset($namedParams['time'])) {
                $value = $namedParams['time'];
            }

            if (is_numeric($value)) {
                return date($format, (int)$value);
            }
            if (is_string($value)) {
                $timestamp = strtotime($value);
                return $timestamp !== false ? date($format, $timestamp) : (string)$value;
            }
            if ($value instanceof \DateTimeInterface) {
                return $value->format($format);
            }

            return (string)$value;
        });

        // Text output function with named parameters
        // Usage: {{ text(value=message) }} or {{ text("Hello") }}
        $this->registerFunction('text', function (...$args) {
            $value = '';

            // Handle positional and named parameters
            $namedParams = [];
            if (!empty($args) && is_array($args[count($args) - 1])) {
                $lastArg = $args[count($args) - 1];
                // Check if it's an associative array (named params)
                if (!array_is_list($lastArg)) {
                    $namedParams = array_pop($args);
                }
            }

            // Get value from positional or named parameters
            if (!empty($args)) {
                $value = $args[0];
            } elseif (isset($namedParams['value'])) {
                $value = $namedParams['value'];
            }

            return (string)$value;
        });
    }

    /**
     * Injecte un TemplateParser optionnel
     */
    public function setParser(TemplateParser $parser): self
    {
        $this->parser = $parser;
        return $this;
    }

    /**
     * Injecte un TemplateCompiler optionnel
     */
    public function setCompiler(TemplateCompiler $compiler): self
    {
        $this->compiler = $compiler;

        // Configurer l'InheritanceResolver si parser disponible
        if ($this->parser !== null) {
            $resolver = new InheritanceResolver(
                fn(string $name): string => $this->loadTemplateSource($name),
                $this->parser
            );
            $compiler->setInheritanceResolver($resolver);

            // Configurer le MacroProcessor
            $macroProcessor = new \JulienLinard\Vision\Compiler\MacroProcessor(
                fn(string $name): string => $this->loadTemplateSource($name),
                $this->parser
            );
            $compiler->setMacroProcessor($macroProcessor);
        }

        return $this;
    }

    /**
     * Injecte un CacheManager optionnel
     */
    public function setCacheManager(CacheManager $cacheManager): self
    {
        $this->cacheManager = $cacheManager;
        return $this;
    }

    /**
     * Injecte un FragmentCache optionnel pour le caching des composants
     */
    public function setFragmentCache(FragmentCache $fragmentCache): self
    {
        $this->fragmentCache = $fragmentCache;
        return $this;
    }

    /**
     * Récupère l'instance FragmentCache
     */
    public function getFragmentCache(): ?FragmentCache
    {
        return $this->fragmentCache;
    }

    /**
     * Active le mode sandbox pour templates non-fiables
     * 
     * @param Sandbox $sandbox Instance Sandbox configurée
     * @return self
     */
    public function setSandbox(Sandbox $sandbox): self
    {
        $this->sandbox = $sandbox;
        return $this;
    }

    /**
     * Obtient le Sandbox si configuré
     * 
     * @return Sandbox|null
     */
    public function getSandbox(): ?Sandbox
    {
        return $this->sandbox;
    }

    /**
     * Configure le collecteur de métriques
     * 
     * @param MetricsCollector $collector Instance du collecteur
     * @return self
     */
    public function setMetricsCollector(MetricsCollector $collector): self
    {
        $this->metricsCollector = $collector;
        return $this;
    }

    /**
     * Obtient le collecteur de métriques si configuré
     * 
     * @return MetricsCollector|null
     */
    public function getMetricsCollector(): ?MetricsCollector
    {
        return $this->metricsCollector;
    }

    /**
     * Configure le logger
     * 
     * @param VisionLoggerInterface $logger Instance du logger
     * @return self
     */
    public function setLogger(VisionLoggerInterface $logger): self
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * Obtient le logger si configuré
     * 
     * @return VisionLoggerInterface|null
     */
    public function getLogger(): ?VisionLoggerInterface
    {
        return $this->logger;
    }

    /**
     * Obtient un rapport de santé (health check)
     * 
     * Retourne un tableau avec les informations de santé du système
     * pour intégration avec des outils de monitoring.
     * 
     * @return array<string, mixed> Rapport de santé
     */
    public function getHealthCheck(): array
    {
        $health = [
            'status' => 'ok',
            'timestamp' => date('c'),
            'cache' => [
                'enabled' => $this->cacheEnabled,
                'directory' => $this->cacheDir,
                'ttl' => $this->cacheTTL,
            ],
            'compiled_pipeline' => [
                'enabled' => $this->parser !== null && $this->compiler !== null && $this->cacheManager !== null,
                'parser' => $this->parser !== null,
                'compiler' => $this->compiler !== null,
                'cache_manager' => $this->cacheManager !== null,
            ],
            'fragment_cache' => [
                'enabled' => $this->fragmentCache !== null && $this->fragmentCache->isEnabled(),
            ],
            'sandbox' => [
                'enabled' => $this->sandbox !== null,
            ],
            'memory' => [
                'usage' => memory_get_usage(true),
                'usage_formatted' => $this->formatBytesHelper(memory_get_usage(true)),
                'peak' => memory_get_peak_usage(true),
                'peak_formatted' => $this->formatBytesHelper(memory_get_peak_usage(true)),
            ],
        ];

        // Ajouter les statistiques du cache si disponible
        if ($this->cacheManager !== null) {
            try {
                $cacheStats = $this->cacheManager->getStats();
                $health['cache']['stats'] = $cacheStats;
            } catch (\Throwable $e) {
                $health['cache']['stats_error'] = $e->getMessage();
                $health['status'] = 'degraded';
            }
        }

        // Ajouter les statistiques du fragment cache si disponible
        if ($this->fragmentCache !== null) {
            try {
                $fragmentStats = $this->fragmentCache->getStats();
                $health['fragment_cache']['stats'] = $fragmentStats;
            } catch (\Throwable $e) {
                $health['fragment_cache']['stats_error'] = $e->getMessage();
                $health['status'] = 'degraded';
            }
        }

        // Ajouter les métriques si disponible
        if ($this->metricsCollector !== null) {
            try {
                $metrics = $this->metricsCollector->getSummary();
                $health['metrics'] = $metrics;
            } catch (\Throwable $e) {
                $health['metrics_error'] = $e->getMessage();
            }
        }

        // Vérifier l'accessibilité du répertoire de cache
        if ($this->cacheDir !== null) {
            $health['cache']['directory_writable'] = is_writable($this->cacheDir);
            $health['cache']['directory_exists'] = is_dir($this->cacheDir);

            if (!$health['cache']['directory_writable'] || !$health['cache']['directory_exists']) {
                $health['status'] = 'degraded';
            }
        }

        // Vérifier le répertoire des templates
        if ($this->templateDir !== null && $this->templateDir !== '') {
            $health['templates'] = [
                'directory' => $this->templateDir,
                'exists' => is_dir($this->templateDir),
                'readable' => is_readable($this->templateDir),
            ];

            if (!$health['templates']['exists'] || !$health['templates']['readable']) {
                $health['status'] = 'degraded';
            }
        }

        return $health;
    }

    /**
     * Helper pour formater les bytes
     * 
     * @param int $bytes Nombre d'octets
     * @return string Bytes formatés
     */
    private function formatBytesHelper(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unitIndex = 0;
        $value = (float)$bytes;

        while ($value >= 1024 && $unitIndex < count($units) - 1) {
            $value /= 1024;
            $unitIndex++;
        }

        return round($value, 2) . ' ' . $units[$unitIndex];
    }

    /**
     * Enregistre un filtre personnalisé
     *
     * @param FilterInterface $filter
     * @return self
     */
    public function registerFilter(FilterInterface $filter): self
    {
        $this->filterManager->addFilter($filter);
        return $this;
    }

    /**
     * Enregistre une fonction personnalisée
     *
     * @param string $name Nom de la fonction
     * @param callable $callback
     * @return self
     */
    public function registerFunction(string $name, callable $callback): self
    {
        $this->functions[$name] = $callback;
        return $this;
    }

    /**
     * Active ou désactive l'échappement automatique
     *
     * @param bool $enabled
     * @return self
     */
    public function setAutoEscape(bool $enabled): self
    {
        $this->autoEscape = $enabled;
        return $this;
    }

    /**
     * Active ou désactive le cache
     *
     * @param bool $enabled
     * @param string|null $cacheDir Répertoire pour le cache
     * @param int $ttl Durée de validité du cache en secondes (défaut: 3600)
     * @return self
     */
    public function setCache(bool $enabled, ?string $cacheDir = null, int $ttl = 3600): self
    {
        $this->cacheEnabled = $enabled;
        $this->cacheTTL = $ttl;

        if ($cacheDir !== null) {
            $this->cacheDir = rtrim($cacheDir, '/\\');
            // Créer le répertoire de cache s'il n'existe pas
            if (!is_dir($this->cacheDir)) {
                if (!mkdir($this->cacheDir, 0755, true) && !is_dir($this->cacheDir)) {
                    throw new VisionException("Impossible de créer le répertoire de cache: {$this->cacheDir}");
                }
            }
        }

        return $this;
    }

    /**
     * Configure le fragment cache pour les composants (méthode utilitaire)
     *
     * @param bool $enabled Activer le cache des fragments
     * @param string|null $cacheDir Répertoire pour le cache (défaut: cacheDir/fragments)
     * @param int $ttl Durée de validité en secondes (défaut: 3600)
     * @return self
     */
    public function setFragmentCacheConfig(bool $enabled, ?string $cacheDir = null, int $ttl = 3600): self
    {
        if (!$enabled) {
            $this->fragmentCache = null;
            return $this;
        }

        // Utiliser un sous-répertoire du cache principal si non spécifié
        if ($cacheDir === null && $this->cacheDir !== null) {
            $cacheDir = $this->cacheDir . '/fragments';
        } elseif ($cacheDir === null) {
            throw new VisionException('Fragment cache directory must be specified');
        }

        $this->fragmentCache = new FragmentCache($cacheDir, $ttl, true);
        return $this;
    }

    /**
     * Nettoie le cache (supprime les fichiers expirés)
     *
     * @param int|null $maxAge Age maximum en secondes (null = utiliser TTL, 0 = tout supprimer)
     * @return int Nombre de fichiers supprimés
     */
    public function clearCache(?int $maxAge = null): int
    {
        if ($this->cacheDir === null || !is_dir($this->cacheDir)) {
            return 0;
        }

        $maxAge = $maxAge ?? $this->cacheTTL;
        $now = time();
        $deleted = 0;

        $files = glob($this->cacheDir . '/*.cache');
        if ($files === false) {
            return 0;
        }

        foreach ($files as $file) {
            if (is_file($file)) {
                // Si maxAge = 0, supprimer tous les fichiers
                // Sinon, vérifier l'âge
                if ($maxAge === 0 || ($now - filemtime($file)) > $maxAge) {
                    @unlink($file);
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    /**
     * Rend un template avec des variables
     *
     * @param string $template Nom du template (sans extension)
     * @param array<string, mixed> $variables Variables à passer au template
     * @return string Le contenu rendu
     * @throws TemplateNotFoundException
     * @throws VisionException
     */
    public function render(string $template, array $variables = []): string
    {
        $startTime = microtime(true);
        $cacheHit = false;

        $templatePath = $this->getTemplatePath($template);

        if (!file_exists($templatePath)) {
            $this->logger?->error('Template not found', ['template' => $template, 'path' => $templatePath]);
            throw new TemplateNotFoundException($template);
        }

        // Pipeline compilé optionnel (Parser + Compiler + CacheManager)
        if ($this->parser !== null && $this->compiler !== null && $this->cacheManager !== null && $this->cacheEnabled) {
            // Essayer de charger un template compilé depuis le cache
            $compiled = $this->cacheManager->getCompiled($templatePath);

            if ($compiled === null) {
                // Lire et parser
                $parseStart = microtime(true);
                $content = file_get_contents($templatePath);
                if ($content === false) {
                    throw new VisionException("Impossible de lire le template : {$template}");
                }
                $parsed = $this->parser->parse($content);
                $parseTime = microtime(true) - $parseStart;
                $this->metricsCollector?->recordParse($parseTime);

                // Compiler (avec rate limiting si configuré)
                $compileStart = microtime(true);
                $compiled = $this->compiler->compile($parsed, $templatePath);
                $compileTime = microtime(true) - $compileStart;
                $this->metricsCollector?->recordCompilation($compileTime);

                // Sauvegarder en cache
                $this->cacheManager->saveCompiled($templatePath, $compiled);
                $this->logger?->debug('Template compiled and cached', ['template' => $template]);
            } else {
                $cacheHit = true;
                $this->logger?->debug('Template loaded from cache', ['template' => $template]);
            }

            // Exécuter avec helpers connectés à cette instance
            $helpers = [
                'resolveVariable' => function (string $path, array $vars) {
                    return $this->getNestedValue($vars, $path);
                },
                'applyFilter' => function (string $filterExpression, mixed $value) {
                    return $this->applyFilter($filterExpression, $value);
                },
                'evaluateCondition' => function (string $condition, array $vars) {
                    return $this->evaluateCondition($condition, $vars);
                },
                'evaluateExpression' => function (string $expr, array $vars) {
                    $evaluator = new \JulienLinard\Vision\Runtime\ExpressionEvaluator($this->resolver);
                    return $evaluator->evaluate($expr, $vars);
                },
            ];

            $result = $compiled->execute($variables, $helpers);

            // Enregistrer les métriques
            $renderTime = microtime(true) - $startTime;
            $this->metricsCollector?->recordRender($renderTime, $cacheHit);

            return $result;
        }

        // Fallback: pipeline historique (cache de rendu par fichier)
        if ($this->cacheEnabled && $this->cacheDir !== null) {
            $cached = $this->getCachedContent($templatePath, $variables);
            if ($cached !== null) {
                $cacheHit = true;
                $renderTime = microtime(true) - $startTime;
                $this->metricsCollector?->recordRender($renderTime, $cacheHit);
                return $cached;
            }
        }

        $content = file_get_contents($templatePath);
        if ($content === false) {
            throw new VisionException("Impossible de lire le template : {$template}");
        }

        $rendered = $this->renderString($content, $variables);

        // Sauvegarder dans le cache si activé
        if ($this->cacheEnabled && $this->cacheDir !== null) {
            $this->saveCachedContent($templatePath, $variables, $rendered);
        }

        // Enregistrer les métriques
        $renderTime = microtime(true) - $startTime;
        $this->metricsCollector?->recordRender($renderTime, $cacheHit);

        return $rendered;
    }

    /**
     * Rend une chaîne de template directement
     *
     * @param string $content Contenu du template
     * @param array<string, mixed> $variables Variables à passer au template
     * @param int $depth Profondeur de récursion actuelle
     * @return string Le contenu rendu
     * @throws VisionException Si la profondeur maximale est atteinte
     */
    public function renderString(string $content, array $variables = [], int $depth = 0): string
    {
        $startTime = $depth === 0 ? microtime(true) : null;

        // Valider avec sandbox si activé (seulement au premier niveau)
        if ($depth === 0 && $this->sandbox !== null) {
            $this->sandbox->validateTemplate($content);
        }

        // Vérifier la limite de récursion (utiliser celle du sandbox si défini)
        $maxDepth = $this->sandbox !== null
            ? $this->sandbox->getMaxRecursionDepth()
            : self::MAX_RECURSION_DEPTH;

        if ($depth > $maxDepth) {
            throw new VisionException(
                "Profondeur de récursion maximale atteinte (" . $maxDepth . "). " .
                    "Vérifiez vos templates pour des boucles ou conditions imbriquées trop profondes."
            );
        }

        // Process whitespace control FIRST to normalize tags and strip whitespace
        $content = $this->processWhitespaceControl($content);

        // Traiter les structures de contrôle
        $content = $this->processControlStructures($content, $variables, $depth);

        // Traiter les variables et filtres
        $content = $this->processVariables($content, $variables);

        // Enregistrer les métriques seulement au premier niveau (depth === 0)
        if ($startTime !== null) {
            $renderTime = microtime(true) - $startTime;
            $this->metricsCollector?->recordRender($renderTime, false);
        }

        return $content;
    }

    /**
     * Traite les structures de contrôle (if, for, etc.)
     *
     * @param string $content
     * @param array<string, mixed> $variables
     * @param int $depth Profondeur de récursion actuelle
     * @return string
     */
    private function processControlStructures(string $content, array $variables, int $depth): string
    {
        // Singleton pattern : réutiliser l'instance pour éviter les allocations répétées
        if ($this->structureProcessor === null) {
            $this->structureProcessor = new ControlStructureProcessor();
        }

        return $this->structureProcessor->process(
            $content,
            $variables,
            $depth,
            fn(string $c, array $v, int $d) => $this->renderString($c, $v, $d),
            fn(string $cond, array $v) => $this->evaluateCondition($cond, $v),
            fn(array $v, string $path) => $this->getNestedValue($v, $path),
            fn(mixed $val, string $filter, array $v) => $this->applyFilterToValue($val, $filter, $v),
        );
    }

    /**
     * Évalue une condition
     *
     * @param string $condition
     * @param array<string, mixed> $variables
     * @return bool
     */
    private function evaluateCondition(string $condition, array $variables): bool
    {
        // Support pour les valeurs littérales true/false
        if ($condition === 'true') {
            return true;
        }
        if ($condition === 'false') {
            return false;
        }

        // Support pour l'opérateur "in" (e.g., "5 in 1..10")
        if (preg_match('/^(.+?)\s+in\s+(.+)$/', $condition, $matches)) {
            $valueStr = trim($matches[1]);
            $rangeOrArray = trim($matches[2]);

            // Resolve value: either numeric literal or variable
            if (is_numeric($valueStr)) {
                $value = (int)$valueStr;
            } else {
                $valueExpr = $this->evaluateExpression($valueStr, $variables);
                $value = is_numeric($valueExpr) ? (int)$valueExpr : $valueExpr;
            }

            // Check if it's a range (e.g., "1..10", "0..20..2")
            if (preg_match('/^(?:\-?\d+|\w+)\.\.(?:\-?\d+|\w+)(?:\.\.(?:\-?\d+|\w+))?$/', $rangeOrArray)) {
                $range = $this->parseRange($rangeOrArray, $variables);
                return in_array($value, $range, true);
            } else {
                // It's an array variable
                $arrayValue = $this->getNestedValue($variables, $rangeOrArray);
                if (is_array($arrayValue)) {
                    return in_array($value, $arrayValue, true);
                }
            }
        }

        // Support pour les opérateurs simples
        if (preg_match(self::PATTERN_CONDITION_OPERATOR, $condition, $matches)) {
            $var = $this->getNestedValue($variables, $matches[1]);
            $operator = $matches[2];
            $compareValue = trim($matches[3], '\'"');

            // Conversion automatique en numérique si les deux valeurs semblent numériques
            if (is_numeric($var) && is_numeric($compareValue)) {
                $var = (float)$var;
                $compareValue = (float)$compareValue;
            }

            return match ($operator) {
                '==' => $var == $compareValue,
                '!=' => $var != $compareValue,
                '>' => $var > $compareValue,
                '<' => $var < $compareValue,
                '>=' => $var >= $compareValue,
                '<=' => $var <= $compareValue,
                default => false,
            };
        }

        // Variable simple
        if (preg_match(self::PATTERN_CONDITION_VARIABLE, $condition, $matches)) {
            $value = $this->getNestedValue($variables, $matches[1]);
            // Un tableau ou objet non vide est truthy
            if (is_array($value)) {
                return !empty($value);
            }
            if (is_object($value)) {
                return true;
            }
            return !empty($value) || $value === 0 || $value === '0';
        }

        // Numeric literals (1, 0, 42, etc.)
        if (is_numeric($condition)) {
            return (int)$condition !== 0;
        }

        // Négation
        if (preg_match(self::PATTERN_CONDITION_NEGATION, $condition, $matches)) {
            $value = $this->getNestedValue($variables, $matches[1]);
            // Pour la négation, un tableau ou objet non vide est falsy (car on inverse)
            if (is_array($value)) {
                return empty($value);
            }
            if (is_object($value)) {
                return false;
            }
            return empty($value) && $value !== 0 && $value !== '0';
        }

        return false;
    }

    /**
     * Applique un filtre à une valeur (utilisé dans les boucles avec filtres)
     * Supporte le format batch(2) ou batch:2
     *
     * @param mixed $value
     * @param string $filter Nom du filtre avec paramètres (e.g., "batch(2)" ou "batch:2" ou "filter")
     * @param array<string, mixed> $variables
     * @return mixed
     */
    private function applyFilterToValue(mixed $value, string $filter, array $variables): mixed
    {
        try {
            // Convert batch(2) format to batch:2 format for FilterManager
            $filter = $this->convertFilterFormat($filter);
            return $this->filterManager->apply($filter, $value);
        } catch (InvalidFilterException) {
            // Si le filtre n'existe pas, retourner la valeur inchangée
            return $value;
        }
    }

    /**
     * Convertit le format batch(2) en batch:2 pour FilterManager
     */
    private function convertFilterFormat(string $filter): string
    {
        // Si le format est déjà batch:2, retourner tel quel
        if (strpos($filter, ':') !== false) {
            return $filter;
        }

        // Convertir batch(param1,param2) en batch:param1,param2
        if (preg_match('/^(\w+)\((.*?)\)$/', $filter, $matches)) {
            $filterName = $matches[1];
            $paramsStr = $matches[2];

            // Nettoyer les paramètres: enlever les quotes
            $params = [];
            $parts = explode(',', $paramsStr);
            foreach ($parts as $part) {
                $part = trim($part);
                $part = trim($part, '\'"');
                $params[] = $part;
            }

            return $filterName . ':' . implode(',', $params);
        }

        return $filter;
    }

    /**
     * Traite les variables et filtres dans le contenu
     *
     * @param string $content
     * @param array<string, mixed> $variables
     * @return string
     */
    private function processVariables(string $content, array $variables): string
    {
        return preg_replace_callback(
            self::PATTERN_VARIABLE,
            function ($matches) use ($variables) {
                $expression = trim($matches[1]);
                return $this->evaluateExpression($expression, $variables);
            },
            $content
        );
    }

    /**
     * Évalue une expression (variable avec filtres ou expression avec opérateurs)
     *
     * @param string $expression
     * @param array<string, mixed> $variables
     * @return string
     */
    private function evaluateExpression(string $expression, array $variables): string
    {
        // Détecter si c'est une fonction (pattern plus permissif pour capturer tous les cas)
        if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*|.+?)\((.*?)\)$/', $expression, $funcMatches)) {
            $funcName = $funcMatches[1];

            // Valider le nom de fonction pour la sécurité (seulement lettres, chiffres et underscore)
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $funcName)) {
                // Message générique en production
                throw new VisionException("Invalid function call in template");
            }

            $params = $this->parseFunctionParams($funcMatches[2], $variables);

            if (isset($this->functions[$funcName])) {
                $result = call_user_func_array($this->functions[$funcName], $params);
                // Si SafeString, retourner le contenu brut (déjà rendu/échappé)
                if ($result instanceof SafeString) {
                    return $result->getValue();
                }
                return $this->formatValue($result);
            }

            // Si la fonction n'existe pas, retourner une chaîne vide plutôt que l'expression
            return '';
        }

        // Détecter si c'est une expression avec opérateurs (math, ternaire, comparaisons)
        if ($this->hasExpressionOperators($expression)) {
            $evaluator = new Runtime\ExpressionEvaluator($this->resolver);
            $result = $evaluator->evaluate($expression, $variables);
            return $this->formatValue($result);
        }

        // Parser les filtres (variable|filter1|filter2:param)
        $parts = explode('|', $expression);
        $variablePart = trim(array_shift($parts) ?? '');

        // Obtenir la valeur de la variable ou chaîne littérale
        $isDoubleQuoted = false;
        if (preg_match(self::PATTERN_QUOTED_STRING, $variablePart, $matches)) {
            // C'est une chaîne littérale entre guillemets
            $value = $matches[1];

            // Check if it's a double-quoted string (supports interpolation)
            $isDoubleQuoted = strpos($variablePart, '"') === 0;
            if ($isDoubleQuoted) {
                // Double-quoted: perform interpolation
                $value = $this->interpolateString($value, $variables);
            }
            // Single-quoted: no interpolation
        } elseif (is_numeric($variablePart)) {
            // C'est un nombre
            $value = str_contains($variablePart, '.') ? (float)$variablePart : (int)$variablePart;
        } else {
            // C'est une variable
            $value = $this->getNestedValue($variables, $variablePart);
        }

        // Déterminer si un filtre "safe" a été appliqué (qui ne nécessite pas d'échappement)
        $safeFilters = ['escape', 'json', 'number'];
        $hasSafeFilter = false;

        // Appliquer les filtres
        foreach ($parts as $filterPart) {
            $filterName = trim(explode(':', $filterPart, 2)[0]);
            if (in_array($filterName, $safeFilters, true)) {
                $hasSafeFilter = true;
            }
            $value = $this->applyFilter($filterPart, $value);
        }

        // Échappement automatique si activé et aucun filtre safe n'a été appliqué
        if ($this->autoEscape && is_string($value) && !$hasSafeFilter) {
            // Utiliser l'API centrale pour appliquer le filtre afin d'éviter les erreurs de typage
            $value = $this->applyFilter('escape', $value);
        }

        return $this->formatValue($value);
    }

    /**
     * Détecte si une expression contient des opérateurs (pas des séparateurs de filtres)
     */
    private function hasExpressionOperators(string $expr): bool
    {
        // Opérateurs booléens: &&, || AVANT de vérifier les filtres (qui utilisent |)
        if (preg_match('/(\|\||&&)/', $expr) !== 0) {
            return true;
        }

        // Si c'est un filtre (contient |), ce n'est PAS une expression avec opérateur
        if (strpos($expr, '|') !== false) {
            return false;
        }

        // Opérateurs math: +, -, *, /, %, **
        if (preg_match('/[\+\-\*\/%]|(\*\*)/', $expr) !== 0) {
            return true;
        }

        // Comparaisons: >, <, >=, <=, ==, !=, ===, !==
        if (preg_match('/(===|!==|==|!=|<=|>=|[<>])/', $expr) !== 0) {
            return true;
        }

        // Ternaire: ? et :
        if (strpos($expr, '?') !== false && strpos($expr, ':') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Applique un filtre à une valeur
     *
     * @param string $filterExpression Expression du filtre (ex: "upper", "date:Y-m-d")
     * @param mixed $value
     * @return mixed
     * @throws InvalidFilterException
     */
    private function applyFilter(string $filterExpression, mixed $value): mixed
    {
        return $this->filterManager->apply(trim($filterExpression), $value);
    }


    /**
     * Parse les paramètres d'une fonction
     *
     * @param string $paramsString
     * @param array<string, mixed> $variables
     * @return array
     */
    private function parseFunctionParams(string $paramsString, array $variables): array
    {
        if (empty(trim($paramsString))) {
            return [];
        }

        $params = [];
        $namedParams = [];
        $parts = $this->splitFunctionParams($paramsString);

        foreach ($parts as $part) {
            $part = trim($part);

            // Check if it's a named argument (key=value)
            if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*(.+)$/', $part, $matches)) {
                $key = strtolower($matches[1]); // Named arguments are case-insensitive
                $value = trim($matches[2]);

                // Parse the value
                if (preg_match(self::PATTERN_QUOTED_STRING, $value, $valueMatches)) {
                    $extractedValue = $valueMatches[1];
                    // Check if it's double-quoted (supports interpolation)
                    if (strpos($value, '"') === 0) {
                        $extractedValue = $this->interpolateString($extractedValue, $variables);
                    }
                    $namedParams[$key] = $extractedValue;
                } elseif (is_numeric($value)) {
                    $namedParams[$key] = str_contains($value, '.') ? (float)$value : (int)$value;
                } else {
                    // Variable or expression
                    $namedParams[$key] = $this->getNestedValue($variables, $value);
                }
            } else {
                // Positional argument
                if (preg_match(self::PATTERN_QUOTED_STRING, $part, $matches)) {
                    $extractedValue = $matches[1];
                    // Check if it's double-quoted (supports interpolation)
                    if (strpos($part, '"') === 0) {
                        $extractedValue = $this->interpolateString($extractedValue, $variables);
                    }
                    $params[] = $extractedValue;
                } elseif (is_numeric($part)) {
                    $params[] = str_contains($part, '.') ? (float)$part : (int)$part;
                } else {
                    // Variable
                    $params[] = $this->getNestedValue($variables, $part);
                }
            }
        }

        // Merge positional and named parameters
        // Named parameters go to the end as an associative array
        if (!empty($namedParams)) {
            $params[] = $namedParams;
        }

        return $params;
    }

    private function splitFunctionParams(string $paramsString): array
    {
        // Split by comma, but respect quoted strings and nested parentheses
        $parts = [];
        $current = '';
        $inQuote = null;
        $depth = 0;

        for ($i = 0; $i < strlen($paramsString); $i++) {
            $char = $paramsString[$i];

            // Handle quotes
            if (($char === '"' || $char === "'") && ($i === 0 || $paramsString[$i - 1] !== '\\')) {
                if ($inQuote === null) {
                    $inQuote = $char;
                } elseif ($inQuote === $char) {
                    $inQuote = null;
                }
                $current .= $char;
            }
            // Handle nested structures
            elseif ($char === '(' || $char === '[') {
                $depth++;
                $current .= $char;
            } elseif ($char === ')' || $char === ']') {
                $depth--;
                $current .= $char;
            }
            // Handle separator
            elseif ($char === ',' && $inQuote === null && $depth === 0) {
                if (!empty($current)) {
                    $parts[] = trim($current);
                }
                $current = '';
            } else {
                $current .= $char;
            }
        }

        if (!empty($current)) {
            $parts[] = trim($current);
        }

        return $parts;
    }

    /**
     * Obtient une valeur imbriquée depuis un tableau (ex: "user.name")
     *
     * @param array<string, mixed> $variables
     * @param string $path
     * @return mixed
     */
    private function getNestedValue(array $variables, string $path): mixed
    {
        return $this->resolver->resolve($variables, $path);
    }

    /**
     * Formate une valeur pour l'affichage
     *
     * @param mixed $value
     * @return string
     */
    private function formatValue(mixed $value): string
    {
        return $this->resolver->format($value);
    }

    /**
     * Obtient le chemin complet vers un template
     *
     * @param string $template
     * @return string
     */
    /**
     * Obtient le filtre d'échappement (singleton)
     *
     * @return EscapeFilter
     */
    private function getEscapeFilter(): EscapeFilter
    {
        return $this->filterManager->getEscapeFilter();
    }

    private function getTemplatePath(string $template): string
    {
        // Validation stricte: interdire tout caractère de traversée
        if (preg_match('#\..|[\\/]{2,}|[\x00-\x1f]#', $template)) {
            // Autoriser un seul point pour l'extension, interdire '..' et caractères de contrôle
            if (str_contains($template, '..')) {
                throw new TemplateNotFoundException($template);
            }
        }

        // Cache du base path (rarement change)
        if ($this->cachedRealBasePath === null && $this->templateDir) {
            $this->cachedRealBasePath = realpath($this->templateDir);
            if ($this->cachedRealBasePath === false || !is_dir($this->cachedRealBasePath)) {
                throw new VisionException("Template directory invalid: {$this->templateDir}");
            }
        }

        // Cache hit pour template spécifique (si templateDir est défini)
        if ($this->templateDir && $this->cachedRealBasePath !== null) {
            $cacheKey = md5($template . $this->cachedRealBasePath);
            if (isset($this->templatePathCache[$cacheKey])) {
                $cachedPath = $this->templatePathCache[$cacheKey];
                // Vérifier que le fichier existe toujours
                if (file_exists($cachedPath)) {
                    return $cachedPath;
                }
                // Sinon, invalider le cache
                unset($this->templatePathCache[$cacheKey]);
            }
        }

        // Déterminer les candidats d'extension si aucune extension explicite n'est fournie
        $hasExtension = str_contains($template, '.');
        $candidates = [];
        if ($hasExtension) {
            $candidates[] = $template;
        } else {
            // Priorité: .html.vis (nouvelle extension), .vis, .php, .html
            $candidates[] = $template . '.html.vis';
            $candidates[] = $template . '.vis';
            $candidates[] = $template . '.php';
            $candidates[] = $template . '.html';
        }

        if ($this->templateDir && $this->cachedRealBasePath !== null) {
            foreach ($candidates as $candidate) {
                $fullPath = $this->cachedRealBasePath . DIRECTORY_SEPARATOR . ltrim($candidate, '\\/');
                $realFullPath = realpath($fullPath);

                if ($realFullPath !== false && is_file($realFullPath)) {
                    // Protection stricte: le chemin doit commencer par le base path
                    if (strpos($realFullPath, $this->cachedRealBasePath . DIRECTORY_SEPARATOR) === 0) {
                        // Mettre en cache (limiter la taille pour éviter fuite mémoire)
                        if (count($this->templatePathCache) < self::MAX_TEMPLATE_PATH_CACHE_SIZE) {
                            $cacheKey = md5($template . $this->cachedRealBasePath);
                            $this->templatePathCache[$cacheKey] = $realFullPath;
                        }
                        return $realFullPath;
                    }
                }
            }

            // Aucun candidat trouvé
            throw new TemplateNotFoundException($template);
        }

        // Pas de templateDir: essayer les candidats dans le cwd
        foreach ($candidates as $candidate) {
            $realFullPath = realpath($candidate);
            if ($realFullPath !== false && is_file($realFullPath)) {
                return $realFullPath;
            }
        }

        throw new TemplateNotFoundException($template);
    }

    /**
     * Nettoie le cache des chemins de templates
     * Utile pour invalider le cache après modification de templates
     */
    public function clearTemplatePathCache(): void
    {
        $this->templatePathCache = [];
        $this->cachedRealBasePath = null;
    }

    /**
     * Charge le contenu d'un template par son nom
     * Utilisé par InheritanceResolver pour charger les parents
     * 
     * @param string $templateName Nom du template (ex: "base.html")
     * @return string Contenu du template
     * @throws TemplateNotFoundException Si le template n'existe pas
     */
    private function loadTemplateSource(string $templateName): string
    {
        $path = $this->getTemplatePath($templateName);
        $content = file_get_contents($path);

        if ($content === false) {
            throw new TemplateNotFoundException($templateName);
        }

        return $content;
    }

    /**
     * Obtient l'instance du cache de statistiques de fichiers
     */
    private function getFileStatsCache(): FileStatsCache
    {
        if ($this->fileStatsCache === null) {
            $this->fileStatsCache = new FileStatsCache(5); // TTL de 5 secondes
        }
        return $this->fileStatsCache;
    }

    /**
     * Récupère le contenu depuis le cache si valide
     *
     * @param string $templatePath Chemin complet vers le template
     * @param array<string, mixed> $variables Variables du template
     * @return string|null Contenu en cache ou null si invalide/inexistant
     */
    private function getCachedContent(string $templatePath, array $variables): ?string
    {
        $cacheFile = $this->getCacheFilePath($templatePath, $variables);
        $statsCache = $this->getFileStatsCache();

        if (!$statsCache->exists($cacheFile)) {
            return null;
        }

        $cacheMTime = $statsCache->mtime($cacheFile);
        $now = time();

        // Vérifier le TTL
        if ($cacheMTime === null || ($now - $cacheMTime) >= $this->cacheTTL) {
            @unlink($cacheFile);
            $statsCache->invalidate($cacheFile);
            return null;
        }

        // Vérifier si le template source a changé
        $templateMTime = $statsCache->mtime($templatePath) ?? 0;
        if ($templateMTime > $cacheMTime) {
            @unlink($cacheFile);
            $statsCache->invalidate($cacheFile);
            return null;
        }

        // Lire le cache avec verrouillage en lecture partagée
        $fp = @fopen($cacheFile, 'rb');
        if ($fp === false) {
            return null;
        }

        // FIX: Verrouillage BLOQUANT avec timeout pour éviter race conditions
        $startTime = time();
        $timeout = 5; // 5 secondes max

        while (!flock($fp, LOCK_SH)) {
            if (time() - $startTime > $timeout) {
                fclose($fp);
                return null; // Timeout, régénérer
            }
            usleep(50000); // Attendre 50ms avant de réessayer
        }

        $content = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        return $content !== false ? $content : null;
    }

    /**
     * Sauvegarde le contenu dans le cache
     *
     * @param string $templatePath Chemin complet vers le template
     * @param array<string, mixed> $variables Variables du template
     * @param string $content Contenu rendu à mettre en cache
     * @return void
     */
    private function saveCachedContent(string $templatePath, array $variables, string $content): void
    {
        if ($this->cacheDir === null) {
            return;
        }

        $cacheFile = $this->getCacheFilePath($templatePath, $variables);

        // Créer le dossier si nécessaire
        $cacheDir = dirname($cacheFile);
        if (!is_dir($cacheDir)) {
            if (!mkdir($cacheDir, 0755, true) && !is_dir($cacheDir)) {
                // Échec silencieux pour ne pas bloquer le rendu
                return;
            }
        }

        // Écrire avec verrouillage exclusif
        $fp = @fopen($cacheFile, 'cb');
        if ($fp === false) {
            // Échec silencieux pour ne pas bloquer le rendu
            return;
        }

        // FIX: Verrouillage BLOQUANT avec timeout
        $startTime = time();
        $timeout = 5;

        while (!flock($fp, LOCK_EX)) {
            if (time() - $startTime > $timeout) {
                fclose($fp);
                return; // Timeout, abandonner
            }
            usleep(50000); // Attendre 50ms
        }

        ftruncate($fp, 0);
        fwrite($fp, $content);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    /**
     * Génère le chemin du fichier de cache
     *
     * @param string $templatePath Chemin complet vers le template
     * @param array<string, mixed> $variables Variables du template
     * @return string Chemin complet du fichier de cache
     */
    private function getCacheFilePath(string $templatePath, array $variables): string
    {
        // SÉCURITÉ: Valider les types des variables pour éviter object injection
        $this->validateCacheVariables($variables);

        // Inclure le timestamp du template dans le hash pour invalidation automatique
        $statsCache = $this->getFileStatsCache();
        $templateMTime = $statsCache->mtime($templatePath) ?? 0;

        // Créer un hash basé sur le template et les variables
        // Les variables sont sérialisées pour créer un hash unique
        $hashData = [
            'template' => $templatePath,
            'template_mtime' => $templateMTime,
            'auto_escape' => $this->autoEscape,
            'variables' => $variables,
        ];

        // Utiliser sha256 pour éviter les collisions
        $json = json_encode($hashData, JSON_THROW_ON_ERROR);
        $hash = hash('sha256', $json);
        // Limiter la longueur du hash pour des noms de fichiers plus courts
        $hash = substr($hash, 0, 32);

        // Nettoyer le nom du template pour le nom de fichier
        // Extraire le nom du template sans extension (supporte .html.vis, .vis, .php, .html)
        $templateName = pathinfo($templatePath, PATHINFO_FILENAME);
        $templateName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $templateName);

        $cacheKey = $templateName . '_' . $hash . '.cache';

        return $this->cacheDir . '/' . $cacheKey;
    }

    /**
     * Valide que les variables ne contiennent pas d'objets dangereux
     *
     * @param array<string, mixed> $variables
     * @throws VisionException Si des objets dangereux sont détectés
     */
    private function validateCacheVariables(array $variables): void
    {
        array_walk_recursive($variables, function ($value, $key) {
            if (is_object($value)) {
                // Autoriser seulement certaines classes safe
                $allowedClasses = [\stdClass::class, \DateTime::class, \DateTimeImmutable::class];
                $className = get_class($value);

                if (!in_array($className, $allowedClasses, true)) {
                    throw new VisionException(
                        "Object of class {$className} not allowed in cached variables. Only scalar types and safe objects are permitted."
                    );
                }
            }
        });
    }

    private function parseRange(string $rangeStr, array $variables): array
    {
        // Parse range strings like "1..5", "0..10..2", "start..end", "-2..2"
        $parts = explode('..', $rangeStr);

        if (count($parts) < 2 || count($parts) > 3) {
            return [];
        }

        // Get start value
        $start = $this->resolveRangePart($parts[0], $variables);
        if ($start === null) {
            return [];
        }

        // Get end value
        $end = $this->resolveRangePart($parts[1], $variables);
        if ($end === null) {
            return [];
        }

        // Get step if provided
        $step = 1;
        if (count($parts) === 3) {
            $step = $this->resolveRangePart($parts[2], $variables);
            if ($step === null || $step === 0) {
                return [];
            }
        }

        // Create range array
        if ($step > 0 && $start <= $end) {
            return range($start, $end, $step);
        } elseif ($step < 0 && $start >= $end) {
            return range($start, $end, $step);
        } elseif ($step === 1 && $start <= $end) {
            return range($start, $end);
        }

        return [];
    }

    private function resolveRangePart(string $part, array $variables): ?int
    {
        // Try to parse as integer first
        if (is_numeric($part)) {
            return (int)$part;
        }

        // Try to resolve as variable
        if (isset($variables[$part]) && is_numeric($variables[$part])) {
            return (int)$variables[$part];
        }

        return null;
    }

    private function interpolateString(string $str, array $variables): string
    {
        // Handle escaped interpolation FIRST: \#{...} -> temporarily replace with placeholder
        $escapedParts = [];
        $counter = 0;
        $str = preg_replace_callback(
            '/\\\\#\{([a-zA-Z_][a-zA-Z0-9_\.]*)\}/',
            function ($matches) use (&$escapedParts, &$counter) {
                $key = '__ESCAPED_' . $counter . '__';
                $counter++;
                $escapedParts[$key] = '#{' . $matches[1] . '}';
                return $key;
            },
            $str
        );

        // Handle string interpolation: "Hello #{name}" -> "Hello World"
        // Pattern: #{variableName} or #{object.property} or #{array.0}
        $result = preg_replace_callback(
            '/#\{([a-zA-Z_][a-zA-Z0-9_\.]*)\}/',
            function ($matches) use ($variables) {
                $path = $matches[1];
                $value = $this->getNestedValue($variables, $path);
                // Convert to string, empty string if null/undefined
                return (string)($value ?? '');
            },
            $str
        );

        // Restore escaped interpolations
        foreach ($escapedParts as $key => $val) {
            $result = str_replace($key, $val, $result);
        }

        return $result;
    }

    private function processWhitespaceControl(string $content): string
    {
        // Process whitespace control: {%- strips left, -%} strips right
        // The - marker indicates stripping direction:
        // - {{- : strip whitespace on the LEFT (before the tag)
        // - -}} : strip whitespace on the RIGHT (after the tag)
        // Same for {% and %}

        // Handle {{- : strip trailing whitespace on left, normalize to {{
        // Match: any content followed by spaces/newlines then {{-
        $content = preg_replace_callback(
            '/(.*?)(?:[ \t]*\n[ \t]*|[ \t]+)\{\{-/',
            function ($matches) {
                $before = $matches[1];
                // Keep the content but remove trailing whitespace
                $before = rtrim($before);
                return $before . '{{';
            },
            $content
        );

        // Handle -}} : strip leading whitespace on right, normalize to }}
        // Strip whitespace immediately after the tag (space/newline/tab)
        $content = preg_replace_callback(
            '/-\}\}[ \t]*(?:\n[ \t]*)?/',
            function ($matches) {
                return '}}';
            },
            $content
        );

        // Handle {%- : strip trailing whitespace on left, normalize to {%
        $content = preg_replace_callback(
            '/(.*?)(?:[ \t]*\n[ \t]*|[ \t]+)\{%-/',
            function ($matches) {
                $before = $matches[1];
                // Keep the content but remove trailing whitespace
                $before = rtrim($before);
                return $before . '{%';
            },
            $content
        );

        // Handle -%} : strip leading whitespace on right, normalize to %}
        // Strip whitespace immediately after the tag (space/newline/tab)
        $content = preg_replace_callback(
            '/-%\}[ \t]*(?:\n[ \t]*)?/',
            function ($matches) {
                return '%}';
            },
            $content
        );

        return $content;
    }
}
