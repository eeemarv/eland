jQuery(function(){
	var $active = $('[data-active-blocks]');
	var $inactive = $('[data-inactive-blocks]');
	var $block_layout = $('[data-block-layout]');
	var $block_select_options = $('[data-block-select-options');

	Sortable.create($active.get(0), {
		group: 'periodic_mail',
		onAdd: function (evt){
			evt.item.classList.remove('bg-danger');
			evt.item.classList.add('bg-success');
		}
	});

	Sortable.create($inactive.get(0), {
		group: 'periodic_mail',
		onAdd: function (evt){
			evt.item.classList.remove('bg-success');
			evt.item.classList.add('bg-danger');
		}
	});

	$('a[data-o]').on('click', function(e){
		e.preventDefault();
		var option = $(this).data('o');
		var $li = $(this).parent().parent().parent();
		$li.find('[data-lbl]').text($(this).text());
		$li.attr('data-option', option);
	});

	$('form[method="post"]').on('submit', function() {
		var blocks = [];
		var selects = {};
		$active.find('[data-block]').each(function(){
			blocks.push($(this).attr('data-block'));
		});
		$active.find('[data-option]').each(function(){
			selects[$(this).attr('data-block')] = $(this).attr('data-option');
		});
		$inactive.find('[data-option]').each(function(){
			selects[$(this).attr('data-block')] = $(this).attr('data-option');
		});
		$block_layout.val(JSON.stringify(blocks));
		$block_select_options.val(JSON.stringify(selects));
	});
});
