
document.querySelector('.new-btn').addEventListener('click', (el) => {
    document.querySelector('.new_razdel').classList.add('show');
});

document.querySelector('.form-new-cancel').addEventListener('click', (el) => {
    document.querySelector('.new_razdel').classList.remove('show');
});


//form check
document.querySelectorAll('[data-controll="url"]').forEach((el) => {
    el.addEventListener('keyup', removeNonLatin);
});

function removeNonLatin(e){
    e.target.value=e.target.value.replace(/[^a-z0-9-_]|\s+|\r?\n|\r/gmi, "");
}

document.querySelectorAll('.change-btn').forEach((el) => {
    el.addEventListener('click', changeBtnShow);
});

document.querySelectorAll('.cancel-btn').forEach((el) => {
    el.addEventListener('click', cancelBtnHide);
});


function changeBtnShow(e) {
    let row = e.target.closest('tr');
    row.querySelectorAll('.form-edit').forEach((el) => {
        el.classList.add('show-block');
    });
    e.target.classList.remove('show-block');
}

function cancelBtnHide(e){
    let row = e.target.closest('tr');
    row.querySelectorAll('.form-edit').forEach((el) => {
        el.classList.remove('show-block');
    });
    row.querySelector('.change-btn').classList.add('show-block');
}

document.querySelectorAll('.save-btn').forEach((el) => {
    el.addEventListener('click', saveBtnAction);
})

function saveBtnAction(e){
    let row = e.target.closest('tr');
    let rez = formCheck(row);
    if (rez[0]) {
        e.target.closest('form').submit();
    }
    else {
        alert(rez[1]);
    }
}

function formCheck(row) {
    let rezult = true;
    let message ='';
    if(row.querySelector('[name="name_razdel_text"]').value=='') {
        rezult = false;
        message += 'Заполните наименование, ';
    }
    if(row.querySelector('[name="url_razdel_name"]').value=='') {
        rezult = false;
        message += 'заполните url-ключ ';
    }

    //url-key unique check
    let currentUrl = row.querySelector('[name="url_razdel_name"]');
    let allUrls = document.querySelectorAll('[name="url_razdel_name"]');

    allUrls.forEach((el) => {
        if (el != currentUrl && el.value == currentUrl.value) {
            rezult = false;
            message += 'url-ключ должен быть уникален (обнаружен дубль!) ';
        }
    });

    return [rezult, message];
}

document.querySelectorAll('.delete-btn').forEach((el) => {
    el.addEventListener('click', (e) => {
        let row = e.target.closest('tr');
        if (confirm('Вы уверены, что хотите безвозвратно удалить раздел?')) {
            row.querySelector('[name="action"]').value='delete';
            e.target.closest('form').submit();
        }
    });
});
