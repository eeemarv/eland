$(document).ready(function(){
	var news_div = $('#news');
	var messages_div = $('#messages');

	$.get(news_div.data('url'), function(data){
		news_div.html(data);
	});
	$.get(messages_div.data('url'), function(data){
		messages_div.html(data);
	});
});
