// TakeATour.js

/**
 * Starts a tour
 * @param {string} tourId 
 */
async function takeATour(el, tourId) {
	el.classList.add('tour-loading');
	const $el = $(el).parents('.take-a-tour')
	$el.find('.tour-icon').hide();
	$el.find('.tour-icon-loading').show();
	fetch(app_path_webroot + 'index.php?pid=' + pid + '&route=TakeATourController:load&tour_id=' + tourId)
	.then(async response => {
		const tour = await response.json();
		startTour(tour);
	})
	.catch(error => showToast(lang.global_01, 'Failed to load tour', 'error'))
	.finally(function () {
		el.classList.remove('tour-loading');
		$el.find('.tour-icon-loading').hide();
		$el.find('.tour-icon').show();
	});
}

function startTour(tour) {
	console.log('Starting tour:', tour);
}