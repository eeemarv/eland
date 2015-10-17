var contacts_div = $('#contacts');
var uid = contacts_div.attr('data-uid');
var transactions_div = $('#transactions');
var messages_div = $('#messages');

$.get('contacts.php?inline=1&uid=' + uid, function(data){
	contacts_div.html(data);
});

$.get('transactions.php?inline=1&uid=' + uid, function(data){
	transactions_div.html(data);
});

$.get('messages.php?inline=1&uid=' + uid, function(data){
	messages_div.html(data);
});
