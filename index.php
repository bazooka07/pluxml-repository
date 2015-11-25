<?php

/*
 * http://www.mondepot.com/index.php?plugin=xxxxxx renvoie le numéro de version au format texte
 * http://www.mondepot.com/index.php?plugin=xxxxxx&infos renvoie les infos du plugin au format XML
 * xxxxxx est le nom de l'archive Zip du plugin, stocké dans le dossier FOLDER défini ci-dessous.
 * L'archive et le plugin ont le même nom par convention.
 * 
 * Ajustez la constante FOLDER selon vos désirs.
 * 
 * @author Jean-Pierre Pourrez
 * @version 2015-10-30 - gère les multiples versions des plugins et génére un cache
 * @license GNU General Public License, version 3
 * @see http://www.pluxml.org
 * 
 * */

if (! class_exists('ZipArchive')) {
	header('HTTP1/1 501 Classe ZipArchive introuvable sur le serveur pour les scripts PHP');
	exit;
}

define('VERSION', date('Y-m-d', filemtime(__FILE__)));
define('FOLDER', 'plugins');
define('CACHE_FILE', 'cache.json');
define('CACHE_ICONS', 'icons.bin');
define('INFOS_FILE', 'infos.xml');
define('INFOS_FILE_LEN', strlen(INFOS_FILE));
define('LIFETIME', 7 * 24 * 3600); // in secondes (temps Unix) for rebuilding $cache and $cache_icons

$root = $_SERVER['PHP_SELF'];

/*
 * Vérifie si le cache a besoin d'une mise à jour
 * On ne fait la mise à jour que toutes les LIFETIME minutes
 * */
function needs_update() {
	$result = false;
	if (! file_exists(CACHE_FILE) or ! file_exists(CACHE_ICONS))
		$result = true;
	else {
		$refTime = filemtime(CACHE_FILE);
		if (time() - $refTime > LIFETIME)
			// caches are too old
			$result = true;
		else {
			$files = scandir(FOLDER);
			foreach ($files as $f) {
				if (substr($f, -4) == '.zip') {
					$filename = FOLDER.'/'.$f;
					if ($refTime < filemtime($filename)) {
						// one file is more recent than the cache
						$result = true;
						break;
					}
				}
			}
		}
	}
	return $result;
}

/*
 * Recherche dans l'archive Zip, une entrée finissant par infos.xml
 * */
function searchInfosFile(ZipArchive $zipFile) {
	$result = false;
	for ($i=0; $i<$zipFile->numFiles; $i++) {
		$filename = $zipFile->getNameIndex($i);
		if (substr($filename, - INFOS_FILE_LEN) == INFOS_FILE) {
			$result = $filename;
			break;
		}
	}
	return $result;
}

/*
 * Recherche la déclaration d'une classe dans un fichier .php
 * dans l'archive Zip, pour connaitre le nom réel du plugin
 * */
function getPluginName(ZipArchive $zipFile) {
	$result = false;
	for ($i=0; $i<$zipFile->numFiles; $i++) {
		$filename = $zipFile->getNameIndex($i);
		if (substr($filename, -4) == '.php') {
			$content = $zipFile->getFromName($filename);
			if (preg_match('/\bclass\s+(\w+)\s+extends\s+plxPlugin\b/', $content, $matches) === 1) {
				$result = $matches[1];
				break;
			} else if (preg_match('/^([\w-]+)\/(?:article|static[^\.]*)\.php$/', $filename, $matches) === 1) {
				// archive zip d'un théme demandé par niqnutn
				$result = $matches[1];
				break;
			}
		}
	}
	return $result;
}

/*
 * Extrait l'icône icon.png de l'archive Zip
 * */
 // not used !
