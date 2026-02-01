document.querySelector('.new-btn').addEventListener('click', (el) => {
    document.querySelector('.new-form-row').classList.add('show');
});

document.querySelector('.form-new-cancel').addEventListener('click', (el) => {
    document.querySelector('.new-form-row').classList.remove('show');
});


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

//form check
document.querySelectorAll('[data-controll="url"]').forEach((el) => {
    el.addEventListener('keyup', removeNonLatin);
});

function removeNonLatin(e){
    e.target.value=e.target.value.replace(/[^a-z0-9-_]|\s+|\r?\n|\r/gmi, "");
}

document.querySelectorAll('.save-btn').forEach((el) => {
    el.addEventListener('click', saveBtnAction);
})


document.querySelectorAll('.cat_filter_text').forEach((el)=>{
  el.addEventListener('input', catFilterChange);
});

document.querySelectorAll('.filter_input').forEach((el)=>{
  el.addEventListener('change', checkFilter);
});


function checkFilter(e){
  let checkedFilter = e.target.checked;
  console.log(checkedFilter);
  let checkBXs = e.target.closest('td').querySelectorAll('.form-check-label');
  checkBXs.forEach((el)=>{
    let div = el.closest('.form-check');
    let checkBox = div.querySelector('input');
    if(checkedFilter && !checkBox.checked) {
      div.classList.add('hide');
    }
    else {
      div.classList.remove('hide');
    }
  });

}

function catFilterChange(e){
  let val = e.target.value;
  //let checkedFilter = e.target.closest('.filter_input').value=='1';
  let checkBXs = e.target.closest('td').querySelectorAll('.form-check-label');

  checkBXs.forEach((el)=>{
    if(el.innerHTML.toLowerCase().includes(val)){
      let div = el.closest('.form-check')
      if(div) div.classList.remove('d-none');
    }
    else {
      let div = el.closest('.form-check')
      if(div) div.classList.add('d-none');
    }
  });

  console.log(val, checkBXs);
}

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
    if(row.querySelector('[name="name_sub_razdel_text"]').value=='') {
        rezult = false;
        message += 'Заполните название подраздела, ';
    }
    if(row.querySelector('[name="url_sub_razdel_name"]').value=='') {
        rezult = false;
        message += 'заполните url-ключ, ';
    }
    if(row.querySelector('[name="main_razdel_id"]').value==0) {
        rezult = false;
        message += 'выберите основной раздел (выпадающий список) ';
    }


    //url-key unique check
    let currentUrl = row.querySelector('[name="url_sub_razdel_name"]');
    let allUrls = document.querySelectorAll('[name="url_sub_razdel_name"]');

    allUrls.forEach((el) => {
        if (el != currentUrl && el.value == currentUrl.value) {
            rezult = false;
            message += 'url-ключ должен быть уникален (обнаружен дубль!) ';
        }
    });

    return [rezult, message];
}


document.querySelectorAll('.cancel-btn').forEach((el) => {
    el.addEventListener('click', cancelBtnHide);
});

function cancelBtnHide(e){
    console.log('eee');
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


document.querySelectorAll('.subrazdel-filter').forEach((el) => {
    el.addEventListener('change', filterSelectActions)
});


function filterSelectActions(e){
    this.form.submit();
}

document.querySelectorAll('[name="main_razdel_id"]').forEach((el) => {
    el.addEventListener('change', chngeMainCat);
});

function chngeMainCat(e){
    let row = e.target.closest('tr');

    let select = e.target;
    let chbs = row.querySelectorAll('[name="razdel[]"]');
    chbs.forEach((el) => {
        if (el.value == select.value) {
            el.checked = true;
            el.disabled = true;
        }
        else if (el.disabled==true) {
            el.disabled=false;
            el.checked=false;
        }
    });
    //name="razdel[]"
    //console.log(e.target);
}
