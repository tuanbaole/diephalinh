jQuery(document).ready(function () {
   jQuery('.set-adds-interval').click(function () {
      var data = {
         action: 'set_adds_interval',
         security: jQuery('#gs_adds_ajax_nonce').val()
      };

      jQuery.post(ajaxurl, data, function (response) {
         if (response.success) {
            jQuery('.gs-adds').slideUp('slow');
         }
      });
   });
   
   jQuery('.close-adds-interval').click(function () {
      var data = {
         action: 'close_adds_interval',
         security: jQuery('#gs_adds_ajax_nonce').val()
      };

      jQuery.post(ajaxurl, data, function (response) {
         if (response.success) {
            jQuery('.gs-adds').slideUp('slow');
         }
      });
   });
   
});