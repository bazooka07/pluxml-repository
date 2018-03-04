# Pluxml-repository
Gestionnaire d'un dépôt de plugins pour Pluxml

Crée une page d'accueil pour afficher la liste de plugins dans leurs différentes versions et télécharger par défaut l'archive zip correspondante de la dernière version.

## Installation
Créez sur votre serveur *http://www.monsite.com* un dossier vide **repo**, par exemple, et copiez à l'intérieur le fichier index.php.

Connectez-vous avec votre navigateur Internet sur votre serveur à l'adresse :

*http://www.monsite.com/repo/*

A la première connexion, un dossier supplémentaire plugins est créé dans le dossier repo.
Déposez maintenant dans le dossier */repo/plugins* toutes les archives zip des plugins que vous souhaitez publier.

A chaque connexion, le script *index.php* régénére un catalogue dès qu'un plugin pls récent est ajouté au dépôt.

## Catalogue du dépôt
Si sur le serveur *http://www.monsite.com*, la page index.php est dans le dossier *repo*, alors le catalogue de plugins au format *JSON* du site est accessible à l'adresse :

http://www.monsite.com/repo/?json

Les archives zip des plugins sont à déposer dans le dossier /repo/plugins du serveur.

## Dernière version d'un plugin
Si on veut connaitre la dernière version disponible du plugin *XXXXXX*, lire le contenu au format text/plain de l'adresse :

*http://www.monsite.com/repository/?plugin=xxxxxx*

Pour télécharger la dernière version du plugin xxxxxx, faire :

*http://www.monsite.com/repository/?plugin=xxxxxx&download*

## Flux RSS
Le suivi des 10 dernières versions de plugins peut se faire avec un lecteur de flux RSS à l'adresse suivante :

*http://www.kazimentou.fr/pluxml-plugins2/?rss*