function getPluginIcon(ZipArchive $zipFile) {
	$result = '<span class="missing">&nbsp;</span>';
	for ($i=0; $i<$zipFile->numFiles; $i++) {
		$filename = $zipFile->getNameIndex($i);
		if (preg_match('#/icon\.(?:jpg|png|gif)$#', $filename)) {
			$src = $zipFile->getFromName($filename);
			$result = '<img src="data:image/x-icon;base64,'.base64_encode($src).'" alt="Icône" />';
			break;
		}
	}
	return $result;
}

function buildCaches(array $files) {
	$cache = array();
	$cache_icons = array();
	$zip = new ZipArchive();
	foreach($files as $f) {
		if (substr($f, -4) == '.zip') {
			$name = substr($f, 0, -4);
			$filename = FOLDER.'/'.$f;
			$filedateEpoc = filemtime($filename);
			$filedate = date('c', $filedateEpoc);
			if ($res = $zip->open($filename)) {
				if ($infoFile = searchInfosFile($zip)) {
					$infos = $zip->getFromName($infoFile);
					try {
			            @$doc = new SimpleXMLElement($infos);
			            $title = $doc->title->__toString();
			            $author = $doc->author->__toString();
			            $version = $doc->version->__toString();
			            $versionOrder = intval(floatval($version) * 1000.0);
			            $site = $doc->site->__toString();
			            $repository = $doc->repository->__toString();
			            $description = $doc->description->__toString();
			            $requirements = $doc->requirements->__toString();
			            $values = array($filename, $filedate, $version, $repository, $author, $site, $description, $requirements);
			            $keyName = getPluginName($zip);
			            // on vérifie si le plugin existe déjà dans le cache
			            if (! array_key_exists($keyName, $cache))
							$cache[$keyName] = array();
						$cache[$keyName][$versionOrder] = $values;
						// look for an icon
						for ($i=0; $i<$zip->numFiles; $i++) {
							$filenameIcon = $zip->getNameIndex($i);
							if (preg_match('#/icon\.(jpg|png|gif)$#', $filenameIcon, $matches)) {
								if (! array_key_exists($keyName, $cache_icons) or ($cache_icons[$keyName][1] < $filedateEpoc)) {
									// on garde l'icone de la dernière version
									$icon = $zip->getFromIndex($i);
									$stats = $zip->StatIndex($i); // keys in array('name', 'index', 'crc', 'size', 'mtime', 'comp_size', ' comp_methohd');
									$cache_icons[$keyName] = array($filedateEpoc, $matches[1], $stats['size'], $icon);
								}
								break;
							}
						}
					} catch (Exception $e) {
						error_log(date('Y-m-d H:i').' - fichier infos.xml incorrect pour le plugin '.$f.' - Ligne n°'.$e->getLine().': '.$e->getMessage()."\n", 3, dirname(__FILE__).'/errors.log');
					}
				}
				$zip->close();
			}
		}
	}
	return array($cache, $cache_icons);
}

