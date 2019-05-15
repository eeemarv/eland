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

		var p_string = '';

		$('ul#list_active > li[data-block]').each(function(){
			var ul_el = $(this);
			p_string += ul_el.attr('data-block') + '.';
			p_string += ul_el.attr('data-option') + ',';
		});

		p_string = p_string.slice(0, -1);
		p_string = '+' + p_string;

		$('#periodic_mail_block_ary').val(p_string);
	});
});
