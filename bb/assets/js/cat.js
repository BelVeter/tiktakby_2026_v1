
// document.querySelectorAll('[data-name]').forEach((el) => {
//     el.addEventListener('keyup', onlyLetterNumber);
// });

function onlyLetterNumber(e) {
    e.target.value=e.target.value.replace(/[^a-z0-9-_\+\-.,\\\\|%/()\wа-я\s]|\r?\n|\r/gmi, "");
}

document.querySelector('.new-btn').addEventListener('click', (el) => {
    document.querySelector('.new-form-row').classList.add('show');
});
document.querySelector('.form-new-cancel').addEventListener('click', (el) => {
    document.querySelector('.new-form-row').classList.remove('show');
});


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
    if(row.querySelector('[name="name"]').value=='') {
        rezult = false;
        message += 'Заполните наименование, ';
    }
    if(row.querySelector('[name="dog_name"]').value=='') {
        rezult = false;
        message += 'заполните наименование для договора, ';
    }
    if(row.querySelector('[name="main_razdel_id"]').value==0) {
        rezult = false;
        message += 'выберите основной подраздел';
    }


    //url-key unique check
    let currentName = row.querySelector('[name="name"]');
    let currentUrlName = row.querySelector('[name="cat_url_key"]');

    let allNames = document.querySelectorAll('[name="name"]');
    let allUrlNames = document.querySelectorAll('[name="cat_url_key"]');

    allNames.forEach((el) => {
        if (el != currentName && el.value == currentName.value) {
            rezult = false;
            message += ', наименование категории должно быть уникально (обнаружен дубль!) ';
        }
    });

    allUrlNames.forEach((el) => {
        if (el != currentUrlName && el.value == currentUrlName.value) {
            rezult = false;
            message += ', URL-ключ категории должен быть уникальным (обнаружен дубль!) ';
        }
    });


    return [rezult, message];
}


document.querySelectorAll('.change-btn').forEach((el) => {
    el.addEventListener('click', changeBtnShow);
});

function changeBtnShow(e) {
    let row = e.target.closest('tr');
    row.querySelectorAll('.form-edit').forEach((el) => {
        el.classList.add('show-block');
    });
    e.target.classList.remove('show-block');
}


document.querySelectorAll('.cancel-btn').forEach((el) => {
    el.addEventListener('click', cancelBtnHide);
});

function cancelBtnHide(e){
    let row = e.target.closest('tr');
    row.querySelectorAll('.form-edit').forEach((el) => {
        el.classList.remove('show-block');
    });
    row.querySelector('.change-btn').classList.add('show-block');
}


document.querySelectorAll('.delete-btn').forEach((el) => {
    el.addEventListener('click', (e) => {
        let row = e.target.closest('tr');
        if (confirm('Вы уверены, что хотите удалить категорию? Внимание, удалятся и все модели в этой категории!')) {
            row.querySelector('[name="action"]').value='delete';
            e.target.closest('form').submit();
        }
    });
});


function translit(str) {
    t = new Map();
    t.set('а','a');
    t.set('б','b');
    t.set('в','v');
    t.set('г','g');
    t.set('д','d');
    t.set('е','e');
    t.set('ё','e');
    t.set('ж','zh');
    t.set('з','z');
    t.set('и','i');
    t.set('й','j');
    t.set('к','k');
    t.set('л','l');
    t.set('м','m');
    t.set('н','n');
    t.set('о','o');
    t.set('п','p');
    t.set('р','r');
    t.set('с','s');
    t.set('т','t');
    t.set('у','u');
    t.set('ф','f');
    t.set('х','h');
    t.set('ц','ts');
    t.set('ч','ch');
    t.set('ш','sh');
    t.set('щ','sch');
    t.set('ъ','');
    t.set('ы','y');
    t.set('ь','');
    t.set('э','e');
    t.set('ю','u');
    t.set('я','ya');
    t.set(' ','_');
    t.set('-','-');
    t.set('_','_');

}




function formatDuration (seconds) {
    // Complete this function
    if (seconds==0) return 'now'
    let rez = [];
    let rezStr='';
    let secondsAr = [seconds];

    let second = 1;
    let minute = 60;
    let hour = minute*60;
    let day = hour * 24;
    let month = day * 30;
    let year = day * 365;

    formItem('year', year, secondsAr, rez);
    formItem('month', month, secondsAr, rez);
    formItem('day', day, secondsAr, rez);
    formItem('hour', hour, secondsAr, rez);
    formItem('minute', minute, secondsAr, rez);
    formItem('second', second, secondsAr, rez);

    console.log(rez);

    let i = 0;
    let count = rez.length;

    while (r = rez.pop()) {
        if(i==0 && count==1) rezStr = r;
        else if (i==0 && count > 1) rezStr = 'and '+r;
        else if (i==i) rezStr = r+' '+rezStr;
        else rezStr = r+', '+rezStr;
        i++;
    }
    return rezStr;
}

function formItem(str, secs, secondsAr, rez){
    //console.log(str, secs, secondsAr[0]);
    if (secs<=secondsAr[0]) {
        let num = Math.floor(secondsAr[0]/secs);
        secondsAr[0] = secondsAr[0] % secs;
        if (num > 1) rez.push(num+' '+str+'s');
        else rez.push(num+' '+str);
    }
}

//url controll
document.querySelectorAll('[data-controll="url"]').forEach((el) => {
    el.addEventListener('keyup', removeNonLatin);
});

function removeNonLatin(e){
    e.target.value=e.target.value.replace(/[^a-z0-9-_]|\s+|\r?\n|\r/gmi, "");
}


// const foo=bar();
// const number = 2;
// function bar() { return number;}
