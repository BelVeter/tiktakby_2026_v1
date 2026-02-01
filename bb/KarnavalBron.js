
function kb_cancel() {
    $('.action').hide();

    var $form = $("#active_values");
    //alert ($form);
    var inv_n = $("#active_inv_n").val();
    //alert (inv_n);
    if (inv_n=='') return null;
    $("#kb_button_"+inv_n).show('slow');
    $.ajax(
        {
            type: $form.attr('method'),
            url: "/bb/kb_ajax_eng.php",
            data: $form.serialize(),
        }
    ).done(function (data) {
        //alert (data);
        var rez=JSON.parse(data);

        $("#k_container_"+inv_n).empty();
        $("#k_container_"+inv_n).append(rez.result);

        $("#active_inv_n").val('');
        $("#active_a_id").val('');
        $("#active_tmp_bron_id").val('');

        $('.action').show();

    });
}

function kb_first($form_id, inv_n) {
    kb_cancel();
    var $result_div_id = "k_container_"+inv_n;
    var $form = $("#"+$form_id);
    $("#kb_button_"+inv_n).hide('slow');
    $("#"+$result_div_id).append('<img src="/bb/w2.gif" class="kb_w2"/>');
    $.ajax(
        {
            type: $form.attr('method'),
            url: "/bb/kb_ajax_eng.php",
            data: $form.serialize(),
        }
    ).done(function (data) {
        var rez=JSON.parse(data);
        //alert (rez);
        $("#"+$result_div_id).empty();
        $("#"+$result_div_id).append(rez.result);
        $("#active_inv_n").val(inv_n);
    });

}


function kb_date_sent($form_id, $result_div_id) {
    $('.action').hide();
    //alert("Work");
    var $form = $("#"+$form_id);
    //var $tmp = $form.serialize();
    //alert($tmp);
    $("#"+$result_div_id).append('<img src="/bb/w2.gif" class="kb_w2"/>');

    $.ajax(
        {
            type: $form.attr('method'),
            url: "/bb/kb_ajax_eng.php",
            data: $form.serialize()
        }
    ).done(function (data) {
        //alert(data);
        var rez=JSON.parse(data);
        //alert(rez);

        if (rez.status=='ok') {
            $("#" + $result_div_id).empty();
            $("#" + $result_div_id).append(rez.result);
        }
        else alert("Произошла ошибка. Перезагрузите страницу.");

        $('.action').show();
    });
}

function kb_sent2($form_id, $result_div_id, a_click_id) {
    //alert("Work");
    $('.action').hide('slow');
    ff=document.getElementById($form_id);
    ff.tmp_br_id.value= $("#active_tmp_bron_id").val();

    var $form = $("#"+$form_id);
    $("#"+$result_div_id).append('<img src="/bb/w2.gif" class="kb_w3"/>');
    //var $tmp = $form.serialize();
    //alert ($tmp);

    $.ajax(
        {
            type: $form.attr('method'),
            url: "/bb/kb_ajax_eng.php",
            data: $form.serialize()
        }
    ).done(function (data) {
        //alert (data);
        if ($("#active_a_id").val()!="") {//if set active <a> change old color to -yellow +!!! need to cancel tmp bron
            $("#"+$("#active_a_id").val()).css('background-color', '#00c400');
            //!!! to add: cancelation of tmp bron
        }

        $("#"+a_click_id).css('background-color', '#6f3');

        $("#active_a_id").val(a_click_id);

        //alert(data);
        var res=JSON.parse(data);
        //alert(res);
        if (res.status=='ok') {
            //alert('ok');
            $("img.kb_w3").remove();
            $("#" + $result_div_id).append(res.result);
            $("#active_tmp_bron_id").val(res.params.kb_id);
        }
        else {
            //alert('not_ok');
            $("#" + $result_div_id).empty();
            $("#" + $result_div_id).append(res.result);
        }
        $('.action').show('slow');
    });
}

function getWorkingHours(date_select_id, hour_select_id) {
    //alert(date_select_id + '---'+hour_select_id);
    var dd= $("#"+date_select_id);
    //alert ('дата'+dd);
    var hh=$("#"+hour_select_id);


    $.ajax(
        {
            type: 'POST',
            url: "/bb/kb_ajax_eng.php",
            data: {start_date: dd.val(), action: 'get_hours_options'}
        }
    ).done(function (data) {
        //alert (data);
        var res=JSON.parse(data);
        //alert(res);
        if (res.status=='ok') {
            //alert(res.result);
            hh.empty();
            hh.append(res.result);
        }
        else {
            alert('Ошибка.');
        }
    });
}


