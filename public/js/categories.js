$(document).ready(function(){
	var sortable_categories = [].slice.call(document.querySelectorAll('[data-sortable]'));
	for (var i = 0; i < sortable_categories.length; i++) {
		var el = sortable_categories[i];
		var has_messages = $(el).data('has-messages');
		var has_categories = $(el).data('has-categories');
		var put_en = !(has_messages || has_categories);
		new Sortable(el, {
			group: {
				name: 'sortable_categories',
				put: put_en
			},
			animation: 150,
			fallbackOnBody: true,
			swapThreshold: 0.65
		});
	}


/*
	$('a[data-o]').click(function(e){
		e.preventDefault();
		var option = $(this).data('o');
		console.log(option);
		var li_el = $(this).parent().parent().parent();
		li_el.find('span.lbl').text($(this).text());
		li_el.data('option', option);
		li_el.attr('data-option', option);
	});
	*/
	$('form[method="post"]').submit(function(event) {
		var $base = $('[data-sort-base]');
		function serialize($sort){
			var ary = [];
			$sort.children('[data-id]').each(function(){
				var $item = $(this);
				ary.push({
					id: $item.data('id'),
					children: serialiaze($item)
				});
			});
			return ary;
		}
		.children('[data-id]').each(function(){
			var  = [];
			var iel = $(this);
			blocks.push({
				'id': iel.data('id'),
				'children':
			});
			var p = ul_el.attr('data-block') + '.';
			p  += ul_el.attr('data-option');
			blocks.push(p);
		});
		$('#periodic_mail_block_ary').val(blocks.join(','));
	});

});
