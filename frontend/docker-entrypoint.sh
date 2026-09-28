#!/bin/sh
# S'assure que le volume partagé des cookies (monté à l'exécution) est accessible en écriture
# par l'utilisateur www-data qui exécute Apache/PHP.
mkdir -p /data/cookies
chown -R www-data:www-data /data/cookies 2>/dev/null || true
chmod 700 /data/cookies 2>/dev/null || true

# Idem pour les miniatures de recettes : le dossier est bind-monté depuis l'hôte (NAS, Linux...),
# il appartient donc à l'utilisateur de l'hôte et non à www-data. Sans ce correctif, Apache ne peut
# pas y écrire et les images restent hébergées sur des URL distantes qui finissent par expirer.
mkdir -p /var/www/html/uploads/recipes
chown -R www-data:www-data /var/www/html/uploads 2>/dev/null || true
chmod -R u+rwX,g+rwX /var/www/html/uploads 2>/dev/null || true

exec "$@"
