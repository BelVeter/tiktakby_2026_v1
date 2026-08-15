/**
 * Живой поиск по справочнику. Общее ядро, без библиотек.
 *
 * Используется на двух страницах:
 *   bb/tovar_new_mod.php — категория (+ создание в модалке, см. category_picker.js);
 *   bb/tovar_new.php     — категория и производитель (создания нет, только ссылки).
 *
 * Виджет пишет выбранное значение в скрытое поле с тем же id/name, что было у
 * прежнего <select>, и дёргает колбэк. Благодаря этому старый каскад
 * «категория -> производитель -> модель» на bb/tovar_new.php продолжает читать
 * `document.getElementById('cat_select_old').value` и работает без переделки.
 *
 * window.LivePicker(config):
 *   inputId    — поле ввода
 *   hiddenId   — скрытое поле со значением (id / строка)
 *   resultsId  — контейнер выпадающего списка
 *   chosenId   — строка «выбрано ...» (необязательно)
 *   url        — endpoint подсказок, отдаёт {items:[...]}
 *   items      — вместо url: готовый массив (локальная фильтрация, без запроса)
 *   valueKey   — какое поле элемента класть в hidden (по умолчанию 'id')
 *   extraParams— функция, возвращающая объект доп. параметров запроса
 *   renderMeta — функция(item) -> строка второй строки в подсказке
 *   onChoose   — функция(item) после выбора
 *   minQuery   — с какой длины запроса искать (по умолчанию 1)
 *   onItems    — функция(items, query) -> items, вызывается после получения
 *                результатов до отрисовки; можно перегруппировать или отфильтровать
 *
 * minQuery: 0 превращает виджет в «редактируемый комбобокс»: по клику в поле
 * показывается ВЕСЬ список (ведёт себя как выпадашка), а при вводе — фильтрует.
 * Так работает выбор подраздела: их всего 30, список удобно листать глазами,
 * но и напечатать пару букв быстрее, чем искать в нём мышкой.
 */
