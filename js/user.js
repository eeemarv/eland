var contacts_div = $('#contacts');
var uid = contacts_div.attr('data-uid');

$.get('contacts.php?inline=1&uid=' + uid , function(data) {
  contacts_div.html(data);
});
