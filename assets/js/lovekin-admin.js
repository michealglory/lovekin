jQuery(function ($) {
	var $builder = $('[data-lk="question-builder"]');
	if (!$builder.length) {
		$builder = null;
	}

	function buildQuestion(index) {
		return (
			'<div class="lk-question-card" data-lk="question-item">' +
			'<div class="lk-question-header">' +
			'<strong>Question ' + (index + 1) + '</strong>' +
			'<button type="button" class="button button-link-delete" data-lk="remove-question">Remove</button>' +
			'</div>' +
			'<textarea name="lk_questions[' + index + '][question]" class="widefat" rows="3" placeholder="Question text"></textarea>' +
			'<div class="lk-option-grid">' +
			['A', 'B', 'C', 'D'].map(function (label, optIndex) {
				return (
					'<div class="lk-option-item">' +
					'<label>' + label + '</label>' +
					'<input type="text" class="widefat" name="lk_questions[' + index + '][options][' + optIndex + ']" />' +
					'</div>'
				);
			}).join('') +
			'</div>' +
			'<div class="lk-correct-select">' +
			'<label>Correct Answer</label>' +
			'<select name="lk_questions[' + index + '][correct]">' +
			['A', 'B', 'C', 'D'].map(function (label) {
				return '<option value="' + label + '">' + label + '</option>';
			}).join('') +
			'</select>' +
			'</div>' +
			'</div>'
		);
	}

	function updateIndexes() {
		$builder.find('[data-lk="question-item"]').each(function (index) {
			$(this).find('strong').text('Question ' + (index + 1));
			$(this).find('textarea').attr('name', 'lk_questions[' + index + '][question]');
			$(this).find('input[type="text"]').each(function (optIndex) {
				$(this).attr('name', 'lk_questions[' + index + '][options][' + optIndex + ']');
			});
			$(this).find('select').attr('name', 'lk_questions[' + index + '][correct]');
		});
	}

	if ($builder) {
		$builder.on('click', '[data-lk="add-question"]', function () {
			var index = $builder.find('[data-lk="question-item"]').length;
			$builder.find('[data-lk="question-list"]').append(buildQuestion(index));
		});

		$builder.on('click', '[data-lk="remove-question"]', function () {
			$(this).closest('[data-lk="question-item"]').remove();
			updateIndexes();
		});
	}

	var $dashboard = $('.lk-admin-dashboard');
	if ($dashboard.length && window.Chart) {
		var data = $dashboard.data('lk-admin-chart');
		var $line = $dashboard.find('[data-lk="admin-line"]');
		var $bar = $dashboard.find('[data-lk="admin-bar"]');

		if (data && $line.length) {
			new Chart($line, {
				type: 'line',
				data: {
					labels: data.labels,
					datasets: [{
						data: data.scores,
						borderColor: '#3b82f6',
						backgroundColor: 'rgba(59, 130, 246, 0.2)',
						fill: true,
						pointRadius: 3
					}]
				},
				options: { plugins: { legend: { display: false } }, responsive: true }
			});
		}

		if (data && $bar.length) {
			new Chart($bar, {
				type: 'bar',
				data: {
					labels: ['0-49', '50-74', '75-100'],
					datasets: [{
						data: [data.distribution.low, data.distribution.mid, data.distribution.high],
						backgroundColor: ['#ef4444', '#f59e0b', '#10b981']
					}]
				},
				options: { plugins: { legend: { display: false } }, responsive: true }
			});
		}
	}

	$('[data-lk="course-file-select"]').on('click', function () {
		if (typeof wp === 'undefined' || !wp.media) {
			return;
		}
		var $button = $(this);
		var $input = $button.closest('.inside').find('input[name="lk_course_file_url"]');
		var frame = wp.media({
			title: 'Select course file',
			button: { text: 'Use this file' },
			multiple: false
		});

		frame.on('select', function () {
			var attachment = frame.state().get('selection').first();
			if (attachment) {
				$input.val(attachment.get('url'));
			}
		});

		frame.open();
	});
});