function sendRSS(array $cache, array $cache_icons) {
	global $root;
	
	$temp = array();
	foreach($cache as $pluginName=>$versions) {
		$keyVersion = array_keys($versions)[0];
		$lastRelease = $versions[$keyVersion];
		list($download, $filedate, $version, $repository, $author, $site, $description, $requirements) = $lastRelease;
		$temp[$filedate.'-'.$pluginName] = array($pluginName, $version, $download, $description, $filedate);
	}
	krsort($temp);
	$lastUpdates = array_slice($temp, 0, 10); // 10 items for the RSS feed
	$hostname = $_SERVER['HTTP_HOST'];
	$epocTime = (file_exists(CACHE_FILE)) ? filemtime(CACHE_FILE) : time();
	$lastBuildDate = date('r', $epocTime);
    header('Content-Type: application/rss+xml');
    echo <<< RSS_STARTS
<?xml version="1.0"  encoding="UTF-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
    <title>Kazimentou, playstore for Pluxml</title>
    <link>http://$hostname$root</link>
    <description>Dépôt de plugins pour le gestionaire de contenus (CMS) de Pluxml</description>
    <lastBuildDate>$lastBuildDate</lastBuildDate>
    <atom:link href="http://$hostname$root?rss" rel="self" type="application/rss+xml" />
    <generator>Repository for Pluxml plugins v2.0</generator>
    <skipDays>
		<day>Sunday</day>
	</skipDays>

RSS_STARTS;
	$folder = dirname($root);
	foreach($lastUpdates as $key=>$item) {
		list($pluginName, $version, $link, $description, $filedate) = $item;
		$title = $pluginName.' - '.$version;
		$pubDate = date('r', strtotime($filedate));
		$description = htmlspecialchars($description, ENT_COMPAT | ENT_XML1);
		$guid = 'http://'.$hostname.$folder.'/'.$link.'_'.substr($filedate, 0, 10);
		if (array_key_exists($pluginName, $cache_icons)) {
			list($filedateEpoc, $imageType, $sizeIcon, $content) = $cache_icons[$pluginName];
			// list($imageType, $content) = $cache_icons[$pluginName];
			if ($imageType == 'jpg')
				$imageType = 'jpeg';
			$length = strlen($content);
			$enclosure = <<< ENCLOSURE

		<enclosure url="http://$hostname$folder/index.php?plugin=$pluginName&amp;icon" length="$length" type="image/$imageType" />
ENCLOSURE;
		} else
			$enclosure = '';
		echo <<< ITEM
	<item>
		<title>$title</title>
		<link>http://$hostname$folder/$link</link>
		<description>$description</description>
		<pubDate>$pubDate</pubDate>$enclosure
		<guid>$guid</guid>
	</item>

ITEM;
			}
	echo <<< RSS_ENDS
</channel>
</rss>

RSS_ENDS;
}

if (! is_dir(FOLDER))
	mkdir(FOLDER);

// clean up caches for an update of this script
$caches = array(CACHE_FILE, CACHE_ICONS);
$lastUpdateProg = filemtime(__FILE__);
foreach ($caches as $file1) {
	if (file_exists($file1) and (filemtime($file1) < $lastUpdateProg))
		unlink($file1);
}

if ($files = scandir(FOLDER)) {
	/*
	 * $cache contient la liste des plugins et de leurs différentes versions
	 * C'est un tableau associatif :
	 * la clé est le nom du plugin, ou plus exactement le nom de la classe dérivé de PlxPlugin
	 * la valeur est un tableau associatif avec :
	 * la clé est le numéro de version du plugin
	 * la valeur est un tableau ordinaire contenant
	 * le nom de l'archive ($filename)
	 * la date de l'archive ($filedate)
	 * le numéro de version comme indiqué dans le fichier infos.xml ($version)
	 * l'url où demander l'archive zip du plugin ($repository)
	 * l'auteur ($author)
	 * le site web de l'auteur ($site)
	 * la description du plugin ($description)
	 * les pré-requis ($requirements).
	 * le numéro de version contenu dans infos.xml est converti en flottant à multiplier par 1000
	 * et à convertir en entier pour trier les versions
	 * */
	if (needs_update()) {
		list($cache, $cache_icons) = buildCaches($files);
		// tri et sauvegarde du cache sur le disque dur si non vide
		if (! empty($cache)) {
			ksort($cache);
			// tri décroissant des versions de chaque plugin
			foreach(array_keys($cache) as $k)
				krsort($cache[$k]);
			// encodage et sauvegarde (il y a du javascript dans l'air)
			if (file_put_contents(CACHE_FILE, json_encode($cache), LOCK_EX) === false)
				$error = 'Pas de droit en écriture sur le disque dur<br />Contactez votre webmaster.';
			else
				// file_put_contents(CACHE_ICONS, json_encode($cache_icons), LOCK_EX);
				file_put_contents(CACHE_ICONS, serialize($cache_icons), LOCK_EX);
		} else
            unset($cache);
	} else {
		$cache = json_decode(file_get_contents(CACHE_FILE), true);
		// $cache_icons = json_decode(file_get_contents(CACHE_ICONS), true);
		$cache_icons = unserialize(file_get_contents(CACHE_ICONS));
	}
}

