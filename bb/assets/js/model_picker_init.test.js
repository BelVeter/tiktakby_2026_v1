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
	'model_edit_start', 'model_edit_cancel', 'model_reset_search', 'edit_phase_banner', 'new_model_div',
	'cat_create_open', 'prod_create_open', 'prod_edit_open', 'cat_input_dog_new',
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
	global.window.categoryPicker = {
		lastChoice: null, resetCalled: false,
		choose: function (item) { this.lastChoice = item; },
		reset: function () { this.resetCalled = true; }
	};
	global.window.producerPicker = {
		lastChoice: null, resetCalled: false,
		choose: function (item) { this.lastChoice = item; },
		reset: function () { this.resetCalled = true; }
	};

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
assert.strictEqual(els4.submit_btn.disabled, true, 'submit_btn задизейблен в locate — Enter не должен уметь имплицитно сабмитить форму (C1)');
assert.strictEqual(els4.color_new.disabled, true, 'поля предпросмотра заблокированы в locate');

// --- Сценарий 5: клик «Внести изменения» -> unlocked, поля разблокированы ---
els4.model_edit_start.dispatchEvent('click');
assert.strictEqual(els4.color_new.disabled, false, 'после «Внести изменения» поле цвета редактируемо');
assert.strictEqual(els4.submit_btn.style.display, 'inline-block', 'в unlocked сабмит виден');
assert.strictEqual(els4.submit_btn.disabled, false, 'submit_btn разблокирован в unlocked (C1)');
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

// --- Сценарий 7: переименование модели в unlocked -> «Отмена» обязана вернуть исходное имя (C2) ---
var initialModel7 = { id: 55, name: 'Zebra', cat_id: 3, cat_name: 'Кроватки', cat_dog_name: 'кроватка', producer: 'Chicco', color: 'белый' };
var els7 = loadPage('edit', { model_search: 'Zebra', model_new: 'Zebra', color_new: 'белый' }, initialModel7);
els7.model_edit_start.dispatchEvent('click'); // -> unlocked, поля разблокированы
els7.model_search.value = 'Zebra Renamed';
els7.model_search.dispatchEvent('input');
assert.strictEqual(
	els7.model_new.value,
	'Zebra Renamed',
	'переименование в unlocked долетает до #model_new — это разрешённая правка, freeText-синк LivePicker'
);
els7.model_edit_cancel.dispatchEvent('click');
assert.strictEqual(
	els7.model_search.value,
	'Zebra',
	'«Отмена» обязана вернуть исходное имя модели в видимое поле поиска, а не оставить переименование (C2 fix)'
);
assert.strictEqual(
	els7.model_new.value,
	'Zebra',
	'«Отмена» обязана вернуть исходное имя модели в скрытое поле #model_new (C2 fix)'
);

// --- Сценарий 8: категория "уплыла" в locate (её трогали, новую модель не выбирали) -> «Внести изменения» обязана пересинхронизировать её к снэпшоту (I1) ---
var initialModel8 = { id: 77, name: 'Panda', cat_id: 11, cat_name: 'Стульчики', cat_dog_name: 'стульчик', producer: 'Peg-Perego', color: 'синий' };
var els8 = loadPage('edit', { model_search: 'Panda', model_new: 'Panda', color_new: 'синий', cat_select_new: '11' }, initialModel8);
// Симулируем «уплывшую» категорию: пользователь в locate полистал живой поиск
// категории и выбрал ДРУГУЮ (id=99), новую модель при этом не выбирал — та же
// ситуация, что описана в I1.
global.window.categoryPicker.choose({ id: 99, name: 'Другая категория', dog_name: 'другая' });
els8.cat_select_new.value = '99';
assert.strictEqual(
	global.window.categoryPicker.lastChoice.id,
	99,
	'подготовка сценария: категория действительно "уплыла" перед кликом «Внести изменения»'
);
els8.model_edit_start.dispatchEvent('click');
assert.strictEqual(
	global.window.categoryPicker.lastChoice.id,
	initialModel8.cat_id,
	'«Внести изменения» обязана пересинхронизировать категорию к снэпшоту найденной модели, а не оставить "уплывшую" (I1 fix)'
);

// --- Сценарий 9: producer_picker.js мог показать «переименовать» вне зависимости от фазы -> applyPhaseUI обязана пере-скрыть её, пока мы ещё в locate (I2) ---
var initialModel9 = { id: 88, name: 'Duckling', cat_id: 5, cat_name: 'Ванночки', cat_dog_name: 'ванночка', producer: 'Roxy Kids', color: 'жёлтый' };
var els9 = loadPage('edit', { model_search: 'Duckling', model_new: 'Duckling', color_new: 'жёлтый' }, initialModel9);
// Мы всё ещё в locate (model_edit_start ещё не нажата). Симулируем то, что
// producer_picker.js:toggleEditButton() реально делает при выборе производителя —
// безусловно показывает кнопку переименования, независимо от фазы.
els9.prod_edit_open.style.display = 'inline-block';
window.__onProducerChosen();
assert.strictEqual(
	els9.prod_edit_open.style.display,
	'none',
	'__onProducerChosen обязана пере-применить applyPhaseUI() и снова спрятать prod_edit_open, пока мы ещё в locate (I2 fix)'
);

