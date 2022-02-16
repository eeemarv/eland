jQuery(function(){
  var $filter_cat = $('[data-filter-cat]');
  var $filter_submit = $filter_cat.closest('form').find('button[type="submit"]');
  $filter_cat.on('change', function() {
    $filter_submit.trigger('click');
  });
});
