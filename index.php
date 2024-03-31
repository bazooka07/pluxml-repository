<?php

/*
 * http://www.mondepot.com/index.php?plugin=xxxxxx renvoie le numéro de version au format texte
 * http://www.mondepot.com/index.php?plugin=xxxxxx&infos renvoie les infos du plugin au format XML
 * xxxxxx est le nom de l'archive Zip du plugin, stocké dans le dossier PLUGINS_FOLDER défini ci-dessous.
 * L'archive et le plugin ont le même nom par convention.
 *
 * Ajustez la constante PLUGINS_FOLDER selon vos désirs.
 *
 * @author	Jean-Pierre Pourrez, Thomas Ingles
 * @version	2018-12-27 - gère les multiples versions des plugins et génére un cache
 * @license	GNU General Public License, version 3
 * @see		http://www.pluxml.org
 *
 * Les catalogues des plugins, themes, scripts sont stockés dans le dossier WORKDIR au format JSON.
 * Les icônes des plugins et les aperçus (preview) des thêmes sont stockés dans les dossiers WORKDIR/ASSETS/plugins
 * et WORKDIR/ASSETS/thêmes repscativement dans leurs formats natifs
 *
 * Les collections des archives zip d'extensions pour PluXml sont classés en pages : plugins, thêmes et scripts
 *
 * */

// Customize these constants for your site
const SITE_STATIC = false; // Générer des pages pour un site statique (pages Github)
const SITE_TITLE = 'Kazimentou';
const SITE_OWNER = 'Bazooka07';
const SITE_AUTHOR = 'Jean-Pierre Pourrez';
const SITE_DESCRIPTION = 'Plugins, thèmes, scripts pour le C.M.S. PluXml';

if(!class_exists('ZipArchive')) {
	$message = 'Class ZipArchive is missing in PHP library';
	header('HTTP/1.0 500 '.$message);
	header('Content-type: text/plain');
	echo $message;
	exit;
}

// CORS support
// https://developer.mozilla.org/en-US/docs/Web/HTTP/Server-Side_Access_Control
if(isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");

	if($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
		header('Access-Control-Allow-Credentials: false');
		header('Access-Control-Max-Age: 7200');    // cache for two hours
	    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
	        header('Access-Control-Allow-Methods: GET, OPTIONS'); //Make sure you remove those you do not want to support
		}
	    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
	        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
		}
	    exit(0);
	}
}


define('VERSION', date('Y-m-d', filemtime(__FILE__)));
const PLUGINS_FOLDER = 'plugins';
const THEMES_FOLDER = 'themes';
const SCRIPTS_FOLDER = 'scripts';
const WORKDIR = 'workdir/';
const ASSETS = 'assets/';
const LAST_RELEASES = 'latest/';
const STYLESHEET_FILENAME = ASSETS.'style.css';

$ALL_PAGES = array(PLUGINS_FOLDER, THEMES_FOLDER, SCRIPTS_FOLDER);

const LIFETIME = 7 * 24 * 3600; // in seconds (Unix time) for rebuilding $cache and $cache_icons
define('MAX_FILESIZE', (preg_match('#\.free\.fr$#', $_SERVER['SERVER_NAME'])) ? 1048576 : 2097152 /*4194304 */); // 1 mega-bytes : 2 mega-bytes 4 mega-bytes

const INFOS_FILE = 'infos.xml';
define('INFOS_FILE_LEN', strlen(INFOS_FILE));
const EXT_RSS = '-rss.xml';

$pageInfos = array(
	'plugins'	=> array('imgSize' =>  48, 'imgAlt' => 'Icon', 'titleSingle' => 'plugin'),
	'themes'	=> array('imgSize' => 300, 'imgAlt' => 'Thème', 'titleSingle' => 'thème'),
	'scripts'	=> array('imgSize' =>  48, 'imgAlt' => 'Icon', 'titleSingle' => 'script')
);

# some icons in base64
const DEFAULT_ICON = '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';
const LOGO = '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';
const BACKGROUND = 'iVBORw0KGgoAAAANSUhEUgAAAAsAAAALCAYAAACprHcmAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH4QUYBxwZy9JxnwAAANpJREFUGNMt0DGOFFEQBNGX1S2BhMH974cLEhbDbv9KjMFLI9KIyK+fP3rfI6c+f79c37/JjJ7DIhdzUJPe9mF3nefIfuo5JuQavZagYwJqT0UlNam15BHImNSdvI9P2YzTEautBq0Mp9yeD+3q67GvD+fPyB12pNUrItq45xqd2z03T11fv+jFXTLjKB3mdtfQ6uAeM2NbW/Lfo6mexySRVLc8j+yRxiZaiLTSuoVOzFWdMQnXG5p4iwqH0ZFedjlbZ6stlgzG9D3nOX8dZMf1Ruy7OFu6amn9A9efm50CFFfrAAAAAElFTkSuQmCC';
const RSS = 'iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAMAAABEpIrGAAABSlBMVEVMaXEFBgauRgCeQAEFBgaUPAGoRAAFBgYFBgatRQCuRgCtRQD/+vT/nCr/niv/rkL/myn/qz7/mijmeAr/pzjcbAD/ny3/ojHxhRf88eTleQz/ozPddA3lew//qjz/r0T/oC7/qDr/pjbmdwr10q//oS//sEX/ozb/sUf/rUD+9+7fexr0iRvuuIL0kyb659OuRgD0z6n/skjgfR3ebwPshRjsgBL2jR7hcwf5mSvhgSPlk0LqgBPyx5vqfRH21rbkjjv00az/s0ntiBz3libeeBX7ojb/pDTedhHsrW70jBz/+/Xonlb7lSX7lCTkdgr22Lj/+vXsghX+9OrjijPts3n4kyTpoVrFXw3+libuqlTyih3CZRr/t1DppGD8njDwv4/3tVjpghb/+fL98ub11LL54cnvvIr43sXzmC7ujCHqqGf/xWr769ogWgLMAAAADHRSTlMAGcmIA5XvIAzoYH8uwYRVAAACFklEQVR4XoXM1ZIbMRBAUS/G3pWGGczMzAzLzMxB+P/XtBQ7DtRU7ktPa061y+VaW8w7tLjmgtbvbr45dHO3DsDzfD906P7ZAyA/LBbjUZ/PF40X/26YJyAej36264cvXzZYQH9GQTTK2r3cp855sz4i5vco8PkS8i4tl0lVNljWN48CluUJoCUz1ohPsL+iIJHg7fDuLH3vZYNPzKKA571Z+6y1fTQlGTvr5adR4PV6A1IjOzIm2zoVAysb8P6MgkAgIEkx6LFiZV6J6JUbUoBGgSQFs0bdvn2MxRrtPj3S6caCEomCYNA0ernC5aTdUNVKM3cMXVbMIImCzU1VJo/Jp60Tf0Rr6vCtpy7MTYgC01SN8DFJb7UV/0krCZ+drt+EKFDVSM1o7g0QQq8PbYapZshXaT+iqioFkUhEUQ5uxbMP8N46wftbOoiPVQV+UOCHFIwFzXqP0NvkAle3AegyB+8UKIrC1AxZTKctuPHU5a6tUxD9GqMoFDAMTqcKyUJK084QSm7tC6EBgPMqZpgp4EJwHB0dXosFhB40QfsK6yDEzUGZ3EzKgraHUEEUan1Yc3XhnwvCQQqh72XhoATraXkOcLoURuFSGnMhmAbmxDCCyc0AiB1RFncwTEM2djAZZM4AERzHYTIxhzFZBYHsFHjGjGNjD4ClK0cxvloC4F5ZzTu0uuIGsOxeeOfQgnvZ9d9+ABtblsTZgILxAAAAAElFTkSuQmCC';

