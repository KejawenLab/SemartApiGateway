#!/usr/bin/env bash
set -e

for name in SEMART_WEBROOT
do
    eval value=\$$name
    sed -i "s|\${${name}}|${value}|g" /etc/nginx/conf.d/default.conf
done

if [[ ! -d "/semart/vendor" ]]; then
    cd /semart && composer update --prefer-dist -vvv
fi

if [[ "prod" == SEMART_ENV ]]; then
    composer dump-autoload --no-dev --classmap-authoritative
fi

chmod 755 -R vendor/

/usr/bin/supervisord -n -c /etc/supervisord.conf
