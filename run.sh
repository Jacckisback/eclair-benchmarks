#!/usr/bin/env bash
# Éclair CMS benchmarks: Éclair vs WordPress (with and without page cache) vs Kirby, same server, same content.
# Tested on a fresh Ubuntu 24.04 machine. Run as root:   sudo ./run.sh
# Options (environment variables) are listed in the README ("Run it yourself").
set -euo pipefail

HERE="$(cd "$(dirname "$0")" && pwd)"
SRC=/opt/eclair-bench/src
WWW=/var/www/eclair-bench
PHPV=8.3
SERVER="${BENCH_SERVER:-apache}"            # apache | nginx
ARTICLES="${BENCH_ARTICLES:-20}"
LIGHTHOUSE="${BENCH_LIGHTHOUSE:-1}"
DB_PASS="bench-$(head -c 6 /dev/urandom | od -An -tx1 | tr -d ' \n')"
FASTCGI_CACHE=/var/cache/nginx/eclair-bench-wp

if [ -n "${BENCH_SITES:-}" ]; then
  SITES=($BENCH_SITES)
elif [ "$SERVER" = nginx ]; then
  SITES=(eclair wordpress-cache wordpress-fastcgi wordpress kirby)
else
  SITES=(eclair wordpress-cache wordpress kirby)
fi
declare -A PORT=([eclair]=8081 [wordpress]=8082 [wordpress-cache]=8083 [kirby]=8084 [wordpress-fastcgi]=8085)
declare -A LIST=([eclair]=/ [wordpress]=/ [wordpress-cache]=/ [wordpress-fastcgi]=/ [kirby]=/notes)
declare -A CACHE=([eclair]="$WWW/eclair/cache/pages" [wordpress]="" [wordpress-cache]="$WWW/wordpress-cache/wp-content/cache/cache-enabler"
                  [wordpress-fastcgi]="$FASTCGI_CACHE" [kirby]="$WWW/kirby/site/cache")
has() { [[ " ${SITES[*]} " == *" $1 "* ]]; }

say() { printf '\n\033[1;35m▶ %s\033[0m\n' "$*"; }
[ "$(id -u)" = 0 ] || { echo "Run as root: sudo ./run.sh"; exit 1; }
[[ "$SERVER" =~ ^(apache|nginx)$ ]] || { echo "BENCH_SERVER must be apache or nginx"; exit 1; }
if [ "$SERVER" = apache ] && has wordpress-fastcgi; then echo "wordpress-fastcgi needs BENCH_SERVER=nginx"; exit 1; fi
if has eclair && [ "$ARTICLES" -gt 2990 ]; then
  echo "Éclair 2.x accepts at most 3,000 contents (articles + pages). Use BENCH_ARTICLES <= 2990, or remove eclair from BENCH_SITES."; exit 1
fi

say "1/8 Installing the server stack ($SERVER, PHP $PHPV FPM, MariaDB, wrk)"
export DEBIAN_FRONTEND=noninteractive
apt-get update -q >/dev/null
apt-get install -y -q git curl unzip rsync wrk mariadb-server \
  php$PHPV-fpm php$PHPV-cli php$PHPV-mysql php$PHPV-gd php$PHPV-mbstring php$PHPV-xml \
  php$PHPV-zip php$PHPV-curl php$PHPV-intl >/dev/null
if [ "$SERVER" = apache ]; then apt-get install -y -q apache2 >/dev/null; else apt-get install -y -q nginx >/dev/null; fi
if [ "$LIGHTHOUSE" = 1 ]; then
  if ! command -v node >/dev/null || [ "$(node -p 'process.versions.node.split(".")[0]')" -lt 20 ]; then
    curl -fsSL https://deb.nodesource.com/setup_22.x | bash - >/dev/null && apt-get install -y -q nodejs >/dev/null
  fi
  (cd "$HERE" && npm install --silent --no-audit --no-fund >/dev/null)
fi

