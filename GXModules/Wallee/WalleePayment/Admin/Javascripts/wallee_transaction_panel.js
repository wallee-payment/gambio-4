$(function() {
    var request;
    $(".changeTransactionStatus").on("click", function(event) {
        event.preventDefault();

        if (request) {
            request.abort();
        }

        var loader = $('#loading');
        loader.show();
        var action = $(this).attr('id');
        var $button = $(this);
        $button.prop("disabled", true);

        request = $.ajax({
            url: "/admin/admin.php?do=WalleeOrderAction/ChangeTransactionStatus",
            type: "post",
            data: {
                'orderId': $('#orderId').val(),
                'action': action
            }
        });

        request.done(function (response) {
            if (response) {
                loader.hide();
                $button.prop("disabled", false);
                alert(response);
            } else {
                setTimeout(function() {
                    loader.hide();
                    location.reload();
                }, 7000);
            }

            return false;
        });

        request.fail(function () {
            $button.prop("disabled", false);
        });
    });

    $("#refund").on("click", function(event) {
        event.preventDefault();

        $('#refund-form').toggle();
    });

    $("#make-refund").on("click", function(event) {
        event.preventDefault();

        if (request) {
            request.abort();
        }

        var loader = $('#loading');
        loader.show();
        var $button = $(this);
        $button.prop("disabled", true);

        request = $.ajax({
            url: "/admin/admin.php?do=WalleeOrderAction/Refund",
            type: "post",
            data: {
                'orderId': $('#orderId').val(),
                'amount': $('#refund-amount').val()
            }
        });

        request.done(function (response) {
            if (response) {
                loader.hide();
                $button.prop("disabled", false);
                alert(response);
            } else {
                setTimeout(function() {
                    loader.hide();
                    location.reload();
                }, 7000);
            }

            return false;
        });

        request.fail(function (jqXHR, textStatus, errorThrown){
            $button.prop("disabled", false);
        });
    });
});
