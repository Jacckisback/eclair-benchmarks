<p align="center"><a href="README.md">English</a> · <a href="README.fr.md">Français</a> · <b>Español</b></p>

<p align="center">
  <img src="results/2026-11-08-cloud-2vcpu/charts/summary.svg" alt="Éclair CMS frente a WordPress: primer byte 2,3 veces más rápido que WordPress con caché, página generada 15,2 veces más rápido, instalación 60,1 veces más pequeña" width="100%">
</p>

<p align="center">
  <a href="LICENSE"><img src="https://img.shields.io/badge/scripts-MIT-22c55e?style=flat-square" alt="MIT"></a>
  <img src="https://img.shields.io/badge/Éclair-2.0.0-6250d6?style=flat-square" alt="Éclair 2.0.0">
  <img src="https://img.shields.io/badge/WordPress-7.1.4-21759b?style=flat-square" alt="WordPress 7.1.4">
  <img src="https://img.shields.io/badge/PHP-8.3_FPM_+_OPcache-777bb4?style=flat-square" alt="PHP 8.3">
  <img src="https://img.shields.io/badge/reproducible-un_comando-0b0b0b?style=flat-square" alt="Reproducible con un comando">
</p>

# Benchmarks de Éclair CMS

**Pruebas de rendimiento abiertas y reproducibles de [Éclair CMS](https://github.com/Jacckisback/eclair-cms) frente a WordPress.** Mismo servidor, mismo PHP, mismo contenido, versiones oficiales, ajustes por defecto. Un solo script instala los tres sitios, los mide y dibuja cada gráfico de esta página a partir de los datos brutos.

> **Transparencia total.** Éclair lo desarrolla el autor de este repositorio. Precisamente por eso todo es público: el método, los scripts, el contenido y el JSON bruto de cada medición. No te fíes de estas cifras: [ejecuta la prueba tú mismo](#ejecútalo-tú-mismo) en tu servidor, en unos 15 minutos. Si encuentras un ajuste que favorece a Éclair, [abre un issue](https://github.com/Jacckisback/eclair-benchmarks/issues): se corregirá y se publicará una nueva medición, aunque el resultado sea peor para Éclair.

Los gráficos están en inglés (una sola versión para los tres idiomas); las cifras aparecen en las tablas.

## Índice

- [Resultados en resumen](#resultados-en-resumen)
- [Tiempo de respuesta](#1-tiempo-de-respuesta)
- [Generar una página sin caché](#2-generar-una-página-sin-caché)
- [Rendimiento y escalabilidad](#3-rendimiento-y-escalabilidad)
- [Qué ocurre dentro de una petición](#qué-ocurre-dentro-de-una-petición)
- [Peso de las páginas y tamaño de la instalación](#4-peso-de-las-páginas-y-tamaño-de-la-instalación)
- [Qué cambia en la práctica](#qué-cambia-en-la-práctica)
- [Cómo se mide](#cómo-se-mide)
- [Límites](#límites)
- [Ejecútalo tú mismo](#ejecútalo-tú-mismo)
- Documentación detallada (en inglés): [FAQ para profesionales de WordPress](docs/FAQ.md) · [Metodología](docs/METHODOLOGY.md) · [Arquitectura](docs/ARCHITECTURE.md)

## Resultados en resumen

Medición del **8 de noviembre de 2026** · Éclair 2.0.0 · WordPress 7.1.4 (tema por defecto Twenty Twenty-Five) · Cache Enabler 1.8.16 · máquina cloud de 2 vCPU, Ubuntu 24.04, Apache 2.4 (mpm_event) + PHP 8.3 FPM con OPcache, MariaDB 10.11 · [informe completo y datos brutos](results/2026-11-08-cloud-2vcpu/)

**Página de artículo**

| | Tiempo de respuesta<br><sub>TTFB mediano</sub> | Página generada sin caché<br><sub>caché vaciada</sub> | Rendimiento<br><sub>20 conexiones</sub> | Archivos PHP<br><sub>por visita</sub> | Consultas SQL<br><sub>por visita</sub> | Peso de la página<br><sub>gzip</sub> |
|---|---:|---:|---:|---:|---:|---:|
| **Éclair 2.0.0** | **0,81 ms** | **3,91 ms** | **2128 req/s** | **1** | **0** | **8,3 KB** |
| WordPress 7.1.4 + Cache Enabler | 1,84 ms | 63,42 ms | 1192 req/s | 27 | 0 | 18,3 KB |
| WordPress 7.1.4, sin plugin | 57,83 ms | 59,27 ms | 36 req/s | 496 | 32 | 18,2 KB |

**Página de inicio (lista de 10 artículos)**

| | Tiempo de respuesta | Página generada sin caché | Rendimiento | Archivos PHP | Consultas SQL | Peso de la página |
|---|---:|---:|---:|---:|---:|---:|
| **Éclair 2.0.0** | **1,02 ms** | **2,58 ms** | **2273 req/s** | **1** | **0** | **7,6 KB** |
| WordPress 7.1.4 + Cache Enabler | 1,83 ms | 61,57 ms | 1220 req/s | 27 | 0 | 14,5 KB |
| WordPress 7.1.4, sin plugin | 61,73 ms | 60,84 ms | 33 req/s | 494 | 25 | 14,4 KB |

**En una frase:** frente a WordPress *con* caché, Éclair responde unas **2 veces más rápido** y atiende unas **2 veces más visitas** en el mismo servidor; cuando ninguna caché puede ayudar, genera una página **entre 15 y 24 veces más rápido**; y se instala en **2,5 MB sin base de datos**, frente a 150 MB más MySQL.

## 1. Tiempo de respuesta

<img src="results/2026-11-08-cloud-2vcpu/charts/ttfb.svg" alt="Tiempo hasta el primer byte, página de artículo: Éclair 0,81 ms, WordPress + Cache Enabler 1,84 ms, WordPress 57,83 ms" width="100%">

Tiempo hasta el primer byte (TTFB), mediana de 300 peticiones tras 50 de calentamiento. Es lo que espera un visitante antes de que el navegador pueda empezar a mostrar la página (sin red: medido en el propio servidor).

- **Éclair 0,81 ms** (p95 0,95 ms): la página sale de la caché integrada de Éclair, sin nada que configurar.
- **WordPress + Cache Enabler 1,84 ms** (p95 2,69 ms): también se sirve desde un archivo, pero después de la secuencia de arranque de WordPress (ver [más abajo](#qué-ocurre-dentro-de-una-petición)).
- **WordPress sin caché 57,83 ms** (p95 79,47 ms): toda la pila de WordPress en cada visita.

## 2. Generar una página sin caché

<img src="results/2026-11-08-cloud-2vcpu/charts/build.svg" alt="Página generada sin caché, página de artículo: Éclair 3,91 ms, WordPress + Cache Enabler 63,42 ms, WordPress 59,27 ms" width="100%">

La caché se vacía antes de cada una de las 30 peticiones. Es la velocidad del motor del CMS, y es lo que reciben los visitantes **en la primera visita tras cada edición** y en las páginas que **no se pueden cachear** (vistas previas, búsqueda, usuarios conectados). Aquí un plugin de caché no puede ayudar a WordPress: la diferencia es de **15 veces en la página de artículo y 24 veces en la de inicio**.

## 3. Rendimiento y escalabilidad

<img src="results/2026-11-08-cloud-2vcpu/charts/throughput.svg" alt="Rendimiento, página de artículo: Éclair 2128 req/s, WordPress + Cache Enabler 1192 req/s, WordPress 36 req/s" width="100%">

<img src="results/2026-11-08-cloud-2vcpu/charts/scaling.svg" alt="Rendimiento de 1 a 100 conexiones: Éclair unas 2100 req/s, WordPress + Cache Enabler unas 1150 req/s, WordPress unas 30 req/s" width="100%">

| Página de artículo | 1 conexión | 10 | 50 | 100 |
|---|---:|---:|---:|---:|
| **Éclair** | 1068 req/s · p99 1,6 ms | 2167 req/s · p99 12,2 ms | 2095 req/s · p99 34,0 ms | 2084 req/s · p99 284,7 ms |
| WordPress + Cache Enabler | 607 req/s · p99 2,7 ms | 1233 req/s · p99 15,9 ms | 1171 req/s · p99 57,4 ms | 1149 req/s · p99 107,9 ms |
| WordPress | 17 req/s · p99 79,9 ms | 35 req/s · p99 368 ms | 35 req/s · p99 1550 ms | 30 req/s · p99 1990 ms |

**Lectura.** El rendimiento se mantiene estable de 10 a 100 conexiones: en esta máquina de 2 vCPU, el procesador (compartido con el generador de carga) es el límite a partir de 10 conexiones. Éclair mantiene unas 1,8 veces el rendimiento de WordPress + caché en cada nivel, y unas 60 veces el de WordPress sin caché.

**Un resultado publicado tal cual:** con 100 conexiones, la latencia del percentil 99 de Éclair (285 ms) es más alta que la de WordPress + caché (108 ms), aunque su mediana sigue siendo menor. Con el pool de PHP-FPM por defecto (5 procesos) y el generador de carga en los mismos 2 vCPU, la cola delante de PHP es larga; todavía no hemos encontrado la causa de esta cola y la estudiaremos en una máquina más grande. Figura en los [límites](#límites).

## Qué ocurre dentro de una petición

<img src="results/2026-11-08-cloud-2vcpu/charts/request-path.svg" alt="Recorrido de una petición: Éclair en caché 1 archivo PHP; Éclair generada 30 archivos; WordPress + Cache Enabler en caché 27 archivos; WordPress generada 496 archivos y 32 consultas SQL" width="100%">

<img src="results/2026-11-08-cloud-2vcpu/charts/php-files.svg" alt="Archivos PHP cargados para servir una página: Éclair 1, WordPress + Cache Enabler 27, WordPress 496" width="100%">

<img src="results/2026-11-08-cloud-2vcpu/charts/memory.svg" alt="Memoria PHP por página: Éclair 0,37 MB, WordPress + Cache Enabler 0,58 MB, WordPress 6,14 MB" width="100%">

Medido en una pasada de perfilado separada (un `auto_prepend_file` que registra `get_included_files()`, `memory_get_peak_usage()` y `$wpdb->num_queries` al final de la petición), retirada antes de cualquier medición de tiempo.

**Por qué Éclair responde con un solo archivo PHP.** Su `index.php` consulta la caché *antes de que exista la aplicación*: lee unos pocos bytes de ajustes, abre `cache/pages/<md5(ruta)>`, comprueba la generación de la caché, envía las cabeceras guardadas con un `ETag` (y responde `304 Not Modified` cuando puede), transmite el HTML y termina. Ninguna clase, ninguna configuración, ninguna base de datos. La invalidación es atómica: cada edición publica una nueva generación de caché *antes* de borrar las páginas antiguas, así que nunca se sirve una página caducada, y las entradas programadas refrescan la caché al publicarse.

**Por qué WordPress + caché carga 27 archivos.** `advanced-cache.php` lo carga `wp-settings.php`, después de `wp-load.php` y `wp-config.php`. Es rápido, pero cada petición paga esa secuencia de arranque.

**Por qué WordPress sin caché carga ~500 archivos y hace ~30 consultas SQL.** El núcleo, las opciones cargadas automáticamente, la consulta principal, la jerarquía de plantillas y el tema de bloques que genera sus plantillas.

Explicación detallada con referencias al código: [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

## 4. Peso de las páginas y tamaño de la instalación

<img src="results/2026-11-08-cloud-2vcpu/charts/weight.svg" alt="Peso de la página de artículo: Éclair 8,3 KB en 2 archivos, WordPress 18,2 KB en 4 archivos" width="100%">

<img src="results/2026-11-08-cloud-2vcpu/charts/disk.svg" alt="Tamaño de la instalación: Éclair 2,5 MB, WordPress 150,2 MB" width="100%">

- **El peso de la página** cuenta el HTML más cada hoja de estilo, script e imagen que referencia, comprimidos en gzip tal como se sirven. **Las fuentes web se listan aparte y no se cuentan**, porque el navegador solo descarga las que usa el texto: Éclair usa las fuentes del sistema (nada que descargar) y el tema por defecto de WordPress declara 2 archivos de fuentes (156 KB).
- **El tamaño de la instalación** cuenta el código y el contenido en disco. Éclair: 2,5 MB, 155 archivos PHP, sin base de datos. WordPress: 150 MB, unos 1900 archivos PHP, más un servidor MySQL/MariaDB que mantener, respaldar y proteger.

## Qué cambia en la práctica

| Para… | Lo que cambian estas cifras |
|---|---|
| **Propietarios de sitios** | Páginas que empiezan a mostrarse antes en cada visita, también justo después de publicar. Ningún plugin de caché que elegir, configurar o depurar. |
| **Agencias y freelancers** | Más sitios por servidor, ninguna base de datos que mantener por cliente, actualizaciones que no pueden romper una pila de plugins. |
| **Hostings** | Unas 2 veces más visitas con el mismo procesador en páginas cacheadas, entre 15 y 24 veces en páginas sin caché, y unas 10 veces menos memoria PHP por página generada (0,55 MB frente a 6,1 MB). |
| **SEO** | El tiempo de respuesta del servidor (TTFB) es el inicio de cada carga de página, y una página ligera ayuda a las Core Web Vitals. Éclair parte con ambas cosas. |

Lo que este benchmark **no dice**: que Éclair pueda sustituir cualquier sitio WordPress. WordPress tiene un enorme ecosistema de plugins, multisitio y veinte años de madurez. Éclair está pensado para sitios de contenido (blogs, webs de pequeñas empresas, webs corporativas), que son la mayoría de las instalaciones de WordPress.

## Cómo se mide

| | |
|---|---|
| **Sitios** | Éclair (archivo oficial, SHA-256 guardado), WordPress (repositorio oficial de build, etiqueta de versión, tema por defecto), WordPress + [Cache Enabler](https://github.com/keycdn/cache-enabler) (ajustes por defecto) |
| **Contenido** | Los mismos 20 artículos en cada sitio ([content/articles.json](content/articles.json)); contenido de ejemplo eliminado; 10 artículos por página de lista en ambos CMS |
| **Servidor** | Un solo Apache 2.4 (mpm_event, `.htaccess` activos), un solo pool PHP 8.3 FPM con OPcache, gzip para todos, ajustes por defecto de Ubuntu |
| **Peticiones** | Cabeceras de un Chrome real en cada petición (algunas cachés ignoran las peticiones que no piden HTML) |
| **Tiempo de respuesta** | TTFB con `curl`, 50 peticiones de calentamiento + 300 peticiones, mediana y p95 |
| **Sin caché** | Caché vaciada antes de cada una de las 30 peticiones |
| **Carga** | [wrk](https://github.com/wg/wrk), 20 conexiones × 20 s por página; escalabilidad con 1, 10, 50 y 100 conexiones × 10 s |
| **Dentro de una petición** | Pasada de perfilado separada con `auto_prepend_file`, retirada antes de medir tiempos |
| **Orden** | Un sitio a la vez, los demás en reposo |

Cada detalle y el motivo de cada decisión: [docs/METHODOLOGY.md](docs/METHODOLOGY.md).

## Límites

Preferimos decirlos nosotros:

- **Máquina pequeña y compartida.** 2 vCPU, con el generador de carga en la misma máquina. Las cifras absolutas serán más altas en un servidor real; compara las diferencias.
- **Ajustes por defecto en todo.** PHP-FPM mantiene los 5 procesos de Ubuntu. Un ajuste fino ayudaría a WordPress sin caché, y también a Éclair.
- **Latencia de cola con 100 conexiones.** El p99 de Éclair es más alto que el de WordPress + caché en ese nivel en esta medición (ver [escalabilidad](#3-rendimiento-y-escalabilidad)). En estudio.
- **Diseños por defecto distintos.** El peso compara dos instalaciones por defecto, no dos páginas idénticas.
- **Aún sin métricas de navegador.** Lighthouse y Core Web Vitals necesitan un navegador real. Previsto.
- **20 artículos.** Los sitios grandes (miles de artículos) aún no se han probado. Previsto.
- **Solo Apache + PHP-FPM.** Nginx, LiteSpeed y las cachés fuera de PHP (Varnish, caché FastCGI de Nginx, CDN) no se han probado; ver la [FAQ](docs/FAQ.md).

## Ejecútalo tú mismo

Necesitas un **servidor Ubuntu 24.04 nuevo** (basta un VPS pequeño: 2 vCPU, 2 GB de RAM). No uses un servidor de producción: el script instala y reconfigura Apache, PHP-FPM y MariaDB.

```bash
git clone https://github.com/Jacckisback/eclair-benchmarks
cd eclair-benchmarks
sudo ./run.sh
```

Unos 15 minutos. El informe aparece al final y se guarda, con el JSON bruto y los gráficos, en `results/<fecha>-<nombre del servidor>/`. Las opciones (versión de Éclair y de WordPress, duración de las pruebas, niveles de conexiones) están en el [README en inglés](README.md#run-it-yourself).

## Hoja de ruta

- [ ] Lighthouse y Core Web Vitals (navegador real)
- [ ] Sitios grandes: 1000 y 10 000 artículos
- [ ] Mediciones en un servidor dedicado, con el generador de carga en otra máquina
- [ ] Nginx + PHP-FPM, LiteSpeed
- [ ] Más CMS: Ghost, Grav, Kirby, Statamic, Drupal
- [ ] Más configuraciones de WordPress: WP Rocket, LiteSpeed Cache, caché FastCGI de Nginx

## Contribuir

- **¿Un ajuste injusto o un error?** [Abre un issue](https://github.com/Jacckisback/eclair-benchmarks/issues). Se corregirá y se publicará una nueva medición.
- **¿Otro CMS u otra configuración de WordPress?** Añade su script de instalación en `lib/` siguiendo las mismas reglas (versión oficial, tema por defecto, mismos 20 artículos, mismo servidor) y abre una pull request.
- **¿Lo has ejecutado en tu servidor?** Abre una pull request con tu carpeta `results/`: resultados de máquinas distintas refuerzan la comparación.

## Licencia

MIT para los scripts de este repositorio. Éclair CMS está bajo AGPL-3.0; WordPress y Cache Enabler bajo GPL-2.0 o posterior.