if (! empty($_GET)) {
	$result = 'Not found';
	if (isset($cache)) {
		if (isset($_GET['plugin'])) {
	        $pluginName = $_GET['plugin'];
	        if (array_key_exists($pluginName, $cache)) {
				$versions = $cache[$pluginName];
				$key = array_keys($versions)[0];
				$lastRelease = $versions[$key];
				list($download, $filedate, $version, $repository, $author, $site, $description, $requirements) = $lastRelease;
				if (isset($_GET['infos'])) { // envoi du contenu du fichier infos.xml du plugin
					$zip = new ZipArchive();
					if ($res = $zip->open($download)) {
						if ($infoFile = searchInfosFile($zip)) {
							// send content of infos.xml
							$result = $zip->getFromName($infoFile);
						    header('Content-Type: text/xml');
							echo $result;
							exit;
						}
						$zip->close();
					}
				} else if (isset($_GET['download'])) { // envoi l'archive zip du plugin
					header('Content-Type: application/zip');
				    header('Content-Length: ' . filesize($download));
				    header('Content-Disposition: attachment; filename="'.basename($download).'"');
					readfile($download);
					exit;
				} else if (isset($_GET['icon'])) { // envoi de l'icône du plugin
					if (array_key_exists($pluginName, $cache_icons)) {
						// list($filedateEpoc, $mimetype, $content) = $cache_icons[$pluginName];
						list($filedateEpoc, $mimetype, $sizeIcon, $content) = $cache_icons[$pluginName];			
						if ($mimetype == 'jpg')
							$mimetype = 'jpeg';
						header('Content-Length: '.strlen($content));
						header('Content-Type: image/'.$mimetype);
						echo $content;
					} else {
						header('HTTP/1.0 404 Not Found');
						header('Content-Type: text/plain');
						echo 'Icon not found for '.$pluginName.' plugin.';
					}
					exit;				
				} else { // send version of plugin
					$result = $version;
				}
	        } else
	            $result = 'unknown';
	    } else if (isset($_GET['json'])) { // envoi du catalogue au format JSON
		    header('Content-Type: application/json');
			echo json_encode($cache);
			exit;
	    } else if (isset($_GET['rss'])) { // envoi flux RSS des 10 dernières nouveautés
			// Look for the last version of each plugin
			sendRSS($cache, $cache_icons);
		    exit;
		}
	}
    header('Content-Type: text/plain');
	echo $result;
	exit;
}