function bron_check (inv_n) {
    //alert ('OK');

    var hours_min=document.getElementById('br_min_h_'+inv_n).value;

    var br_from_temp=new Date(document.getElementById('kb_start_date_select_'+inv_n).value);
    var br_from=new Date(br_from_temp.getFullYear(), br_from_temp.getMonth(), br_from_temp.getDate(), document.getElementById('kb_start_hour_select_'+inv_n).value);

    var br_to_temp=new Date(document.getElementById('kb_end_date_select_'+inv_n).value);
    var br_to=new Date(br_to_temp.getFullYear(), br_to_temp.getMonth(), br_to_temp.getDate(), document.getElementById('kb_end_hour_select_'+inv_n).value);

    var max_time=new Date(document.getElementById('max_time_'+inv_n).value);
    var min_time=new Date(document.getElementById('min_time_'+inv_n).value);

    bron_date_from=document.getElementById('kb_start_date_select_'+inv_n).value;
    bron_hour_from=document.getElementById('kb_start_hour_select_'+inv_n).value;
    bron_date_to=document.getElementById('kb_end_date_select_'+inv_n).value;
    bron_hour_to=document.getElementById('kb_end_hour_select_'+inv_n).value;
    bron_fio=document.getElementById('br_fio_'+inv_n).value;
    bron_phone1=document.getElementById('br_phone1_'+inv_n).value;
    bron_phone2=document.getElementById('br_phone2_'+inv_n).value;

    valid=true;

    if (bron_date_from==0) {
        valid=false;
        alert ('Не заполнена дата выдачи');
    }
    if (bron_hour_from==0) {
        valid=false;
        alert ('Не заполнено время выдачи');
    }
    if (bron_date_to==0) {
        valid=false;
        alert ('Не заполнена дата возврата');
    }
    if (bron_hour_to==0) {
        valid=false;
        alert ('Не заполнено время возврата');
    }
    if (valid==true) {
        if (br_from < min_time) {
            valid = false;
            alert('Дата и время выдачи должны быть позднее даты и времени начала свободного периода.');
        }
        if (br_to > max_time) {
            valid = false;
            alert('Дата и время возврата должны быть не позднее даты и времени окончания свободного периода.');
        }
    }

    if (valid==true) {

        $end = br_to.getTime();
        $start = br_from.getTime();

        $dif_h = ($end - $start) / 1000 / 60 / 60;

        if (+br_from >= +br_to) {
            alert('Дата начала брони должна быть ранее даты окончания брони.');
        }
        else {
            if ($dif_h < hours_min) {
                if (!confirm('Вы уверены, что хотите сохранить бронь длительностью менее ' + hours_min + ' часов ?')) {
                    valid = false;
                }
            }
        }
    }

    if (bron_fio=='') {
        valid=false;
        alert ('Не заполнено ФИО');
    }

    if (bron_phone1=='') {
        valid=false;
        alert ('Не заполнен телефон');
    }
    else {
        if (bron_phone1.match(/\d/g).length!=9) {
            valid=false;
            alert ('в поле телефон 1 должно быть 9 цифр.');
        }
    }
    if (bron_phone2!='') {
        if (bron_phone2.match(/\d/g).length > 0 && bron_phone2.match(/\d/g).length != 9) {
            valid = false;
            alert('Поле телефон 2 должно быть либо пустым, либо в нем должно быть 9 цифр.');
        }
    }


    if (valid==true) {
        $('.action').hide('slow');

        var $form = $("#kb_info_form_"+inv_n);

        $.ajax(
            {
                type: 'POST',
                url: "/bb/kb_ajax_eng.php",
                data: $form.serialize()
            }
        ).done(function (data) {
            //alert(data);
            var res=JSON.parse(data);
            //alert(res);
            if (res.status=='ok') {
                //alert('ok');
                $("#k_container_" + inv_n).empty();
                $("#k_container_" + inv_n).append(res.result);
                $("#active_tmp_bron_id").val('');
            }
            else {
                alert('Ошибка.');
            }

            $('.action').show();
        });
    }

}//end of function




function to_delete_k_bron(inv_num, result_div_id, ) {
    $.post("/bb/kb_ajax_eng.php",
        {
            action: "inv_br_start",
            inv_n: inv_num,
        },
        function (data, status) {
            if (status="success") {
                $("#"+result_div_id).append(data);
            }
            else {
                $("#k_result").text("Ошибка. Свяжитесь с администратором.");
            }
        }
    );
}