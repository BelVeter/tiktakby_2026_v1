//srch item
let srchBut = document.querySelector('.model_srch_but');
if (srchBut) {
    srchBut.addEventListener('click', srchButCheck);
}

function srchButCheck(e){
    e.preventDefault();
    if ((document.querySelector('#model_srch').value*1<1 && document.querySelector('#inv_srch').value*1<1)){
        alert('Заполните хоя бы одно поле: или id модели, или инвентарный номер.')
    }
    else {
        e.target.closest('form').submit();
    }
}


document.querySelectorAll('.change-btn').forEach((el)=> {
    el.addEventListener('click', (e) => {
        e.target.closest('tr').querySelectorAll('.edit-field').forEach((el2) => {
            el2.classList.add('show-block');
        });
        e.target.closest('tr').querySelectorAll('.hide-when-edit').forEach((el2) => {
            el2.classList.add('hide');
        });
    });
});

document.querySelectorAll('.cancel-btn').forEach((el) => {
    el.addEventListener('click', cancelBtnHide);
});

function cancelBtnHide(e){
    let row = e.target.closest('tr');
    row.querySelectorAll('.edit-field').forEach((el) => {
        el.classList.remove('show-block');
    });
    row.querySelectorAll('.hide-when-edit').forEach((el2) => {
        el2.classList.remove('hide');
    });

}

//delete
document.querySelectorAll('.delete-btn').forEach((el) => {
    el.addEventListener('click', (e) => {
        let row = e.target.closest('tr');
        if (confirm('Вы уверены, что хотите безвозвратно удалить раздел?')) {
            row.querySelector('[name="action"]').value='delete';
            e.target.closest('form').submit();
        }
    });
});

//show-hide new item form
if (d=document.querySelector('.form-new-cancel')) {
    d.addEventListener('click', (el) => {
        window.location='/bb/favorite_tovars_management.php';
    });

}

document.querySelectorAll('[data-controll="noquotes"]').forEach((el) => {
    el.addEventListener('keyup', removeQuotes);
});

function removeQuotes(e){
    console.log('work');
    e.target.value=e.target.value.replace(/["']/gmi, "");
}
