# Patch FCM MA Livraison

## Contenu
Ce ZIP contient uniquement les fichiers Laravel à ajouter/remplacer pour enregistrer les jetons FCM et envoyer les notifications de commande. Fusionner les chemins avec le projet Laravel existant.

## Configuration .env
Ajouter :
```env
FIREBASE_PROJECT_ID=ma-livraison-43517
FIREBASE_CREDENTIALS=/home/UTILISATEUR/htdocs/ma.ma3hsarl.com/storage/app/private/firebase/service-account.json
```
Le JSON du compte de service reste sur le serveur et ne doit jamais être partagé.

## Déploiement
Après copie des fichiers, depuis la racine Laravel :
```bash
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
```

Le patch utilise OpenSSL et le client HTTP Laravel pour obtenir un jeton OAuth Google ; il n'ajoute pas de dépendance Composer.

## Notifications déclenchées
- nouvelle commande -> administrateurs ;
- commande validée -> client ;
- paiement confirmé -> livreurs disponibles ;
- commande attribuée -> livreur choisi ;
- commande acceptée, démarrée, colis récupéré, livrée ou annulée -> client.

Le front Flutter correspondant enregistre automatiquement le jeton après connexion/restauration de session. Les autorisations de notification doivent être acceptées sur l'appareil.
