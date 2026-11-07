# Sourced by the deploy scripts: sets $PHP to a PHP 8.4 CLI binary.
PHP=""
for candidate in /usr/local/bin/ea-php84 /opt/cpanel/ea-php84/root/usr/bin/php /opt/alt/php84/usr/bin/php; do
  [ -x "$candidate" ] && PHP="$candidate" && break
done
PHP="${PHP:-php}"
