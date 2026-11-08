<p align="center"><a href="README.md">English</a> · <b>Français</b> · <a href="README.es.md">Español</a></p>

<p align="center">
  <img src="results/2026-11-08-cloud-2vcpu/charts/summary.svg" alt="Éclair CMS face à WordPress : premier octet 2,3× plus rapide que WordPress avec cache, page construite 15,2× plus vite, installation 60,1× plus petite" width="100%">
</p>

<p align="center">
  <a href="LICENSE"><img src="https://img.shields.io/badge/scripts-MIT-22c55e?style=flat-square" alt="MIT"></a>
  <img src="https://img.shields.io/badge/Éclair-2.0.0-6250d6?style=flat-square" alt="Éclair 2.0.0">
  <img src="https://img.shields.io/badge/WordPress-7.1.4-21759b?style=flat-square" alt="WordPress 7.1.4">
  <img src="https://img.shields.io/badge/PHP-8.3_FPM_+_OPcache-777bb4?style=flat-square" alt="PHP 8.3">
  <img src="https://img.shields.io/badge/reproductible-une_commande-0b0b0b?style=flat-square" alt="Reproductible en une commande">
</p>

# Benchmarks d'Éclair CMS

**Des tests de performance ouverts et reproductibles d'[Éclair CMS](https://github.com/Jacckisback/eclair-cms) face à WordPress.** Même serveur, même PHP, même contenu, versions officielles, réglages par défaut. Un seul script installe les trois sites, les mesure et dessine chaque graphique de cette page à partir des données brutes.

