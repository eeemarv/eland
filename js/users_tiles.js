$(function() {

	var $grid = $('.tiles');

	$grid.isotope({

		itemSelector: '.tile',
		filter: function(){
			var str = $(this).find('.caption').text().toLowerCase();
			return (str.indexOf($('#q').val().toLowerCase()) > -1) ? true : false;
		},
		getSortData: {
			letscode: '.letscode',
			name: '.name',
			postcode: '.postcode'
		},
		sortBy: 'original-order'
	});

	$('.sort-by').on( 'click', 'button', function() {
		$(this).siblings().removeClass('active');
		$(this).siblings('.fa').removeClass('fa-sort-asc').removeClass('fa-sort-desc');
		$(this).addClass('active');
		var sortBy = $(this).attr('data-sort-by');
		$grid.isotope({ sortBy: sortBy });
	});

	$('#q').keyup(function(){
		var q = $(this).val().toLowerCase();

		$grid.isotope({
			filter: function(){
				var str = $(this).find('.caption').text().toLowerCase();
				return (str.indexOf(q) > -1) ? true : false;
			}
		});
	});

	$grid.on('layoutComplete', function(e, items){
		setTimeout(function(){
			calcTotal();
		}, 100);
	});

	function calcTotal(){
		var total = 0;
		$('div.tiles > div.tile:visible').each(function() {
			total++;
		});
		$('span#total').text(total);
	}

	calcTotal();
});
