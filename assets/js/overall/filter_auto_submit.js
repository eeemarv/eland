export default function() {
      var $el = $('[data-auto-submit]');
      $el.change(function() {
        $(this).closest('form[method="get"]').find('input[type="submit"]').click();
    });
};
