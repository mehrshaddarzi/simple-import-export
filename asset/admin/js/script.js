jQuery(document).ready(function ($) {
    // Ajax Url
    const ajax_url = simple_import_export.ajax;

    // Setup Persian Datepicker
    $("input[data-picker-now]").persianDatepicker({
        theme: 'latoja',
        cellWidth: 35,
        cellHeight: 30,
        fontSize: 17,
        formatDate: "YYYY/0M/0D",
        selectedBefore: !0
    });

    // Example Ajax
    /*$(document).on('click', '#button', function (e) {
          e.preventDefault();

          $.ajax({
              url: ajax_url,
              type: 'GET',
              dataType: "json",
              contentType: "application/json; charset=utf-8",
              cache: false,
              data: {
               'action': ''
              },
              success: function (data, textStatus, xhr) {
                  $("#tag-id").html(data.html);
              },
              error: function (xhr, status, error) {
                  $("#tag-id").html(xhr.responseJSON.html);
              }
          });
      });*/

});
