var contacts_div = $('#contacts');
var uid = contacts_div.attr('data-uid');
var transactions_div = $('#transactions');

$.get('contacts.php?inline=1&uid=' + uid, function(data) {
	contacts_div.html(data);
});

$.get('transactions.php?inline=1&uid=' + uid, function(data) {
	transactions_div.html(data).trigger('footable_redraw');
});
