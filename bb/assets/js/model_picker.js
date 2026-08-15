/**
 * Поле «Модель» на bb/tovar_new_mod.php — живой поиск с двумя режимами.
 *
 * Один и тот же эндпоинт (bb/ajax_model_suggest.php) отдаёт полные строки tovar_rent
 * (категория+фирма+модель+ЦВЕТ — единица уникальности в этой таблице). Разница между
 * вкладками — только в том, как эти строки показываются и что происходит при выборе:
 *
 *   «Новая модель»            — строки схлопываются по имени (groupByName), цвет не
 *                                показывается отдельно — на этой вкладке он вводится вручную
 *                                ниже. Выбор жёстко проставляет категорию и фирму, остальные
 *                                поля не трогает. Если набранное имя точно совпадает с
 *                                существующей записью в выбранных категории+фирме — рядом
 *                                появляется подсказка со ссылкой на вкладку редактирования.
 *
 *   «Редактировать действующую» — строки показываются как есть, по одной на tovar_rent_id,
 *                                цвет — во второй строке подсказки. Выбор жёстко проставляет
 *                                категорию, фирму, ID модели и ВСЕ остальные поля формы —
 *                                редактируемо, как today's action=редактировать.
 *
 * Категория и фирма — общие виджеты (category_picker.js, producer_picker.js), их LivePicker
 * инстансы открыты в window.categoryPicker/window.producerPicker специально для того, чтобы
 * этот файл мог вызвать их .choose() и проставить значение в обход обычного поиска.
 */
