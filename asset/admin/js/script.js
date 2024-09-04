jQuery(document).ready(function ($) {

    // Ajax Url
    const ajax_url = simple_import_export.ajax;

    // Load field Type
    $("tr[data-export-type], tr[data-import-type]").hide();

    function showTrField(name, type = 'export') {
        let title = 'data-' + type + '-type';
        $("tr[" + title + "]").hide();
        $("tr[" + title + "=" + name + "]").show();
    }

    $(document).on("change", "select[name=type]", function (e) {
        e.preventDefault();
        let actionType = $(this).attr('data-action');
        let val = $(this).val();
        showTrField(val, actionType);
    });

    $("select[name=type]").each(function (index) {
        let actionType = $(this).attr('data-action');
        let val = $(this).val();
        showTrField(val, actionType);
    });

    // Show Loading For Export
    $("#simple-import-export__export_form").submit(function (e) {
        //e.preventDefault();

        let form = $(this);
        let SubmitBtn = form.find(":submit");
        SubmitBtn.prop('disabled', true);
        SubmitBtn.val(simple_import_export.loading);
    });

    // Import Run System Step 1
    $("#simple-import-export__import_form").submit(function (e) {
        e.preventDefault();

        // Get File
        var fileTag = $("input[name=attachment]");
        var fileData = fileTag[0].files[0];
        var filePath = fileTag.val();

        // Alert Loading
        var excel_alert_div = $("#simple-import-alert");
        excel_alert_div.html(simple_import_export.loading).show();
        let form = $(this);
        let SubmitBtn = form.find(":submit");
        SubmitBtn.hide();

        // Create Form Data
        var formData = new FormData(document.getElementById("simple-import-export__import_form"));
        formData.append('attachment', fileData);
        formData.append('action', 'simple_import_export__import');

        // Ajax Request
        $.ajax({
            type: "POST",
            url: ajax_url,
            data: formData,
            processData: false,
            contentType: false,
            success: function (data, textStatus, xhr) {
                excel_alert_div.html(data.data.message).show();
            },
            error: function (xhr, status, error) {
                excel_alert_div.html(simple_import_export.error + ': ' + xhr.responseJSON.data.message).show();
            }
        });
    });

    // Import Run System Step 2
    $(document).on("click", "#import-button-action", function (e) {
        e.preventDefault();

        // Hide Step 1
        $("[data-import-step=1]").hide();
        $("[data-import-step=2]").show();

        // New Ajax Request
        jQuery.ajax({
            url: ajaxurl,
            type: 'get',
            dataType: "json",
            tryCount: 0,
            retryLimit: 5,
            data: {
                'action': 'simple_import_export__import_run',
                'number_all': jQuery("#import_number_all").val(),
                '_': Date.now()
            },
            success: function (data) {
                if (data.process_status === "complete") {

                    // Completed Process
                    $("[data-import-step=2]").hide();
                    $("[data-import-step=3]").show();

                } else {

                    // Get number Process
                    jQuery("span#import_num_page_process").html(data.number_process);

                    // Get process Percentage
                    jQuery("progress#import_html_progress").attr("value", data.percentage);

                    // Again request
                    var _this = this;
                    setTimeout(function () {
                        $.ajax(_this);
                    }, 4000);
                }
            },
            error: function () {

                this.tryCount++;
                if (this.tryCount <= this.retryLimit) {
                    var productAjax = this;
                    setTimeout(function () {
                        $.ajax(productAjax);
                    }, 4000, productAjax);
                    return;
                }

                $("[data-import-step=2]").html("<p>" + simple_import_export.import_error + "</p>");
            }
        });
    });
});
