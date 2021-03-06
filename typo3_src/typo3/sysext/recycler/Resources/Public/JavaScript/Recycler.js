/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

/**
 * RequireJS module for Recycler
 */
define(['jquery', 'nprogress', 'TYPO3/CMS/Backend/jquery.clearable'], function($, NProgress) {
	var Recycler = {
		identifiers: {
			searchForm: '#recycler-form',
			searchText: '#recycler-form [name=search-text]',
			searchSubmitBtn: '#recycler-form button[type=submit]',
			depthSelector: '#recycler-form [name=depth]',
			tableSelector: '#recycler-form [name=pages]',
			recyclerTable: '#itemsInRecycler',
			paginator: '#recycler-index nav',
			reloadAction: 'a[data-action=reload]',
			massUndo: 'button[data-action=massundo]',
			massDelete: 'button[data-action=massdelete]',
			toggleAll: '.t3js-toggle-all'
		},
		elements: {}, // filled in getElements()
		paging: {
			currentPage: 1,
			totalPages: 1,
			totalItems: 0,
			itemsPerPage: 20
		},
		markedRecordsForMassAction: [],
		allToggled: false
	};

	/**
	 * Gets required elements
	 */
	Recycler.getElements = function() {
		Recycler.elements = {
			$searchForm: $(Recycler.identifiers.searchForm),
			$searchTextField: $(Recycler.identifiers.searchText),
			$searchSubmitBtn: $(Recycler.identifiers.searchSubmitBtn),
			$depthSelector: $(Recycler.identifiers.depthSelector),
			$tableSelector: $(Recycler.identifiers.tableSelector),
			$recyclerTable: $(Recycler.identifiers.recyclerTable),
			$tableBody: $(Recycler.identifiers.recyclerTable).find('tbody'),
			$paginator: $(Recycler.identifiers.paginator),
			$reloadAction: $(Recycler.identifiers.reloadAction),
			$massUndo: $(Recycler.identifiers.massUndo),
			$massDelete: $(Recycler.identifiers.massDelete),
			$toggleAll: $(Recycler.identifiers.toggleAll)
		};
	};

	/**
	 * Register events
	 */
	Recycler.registerEvents = function() {
		// submitting the form
		Recycler.elements.$searchForm.on('submit', function(e) {
			e.preventDefault();
			if (Recycler.elements.$searchTextField.val() !== '') {
				Recycler.loadDeletedElements();
			}
		});

		// changing the search field
		Recycler.elements.$searchTextField.on('keyup', function() {
			var $me = $(this);

			if ($me.val() !== '') {
				Recycler.elements.$searchSubmitBtn.removeClass('disabled');
			} else {
				Recycler.elements.$searchSubmitBtn.addClass('disabled');
				Recycler.loadDeletedElements();
			}
		}).clearable(
			{
				onClear: function() {
					Recycler.elements.$searchSubmitBtn.addClass('disabled');
					Recycler.loadDeletedElements();
				}
			}
		);

		// changing "depth"
		Recycler.elements.$depthSelector.on('change', function() {
			Recycler.loadAvailableTables();
			Recycler.loadDeletedElements();
		});

		// changing "table"
		Recycler.elements.$tableSelector.on('change', function() {
			Recycler.paging.currentPage = 1;
			Recycler.loadDeletedElements();
		});

		// clicking "recover" in single row
		Recycler.elements.$recyclerTable.on('click', '[data-action=undo]', Recycler.undoRecord);

		// clicking "delete" in single row
		Recycler.elements.$recyclerTable.on('click', '[data-action=delete]', Recycler.deleteRecord);

		Recycler.elements.$reloadAction.on('click', function(e) {
			e.preventDefault();
			Recycler.loadAvailableTables();
			Recycler.loadDeletedElements();
		});

		// clicking an action in the paginator
		Recycler.elements.$paginator.on('click', 'a[data-action]', function(e) {
			e.preventDefault();

			var $el = $(this),
				reload = false;

			switch ($el.data('action')) {
				case 'previous':
					if (Recycler.paging.currentPage > 1) {
						Recycler.paging.currentPage--;
						reload = true;
					}
					break;
				case 'next':
					if (Recycler.paging.currentPage < Recycler.paging.totalPages) {
						Recycler.paging.currentPage++;
						reload = true;
					}
					break;
				case 'page':
					Recycler.paging.currentPage = parseInt($el.find('span').text());
					reload = true;
					break;
			}

			if (reload) {
				Recycler.loadDeletedElements();
			}
		});

		if (!TYPO3.settings.Recycler.deleteDisable) {
			Recycler.elements.$massDelete.show();
		} else {
			Recycler.elements.$massDelete.remove();
		}

		Recycler.elements.$recyclerTable.on('show.bs.collapse hide.bs.collapse', 'tr.collapse', function(e) {
			var $trigger = $(e.currentTarget).prev('tr').find('[data-action=expand]'),
				$iconEl = $trigger.find('.t3-icon'),
				removeClass,
				addClass;

			switch (e.type) {
				case 'show':
					removeClass = 't3-icon-pagetree-collapse';
					addClass = 't3-icon-pagetree-expand';
					break;
				case 'hide':
					removeClass = 't3-icon-pagetree-expand';
					addClass = 't3-icon-pagetree-collapse';
					break;
			}

			$iconEl.removeClass(removeClass).addClass(addClass);
		});

		// checkboxes in the table
		Recycler.elements.$toggleAll.on('click', function() {
			Recycler.allToggled = !Recycler.allToggled;
			$('input[type="checkbox"]').prop('checked', Recycler.allToggled).trigger('change');
		});
		Recycler.elements.$recyclerTable.on('change', 'tr input[type=checkbox]', Recycler.handleCheckboxSelects);

		Recycler.elements.$massUndo.on('click', Recycler.undoRecord);
		Recycler.elements.$massDelete.on('click', Recycler.deleteRecord);
	};

	/**
	 * Initialize the recycler module
	 */
	Recycler.initialize = function() {
		NProgress.configure({parent: '#typo3-docheader', showSpinner: false});

		Recycler.getElements();
		Recycler.registerEvents();

		if (TYPO3.settings.Recycler.depthSelection > 0) {
			Recycler.elements.$depthSelector.val(TYPO3.settings.Recycler.depthSelection).trigger('change');
		} else {
			Recycler.loadAvailableTables();
			Recycler.loadDeletedElements();
		}
	};

	/**
	 * Handles the clicks on checkboxes in the records table
	 */
	Recycler.handleCheckboxSelects = function() {
		var $checkbox = $(this),
			$tr = $checkbox.parents('tr'),
			table = $tr.data('table'),
			uid = $tr.data('uid'),
			record = table + ':' + uid;

		if ($checkbox.prop('checked')) {
			Recycler.markedRecordsForMassAction.push(record);
			$tr.addClass('warning');
		} else {
			var index = Recycler.markedRecordsForMassAction.indexOf(record);
			if (index > -1) {
				Recycler.markedRecordsForMassAction.splice(index, 1);
			}
			$tr.removeClass('warning');
		}

		if (Recycler.markedRecordsForMassAction.length > 0) {
			if (Recycler.elements.$massUndo.hasClass('disabled')) {
				Recycler.elements.$massUndo.removeClass('disabled');
			}
			if (Recycler.elements.$massDelete.hasClass('disabled')) {
				Recycler.elements.$massDelete.removeClass('disabled');
			}

			var btnTextUndo = Recycler.createMessage(TYPO3.lang['button.undoselected'], [Recycler.markedRecordsForMassAction.length]),
				btnTextDelete = Recycler.createMessage(TYPO3.lang['button.deleteselected'], [Recycler.markedRecordsForMassAction.length]);

			Recycler.elements.$massUndo.text(btnTextUndo);
			Recycler.elements.$massDelete.text(btnTextDelete);
		} else {
			Recycler.resetMassActionButtons();
		}
	};

	/**
	 * Resets the mass action state
	 */
	Recycler.resetMassActionButtons = function() {
		Recycler.markedRecordsForMassAction = [];
		Recycler.elements.$massUndo.addClass('disabled').text(TYPO3.lang['button.undo']);
		Recycler.elements.$massDelete.addClass('disabled').text(TYPO3.lang['button.delete']);
	};

	/**
	 * Loads all tables which contain deleted records. This call is not asynchronous
	 * due to settings the stored table before loading the deleted records.
	 */
	Recycler.loadAvailableTables = function() {
		$.ajax({
			url: TYPO3.settings.ajaxUrls['RecyclerAjaxController::dispatch'],
			dataType: 'json',
			async: false,
			data: {
				action: 'getTables',
				startUid: TYPO3.settings.Recycler.startUid,
				depth: Recycler.elements.$depthSelector.find('option:selected').val()
			},
			beforeSend: function() {
				NProgress.start();
				Recycler.elements.$tableSelector.val('');
				Recycler.paging.currentPage = 1;
			},
			success: function(data) {
				var tables = [];
				Recycler.elements.$tableSelector.children().remove();
				$.each(data, function(_, value) {
					var tableName = value[0],
						deletedRecords = value[1],
						tableDescription = value[2];

					if (tableDescription === '') {
						tableDescription = TYPO3.lang['label_allrecordtypes'];
					}
					var optionText = tableDescription + ' (' + deletedRecords + ')';
					tables.push($('<option />').val(tableName).text(optionText))
				});

				if (tables.length > 0) {
					Recycler.elements.$tableSelector.append(tables);
					if (TYPO3.settings.Recycler.tableSelection !== '') {
						Recycler.elements.$tableSelector.val(TYPO3.settings.Recycler.tableSelection);
					}
				}
			},
			complete: function() {
				NProgress.done();
			}
		});
	};

	/**
	 * Loads the deleted elements, based on the filters
	 */
	Recycler.loadDeletedElements = function() {
		$.ajax({
			url: TYPO3.settings.ajaxUrls['RecyclerAjaxController::dispatch'],
			dataType: 'json',
			data: {
				action: 'getDeletedRecords',
				depth: Recycler.elements.$depthSelector.find('option:selected').val(),
				startUid: TYPO3.settings.Recycler.startUid,
				table: Recycler.elements.$tableSelector.find('option:selected').val(),
				filterTxt: Recycler.elements.$searchTextField.val(),
				start: (Recycler.paging.currentPage -1) * Recycler.paging.itemsPerPage,
				limit: Recycler.paging.itemsPerPage
			},
			beforeSend: function() {
				NProgress.start();
				Recycler.resetMassActionButtons();
			},
			success: function(data) {
				Recycler.elements.$tableBody.html(data.rows);
				Recycler.buildPaginator(data.totalItems);
			},
			complete: function() {
				NProgress.done();
			}
		});
	};

	Recycler.deleteRecord = function() {
		if (TYPO3.settings.Recycler.deleteDisable) {
			return;
		}

		var $tr = $(this).parents('tr'),
			isMassDelete = $tr.parent().prop('tagName') !== 'TBODY'; // deleteRecord() was invoked by the mass delete button

		if (isMassDelete) {
			var records = Recycler.markedRecordsForMassAction,
				message = TYPO3.lang['modal.massdelete.text'];
		} else {
			var uid = $tr.data('uid'),
				table = $tr.data('table'),
				records = table + ':' + uid,
				recordTitle = $tr.data('recordtitle'),
				message = table === 'pages' ? TYPO3.lang['modal.deletepage.text'] : TYPO3.lang['modal.deletecontent.text'];
				message = Recycler.createMessage(message, [recordTitle, '[' + records + ']']);
		}

		top.TYPO3.Modal.confirm(TYPO3.lang['modal.delete.header'], message, top.TYPO3.Severity.error, [
			{
				text: TYPO3.lang['button.cancel'],
				trigger: function() {
					top.TYPO3.Modal.dismiss();
				}
			}, {
				text: TYPO3.lang['button.delete'],
				btnClass: 'btn-danger',
				trigger: function() {
					Recycler.callAjaxAction('delete', typeof records === 'object' ? records : [records], isMassDelete);
				}
			}
		]);
	};

	Recycler.undoRecord = function() {
		var $tr = $(this).parents('tr'),
			isMassUndo = $tr.parent().prop('tagName') !== 'TBODY'; // undoRecord() was invoked by the mass delete button

		if (isMassUndo) {
			var records = Recycler.markedRecordsForMassAction,
				messageText = TYPO3.lang['modal.massundo.text'],
				recoverPages = true;
		} else {
			var uid = $tr.data('uid'),
				table = $tr.data('table'),
				records = table + ':' + uid,
				recordTitle = $tr.data('recordtitle'),
				$message = null,
				recoverPages = table === 'pages',
				messageText = recoverPages ? TYPO3.lang['modal.undopage.text'] : TYPO3.lang['modal.undocontent.text'];
				messageText = Recycler.createMessage(messageText, [recordTitle, '[' + records + ']']);
		}

		if (recoverPages) {
			$message = $('<div />').append(
				$('<p />').text(messageText),
				$('<div />', {class: 'checkbox'}).append(
					$('<label />').append(TYPO3.lang['modal.undo.recursive']).prepend($('<input />', {id: 'undo-recursive', type: 'checkbox'}))
				)
			);
		} else {
			$message = messageText;
		}

		top.TYPO3.Modal.confirm(TYPO3.lang['modal.undo.header'], $message, top.TYPO3.Severity.ok, [
			{
				text: TYPO3.lang['button.cancel'],
				trigger: function() {
					top.TYPO3.Modal.dismiss();
				}
			}, {
				text: TYPO3.lang['button.undo'],
				btnClass: 'btn-success',
				trigger: function() {
					Recycler.callAjaxAction('undo', typeof records === 'object' ? records : [records], isMassUndo);
				}
			}
		]);
	};

	/**
	 * Method that really calls the action via AJAX
	 */
	Recycler.callAjaxAction = function(action, records, isMassAction) {
		var data = {
				records: records,
				action: ''
			},
			reloadPageTree = false;
		if (action === 'undo') {
			data.action = 'undoRecords';
			data.recursive = top.TYPO3.jQuery('#undo-recursive').prop('checked')  ? 1 : 0;
			reloadPageTree = true;
		} else if (action === 'delete') {
			data.action = 'deleteRecords';
		} else {
			return;
		}

		$.ajax({
			url: TYPO3.settings.ajaxUrls['RecyclerAjaxController::dispatch'],
			dataType: 'json',
			data: data,
			beforeSend: function() {
				NProgress.start();
			},
			success: function(data) {
				if (data.success) {
					top.TYPO3.Notification.success('', data.message);
				} else {
					top.TYPO3.Notification.error('', data.message);
				}

				// reload recycler data
				Recycler.paging.currentPage = 1;
				Recycler.loadAvailableTables();
				Recycler.loadDeletedElements();

				if (isMassAction) {
					Recycler.resetMassActionButtons();
				}

				if (reloadPageTree) {
					Recycler.refreshPageTree();
				}

				// Reset toggle state
				Recycler.allToggled = false;
			},
			complete: function() {
				top.TYPO3.Modal.dismiss();
				NProgress.done();
			}
		});
	};

	/**
	 * Replaces the placeholders with actual values
	 */
	Recycler.createMessage = function(message, placeholders) {
		if (typeof message === 'undefined') {
			return '';
		}

		return message.replace(
			/\{([0-9]+)\}/g,
			function(_, index) {
				return placeholders[index];
			}
		);
	};

	/**
	 * Reloads the page tree
	 */
	Recycler.refreshPageTree = function() {
		if (top.TYPO3 && top.TYPO3.Backend && top.TYPO3.Backend.NavigationContainer && top.TYPO3.Backend.NavigationContainer.PageTree) {
			top.TYPO3.Backend.NavigationContainer.PageTree.refreshTree();
		}
	};

	/**
	 * Build the paginator
	 */
	Recycler.buildPaginator = function(totalItems) {
		if (totalItems === 0) {
			Recycler.elements.$paginator.contents().remove();
			return;
		}

		Recycler.paging.totalItems = totalItems;
		Recycler.paging.totalPages = Math.ceil(totalItems / Recycler.paging.itemsPerPage);

		if (Recycler.paging.totalPages === 1) {
			// early abort if only one page is available
			Recycler.elements.$paginator.contents().remove();
			return;
		}

		var $ul = $('<ul />', {class: 'pagination pagination-block'}),
			liElements = [],
			$controlFirstPage = $('<li />').append(
				$('<a />', {'data-action': 'previous'}).append(
					$('<span />', {class: 't3-icon fa fa-arrow-left'})
				)
			),
			$controlLastPage = $('<li />').append(
				$('<a />', {'data-action': 'next'}).append(
					$('<span />', {class: 't3-icon fa fa-arrow-right'})
				)
			);

		if (Recycler.paging.currentPage === 1) {
			$controlFirstPage.disablePagingAction();
		}

		if (Recycler.paging.currentPage === Recycler.paging.totalPages) {
			$controlLastPage.disablePagingAction();
		}

		for (var i = 1; i <= Recycler.paging.totalPages; i++) {
			var $li = $('<li />', {class: Recycler.paging.currentPage === i ? 'active' : ''});
			$li.append(
				$('<a />', {'data-action': 'page'}).append(
					$('<span />').text(i)
				)
			);
			liElements.push($li);
		}

		$ul.append($controlFirstPage, liElements, $controlLastPage);
		Recycler.elements.$paginator.html($ul);
	};

	/**
	 * Changes the markup of a pagination action being disabled
	 */
	$.fn.disablePagingAction = function() {
		$(this).addClass('disabled').find('.t3-icon').unwrap().wrap($('<span />'));
	};

	/**
	 * return the main Recycler object
	 * initialize once on document ready
	 */
	return function() {
		$(document).ready(function() {
			Recycler.initialize();
		});

		return Recycler;
	}();
});