const STYLESHEET = <<< STYLESHEET
* {margin:0; padding: 0; box-sizing: border-box;}
body {font-family: 'Noto Sans',Droid,Arial,Georgia,Sans-Serif; color: #000; background-color: #ebeff2; background-image: url('background.png');
}
header .cell-border {margin-top: 0.7rem;}
.cell-border.logo {margin-top: 2rem;}
.logo a {display: block;}
.logo img {max-width:100%;}
.nowrap > * { margin: 0 0.3rem; white-space: nowrap;}
#bandeau {display: grid; grid-template-columns: 60px auto 60px; padding: 0.3rem 0.7rem 0 0; background-color: #ddd; line-height: 1.2; font-size: 82%;}
#bandeau h2 {grid-column: span 3;}
#bandeau span {white-space: nowrap;}
#bandeau > div:first-of-type > div:last-of-type {margin-top: 2.2rem;}

#menu {position: fixed; top: 0; left: 0;}
#nav-toggle {display: none;}
#nav-toggle + ul {
	position: absolute;
	top: 3rem;
	width: 7rem;
	left: -7rem;
	background-color: #eee;
	text-align: center;
	list-style: none;
	transition: left 0.3s ease;
}
#nav-toggle:checked + ul {left: 0;}
#menu label {
	font-size: 150%;
	padding: 0.3rem;
	background-color: #ddd;
}
#menu a { display: block; padding: 0.3rem 0.5rem; text-decoration: none; color: inherit;}
#menu a:hover { color: #fff;}

h1, h2, h3, h4, h5 {text-align: center;}
h3 {margin: 0.5em 0;}
h3 + p {margin:0; padding:0; text-align: center; font-style: italic;}
h3 + p a {padding: 0 10px;}
.txt-center { text-align: center; text-indent: 0;}
#bandeau a {text-decoration: none;}
#bandeau div {padding: 0; text-align: center;}
#bandeau label {cursor: pointer;}
/* p:last-of-type, #detail + p {text-align: center;} */
h1 {margin-top: 0;}
h1 + p {margin-bottom: 0;}
label, a {padding: 0 4px;}
label:hover, a:hover {color: #FFF; background-color: green;}
.url-help {margin: 0.5rem;}
.url-help ul {padding-left: 1.5rem; margin: 0;}
#detail {width: 99%; background-color: #FFF; border: 1px solid #A99; margin: 5px auto; border-spacing: 0;}
#detail thead {background-color: #C2C2A6; }
#detail th {padding: 5px 2px;}
#detail tr {vertical-align: top;}
#detail tbody tr:nth-of-type(even) {background-color: #DAE7E6;}
#detail tbody tr:hover {background-color: #EDD780;}
#detail tbody td:nth-of-type(3) {text-align: right; padding-right: 5px;}
#detail tbody td:nth-of-type(7) {text-align: justify; padding: 0 5px; white-space: normal; min-width: 20rem;}
#detail tbody td:nth-of-type(8) {white-space: normal; }
#detail tbody td:last-of-type {text-align: center;}
#detail + p {font-style: italic; font-size: 80%;}
#detail td:first-of-type a:hover {background-color: initial;}
#bandeau a img, #detail a img, #detail td:first-of-type span {border: 2px solid transparent;}
#bandeau a img:hover, #detail a img:hover, #detail td:first-of-type span:hover {border-color: green;}
#help_nav {display: none;}
#help_nav + div {display:none; position: fixed; top: 10em; right: 10%; width: 400px; margin:0; padding: 10px; background-color: #DFF5F1; border: 1px solid #4B7A9C; border-radius: 5px; text-align: justify; z-index: 10;}
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
.rss:hover {background-color: inherit;}

#catalogue {padding-right: 0.7rem;}
#catalogue article {
	display: flex;
	flex-direction: column;
	max-width: 23rem;
	margin: 0.5rem auto;
	padding: 0;
	background-color: #fff;
	border: none;
	box-shadow: 4px 4px 3px #aaa;
	border-radius: 0.5rem;
}
#catalogue article header {
	margin: 0;
	padding: 0;
	background-color: #556;
	color: orange;
	text-align: center;
	letter-spacing: 0.2rem;
}
#catalogue article header a {
	display: inline-block;
	width: 100%;
	margin: 0;
	padding: 0.3rem 0;
	color: inherit;
	text-decoration: none;
}
#catalogue article header a:hover {
	color: #fff;
}
#catalogue article section {
	flex-grow: 1;
}
#catalogue article footer {
	margin: 0;
	background-color: orange;
	text-align: center;
}
#catalogue article p { margin: 0.3rem 0; }
#catalogue article section span {
	display: inline-block;
	width: 4rem;
	text-align: right;
	margin-right: 0.5rem;
	font-style: italic;
	color: #aaa;
}
#catalogue article footer span {
	white-space: nowrap;
}
#catalogue .descr {
	padding: 0 0.5rem;
	font-family: "Skolar Regular","Roboto Slab","Droid Serif",Cambria,Georgia,"Times New Roman",Times,serif;
	font-size: 120%;
	overflow: auto;
}
body.page-themes #catalogue .descr {
	text-align: center;
}
#catalogue .descr > a {
	text-decoration: none;
}
body.page-plugins #catalogue .descr > a {
	float: left;
	margin-right: 0.5rem;
	padding: 0;
}
div.scrollable-table { overflow: auto; white-space: nowrap; padding-bottom: 0.8rem;}
div.nothing { padding: 2rem 0; color: red; font-size: 250%; font-weight: bold;}

