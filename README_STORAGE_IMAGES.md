# Images produits / publicités

Les fichiers sont enregistrés dans `storage/app/public`. Après installation ou déploiement, exécuter depuis la racine Laravel :

```bash
php artisan storage:link
```

Puis vérifier qu’une URL `/storage/...` est accessible depuis le navigateur. Cette étape est nécessaire pour rendre publiques les images stockées sur le disque `public`.
