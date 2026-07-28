/**
 * Живой поиск категории + создание новой в модалке. Без библиотек.
 *
 * Разметку и список подразделов отдаёт bb/tovar_new_mod.php, данные —
 * bb/ajax_category_suggest.php и bb/ajax_category_create.php.
 *
 * Заменяет два сломанных механизма старой страницы:
 *   - select на 150+ категорий с onchange="select_ch3(...)", который дёргал
 *     несуществующий getXmlHttp() и навсегда вешал поле «для договора»
 *     на «... ждите ...»;
 *   - создание категории позиционным INSERT на 3 колонки из 9.
 */
(function () {
	'use strict';

	var SEARCH_DELAY = 200;
	var MIN_QUERY = 1;

	var els = {};
	var state = { items: [], active: -1, timer: null, confirmPending: false };

	function $(id) {
		return document.getElementById(id);
	}

	function init() {
		els.search   = $('cat_search');
		els.hidden   = $('cat_select_new');
		els.results  = $('cat_results');
		els.chosen   = $('cat_chosen');
		els.dogName  = $('cat_input_dog_new');
		els.openBtn  = $('cat_create_open');
		els.modal    = $('cat_modal');

		if (!els.search || !els.hidden) {
			return;
		}

		els.search.addEventListener('input', onSearchInput);
		els.search.addEventListener('keydown', onSearchKeydown);
		els.search.addEventListener('focus', onSearchInput);
		document.addEventListener('click', onDocumentClick);

		if (els.openBtn) {
			els.openBtn.addEventListener('click', openModal);
		}

		initModal();
	}

	// ---------------------------------------------------------------- поиск

	function onSearchInput() {
		var query = els.search.value.trim();

		clearTimeout(state.timer);

		if (query.length < MIN_QUERY) {
			renderResults([]);
			return;
		}

		state.timer = setTimeout(function () {
			request('/bb/ajax_category_suggest.php?q=' + encodeURIComponent(query), null, function (data) {
				state.items = data.items || [];
				state.active = -1;
				renderResults(state.items);
			});
		}, SEARCH_DELAY);
	}

	function onSearchKeydown(event) {
		if (!state.items.length) {
			return;
		}

		if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
			event.preventDefault();
			var step = event.key === 'ArrowDown' ? 1 : -1;
			state.active = (state.active + step + state.items.length) % state.items.length;
			highlight();
		} else if (event.key === 'Enter') {
			if (state.active >= 0) {
				event.preventDefault();
				choose(state.items[state.active]);
			}
		} else if (event.key === 'Escape') {
			renderResults([]);
		}
	}

	function renderResults(items) {
		els.results.innerHTML = '';

		if (!items.length) {
			els.results.style.display = 'none';
			return;
		}

		items.forEach(function (item, index) {
			var row = document.createElement('div');
			row.className = 'catp__item';
			row.setAttribute('data-index', String(index));

			var title = document.createElement('span');
			title.className = 'catp__name';
			title.textContent = item.name;
			row.appendChild(title);

			var meta = document.createElement('span');
			meta.className = 'catp__meta';
			meta.textContent = (item.tree_path ? item.tree_path : 'вне дерева каталога')
				+ ' · моделей: ' + item.models;
			row.appendChild(meta);

			if (!item.in_tree) {
				row.appendChild(badge('вне каталога', 'catp__badge--warn'));
			}
			if (item.fuzzy) {
				row.appendChild(badge('похоже на запрос', 'catp__badge--hint'));
			}

			row.addEventListener('mouseenter', function () {
				state.active = index;
				highlight();
			});
			row.addEventListener('click', function () {
				choose(item);
			});

			els.results.appendChild(row);
		});

		els.results.style.display = 'block';
	}

	function badge(text, extraClass) {
		var el = document.createElement('span');
		el.className = 'catp__badge ' + extraClass;
		el.textContent = text;
		return el;
	}

	function highlight() {
		var rows = els.results.querySelectorAll('.catp__item');
		for (var i = 0; i < rows.length; i++) {
			rows[i].classList.toggle('catp__item--active', i === state.active);
		}
	}

	function choose(item) {
		els.hidden.value = item.id;
		els.search.value = item.name;

		if (els.dogName) {
			els.dogName.value = item.dog_name || '';
		}

		els.chosen.innerHTML = '';
		els.chosen.appendChild(document.createTextNode('Выбрана категория #' + item.id + ' — ' + item.name));
		if (!item.in_tree) {
			els.chosen.appendChild(badge('нет в меню каталога', 'catp__badge--warn'));
		}
		els.chosen.style.display = 'block';

		renderResults([]);
	}

	function onDocumentClick(event) {
		if (els.results && !els.results.contains(event.target) && event.target !== els.search) {
			renderResults([]);
		}
	}

	// --------------------------------------------------------------- модалка

	function initModal() {
		if (!els.modal) {
			return;
		}

		els.mName    = $('newcat_name');
		els.mDog     = $('newcat_dog_name');
		els.mUrl     = $('newcat_url_key');
		els.mWarn    = $('newcat_warning');
		els.mSave    = $('newcat_save');
		els.mCancel  = $('newcat_cancel');

		els.mCancel.addEventListener('click', closeModal);
		els.mSave.addEventListener('click', submitModal);

		els.modal.addEventListener('click', function (event) {
			if (event.target === els.modal) {
				closeModal();
			}
		});

		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && els.modal.style.display === 'flex') {
				closeModal();
			}
		});

		// URL-ключ подставляем транслитом, пока оператор его не правил вручную.
		els.mUrl.addEventListener('input', function () {
			els.mUrl.dataset.touched = '1';
		});

		var checkTimer = null;
		els.mName.addEventListener('input', function () {
			state.confirmPending = false;
			els.mSave.value = 'создать категорию';

			if (!els.mUrl.dataset.touched) {
				els.mUrl.value = translit(els.mName.value);
			}

			clearTimeout(checkTimer);
			checkTimer = setTimeout(checkName, SEARCH_DELAY * 2);
		});
	}

	function openModal() {
		els.modal.style.display = 'flex';
		els.mName.value = els.search.value.trim();
		els.mUrl.value = translit(els.mName.value);
		delete els.mUrl.dataset.touched;
		warn('');
		state.confirmPending = false;
		els.mSave.value = 'создать категорию';
		els.mName.focus();

		if (els.mName.value) {
			checkName();
		}
	}

	function closeModal() {
		els.modal.style.display = 'none';
	}

	/** Живая проверка вводимого названия на дубль и опечатку. */
	function checkName() {
		var name = els.mName.value.trim();

		if (name.length < 2) {
			warn('');
			return;
		}

		request('/bb/ajax_category_suggest.php?check=1&q=' + encodeURIComponent(name), null, function (data) {
			if (data.exact) {
				warn('Такая категория уже есть: «' + data.exact.name + '» (#' + data.exact.id + '). '
					+ 'Закройте окно и выберите её в списке.', 'error');
				return;
			}

			if (data.similar && data.similar.length) {
				warn('Похожие категории уже есть: ' + data.similar.map(function (item) {
					return '«' + item.name + '»';
				}).join(', ') + '. Проверьте, не дубль ли это.', 'hint');
				return;
			}

			warn('');
		});
	}

	function warn(text, kind) {
		els.mWarn.textContent = text || '';
		els.mWarn.className = 'catp__warn' + (text ? ' catp__warn--' + (kind || 'hint') : '');
		els.mWarn.style.display = text ? 'block' : 'none';
	}

	function submitModal() {
		var payload = {
			name:               els.mName.value.trim(),
			name_en:            $('newcat_name_en').value.trim(),
			name_lt:            $('newcat_name_lt').value.trim(),
			dog_name:           els.mDog.value.trim(),
			cat_url_key:        els.mUrl.value.trim(),
			main_sub_razdel_id: $('newcat_sub_razdel').value,
			cat_type:           $('newcat_cat_type').value,
			cat_sort:           $('newcat_cat_sort').value
		};

		if (state.confirmPending) {
			payload.confirm = '1';
		}

		els.mSave.disabled = true;

		request('/bb/ajax_category_create.php', payload, function (data) {
			els.mSave.disabled = false;

			if (data.error) {
				warn(data.error, 'error');
				return;
			}

			if (data.needs_confirm) {
				state.confirmPending = true;
				els.mSave.value = 'всё равно создать';
				warn('Похожие категории: ' + data.similar.map(function (item) {
					return '«' + item.name + '»';
				}).join(', ') + '. Если это точно другая категория — нажмите ещё раз.', 'hint');
				return;
			}

			if (data.ok) {
				choose(data.category);
				closeModal();
			}
		}, function () {
			els.mSave.disabled = false;
			warn('Не удалось связаться с сервером. Попробуйте ещё раз.', 'error');
		});
	}

	// ------------------------------------------------------------- утилиты

	var TRANSLIT = {
		а: 'a', б: 'b', в: 'v', г: 'g', д: 'd', е: 'e', ё: 'e', ж: 'zh', з: 'z',
		и: 'i', й: 'y', к: 'k', л: 'l', м: 'm', н: 'n', о: 'o', п: 'p', р: 'r',
		с: 's', т: 't', у: 'u', ф: 'f', х: 'h', ц: 'c', ч: 'ch', ш: 'sh',
		щ: 'sch', ъ: '', ы: 'y', ь: '', э: 'e', ю: 'yu', я: 'ya'
	};

	function translit(value) {
		var out = '';
		var lower = String(value).toLowerCase();

		for (var i = 0; i < lower.length; i++) {
			var ch = lower[i];
			if (TRANSLIT.hasOwnProperty(ch)) {
				out += TRANSLIT[ch];
			} else if (/[a-z0-9]/.test(ch)) {
				out += ch;
			} else {
				out += '-';
			}
		}

		return out.replace(/-+/g, '-').replace(/^-|-$/g, '');
	}

	function request(url, payload, onSuccess, onError) {
		var xhr = new XMLHttpRequest();
		xhr.open(payload ? 'POST' : 'GET', url, true);

		if (payload) {
			xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
		}

		xhr.onreadystatechange = function () {
			if (xhr.readyState !== 4) {
				return;
			}
			if (xhr.status !== 200) {
				if (onError) { onError(); }
				return;
			}
			try {
				onSuccess(JSON.parse(xhr.responseText));
			} catch (e) {
				if (onError) { onError(); }
			}
		};

		xhr.send(payload ? encodeForm(payload) : null);
	}

	function encodeForm(payload) {
		var parts = [];
		for (var key in payload) {
			if (payload.hasOwnProperty(key)) {
				parts.push(encodeURIComponent(key) + '=' + encodeURIComponent(payload[key]));
			}
		}
		return parts.join('&');
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
