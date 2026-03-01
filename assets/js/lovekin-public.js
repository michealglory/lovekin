jQuery(function ($) {
	function parseData(value) {
		if (!value) {
			return [];
		}
		if (typeof value === 'string') {
			try {
				return JSON.parse(value);
			} catch (e) {
				return [];
			}
		}
		return value;
	}

	function buildChartData(attempts) {
		var labels = [];
		var series = [];
		var distribution = { '0-49': 0, '50-74': 0, '75-100': 0 };

		attempts.forEach(function (attempt) {
			labels.push(attempt.date);
			series.push(attempt.score);
			if (attempt.score < 50) {
				distribution['0-49'] += 1;
			} else if (attempt.score < 75) {
				distribution['50-74'] += 1;
			} else {
				distribution['75-100'] += 1;
			}
		});

		return { labels: labels, series: series, distribution: distribution };
	}

	function getQueryParam(name) {
		var params = new URLSearchParams(window.location.search);
		return params.get(name);
	}

	function removeQueryParam(name) {
		var params = new URLSearchParams(window.location.search);
		if (!params.has(name)) {
			return;
		}
		params.delete(name);
		var next = window.location.pathname + (params.toString() ? '?' + params.toString() : '') + window.location.hash;
		window.history.replaceState({}, document.title, next);
	}

	function showInlineSaved($form) {
		var $saved = $form.find('.lk-inline-saved');
		if (!$saved.length) {
			return;
		}
		var timer = $saved.data('lkHideTimer');
		if (timer) {
			clearTimeout(timer);
		}
		$saved.removeAttr('hidden').addClass('is-visible');
		timer = setTimeout(function () {
			$saved.removeClass('is-visible').attr('hidden', 'hidden');
		}, 4000);
		$saved.data('lkHideTimer', timer);
	}

	function initTimedAlerts() {
		var maxDelay = 0;
		var hasNoticeParam = getQueryParam('lk_notice');
		var hasErrorParam = getQueryParam('lk_error');
		var hasArchiveParam = getQueryParam('lk_archive');
		$('[data-lk-auto-hide]').each(function () {
			var $alert = $(this);
			var duration = parseInt($alert.attr('data-lk-auto-hide'), 10) || 0;
			if (duration <= 0) {
				return;
			}
			maxDelay = Math.max(maxDelay, duration);
			setTimeout(function () {
				$alert.fadeOut(250, function () {
					$alert.remove();
				});
			}, duration);
		});

		if (maxDelay > 0 && (hasNoticeParam || hasErrorParam || hasArchiveParam)) {
			setTimeout(function () {
				removeQueryParam('lk_notice');
				removeQueryParam('lk_error');
				removeQueryParam('lk_archive');
			}, maxDelay + 150);
		}
	}

	function initPaginator($container, options) {
		var settings = $.extend(
			{
				rowSelector: '[data-lk-page-row]',
				pageSize: parseInt($container.data('lk-page-size'), 10) || 5,
				getRows: null
			},
			options || {}
		);
		var state = {
			currentPage: 1,
			totalPages: 1
		};

		var $controls = $container.find('.lk-pagination');
		if (!$controls.length) {
			$controls = $(
				'<div class="lk-pagination" data-lk="pagination-controls">' +
					'<button type="button" class="lk-button lk-button--ghost" data-lk-page-prev>Prev</button>' +
					'<span class="lk-page-label" data-lk-page-label></span>' +
					'<button type="button" class="lk-button lk-button--ghost" data-lk-page-next>Next</button>' +
				'</div>'
			);
			$container.append($controls);
		}

		var $prev = $controls.find('[data-lk-page-prev]');
		var $next = $controls.find('[data-lk-page-next]');
		var $label = $controls.find('[data-lk-page-label]');

		function getRows() {
			if (typeof settings.getRows === 'function') {
				return settings.getRows();
			}
			return $container.find(settings.rowSelector).toArray();
		}

		function renderPage() {
			var rows = getRows();
			var total = rows.length;
			state.totalPages = Math.max(1, Math.ceil(total / settings.pageSize));
			if (state.currentPage > state.totalPages) {
				state.currentPage = state.totalPages;
			}
			if (state.currentPage < 1) {
				state.currentPage = 1;
			}

			$container.find(settings.rowSelector).addClass('lk-page-hidden');

			if (total > 0) {
				var start = (state.currentPage - 1) * settings.pageSize;
				var end = start + settings.pageSize;
				rows.forEach(function (row, index) {
					if (index >= start && index < end) {
						$(row).removeClass('lk-page-hidden');
					}
				});
			}

			$label.text('Page ' + state.currentPage + ' of ' + state.totalPages);
			$prev.prop('disabled', state.currentPage <= 1);
			$next.prop('disabled', state.currentPage >= state.totalPages);
			$controls.toggle(total > settings.pageSize);
		}

		function refresh(resetPage) {
			if (resetPage) {
				state.currentPage = 1;
			}
			renderPage();
		}

		$prev.on('click', function () {
			if (state.currentPage > 1) {
				state.currentPage -= 1;
				renderPage();
			}
		});

		$next.on('click', function () {
			if (state.currentPage < state.totalPages) {
				state.currentPage += 1;
				renderPage();
			}
		});

		renderPage();

		return {
			renderPage: renderPage,
			refresh: refresh,
			next: function () {
				if (state.currentPage < state.totalPages) {
					state.currentPage += 1;
					renderPage();
				}
			},
			prev: function () {
				if (state.currentPage > 1) {
					state.currentPage -= 1;
					renderPage();
				}
			}
		};
	}

	function updateHero($report, attempts) {
		if (!attempts.length) {
			$report.find('[data-lk="hero-average"]').text('0.0%');
			$report.find('[data-lk="hero-count"]').text('0');
			$report.find('[data-lk="hero-recent"]').text('0.0%');
			return;
		}

		var total = attempts.reduce(function (sum, item) {
			return sum + item.score;
		}, 0);
		var average = total / attempts.length;
		var recent = attempts.slice(-5);
		var recentTotal = recent.reduce(function (sum, item) {
			return sum + item.score;
		}, 0);
		var recentAverage = recentTotal / Math.max(1, recent.length);

		$report.find('[data-lk="hero-average"]').text(average.toFixed(1) + '%');
		$report.find('[data-lk="hero-count"]').text(attempts.length);
		$report.find('[data-lk="hero-recent"]').text(recentAverage.toFixed(1) + '%');
	}

	initTimedAlerts();

	$('[data-lk="report"]').each(function () {
		var $report = $(this);
		var baseData = parseData($report.data('lk-chart'));
		var attempts = parseData($report.data('lk-attempts'));

		var $lineCanvas = $report.find('[data-lk="chart-line"]');
		var $barCanvas = $report.find('[data-lk="chart-bar"]');
		var $attemptsPagination = $report.find('[data-lk-pagination="attempts"]');
		var lineChart = null;
		var barChart = null;
		var attemptsPaginator = null;

		if ($attemptsPagination.length) {
			attemptsPaginator = initPaginator($attemptsPagination, {
				rowSelector: 'tbody tr[data-lk-page-row]',
				pageSize: 5,
				getRows: function () {
					return $attemptsPagination
						.find('tbody tr[data-lk-page-row]')
						.filter(function () {
							return $(this).attr('data-lk-filter-match') !== '0';
						})
						.toArray();
				}
			});
		}

		if ($lineCanvas.length && window.Chart) {
			lineChart = new Chart($lineCanvas, {
				type: 'line',
				data: {
					labels: baseData.labels || [],
					datasets: [
						{
							label: 'Score',
							data: baseData.series || [],
							borderColor: '#3b82f6',
							backgroundColor: 'rgba(59, 130, 246, 0.2)',
							fill: true,
							pointRadius: 4,
							pointBackgroundColor: '#3b82f6'
						}
					]
				},
				options: {
					responsive: true,
					plugins: {
						legend: { display: false }
					}
				}
			});
		}

		if ($barCanvas.length && window.Chart) {
			barChart = new Chart($barCanvas, {
				type: 'bar',
				data: {
					labels: baseData.distribution ? Object.keys(baseData.distribution) : [],
					datasets: [
						{
							label: 'Count',
							data: baseData.distribution ? Object.values(baseData.distribution) : [],
							backgroundColor: ['#ef4444', '#f59e0b', '#10b981']
						}
					]
				},
				options: {
					responsive: true,
					plugins: {
						legend: { display: false }
					}
				}
			});
		}

		function applyReportFilters() {
			var course = $report.find('[data-lk="report-course"]').val();
			var range = $report.find('[data-lk="report-range"]').val();
			var now = Date.now() / 1000;
			var cutoff = 0;
			if (range !== 'all') {
				cutoff = now - parseInt(range, 10) * 86400;
			}

			var filtered = attempts.filter(function (attempt) {
				var matchesCourse = course === 'all' || String(attempt.course_id) === String(course);
				var matchesRange = range === 'all' || attempt.timestamp >= cutoff;
				return matchesCourse && matchesRange;
			});

			filtered.sort(function (a, b) {
				return a.timestamp - b.timestamp;
			});

			updateHero($report, filtered);

			if (lineChart) {
				var chartData = buildChartData(filtered);
				lineChart.data.labels = chartData.labels;
				lineChart.data.datasets[0].data = chartData.series;
				lineChart.update();
			}

			if (barChart) {
				var distribution = buildChartData(filtered).distribution;
				barChart.data.labels = Object.keys(distribution);
				barChart.data.datasets[0].data = Object.values(distribution);
				barChart.update();
			}

			$report.find('tbody tr[data-course]').each(function () {
				var $row = $(this);
				var rowCourse = $row.data('course');
				var rowTimestamp = $row.data('timestamp');
				var matchesCourse = course === 'all' || String(rowCourse) === String(course);
				var matchesRange = range === 'all' || rowTimestamp >= cutoff;
				var isMatch = matchesCourse && matchesRange;
				$row.attr('data-lk-filter-match', isMatch ? '1' : '0');
				if (!attemptsPaginator) {
					$row.toggle(isMatch);
				}
			});

			if (attemptsPaginator) {
				attemptsPaginator.refresh(true);
			}
		}

		$report.find('[data-lk="report-course"], [data-lk="report-range"]').on('change', applyReportFilters);
		applyReportFilters();
	});

	$('[data-lk-pagination]').each(function () {
		var $container = $(this);
		if ($container.data('lk-pagination') === 'attempts') {
			return;
		}
		if (!$container.find('[data-lk-page-row]').length) {
			return;
		}
		initPaginator($container, {
			rowSelector: '[data-lk-page-row]',
			pageSize: parseInt($container.data('lk-page-size'), 10) || 5
		});
	});

	var savedAttemptId = getQueryParam('lk_saved_attempt');
	if (savedAttemptId) {
		$('.lk-remark-form[data-lk-attempt-id="' + savedAttemptId + '"]').each(function () {
			showInlineSaved($(this));
		});
		removeQueryParam('lk_saved_attempt');
	}

	$('.lk-remark-form[data-lk-ajax="1"]').on('submit', function (event) {
		var $form = $(this);
		var ajaxUrl = window.lovekinPublic && window.lovekinPublic.ajaxUrl ? window.lovekinPublic.ajaxUrl : '';
		var nonce = $form.find('input[name="lk_ajax_nonce"]').val() || (window.lovekinPublic && window.lovekinPublic.remarkSaveNonce ? window.lovekinPublic.remarkSaveNonce : '');
		if (!ajaxUrl || !nonce) {
			return;
		}

		event.preventDefault();

		var $button = $form.find('button[type="submit"]');
		var originalText = $button.text();
		var savingText = window.lovekinPublic && window.lovekinPublic.savingText ? window.lovekinPublic.savingText : 'Saving...';
		var failedText = window.lovekinPublic && window.lovekinPublic.remarkSaveFailed ? window.lovekinPublic.remarkSaveFailed : 'Unable to save remark. Please try again.';

		$button.prop('disabled', true).text(savingText);

		$.post(ajaxUrl, {
			action: 'lk_update_remark',
			nonce: nonce,
			attempt_id: $form.find('input[name="attempt_id"]').val(),
			remark: $form.find('textarea[name="remark"]').val()
		})
			.done(function (response) {
				if (response && response.success) {
					showInlineSaved($form);
				} else {
					window.alert((response && response.data && response.data.message) ? response.data.message : failedText);
				}
			})
			.fail(function (xhr) {
				var message = failedText;
				if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
					message = xhr.responseJSON.data.message;
				}
				window.alert(message);
			})
			.always(function () {
				$button.prop('disabled', false).text(originalText || (window.lovekinPublic && window.lovekinPublic.saveText ? window.lovekinPublic.saveText : 'Save'));
			});
	});

	$('[data-lk="doc-search"]').each(function () {
		var $search = $(this);
		var $card = $search.closest('.lk-card');
		var $filter = $card.find('[data-lk="doc-filter"]');
		var $cards = $card.find('.lk-document-card');

		function applyDocFilter() {
			var term = ($search.val() || '').toLowerCase();
			var category = $filter.val();
			$cards.each(function () {
				var $doc = $(this);
				var matchesCategory = category === 'all' || $doc.data('category') === category;
				var matchesTerm = !$doc.data('title') || String($doc.data('title')).indexOf(term) !== -1;
				$doc.toggle(matchesCategory && matchesTerm);
			});
		}

		$search.on('input', applyDocFilter);
		$filter.on('change', applyDocFilter);
	});

	$('[data-lk="archive-search"]').each(function () {
		var $search = $(this);
		var $card = $search.closest('.lk-card');
		var $filter = $card.find('[data-lk="archive-folder"]');

		function applyArchiveFilter() {
			var term = ($search.val() || '').toLowerCase();
			var folder = $filter.val();
			$card.find('tbody tr[data-name]').each(function () {
				var $row = $(this);
				var matchesFolder = folder === 'all' || String($row.data('folder')) === folder;
				var matchesTerm = !$row.data('name') || String($row.data('name')).indexOf(term) !== -1;
				$row.toggle(matchesFolder && matchesTerm);
			});
		}

		$search.on('input', applyArchiveFilter);
		$filter.on('change', applyArchiveFilter);
	});

	$('[data-lk="funding-form"]').each(function () {
		var $form = $(this);
		var $stepper = $form.closest('.lk-card').find('.lk-stepper');
		var currentStep = 1;

		function setStep(step) {
			currentStep = step;
			$form.find('.lk-step-panel').removeClass('is-active');
			$form.find('.lk-step-panel[data-step="' + step + '"]').addClass('is-active');
			$stepper.find('.lk-step').each(function () {
				var $step = $(this);
				var stepNumber = parseInt($step.data('step'), 10);
				$step.toggleClass('is-active', stepNumber === step);
				$step.toggleClass('is-complete', stepNumber < step);
			});

			if (step === 3) {
				$form.find('[data-lk-review="format"]').text($form.find('select[name="format"] option:selected').text());
				var amount = $form.find('input[name="amount"]').val();
				$form.find('[data-lk-review="amount"]').text(amount ? '\u20A6' + amount : '--');
				$form.find('[data-lk-review="purpose"]').text($form.find('textarea[name="purpose"]').val() || '--');
				$form.find('[data-lk-review="account_name"]').text($form.find('input[name="account_name"]').val() || '--');
				$form.find('[data-lk-review="account_number"]').text($form.find('input[name="account_number"]').val() || '--');
				$form.find('[data-lk-review="bank_name"]').text($form.find('input[name="bank_name"]').val() || '--');
			}
		}

		function showError(message, $fields) {
			var $error = $form.prev('[data-lk="form-error"]');
			if ($error.length) {
				$error.text(message).show();
			}
			$form.find('.is-invalid').removeClass('is-invalid');
			if ($fields && $fields.length) {
				$fields.addClass('is-invalid');
				$fields.each(function () {
					var $field = $(this);
					var $group = $field.closest('.lk-input-group');
					if ($group.length) {
						$group.addClass('is-invalid');
					}
				});
			}
		}

		function clearError() {
			var $error = $form.prev('[data-lk="form-error"]');
			if ($error.length) {
				$error.hide().text('');
			}
			$form.find('.is-invalid').removeClass('is-invalid');
		}

		$form.on('click', '[data-lk="next-step"]', function () {
			clearError();
			if (currentStep === 1) {
				var $required = $form.find('.lk-step-panel[data-step="1"]').find('select[name="format"], input[name="amount"]');
				var missing = $required.filter(function () {
					return !$(this).val();
				});
				if (missing.length) {
					showError('Please complete all required fields before continuing.', missing);
					return;
				}
			}
			if (currentStep === 2) {
				var $requiredStep2 = $form.find('.lk-step-panel[data-step="2"]').find('textarea[name="purpose"], input[name="account_name"], input[name="account_number"], input[name="bank_name"]');
				var missingStep2 = $requiredStep2.filter(function () {
					return !$(this).val();
				});
				if (missingStep2.length) {
					showError('Please complete all required fields before continuing.', missingStep2);
					return;
				}
			}
			if (currentStep < 3) {
				setStep(currentStep + 1);
			}
		});

		$form.on('click', '[data-lk="prev-step"]', function () {
			clearError();
			if (currentStep > 1) {
				setStep(currentStep - 1);
			}
		});

		setStep(1);
	});

	$('[data-lk="dashboard"]').each(function () {
		var $dashboard = $(this);
		var $toggle = $dashboard.find('[data-lk="menu-toggle"]');
		var $tabsWrap = $dashboard.find('[data-lk="tabs-wrap"]');

		if (!$toggle.length || !$tabsWrap.length) {
			return;
		}

		function setMenuState(open) {
			$dashboard.toggleClass('is-menu-open', open);
			$toggle.attr('aria-expanded', open ? 'true' : 'false');
		}

		$toggle.on('click', function () {
			setMenuState(!$dashboard.hasClass('is-menu-open'));
		});

		$dashboard.find('[data-lk="tab"]').on('click', function () {
			setMenuState(false);
		});

		$(document).on('click', function (event) {
			if (!$dashboard.hasClass('is-menu-open')) {
				return;
			}
			if ($dashboard[0].contains(event.target)) {
				return;
			}
			setMenuState(false);
		});

		$(document).on('keydown', function (event) {
			if (event.key === 'Escape' && $dashboard.hasClass('is-menu-open')) {
				setMenuState(false);
			}
		});
	});

	$('[data-lk="dropzone"], [data-lk="file-picker"]').each(function () {
		var $zone = $(this);
		var $input = $zone.find('input[type="file"]');
		var $fileName = $zone.find('[data-lk="file-name"]');
		if (!$fileName.length) {
			$fileName = $zone.parent().find('[data-lk="file-name"]');
		}
		var defaultFileName = $fileName.length ? $.trim($fileName.text()) : '';

		function updateFileName() {
			if (!$fileName.length) {
				return;
			}
			var files = $input[0] && $input[0].files ? $input[0].files : [];
			if (files.length) {
				$fileName.text(files[0].name);
			} else {
				$fileName.text(defaultFileName);
			}
		}

		if ($zone.data('lk') === 'dropzone') {
			$zone.on('dragover', function (event) {
				event.preventDefault();
				$zone.addClass('is-dragover');
			});

			$zone.on('dragleave', function () {
				$zone.removeClass('is-dragover');
			});

			$zone.on('drop', function (event) {
				event.preventDefault();
				$zone.removeClass('is-dragover');
				var files = event.originalEvent && event.originalEvent.dataTransfer ? event.originalEvent.dataTransfer.files : null;
				if (files && files.length) {
					$input[0].files = files;
					updateFileName();
				}
			});
		}

		$input.on('change', updateFileName);
		updateFileName();
	});
});
