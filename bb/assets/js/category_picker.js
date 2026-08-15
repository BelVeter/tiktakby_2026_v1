/**
 * Выбор категории на bb/tovar_new_mod.php + создание новой в модалке.
 *
 * Поиск построен на общем ядре live_picker.js. Здесь — только специфика
 * категории: подстановка `dog_name` в форму, проверка вводимого названия на
 * дубль и опечатку, и сама модалка.
 *
 * Заменяет два сломанных механизма старой страницы:
 *   - select на 115 категорий с onchange="select_ch3(...)", который дёргал
 *     getXmlHttp() — а он определён только во фронтовых инклюдах, и на этой
 *     странице подключение закомментировано (tovar_new_mod.php:56). Поле
 *     «для договора» навсегда зависало на «... ждите ...»;
 *   - создание категории позиционным INSERT на 3 колонки из 9.
 */
(function () {
	'use strict';

	var CHECK_DELAY = 400;

	var picker = null;
	var subRazdelPicker = null;
	var els = {};
	var confirmPending = false;

	function $(id) {
		return document.getElementById(id);
	}

	function init() {
		els.search  = $('cat_search');
		els.hidden  = $('cat_select_new');
		els.dogName = $('cat_input_dog_new');
		els.openBtn = $('cat_create_open');
		els.modal   = $('cat_modal');

		if (!els.search || !els.hidden || !window.LivePicker) {
			return;
		}

		picker = new window.LivePicker({
			inputId:   'cat_search',
			hiddenId:  'cat_select_new',
			resultsId: 'cat_results',
			chosenId:  'cat_chosen',
			url:       '/bb/ajax_category_suggest.php',
			minQuery:  0,
			renderMeta: function (item) {
				return (item.tree_path ? item.tree_path : 'вне дерева каталога')
					+ ' · моделей: ' + item.models;
			},
			onChoose: function (item) {
				if (els.dogName) {
					els.dogName.value = item.dog_name || '';
				}
			}
		});

		if (els.openBtn) {
			els.openBtn.addEventListener('click', openModal);
		}

		initModal();
	}

	// --------------------------------------------------------------- модалка

	function initModal() {
		if (!els.modal) {
			return;
		}

		els.mName   = $('newcat_name');
		els.mDog    = $('newcat_dog_name');
		els.mUrl    = $('newcat_url_key');
		els.mWarn   = $('newcat_warning');
		els.mSave   = $('newcat_save');
		els.mCancel = $('newcat_cancel');

		// Подраздел: один контрол, два поведения. Клик по пустому полю
		// показывает весь список (minQuery: 0), ввод — фильтрует. Список из
		// 30 подразделов лежит в странице, запрос не нужен.
		if (window.SUB_RAZDELS) {
			subRazdelPicker = new window.LivePicker({
				inputId:   'newcat_sub_razdel_search',
				hiddenId:  'newcat_sub_razdel',
				resultsId: 'newcat_sub_razdel_results',
				items:     window.SUB_RAZDELS,
				minQuery:  0
			});
		}

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

		els.mUrl.addEventListener('input', function () {
			els.mUrl.dataset.touched = '1';
		});

		var checkTimer = null;
		els.mName.addEventListener('input', function () {
			confirmPending = false;
			els.mSave.value = 'создать категорию';

			// URL-ключ подставляем транслитом, пока его не правили руками.
			if (!els.mUrl.dataset.touched) {
				els.mUrl.value = translit(els.mName.value);
			}

			clearTimeout(checkTimer);
			checkTimer = setTimeout(checkName, CHECK_DELAY);
		});
	}

	function openModal() {
		els.modal.style.display = 'flex';
		els.mName.value = els.search.value.trim();
		els.mUrl.value = translit(els.mName.value);
		delete els.mUrl.dataset.touched;

		if (subRazdelPicker) {
			subRazdelPicker.reset();
		}

		warn('');
		confirmPending = false;
		els.mSave.value = 'создать категорию';
		els.mName.focus();

		if (els.mName.value) {
			checkName();
		}
	}

	function closeModal() {
		els.modal.style.display = 'none';
	}

	/** Живая проверка вводимого названия на точный дубль и на опечатку. */
	function checkName() {
		var name = els.mName.value.trim();

		if (name.length < 2) {
			warn('');
			return;
		}

		window.LivePicker.request(
			'/bb/ajax_category_suggest.php?check=1&q=' + encodeURIComponent(name),
			null,
			function (data) {
				if (data.exact) {
					warn('Такая категория уже есть: «' + data.exact.name + '» (#' + data.exact.id + '). '
						+ 'Закройте окно и выберите её в списке.', 'error');
					return;
				}

				if (data.similar && data.similar.length) {
					warn('Похожие категории уже есть: ' + names(data.similar)
						+ '. Проверьте, не дубль ли это.', 'hint');
					return;
				}

				warn('');
			}
		);
	}

	function names(list) {
		return list.map(function (item) { return '«' + item.name + '»'; }).join(', ');
	}

	function warn(text, kind) {
		els.mWarn.textContent = text || '';
		els.mWarn.className = 'catp__warn' + (text ? ' catp__warn--' + (kind || 'hint') : '');
		els.mWarn.style.display = text ? 'block' : 'none';
	}

	function submitModal() {
		var payload = {
			name:               els.mName.value.trim(),
			dog_name:           els.mDog.value.trim(),
			cat_url_key:        els.mUrl.value.trim(),
			main_sub_razdel_id: $('newcat_sub_razdel').value,
			cat_type:           $('newcat_cat_type').value,
			cat_sort:           $('newcat_cat_sort').value
		};

		if (confirmPending) {
			payload.confirm = '1';
		}

		els.mSave.disabled = true;

		window.LivePicker.request('/bb/ajax_category_create.php', payload, function (data) {
			els.mSave.disabled = false;

			if (data.error) {
				warn(data.error, 'error');
				return;
			}

			if (data.needs_confirm) {
				confirmPending = true;
				els.mSave.value = 'всё равно создать';
				warn('Похожие категории: ' + names(data.similar)
					+ '. Если это точно другая категория — нажмите ещё раз.', 'hint');
				return;
			}

			if (data.ok) {
				picker.choose(data.category);
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

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
