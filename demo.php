<?php
// Structure du cache :
$cache = array(
	'plugin1' => array(
		'3000' => array(
			'dossier_archives/plugin1-3.zip', '2015-10-03', '3.0', 'http://www.monsite.com/depot/',
			'Jean Khol', 'http://www.monsite.com', 
			'Description de mon super plugin plugin3 en version 3.0', 'Aucun pré-requis'
		),
		'2500' => array(
			'dossier_archives/plugin1-2.zip', '2015-09-23', '2.5', 'http://www.monsite.com/depot/',
			'Jean Khol', '', 
			'Description de mon super plugin plugin3 en version 3.0', ''
		),
		'0600' => array(
			'dossier_archives/plugin1-2.zip', '2015-09-23', '0.6', 'http://www.monsite.com/depot/',
			'Michel Martin', 'http://www.monsite.com', 
			'1ère R.C. candidate de mon super plugin plugin3 en version 3.0', 'Aucun pré-requis'
		)
    ),    
	// ..........
	'plugin99' => array(
		'1000' => array(
			'dossier_archives/plugin99-master.zip', '2015-10-03', '1.0', 'http://www.monsite.com/depot/',
			'Michel Martin', 'http://www.monsite.com', 
			'Description de mon super plugin plugin99', 'Jquery, Pluxml 5.4'
		)
    )
);

// Pour récupérer le catalogue sur un site distant :
$repo = 'http://www.monsite.com/page_accueil/';
$cache = json_decode(file_get_contents($repo.'/?json'), true);

// toutes les versions du plugin plugin3 :
$versions = $cache['plugin3'];

// dernière version du plugin plugin3 :
$lastRelease = $versions[array_keys($versions)[0]]

// tout savoir sur la dernière version :
list($download, $filedate, $version, $repository, $author, $site, $description, $requirements) = $lastRelease;

/*
 * $download: chemin relatif pour télécharger l'archive zip du plugin
 * $filedate: date de l'archive zip
 * $repo: adresse de base ou sont stockés les archives zip et où est située la page d'accueil pour afficher le dépôt
 * Pour télécharger un plugin : 
 * if (substr($repository, -1) != '/')
 *    $repository .= '/';
 * $archive = file_get_contents($repository.$download) ;
 * */
?>
