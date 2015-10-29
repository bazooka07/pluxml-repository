# Pluxml-repository
Gestionnaire d'un dépôt de plugins pour Pluxml

Crée une page d'accueil pour afficher la liste de plugins dans leurs différentes versions et télécharger l'archive zip correspondante.

Si la page index.php est dans le dossier repository sur le serveur http://www.monsite.com, alors le catalogue de plugins au format JSON du site est accessible à l'adresse :

http://www.monsite.com/repository/?json
Les archives zip des plugins sont à déposer dans le dossier /repository/plugins du serveur.

Si on veut connaitre la dernière version disponible du plugin XXXXXX, lire le contenu au format text/plain de l'adresse :
http://www.monsite.com/repository/?plugin=xxxxxx

Pour télécharger la dernière version du plugin xxxxxx, faire :
http://www.monsite.com/repository/?plugin=xxxxxx&download

Le suivi des 10 dernières versions de plugins peut se faire avec un lecteur de flux RSS à l'adresse :
http://www.kazimentou.fr/pluxml-plugins2/?rss

.