say "2/8 Downloading Éclair, WordPress, Cache Enabler and Kirby"
mkdir -p "$SRC"
if [ ! -d "$SRC/eclair-cms" ]; then git clone -q --depth 1 https://github.com/Jacckisback/eclair-cms "$SRC/eclair-cms"; fi
ECLAIR_VERSION="${ECLAIR_VERSION:-$(php -r 'echo json_decode(json_decode(file_get_contents($argv[1]), true)["payload"], true)["version"];' "$SRC/eclair-cms/dist/latest.json")}"
ECLAIR_ZIP="$SRC/eclair-cms/dist/eclair-cms-$ECLAIR_VERSION.zip"
[ -f "$ECLAIR_ZIP" ] || { echo "Éclair archive $ECLAIR_ZIP not found"; exit 1; }
WP_VERSION="${WP_VERSION:-$(git ls-remote --tags https://github.com/WordPress/WordPress | awk -F/ '{print $3}' | grep -E '^[0-9]+\.[0-9]+(\.[0-9]+)?$' | sort -V | tail -1)}"
rm -rf "$SRC/wordpress" "$SRC/cache-enabler" "$SRC/starterkit"
git -c advice.detachedHead=false clone -q --depth 1 --branch "$WP_VERSION" https://github.com/WordPress/WordPress "$SRC/wordpress"
git clone -q --depth 1 https://github.com/keycdn/cache-enabler "$SRC/cache-enabler"
git clone -q --depth 1 https://github.com/getkirby/starterkit "$SRC/starterkit"
CE_VERSION="$(grep -m1 -oP 'Version:\s*\K\S+' "$SRC/cache-enabler/cache-enabler.php")"
KIRBY_VERSION="$(php -r 'echo json_decode(file_get_contents($argv[1]), true)["version"] ?? "";' "$SRC/starterkit/kirby/composer.json")"
echo "Éclair $ECLAIR_VERSION (sha256 $(sha256sum "$ECLAIR_ZIP" | cut -c1-16)…) · WordPress $WP_VERSION · Cache Enabler $CE_VERSION · Kirby $KIRBY_VERSION"

say "3/8 Configuring $SERVER + PHP-FPM (same settings for every site)"
cat > /etc/php/$PHPV/fpm/conf.d/90-bench.ini <<'INI'
opcache.enable=1
opcache.memory_consumption=128
opcache.validate_timestamps=1
memory_limit=256M
INI
rm -f /etc/php/$PHPV/fpm/conf.d/95-bench-profile.ini
if [ "$SERVER" = apache ]; then
  service nginx stop >/dev/null 2>&1 || true
  a2dismod -q mpm_prefork php$PHPV >/dev/null 2>&1 || true
  a2enmod -q mpm_event proxy_fcgi setenvif rewrite headers expires deflate >/dev/null
  a2enconf -q php$PHPV-fpm >/dev/null
  {
    echo "ServerName localhost"
    for s in "${SITES[@]}"; do echo "Listen ${PORT[$s]}"; done
    echo "<Directory $WWW>"; echo "    AllowOverride All"; echo "    Require all granted"; echo "</Directory>"
    for s in "${SITES[@]}"; do echo "<VirtualHost *:${PORT[$s]}>"; echo "    DocumentRoot $WWW/$s"; echo "</VirtualHost>"; done
  } > /etc/apache2/sites-available/eclair-bench.conf
  a2ensite -q eclair-bench >/dev/null
else
  service apache2 stop >/dev/null 2>&1 || true
  FPM="unix:/run/php/php$PHPV-fpm.sock"
  mkdir -p "$FASTCGI_CACHE" && chown www-data:www-data "$FASTCGI_CACHE"
  # Compression for every site, the same text types as Apache's mod_deflate defaults.
  cat > /etc/nginx/conf.d/eclair-bench-http.conf <<NGX