(function () {
	'use strict';

	var CHECK = { NEW: 'new', EDIT: 'edit' };

	var EDIT_FIELD_MAP = {
		color:         'color_new',
		set:           'm_set_new',
		agr_price:     'm_price_new',
		agr_price_cur: 'm_price_cur_new',
		price_new:     'price_new_new',
		lom_srok:      'lom_srok_new',
		model_addr:    'model_addr_new',
		ph_addr:       'ph_addr_new',
		age_from:      'age_from_new',
		age_to:        'age_to_new',
		weight_from:   'weight_from_new',
		weight_to:     'weight_to_new',
		m_sex:         'm_sex',
		collateral:    'collateral_new',
		ny:            'ny_new',
		zv:            'zv_new',
		tale:          'tale_new',
		rez1:          'rez1_new',
		rez2:          'rez2_new'
	};

	var els = {};
	var picker = null;
	var mode = CHECK.NEW;
	var pendingEditGroup = null;

	function $(id) {
		return document.getElementById(id);
	}

	/** Схлопывает строки с одинаковым именем в одну запись со списком цветов. */
	function groupByName(items) {
		var order = [];
		var byName = {};

		items.forEach(function (item) {
			if (!byName[item.name]) {
				byName[item.name] = {
					id:           item.name,
					name:         item.name,
					cat_id:       item.cat_id,
					cat_name:     item.cat_name,
					cat_dog_name: item.cat_dog_name,
					producer:     item.producer,
					colors:       []
				};
				order.push(item.name);
			}
			byName[item.name].colors.push(item.color);
		});

		return order.map(function (name) { return byName[name]; });
	}

	/**
	 * Что показать под полем: 'empty' — подсказка начать вводить/выбрать категорию,
	 * 'exists' — подсказка о существующей модели (только вкладка «новая»), null — ничего.
	 */
	function resolveHint(state) {
		if (state.query === '' && !state.hasFilter) {
			return 'empty';
		}

		if (state.mode === CHECK.NEW && state.query !== '') {
			var needle = window.LivePicker.normalize(state.query);
			var exact = state.groups.filter(function (g) {
				return window.LivePicker.normalize(g.name) === needle;
			});
			if (exact.length) {
				return 'exists';
			}
		}

		return null;
	}

	function currentFilterState(query) {
		var catVal = els.cat ? Number(els.cat.value) : 0;
		var prodVal = els.prod ? els.prod.value : '';
		return {
			mode:      mode,
			query:     query,
			hasFilter: catVal > 0 || !!prodVal
		};
	}

	function showHintText(text) {
		els.hint.textContent = '';
		els.hint.appendChild(document.createTextNode(text));
		els.hint.className = 'catp__warn catp__warn--hint';
		els.hint.style.display = 'block';
	}

	function showExistsHint(colorsText) {
		els.hint.textContent = '';
		els.hint.appendChild(document.createTextNode('Такая модель уже есть (цвета: ' + colorsText + ') — '));
		var link = document.createElement('a');
		link.href = '#';
		link.id = 'model_edit_link';
		link.textContent = 'редактировать';
		els.hint.appendChild(link);
		els.hint.className = 'catp__warn catp__warn--hint';
		els.hint.style.display = 'block';
	}

	function hideHint() {
		els.hint.style.display = 'none';
		els.hint.innerHTML = '';
	}

	function updateHint(rawItems, query) {
		var groups = mode === CHECK.NEW ? groupByName(rawItems) : [];
		var state = currentFilterState(query);
		state.groups = groups;

		var verdict = resolveHint(state);

		if (verdict === 'empty') {
			showHintText('Начните вводить название модели или сначала выберите категорию/фирму');
			return;
		}

		if (verdict === 'exists') {
			var needle = window.LivePicker.normalize(query);
			var match = groups.filter(function (g) {
				return window.LivePicker.normalize(g.name) === needle;
			})[0];
			pendingEditGroup = match;
			showExistsHint(match.colors.join(', '));
			return;
		}

		pendingEditGroup = null;
		hideHint();
	}

	/** Жёсткий выбор категории и фирмы из найденной записи — общий для обеих вкладок. */
	function hardSelectCategoryAndProducer(item) {
		if (window.categoryPicker) {
			window.categoryPicker.choose({
				id:       item.cat_id,
				name:     item.cat_name,
				dog_name: item.cat_dog_name
			});
		}
		if (window.producerPicker) {
			window.producerPicker.choose({ name: item.producer });
		}
	}

	/** Только на вкладке «редактировать»: подтягивает все остальные поля формы. */
	function fillEditFields(item) {
		if (els.modelId) {
			els.modelId.value = item.id;
		}
		Object.keys(EDIT_FIELD_MAP).forEach(function (key) {
			var field = $(EDIT_FIELD_MAP[key]);
			if (field && item[key] !== undefined) {
				field.value = item[key];
			}
		});
	}

	function setMode(newMode, options) {
		var shouldReset = !options || options.reset !== false;

		mode = newMode;
		pendingEditGroup = null;
		hideHint();

		if (els.tabNew) {
			els.tabNew.classList.toggle('catp-tab--active', mode === CHECK.NEW);
		}
		if (els.tabEdit) {
			els.tabEdit.classList.toggle('catp-tab--active', mode === CHECK.EDIT);
		}
		if (els.search) {
			els.search.placeholder = mode === CHECK.NEW
				? 'начните вводить название модели'
				: 'найдите модель для редактирования';
		}
		if (els.submitBtn) {
			els.submitBtn.value = mode === CHECK.NEW ? 'сохранить' : 'обновить';
		}
		if (mode === CHECK.NEW && els.modelId) {
			els.modelId.value = '';
		}
		if (picker && shouldReset) {
			picker.reset();
		}
	}

	function switchToEditWithQuery(name) {
		setMode(CHECK.EDIT);
		if (els.search) {
			els.search.value = name;
			els.search.dispatchEvent(new Event('input'));
			els.search.focus();
		}
	}

	function init() {
		els.search    = $('model_search');
		els.hidden    = $('model_new');
		els.results   = $('model_results');
		els.hint      = $('model_hint');
		els.cat       = $('cat_select_new');
		els.prod      = $('producer_select_new');
		els.modelId   = $('model_id');
		els.tabNew    = $('tab_new');
		els.tabEdit   = $('tab_edit');
		els.submitBtn = $('submit_btn');

		if (!els.search || !els.hidden || !els.results || !window.LivePicker) {
			return;
		}

		picker = new window.LivePicker({
			inputId:   'model_search',
			hiddenId:  'model_new',
			resultsId: 'model_results',
			url:       '/bb/ajax_model_suggest.php',
			valueKey:  'name',
			minQuery:  0,
			emptyValue: '',
			freeText:  true,
			extraParams: function () {
				var params = {};
				if (els.cat && Number(els.cat.value) > 0) {
					params.cat_id = els.cat.value;
				}
				if (els.prod && els.prod.value) {
					params.producer = els.prod.value;
				}
				return params;
			},
			onItems: function (items, query) {
				updateHint(items, query);
				return mode === CHECK.NEW ? groupByName(items) : items;
			},
			renderMeta: function (item) {
				if (mode === CHECK.NEW) {
					return item.producer + ' · ' + item.cat_name
						+ (item.colors.length > 1 ? ' · цветов: ' + item.colors.length : '');
				}
				return item.producer + ' · ' + item.cat_name + ' · ' + item.color;
			},
			onChoose: function (item) {
				hardSelectCategoryAndProducer(item);
				if (mode === CHECK.EDIT) {
					fillEditFields(item);
				}
				hideHint();
			}
		});

		if (els.hint) {
			els.hint.addEventListener('click', function (event) {
				if (event.target && event.target.id === 'model_edit_link') {
					event.preventDefault();
					if (pendingEditGroup) {
						switchToEditWithQuery(pendingEditGroup.name);
					}
				}
			});
		}

		if (els.tabNew) {
			els.tabNew.addEventListener('click', function () { setMode(CHECK.NEW); });
		}
		if (els.tabEdit) {
			els.tabEdit.addEventListener('click', function () { setMode(CHECK.EDIT); });
		}

		setMode(window.TOVAR_MOD_INITIAL_TAB === 'edit' ? CHECK.EDIT : CHECK.NEW, { reset: false });
	}

	window.__modelPickerTestHooks = { groupByName: groupByName, resolveHint: resolveHint };

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