(function () {
	'use strict';

	var SEARCH_DELAY = 200;
	var BLUR_DELAY = 150;

	function LivePicker(config) {
		var self = this;

		this.config = config;
		this.input = document.getElementById(config.inputId);
		this.hidden = document.getElementById(config.hiddenId);
		this.results = document.getElementById(config.resultsId);
		this.chosen = config.chosenId ? document.getElementById(config.chosenId) : null;
		this.valueKey = config.valueKey || 'id';
		this.minQuery = config.minQuery === undefined ? 1 : config.minQuery;
		this.lastValue = this.input.value || '';

		this.items = [];
		this.active = -1;
		this.timer = null;

		if (!this.input || !this.hidden || !this.results) {
			return;
		}

		this.input.addEventListener('input', function () { self.search(); });
		this.input.addEventListener('focus', function () { self.search(); });
		this.input.addEventListener('keydown', function (event) { self.onKeydown(event); });
		this.input.addEventListener('blur', function () {
			setTimeout(function () { self.syncOnBlur(); }, BLUR_DELAY);
		});

		document.addEventListener('click', function (event) {
			if (!self.results.contains(event.target) && event.target !== self.input) {
				self.render([]);
			}
		});
	}

	/** Нормализация для локального поиска: регистр, пробелы, дефисы, кавычки. */
	LivePicker.normalize = function (value) {
		return String(value).toLowerCase().replace(/[\s\-_«»"'`.,()]+/g, '');
	};

	/**
	 * Что делать с текстом поля на потере фокуса, если он не был подтверждён
	 * выбором варианта. Чистая функция — без DOM, чтобы её можно было
	 * протестировать без браузера (см. live_picker.test.js).
	 */
	LivePicker.resolveBlurAction = function (currentText, lastValue) {
		var trimmed = String(currentText).trim();

		if (trimmed === lastValue) {
			return 'keep';
		}
		if (trimmed === '') {
			return 'clear';
		}
		return 'revert';
	};

	LivePicker.prototype.search = function () {
		var self = this;
		var query = this.input.value.trim();

		clearTimeout(this.timer);

		if (query.length < this.minQuery) {
			this.render([]);
			return;
		}

		// Локальный режим: список уже в странице, запрос не нужен.
		if (this.config.items) {
			var needle = LivePicker.normalize(query);
			var localItems = needle === ''
				? this.config.items.slice(0)
				: this.config.items.filter(function (item) {
					return LivePicker.normalize(item.name).indexOf(needle) !== -1;
				});
			if (self.config.onItems) {
				localItems = self.config.onItems(localItems, query) || localItems;
			}
			this.items = localItems;
			this.active = -1;
			this.render(this.items);
			return;
		}

		this.timer = setTimeout(function () {
			var url = self.config.url + (self.config.url.indexOf('?') === -1 ? '?' : '&')
				+ 'q=' + encodeURIComponent(query);

			if (self.config.extraParams) {
				var extra = self.config.extraParams() || {};
				for (var key in extra) {
					if (extra.hasOwnProperty(key)) {
						url += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(extra[key]);
					}
				}
			}

			LivePicker.request(url, null, function (data) {
				var items = data.items || [];
				if (self.config.onItems) {
					items = self.config.onItems(items, query) || items;
				}
				self.items = items;
				self.active = -1;
				self.render(self.items);
			});
		}, SEARCH_DELAY);
	};

	LivePicker.prototype.onKeydown = function (event) {
		if (!this.items.length) {
			return;
		}

		if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
			event.preventDefault();
			var step = event.key === 'ArrowDown' ? 1 : -1;
			this.active = (this.active + step + this.items.length) % this.items.length;
			this.highlight();
		} else if (event.key === 'Enter') {
			if (this.active >= 0) {
				event.preventDefault();
				this.choose(this.items[this.active]);
			}
		} else if (event.key === 'Escape') {
			this.render([]);
		}
	};

	LivePicker.prototype.render = function (items) {
		var self = this;

		this.results.innerHTML = '';

		if (!items.length) {
			this.results.style.display = 'none';
			return;
		}

		items.forEach(function (item, index) {
			var row = document.createElement('div');
			row.className = 'catp__item';

			var title = document.createElement('span');
			title.className = 'catp__name';
			title.textContent = item.name;
			row.appendChild(title);

			var metaText = self.config.renderMeta ? self.config.renderMeta(item) : '';
			if (metaText) {
				var meta = document.createElement('span');
				meta.className = 'catp__meta';
				meta.textContent = metaText;
				row.appendChild(meta);
			}

			if (item.in_tree === false) {
				row.appendChild(LivePicker.badge('вне каталога', 'catp__badge--warn'));
			}
			if (item.fuzzy) {
				row.appendChild(LivePicker.badge('похоже на запрос', 'catp__badge--hint'));
			}

			row.addEventListener('mouseenter', function () {
				self.active = index;
				self.highlight();
			});
			row.addEventListener('click', function () {
				self.choose(item);
			});

			self.results.appendChild(row);
		});

		this.results.style.display = 'block';
		this.fitToViewport();
	};

	/**
	 * Подгоняет список под реально свободное место на экране.
	 *
	 * Сотрудники работают на бюджетных ноутбуках 1366×768, где под браузером
	 * остаётся ~600 px: фиксированная высота списка либо уезжает за нижний край,
	 * либо занимает весь экран. Считаем место под полем и над ним; если снизу
	 * тесно, а сверху просторнее — открываем список вверх.
	 */
	LivePicker.prototype.fitToViewport = function () {
		var MIN_HEIGHT = 120;
		var GAP = 12;

		var rect = this.input.getBoundingClientRect();
		var below = window.innerHeight - rect.bottom - GAP;
		var above = rect.top - GAP;

		var openUp = below < MIN_HEIGHT && above > below;

		this.results.classList.toggle('catp__results--up', openUp);
		this.results.style.maxHeight = Math.max(MIN_HEIGHT, Math.floor(openUp ? above : below)) + 'px';
	};

	LivePicker.prototype.highlight = function () {
		var rows = this.results.querySelectorAll('.catp__item');
		for (var i = 0; i < rows.length; i++) {
			rows[i].classList.toggle('catp__item--active', i === this.active);
		}
	};

	LivePicker.prototype.choose = function (item) {
		this.hidden.value = item[this.valueKey];
		this.input.value = item.name;
		this.lastValue = item.name;

		if (this.chosen) {
			this.chosen.innerHTML = '';
			this.chosen.appendChild(document.createTextNode('Выбрано: ' + item.name));
			if (item.in_tree === false) {
				this.chosen.appendChild(LivePicker.badge('нет в меню каталога', 'catp__badge--warn'));
			}
			this.chosen.style.display = 'block';
		}

		this.render([]);

		if (this.config.onChoose) {
			this.config.onChoose(item);
		}
	};

	/** Сбрасывает выбор — например, когда сменилась категория выше по каскаду. */
	LivePicker.prototype.reset = function () {
		this.hidden.value = '0';
		this.input.value = '';
		this.lastValue = '';
		this.items = [];
		this.render([]);

		if (this.chosen) {
			this.chosen.style.display = 'none';
			this.chosen.innerHTML = '';
		}
	};

	/**
	 * Синхронизирует текст поля со скрытым значением, если пользователь начал
	 * печатать поверх уже выбранного варианта и передумал, не выбрав новый.
	 * Без этого скрытое поле держит старое значение, а видимый текст — то,
	 * что не удалось допечатать до конца.
	 */
	LivePicker.prototype.syncOnBlur = function () {
		var action = LivePicker.resolveBlurAction(this.input.value, this.lastValue);

		if (action === 'keep') {
			return;
		}
		if (action === 'clear') {
			this.reset();
			return;
		}
		this.input.value = this.lastValue;
		this.render([]);
	};

	LivePicker.badge = function (text, extraClass) {
		var el = document.createElement('span');
		el.className = 'catp__badge ' + extraClass;
		el.textContent = text;
		return el;
	};

	LivePicker.request = function (url, payload, onSuccess, onError) {
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

		xhr.send(payload ? LivePicker.encodeForm(payload) : null);
	};

	LivePicker.encodeForm = function (payload) {
		var parts = [];
		for (var key in payload) {
			if (payload.hasOwnProperty(key)) {
				parts.push(encodeURIComponent(key) + '=' + encodeURIComponent(payload[key]));
			}
		}
		return parts.join('&');
	};

	window.LivePicker = LivePicker;
})();
