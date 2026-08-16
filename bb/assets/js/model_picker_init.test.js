// Автономный тест без фреймворка, как live_picker.test.js / model_picker.test.js. Запуск:
// node bb/assets/js/model_picker_init.test.js
//
// В отличие от двух других тестов этого набора, здесь реально дёргается init()
// на живом DOM-стабе — как это делает браузер при загрузке страницы.
// model_picker.test.js держит document.addEventListener пустышкой нарочно,
// чтобы init() НЕ запускался (там тестируются только чистые функции
// groupByName/resolveHint). Этот файл закрывает именно тот пробел — целиком
// живой init() и поймал баг с сентинелом '0', затиравшим поле модели при
// загрузке страницы (см. итоговый review ветки).
'use strict';

var assert = require('assert');
var path = require('path');

var LIVE_PICKER_PATH = path.join(__dirname, 'live_picker.js');
var MODEL_PICKER_PATH = path.join(__dirname, 'model_picker.js');

var STUB_IDS = [
	'model_search', 'model_new', 'model_results', 'model_hint',
	'cat_select_new', 'producer_select_new', 'model_id',
	'tab_new', 'tab_edit', 'submit_btn',
	'model_edit_start', 'model_edit_cancel', 'edit_phase_banner', 'new_model_div',
	'cat_create_open', 'prod_create_open', 'prod_edit_open',
	'color_new', 'm_set_new', 'color_multicolor_btn'
];

function makeElement(initialValue) {
	var listeners = {};
	return {
		value: initialValue !== undefined ? initialValue : '',
		placeholder: '',
		className: '',
		innerHTML: '',
		textContent: '',
		style: {},
		checked: false,
		disabled: false,
		classList: { toggle: function () {} },
		addEventListener: function (type, handler) {
			(listeners[type] = listeners[type] || []).push(handler);
		},
		dispatchEvent: function (event) {
			var type = typeof event === 'string' ? event : event.type;
			var ev = typeof event === 'string' ? { type: event } : event;
			(listeners[type] || []).forEach(function (fn) { fn(ev); });
		},
		appendChild: function () {},
		contains: function () { return false; },
		focus: function () {},
		querySelectorAll: function () { return []; },
		getBoundingClientRect: function () { return { top: 0, bottom: 0, left: 0, right: 0 }; }
	};
}

/**
 * Собирает свежий DOM-стаб и грузит live_picker.js + model_picker.js заново
 * (сбрасывая require.cache), чтобы каждый сценарий получал чистое состояние
 * модуля — свой picker, свой mode и т.д., как будто это отдельная загрузка
 * страницы, а не переиспользование состояния между сценариями.
 */
function loadPage(initialTab, overrides, initialModel) {
	overrides = overrides || {};

	var elements = {};
	STUB_IDS.forEach(function (id) {
		elements[id] = makeElement(overrides[id]);
	});

	var docListeners = {};
	global.document = {
		readyState: 'loading',
		getElementById: function (id) {
			return elements.hasOwnProperty(id) ? elements[id] : null;
		},
		addEventListener: function (type, handler) {
			(docListeners[type] = docListeners[type] || []).push(handler);
		},
		createElement: function () { return makeElement(); },
		createTextNode: function (text) { return { textContent: text }; }
	};

	global.window = {};
	global.window.TOVAR_MOD_INITIAL_TAB = initialTab;
	global.window.TOVAR_MOD_INITIAL_MODEL = initialModel || null;
	// Стабы categoryPicker/producerPicker — hardSelectCategoryAndProducer() (вызывается
	// и при выборе модели, и из cancelEdit() при «Отмена») дёргает их .choose(item) и
	// больше ничего; записываем последний переданный item, чтобы сценарии могли
	// проверить, что «Отмена» реально откатывает категорию/фирму, а не просто
	// зовёт функцию вхолостую (оба window.*Picker отсутствуют в реальном DOM-стабе
	// иначе, и вызов был бы неотличим от no-op).
	global.window.categoryPicker = { lastChoice: null, choose: function (item) { this.lastChoice = item; } };
	global.window.producerPicker = { lastChoice: null, choose: function (item) { this.lastChoice = item; } };

	delete require.cache[require.resolve(LIVE_PICKER_PATH)];
	delete require.cache[require.resolve(MODEL_PICKER_PATH)];
	require(LIVE_PICKER_PATH);
	require(MODEL_PICKER_PATH);

	// document.readyState === 'loading' => model_picker.js не вызывает init()
	// сразу при require (тот самый bootstrap-runs-at-require-time фикс, что и в
	// model_picker.test.js), а регистрирует его на DOMContentLoaded. Стаб-элементы
	// уже готовы — теперь можно «долистать» загрузку страницы вручную.
	(docListeners.DOMContentLoaded || []).forEach(function (fn) { fn(); });

	return elements;
}