gzip_types text/plain text/css text/xml application/javascript application/json application/xml application/rss+xml image/svg+xml;
gzip_vary on;
fastcgi_cache_path $FASTCGI_CACHE levels=1:2 keys_zone=WPBENCH:50m inactive=60m max_size=1g;
NGX
  {
    for s in "${SITES[@]}"; do
      echo "server {"
      echo "  listen ${PORT[$s]};"
      echo "  root $WWW/$s;"
      echo "  index index.php;"
      case "$s" in
        eclair) cat <<NGX
  # Official Éclair rules (docs/GUIDE.md, Nginx section)
  location ~ ^/(app|data|cache)(/|\$) { return 404; }
  location ~ /\.(?!well-known/) { return 404; }
  location ^~ /media/ {
    location ~* \.(php\d?|phtml|phar|inc|pl|py|cgi|sh|asp|aspx|jsp|s?html?|xht(ml)?|svgz?|xml|m?js)\$ { return 404; }
    add_header X-Content-Type-Options nosniff;
    expires 1y;
  }
  location ~* \.(webp|avif|jpe?g|png|gif|ico|css|js|woff2)\$ { expires 1y; add_header Cache-Control "public, immutable"; }
  location / { try_files \$uri /index.php\$is_args\$args; }
  location = /index.php { include fastcgi_params; fastcgi_param SCRIPT_FILENAME \$document_root/index.php; fastcgi_pass $FPM; }
  location ~ \.php\$ { return 404; }
NGX
        ;;
        kirby) cat <<NGX
  # Kirby's documented Nginx rules: content, site and kirby folders never served directly
  location ~ ^/(content|site|kirby)/ { rewrite ^ /index.php last; }
  location ~ /\.(?!well-known/) { rewrite ^ /index.php last; }
  location / { try_files \$uri \$uri/ /index.php\$is_args\$args; }
  location ~ \.php\$ { include snippets/fastcgi-php.conf; fastcgi_pass $FPM; }
NGX
        ;;
        wordpress-fastcgi) cat <<NGX
  # WordPress + Nginx FastCGI cache (common set-up: skip POST, query strings and logged-in/comment cookies)
  set \$skip_cache 0;
  if (\$request_method = POST) { set \$skip_cache 1; }
  if (\$query_string != "") { set \$skip_cache 1; }
  if (\$request_uri ~* "/wp-admin/|/xmlrpc.php|wp-.*\.php|/feed/|sitemap") { set \$skip_cache 1; }
  if (\$http_cookie ~* "comment_author|wordpress_[a-f0-9]+|wp-postpass|wordpress_no_cache|wordpress_logged_in") { set \$skip_cache 1; }
  location / { try_files \$uri \$uri/ /index.php?\$args; }
  location ~ \.php\$ {
    include snippets/fastcgi-php.conf; fastcgi_pass $FPM;
    fastcgi_cache WPBENCH; fastcgi_cache_key "\$scheme\$request_method\$host\$request_uri";
    fastcgi_cache_valid 200 301 302 60m; fastcgi_cache_use_stale error timeout updating;
    fastcgi_cache_bypass \$skip_cache; fastcgi_no_cache \$skip_cache;
    fastcgi_ignore_headers Cache-Control Expires;
    add_header X-FastCGI-Cache \$upstream_cache_status;
  }
NGX
        ;;
        *) cat <<NGX
  # WordPress (official Nginx recipe)
  location / { try_files \$uri \$uri/ /index.php?\$args; }
  location ~ \.php\$ { include snippets/fastcgi-php.conf; fastcgi_pass $FPM; }
NGX
        ;;
      esac
      echo "}"
    done
  } > /etc/nginx/sites-available/eclair-bench
  rm -f /etc/nginx/sites-enabled/default   # the test server only serves the benchmark sites
  ln -sf /etc/nginx/sites-available/eclair-bench /etc/nginx/sites-enabled/eclair-bench
  nginx -t -q
fi
service php$PHPV-fpm restart >/dev/null
service mariadb start >/dev/null 2>&1 || true
service "$([ "$SERVER" = apache ] && echo apache2 || echo nginx)" restart >/dev/null

