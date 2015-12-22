$(document).ready(function() {

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
		sortBy: 'letscode',
		sortAscending: true
	});

	$grid.imagesLoaded().progress( function() {
	  $grid.isotope('layout');
	});

	$('.sort-by').on( 'click', 'button', function() {

		var $prev_active = $(this).parent().find('.active');

		var sortBy = $(this).attr('data-sort-by');

		var $prev_i = $prev_active.find('i');
		var $this_i = $(this).find('i');

		if ($(this).hasClass('active')){

			if ($this_i.hasClass('fa-sort-asc')){
				$this_i.removeClass('fa-sort-asc').addClass('fa-sort-desc');
				$grid.isotope({
					sortAscending: false
				});
			} else {
				$this_i.removeClass('fa-sort-desc').addClass('fa-sort-asc');
				$grid.isotope({
					sortAscending: true
				});
			}
		}
		else
		{
			$prev_active.removeClass('active');
			$prev_i.removeClass('fa-sort-asc fa-sort-desc').addClass('fa-sort');
			$(this).addClass('active');
			$this_i.removeClass('fa-sort').addClass('fa-sort-asc');

			$grid.isotope({
				sortBy: sortBy,
				sortAscending: true
			});
		}
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
		}, 300);
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