function download_source() {
	$filename = 'repository2-'.VERSION.'.zip';
	if (! file_exists($filename)) {
		$zip = new ZipArchive();
		if ($zip->open($filename, ZipArchive::CREATE) === true) {
			$infos = <<< INFOS
<html lang="fr">
<head>
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<title>Dépôt de plugins pour Pluxml</title>
</head>
<body>
<p>
<a href="http://www.kazimentou.fr/pluxml-plugins2/">Voir démo</a><br />
<a href="https://github.com/bazooka07/Pluxml-repository">Github</a>
</p>
</body>
</html>
INFOS;
			$zip->addFromString('lisez-moi.html', $infos);
			$zip->addFile(basename(__FILE__));
			$zip->addFile('demo.php');
			$zip->close();
		}
	}
	echo 'http://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['SCRIPT_NAME']).'/'.$filename;
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="fr" lang="fr">
<head>
	<title>Dépôt de plugins pour Pluxml</title>
	<meta http-equiv="content-type" content="text/html;charset=utf-8" />
	<link rel="alternate" type="application/rss+xml" href="<?php echo $root; ?>?rss" title="Dépôt de plugins pour Pluxml" />
	<style>
		body {font: 1em; font-family: droid, arial, georgia,sans-serif; color: #000; background-color: rgba(214, 215, 200, 0.8);}
		#bandeau {display: flex; max-width: 900px; margin: 1em auto; padding: 0;}
		h1, h2, h3 {text-align: center;}
		h3 {margin: 0.5em 0;}
		#bandeau h2 {margin: 0;}
		#bandeau a {text-decoration: none;}
		#bandeau div:nth-of-type(2) {margin: auto; padding: 0;}
		#logo  {display: inline-block; width: 104px; height: 82px;}
		p:last-of-type, #detail + p {text-align: center;}
		h1 {margin-top: 0;}
		h1 + p {margin-bottom: 0;}
		a {padding: 0 4px;}
		a:hover {color: #FFF; background-color: green;}
		#detail {width: 99%; background-color: #FFF; border: 1px solid #A99; margin: 5px auto; border-spacing: 0;}
		#detail thead {background-color: #C2C2A6; }
		#detail th {padding: 5px 2px;}
		#detail tr {vertical-align: top;}
		#detail tbody tr:nth-of-type(even) {background-color: #DAE7E6;}
		#detail tbody tr:hover {background-color: #EDD780;}
		#detail tbody td:nth-of-type(3) {text-align: right; padding-right: 5px;}
		#detail tbody td:nth-of-type(7) {text-align: justify; padding: 0 5px;}
		#detail tbody td:last-of-type {text-align: center;}
		#detail + p {font-style: italic; font-size: 80%;}
		#detail .missing {display: inline-block; background: url('data:image/png;base64,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') no-repeat center center; }
		#detail img, #detail .missing {width: 48px; height: 48px;}
		#detail td:first-of-type a:hover {background-color: initial;}
		#bandeau a img, #detail a img, #detail td:first-of-type span {border: 2px solid transparent;}
		#bandeau a img:hover, #detail a img:hover, #detail td:first-of-type span:hover {border-color: green;}
		#help_nav {display: none;}
		#help_nav + div {display:none; position: absolute; top: 10em; right: 10%; width: 400px; margin:0; padding: 10px; background-color: #DFF5F1; border: 1px solid #4B7A9C; border-radius: 5px; text-align: justify; z-index: 10;}
		#help_nav:checked + div {display: block;}
		#help_nav + div ul {padding-left: 1em;}
		#help_nav + div + table #help::before {display: inline-block; width: 75px; text-align: right; content: 'Afficher'}
		#help_nav:checked + div + table #help::before {content: 'Masquer';}		
		#help_nav:checked + div + table #help {color: #FFF; background-color: green;}
		#help_nav + div ul {margin: 0.5em 0;}		
		th[title]:hover {background-color: yellow; cursor: help;}
		td {border-left: 1px solid #A99;}
		td:first-of-type, td:nth-of-type(2) {border-left: inherit;}
		td:nth-of-type(2) {padding-left: 5px;}
		#help {margin:0; padding:3px; cursor: pointer; white-space: nowrap;}
		.alert {padding: 1em 0; text-align: center; color: red;}
		.first td {border-top: 2px solid #A99;}
		.rss {margin: 0; padding: 0}
		.rss:hover {background-color: inherit;}
	</style>
</head>
<body>
	<div id="bandeau">
		<div>
			<img id="logo" src="data:image/png;base64,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" alt="Logo du site" />
		</div>
		<div>
			<h1>Dépôt&nbsp;de&nbsp;plugins multi-versions pour&nbsp;Pluxml</h1>
			<h2><a href="http://www.pluxml.org" target="_blank">Le site de PluXml</a></h2>
		</div>
		<h2><a class="rss" href="<?php echo $root; ?>?rss" target="_blank"><img width="32" height="32" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAMAAABEpIrGAAABSlBMVEVMaXEFBgauRgCeQAEFBgaUPAGoRAAFBgYFBgatRQCuRgCtRQD/+vT/nCr/niv/rkL/myn/qz7/mijmeAr/pzjcbAD/ny3/ojHxhRf88eTleQz/ozPddA3lew//qjz/r0T/oC7/qDr/pjbmdwr10q//oS//sEX/ozb/sUf/rUD+9+7fexr0iRvuuIL0kyb659OuRgD0z6n/skjgfR3ebwPshRjsgBL2jR7hcwf5mSvhgSPlk0LqgBPyx5vqfRH21rbkjjv00az/s0ntiBz3libeeBX7ojb/pDTedhHsrW70jBz/+/Xonlb7lSX7lCTkdgr22Lj/+vXsghX+9OrjijPts3n4kyTpoVrFXw3+libuqlTyih3CZRr/t1DppGD8njDwv4/3tVjpghb/+fL98ub11LL54cnvvIr43sXzmC7ujCHqqGf/xWr769ogWgLMAAAADHRSTlMAGcmIA5XvIAzoYH8uwYRVAAACFklEQVR4XoXM1ZIbMRBAUS/G3pWGGczMzAzLzMxB+P/XtBQ7DtRU7ktPa061y+VaW8w7tLjmgtbvbr45dHO3DsDzfD906P7ZAyA/LBbjUZ/PF40X/26YJyAej36264cvXzZYQH9GQTTK2r3cp855sz4i5vco8PkS8i4tl0lVNljWN48CluUJoCUz1ohPsL+iIJHg7fDuLH3vZYNPzKKA571Z+6y1fTQlGTvr5adR4PV6A1IjOzIm2zoVAysb8P6MgkAgIEkx6LFiZV6J6JUbUoBGgSQFs0bdvn2MxRrtPj3S6caCEomCYNA0ernC5aTdUNVKM3cMXVbMIImCzU1VJo/Jp60Tf0Rr6vCtpy7MTYgC01SN8DFJb7UV/0krCZ+drt+EKFDVSM1o7g0QQq8PbYapZshXaT+iqioFkUhEUQ5uxbMP8N46wftbOoiPVQV+UOCHFIwFzXqP0NvkAle3AegyB+8UKIrC1AxZTKctuPHU5a6tUxD9GqMoFDAMTqcKyUJK084QSm7tC6EBgPMqZpgp4EJwHB0dXosFhB40QfsK6yDEzUGZ3EzKgraHUEEUan1Yc3XhnwvCQQqh72XhoATraXkOcLoURuFSGnMhmAbmxDCCyc0AiB1RFncwTEM2djAZZM4AERzHYTIxhzFZBYHsFHjGjGNjD4ClK0cxvloC4F5ZzTu0uuIGsOxeeOfQgnvZ9d9+ABtblsTZgILxAAAAAElFTkSuQmCC" alt="Flux RSS" /></a></h2>
	</div>
	<input id="help_nav" type="checkbox" />
	<div>
		<h3>Aide</h3>
		<ul>
			<li>Télécharger l'archive</li>
			<li>Dézipper l'archive dans le dossier des plugins de Pluxml</li>
			<li>Un nouveau dossier a été créé. Vérifier qu'il a exactement le même nom que le plugin. Sinon renommer-le.</li>
			<li>Ensuite, se connecter sur l'administration de Pluxml pour activer le plugin</li>
		</ul>
	</div>
	<h3>Liste des plugins disponibles (<i>Cliquez sur l'icône pour avoir la version la plus récente</i>)</h3>
	<table id="detail"> <!-- catalogue starts here -->
		<thead>
			<tr>
				<th colspan="2">Nom du plugin</th>
				<th colspan="2" title="Version du plugin indiqué&#13;dans le fichier obligatoire&#13;infos.xml">Version / Archive</th>
				<th title="Cliquez sur un lien&#13;pour accéder au site de l'auteur">Auteur</th>
				<th title="Date de l'archive zip&#13;&#13;Cliquez sur le lien&#13;pour afficher le fichier&#13;infos.xml">Date</th>
				<th title="">Description (<label for="help_nav" id="help"> l'aide</label>)</th>
				<th>Pré-requis</th>
			</tr>
		</thead>
		<tbody>
<?php
if (isset($cache)) {
	// Now, let's play : on génére le contenu HTML
	$zip = new ZipArchive();
	foreach ($cache as $pluginName=>$versions) {
		$firstRow = true;
		foreach ($versions as $version=>$infos) {
			list($download, $filedate, $version, $repository, $author, $site, $description, $requirements) = $infos;
            $url = (strlen($site) > 0) ? '<a href="'.$site.'" target="_blank">'.$author.'</a>' : $author;
            $filedate = substr($filedate, 0, 10);
            $filename = basename($download); ?>
	    <tr<?php echo ($firstRow) ? ' class="first"' : ''; ?>>
<?php
			if ($firstRow) {
				// get the icon plugin
				$cell1 = '<span class="missing">&nbsp;</span>';
				if (array_key_exists($pluginName, $cache_icons)) {
					// list($imageType, $content) = $cache_icons[$pluginName];
					// list($filedateEpoc, $imageType, $content) = $cache_icons[$pluginName];
					list($filedateEpoc, $imageType, $sizeIcon, $content) = $cache_icons[$pluginName];									
					$cell1 = '<img src="data:image/x-icon;base64,'.base64_encode($content).'" alt="Icône" />';
				}
			    $cell1 = '<a href="'.$root.'?plugin='.$pluginName.'&download">'.$cell1.'</a>';
				$cell2 = $pluginName;
				$cell4 = '<a href="'.$root.'?plugin='.$pluginName.'" target="_blank">'.$version.'</a>';
				$cell6 = '<a href="'.$root.'?plugin='.$pluginName.'&infos" target="_blank">'.$filedate.'</a>'; 
			} else {
				$cell1 = '&nbsp;';
				$cell2 = '&nbsp;';
				$cell4 = $version;
				$cell6 = $filedate;
			}
			echo <<< VERSION
			<td>$cell1</td>
			<td><strong>$cell2</strong></td>
			<td>$cell4</td>
			<td><a href="$download">$filename</a></td>
			<td>$url</td>
			<td>$cell6</td>
			<td>$description</td>
			<td>$requirements</td>

VERSION;
			$firstRow = false;
		} ?>
		</tr>
<?php
	}
} else { ?>
		<tr>
			<td colspan="8" class="alert">Le dépôt ne contient aucun plugin.</td>
		</tr>	
<?php } ?>
		</tbody>
	</table> <!-- catalogue ends here -->
	<p>
		Lovely designed by theirs authors - 
		<a href="<?php download_source(); ?>">Download source of this page</a>
		version <?php echo VERSION; ?> - 
		Php <?php echo PHP_VERSION; ?>
	</p>
	<h3>Paramètres de l'url</h3>
	<ul>
		<li><strong><?php echo $root; ?>?plugin=xxxxxx</strong> renvoie le numéro de version du plugin xxxxxx au format texte</li>
		<li><strong><?php echo $root; ?>?plugin=xxxxxx&amp;download</strong> télécharge la dernière version du plugin xxxxxx</li>
		<li><strong><?php echo $root; ?>?plugin=xxxxxx&amp;infos</strong> renvoie les infos du plugin xxxxxx au format XML</li>
		<li><strong><?php echo $root; ?>?plugin=xxxxxx&amp;icon</strong> renvoie l'icône du plugin xxxxxx</li>
		<li><strong><?php echo $root; ?>?json</strong> renvoie toutes les infos des plugins pour toutes les versions au format JSON (<a href="<?php echo $root; ?>?json" target="_blank">Catalogue</a>)</li>
		<li><strong><?php echo $root; ?>?rss</strong> Récupère le flux RSS des 10 dernières mises à jour (<a href="<?php echo $root; ?>?rss" target="_blank">RSS</a>)</li>
	</ul>
</body>
</html>