> **En toute transparence.** Éclair est développé par l'auteur de ce dépôt. C'est justement pour ça que tout est public : la méthode, les scripts, le contenu, le JSON brut de chaque mesure. Ne crois pas ces chiffres sur parole : [lance le test toi-même](#le-lancer-toi-même) sur ton serveur, en 15 minutes environ. Si tu trouves un réglage qui avantage Éclair, [ouvre une issue](https://github.com/Jacckisback/eclair-benchmarks/issues) : il sera corrigé et une nouvelle mesure publiée, même si le résultat est moins bon pour Éclair.

Les graphiques sont en anglais (une seule version pour les trois langues) ; les chiffres sont repris dans les tableaux ci-dessous.

## Sommaire

- [Les résultats en bref](#les-résultats-en-bref)
- [Temps de réponse](#1-temps-de-réponse)
- [Construire une page sans cache](#2-construire-une-page-sans-cache)
- [Débit et montée en charge](#3-débit-et-montée-en-charge)
- [Ce qui se passe dans une requête](#ce-qui-se-passe-dans-une-requête)
- [Poids des pages et taille de l'installation](#4-poids-des-pages-et-taille-de-linstallation)
- [Ce que ça change concrètement](#ce-que-ça-change-concrètement)
- [Comment c'est mesuré](#comment-cest-mesuré)
- [Limites](#limites)
- [Le lancer toi-même](#le-lancer-toi-même)
- Documentation détaillée (en anglais) : [FAQ pour les pros de WordPress](docs/FAQ.md) · [Méthodologie](docs/METHODOLOGY.md) · [Architecture](docs/ARCHITECTURE.md)

## Les résultats en bref

Mesure du **8 novembre 2026** · Éclair 2.0.0 · WordPress 7.1.4 (thème par défaut Twenty Twenty-Five) · Cache Enabler 1.8.16 · machine cloud 2 vCPU, Ubuntu 24.04, Apache 2.4 (mpm_event) + PHP 8.3 FPM avec OPcache, MariaDB 10.11 · [rapport complet et données brutes](results/2026-11-08-cloud-2vcpu/)

**Page article**

| | Temps de réponse<br><sub>TTFB médian</sub> | Page construite sans cache<br><sub>cache vidé</sub> | Débit<br><sub>20 connexions</sub> | Fichiers PHP<br><sub>par page vue</sub> | Requêtes SQL<br><sub>par page vue</sub> | Poids de la page<br><sub>gzip</sub> |
|---|---:|---:|---:|---:|---:|---:|
| **Éclair 2.0.0** | **0,81 ms** | **3,91 ms** | **2 128 req/s** | **1** | **0** | **8,3 Ko** |
| WordPress 7.1.4 + Cache Enabler | 1,84 ms | 63,42 ms | 1 192 req/s | 27 | 0 | 18,3 Ko |
| WordPress 7.1.4, sans extension | 57,83 ms | 59,27 ms | 36 req/s | 496 | 32 | 18,2 Ko |

**Page d'accueil (liste de 10 articles)**

| | Temps de réponse | Page construite sans cache | Débit | Fichiers PHP | Requêtes SQL | Poids de la page |
|---|---:|---:|---:|---:|---:|---:|
| **Éclair 2.0.0** | **1,02 ms** | **2,58 ms** | **2 273 req/s** | **1** | **0** | **7,6 Ko** |
| WordPress 7.1.4 + Cache Enabler | 1,83 ms | 61,57 ms | 1 220 req/s | 27 | 0 | 14,5 Ko |
| WordPress 7.1.4, sans extension | 61,73 ms | 60,84 ms | 33 req/s | 494 | 25 | 14,4 Ko |

**En une phrase :** face à WordPress *avec* un cache, Éclair répond environ **2× plus vite** et sert environ **2× plus de visiteurs** sur le même serveur ; quand aucun cache ne peut aider, il construit une page **15 à 24× plus vite** ; et il s'installe en **2,5 Mo sans base de données**, contre 150 Mo plus MySQL.

## 1. Temps de réponse

<img src="results/2026-11-08-cloud-2vcpu/charts/ttfb.svg" alt="Temps jusqu'au premier octet, page article : Éclair 0,81 ms, WordPress + Cache Enabler 1,84 ms, WordPress 57,83 ms" width="100%">

Temps jusqu'au premier octet (TTFB), médiane de 300 requêtes après 50 requêtes de chauffe. C'est ce qu'attend un visiteur avant que le navigateur puisse commencer à afficher la page (réseau exclu : mesuré sur le serveur lui-même).

- **Éclair 0,81 ms** (p95 0,95 ms) : la page vient du cache intégré d'Éclair, sans rien à configurer.
- **WordPress + Cache Enabler 1,84 ms** (p95 2,69 ms) : servie aussi depuis un fichier, mais après la séquence de démarrage de WordPress (voir [plus bas](#ce-qui-se-passe-dans-une-requête)).
- **WordPress sans cache 57,83 ms** (p95 79,47 ms) : toute la pile WordPress à chaque visite.

## 2. Construire une page sans cache

<img src="results/2026-11-08-cloud-2vcpu/charts/build.svg" alt="Page construite sans cache, page article : Éclair 3,91 ms, WordPress + Cache Enabler 63,42 ms, WordPress 59,27 ms" width="100%">

Le cache est vidé avant chacune des 30 requêtes. C'est la vitesse du moteur du CMS, et c'est ce que reçoivent les visiteurs **à la première visite après chaque modification**, et sur les pages **impossibles à mettre en cache** (aperçus, recherche, utilisateurs connectés). Ici, une extension de cache ne peut pas aider WordPress : l'écart est de **15× sur la page article et 24× sur l'accueil**.

## 3. Débit et montée en charge

<img src="results/2026-11-08-cloud-2vcpu/charts/throughput.svg" alt="Débit, page article : Éclair 2 128 req/s, WordPress + Cache Enabler 1 192 req/s, WordPress 36 req/s" width="100%">

<img src="results/2026-11-08-cloud-2vcpu/charts/scaling.svg" alt="Débit de 1 à 100 connexions : Éclair environ 2 100 req/s, WordPress + Cache Enabler environ 1 150 req/s, WordPress environ 30 req/s" width="100%">

| Page article | 1 connexion | 10 | 50 | 100 |
|---|---:|---:|---:|---:|
| **Éclair** | 1 068 req/s · p99 1,6 ms | 2 167 req/s · p99 12,2 ms | 2 095 req/s · p99 34,0 ms | 2 084 req/s · p99 284,7 ms |
| WordPress + Cache Enabler | 607 req/s · p99 2,7 ms | 1 233 req/s · p99 15,9 ms | 1 171 req/s · p99 57,4 ms | 1 149 req/s · p99 107,9 ms |
| WordPress | 17 req/s · p99 79,9 ms | 35 req/s · p99 368 ms | 35 req/s · p99 1 550 ms | 30 req/s · p99 1 990 ms |

**Lecture.** Le débit reste stable de 10 à 100 connexions : sur cette machine 2 vCPU, le processeur (partagé avec le générateur de charge) est la limite dès 10 connexions. Éclair garde environ 1,8× le débit de WordPress + cache à chaque niveau, et environ 60× celui de WordPress sans cache.

**Un résultat publié tel quel :** à 100 connexions, la latence au 99e centile d'Éclair (285 ms) est plus haute que celle de WordPress + cache (108 ms), alors que sa médiane reste plus basse. Avec le pool PHP-FPM par défaut (5 processus) et le générateur de charge sur les mêmes 2 vCPU, la file d'attente devant PHP est longue ; on n'a pas encore trouvé la cause de cette traîne et on l'étudiera sur une machine plus grosse. C'est noté dans les [limites](#limites).

## Ce qui se passe dans une requête

<img src="results/2026-11-08-cloud-2vcpu/charts/request-path.svg" alt="Chemin d'une requête : Éclair en cache 1 fichier PHP ; Éclair construit 30 fichiers ; WordPress + Cache Enabler en cache 27 fichiers ; WordPress construit 496 fichiers et 32 requêtes SQL" width="100%">

<img src="results/2026-11-08-cloud-2vcpu/charts/php-files.svg" alt="Fichiers PHP chargés pour servir une page : Éclair 1, WordPress + Cache Enabler 27, WordPress 496" width="100%">

<img src="results/2026-11-08-cloud-2vcpu/charts/memory.svg" alt="Mémoire PHP par page : Éclair 0,37 Mo, WordPress + Cache Enabler 0,58 Mo, WordPress 6,14 Mo" width="100%">

Mesuré lors d'une passe de profilage séparée (un `auto_prepend_file` qui enregistre `get_included_files()`, `memory_get_peak_usage()` et `$wpdb->num_queries` en fin de requête), retirée avant toute mesure de temps.

**Pourquoi Éclair répond avec un seul fichier PHP.** Son `index.php` regarde le cache *avant même que l'application existe* : il lit quelques octets de réglages, ouvre `cache/pages/<md5(chemin)>`, vérifie la génération du cache, envoie les en-têtes enregistrés avec un `ETag` (et répond `304 Not Modified` quand c'est possible), transmet le HTML et s'arrête. Aucune classe, aucune configuration, aucune base de données. L'invalidation est atomique : chaque modification publie une nouvelle génération de cache *avant* de supprimer les anciennes pages, donc une page périmée n'est jamais servie, et les articles programmés rafraîchissent le cache au moment de leur parution.

**Pourquoi WordPress + cache charge 27 fichiers.** `advanced-cache.php` est chargé par `wp-settings.php`, après `wp-load.php` et `wp-config.php`. C'est rapide, mais chaque requête paie cette séquence de démarrage.

**Pourquoi WordPress sans cache charge ~500 fichiers et fait ~30 requêtes SQL.** Le cœur, les options chargées automatiquement, la requête principale, la hiérarchie des modèles et le thème en blocs qui construit ses gabarits.

Explication détaillée avec références au code : [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

## 4. Poids des pages et taille de l'installation

<img src="results/2026-11-08-cloud-2vcpu/charts/weight.svg" alt="Poids de la page article : Éclair 8,3 Ko en 2 fichiers, WordPress 18,2 Ko en 4 fichiers" width="100%">

<img src="results/2026-11-08-cloud-2vcpu/charts/disk.svg" alt="Taille de l'installation : Éclair 2,5 Mo, WordPress 150,2 Mo" width="100%">

- **Le poids de la page** compte le HTML plus chaque feuille de style, script et image qu'il appelle, compressés en gzip comme servis. **Les polices web sont listées à part et non comptées**, car le navigateur ne télécharge que celles que le texte utilise : Éclair utilise les polices du système (rien à télécharger), le thème WordPress par défaut déclare 2 fichiers de polices (156 Ko).
- **La taille de l'installation** compte le code et le contenu sur le disque. Éclair : 2,5 Mo, 155 fichiers PHP, sans base de données. WordPress : 150 Mo, environ 1 900 fichiers PHP, plus un serveur MySQL/MariaDB à faire tourner, sauvegarder et sécuriser.

## Ce que ça change concrètement

| Pour… | Ce que ces chiffres changent |
|---|---|
| **Les propriétaires de sites** | Des pages qui s'affichent plus tôt à chaque visite, y compris juste après une publication. Aucune extension de cache à choisir, régler ou dépanner. |
| **Les agences et freelances** | Plus de sites par serveur, aucune base de données à maintenir par client, des mises à jour qui ne peuvent pas casser une pile d'extensions. |
| **Les hébergeurs** | Environ 2× plus de visiteurs sur le même processeur pour les pages en cache, 15 à 24× pour les pages hors cache, et environ 10× moins de mémoire PHP par page construite (0,55 Mo contre 6,1 Mo). |
| **Le SEO** | Le temps de réponse du serveur (TTFB) est le début de chaque chargement de page, et une page légère aide les Core Web Vitals. Éclair part avec les deux. |

Ce que ce benchmark **ne dit pas** : qu'Éclair peut remplacer n'importe quel site WordPress. WordPress a un énorme écosystème d'extensions, le multisite et vingt ans de maturité. Éclair est conçu pour les sites de contenu (blogs, sites de petites entreprises, sites vitrines), qui sont la majorité des installations WordPress.

## Comment c'est mesuré

| | |
|---|---|
| **Sites** | Éclair (archive officielle, SHA-256 enregistré), WordPress (dépôt officiel de build, tag de version, thème par défaut), WordPress + [Cache Enabler](https://github.com/keycdn/cache-enabler) (réglages par défaut) |
| **Contenu** | Les mêmes 20 articles sur chaque site ([content/articles.json](content/articles.json)) ; contenus d'exemple supprimés ; 10 articles par page de liste sur les deux CMS |
| **Serveur** | Un seul Apache 2.4 (mpm_event, `.htaccess` actifs), un seul pool PHP 8.3 FPM avec OPcache, gzip pour tous, réglages Ubuntu par défaut |
| **Requêtes** | En-têtes d'un vrai Chrome à chaque requête (certains caches ignorent les requêtes qui ne demandent pas de HTML) |
| **Temps de réponse** | TTFB `curl`, 50 requêtes de chauffe + 300 requêtes, médiane et p95 |
| **Sans cache** | Cache vidé avant chacune des 30 requêtes |
| **Charge** | [wrk](https://github.com/wg/wrk), 20 connexions × 20 s par page ; montée en charge à 1, 10, 50, 100 connexions × 10 s |
| **Dans une requête** | Passe de profilage séparée avec `auto_prepend_file`, retirée avant les mesures de temps |
| **Ordre** | Un site à la fois, les autres au repos |

Chaque détail et la raison de chaque choix : [docs/METHODOLOGY.md](docs/METHODOLOGY.md).

## Limites

On préfère les dire nous-mêmes :

- **Petite machine partagée.** 2 vCPU, avec le générateur de charge sur la même machine. Les chiffres absolus seront plus hauts sur un vrai serveur ; compare les écarts.
- **Réglages par défaut partout.** PHP-FPM garde les 5 processus d'Ubuntu. Un réglage fin aiderait WordPress sans cache, et Éclair aussi.
- **Latence de traîne à 100 connexions.** Le p99 d'Éclair est plus haut que celui de WordPress + cache à ce niveau dans cette mesure (voir [montée en charge](#3-débit-et-montée-en-charge)). En cours d'analyse.
- **Designs par défaut différents.** Le poids compare deux installations par défaut, pas deux pages identiques.
- **Pas encore de mesures navigateur.** Lighthouse et Core Web Vitals demandent un vrai navigateur. Prévu.
- **20 articles.** Les gros sites (des milliers d'articles) ne sont pas encore testés. Prévu.
- **Apache + PHP-FPM uniquement.** Nginx, LiteSpeed et les caches hors PHP (Varnish, cache FastCGI de Nginx, CDN) ne sont pas testés ; voir la [FAQ](docs/FAQ.md).

## Le lancer toi-même

Il faut un **serveur Ubuntu 24.04 neuf** (un petit VPS suffit : 2 vCPU, 2 Go de RAM). Pas de serveur de production : le script installe et reconfigure Apache, PHP-FPM et MariaDB.

```bash
git clone https://github.com/Jacckisback/eclair-benchmarks
cd eclair-benchmarks
sudo ./run.sh
```

Environ 15 minutes. Le rapport s'affiche à la fin et il est enregistré, avec le JSON brut et les graphiques, dans `results/<date>-<nom du serveur>/`. Les options (version d'Éclair et de WordPress, durée des tests, niveaux de connexions) sont décrites dans le [README en anglais](README.md#run-it-yourself).

## Feuille de route

- [ ] Lighthouse et Core Web Vitals (vrai navigateur)
- [ ] Gros sites : 1 000 et 10 000 articles
- [ ] Mesures sur un serveur dédié, avec le générateur de charge sur une autre machine
- [ ] Nginx + PHP-FPM, LiteSpeed
- [ ] D'autres CMS : Ghost, Grav, Kirby, Statamic, Drupal
- [ ] D'autres configurations WordPress : WP Rocket, LiteSpeed Cache, cache FastCGI de Nginx

## Contribuer

- **Un réglage injuste ou une erreur ?** [Ouvre une issue](https://github.com/Jacckisback/eclair-benchmarks/issues). Elle sera corrigée et une nouvelle mesure publiée.
- **Un autre CMS ou une autre configuration WordPress ?** Ajoute son script d'installation dans `lib/` en suivant les mêmes règles (version officielle, thème par défaut, mêmes 20 articles, même serveur) et ouvre une pull request.
- **Tu l'as lancé sur ton serveur ?** Ouvre une pull request avec ton dossier `results/` : des résultats sur des machines différentes renforcent la comparaison.

## Licence

MIT pour les scripts de ce dépôt. Éclair CMS est sous AGPL-3.0 ; WordPress et Cache Enabler sous GPL-2.0 ou ultérieure.
