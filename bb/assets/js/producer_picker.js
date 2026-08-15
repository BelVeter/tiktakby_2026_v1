/**
 * Выбор производителя на bb/tovar_new_mod.php + создание и редактирование
 * в модалках. Построен на общем ядре live_picker.js, по образцу
 * category_picker.js.
 */
(function () {
	'use strict';

	var CHECK_DELAY = 400;

	var picker = null;
	var els = {};
	var confirmPending = false;
	var editingId = null;

	function $(id) {
		return document.getElementById(id);
	}

	function init() {
		els.search  = $('prod_search');
		els.hidden  = $('producer_select_new');
		els.chosen  = $('prod_chosen');
		els.createBtn = $('prod_create_open');
		els.editBtn   = $('prod_edit_open');
		els.createModal = $('prod_create_modal');
		els.editModal    = $('prod_edit_modal');

		if (!els.search || !els.hidden || !window.LivePicker) {
			return;
		}

		picker = new window.LivePicker({
			inputId:   'prod_search',
			hiddenId:  'producer_select_new',
			resultsId: 'prod_results',
			chosenId:  'prod_chosen',
			url:       '/bb/ajax_producer_suggest.php',
			valueKey:  'name',
			minQuery:  0,
			emptyValue: '',
			extraParams: function () {
				var catField = $('cat_select_new');
				return catField && catField.value ? { cat_id: catField.value } : {};
			},
			renderMeta: function (item) {
				return item.hidden ? 'скрыт' : '';
			},
			onChoose: function () {
				toggleEditButton();
			}
		});

		window.producerPicker = picker;

		if (els.createBtn) {
			els.createBtn.addEventListener('click', openCreateModal);
		}
		if (els.editBtn) {
			els.editBtn.addEventListener('click', openEditModal);
		}

		toggleEditButton();
		initCreateModal();
		initEditModal();
	}

	function toggleEditButton() {
		if (els.editBtn) {
			els.editBtn.style.display = els.hidden.value ? 'inline-block' : 'none';
		}
	}

	// ----------------------------------------------------------- создание

	function initCreateModal() {
		if (!els.createModal) {
			return;
		}

		els.cName  = $('newprod_name');
		els.cComment = $('newprod_comment');
		els.cWarn  = $('newprod_warning');
		els.cSave  = $('newprod_save');
		els.cCancel = $('newprod_cancel');

		els.cCancel.addEventListener('click', function () { els.createModal.style.display = 'none'; });
		els.createModal.addEventListener('click', function (e) {
			if (e.target === els.createModal) { els.createModal.style.display = 'none'; }
		});

		var checkTimer = null;
		els.cName.addEventListener('input', function () {
			confirmPending = false;
			els.cSave.value = 'создать производителя';
			clearTimeout(checkTimer);
			checkTimer = setTimeout(checkCreateName, CHECK_DELAY);
		});

		els.cSave.addEventListener('click', submitCreate);
	}

	function openCreateModal() {
		els.createModal.style.display = 'flex';
		els.cName.value = els.search.value.trim();
		els.cComment.value = '';
		warn(els.cWarn, '');
		confirmPending = false;
		els.cSave.value = 'создать производителя';
		els.cName.focus();
		if (els.cName.value) {
			checkCreateName();
		}
	}

	function checkCreateName() {
		var name = els.cName.value.trim();
		if (name.length < 2) {
			warn(els.cWarn, '');
			return;
		}

		window.LivePicker.request(
			'/bb/ajax_producer_suggest.php?check=1&q=' + encodeURIComponent(name),
			null,
			function (data) {
				if (data.exact) {
					warn(els.cWarn, 'Такой производитель уже есть: «' + data.exact.name + '». Закройте окно и выберите его в списке.', 'error');
					return;
				}
				if (data.similar && data.similar.length) {
					warn(els.cWarn, 'Похожие уже есть: ' + names(data.similar) + '. Проверьте, не дубль ли это.', 'hint');
					return;
				}
				warn(els.cWarn, '');
			}
		);
	}

	function submitCreate() {
		var payload = {
			name:    els.cName.value.trim(),
			comment: els.cComment.value.trim()
		};
		if (confirmPending) {
			payload.confirm = '1';
		}

		els.cSave.disabled = true;

		window.LivePicker.request('/bb/ajax_producer_create.php', payload, function (data) {
			els.cSave.disabled = false;

			if (data.error) {
				warn(els.cWarn, data.error, 'error');
				return;
			}
			if (data.needs_confirm) {
				confirmPending = true;
				els.cSave.value = 'всё равно создать';
				warn(els.cWarn, 'Похожие: ' + names(data.similar) + '. Если это точно другой производитель — нажмите ещё раз.', 'hint');
				return;
			}
			if (data.ok) {
				picker.choose(data.producer);
				els.createModal.style.display = 'none';
			}
		}, function () {
			els.cSave.disabled = false;
			warn(els.cWarn, 'Не удалось связаться с сервером. Попробуйте ещё раз.', 'error');
		});
	}

	// -------------------------------------------------------- редактирование

	function initEditModal() {
		if (!els.editModal) {
			return;
		}

		els.eName    = $('editprod_name');
		els.eComment = $('editprod_comment');
		els.eActive  = $('editprod_active');
		els.ePreview = $('editprod_preview');
		els.eWarn    = $('editprod_warning');
		els.eSave    = $('editprod_save');
		els.eCancel  = $('editprod_cancel');

		els.eCancel.addEventListener('click', function () { els.editModal.style.display = 'none'; });
		els.editModal.addEventListener('click', function (e) {
			if (e.target === els.editModal) { els.editModal.style.display = 'none'; }
		});

		els.eSave.addEventListener('click', submitEdit);
	}

	function openEditModal() {
		var name = els.hidden.value;
		if (!name) {
			return;
		}

		window.LivePicker.request(
			'/bb/ajax_producer_suggest.php?check=1&q=' + encodeURIComponent(name),
			null,
			function (data) {
				var current = data.exact;
				if (!current) {
					return;
				}

				editingId = current.id;
				els.eName.value = current.name;
				els.eComment.value = '';
				els.eActive.checked = true;
				warn(els.eWarn, '');
				els.ePreview.textContent = '';
				els.editModal.style.display = 'flex';
				els.eName.focus();

				loadPreview();
			}
		);
	}

	function loadPreview() {
		window.LivePicker.request('/bb/ajax_producer_update.php', {
			id: editingId, name: els.eName.value.trim(), preview: '1'
		}, function (data) {
			if (data.ok) {
				els.ePreview.textContent = 'Затронет: моделей — ' + data.impact.models
					+ ', единиц товара — ' + data.impact.items
					+ ', архивных записей — ' + data.impact.items_arch + '.';
			}
		});
	}

	function submitEdit() {
		var payload = {
			id:        editingId,
			name:      els.eName.value.trim(),
			comment:   els.eComment.value.trim(),
			is_active: els.eActive.checked ? '1' : ''
		};

		els.eSave.disabled = true;

		window.LivePicker.request('/bb/ajax_producer_update.php', payload, function (data) {
			els.eSave.disabled = false;

			if (data.error) {
				warn(els.eWarn, data.error, 'error');
				return;
			}
			if (data.ok) {
				picker.choose(data.producer);
				els.editModal.style.display = 'none';
			}
		}, function () {
			els.eSave.disabled = false;
			warn(els.eWarn, 'Не удалось связаться с сервером. Попробуйте ещё раз.', 'error');
		});
	}

	// ------------------------------------------------------------- утилиты

	function names(list) {
		return list.map(function (item) { return '«' + item.name + '»'; }).join(', ');
	}

	function warn(el, text, kind) {
		el.textContent = text || '';
		el.className = 'catp__warn' + (text ? ' catp__warn--' + (kind || 'hint') : '');
		el.style.display = text ? 'block' : 'none';
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
