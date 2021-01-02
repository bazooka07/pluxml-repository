(function() {
	'use strict';

	const catalogDiv = document.getElementById('catalogue');
	const rss = document.querySelector('a.rss');
	const footer = document.getElementById('footer');
	const allVersionsTbody = document.getElementById('all-versions-tbody');
	const spinner = document.getElementById('spinner');
	const FIELDS_PATTERN = /#(download|filedate|title|author|version|date|site|description|img|filename)#/g;
	const NO_ITEM = 'Il n\'y a aucun élément';
	const IMG_SIZES = {
		plugins: '48',
		themes: '300',
		scripts: '48'
	};

	var imgSize = IMG_SIZES.plugins;
	var page = 'plugins';

	function myTemplate() { /* #BEGIN#
				<header>
					<a href="#download#" download>#title#</a>
				</header>
				<section>
					<p><span>Auteur:</span>#author#</p>
					<p><span>Version:</span>#version#</p>
					<p><span>Date:</span>#date#</p>
					<p><span>Site:</span><a href="#site#" target="_blank">#site#</a></p>
					<div class="descr">
						<a href="#download#" download><img src="#img#" width="#IMG_SIZE#" height="#IMG_SIZE#" alt="Icon" /></a>
						<p>#description#</p>
					</div>
				</section>
				<footer>Pour télécharger, cliquez sur le titre <span>ou l'image</span></footer>
#END# */ return myTemplate.toString().replace(/^.*#BEGIN#/m, '').replace(/#END#.*\n.*$/m, '').replace(/#IMG_SIZE#/g, imgSize).trim() + "\n";
	}

	function myFirstRowTemplate() { /* #BEGIN#
				<td><a href="#download#" download><img src="#img#" width="#IMG_SIZE#" height="#IMG_SIZE#" alt="Icon" /></a></td>
				<td><strong>#title#</strong></td>
				<td>#version#</td>
				<td><a href="#download#" download>#filename#</a></td>
				<td><a href="#site#" target="_blank">#author#</a></td>
				<td>#date#</td>
				<td class="wrap">#description#</td>
#END# */ return myFirstRowTemplate.toString().replace(/^.*#BEGIN#/m, '').replace(/#END#.*\n.*$/m, '').replace(/#IMG_SIZE#/g, imgSize).trim() + "\n";
	}

	function myRowTemplate() { /* #BEGIN#
				<td>&nbsp;</td>
				<td>&nbsp;</td>
				<td>#version#</td>
				<td><a href="#download#" download>#filename#</a></td>
				<td><a href="#site#" target="_blank">#author#</a></td>
				<td>#date#</td>
				<td class="wrap">#description#</td>
#END# */ return myRowTemplate.toString().replace(/^.*#BEGIN#/m, '').replace(/#END#.*\n.*$/m, '').replace(/#IMG_SIZE#/g, imgSize).trim() + "\n";
	}

	function myFooterTemplate() { /* #BEGIN#
		<span>Télécharger le catalogue au <a href="workdir/latest/#PAGE#.json" download="">format JSON</a></span>
		<span> ou au <a href="workdir/xml/#PAGE#.xml" download="">format XML</a> et <a href="workdir/xml/#PAGE#.version" download="">n° version</a></span>
#END# */ return myFooterTemplate.toString().replace(/^.*#BEGIN#/m, '').replace(/#END#.*\n.*$/m, '') + "\n";
	}

	function errorDisplay(message) {
		if(catalogDiv != null) {
			catalogDiv.className = '';
			catalogDiv.innerHTML = '<div class="error">' + message + '</div>';
			return;
		}
		if(allVersionsTbody != null) {
			allVersionsTbody.innerHTML = '<tr><td class="error" colspan="7">' + message + '</td></tr>';
			return;
		}
		console.log(message);
	}

	const xhr = new XMLHttpRequest();
	xhr.onload = function(event) {
		if(spinner != null) { spinner.classList.remove('active'); }

		if(xhr.status != 200) {
			errorDisplay('Erreur n°' + xhr.status + ' (<em>' + xhr.statusText +  '</em>)');
			return;
		}

		const datas = JSON.parse(xhr.responseText);
		if(datas == null) {
			errorDisplay('Mauvais format de catalogue');
			return;
		}

		const noEmptyItemsList = ('items' in datas || typeof datas.length == 'undefined');
		if(rss != null) {
			rss.href = (noEmptyItemsList) ? 'workdir/rss/' + page + '.xml' : '';
		}
		if(footer != null) {
			footer.innerHTML = (noEmptyItemsList) ? myFooterTemplate().replace(/#PAGE#/g, page) : "<span>&nbsp;</span>\n<span>&nbsp;</span>";
		}

		if(catalogDiv != null) {
			if('items' in datas && 'page' in datas) {
				catalogDiv.className = datas.page; // Taille des images

				if(typeof datas.items == 'object') {
					const pattern = myTemplate();
					catalogDiv.textContent = '';
					for(var i in datas.items) {
						const article = document.createElement('ARTICLE');
						article.innerHTML = pattern.replace(FIELDS_PATTERN, function(value, p1) {
							if(p1 in datas.items[i]) { return datas.items[i][p1]; }
							switch(p1) {
								case 'img' : return 'assets/' + datas.page + '.png'; break;
								default: return '';
							}
						});
						catalogDiv.appendChild(article);
					}
				} else {
					errorDisplay(NO_ITEM);
				}
			}
			return;
		}

		if(allVersionsTbody != null)  {
			if(typeof datas.length != 'undefined' && datas.length == 0) {
				errorDisplay(NO_ITEM);
				return;
			}

			allVersionsTbody.textContent = '';
			for(var plugin in datas) {
				var firstRow = true;
				var rowPattern = myFirstRowTemplate();
				for(var version in datas[plugin].versions) {
					const row = document.createElement('TR');
					if(firstRow) { row.className = 'latest-version'; }
					const infos = datas[plugin].versions[version];
					row.innerHTML = rowPattern.replace(FIELDS_PATTERN, function(value, p1) {
						if(p1 in infos) { return infos[p1]; }
						switch(p1) {
							case 'filename' : return infos['download'].replace(/^.*\//, '');
							case 'img' : return ('img' in datas[plugin]) ? datas[plugin].img : 'assets/' + page + '.png'; break;
							default: return '';
						}
					});
					allVersionsTbody.appendChild(row);
					if(firstRow) {
						firstRow = false;
						rowPattern = myRowTemplate();
					}
				}
			}
		}
	};

	xhr.onerror = function(event) {
		if(spinner != null) { spinner.classList.remove('active'); }
		alert('Quelque chose s\'est mal passée');
	}

	function displayCatalog(itemsType) {
		if(spinner != null) { spinner.classList.add('active'); }
		imgSize = IMG_SIZES[page];
		const path = (allVersionsTbody != null) ? '' : 'latest/';
		const url = window.location.href.replace(/[^\/]*$/, '') + 'workdir/' + path + itemsType + '.json';
		// console.log(url);
		xhr.open('GET', url);
		xhr.send();
	}

	const tabs = document.querySelectorAll('#menu-ul input[type="radio"]');
	for(var i= 0, iMax=tabs.length; i< iMax; i++) {
		tabs[i].onchange = function(event) {
			if(event.target.checked) {
				event.preventDefault();
				page = event.target.value;
				displayCatalog(page);
				const toggle = document.querySelector('#menu > input[type="checkbox"]');
				if(toggle != null) { toggle.checked = false; }
			}
		};
	}

	document.addEventListener('DOMContentLoaded', function(event) {
		tabs[0].click();
	});
})();
