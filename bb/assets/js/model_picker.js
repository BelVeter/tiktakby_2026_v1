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
	var editPhase = 'locate';
	var currentEditItem = null;

	var EDIT_PHASE_FIELD_IDS = Object.keys(EDIT_FIELD_MAP).map(function (key) {
		return EDIT_FIELD_MAP[key];
	}).concat(['color_multicolor_btn']);

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

	/**
	 * Что показать/скрыть/заблокировать для комбинации режим+фаза. Чистая
	 * функция — без DOM, тестируется в model_picker.test.js. Единственная
	 * точка правды: applyPhaseUI() только переносит это на DOM, никакой
	 * своей логики видимости не содержит.
	 */
	function resolveEditPhaseUI(mode, editPhase, hasModel) {
		if (mode === CHECK.NEW) {
			return {
				showEditStart: false,
				showSubmit: true,
				showCancel: false,
				showResetSearch: true,
				showModelLinks: false,
				fieldsDisabled: false,
				createControlsVisible: true,
				filterActive: false
			};
		}

		if (editPhase === 'unlocked') {
			return {
				showEditStart: false,
				showSubmit: true,
				showCancel: true,
				showResetSearch: false,
				showModelLinks: true,
				fieldsDisabled: false,
				createControlsVisible: true,
				filterActive: false
			};
		}

		// editPhase === 'locate'
		return {
			showEditStart: hasModel,
			showSubmit: false,
			showCancel: false,
			showResetSearch: true,
			showModelLinks: hasModel,
			fieldsDisabled: true,
			createControlsVisible: false,
			filterActive: true
		};
	}

	/** Переносит resolveEditPhaseUI() на реальный DOM. */
	function applyPhaseUI() {
		var ui = resolveEditPhaseUI(mode, editPhase, !!currentEditItem);

		EDIT_PHASE_FIELD_IDS.forEach(function (id) {
			var field = $(id);
			if (field) {
				field.disabled = ui.fieldsDisabled;
			}
		});

		if (els.editStartBtn) { els.editStartBtn.style.display = ui.showEditStart ? 'inline-block' : 'none'; }
		if (els.submitBtn) {
			els.submitBtn.style.display = ui.showSubmit ? 'inline-block' : 'none';
			els.submitBtn.disabled = !ui.showSubmit;
		}
		if (els.cancelBtn)    { els.cancelBtn.style.display = ui.showCancel ? 'inline-block' : 'none'; }
		if (els.resetSearchBtn) { els.resetSearchBtn.style.display = ui.showResetSearch ? 'inline-block' : 'none'; }
		if (els.modelLinksWrap) {
			els.modelLinksWrap.style.display = ui.showModelLinks ? 'block' : 'none';
			if (ui.showModelLinks && currentEditItem) {
				if (els.linksTarifId) { els.linksTarifId.value = currentEditItem.id; }
				if (els.linksCatId)   { els.linksCatId.value = currentEditItem.cat_id; }
				if (els.linksNewItemId) { els.linksNewItemId.value = currentEditItem.id; }
			}
		}
		if (els.catCreateBtn)  { els.catCreateBtn.style.display = ui.createControlsVisible ? 'inline-block' : 'none'; }
		if (els.prodCreateBtn) { els.prodCreateBtn.style.display = ui.createControlsVisible ? 'inline-block' : 'none'; }
		if (els.prodEditBtn) {
			// Дублирует условие producer_picker.js:toggleEditButton() одной
			// строкой — заводить общий API ради одной строки не стали;
			// порядок подключения скриптов (после producer_picker.js)
			// гарантирует, что здесь будет последнее слово.
			els.prodEditBtn.style.display = (ui.createControlsVisible && els.prod && els.prod.value) ? 'inline-block' : 'none';
		}

		window.TOVAR_MOD_LOCATE_FILTER = ui.filterActive;

		if (els.area) {
			els.area.classList.toggle('catp-phase--new', mode === CHECK.NEW);
			els.area.classList.toggle('catp-phase--locate', mode === CHECK.EDIT && editPhase === 'locate');
			els.area.classList.toggle('catp-phase--unlocked', mode === CHECK.EDIT && editPhase === 'unlocked');
		}

		if (els.phaseBanner) {
			var showBanner = mode === CHECK.EDIT && editPhase === 'unlocked';
			els.phaseBanner.style.display = showBanner ? 'block' : 'none';
			els.phaseBanner.textContent = showBanner
				? 'Режим редактирования — изменения сохранятся только по кнопке «Сохранить»'
				: '';
		}
	}

	function enterUnlocked() {
		if (currentEditItem) {
			hardSelectCategoryAndProducer(currentEditItem);
			restoreModelNameFromSnapshot();
		}
		editPhase = 'unlocked';
		applyPhaseUI();
	}

	/** Откатывает поля «Модель» (текст, скрытое значение, lastValue виджета) к снэпшоту. */
	function restoreModelNameFromSnapshot() {
		if (!currentEditItem) {
			return;
		}
		if (els.search) { els.search.value = currentEditItem.name; }
		if (els.hidden) { els.hidden.value = currentEditItem.name; }
		if (picker) { picker.lastValue = currentEditItem.name; }
	}

	/** Откатывает к состоянию сразу после выбора модели — как будто «Внести изменения» не нажималась. */
	function cancelEdit() {
		editPhase = 'locate';
		if (currentEditItem) {
			restoreModelNameFromSnapshot();
			fillEditFields(currentEditItem);
			hardSelectCategoryAndProducer(currentEditItem);
		}
		hideHint();
		applyPhaseUI();
	}

	/**
	 * Возвращает категорию/фирму/модель к пустому поиску одним кликом —
	 * вместо того, чтобы чистить три поля по отдельности. Доступна, пока
	 * ничего не заблокировано на редактирование (locate и вкладка «Новая
	 * модель») — в unlocked за откат уже отвечает «Отмена» (cancelEdit).
	 */
	function resetSearch() {
		resetAllFields();
		applyPhaseUI();
	}

	/** Квитанция «Модель успешно обновлена» из предыдущего сохранения устаревает,
	 * как только начался новый поиск — прячем её, чтобы не вводить в заблуждение. */
	function hideSuccessBanner() {
		if (els.successBanner) { els.successBanner.style.display = 'none'; }
	}

	/**
	 * Ищет среди подсказок точное совпадение по имени+цвету, кроме самой
	 * редактируемой записи. Используется только в unlocked-фазе — до этого
	 * подсказка «уже есть» показывает совсем другое (см. resolveHint).
	 */
	function findDuplicateMatch(items, params) {
		var needle = window.LivePicker.normalize(params.name);

		var matches = items.filter(function (item) {
			return window.LivePicker.normalize(item.name) === needle
				&& item.color === params.color
				&& item.id !== params.excludeId;
		});

		return matches.length ? matches[0] : null;
	}

	/** Только unlocked-фаза: предупреждает, если правки сольются с ДРУГОЙ записью. */
	function checkDuplicateAndWarn(items, query) {
		var name = query.trim();
		if (name === '') {
			hideHint();
			return;
		}

		var excludeId = currentEditItem ? currentEditItem.id : null;
		var match = findDuplicateMatch(items, {
			name: name,
			color: els.color ? els.color.value : '',
			excludeId: excludeId
		});

		if (match) {
			showHintText('Эта комбинация совпадает с моделью #' + match.id
				+ ' (' + match.producer + ' · ' + match.cat_name + ') — сохранение объединит записи');
			return;
		}

		hideHint();
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

	/**
	 * Чистит все поля деталей модели (цвет, цена, возраст и т.д. — весь
	 * EDIT_FIELD_MAP), не трогая категорию/фирму/модель — теми занимается
	 * resetAllFields(). Для <select> откатывает к первому option (тот же
	 * дефолт, что при чистой загрузке страницы), для остальных — value=''.
	 */
	function clearDetailFields() {
		Object.keys(EDIT_FIELD_MAP).forEach(function (key) {
			var field = $(EDIT_FIELD_MAP[key]);
			if (!field) {
				return;
			}
			if (field.tagName === 'SELECT' && field.options && field.options.length) {
				field.value = field.options[0].value;
			} else {
				field.value = '';
			}
		});
	}

	/**
	 * Полный сброс общего набора полей формы — категория/фирма/модель и все
	 * поля деталей. Используется и кнопкой «Начать заново», и переключением
	 * вкладок (иначе значения, набранные на одной вкладке, утекают на
	 * другую — форма-то одна на обе вкладки).
	 */
	function resetAllFields() {
		if (picker) { picker.reset(); }
		if (window.categoryPicker) { window.categoryPicker.reset(); }
		if (window.producerPicker) { window.producerPicker.reset(); }
		var dogNameField = $('cat_input_dog_new');
		if (dogNameField) { dogNameField.value = ''; }
		if (els.modelId) { els.modelId.value = ''; }
		clearDetailFields();
		currentEditItem = null;
		pendingEditGroup = null;
		hideHint();
		hideSuccessBanner();
	}

	function setMode(newMode, options) {
		var shouldReset = !options || options.reset !== false;

		mode = newMode;
		editPhase = 'locate';
		pendingEditGroup = null;
		hideHint();

		if (shouldReset) {
			resetAllFields();
		}

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

		applyPhaseUI();
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
		els.editStartBtn  = $('model_edit_start');
		els.cancelBtn     = $('model_edit_cancel');
		els.resetSearchBtn = $('model_reset_search');
		els.phaseBanner   = $('edit_phase_banner');
		els.successBanner = $('update_success_banner');
		els.modelLinksWrap = $('model_quick_links');
		els.linksTarifId  = $('model_links_tarif_id');
		els.linksCatId    = $('model_links_cat_id');
		els.linksNewItemId = $('model_links_new_item_id');
		els.area          = $('new_model_div');
		els.catCreateBtn  = $('cat_create_open');
		els.prodCreateBtn = $('prod_create_open');
		els.prodEditBtn   = $('prod_edit_open');
		els.color         = $('color_new');
		if (els.color) {
			els.color.addEventListener('input', function () {
				if (mode === CHECK.EDIT && editPhase === 'unlocked') {
					picker.search();
				}
			});
		}

		if (!els.search || !els.hidden || !els.results || !window.LivePicker) {
			return;
		}

		if (window.TOVAR_MOD_INITIAL_MODEL) {
			currentEditItem = window.TOVAR_MOD_INITIAL_MODEL;
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
				if (mode === CHECK.EDIT && editPhase === 'unlocked') {
					checkDuplicateAndWarn(items, query);
					return [];
				}
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
					currentEditItem = item;
					fillEditFields(item);
					hideSuccessBanner();
					applyPhaseUI();
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
		if (els.editStartBtn) {
			els.editStartBtn.addEventListener('click', function () {
				if (mode === CHECK.EDIT && currentEditItem) {
					enterUnlocked();
				}
			});
		}
		if (els.cancelBtn) {
			els.cancelBtn.addEventListener('click', function () {
				cancelEdit();
			});
		}
		if (els.resetSearchBtn) {
			els.resetSearchBtn.addEventListener('click', function () {
				resetSearch();
			});
		}

		window.__onCategoryChosen = function () {
			applyPhaseUI();
			if (mode === CHECK.EDIT && editPhase === 'unlocked') {
				picker.search();
			}
		};
		window.__onProducerChosen = function () {
			applyPhaseUI();
			if (mode === CHECK.EDIT && editPhase === 'unlocked') {
				picker.search();
			}
		};

		setMode(window.TOVAR_MOD_INITIAL_TAB === 'edit' ? CHECK.EDIT : CHECK.NEW, { reset: false });
	}

	window.__modelPickerTestHooks = { groupByName: groupByName, resolveHint: resolveHint, resolveEditPhaseUI: resolveEditPhaseUI, findDuplicateMatch: findDuplicateMatch };

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
