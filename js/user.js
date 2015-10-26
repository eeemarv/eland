var contacts_div = $('#contacts');
var transactions_div = $('#transactions');
var messages_div = $('#messages');

$.get(contacts_div.data('url'), function(data){
	contacts_div.html(data);
});

$.get(transactions_div.data('url'), function(data){
	transactions_div.html(data);
});

$.get(messages_div.data('url'), function(data){
	messages_div.html(data);
});
