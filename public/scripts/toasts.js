let active_toasts = [];
var toasts_displayed = 0;



function show_toast(text, color, time, closable) {
    for(i = 0; i<active_toasts.length; i++) {
        $("#toast-"+active_toasts[active_toasts.length - i - 1]).stop(true, true)
        $("#toast-"+active_toasts[active_toasts.length - i - 1]).parent().css("bottom", 49 +(50*i) + "px")
        $("#toast-"+active_toasts[active_toasts.length - i - 1]).parent().animate({bottom: 22 +(50*(i+1)) + "px"}, 250)
    }

    setTimeout(function() {async_show_toast(text, color, time, closable)}, 0)
}

function async_show_toast(text, color, time, closable) {
    $("#snackbar").append(get_toast(text, color, closable));
    $("#toast-"+toasts_displayed).parent().css("bottom", "10px")
    $("#toast-"+toasts_displayed).parent().animate({bottom: "22px"}, 500)

    active_toasts.push(toasts_displayed);

    remove_toast(toasts_displayed, time, 500);

    if(closable) {
        $("#toast-"+toasts_displayed).hover(
            function() {
                id = $(this).attr("data-id");
                $(this).children(".toast-close").stop(true, false)
                $(this).children(".toast-close").animate(
                    {width: "20px"}, 200
                )
                setTimeout(function() {
                    $("#toast-"+id+" .toast-close span").html("&times;")
                    $("#toast-"+id+" .toast-close span").animate({opacity: "100%"}, 100)
                }, 50)
            },
            function() {
                id = $(this).attr("data-id");
                $(this).children(".toast-close").stop(true, false)
                $(this).children(".toast-close").animate(
                    {width: "4.5px"}, 200
                )
                setTimeout(function() {
                    $("#toast-"+id+" .toast-close span").html("&nbsp;")
                    $("#toast-"+id+" .toast-close span").animate({opacity: "0"}, 100)
                }, 50)
            }
        )
        $("#toast-"+toasts_displayed).click(function() {
            id = $(this).attr("data-id");
            remove_toast(parseInt(id), 0, 250);
            //active_toasts.splice(active_toasts.indexOf(parseInt(id)), 1);
            for(i = 0; i<active_toasts.length; i++) {
                cid = active_toasts[active_toasts.length - i - 1];
                if(cid > id) continue;
                if(cid == id) break;
                $("#toast-"+cid).parent().animate({bottom: ($("#toast-"+cid).parent().css("bottom").split("px")[0] - 50) + "px"}, 250);
            }
        })
    }
    toasts_displayed++;
}

function get_toast(text, color, closable) {
    return '<div class="toast-container">'                                                                              +
        '<div class="toast show" id="toast-'+toasts_displayed+'" data-id="'+toasts_displayed+'">'               +
        '<div class="toast-close'+(closable ? " closable" : "")+'" style="--data-background: '+color+'">'   +
        '<span>&nbsp;</span>'                                                                           +
        '</div>'                                                                                            +
        '<div class="toast-content">'                                                                       +
        '<span>' + text + "</span>"                                                                     +
        '</div>'                                                                                            +
        '</div>'                                                                                                +
        '</div>';
}

function remove_toast(id, time, fadeTime) {
    setTimeout(
        function() {
            active_toasts.splice(active_toasts.indexOf(id), 1);
            $("#toast-"+id).parent().animate(
                {opacity: 0}, fadeTime,
                function() {
                    this.remove()
                }
            )
            for(i = 0; i<active_toasts.length; i++) {
                cid = active_toasts[active_toasts.length - i - 1];
                if(cid > id) continue;
                if(cid == id) break;
                $("#toast-"+cid).parent().animate({bottom: ($("#toast-"+cid).parent().css("bottom").split("px")[0] - 50) + "px"}, 250);
            }
        }, time
    )
}

function show_toast_by_data(data) {
    if(data.hasOwnProperty("error")) {
        show_toast(data["message"], "var(--color-standard-error-text)", 2000, true);
    } else {
        show_toast(data["message"], "var(--color-standard-success-text)", 2000, true);
    }
}

function toast_json_answer(data) {
    data = JSON.parse(data);
    if(data.hasOwnProperty("error"))
        show_toast(data.message, "var(--color-standard-error-text)", 3000, true);
    else
        show_toast(data.message, "var(--color-standard-success-background)", 3000, true);
    return data.hasOwnProperty("error");
}