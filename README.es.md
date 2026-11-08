<p align="center"><a href="README.md">English</a> · <a href="README.fr.md">Français</a> · <b>Español</b></p>

# Benchmarks de Éclair CMS

Pruebas de velocidad abiertas y reproducibles de [Éclair CMS](https://github.com/Jacckisback/eclair-cms) frente a WordPress, en el mismo servidor y con el mismo contenido. Un solo script lo instala y lo mide todo. **Cualquiera puede ejecutarlo y comprobar las cifras.**

> Éclair lo crea el autor de este repositorio. Precisamente por eso el método, los scripts y los datos brutos son públicos: no te fíes de nosotros, ejecútalo tú mismo. Correcciones y nuevos CMS son bienvenidos (ver [Contribuir](#contribuir)).

## Últimos resultados

Medición del 8 de noviembre de 2026 · Éclair 2.0.0 · WordPress 7.1.4 · máquina cloud de 2 vCPU, Ubuntu 24.04, Apache 2.4 + PHP 8.3 FPM (OPcache activo), MariaDB 10.11 · [informe completo y datos brutos](results/2026-11-08-cloud-2vcpu/)

**Página de artículo**

| | Tiempo de respuesta (TTFB mediano) | Página generada sin caché | Rendimiento | Peso de la página (gzip) |
|---|---:|---:|---:|---:|
| **Éclair 2.0.0** | **0,83 ms** | **2,50 ms** | **2183 req/s** | **8,3 KB** |
| WordPress 7.1.4 + Cache Enabler | 2,14 ms | 65,62 ms | 1154 req/s | 18,3 KB |
| WordPress 7.1.4 (sin plugin) | 58,07 ms | 59,41 ms | 34 req/s | 18,2 KB |

En resumen, en esta máquina:

- **Frente a WordPress con un plugin de caché** (como funcionan la mayoría de los sitios WordPress): Éclair responde unas **2 veces más rápido**, atiende unas **2 veces más visitas por segundo** y envía una página unas **2 veces más ligera**.
- **Cuando la página no está en caché** (primera visita tras una edición, o sitio sin plugin de caché): Éclair genera la página en menos de **3 ms**, WordPress en 60 ms o más: más de **20 veces más rápido**.
- **Instalación**: Éclair ocupa **2,5 MB**, sin base de datos. WordPress ocupa **150 MB**, más una base de datos MariaDB/MySQL.
- **Fuentes web**: Éclair usa las fuentes del sistema (nada que descargar). El tema por defecto de WordPress declara 2 archivos de fuentes (156 KB); el navegador descarga los que usa el texto. No se cuentan en el peso de arriba.

La página de inicio da resultados parecidos: ver el [informe completo](results/2026-11-08-cloud-2vcpu/).

## Ejecútalo tú mismo

Necesitas un **servidor Ubuntu 24.04 nuevo** (basta un VPS pequeño, 2 vCPU / 2 GB de RAM). No uses un servidor de producción: el script instala y reconfigura Apache, PHP-FPM y MariaDB.

```bash
git clone https://github.com/Jacckisback/eclair-benchmarks
cd eclair-benchmarks
sudo ./run.sh
```

Tarda unos 10 minutos. El informe aparece al final y se guarda en `results/<fecha>-<nombre del servidor>/`, con los datos JSON brutos. Las opciones están descritas en el [README en inglés](README.md#run-it-yourself).

## Método

- **Mismo servidor, mismos ajustes**: los tres sitios funcionan en la misma máquina, con el mismo Apache y el mismo PHP-FPM (OPcache activo, ajustes por defecto de Ubuntu). Cada sitio se mide solo, uno tras otro.
- **Los tres sitios**: Éclair (archivo oficial sin modificar, instalado con su propio asistente), WordPress sin plugin (versión oficial, tema por defecto) y WordPress + [Cache Enabler](https://github.com/keycdn/cache-enabler), un plugin de caché gratuito y popular, con su configuración por defecto. Es la comparación justa: casi nadie usa WordPress sin caché.
- **Mismo contenido**: los mismos 20 artículos en cada sitio ([content/articles.json](content/articles.json)), contenido de ejemplo eliminado.
- **Mediciones**: tiempo de respuesta (TTFB, 300 peticiones), página generada con la caché vaciada (30 peticiones), rendimiento y latencia bajo carga ([wrk](https://github.com/wg/wrk), 20 conexiones durante 20 s), peso de la página (HTML + CSS + JS + imágenes, gzip), tamaño en disco y número de archivos PHP. Cada petición envía las cabeceras de un navegador Chrome real.

## Límites

Preferimos decirlos nosotros:

- **Una sola máquina, pequeña y compartida.** El generador de carga funciona en la misma máquina que los sitios: el rendimiento es menor que en un servidor real. Compara los sitios entre sí, no con otros benchmarks.
- **Ajustes por defecto.** PHP-FPM mantiene los ajustes de Ubuntu (5 procesos). Un ajuste fino ayudaría a WordPress sin caché, y también a Éclair.
- **No es el mismo diseño.** Cada CMS usa su tema por defecto: el peso compara dos instalaciones por defecto, no dos páginas idénticas.
- **Aún sin métricas de navegador** (Lighthouse, Core Web Vitals). Las contribuciones son bienvenidas.
- **Solo WordPress por ahora.** Ghost, Grav, Kirby, Statamic, Drupal… están previstos.

## Contribuir

- ¿Un error o un ajuste injusto? Abre un issue: lo corregimos y publicamos una nueva medición, aunque sea peor para Éclair.
- ¿Otro CMS que añadir? Abre una pull request con su script de instalación en `lib/`, siguiendo las mismas reglas.
- ¿Has ejecutado el benchmark en tu servidor? Abre una pull request con tu carpeta `results/`.

## Licencia

MIT para los scripts de este repositorio. Éclair CMS está bajo AGPL-3.0; WordPress y Cache Enabler bajo GPL-2.0 o posterior.
