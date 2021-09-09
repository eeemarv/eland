jQuery(function(){
  var $f_cat = $('[data-f-cat]');
  var $f_submit = $f_cat.closest('form').find('button[type="submit"]');
  $f_cat.on('change', function() {
    $f_submit.trigger('click');
  });
});
