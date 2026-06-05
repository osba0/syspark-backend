# syspark-backend

Backend systeme de gestion parc auto

En cas d'erreur 403 dans signalement et affection pour le profil chauffeur, ajouter manuellement les permissions avec les nom.viewAny en base

# A mettre absolument dans le .env pour les fichier uplodé soit au bon endroit dans storage/app/public et toujours faire la commande ensuite # 2. Créer le symlink public/storage → storage/app/public

php artisan storage:link
PARC_STORAGE_DISK=public
