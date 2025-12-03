# OPcache Preloading pour PHP Vision

## 📋 Vue d'ensemble

Le script `opcache-preload.php` permet de précharger toutes les classes PHP Vision dans OPcache au démarrage de PHP, améliorant ainsi les performances en production.

## 🚀 Installation

### 1. Vérifier les prérequis

Assurez-vous que OPcache est activé et que le preloading est supporté :

```bash
php -i | grep opcache
```

Vous devez voir :
- `opcache.enable => On`
- `opcache.preload` (doit être configurable)

### 2. Configurer php.ini

Ajoutez les lignes suivantes dans votre `php.ini` :

```ini
; Activer OPcache
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000

; Activer le preloading
opcache.preload=/chemin/vers/php-vision/opcache-preload.php
opcache.preload_user=www-data
```

**Important** : Remplacez `/chemin/vers/php-vision/` par le chemin absolu vers votre installation de php-vision.

### 3. Redémarrer PHP-FPM / Apache

```bash
# PHP-FPM
sudo systemctl restart php-fpm

# Apache
sudo systemctl restart apache2

# Nginx + PHP-FPM
sudo systemctl restart php-fpm
```

## ✅ Vérification

Pour vérifier que le preloading fonctionne :

```bash
php -r "var_dump(opcache_get_status()['preload_statistics']);"
```

Vous devriez voir des statistiques sur les fichiers préchargés.

## 📊 Bénéfices

- **10-15%** de réduction du temps de chargement des classes
- Classes toujours en mémoire (pas de chargement à la demande)
- Amélioration des performances globales en production

## ⚠️ Notes importantes

1. **Sécurité** : Le script `opcache-preload.php` doit être accessible uniquement par PHP, pas par le web
2. **Permissions** : `opcache.preload_user` doit avoir les permissions de lecture sur les fichiers
3. **Développement** : Le preloading peut être désactivé en développement pour faciliter le debugging
4. **Mise à jour** : Après chaque mise à jour de php-vision, redémarrer PHP-FPM/Apache pour recharger les classes

## 🔧 Désactiver le preloading

Pour désactiver temporairement le preloading, commentez la ligne dans `php.ini` :

```ini
; opcache.preload=/chemin/vers/php-vision/opcache-preload.php
```

Puis redémarrez PHP-FPM/Apache.

## 📚 Documentation

- [PHP OPcache Preloading](https://www.php.net/manual/en/opcache.preloading.php)
- [OPcache Configuration](https://www.php.net/manual/en/opcache.configuration.php)
