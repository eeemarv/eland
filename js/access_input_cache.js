$(document).ready(function(){
	$access_div = $('div[data-access-cache-id]');

	$access_div.each(function(){

		var cache_id = $(this).data('access-cache-id');

		var access_value = $(this).find('input:checked').val();

		if (!access_value){

			var access_value = sessionStorage.getItem(cache_id);

			if (access_value){

				$(this).find('input[value="' + access_value + '"]').prop('checked', true);
			}
		}

		$(this).change(function(){

			sessionStorage.setItem(cache_id, $(this).find(':checked').val());
			
			console.log(cache_id + ': ' + $(this).find(':checked').val());
		});
	});
});
