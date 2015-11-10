$(function() {
	/*
	$('div.tiles').footable().bind({
		'footable_filtered': function(e) {
			var total = 0;
			$('div.tiles > div:visible').each(function() {
				total++;
			});
			$('span#total').text(total);
		}
	});
	*/


	var total = 0;
	$('div.tiles > div:visible').each(function() {
		total++;
	});
	$('span#total').text(total);
});
