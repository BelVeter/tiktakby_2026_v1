document.addEventListener('submit', formCheck);

const urlCode = document.querySelector('#url_code');
const urlCodeFeedback = document.querySelector('#url_code_feedback');
const noEmptyFields = document.querySelectorAll('[data-noempty]');

function formCheck(e) {
  e.preventDefault();

  let result = true;
  const form = e.target;
  const formD = new FormData(form);
  formD.append('form_check', 'form_check');

  noEmptyFields.forEach(el=>{
    if (el.offsetParent!==null && el.value.trim() === '') {
      //console.log(el);
      result=false;
      el.classList.add('is-invalid');
      el.nextElementSibling.innerHTML='это поле не может быть пустым';
    }
    else {
      el.classList.remove('is-invalid');
      el.nextElementSibling.innerHTML='';
    }
  });

  if (urlCode.value.trim() === ''){
    result = false;
    urlCode.classList.add('is-invalid');
    urlCodeFeedback.innerHTML='поле не может быть пустым';
  }
  else {
    urlCode.classList.remove('is-invalid');
    urlCodeFeedback.innerHTML='';

    fetch(form.action, {
      method: form.method,
      body: formD,
    }).then((responce)=>{
      return responce.json();
    }).then(rez => {
      if (!processFormValidationResults(rez)){
        e.preventDefault();
      }
      else {
        e.target.submit();
      }
    });
  }

}

function processFormValidationResults(rez){
  let readyToSend = true;
  if (rez.hasUrlDublicates) {
    readyToSend=false;
    urlCode.classList.add('is-invalid');
    urlCodeFeedback.innerHTML='Адрес должен быть уникальным. Текущий адрес дублирует модель №'+rez.hasUrlDublicates;
  }
  else {
    urlCode.classList.remove('is-invalid');
    urlCodeFeedback.innerHTML='';
  }

  return readyToSend;
}


//file upload handle

document.querySelectorAll('[type="file"]').forEach((f)=>{
  f.addEventListener('change', fileChosen)
});

function fileChosen(e){
  let parentDiv = e.target.parentElement.parentElement;
  let img = document.createElement('img');
  img.src = URL.createObjectURL(e.target.files[0]);

  let input = parentDiv.querySelector('.new-filename');
  let span = parentDiv.querySelector('.file-name');

  input.value = e.target.files[0].name.split('.')[0];
  let ch = new Event('keyup');
  input.dispatchEvent(ch);

  parentDiv.children[0].innerHTML='';
  parentDiv.children[0].append(img);
  input.classList.remove('d-none');
  span.classList.remove('d-none');
}

//form check
document.querySelectorAll('[data-controll="url"]').forEach((el) => {
  el.addEventListener('keyup', removeNonLatin);
});

function removeNonLatin(e){
  e.target.value=e.target.value.replace(/[^a-z0-9-_]|\s+|\r?\n|\r/gmi, "");
}

const razdelSelect = document.querySelector('#add_razdel');
const subRazdelSelect = document.querySelector('#add_subrazdel');
const catSelect = document.querySelector('#add_category');

const mainForm = document.querySelector('#main-form');

razdelSelect.addEventListener('change', razdelChange);
subRazdelSelect.addEventListener('change', subRazdelChange);

function razdelChange(){
  if (razdelSelect.value*1===0) {
    subRazdelSelect.innerHTML='<option value="0">---</option>';
    catSelect.innerHTML='<option value="0">---</option>';
    return false;
  }
  else {
    subRazdelSelect.innerHTML='<option value="">...</option>';
    catSelect.innerHTML='<option value="0">---</option>';
  }
  let data = new FormData(mainForm);
  data.append('action', 'get_subrazdels_options');

  fetch("/bb/model_web_ajax.php", {
    method: mainForm.method,
    body: data,
  })
    .then((rez) => rez.json())
    .then((rezObj) => {

      if (rezObj.result=='ok') {
        subRazdelSelect.innerHTML =rezObj.options;
      }

    });
}

function subRazdelChange(){
  if (subRazdelSelect.value*1===0) {
    catSelect.innerHTML='<option value="0">---</option>';
    return false;
  }
  else {
    catSelect.innerHTML='<option value="">...</option>';
  }
  let data = new FormData(mainForm);
  data.append('action', 'get_cat_options');

  fetch("/bb/model_web_ajax.php", {
    method: mainForm.method,
    body: data,
  })
    .then((rez) => rez.json())
    .then((rezObj) => {

      if (rezObj.result=='ok') {
        catSelect.innerHTML =rezObj.options;
      }

    });
}

const addCatBtn = document.querySelector('#add-btn');
addCatBtn.addEventListener('click', addAddCategory);

const addCategoriesContainer = document.querySelector('#add-cat-container');

function addAddCategory(){
  if (catSelect.value==0){
    alert('Необходимо выбрать категорию!');
    return false;
  }
  let id = catSelect.value;
  let name = catSelect.options[catSelect.selectedIndex].text;
  let newDiv = document.createElement('div');
    newDiv.classList.add('form-check');
  let newCheckbox = `
      <input class="form-check-input" type="checkbox" name="dop_cat[]" value="${id}" id="cat-${id}" checked>
      <label class="form-check-label" for="cat-${id}">${name}</label>
      <input class="form-control" type="text" name="add_cat_pic_url_${id}" value="" placeholder="url доп. картинки листинг" data-controll="url-file">
  `;
  newDiv.innerHTML=newCheckbox;
  addCategoriesContainer.appendChild(newDiv);

  addCategoriesContainer.querySelectorAll('[data-controll="url-file"]').forEach((el) => {
    el.addEventListener('keyup', removeNonLatin);
    el.addEventListener('change', removeNonLatin);
  });


}

document.querySelectorAll('[data-controll="url-file"]').forEach((el) => {
  el.addEventListener('keyup', removeNonLatin);
  el.addEventListener('change', removeNonLatin);
});

function removeNonLatin(e){
  e.target.value=e.target.value.replace(/[^a-z0-9-_./]|\s+|\r?\n|\r/gmi, "");
}

//clipboard copy
let toCopySpans = document.querySelectorAll('[data-copy="1"]');
if (toCopySpans) {
  toCopySpans.forEach((el)=>{
    el.addEventListener('click', copyToClipBoard);
  });
}

function copyToClipBoard(e){
  console.log(e.target);
  navigator.clipboard.writeText(e.target.innerText);
}
