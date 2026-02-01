function op_form_sent($form_id, $target_id) {
    var $form = $("#"+$form_id);
    //$("#kb_button_"+inv_n).hide('slow');
    $form.hide();
    $("#"+$target_id).append('<img src="/bb/w2.gif" class="kb_w2"/>');
    $.ajax(
        {
            type: $form.attr('method'),
            url: "/bb/kassa_operations.php",
            data: $form.serialize(),
        }
    ).done(function (data) {
        var rez=JSON.parse(data);
        //alert (rez);
        $("#"+$target_id).empty();
        $("#"+$target_id).append(rez.result);

        $(document).ready(function() {
            $('.selectpicker').selectpicker();
        });

        //$("#active_inv_n").val(inv_n);
    });
}


function new_rash_check() {
    var rez=true;
    var op_type=$("input[name='operation_type']:checked").val();
    //alert (op_type);
    var text_msg='';
    if ($('#channel').val()=='') {
        text_msg+='Выберите офис/кассу';
        rez=false;
    }

    var dd=new Date($('#operation_date').val());
        dd.setHours(0,0,0,0);
    var today = new Date();
        today.setHours(0,0,0,0);
    if ($('#channel').val()!='bank_' && dd.getTime()!=today.getTime()) {
        text_msg+=', дата не может быть в прошлом или будущем';
        rez=false;
    }
    if ($('#channel').val()=='bank_' && dd.getTime()>today.getTime()) {
        text_msg+=', дата не может быть в будущем';
        rez=false;
    }
    if ($('#kassa_type').val()=='') {
        text_msg+=', выберите кассу';
        rez=false;
    }
    if ($('#op_amount').val()=='' || $('#op_amount').val()==0) {
        text_msg+=', заполните сумму операции';
        rez=false;
    }

    var type2=$('#operation_type2').val();
    if (type2=='' || type2==0) {
        text_msg+=', выберите статью расхода (дохода)';
        rez=false;
    }
    if (type2=='avans' || type2=='zpl') {

        if ($('#pesonal_op_id').val()=='' || $('#pesonal_op_id').val()==0) {
            text_msg+=', выберите имя получателя аванса/зарплаты';
            rez=false;
        }
        if (type2=='zpl' && ($('#zpl_month').val()=='' || $('#zpl_month').val()==0)) {
            text_msg+=', выберите месяц, за который выплачивается зарплата';
            rez=false;
        }
        if (type2=='zpl' && ($('#zpl_year').val()=='' || $('#zpl_year').val()==0)) {
            text_msg+=', выберите год периода, за который выплачивается зарплата';
            rez=false;
        }

        //important!!! this check only after Year and Month is valid
        if (type2=='zpl' && rez==true) {
            var today_ym=new Date();
                today_ym.setHours(0,0,0,0);
                today_ym.setDate(1);

            var zp_ym=new Date();
                zp_ym.setFullYear($('#zpl_year').val(), $('#zpl_month').val()-1, 1);

            var month_diff= (zp_ym.getFullYear()-today_ym.getFullYear())*12 + (zp_ym.getMonth()-today_ym.getMonth());
            if (Math.abs(month_diff)>2) {
                text_msg+=', период, за который выплачивается зарплата должен быть не далее 2-х месяцев от текущей даты';
                rez=false;
            }
        }
    }
    if (op_type=='shift') {
        if ($('#shift_to_channel').val()=='' || $('#shift_to_channel').val()==0) {
            text_msg+=', выберите офис получатель';
            rez=false;
        }
        if ($('#kassa_type_shift').val()=='' || $('#kassa_type_shift').val()==0) {
            text_msg+=', выберите кассу получатель';
            rez=false;
        }
        if ($('#shift_to_channel').val()==$('#channel').val() && $('#kassa_type_shift').val()==$('#kassa_type').val()) {
            text_msg+=', касса отправитель и касса получатель не могут совпадать';
            rez=false;
        }
    }


    if (rez==false) {
        alert(text_msg);
    }

    return rez;
}

function formAppendSend($source_id, $target_id) {
    var $ist = $('#'+$source_id).html();
    var $targ = $('#'+$target_id);
    $targ.append($ist);
      $targ.submit();
}
