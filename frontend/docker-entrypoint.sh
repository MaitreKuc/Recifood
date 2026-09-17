#!/bin/sh
# S'assure que le volume partagÃ© des cookies (montÃ© Ã  l'exÃ©cution) est accessible en Ã©criture
# par l'utilisateur www-data qui exÃ©cute Apache/PHP.
mkdir -p /data/cookies
chown -R www-data:www-data /data/cookies 2>/dev/null || true
chmod 700 /data/cookies 2>/dev/null || true

exec "$@"