// --- Сценарий 1: свежая загрузка, вкладка «новая модель», поле пустое ---
var els1 = loadPage('new', { model_new: '' });
assert.strictEqual(
	els1.model_new.value,
	'',
	'после init() на пустой форме поле модели должно остаться пустым, а не стать "0"'
);

// --- Сценарий 2: загрузка сразу на вкладке редактирования, поля прогреты сервером ---
var els2 = loadPage('edit', { model_search: 'Fox', model_new: 'Fox' });
assert.strictEqual(
	els2.model_search.value,
	'Fox',
	'init() на вкладке редактирования не должен трогать серверный префилл в поле поиска'
);
assert.strictEqual(
	els2.model_new.value,
	'Fox',
	'init() на вкладке редактирования не должен затирать серверный префилл в поле модели'
);

// --- Сценарий 3: живой ввод новой модели должен долетать до скрытого поля ---
var els3 = loadPage('new', { model_new: '' });
els3.model_search.value = 'Совершенно новая модель';
els3.model_search.dispatchEvent('input');
assert.strictEqual(
	els3.model_new.value,
	'Совершенно новая модель',
	'напечатанное имя новой модели должно попасть в #model_new немедленно, без выбора из списка'
);

// --- Сценарий 4: заход по прямой ссылке с готовым model_id -> сразу locate с найденной моделью ---
var initialModel = { id: 42, name: 'Fox', cat_id: 7, cat_name: 'Коляски', cat_dog_name: 'коляска', producer: 'Bugaboo', color: 'красный' };
var els4 = loadPage('edit', { model_search: 'Fox', model_new: 'Fox', color_new: 'красный' }, initialModel);
assert.strictEqual(els4.model_edit_start.style.display, 'inline-block', 'модель уже найдена деплинком -> «Внести изменения» видна сразу');
assert.strictEqual(els4.submit_btn.style.display, 'none', 'в locate сабмит скрыт, даже придя по прямой ссылке');
assert.strictEqual(els4.color_new.disabled, true, 'поля предпросмотра заблокированы в locate');

// --- Сценарий 5: клик «Внести изменения» -> unlocked, поля разблокированы ---
els4.model_edit_start.dispatchEvent('click');
assert.strictEqual(els4.color_new.disabled, false, 'после «Внести изменения» поле цвета редактируемо');
assert.strictEqual(els4.submit_btn.style.display, 'inline-block', 'в unlocked сабмит виден');
assert.strictEqual(els4.model_edit_cancel.style.display, 'inline-block', 'в unlocked видна «Отмена»');
assert.strictEqual(els4.cat_create_open.style.display, 'inline-block', 'в unlocked доступно «+ создать категорию»');

// --- Сценарий 6: «Отмена» откатывает правки и блокирует поля обратно ---
els4.color_new.value = 'зелёный';
els4.model_edit_cancel.dispatchEvent('click');
assert.strictEqual(els4.color_new.value, 'красный', '«Отмена» возвращает исходный цвет из снэпшота');
assert.strictEqual(els4.color_new.disabled, true, '«Отмена» блокирует поля обратно');
assert.strictEqual(els4.submit_btn.style.display, 'none', '«Отмена» снова прячет сабмит (обратно в locate)');
assert.strictEqual(els4.model_edit_start.style.display, 'inline-block', '«Отмена» возвращает «Внести изменения»');
assert.deepStrictEqual(
	global.window.categoryPicker.lastChoice,
	{ id: initialModel.cat_id, name: initialModel.cat_name, dog_name: initialModel.cat_dog_name },
	'«Отмена» реально зовёт categoryPicker.choose() со снэпшотом категории, а не просто текстовые поля'
);
assert.deepStrictEqual(
	global.window.producerPicker.lastChoice,
	{ name: initialModel.producer },
	'«Отмена» реально зовёт producerPicker.choose() со снэпшотом фирмы'
);

console.log('model_picker_init.test.js: OK (15 assertions)');

// SEARCH_DELAY-таймер, запущенный сценарием 3, через 200мс дёрнул бы
// LivePicker.request() -> new XMLHttpRequest(), которого в Node нет. Синхронные
// проверки этого теста уже выполнены — выходим сразу, не дожидаясь таймера.
process.exit(0);