say "4/8 Installing ${#SITES[@]} sites with the same $ARTICLES articles"
php "$HERE/content/make-articles.php" "$ARTICLES"
rm -rf "$WWW" && mkdir -p "$WWW"
declare -A ARTICLE NAME
WP_HTACCESS='# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>
# END WordPress'
for s in "${SITES[@]}"; do
  case "$s" in
    eclair)
      unzip -q "$ECLAIR_ZIP" -d "$WWW/tmp" && mv "$WWW/tmp/eclair" "$WWW/eclair" && rm -rf "$WWW/tmp"
      chown -R www-data:www-data "$WWW/eclair"
      php "$HERE/lib/setup-eclair.php" "$WWW/eclair" "${PORT[eclair]}" "$HERE/content/articles.json"
      chown -R www-data:www-data "$WWW/eclair"
      OUTPUT="$(sudo -u www-data php "$HERE/lib/seed-eclair.php" "$WWW/eclair" "$HERE/content/articles.json")"
      NAME[$s]="Éclair $ECLAIR_VERSION" ;;
    wordpress|wordpress-cache|wordpress-fastcgi)
      db="${s//-/_}"
      mysql -e "DROP DATABASE IF EXISTS $db; CREATE DATABASE $db; CREATE USER IF NOT EXISTS 'bench'@'localhost'; ALTER USER 'bench'@'localhost' IDENTIFIED BY '$DB_PASS'; GRANT ALL ON $db.* TO 'bench'@'localhost'; FLUSH PRIVILEGES;"
      rsync -a --exclude .git "$SRC/wordpress/" "$WWW/$s/"
      sed -e "s/database_name_here/$db/" -e "s/username_here/bench/" -e "s/password_here/$DB_PASS/" "$WWW/$s/wp-config-sample.php" > "$WWW/$s/wp-config.php"
      echo "$WP_HTACCESS" > "$WWW/$s/.htaccess"
      cache=0
      if [ "$s" = wordpress-cache ]; then rsync -a --exclude .git "$SRC/cache-enabler/" "$WWW/$s/wp-content/plugins/cache-enabler/"; cache=1; fi
      chown -R www-data:www-data "$WWW/$s"
      OUTPUT="$(sudo -u www-data php "$HERE/lib/setup-wordpress.php" "$WWW/$s" "http://127.0.0.1:${PORT[$s]}" "$HERE/content/articles.json" "$cache")"
      if [ "$s" = wordpress-cache ]; then grep -q "WP_CACHE', true" "$WWW/$s/wp-config.php" || { echo "Cache Enabler did not enable WP_CACHE"; exit 1; }; fi
      case "$s" in
        wordpress) NAME[$s]="WordPress $WP_VERSION" ;;
        wordpress-cache) NAME[$s]="WordPress $WP_VERSION + Cache Enabler" ;;
        wordpress-fastcgi) NAME[$s]="WordPress $WP_VERSION + Nginx FastCGI cache" ;;
      esac ;;
    kirby)
      rsync -a --exclude .git "$SRC/starterkit/" "$WWW/kirby/"
      OUTPUT="$(php "$HERE/lib/setup-kirby.php" "$WWW/kirby" "$HERE/content/articles.json")"
      chown -R www-data:www-data "$WWW/kirby"
      NAME[$s]="Kirby $KIRBY_VERSION (pages cache)" ;;
  esac
  echo "$OUTPUT" | grep -v '^ARTICLE_PATH='
  ARTICLE[$s]="$(echo "$OUTPUT" | grep -oP '^ARTICLE_PATH=\K.+')"
done
chown -R www-data:www-data "$WWW"
service "$([ "$SERVER" = apache ] && echo apache2 || echo nginx)" reload >/dev/null
for s in "${SITES[@]}"; do   # every page must answer 200 before measuring
  for p in "${LIST[$s]}" "${ARTICLE[$s]}"; do
    code="$(curl -s -o /dev/null -w '%{http_code}' -H 'Accept: text/html' "http://127.0.0.1:${PORT[$s]}$p")"
    [ "$code" = 200 ] || { echo "$s $p answered HTTP $code"; exit 1; }
  done
