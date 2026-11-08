<p align="center"><a href="README.md">English</a> · <b>Français</b> · <a href="README.es.md">Español</a></p>

# Benchmarks d'Éclair CMS

Des tests de vitesse ouverts et reproductibles d'[Éclair CMS](https://github.com/Jacckisback/eclair-cms) face à WordPress, sur le même serveur, avec le même contenu. Un seul script installe tout et mesure tout. **Tout le monde peut le lancer et vérifier les chiffres.**

> Éclair est créé par l'auteur de ce dépôt. C'est justement pour ça que la méthode, les scripts et les données brutes sont publics : ne nous crois pas sur parole, lance-le toi-même. Corrections et nouveaux CMS bienvenus (voir [Contribuer](#contribuer)).

## Derniers résultats

Mesure du 8 novembre 2026 · Éclair 2.0.0 · WordPress 7.1.4 · machine cloud 2 vCPU, Ubuntu 24.04, Apache 2.4 + PHP 8.3 FPM (OPcache actif), MariaDB 10.11 · [rapport complet et données brutes](results/2026-11-08-cloud-2vcpu/)

**Page article**

| | Temps de réponse (TTFB médian) | Page construite sans cache | Débit | Poids de la page (gzip) |
|---|---:|---:|---:|---:|
| **Éclair 2.0.0** | **0,83 ms** | **2,50 ms** | **2 183 req/s** | **8,3 Ko** |
| WordPress 7.1.4 + Cache Enabler | 2,14 ms | 65,62 ms | 1 154 req/s | 18,3 Ko |
| WordPress 7.1.4 (sans extension) | 58,07 ms | 59,41 ms | 34 req/s | 18,2 Ko |

En résumé, sur cette machine :

- **Face à WordPress avec une extension de cache** (la façon dont tournent la plupart des sites WordPress) : Éclair répond environ **2× plus vite**, sert environ **2× plus de visiteurs par seconde** et envoie une page environ **2× plus légère**.
- **Quand la page n'est pas en cache** (première visite après une modification, ou site sans extension de cache) : Éclair construit la page en moins de **3 ms**, WordPress en 60 ms ou plus : plus de **20× plus rapide**.
- **Installation** : Éclair pèse **2,5 Mo**, sans base de données. WordPress pèse **150 Mo**, plus une base MariaDB/MySQL.
- **Polices web** : Éclair utilise les polices du système (rien à télécharger). Le thème WordPress par défaut déclare 2 fichiers de polices (156 Ko) ; le navigateur télécharge ceux que le texte utilise. Ils ne sont pas comptés dans le poids ci-dessus.

La page d'accueil donne des résultats proches : voir le [rapport complet](results/2026-11-08-cloud-2vcpu/).

## Le lancer toi-même

Il faut un **serveur Ubuntu 24.04 neuf** (un petit VPS suffit, 2 vCPU / 2 Go de RAM). Pas de serveur de production : le script installe et reconfigure Apache, PHP-FPM et MariaDB.

```bash
git clone https://github.com/Jacckisback/eclair-benchmarks
cd eclair-benchmarks
sudo ./run.sh
```

Compte environ 10 minutes. Le rapport s'affiche à la fin et il est enregistré dans `results/<date>-<nom du serveur>/`, avec les données JSON brutes. Les options (version d'Éclair, de WordPress, durée des tests) sont décrites dans le [README en anglais](README.md#run-it-yourself).

## Méthode

- **Même serveur, mêmes réglages** : les trois sites tournent sur la même machine, le même Apache et le même PHP-FPM (OPcache actif, réglages Ubuntu par défaut). Chaque site est mesuré seul, l'un après l'autre.
- **Les trois sites** : Éclair (archive officielle non modifiée, installée avec son propre assistant), WordPress sans extension (version officielle, thème par défaut), et WordPress + [Cache Enabler](https://github.com/keycdn/cache-enabler), une extension de cache gratuite et populaire, réglages par défaut. C'est la comparaison honnête : presque personne ne fait tourner WordPress sans cache.
- **Même contenu** : les mêmes 20 articles dans chaque site ([content/articles.json](content/articles.json)), contenus d'exemple supprimés.
- **Mesures** : temps de réponse (TTFB, 300 requêtes), page construite cache vidé (30 requêtes), débit et latence sous charge ([wrk](https://github.com/wg/wrk), 20 connexions pendant 20 s), poids de la page (HTML + CSS + JS + images, gzip), taille sur le disque et nombre de fichiers PHP. Chaque requête envoie les en-têtes d'un vrai navigateur Chrome.

## Limites

On préfère les dire nous-mêmes :

- **Une seule machine, petite et partagée.** Le générateur de charge tourne sur la même machine que les sites : les débits sont plus bas que sur un vrai serveur. Compare les sites entre eux, pas avec d'autres benchmarks.
- **Réglages par défaut.** PHP-FPM garde les réglages Ubuntu (5 processus). Un réglage fin aiderait WordPress sans cache, et Éclair aussi.
- **Pas le même design.** Chaque CMS utilise son thème par défaut : le poids compare deux installations par défaut, pas deux pages identiques.
- **Pas encore de mesures navigateur** (Lighthouse, Core Web Vitals). Contributions bienvenues.
- **Seulement WordPress pour l'instant.** Ghost, Grav, Kirby, Statamic, Drupal… sont prévus.

## Contribuer

- Une erreur ou un réglage injuste ? Ouvre une issue : on corrige et on publie une nouvelle mesure, même si elle est moins bonne pour Éclair.
- Un autre CMS à ajouter ? Ouvre une pull request avec son script d'installation dans `lib/`, en suivant les mêmes règles.
- Tu as lancé le benchmark sur ton serveur ? Ouvre une pull request avec ton dossier `results/`.

## Licence

MIT pour les scripts de ce dépôt. Éclair CMS est sous AGPL-3.0, WordPress et Cache Enabler sous GPL-2.0 ou ultérieure.