@media screen and (min-width: 41rem) {
	#catalogue {display: flex; justify-content: space-around; flex-wrap: wrap;}
	#catalogue article {width: 48%; margin: 0.5rem 0.3rem;}
}
@media screen and (min-width: 46rem) {
	#bandeau {position: sticky; top:0; padding: 0.3rem 2rem 0; font-size: 100%;}
	#bandeau h1, #bandeau h2 { padding: 0 1rem; }
	.logo { margin-top: 0;
}
@media screen and (min-width: 63rem) {
	#bandeau {position: static; padding-bottom: 0.5rem; padding: 0.3rem 2% 0.5rem;}
	#bandeau > div:first-of-type {padding: 0 1.5%;}
	#bandeau h1 {margin-top: 1rem; lline-height: 1.6;}

	#menu {position: sticky;}
	#menu label[for="nav-toggle"] {display: none;}
	#nav-toggle + ul {position: initial; width: 100%; display: flex; justify-content: center; padding-top: 0.5rem; background-color: transparent;}
	#menu li {margin: 0 1rem;}
	#menu li a {display: block; padding: 0.2rem 1rem; background-color: #888; color: #fff; border-radius: 0.5rem; letter-spacing: 0.1rem;}
	#menu li a:hover {background-color: green;}

	#catalogue article {width: 31.8%;}
	#catalogue.count-2 {justify-content: center;}
	#catalogue.count-2 article { margin: 0.5rem 1rem;}

	.url-help {max-width: 64rem; margin: 0.5rem auto;}
}
@media screen and (min-width: 86rem) {
	#catalogue article {width: 25.5%;}
	#catalogue.count-3 {justify-content: center;}
	#catalogue.count-3 article { margin: 0.5rem 1rem;}
}
@media screen and (min-width: 96rem) {
	#catalogue article {width: 19.3%;}
	#catalogue.count-4 {justify-content: center;}
	#catalogue.count-4 article { margin: 0.5rem 1rem;}
}
STYLESHEET;

$root = htmlspecialchars($_SERVER['PHP_SELF']);
// Only used if $page == 'plugins'
$specialHTMLPages = array('index', 'all');

function singlePage($page) {
	return rtrim($page, "sx \t\n\r\0\x0B");
}

function getPage($key=false) {
	if(empty($key)) {
        if(empty($_GET)) { return 'plugins'; } // Here is the homepage
		$key = 'page';
	} elseif(!in_array($key, array('json', 'latest', 'rss', 'xml', 'cat', 'lastUpdated'))) {
		// this parameter is not allowed
		return false;
	}

	if(!empty($_GET[$key])) {
		$page = htmlspecialchars($_GET[$key]);
		if(!empty($page) and in_array($page, $GLOBALS['ALL_PAGES'])) {
			return $page;
		} else {
			// Anti-hacking
			exit('What did you expect ?');
		}
	} else {
		// Choice by default
		return 'plugins';
	}
}

function getBaseUrl1() {
	$proto = (!empty($_SERVER['HTTPS']) and strtolower($_SERVER['HTTPS']) == 'on') ? 'https' : 'http';
	$path = preg_replace('@'.basename(__FILE__).'$@', '', $_SERVER['PHP_SELF']);
	return "${proto}://${_SERVER['HTTP_HOST']}${path}";
}

/*
 * Recherche dans l'archive Zip, une entrée finissant par infos.xml
 * */
function searchInfosFile(ZipArchive $zipFile) {
	for ($i=0; $i<$zipFile->numFiles; $i++) {
		$filename = $zipFile->getNameIndex($i);
		if(substr($filename, - INFOS_FILE_LEN) == INFOS_FILE) {
			return $filename;
			break;
		}
	}

	return false;
}

function buildCatalogue(&$zipsList, $page) {
	$cache = array();
	$imgsFolder = WORKDIR."assets/$page";

	$result = true;
	$zip = new ZipArchive();
	foreach($zipsList as $filename) {
		if($res = $zip->open($filename)) {
			if($infoFile = searchInfosFile($zip)) {
				$infosXML = $zip->getFromName($infoFile);
				try {
					$infos = array(
						'download' => $filename
					);
					# On parse le fichier infos.xml
					@$doc = new SimpleXMLElement($infosXML);
					foreach($doc as $key=>$value) {
						$infos[$key] = $value->__toString();
					}

					# On récupère la date du fichier infos.xml
					$infosStats = $zip->statName($infoFile);
					$filedateEpoc = $infosStats['mtime'];
					$infos['filedate'] = date('Y-m-d', $filedateEpoc);

					switch($page) {
						case PLUGINS_FOLDER:
							$keyName = getPluginName($zip);
							$imgPattern = '@^'.$keyName.'/icon\.(jpe?g|png|gif)$@i';
							break;
						case THEMES_FOLDER:
							$keyName = getThemeName($zip);
							$imgPattern = '@^'.$keyName.'/preview\.(jpe?g|png|gif)$@i';
							break;
						case SCRIPTS_FOLDER:
							// $keyName = getThemeName($zip);
							break;
						default:
					}

					// look for an image
					$imgPath = false;
					if(!empty($imgPattern)) {
						for ($i=0; $i<$zip->numFiles; $i++) {
							$filename = $zip->getNameIndex($i);
							if(preg_match($imgPattern, $filename, $matches)) {
								$img = "$imgsFolder/${keyName}.$matches[1]";
								if(!file_exists($img) or filemtime($img) < $filedateEpoc) {
									if($zip->extractTo($imgsFolder, $filename)) {
										$from = $imgsFolder.'/'.$filename;
										rename($from, $img);
										touch($img, $filedateEpoc);
									}
								}
								if(empty($imgPath)) {
									$imgPath = $img;
								}
								break;
							}
						}
					}

					// on vérifie si le plugin est déjà référencé dans le cache
					if(! array_key_exists($keyName, $cache)) {
						$cache[$keyName] = array(
							'img'		=> $imgPath,
							'versions'	=> array()
						);
					}
					$cache[$keyName]['versions'][$infos['version']] = $infos;
					if(!empty($imgPath)) {
						$cache[$keyName]['img'] = $imgPath;
					}

				} catch (Exception $e) {
					error_log(date('Y-m-d H:i').' - fichier infos.xml incorrect pour le plugin '.$f.' - Ligne n°'.$e->getLine().': '.$e->getMessage()."\n", 3, dirname(__FILE__).'/errors.log');
					$result = false;
				}
			}
			$zip->close();
		}
	}

	// supprime les dossiers créés par l'extraction des iĉones ou des previews
	foreach(glob($imgsFolder.'/*', GLOB_ONLYDIR) as $folder) {
		rmdir($folder);
	}

	// tri et sauvegarde du cache sur le disque dur
	$lastReleases = array();
	if(!empty($cache)) {
		ksort($cache);

		// tri décroissant des versions
		foreach(array_keys($cache) as $k) {
			uksort($cache[$k]['versions'], function($a, $b) {
				return -version_compare($a, $b);
			});
			$lastVersion = array_keys($cache[$k]['versions'])[0];
			$lastReleases[$k] = $cache[$k]['versions'][$lastVersion];
			$lastReleases[$k]['img'] = $cache[$k]['img'];
		}
	}
	// encodage et sauvegarde au format JSON
	$filename = WORKDIR.$page.'.json';
	if(file_put_contents($filename, json_encode($cache, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) {
		$error = "No rights for writing in the $filename file.\nFeel free for calling the webmaster.";
	}

	if(!empty($lastReleases)) {
		// build parameter for the callback request (JSONP)
		$hostname = ((isset($_SERVER['HTTPS']) and !empty($_SERVER['HTTPS'])) ? 'https://' : 'http://').$_SERVER['HTTP_HOST'];
		$url_base = preg_replace('@'.basename(__FILE__).'$@', '', htmlspecialchars($_SERVER['PHP_SELF']));
		$callbacks = array(
			'hostname'		=> $hostname,
			'urlBase'		=> $url_base,
			'page'			=> $page,
			'lastUpdate'	=> date('c', filemtime($filename)),
			'items'			=> $lastReleases
		);
		$filename = WORKDIR.LAST_RELEASES.$page.'.json';
		if(file_put_contents($filename, json_encode($callbacks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) {
			$error = "No rights for writing in the $filename file.\nFeel free for calling the webmaster.";
		}
	}

	return $result;
}

/*
 * Vérifie si le cache a besoin d'une mise à jour
 * On ne fait la mise à jour que toutes les LIFETIME minutes
 * */
function checkCatalogue($page) {

	$catalogue = WORKDIR.$page.'.json';
	$zipsList = glob($page.'/*.zip');

	$outdated = !file_exists($catalogue);
	if(!$outdated) {
		$refTime = filemtime($catalogue);
		$outdated = (time() - $refTime > LIFETIME);
	}

	if(!$outdated) {
		foreach($zipsList as $zipfile) {
			if($refTime < filemtime($zipfile)) {
				// one file is more recent than the cache
				$outdated = true;
				break;
			}
		}
	}

	return ($outdated and buildCatalogue($zipsList, $page));
}

function getCatalog($page, $expires=true) {
	$filename = WORKDIR.$page.'.json';
	if($expires) {
		header("Expires: ".date('r', filemtime($filename) + 86400)); // 24 heures
	}
	return json_decode(file_get_contents($filename), true);
}

/*
 * Recherche la déclaration d'une classe dans un fichier .php
 * dans l'archive Zip, pour connaitre le nom réel du plugin
 * */
function getPluginName(ZipArchive $zipFile) {
	$result = false;
	for ($i=0; $i<$zipFile->numFiles; $i++) {
		$filename = $zipFile->getNameIndex($i);
		if(substr($filename, -4) == '.php') {
			$content = $zipFile->getFromName($filename);
			if(preg_match('/\bclass\s+(\w+)\s+extends\s+plxPlugin\b/', $content, $matches) === 1) {
				$result = $matches[1];
				break;
			} else if(preg_match('/^([\w-]+)\/(?:article|static[^\.]*)\.php$/', $filename, $matches) === 1) {
				// archive zip d'un théme demandé par niqnutn
				$result = $matches[1];
				break;
			}
		}
	}
	return $result;
}

function getThemeName(ZipArchive $zipFile) {
	return preg_replace('@^(\w[^/]*).*@', '$1',$zipFile->getNameIndex(0));
}

function exportLatest($page, $static=false) {
	if(empty($page)) { exit('No  catalog for '.$page); }

	if(!isset($_SERVER['HTTP_ORIGIN'])) {
		header('HTTP/1.1 403 Access Forbidden');
		header('Content-Type: text/plain');
		echo "I have a dream : You're in a jail !\n\n";
		exit;
	}

	checkCatalogue($page);
	$filename = WORKDIR.LAST_RELEASES."$page.json";
	header('Content-Type: application/json');
	header('Content-Length: ' . filesize($filename));
	readfile($filename);
}

function exportJSON($page, $static=false) {
	if(empty($page)) { exit('No  catalog for '.$page); }

	checkCatalogue($page);
	$filename = WORKDIR."$page.json";
	header('Content-Type: application/json');
	header('Content-Length: ' . filesize($filename));
	readfile($filename);
}

function exportRSS($page, $static=false) {
	if(!in_array($page, $GLOBALS['ALL_PAGES'])) { exit('Are You Jupiter ?'); }

	$updated = checkCatalogue($page); // true if the catalog for this page is just updated
	// Don't make a static file if not useful
	$staticFilename = $page.EXT_RSS;
	if(!$updated and ($static and file_exists($staticFilename))) { return; }

	$filename = WORKDIR.$page.'.json';
	$lastBuildDate = date('r', filemtime($filename));
	$sitename = SITE_TITLE;
	$root = getBaseUrl1();
	if($static) {
		$link = ($page == 'plugins') ? 'index' : $page;
		$link .= '.html';
		$href = $root.$page.EXT_RSS;
	} else {
		$bs = basename(__FILE__);
		$link = $bs;
		if($page != 'plugins') {
			$link .= "?page=$page";
		}
		$href = "${root}${bs}?rss";
		if($page != 'plugins') { $href .= "=${page}"; };
	}
	$description = SITE_DESCRIPTION;
	$ver = VERSION;
	$output = <<< RSS_STARTS
<?xml version="1.0"  encoding="UTF-8" ?>
<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:atom="http://www.w3.org/2005/Atom">
	<channel>
		<title>$sitename, playstore for Pluxml</title>
		<link>${root}${link}</link>
		<description>$description</description>
		<lastBuildDate>$lastBuildDate</lastBuildDate>
		<atom:link xmlns:atom="http://www.w3.org/2005/Atom" rel="self" type="application/rss+xml" href="$href" />
		<generator>Repository for Pluxml v3.0 ($ver)</generator>
		<skipDays>
			<day>Sunday</day>
		</skipDays>\n
RSS_STARTS;

	$temp = array();
	$cache = getCatalog($page);
	foreach($cache as $item=>$infos) {
		$lastVersion = array_keys($infos['versions'])[0];
		$lastRelease = $infos['versions'][$lastVersion];
		if(!empty($infos['img'])) { $lastRelease['img'] = $infos['img']; }
		$temp[$lastRelease['filedate'].'-'.$item] = $lastRelease;
	}

	if(!empty($temp)) {
		krsort($temp);
		$lastUpdates = array_slice($temp, 0, 10); // 10 items for the RSS feed
		foreach($lastUpdates as $item=>$infos) {
			$title = $infos['title'];
			$pubDate = date('r', strtotime($infos['filedate']));
			$description = htmlspecialchars($infos['description'], ENT_COMPAT | ENT_XML1);
			$author = htmlspecialchars($infos['author'], ENT_COMPAT | ENT_XML1);
			$guid = $link.'_'.$item;

			if(!empty($infos['img']) and file_exists($infos['img'])) {
				$length = filesize($infos['img']);
				$size = getimagesize($infos['img']);
				$enclosure = <<< ENCLOSURE
\n			<enclosure url="${root}${infos['img']}" length="$length" type="${size['mime']}" />
ENCLOSURE;
			} else
				$enclosure = '';

			$output .= <<< ITEM
		<item>
			<title>$title</title>
			<link>${root}${infos['download']}</link>
			<description>$description</description>
			<dc:creator>$author</dc:creator>
			<pubDate>$pubDate</pubDate>$enclosure
			<guid>$guid</guid>
		</item>\n
ITEM;
		}
	}

	$output .=  <<< RSS_ENDS
	</channel>
</rss>\n
RSS_ENDS;

	if(!$static) {
		$defaultMime = 'application/rss+xml';
		$mimetype = (strpos($_SERVER['HTTP_ACCEPT'], $defaultMime) !== false) ? $defaultMime : 'application/xml';
		header('Content-Type: '.$mimetype);
		header('Content-Length: '.strlen($output));
		echo $output;
	} else {
		file_put_contents($staticFilename, $output);
	}
}

function exportXML($page, $static=false) {
	if(!in_array($page, $GLOBALS['ALL_PAGES'])) { exit('Do you know Einstein ?'); }

	$updated = checkCatalogue($page); // true if the catalog for this page is just updated
	// Don't make a static file if not useful
	$staticFilename = $page.'.xml';
	if(!$updated and ($static and file_exists($staticFilename))) { return; }

	$url_base = getBaseUrl1();
	$repo_url = $url_base;
	$repo_site = $url_base;
	if($static) {
		$repo_url .= $staticFilename;
		$repo_site .= ($page == 'plugins') ? 'index' : $page;
		$repo_site .= '.html';
	} else {
		$repo_url .= basename(__FILE__).'?xml';
		$repo_site .= basename(__FILE__);
		if($page != 'plugins') {
			$repo_url .= "=$page";
			$repo_site .= "?page=$page";
		}
	}

	$repo_icon = '';
	foreach(array('png', 'jpg', 'gif', 'jpeg') as $ext) {
		$iconName = "icon.$ext";
		if(is_readable($iconName)) {
			$repo_icon = $url_base.$iconName;
		}
	}

	$output = <<< BEGIN_XML
<?xml version="1.0" encoding="UTF-8"?>
<document>
BEGIN_XML;

	/*
	 * // Désactivé pour assurer une certaine compatibilité avec l'existant
	$title = SITE_TITLE;
	$author = SITE_AUTHOR;
	$name = SITE_OWNER;

	$filename = WORKDIR.LAST_RELEASES."$page.json";
	$version = "$page-".date('ymdHi', filemtime($filename));

	$description = SITE_DESCRIPTION;
	$output .= <<< XML_PLUS
	<repo>
		<repo_title>$title</repo_title>
		<repo_author>$author</repo_author>
		<repo_url>$repo_url</repo_url>
		<repo_version_url>$url_base?lastUpdated</repo_version_url>
		<repo_version>$version</repo_version>
		<repo_site>$repo_site</repo_site>
		<repo_description><![CDATA[$description]]></repo_description>
		<repo_name>$name</repo_name>
		<repo_icon>$repo_icon</repo_icon>
	</repo>\n
XML_PLUS;
	*/

	$cache = getCatalog($page);
	foreach($cache as $item=>$infos) {
		foreach($infos['versions'] as $version=>$release) {
			$filedate = substr($release['filedate'], 0, 10);
			$filename = $url_base.$release['download'];
			$name = basename($release['download']);
			$site = (!empty($release['site'])) ? $release['site'] : '';
			break;
		}
	$icon = (!empty($infos['img'])) ? $url_base.WORKDIR.ASSETS.$infos['img'] : '';
	$output .= <<< PLUGIN
	<plugin>
		<title>$item</title>
		<author>${release['author']}</author>
		<version>$version</version>
		<date>$filedate</date>
		<site>$site</site>
		<description><![CDATA[${release['description']}]]></description>
		<name>$name</name>
		<file>$filename</file>
		<icon>$icon</icon>
	</plugin>\n
PLUGIN;
	}
	$output .= <<< END_XML
</document>\n
END_XML;

	if(!$static) {
		header('Content-Type: text/xml');
		header('Content-Length: '.strlen($output));
		echo $output;
	} else {
		file_put_contents($staticFilename, $output);
	}
}

function getRepoVersion($page) {
	checkCatalogue($page);
	$filename = WORKDIR.LAST_RELEASES."$page.json";
	$content =
	header('Content-Type: text/plain');
	echo date('ymd', filemtime($filename))."\n\n";
}

function callbackRequest($page, $callback) {
	if(empty($callback) or empty($page)) {
		header('HTTP/1.0 400 bad request');
		return;
	}

	checkCatalogue($page);
	$filename = WORKDIR.LAST_RELEASES."$page.json";
	if(file_exists($filename)) {
		$hostname = ((isset($_SERVER['HTTPS']) and !empty($_SERVER['HTTPS'])) ? 'https://' : 'http://').$_SERVER['HTTP_HOST'];
		$url_base = preg_replace('@'.basename(__FILE__).'$@', '', htmlspecialchars($_SERVER['PHP_SELF']));
		$title = SITE_TITLE;
		$comments = <<< EOT
/* ----- $title's repository @ $hostname$url_base ----- */\n\n
EOT;
		$output = $comments.$callback.'("'.addslashes(file_get_contents($filename)).'");';
		header('Cache-Control: max-age=3600');
		header('Content-Type: application/javascript; charset=utf-8');
		echo $output;
		exit;
	} else {
		header('HTTP/1.0 404 Not Found');
	}
}

function download_source() {
	$filename = 'repository3-'.VERSION.'.zip';
	if(! file_exists($filename)) {
		$zip = new ZipArchive();
		if($zip->open($filename, ZipArchive::CREATE) === true) {
			$description = SITE_DESCRIPTION;
			$infos = <<< INFOS
<html lang="fr">
<head>
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<title>$description</title>
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
			foreach(array(basename(__FILE__), 'demo.php', 'README.md', 'LICENSE.txt') as $entry) {
				if(file_exists($entry)) {
					$zip->addFile($entry);
				}
			}
			$zip->close();
		}
	}
	// echo 'http://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF']).'/'.$filename;
	echo $filename;
}

function getLastRelease($page, $item) {
	$cache = getCatalog($page);
	if(array_key_exists($item, $cache)) {
		$lastVersion = array_keys($cache[$item]['versions'])[0];
		return $cache[$item]['versions'][$lastVersion];
	}

	return false;
}

/**
 * Send an zip archive of plugin, theme, script.
 * */
function cmd_download($page, $item) {
	$lastRelease = getLastRelease($page, $item);
	if(!empty($lastRelease)) {
		$filename = $lastRelease['download'];
		if(!file_exists($filename)) {
			header('HTTP/1.0 404 Not Found');
			exit;
		}
		$filesize = filesize($filename);
		if($filesize < MAX_FILESIZE) {
			header('Content-Type: application/zip');
			header('Content-Length: ' . $filesize);
			header('Content-Disposition: attachment; filename="'.basename($filename).'"');
			readfile($filename);
		} else {
			header('Status: 307 Temporary Redirect', false, 307);
			header('Location: '.$filename);
		}
	} else {
		header('HTTP/1.0 404 Not Found');
	}
}

/**
 * Send the content of the infos.xml file of the last version of an item.
 * */
function cmd_infos($page, $item) {
	$lastRelease = getLastRelease($page, $item);
	if(!empty($lastRelease)) {
		$filename = $lastRelease['download'];
		if(!file_exists($filename)) {
			header('HTTP/1.0 404 Not Found');
			exit;
		}
		$zip = new ZipArchive();
		if($res = $zip->open($lastRelease['download'])) {
			if($infoFile = searchInfosFile($zip)) {
				// send content of infos.xml
				$result = $zip->getFromName($infoFile);
				header('Content-Type: text/xml');
				header('Content-Length: ' .strlen($result));
				echo $result;
				exit;
			}
			$zip->close();
		} else {
			header('HTTP/1.0 404 Not Found');
			exit;
		}
	} else {
		header('HTTP/1.0 404 Not Found');
	}
}

/**
 * Send an image : icon for plugin, preview for theme.
 * */
function cmd_icon($page, $item) {
	$cache = getCatalog($page);
	if(array_key_exists($item, $cache)) {
		$filename = $cache[$item]['img'];
		if(!empty($filename)) {
			header('Status: 307 Temporary Redirect', false, 307);
			header('Location: '.$filename);
		} else {
			header('HTTP/1.0 404 Not Found');
			header('Content-Type: text/plain');
			echo "Icon not found for $item".singlePage($page)."\n\n";
		}
	} else {
		header('HTTP/1.0 404 Not Found');
	}
}

/**
 * Crée les différents dossiers et installe les fichiers statiques (assets).
 * */
function init() {
	$folders = array_merge(
		array(WORKDIR, WORKDIR.ASSETS, WORKDIR.LAST_RELEASES, ASSETS),
		$GLOBALS['ALL_PAGES'],
		array_map(function($item) { return WORKDIR.ASSETS."/$item"; }, $GLOBALS['ALL_PAGES'])
	);
	foreach($folders as $dir1) {
		if(!is_dir($dir1) and !mkdir($dir1, 0755, true)) {
			header('Content-Type: text/plain;charset=uft-8');
			$dir1 = trim($dir1, '/');
			printf("Unable to create %s/$dir1 folder.", __DIR__) ;
			exit;
		}
	}

	foreach(array(
		'logo'			=> LOGO,
		'default-icon'	=> DEFAULT_ICON,
		'background'	=> BACKGROUND,
		'rss'			=> RSS
	) as $name=>$content) {
		$filename = ASSETS.$name.'.png';
		if(!file_exists($filename)) {
			$fp = fopen($filename, 'wb');
			fwrite($fp, base64_decode($content));
			fclose($fp);
		}
	}

	if(!file_exists(STYLESHEET_FILENAME) or filemtime(STYLESHEET_FILENAME) < filemtime(__FILE__)) {
		file_put_contents(STYLESHEET_FILENAME, STYLESHEET);
	}

	// clean up caches for an update of this script
	$lastUpdateProg = filemtime(__FILE__);
	foreach(glob(WORKDIR.'/*.json') as $file1) {
		if(file_exists($file1) and (filemtime($file1) < $lastUpdateProg))
			unlink($file1);
	}
}

/* ================== the core starts here =============== */
init();

header("Cache-Control: public");
/* ----------------- Parsing the parameters of the url --------------------- */
// One item is required
foreach($ALL_PAGES as $page) {
	if(!isset($_GET[singlePage($page)])) {
		continue;
	}

	$item = htmlspecialchars($_GET[singlePage($page)]);
	if(!empty($item)) {
		foreach(array('download', 'infos', 'icon') as $cmd) {
			if(isset($_GET[$cmd])) {
				$process = 'cmd_'.$cmd;
				if(function_exists($process)) {
					checkCatalogue($page);
					$process($page, $item);
				}
				exit;
			}
		}
	}
}

// working with catalogs
if(isset($_GET['callback'])) {
	$callback = htmlspecialchars($_GET['callback']);
	callbackRequest(getPage('cat'), $callback);
	exit;
} else {
	foreach(array(
		'latest'		=> 'exportLatest',
		'json'			=> 'exportJSON',
		'rss'			=> 'exportRSS',
		'lastUpdated'	=> 'getRepoVersion',
		'xml'			=> 'exportXML'
	) as $cmd=>$process) {
		if(isset($_GET[$cmd])) {
			$process(getPage($cmd));
			exit;
		}
	}
}

// Go for displaying a HTML page
// if(!isset($_GET['all_versions']) and !isset($_GET['grille'])) {}
$page = getPage();
$updateStatic = checkCatalogue($page); // true if the catalog for this page is just updated
$cache = getCatalog($page, true);
$root = getBaseUrl1();

$displayAll = (isset($_GET['all_versions']));
$displayGrid = ($displayAll or isset($_GET['grille']));

$passMax = (SITE_STATIC and $updateStatic) ? ($page == 'plugins') ? 3 : 2 : 1;
for($pass=0; $pass < $passMax; $pass++) {
	if($pass == 0) {
		$hrefRSS = $root.basename(__FILE__).'?rss';
		if($page != 'plugins') {
			$hrefRSS .= "=$page";
		}
		$hrefVersions = basename(__FILE__);
		if($displayAll) {
			$hrefVersions .= '?grille';
		} else {
			$hrefVersions .= '?all_versions';
		}

		$hrefDisplayMode = basename(__FILE__);
		if(!$displayGrid) {
			$hrefDisplayMode .= '?grille';
		}
	} else {
		// Builds HTML pages for a static site
		ob_start();
		$hrefRSS = $root.$page.EXT_RSS;

		if($page == 'plugins') {
			$displayAll = ($pass == 2);
			$displayGrid = $displayAll;
			$hrefDisplayMode = ($pass == 2) ? 'index.html' : 'all.html';
			$hrefVersions = '';
		}
	}
	$label_versions = ($displayAll) ? 'la dernière version seulement' : 'toutes les versions';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, user-scalable=yes, initial-scale=1.0">
	<link rel="icon" type="image/png" href="<?= $root; ?>icon.png" />
	<title><?= SITE_DESCRIPTION; ?></title>
	<link rel="alternate" type="application/rss+xml" href="<?= $hrefRSS; ?>" title="<?= SITE_TITLE; ?>" />
	<link rel="stylesheet" href="<?= STYLESHEET_FILENAME; ?>" />
</head>
<body class="page-<?= $page; ?>">
	<header id="bandeau">
		<div class="logo cell-border">
			<a href="https://www.pluxml.org" target="_blank" title="Le site de PluXml"><img src="<?= ASSETS; ?>logo.png" alt="Logo du site" /></a>
		</div>
		<div>
			<h1><span>Dépôt d'extensions</span> multi-versions <span>pour Pluxml</span></h1>
		</div>
		<div class="cell-border">
<?php $href = ($pass == 0) ? basename(__FILE__).'?rss='.$page : $page.EXT_RSS; ?>
			<a class="rss" href="<?= $href; ?>" target="_blank"><img width="32" height="32" src="<?= ASSETS; ?>rss.png" alt="Flux RSS" /></a><br />
			<label for="help_nav" style="margin-top: 1rem;">Aide</label><br />
<?php
if($page == 'plugins') {
	$caption = (!$displayGrid and !$displayAll) ? 'Tableau' : 'Standard';
?>
			<a href="<?= $hrefDisplayMode ?>">$caption</a>
<?php
}
?>
		</div>
		<h2 class="nowrap">Welcome at <span><?= SITE_TITLE; ?>'s repository</span> and get <span>the finest addons</span><span>for PluXml</span></h2>
	</header>
	<nav id="menu">
		<label for="nav-toggle" title="Menu">☰</label>
		<input id="nav-toggle" type="checkbox" />
		<ul>
<?php
	if($pass == 0) {
        $url = basename($_SERVER['PHP_SELF']);
?>
            <li><a href="<?= $url; ?>">Plugins</a></li>
			<li><a href="<?= $url; ?>?page=themes">Thèmes</a></li>
			<li><a href="<?= $url; ?>?page=scripts">Scripts</a></li>
<?php
    } else {
?>
            <li><a href="index.html">Plugins</a></li>
			<li><a href="themes.html">Thèmes</a></li>
			<li><a href="scripts.html">Scripts</a></li>
<?php
    }
?>
		</ul>
	</nav>
	<section>
	<input id="help_nav" type="checkbox" />
	<div>
		<h3>Aide</h3>
		<ul>
			<li>Télécharger l'archive zip du plugin ou du thème</li>
			<li>Dézipper l'archive dans le dossier des plugins ou des thèmes de Pluxml</li>
			<li>Un nouveau dossier a été créé. Vérifier qu'il a exactement le même nom que le plugin. Sinon renommer-le.</li>
			<li>Ensuite, se connecter sur l'administration de Pluxml pour activer le plugin ou le thème</li>
		</ul>
	</div>
<?php
if($pass == 0 and !empty($cache) and $page == 'plugins') {
?>
	<p class="txt-center">
		<a href="<?= $hrefVersions; ?>">Afficher <?= $label_versions; ?></a>
	</p>
<?php
}

$imgSize = $pageInfos[$page]['imgSize'];
$imgAlt = $pageInfos[$page]['imgAlt'];
$titleSingle = $pageInfos[$page]['titleSingle'];
$itemType = singlePage($page);

if(!empty($cache)) {
	if(!$displayGrid) {
		$extra = (count($cache) < 5) ? ' class="count-'.count($cache).'"' : '';
		echo <<< EOT
		<div id="catalogue"$extra>\n
EOT;

		foreach ($cache as $itemName=>$infos) { // on boucle sur tous les plugins
			$version = array_keys($infos['versions'])[0];
			$latestRelease = $infos['versions'][$version];
			$filedate = (array_key_exists('date', $latestRelease)) ? substr($latestRelease['date'], 0, 10) : '';
			$site1 = '';
			if(array_key_exists('site', $latestRelease)) {
				$site = $latestRelease['site'];
				$site1 = <<< SITE
\n				<p><span>Site:</span><a href="$site" target="_blank">$site</a></p>
SITE;
		}
			if($pass == 0) {
				$href = "${root}".basename(__FILE__)."?${itemType}=${itemName}&download";
			} else {
				$href = $latestRelease['download'];
			}
			$src = (!empty($infos['img'])) ? $infos['img'] :  ASSETS.'default-icon.png';
?>
			<article>
				<header><a href="<?= $href ?>" download><?= $itemName ?></a></header>
				<section>
					<p><span>Auteur:</span><?= $latestRelease['author'] ?></p>
					<p><span>Version:</span><?= $version ?></p>
					<p><span>Date:</span><?= $filedate ?></p><?= $site1 ?>
					<div class="descr">
						<a href="<?= $href ?>" download><img src="<?= $src ?>" width="<?= $imgSize ?>" height="<?= $imgSize ?>" alt="<?= $imgAlt ?>" /></a>
						<p><?= $latestRelease['description'] ?></p>
					</div>
				</section>
				<footer>Cliquez sur le titre <span>pour télécharger le <?= $titleSingle ?></span></footer>
			</article>
<?php
		}
?>
		</div>
<?php
	} else { // début mode grille
?>
	<div class="scrollable-table">
		<table id="detail"> <!-- catalogue starts here -->
			<thead>
				<tr>
					<th colspan="2">Nom du plugin</th>
					<th colspan="2" title="Version du plugin indiqué&#13;dans le fichier obligatoire&#13;infos.xml">Version / Archive</th>
					<th title="Cliquez sur un lien&#13;pour accéder au site de l'auteur">Auteur</th>
					<th title="Date de l'archive zip&#13;&#13;Cliquez sur le lien&#13;pour afficher le fichier&#13;infos.xml">Date</th>
					<th>Description</th>
					<th>Pré-requis</th>
				</tr>
			</thead>
			<tbody>
<?php
		// Now, let's play : on génére le contenu HTML
		foreach($cache as $itemName=>$infos) { // on boucle sur tous les plugins
			$firstRow = true;
			foreach($infos['versions'] as $version=>$release) {
				if(!empty($release['site'])) {
					$url = <<< URL
<a href="${release['site']}" target="_blank">${release['author']}</a>
URL;
				} else {
					$url = $release['author'];
				}
				$filedate = substr($release['filedate'], 0, 10);
				$filename = basename($release['download']);
?>
			<tr <?= ($displayAll and $firstRow) ? ' class="first"' : ''; ?>>
<?php
				if($firstRow) {
					// get the icon plugin
					$imgSrc = (!empty($infos['img'])) ? $infos['img'] : ASSETS.'default-icon.png';
					if($pass == 0) {
						$href = basename(__FILE__)."?${itemType}=${itemName}&download";
					} else {
						$href = $release['download'];
					}
					$cell1 = <<< CELL1
<a href="$href" download><img src="$imgSrc" width="$imgSize" height="$imgSize" alt="$imgAlt" /></a>
CELL1;
					$cell2 = $itemName;
					$cell3 = '<a href="'.basename(__FILE__).'?plugin='.$itemName.'&infos" target="_blank">'.$version.'</a>';
				} else {
					$cell1 = '&nbsp;';
					$cell2 = '&nbsp;';
					$cell3 = $version;
				}
				$description = (!empty($release['description'])) ? $release['description'] : '&nbsp;';
				$requirements = (!empty($release['requirements'])) ? $release['requirements'] : '&nbsp;';
?>
					<td><?= $cell1 ?></td>
					<td><strong><?= $cell2 ?></strong></td>
					<td><?= $cell3 ?></td>
					<td><a href="<?= $release['download'] ?>" download><?= $filename ?></a></td>
					<td><?= $url ?></td>
					<td><?= $filedate ?></td>
					<td><?= $description ?></td>
					<td><?= $requirements ?></td>
		</tr>
<?php
				if(!$displayAll) {
					break;
				} else {
					$firstRow = false;
				}
			}
		}
?>
			</tbody>
		</table> <!-- catalogue ends here -->
	</div>
<?php
	} // fin mode grille
} else { ?>
	<article>
		<div class="txt-center nothing">Le dépôt ne contient aucun <?= $titleSingle; ?>.</div>
	</article>
<?php
}
?>
	</section>
	<footer>
<?php
	if($pass == 0) {
?>
		<p class="nowrap txt-center">
			<span>Lovely designed by theirs authors</span>
			<a href="<?php download_source(); ?>">Download source of this page</a>
			<span>version <?= VERSION; ?></span>
			<span>Php <?= PHP_VERSION; ?></span>
		</p>
<?php
		if(!empty($cache)) {
			// Il y a des plugins, thêmes, ...
			$baseUrl = $root . basename(__FILE__);
?>
		<h3>Paramètres de l'url :</h3>
		<div class="url-help scrollable-table">
			<ul>
				<li><strong><?= $baseUrl ?>?plugin=xxxxxx</strong> renvoie le numéro de version du plugin xxxxxx au format texte</li>
				<li><strong><?= $baseUrl ?>?plugin=xxxxxx&amp;download</strong> télécharge la dernière version du plugin xxxxxx</li>
				<li><strong><?= $baseUrl ?>?plugin=xxxxxx&amp;icon</strong> renvoie l'icône du plugin xxxxxx</li>
				<li><strong><?= $baseUrl ?>?json</strong> renvoie les infos pour <a href="<?= $baseUrl ?>?json" target="_blank">toutes les versions des plugins au format JSON</a></li>
				<li><strong><?= $baseUrl ?>?callback=myCallback</strong> renvoie les <a href="<?= $baseUrl ?>?callback=myCallback">infos de la dernière version de chaque plugin au format JSON</a> <em>avec rappel de la fonction myCallback (JSONP)</em></li>
				<li><strong><?= $baseUrl ?>?plugin=xxxxxx&amp;infos</strong> renvoie les infos du plugin xxxxxx au format XML</li>
				<li><strong><?= $baseUrl ?>?xml</strong> renvoie les infos de la <a href="<?= $baseUrl ?>?xml" target="_blank">dernière version de chaque plugin au format XML</a></li>
				<li><strong><?= $baseUrl ?>?lastUpdated</strong> renvoie la <a href="<?= $baseUrl ?>?lastUpdated" target="_blank">date du plugin le plus récent mis en ligne</a></li>
				<li><strong><?= $baseUrl ?>?rss</strong> Récupère le <a href="<?= $baseUrl ?>?rss" target="_blank">flux RSS des 10 dernières mises à jour</a></li>\n
			</ul>
		</div>
<?php
		}
	} elseif($pass > 0) {
		// 2nd pass
	}
?>
	</footer>
</body>
</html>
<?php
	if($pass > 0) {
		$htmlPage = ($page != 'plugins') ? $page : $specialHTMLPages[$pass - 1];
		file_put_contents($htmlPage.'.html', ob_get_clean());
		if($pass == 1) {
			// creating flux rss
			exportRSS($page, true);
		}
	}
}
?>
