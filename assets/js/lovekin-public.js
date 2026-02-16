jQuery(function ($) {
	$('[data-lk="report"]').each(function () {
		var $report = $(this);
		var data = $report.data('lk-chart');
		if (!data) {
			return;
		}

		var lineCtx = $report.find('[data-lk="chart-line"]');
		var barCtx = $report.find('[data-lk="chart-bar"]');

		if (lineCtx.length && window.Chart) {
			new Chart(lineCtx, {
				type: 'line',
				data: {
					labels: data.labels,
					datasets: [{
						label: 'Score',
						data: data.series,
						borderColor: '#3b82f6',
						backgroundColor: 'rgba(59, 130, 246, 0.2)',
						fill: true,
						pointRadius: 4,
						pointBackgroundColor: '#3b82f6'
					}]
				},
				options: {
					responsive: true,
					plugins: {
						legend: { display: false }
					}
				}
			});
		}

		if (barCtx.length && window.Chart) {
			new Chart(barCtx, {
				type: 'bar',
				data: {
					labels: Object.keys(data.distribution),
					datasets: [{
						label: 'Count',
						data: Object.values(data.distribution),
						backgroundColor: ['#ef4444', '#f59e0b', '#10b981']
					}]
				},
				options: {
					responsive: true,
					plugins: {
						legend: { display: false }
					}
				}
			});
		}
	});
});
