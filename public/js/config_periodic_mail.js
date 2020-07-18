$(document).ready(function(){

	var list_active = document.getElementById('list_active');
	var list_inactive = document.getElementById('list_inactive');

	Sortable.create(list_active, {
		group: "periodic_mail",
		onAdd: function (evt){
			evt.item.classList.remove('bg-danger');
			evt.item.classList.add('bg-success');
		}
	});

	Sortable.create(list_inactive, {
		group: "periodic_mail",
		onAdd: function (evt){
			evt.item.classList.remove('bg-success');
			evt.item.classList.add('bg-danger');
		}
	});

	$('a[data-o]').click(function(e){
		e.preventDefault();
		var option = $(this).data('o');
		console.log(option);
		var li_el = $(this).parent().parent().parent();
		li_el.find('span.lbl').text($(this).text());
		li_el.data('option', option);
		li_el.attr('data-option', option);
	});

	$('form[method="post"]').submit(function(event) {
		var blocks = [];
		$('ul#list_active > li[data-block]').each(function(){
			var ul_el = $(this);
			var p = ul_el.attr('data-block') + '.';
			p  += ul_el.attr('data-option');
			blocks.push(p);
		});
		$('#periodic_mail_block_ary').val(blocks.join(','));
	});
});