// --- Сценарий 10: имя "уплыло" в locate (искали, но новую строку не выбирали) -> «Внести изменения» обязана пересинхронизировать #model_new к снэпшоту, иначе UPDATE переименует чужую запись (adversarial re-review finding) ---
var initialModel10 = { id: 99, name: 'Mercedes AMG', cat_id: 4, cat_name: 'Электромобили', cat_dog_name: 'электромобиль', producer: 'Mercedes', color: 'чёрный' };
var els10 = loadPage('edit', { model_search: 'Mercedes AMG', model_new: 'Mercedes AMG', color_new: 'чёрный' }, initialModel10);
// Мы всё ещё в locate (model_edit_start ещё не нажата). Симулируем: пользователь
// уже выбрал реальную модель (currentEditItem = initialModel10 через деплинк),
// но потом продолжил печатать/удалять в поле поиска в охоте за ДРУГОЙ записью,
// не кликая по новой строке — currentEditItem остаётся старым, а freeText-синк
// LivePicker уже утащил #model_new за напечатанным текстом.
els10.model_search.value = 'Mercedes AM';
els10.model_search.dispatchEvent('input');
assert.strictEqual(
	els10.model_new.value,
	'Mercedes AM',
	'подготовка сценария: недопечатанный текст в locate уже долетел до #model_new (freeText-синк)'
);
els10.model_edit_start.dispatchEvent('click');
assert.strictEqual(
	els10.model_new.value,
	'Mercedes AMG',
	'«Внести изменения» обязана пересинхронизировать #model_new к снэпшоту, а не оставить "уплывший" текст поиска (иначе UPDATE переименует чужую запись)'
);

// --- Сценарий 11: «Начать поиск заново» в locate — одним кликом сброс категории/фирмы/модели, без «Внести изменения» ---
var initialModel11 = { id: 66, name: 'Lion', cat_id: 9, cat_name: 'Санки', cat_dog_name: 'санки', producer: 'Nika', color: 'красный' };
var els11 = loadPage('edit', { model_search: 'Lion', model_new: 'Lion', cat_select_new: '9', producer_select_new: 'Nika', cat_input_dog_new: 'санки' }, initialModel11);
assert.strictEqual(els11.model_reset_search.style.display, 'inline-block', 'в locate «начать поиск заново» видна сразу (модель уже найдена деплинком)');
els11.model_reset_search.dispatchEvent('click');
assert.strictEqual(els11.model_search.value, '', '«начать заново» очищает поле поиска модели');
assert.strictEqual(els11.model_new.value, '', '«начать заново» очищает скрытое значение модели');
assert.strictEqual(els11.model_id.value, '', '«начать заново» очищает id редактируемой модели');
assert.strictEqual(els11.cat_input_dog_new.value, '', '«начать заново» очищает подставленное название для договора');
assert.strictEqual(global.window.categoryPicker.resetCalled, true, '«начать заново» реально зовёт categoryPicker.reset()');
assert.strictEqual(global.window.producerPicker.resetCalled, true, '«начать заново» реально зовёт producerPicker.reset()');
assert.strictEqual(els11.model_edit_start.style.display, 'none', 'после сброса «Внести изменения» снова скрыта — currentEditItem обнулён');
assert.strictEqual(els11.model_reset_search.style.display, 'inline-block', '«начать заново» остаётся видна — мы всё ещё в locate');

// --- Сценарий 12: в unlocked «начать поиск заново» скрыта — там уже есть «Отмена» для отката ---
var initialModel12 = { id: 71, name: 'Bear', cat_id: 2, cat_name: 'Санки', cat_dog_name: 'санки', producer: 'Nika', color: 'синий' };
var els12 = loadPage('edit', { model_search: 'Bear', model_new: 'Bear', color_new: 'синий' }, initialModel12);
els12.model_edit_start.dispatchEvent('click');
assert.strictEqual(els12.model_reset_search.style.display, 'none', 'в unlocked «начать поиск заново» скрыта — сброс уже делает «Отмена»');

console.log('model_picker_init.test.js: OK (37 assertions)');

// SEARCH_DELAY-таймер, запущенный сценарием 3, через 200мс дёрнул бы
// LivePicker.request() -> new XMLHttpRequest(), которого в Node нет. Синхронные
// проверки этого теста уже выполнены — выходим сразу, не дожидаясь таймера.
process.exit(0);