done

STAMP="${BENCH_RUN_NAME:-$(date -u +%Y-%m-%d)-$(hostname -s)-$SERVER-$ARTICLES}"
OUT="$HERE/results/$STAMP"
rm -rf "$OUT" && mkdir -p "$OUT"
{
  echo "{\"date\": \"$(date -u +%Y-%m-%dT%H:%MZ)\", \"server\": \"$SERVER\", \"articles\": $ARTICLES,"
  echo " \"eclair\": \"$ECLAIR_VERSION\", \"eclair_sha256\": \"$(sha256sum "$ECLAIR_ZIP" | cut -d' ' -f1)\","
  echo " \"wordpress\": \"$WP_VERSION\", \"cache_enabler\": \"$CE_VERSION\", \"kirby\": \"$KIRBY_VERSION\","
  echo " \"load_generator\": \"${BENCH_LOAD_SSH:-same machine}\","
  echo " \"machine\": \"$(grep -m1 'model name' /proc/cpuinfo | cut -d: -f2 | xargs), $(nproc) vCPU, $(free -g | awk '/Mem:/{print $2}') GB RAM\","
  echo " \"sites\": ["
  i=0; for s in "${SITES[@]}"; do [ $i -gt 0 ] && echo ","; i=1
    printf '  {"key": "%s", "name": "%s", "port": %s, "list": "%s", "article": "%s"}' "$s" "${NAME[$s]}" "${PORT[$s]}" "${LIST[$s]}" "${ARTICLE[$s]}"; done
  echo; echo " ]}"
} > "$OUT/meta.json"

say "5/8 Profiling one request inside PHP (files loaded, memory, SQL queries)"
mkdir -p /var/lib/eclair-bench /opt/eclair-bench
cp "$HERE/lib/profile-prepend.php" /opt/eclair-bench/profile-prepend.php
chown www-data:www-data /var/lib/eclair-bench
echo "auto_prepend_file=/opt/eclair-bench/profile-prepend.php" > /etc/php/$PHPV/fpm/conf.d/95-bench-profile.ini
service php$PHPV-fpm restart >/dev/null
SPECS=(); for s in "${SITES[@]}"; do SPECS+=("$s|http://127.0.0.1:${PORT[$s]}|${LIST[$s]}|${ARTICLE[$s]}|${CACHE[$s]}"); done
php "$HERE/lib/profile.php" "$OUT/profile.json" "${SPECS[@]}"
rm -f /etc/php/$PHPV/fpm/conf.d/95-bench-profile.ini   # the profiler never runs during timings
service php$PHPV-fpm restart >/dev/null

say "6/8 Measuring speed (each site alone, one after the other)"
for s in "${SITES[@]}"; do
  php "$HERE/lib/measure.php" "${NAME[$s]}" "http://127.0.0.1:${PORT[$s]}" "$WWW/$s" "${ARTICLE[$s]}" "$OUT/$s.json" "${CACHE[$s]}" "${LIST[$s]}"
done

if [ "$LIGHTHOUSE" = 1 ]; then
  say "7/8 Lighthouse in a real Chromium (mobile, simulated slow 4G, like PageSpeed Insights)"
  LH=(); for s in "${SITES[@]}"; do LH+=("$s|http://127.0.0.1:${PORT[$s]}|${LIST[$s]}|${ARTICLE[$s]}"); done
  (cd "$HERE" && node lib/lighthouse.mjs "$OUT/lighthouse.json" "${LH[@]}")
else
  say "7/8 Lighthouse skipped (BENCH_LIGHTHOUSE=0)"
fi

say "8/8 Writing the report and the charts"
php "$HERE/lib/report.php" "$OUT" > "$OUT/README.md"
php "$HERE/lib/charts.php" "$OUT"
cat "$OUT/README.md"
echo; echo "Results saved in results/$STAMP/"
