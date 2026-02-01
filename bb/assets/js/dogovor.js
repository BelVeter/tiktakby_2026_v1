console.log('ok');

let clientSrchForm = document.querySelector('#clientSrchForm');

let fioInputSrch = document.querySelector('#srch-fio');
let phoneInputSrch = document.querySelector('#srch-phone');
let strInputSrch = document.querySelector('#srch-str');
let domInputSrch = document.querySelector('#srch-dom');

let clientSrchFioNumResult = document.querySelector('#clientSrchFioNumResult');
let clientSrchPhoneNumResult = document.querySelector('#clientSrchPhoneNumResult');
let clientSrchAddrNumResult = document.querySelector('#clientSrchAddrNumResult');
let clientSrchAllNumResult = document.querySelector('#clientSrchAllNumResult');

let clientSrchFioResultDiv = document.querySelector('#client-srch-fio-result-div');
let clientSrchPhoneResultDiv = document.querySelector('#client-srch-phone-result-div');
let clientSrchAddrResultDiv = document.querySelector('#client-srch-addr-result-div');
let clientSrchAllResultDiv = document.querySelector('#client-srch-all-result-div');

const clientRowTemplate = document.querySelector('.client-row-template');

fioInputSrch.addEventListener('change', fioSrchChange);
fioInputSrch.addEventListener('change', clientAllSrchChange);

phoneInputSrch.addEventListener('change', phoneSrchChange);
phoneInputSrch.addEventListener('change', clientAllSrchChange);

strInputSrch.addEventListener('change', addrSrchChange);
strInputSrch.addEventListener('change', clientAllSrchChange);

domInputSrch.addEventListener('change', addrSrchChange);
domInputSrch.addEventListener('change', clientAllSrchChange);

const clientsTBody = document.querySelector('.clients-tbody');

function fioSrchChange(){
  clientSrchFioResultDiv.classList.remove('d-none');
  let data = new FormData(clientSrchForm);
  data.append('action', 'client-fio-srch-num');

  fetch("/bb/dog3_ajax.php", {
    method: clientSrchForm.method,
    body: data,
  })
    .then((rez) => rez.json())
    .then((rezObj) => {

      if (rezObj.result=='ok') {
        clientSrchFioNumResult.innerText =rezObj.clientsNum;
      }

    });
}

function phoneSrchChange(){
  let phoneNum = (""+phoneInputSrch.value).replace(/\D/g, "");
  console.log(phoneNum);
  if (phoneNum*1 < 100000){
    return false;
  }
  clientSrchPhoneResultDiv.classList.remove('d-none');
  let data = new FormData(clientSrchForm);
  data.append('action', 'client-phone-srch-num');

  fetch("/bb/dog3_ajax.php", {
    method: clientSrchForm.method,
    body: data,
  })
    .then((rez) => rez.json())
    .then((rezObj) => {

      if (rezObj.result=='ok') {
        clientSrchPhoneNumResult.innerText =rezObj.Num;
      }

    });
}

function addrSrchChange(){
  console.log('addr');
  clientSrchAddrResultDiv.classList.remove('d-none');
  let data = new FormData(clientSrchForm);
  data.append('action', 'client-addr-srch-num');

  fetch("/bb/dog3_ajax.php", {
    method: clientSrchForm.method,
    body: data,
  })
    .then((rez) => rez.json())
    .then((rezObj) => {

      if (rezObj.result=='ok') {
        clientSrchAddrNumResult.innerText =rezObj.Num;
      }

    });
}

function clientAllSrchChange(){
  clientSrchAllResultDiv.classList.remove('d-none');
  let data = new FormData(clientSrchForm);
  data.append('action', 'client-all-srch-num');

  fetch("/bb/dog3_ajax.php", {
    method: clientSrchForm.method,
    body: data,
  })
    .then((rez) => rez.json())
    .then((rezObj) => {

      if (rezObj.result=='ok') {
        clientSrchAllNumResult.innerText = rezObj.Num;
        if (rezObj.Num<=20) clientAllSrchFillIn();
        else clientsTBody.innerHTML='';
      }

    });
}

function createClientsTableRow($cl){
  let newClientRow = clientRowTemplate.cloneNode(true);

  newClientRow.querySelector('[data-clrow="fio"]').innerHTML=$cl.family + ' ' + $cl.name + ' ' + $cl.otch;
  newClientRow.querySelector('[data-clrow="addr"]').innerHTML='г. '+$cl.city + ', ' + $cl.str + ' ' + $cl.dom+'-'+$cl.kv;
  newClientRow.querySelector('[data-clrow="reg_addr"]').innerHTML='г. '+$cl.reg_city + ', ' + $cl.reg_str + ' ' + $cl.reg_dom+'-'+$cl.reg_kv;
  newClientRow.querySelector('[data-clrow="phone1"]').innerHTML=formatPhone($cl.phone_1);
  newClientRow.querySelector('[data-clrow="phone2"]').innerHTML=formatPhone($cl.phone_2);

  return newClientRow;
}

function fillClientsSearchResults(clArray){
  //console.log(clArray);
  clientsTBody.innerHTML='';
  clArray.forEach((cl)=>{
    clientsTBody.appendChild(createClientsTableRow(cl));
  });
}

function clientAllSrchFillIn(){
  console.log('startF1');
  let data = new FormData(clientSrchForm);
  data.append('action', 'client-all-srch-clients');

  fetch("/bb/dog3_ajax.php", {
    method: clientSrchForm.method,
    body: data,
  })
    .then((rez) => rez.json())
    .then((rezObj) => {

      if (rezObj.result=='ok') {
        fillClientsSearchResults(rezObj.clients);
      }

    });
}

document.querySelector('.test-btn').addEventListener('click', clientAllSrchFillIn);

function formatPhone(str){
  if (str.length*1<=7) return str.slice(-7, str.length-4)+'-'+str.slice(-4, str.length-2)+'-'+str.slice(-2);
  else if (str.length*1<=9) return '('+str.slice(-9, str.length-7)+') '+str.slice(-7, str.length-4)+'-'+str.slice(-4, str.length-2)+'-'+str.slice(-2);
  else if (str.length*1>9) return str.slice(0, str.length-9)+'('+str.slice(-9, str.length-7)+') '+str.slice(-7, str.length-4)+'-'+str.slice(-4, str.length-2)+'-'+str.slice(-2);
}

document.querySelector('#addr-copy').addEventListener('click', ()=>{
  document.querySelector('#srch-reg_str').value = document.querySelector('#srch-str').value;
  document.querySelector('#srch-reg_dom').value = document.querySelector('#srch-dom').value;
  document.querySelector('#srch-reg_kv').value = document.querySelector('#srch-kv').value;
});


//btn new client
const newClientFileds = document.querySelectorAll('.client_new-input');
let newClentBtn = document.querySelector('.client_new-btn');
newClentBtn.addEventListener('click', newBtnToggle);

function newBtnToggle(){
  if (newClientFileds[0].classList.contains('hide')) {
    showNewClientFields();
    newClentBtn.innerText = '-';
  }
  else {
    hideNewClientFields();
    newClentBtn.innerText = '+';
  }
}

function showNewClientFields(){
  newClientFileds.forEach((el)=>{
    el.classList.remove('hide');
    setTimeout(()=>{
      el.classList.add('show');
    }, 50);
  });
}

function hideNewClientFields(){
  newClientFileds.forEach((el)=>{
    el.classList.remove('show');
    setTimeout(()=>{
      el.classList.add('hide');
    },700);
  });
}

