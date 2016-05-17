$(document).ready(function(){
	var messages_div = $('#messages');

	$.get(messages_div.data('url'), function(data){
		messages_div.html(data);
	});
});
