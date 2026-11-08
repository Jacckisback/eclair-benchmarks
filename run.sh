#!/usr/bin/env bash
# Éclair CMS benchmarks: Éclair vs WordPress vs WordPress + page cache, same server, same content.
# Tested on a fresh Ubuntu 24.04 machine. Run as root:   sudo ./run.sh
# Options (environment variables):
#   ECLAIR_VERSION=2.0.0   WP_VERSION=7.1.4   BENCH_SAMPLES=300   BENCH_WRK_SECONDS=20   BENCH_WRK_CONNECTIONS=20
set -euo pipefail

HERE="$(cd "$(dirname "$0")" && pwd)"
SRC=/opt/eclair-bench/src
WWW=/var/www/eclair-bench
PHPV=8.3
DB_PASS="bench-$(head -c 6 /dev/urandom | od -An -tx1 | tr -d ' \n')"

say() { printf '\n\033[1;35m▶ %s\033[0m\n' "$*"; }
[ "$(id -u)" = 0 ] || { echo "Run as root: sudo ./run.sh"; exit 1; }

say "1/7 Installing the server stack (Apache, PHP $PHPV FPM, MariaDB, wrk)"
export DEBIAN_FRONTEND=noninteractive
apt-get update -q >/dev/null
apt-get install -y -q git curl unzip apache2 apache2-utils wrk mariadb-server \
  php$PHPV-fpm php$PHPV-cli php$PHPV-mysql php$PHPV-gd php$PHPV-mbstring php$PHPV-xml \
  php$PHPV-zip php$PHPV-curl php$PHPV-intl >/dev/null

say "2/7 Downloading Éclair, WordPress and Cache Enabler"
mkdir -p "$SRC"
if [ ! -d "$SRC/eclair-cms" ]; then git clone -q --depth 1 https://github.com/Jacckisback/eclair-cms "$SRC/eclair-cms"; fi
ECLAIR_VERSION="${ECLAIR_VERSION:-$(php -r 'echo json_decode(json_decode(file_get_contents($argv[1]), true)["payload"], true)["version"];' "$SRC/eclair-cms/dist/latest.json")}"
WP_VERSION="${WP_VERSION:-$(git ls-remote --tags https://github.com/WordPress/WordPress | awk -F/ '{print $3}' | grep -E '^[0-9]+\.[0-9]+(\.[0-9]+)?$' | sort -V | tail -1)}"
rm -rf "$SRC/wordpress" "$SRC/cache-enabler"
git -c advice.detachedHead=false clone -q --depth 1 --branch "$WP_VERSION" https://github.com/WordPress/WordPress "$SRC/wordpress"
git clone -q --depth 1 https://github.com/keycdn/cache-enabler "$SRC/cache-enabler"
ECLAIR_ZIP="$SRC/eclair-cms/dist/eclair-cms-$ECLAIR_VERSION.zip"
[ -f "$ECLAIR_ZIP" ] || { echo "Éclair archive $ECLAIR_ZIP not found"; exit 1; }
echo "Éclair archive sha256: $(sha256sum "$ECLAIR_ZIP" | cut -d' ' -f1)"
echo "Éclair $ECLAIR_VERSION · WordPress $WP_VERSION · Cache Enabler $(grep -m1 -oP 'Version:\s*\K\S+' "$SRC/cache-enabler/cache-enabler.php")"

say "3/7 Configuring Apache + PHP-FPM (same settings for every site)"
cat > /etc/php/$PHPV/fpm/conf.d/90-bench.ini <<'INI'
opcache.enable=1
opcache.memory_consumption=128
opcache.validate_timestamps=1
memory_limit=256M
INI
a2dismod -q mpm_prefork php$PHPV >/dev/null 2>&1 || true
a2enmod -q mpm_event proxy_fcgi setenvif rewrite headers expires deflate >/dev/null
a2enconf -q php$PHPV-fpm >/dev/null
cat > /etc/apache2/sites-available/eclair-bench.conf <<APACHE
ServerName localhost
Listen 8081
Listen 8082
Listen 8083
<Directory $WWW>
    AllowOverride All
    Require all granted
</Directory>
<VirtualHost *:8081>
    DocumentRoot $WWW/eclair
</VirtualHost>
<VirtualHost *:8082>
    DocumentRoot $WWW/wordpress
</VirtualHost>
<VirtualHost *:8083>
    DocumentRoot $WWW/wordpress-cache
</VirtualHost>
APACHE
a2ensite -q eclair-bench >/dev/null
service php$PHPV-fpm restart >/dev/null
service mariadb start >/dev/null 2>&1 || true
service apache2 restart >/dev/null

say "4/7 Installing the three sites with the same 20 articles"
php "$HERE/content/make-articles.php" >/dev/null
rm -rf "$WWW" && mkdir -p "$WWW"
# Éclair: the official release archive, unchanged.
unzip -q "$ECLAIR_ZIP" -d "$WWW/tmp" && mv "$WWW/tmp/eclair" "$WWW/eclair" && rm -rf "$WWW/tmp"
# WordPress: official release, default theme. One copy without cache plugin, one with Cache Enabler.
for site in wordpress wordpress-cache; do
  db="${site//-/_}"
  mysql -e "DROP DATABASE IF EXISTS $db; CREATE DATABASE $db; CREATE USER IF NOT EXISTS 'bench'@'localhost'; ALTER USER 'bench'@'localhost' IDENTIFIED BY '$DB_PASS'; GRANT ALL ON $db.* TO 'bench'@'localhost'; FLUSH PRIVILEGES;"
  rsync -a --exclude .git "$SRC/wordpress/" "$WWW/$site/"
  sed -e "s/database_name_here/$db/" -e "s/username_here/bench/" -e "s/password_here/$DB_PASS/" \
      "$WWW/$site/wp-config-sample.php" > "$WWW/$site/wp-config.php"
  cat > "$WWW/$site/.htaccess" <<'HT'
# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>
# END WordPress
HT
done
rsync -a --exclude .git "$SRC/cache-enabler/" "$WWW/wordpress-cache/wp-content/plugins/cache-enabler/"
chown -R www-data:www-data "$WWW"
WP1="$(sudo -u www-data php "$HERE/lib/setup-wordpress.php" "$WWW/wordpress" http://127.0.0.1:8082 "$HERE/content/articles.json" 0)"
WP2="$(sudo -u www-data php "$HERE/lib/setup-wordpress.php" "$WWW/wordpress-cache" http://127.0.0.1:8083 "$HERE/content/articles.json" 1)"
echo "$WP1" | head -1; echo "$WP2" | head -1
WP_ARTICLE="$(echo "$WP1" | grep -oP '^ARTICLE_PATH=\K.+')"
grep -q "WP_CACHE', true" "$WWW/wordpress-cache/wp-config.php" || { echo "Cache Enabler did not enable WP_CACHE"; exit 1; }
php "$HERE/lib/setup-eclair.php" "$WWW/eclair" 8081 "$HERE/content/articles.json"
chown -R www-data:www-data "$WWW"
SEED="$(sudo -u www-data php "$HERE/lib/seed-eclair.php" "$WWW/eclair" "$HERE/content/articles.json")"
echo "$SEED" | head -1
ECLAIR_ARTICLE="$(echo "$SEED" | grep -oP '^ARTICLE_PATH=\K.+')"
service apache2 reload >/dev/null

STAMP="${BENCH_RUN_NAME:-$(date -u +%Y-%m-%d)-$(hostname -s)}"
OUT="$HERE/results/$STAMP"
mkdir -p "$OUT"

say "5/7 Profiling one request inside PHP (files loaded, memory, SQL queries)"
mkdir -p /var/lib/eclair-bench /opt/eclair-bench
cp "$HERE/lib/profile-prepend.php" /opt/eclair-bench/profile-prepend.php
chown www-data:www-data /var/lib/eclair-bench
echo "auto_prepend_file=/opt/eclair-bench/profile-prepend.php" > /etc/php/$PHPV/fpm/conf.d/95-bench-profile.ini
service php$PHPV-fpm restart >/dev/null
php "$HERE/lib/profile.php" "$OUT/profile.json" \
  "eclair|http://127.0.0.1:8081|/|$ECLAIR_ARTICLE|$WWW/eclair/cache/pages" \
  "wordpress|http://127.0.0.1:8082|/|$WP_ARTICLE|" \
  "wordpress-cache|http://127.0.0.1:8083|/|$WP_ARTICLE|$WWW/wordpress-cache/wp-content/cache/cache-enabler"
# The profiler is removed before any timing measurement.
rm -f /etc/php/$PHPV/fpm/conf.d/95-bench-profile.ini
service php$PHPV-fpm restart >/dev/null

say "6/7 Measuring speed (each site alone, one after the other)"
cat > "$OUT/meta.json" <<META
{"date": "$(date -u +%Y-%m-%dT%H:%MZ)", "eclair": "$ECLAIR_VERSION", "eclair_sha256": "$(sha256sum "$ECLAIR_ZIP" | cut -d' ' -f1)",
 "wordpress": "$WP_VERSION", "cache_enabler": "$(grep -m1 -oP 'Version:\s*\K\S+' "$SRC/cache-enabler/cache-enabler.php")",
 "theme": "$(sudo -u www-data php -r 'define("WP_USE_THEMES",false); $_SERVER["HTTP_HOST"]="127.0.0.1:8082"; require "'"$WWW"'/wordpress/wp-load.php"; echo get_stylesheet();' 2>/dev/null)"}
META
php "$HERE/lib/measure.php" "Éclair $ECLAIR_VERSION" http://127.0.0.1:8081 "$WWW/eclair" "$ECLAIR_ARTICLE" "$OUT/eclair.json" "$WWW/eclair/cache/pages"
php "$HERE/lib/measure.php" "WordPress $WP_VERSION" http://127.0.0.1:8082 "$WWW/wordpress" "$WP_ARTICLE" "$OUT/wordpress.json"
php "$HERE/lib/measure.php" "WordPress $WP_VERSION + Cache Enabler" http://127.0.0.1:8083 "$WWW/wordpress-cache" "$WP_ARTICLE" "$OUT/wordpress-cache.json" "$WWW/wordpress-cache/wp-content/cache/cache-enabler"

say "7/7 Writing the report and the charts"
php "$HERE/lib/report.php" "$OUT" > "$OUT/README.md"
php "$HERE/lib/charts.php" "$OUT"
cat "$OUT/README.md"
echo; echo "Results saved in results/$STAMP/"
