const date1Input = document.querySelector('.l3_date_from');
const date2Input = document.querySelector('.l3_date_to');
const daysInput = document.querySelector('.l3_days_input');
const perDaySpan = document.querySelector('.per_day_span');
const totalSpan = document.querySelector('.total_span');
const buttonPlus = document.querySelector('.l3_button_plus');
const buttonMinus = document.querySelector('.l3_button_minus');
const one_day = 1000 * 60 * 60 * 24;

//for modal
const buttonPlusModal = document.querySelector('.l3_button_plus2');
const buttonMinusModal = document.querySelector('.l3_button_minus2')
const date1InputModal = document.querySelector('.l3_date_from2');
const date2InputModal = document.querySelector('.l3_date_to2');
const daysInputModal = document.querySelector('.l3_days_input2');
const perDaySpanModal = document.querySelector('.per_day_span2');
const totalSpanModal = document.querySelector('.total_span2');
//end for modal

const tarifs = [];
document.querySelectorAll('.tarif').forEach((el)=>{
    tarifs.push({
      days: el.dataset.days*1,
      perDay: (Math.round((el.value / el.dataset.days)*100) / 100),
      total: (Math.round((el.value / el.dataset.days)*100) / 100) * el.dataset.days*1,
    });
});
tarifs.sort((a,b)=>{
    return a.days - b.days;
});

let tarifsCopy = tarifs.map((x) => x);
tarifsCopy.sort((a,b)=>{
  return -a.days + b.days;
});

//console.log(tarifs, tarifsCopy);

if(date1Input) {//to avoid mistakes

  date1Input.addEventListener('change', dateChange);
  date2Input.addEventListener('change', dateChange);
  daysInput.addEventListener('change', daysChange);

  buttonPlus.addEventListener('click', (e)=>{
    daysInput.value = +daysInput.value + 1;
    daysChange();
  });
    buttonPlusModal.addEventListener('click', (e)=>{
      daysInput.value = +daysInput.value + 1;
      daysChange();
    });

  buttonMinus.addEventListener('click', (e)=>{
    if (daysInput.value*1 < 1) {
      daysInput.value = 1;
      return;
    };
    daysInput.value = +daysInput.value - 1;
    daysChange();
  });

    buttonMinusModal.addEventListener('click', (e)=>{
      if (daysInput.value*1 < 1) {
        daysInput.value = 1;
        return;
      };
      daysInput.value = +daysInput.value - 1;
      daysChange();
    });

  daysChange();

}


function dateChange(){
    daysInput.value=getDayDiffDates();
    makeCalculation();
}

function daysChange(){
  if (daysInput.value*1 < 1) daysInput.value=1;

    let date1 = new Date(date1Input.value);
    let date2 = new Date(date1Input.value);
        date2.setDate(date2.getDate()+daysInput.value*1);
    let dateString = date2.getFullYear()+'-'+("0"+(date2.getMonth()+1)).slice(-2)+'-'+("0"+date2.getDate()).slice(-2);
    date2Input.value = dateString;
    makeCalculation();
}


function getDayDiffDates(){
    let date1 = new Date(date1Input.value);
    let date2 = new Date(date2Input.value);
    let days = (date2.getTime() - date1.getTime()) / one_day;
    if (days>1) return days;
    else  return 1;
}

function getDayTarifForDaysPeriod(days){
    if (days<1) return 0;

    let tarif=tarifs[0];

    tarifs.forEach((el)=>{
       if (days>=el.days) tarif = el;
    });

    return tarif.perDay;
}

function getTarifForDaysPeriod(days){
  if (days<1) return 0;

  let tarif=tarifs[0];

  tarifs.forEach((el)=>{
    if (days>=el.days) {
      tarif = el;
    }
  });

  return tarif;
}

function getCeilingAmountForTarif(tar){
  let tarif = false;
  tarifsCopy.forEach((el)=>{
    if (el.days>tar.days) tarif = el;
  });

  if (tarif) {
    return tarif.total*1;
  }
  else return false;

}

function getRentToPay(days){
  let theTarif = getTarifForDaysPeriod(days);
  let dayTarif = getDayTarifForDaysPeriod(days);
  let amount = Math.round(days * dayTarif*100)/100;
  let ceilingAmount = getCeilingAmountForTarif(theTarif);
  //console.log(amount, ceilingAmount);
  if (ceilingAmount && amount>ceilingAmount) amount = ceilingAmount;
  //console.log(amount, ceilingAmount);
  return amount;
}

function makeCalculation(){
    let days = getDayDiffDates();
    //let perDayPay = getDayTarifForDaysPeriod(days);
    let totalPay = getRentToPay(days);
    let perDayPay = totalPay/days;
    perDaySpan.innerText = perDayPay.toFixed(2);
    totalSpan.innerText = totalPay.toFixed(2);

  copytarifToModal();
}

//modal order tarifs duplication

function copytarifToModal(){
  //console.log(date1InputModal);
  date1InputModal.value = date1Input.value;
  date2InputModal.value = date2Input.value;
  daysInputModal.value=daysInput.value;
  perDaySpanModal.innerText = perDaySpan.innerText;
  totalSpanModal.innerText = totalSpan.innerText;
}



//l3 slider
const smallPicsLinks = document.querySelectorAll('.l3__slider__small_pic_a');
const sliderContainer = document.querySelector('.l3__slider__big_pic_container');
const bigPics = document.querySelectorAll('.l3__slider__big_pic');

document.querySelectorAll('.l3MainSliderBtn').forEach((el)=>{
    el.addEventListener('click', l3MainSliderBtnClick);
});

function l3MainSliderBtnClick(e){
    let gap = 0;
    let itemWidth = bigPics[0].clientWidth;
    let totalItemsNum = bigPics.length;
    let itemsHiddenLeftNum = Math.round((sliderContainer.scrollLeft+gap)/(itemWidth+gap));
    let showCapacityNum = Math.round((sliderContainer.clientWidth+gap) / (itemWidth+gap));

    let num = 0;

    if (e.currentTarget.classList.contains('btn-left')) {
        num = itemsHiddenLeftNum - showCapacityNum;
        if (num < 0) num = 0;
    }
    else {//btn-right
        num = itemsHiddenLeftNum + showCapacityNum*1;//one, because index is less for 1
        if ((num > totalItemsNum-1)) num = totalItemsNum-1;
    }
    //console.log(itemsHiddenLeftNum, showCapacityNum, num);
    bigPics[num].scrollIntoView({ behavior: 'smooth', block: 'nearest'})
}

onScrollStop(sliderContainer, mainSliderScrollFinished);

function mainSliderScrollFinished(){
    if((sliderContainer.scrollLeft+sliderContainer.clientWidth)>=sliderContainer.scrollWidth) {
        document.querySelector('.l3MainSliderBtn.btn-right').classList.add('hide');
    }
    else {
        document.querySelector('.l3MainSliderBtn.btn-right').classList.remove('hide');
    }

    if(sliderContainer.scrollLeft==0){
        document.querySelector('.l3MainSliderBtn.btn-left').classList.add('hide');
    }
    else {
        document.querySelector('.l3MainSliderBtn.btn-left').classList.remove('hide');
    }
}

smallPicsLinks.forEach((el)=>{
    el.addEventListener('click', slideLinkClick);
});

function slideLinkClick(e) {
    e.preventDefault();

    smallPicsLinks.forEach((el) => el.classList.remove('active'));

    e.currentTarget.classList.add('active');

    let num = e.currentTarget.dataset.slide_num;
    //console.log(num)
    if(bigPics[num]) bigPics[num].scrollIntoView({ behavior: 'smooth', block: 'nearest'});
    else bigPics[0].scrollIntoView({ behavior: 'smooth', block: 'nearest'});
}


onScrollStop(document.querySelector('.l3__slider__big_pic_container'), sliderStopAction);

function onScrollStop(target, callback){
    let isScrolling;
    target.addEventListener('scroll', (e)=>{
        clearTimeout(isScrolling);
        isScrolling = setTimeout(()=>{
            callback();
        },200);
    });
}

function sliderStopAction(){
    let itemWidth = bigPics[0].clientWidth;
    let scrolledLeft = sliderContainer.scrollLeft;
    let itemsHidden = Math.round(scrolledLeft/itemWidth);
    makeSmallPicActiveByIndex(itemsHidden); // == show next index, as indexes are less by one
}

function makeSmallPicActiveByIndex(index){
    if (index<0) return false;
    if (index>smallPicsLinks.length-1) index=smallPicsLinks.length-1;

    smallPicsLinks.forEach((el) => el.classList.remove('active'));
    smallPicsLinks[index].classList.add('active');
}



// favorite tovars script
const l3FavContainer = document.querySelector('.l3_favorite_tovar_container');
const l3FavSliderItems = l3FavContainer.querySelectorAll('.small-card-container');
const l3LeftButton = document.querySelector('.btn-controll.btn-left');
const l3RightButton = document.querySelector('.btn-controll.btn-right');

document.querySelectorAll('.l3-fav-slider-container .btn-controll').forEach((el)=>{
    el.addEventListener('click', l3FavSliderClick);
});

function l3FavSliderClick(e){
    let gap = 20;
    let itemWidth = l3FavSliderItems[0].clientWidth;
    let totalItemsNum = l3FavSliderItems.length;
    let itemsHiddenLeftNum = Math.round((l3FavContainer.scrollLeft+gap)/(itemWidth+gap));
    let showCapacityNum = Math.round((l3FavContainer.clientWidth+gap) / (itemWidth+gap));

    let num = 0;

    if (e.currentTarget.classList.contains('btn-left')) {
        num = itemsHiddenLeftNum - showCapacityNum;
        if (num < 0) num = 0;
    }
    else {
        num = itemsHiddenLeftNum + showCapacityNum*2;
        if ((num > totalItemsNum-1)) num = totalItemsNum-1;
    }

    l3FavSliderItems[num].scrollIntoView({ behavior: 'smooth', block: 'nearest'})
}


onScrollStop(l3FavContainer, favSliderScrollFinished);


function favSliderScrollFinished(){
    if(isScrollStart(l3FavContainer)) l3LeftButton.classList.add('hide');
    else l3LeftButton.classList.remove('hide');

    if (isScrollEnd(l3FavContainer)) l3RightButton.classList.add('hide');
    else l3RightButton.classList.remove('hide');
}

function isScrollEnd(scrollContainer){
    if((scrollContainer.scrollLeft+scrollContainer.clientWidth)>=scrollContainer.scrollWidth) return true;
    return false;
}

function isScrollStart(scrollContainer){
    if(scrollContainer.scrollLeft==0) return true;
    else return false;
}



//show more btn
document.querySelector('.show-more-btn').addEventListener('click', (e)=>{
    e.currentTarget.classList.toggle('show');
    e.currentTarget.parentElement.classList.toggle('show');
});



//l3 karnaval select
const karnSelectContainer = document.querySelector('.l3_karnaval-size-container');

const karnSelectArrow = karnSelectContainer ? karnSelectContainer.querySelector('.select-arrow') : false;
const karnSelectFirstItem = document.querySelector('.l3_karnaval-first-item');
const karnSelectItems = karnSelectContainer ? karnSelectContainer.querySelectorAll('.karnaval-size-item') : false;

if (karnSelectFirstItem){
  karnSelectFirstItem.addEventListener('click', l3SelectFirstDivClick);
  karnSelectArrow.addEventListener('click', l3SelectFirstDivClick);
}

karnSelectItems && karnSelectItems.forEach((el)=>{
  el.addEventListener('click', l3SelectItemClick)
});


function l3SelectFirstDivClick(e) {
  karnSelectContainer.classList.toggle('open')
}

function l3SelectItemClick(e){
  karnSelectContainer.classList.remove('open');
  karnSelectItems.forEach((el)=>{
    el.classList.remove('selected');
  });
  e.currentTarget.classList.add('selected');


  let from = e.currentTarget.dataset.from;
  let to = e.currentTarget.dataset.to;
  let size = e.currentTarget.dataset.size;
    karnSelectFirstItem.dataset.from = from;
    karnSelectFirstItem.dataset.to = to;
    karnSelectFirstItem.dataset.size = size;

  karnSelectContainer.querySelector('.l3_karnaval-first-item span').innerHTML = from+'/'+to;

}


//bron form
const form = document.querySelector('#orderModal');
const officeContainer = document.querySelector('.content-sam');

const bronRadioBtns = document.querySelectorAll('.radio-row label');
const radioDeliv = document.querySelector('.radio-row-content .content-deliv');
const radioSamVivoz = document.querySelector('.radio-row-content .content-sam');
const bronButton = document.querySelector('[data-actionbtn="order"]');
const brontSubmitBtn = document.querySelector('#bron-submit-btn');

bronButton && bronButton.addEventListener('click', bronStart);

bronRadioBtns.forEach((el)=>{
  el.addEventListener('change', radioChange);
});


function bronStart(e){
  //console.log('br-start');
  let data = new FormData(form);
  let forFreeDivs = form.querySelectorAll('[data-show="whenfree"]');

  data.append('action', 'get-offices-for-model');
  let textarea = document.querySelector('#info');

  fetch("/zvonok/bron", {
    method: form.method,
    body: data,
  })
    .then((rez) => rez.json())
    .then((rezObj) => {

      if (rezObj.hasFree) {
        forFreeDivs.forEach((el)=>{
          el.classList.add('show');
        });
        let officesHtml = '';
        rezObj.offices.forEach((of)=>{
          officesHtml += createOfficeBronElement(of.offNum, of.address, of.todayFrom, of.todayTo, of.tomorrowFrom, of.tomorrowTo, of.hasItem);
        });
        officeContainer.innerHTML=officesHtml;
      }
      else {
        forFreeDivs.forEach((el)=>{
          el.classList.remove('show');
        });
      }

      //console.log(rezObj);
    });
}

function createOfficeBronElement(officeId, address, todayFrom, todayTo, tomorrowFrom, tomorrowTo, hasFree){
  let of;
  if (hasFree) {
    of = `
    <label class="label1">
      <span><input class="form-check-input" type="radio" name="office" value="${officeId}"><span>${address}</span></span>
      <span>Товар доступен к выдаче </span>
      <span>Бронь хранится 2-е суток</span>
      <span>Пункт выдачи работает сегодня с <span>${todayFrom}</span> до <span>${todayTo}</span> <br>завтра с <span>${tomorrowFrom}</span> до <span>${tomorrowTo}</span></span>
      <span>Не забудьте паспорт</span>
    </label>`;
  }
  else {
    of = `
    <label class="label1">
      <span><input class="form-check-input" type="radio" name="office" value="${officeId}"><span>${address}</span></span>
      <span>Товар будет доставлен в пункт выдачи в течение суток </span>
      <span>Вам придет sms с уведомлением, как только товар будет доступен к выдаче по указанному адресу</span>
    </label>`;
  }

  return of;
}

let zayavkaBtn = document.querySelector('#zayavka-submit-btn');
if (zayavkaBtn) zayavkaBtn.addEventListener('click', zayavkaSent);

function zayavkaSent(){

  console.log('zayavka_sent');
  let rez = true;
  let messages=[];

  let phone = form.querySelector('[name="phone"]');
    let phoneNumbers = phone.value.replace(/\D/g, "");

  if (phoneNumbers.length<7) {
    rez = false;
    phone.classList.add('is-invalid');
    phone.classList.remove('is-valid');
  }
  else {
    phone.classList.add('is-valid');
    phone.classList.remove('is-invalid');
  }

  if (rez) form.submit();

}

brontSubmitBtn && brontSubmitBtn.addEventListener('click', bronFormValidate);

function bronFormValidate(){
  let bronName = form.querySelector('[name="fio"]');
  let phone = form.querySelector('[name="phone"]');
    let phoneNumbers = phone.value.replace(/\D/g, "");
  let delivRadios = form.querySelectorAll('[name="delivery"]');
  let delivRadioText = form.querySelector('.deliv-radio-text>span');
  let deliveryAddress = form.querySelector('[name="address"]');
  let radioRowContent = form.querySelector('.radio-row-content');

  let rez = true;
  let messages=[];

  if (bronName.value.length < 3){
    rez = false;
    bronName.classList.add('is-invalid');
    bronName.classList.remove('is-valid');
  }
  else {
    bronName.classList.add('is-valid');
    bronName.classList.remove('is-invalid');
  }

  if (phoneNumbers.length<7) {
    rez = false;
    phone.classList.add('is-invalid');
    phone.classList.remove('is-valid');
  }
  else {
    phone.classList.add('is-valid');
    phone.classList.remove('is-invalid');
  }

  let checkRadio = true;
  if (!form.querySelector('.radio-row').classList.contains('show')) checkRadio = false;

  let deliveryChecked = false;
  delivRadios.forEach((el)=> {
    if (el.checked) deliveryChecked = true;
  });
  if (!deliveryChecked && checkRadio) {
    rez = false;
    delivRadioText.classList.add('is-invalid');
    delivRadioText.classList.remove('is-valid');
  }
  else {
    delivRadioText.classList.add('is-valid');
    delivRadioText.classList.remove('is-invalid');
  }

  // delivery checked
  //console.log(delivRadios[0].checked, deliveryAddress.value.length<5, checkRadio);
  if (delivRadios[0].checked && deliveryAddress.value.length<5 && checkRadio){
    rez = false;
    deliveryAddress.classList.add('is-invalid');
    deliveryAddress.classList.remove('is-valid');
  }
  else {
    deliveryAddress.classList.add('is-valid');
    deliveryAddress.classList.remove('is-invalid');
  }

  //samovivoz checked
  if(delivRadios[1].checked && checkRadio){
    let offices = form.querySelectorAll('[name="office"]');
    let officesChecked = false;
    offices.forEach((of)=>{
      if (of.checked) officesChecked=true;
    });
    if (!officesChecked){
      rez = false;
      radioRowContent.classList.add('is-invalid');
      radioRowContent.classList.remove('is-valid');
    }
    else {
      radioRowContent.classList.add('is-valid');
      radioRowContent.classList.remove('is-invalid');
    }
  }
  else {
    radioRowContent.classList.add('is-valid');
    radioRowContent.classList.remove('is-invalid');
  }
  //console.log('cool', rez);
  if (rez) form.submit();
}

function radioChange(e){
  let target = e.currentTarget.children[0].value;
  if (target*1 === 1) {
    radioDeliv.classList.add('show');
    radioSamVivoz.classList.remove('show');
  }
  else {
    radioDeliv.classList.remove('show');
    radioSamVivoz.classList.add('show');
  }
}

const KBronStartButton = document.querySelector('[data-action="kbronstart"]');
if (KBronStartButton) {

  document.querySelector('#orderModal').addEventListener('hidden.bs.modal', backToFirstStep);

  document.querySelectorAll('[data-action="l3_k_more_btn"]').forEach(el=>{
    el.addEventListener('click', l3_k_more_toggle)
  });

  function l3_k_more_toggle(e){
    let cont = e.currentTarget.closest('.l3_more_cont');
    let target = cont.querySelector('.line2');
    target.classList.toggle('show');
    e.currentTarget.querySelector('img').classList.toggle('rotate');
    //console.log(target);
  }



  KBronStartButton.addEventListener('click', KBronShowItems);
  let bodyTarget = form.querySelector('.k_second-step_container')
  let next1btn=document.querySelector('[data-action="modal-next1"]');
  let firstStepContainer=document.querySelector('.k_first-step_container');

  let rostFromEl = document.querySelector('input[name="rost_from"]');
  let rostToEl = document.querySelector('input[name="rost_to"]');
  let rostSizeEl = document.querySelector('input[name="size"]');
  let eventDateEl = document.querySelector('.event_date_1');
  let modelId = document.querySelector('input[name="model_id"]').value;

  let sizeLis = document.querySelectorAll('.rostsize-li');
    if (sizeLis) {
      sizeLis.forEach(el=>{
        el.addEventListener('click', sizeLiClick);
      });
    }

  let sizeListL3 = document.querySelectorAll('.k_sizes li');
    if (sizeListL3){
      sizeListL3.forEach(el=>{
        el.addEventListener('click', sizeClickL3);
      });
    }

  if (next1btn) next1btn.addEventListener('click', next1Click);

  document.querySelector('.date.event_date_1').addEventListener('click', makeSelfActive);
  document.querySelector('.date.event_date_1').addEventListener('change', makeSelfActive);

  function makeSelfActive(e){
    if (e.currentTarget.value!='') {
      e.currentTarget.classList.add('active');
    }
    else {
      e.currentTarget.classList.remove('active');
    }
  }

  function backToFirstStep(e){
    firstStepContainer.classList.remove('hide');
    bodyTarget.innerHTML='';
  }


  function KBronShowItems(e){
    if (e && e.currentTarget.classList.contains('action-button')) {
      let modal = new bootstrap.Modal(document.querySelector('#orderModal'));
      modal.show();
    }
    let modelId = document.querySelector('input[name="model_id"]').value;
  }

  function sizeClickL3(e){
    sizeListL3.forEach((li, index)=>{
      if (li==e.currentTarget){
        li.classList.add('active');
        sizeLis[index].classList.add('active');
        rostFromEl.value=e.currentTarget.dataset.rost_from;
        rostToEl.value=e.currentTarget.dataset.rost_to;
        rostSizeEl.value=e.currentTarget.dataset.size;
      }
      else{
        li.classList.remove('active');
        sizeLis[index].classList.remove('active');
      }
    });
  }

  function sizeLiClick(e){
    if (sizeLis) {
      sizeLis.forEach((li, index)=>{
        if (li==e.currentTarget){
          li.classList.add('active');
          sizeListL3[index].classList.add('active');

          rostFromEl.value=e.currentTarget.dataset.rost_from;
          rostToEl.value=e.currentTarget.dataset.rost_to;
          rostSizeEl.value=e.currentTarget.dataset.size;
        }
        else{
          li.classList.remove('active');
          sizeListL3[index].classList.remove('active');
        }
      });
    }
  }

  function next1Click(e){
    let eventDate = new Date(eventDateEl.value);
      eventDate.setHours(23);
      eventDate.setMinutes(59);
      eventDate.setSeconds(59);
    let today = new Date();

    let checkPassed = true;
    let messages = [];

    if (eventDate<today) {
      checkPassed = false;
      messages.push('Дата мероприятия не должна быть в прошлом.');
    }
    if (rostFromEl.value=='') {
      checkPassed = false;
      messages.push('Выберите размер.');
    }
    if (eventDateEl.value==''){
      checkPassed = false;
      messages.push('Выберите дату мероприятия.');
    }

    if (!checkPassed) {
      alert(messages.join('; '));
      return false;
    }

    let data = new FormData(form);
    data.append('action', 'new_bron');
    data.append('date', eventDateEl.value);
    data.append('model_id', modelId);
    data.append('rost_from', rostFromEl.value);
    data.append('rost_to', rostToEl.value);
    data.append('size', rostSizeEl.value);

    fetch("/zvonok/kb", {
      method: 'POST',
      body: data,
    })
      .then((rez) => rez.json())
      .then((rezObj) => {
        //console.log(rezObj);
        firstStepContainer.classList.add('hide');
        bodyTarget.innerHTML=rezObj.rez;
        if (document.querySelector('.kb-btn-zayavka')) document.querySelector('.kb-btn-zayavka').addEventListener('click', kbZayavka);
        if (document.querySelector('.kb_bron')) {
          document.querySelector('.kb_bron').addEventListener('click', kbBron);
          document.querySelectorAll('.k_second-step_container .kb_select.day').forEach((el)=>{
            el.addEventListener('change', kbDateChange);
          });
          document.querySelectorAll('.free-period-radio').forEach((el)=>{
            el.addEventListener('change', freePeriodSelect);
          });
        }
        if (document.querySelector('.btn-back')) document.querySelector('.btn-back').addEventListener('click', backToFirstStep);
        document.querySelector('.kb_message').src = document.querySelector('.l3__slider__big_pic_container img').src;

      });

  }

  function kbZayavka(){
    console.log('zayavka');

    let modelId = document.querySelector('input[name="model_id"]').value;
    let date = document.querySelector('input.event_date_1').value;
    let rostFrom = rostFromEl.value;
    let rostTo = rostToEl.value;

    let data = new FormData(form);
    data.append('action', 'zayavka');
    data.append('date', date);
    data.append('model_id', modelId);
    data.append('rost_from', rostFrom);
    data.append('rost_to', rostTo);

    let phone=document.querySelector('.kb_phone_zayavka').value;

    let ok = true;

   if (phone=='' || phone.length<7) {
      ok = false;
      alert('Заполните номер телефона (не менее 7 цифр).');
    }

    if (!ok) return false;

    fetch("/zvonok/kb", {
      method: 'POST',
      body: data,
    })
      .then((rez) => rez.json())
      .then((rezObj) => {
        //console.log(rezObj);
        if (rezObj.status=='zayavka_ok') {
          bodyTarget.innerHTML=rezObj.rez;
        }
        else {
          bodyTarget.innerHTML='Произошла техническая ошибка. Попробуйте повторить операцию или связаться с нами по телефону.';
        }


      });
  }
  function kbBron(){
    console.log('start');
    let dayFrom = document.querySelector('.free-period.selected #from_day').value;
    let timeFrom = document.querySelector('.free-period.selected #from_time').value;
    let dayTo = document.querySelector('.free-period.selected #to_day').value;
    let timeTo = document.querySelector('.free-period.selected #to_time').value;
    let fio = document.querySelector('#kb_fio').value;
    let phone = document.querySelector('#kb_phone').value;
    let info = document.querySelector('#kb_info').value;

    let modelId = document.querySelector('input[name="model_id"]').value;
    let invN = document.querySelector('.free-period.selected').dataset.inv;
    let date = document.querySelector('input.event_date_1').value;
    let rostFrom = rostFromEl.value;
    let rostTo = rostToEl.value;

    let from = new Date(dayFrom);
      from.setHours(timeFrom);
      from.setMinutes(0);

    let to = new Date(dayTo);
      to.setHours(timeTo);
      to.setMinutes(0);


    let ok = true;
    let messagesArray = [];

    if (dayFrom == '' || timeFrom == '' || dayTo == '' || timeTo == '') {
      ok = false;
      alert('Заполните даты и время выдачи и возврата костюма.');
    }
    else if (to <= from) {
      ok = false;
      alert('Дата/время возврата костюма должна быть позже даты/времени выдачи');
    }
    else if (fio=='') {
      ok = false;
      alert('Заполните ФИО');
    }
    else if (phone=='' || phone.length<9) {
      ok = false;
      alert('Заполните номер телефона с кодом (не менее 9 цифр).');
    }

    if (!ok) return false;

    let data = new FormData(form);
    data.append('action', 'kb_save');
    data.append('date', date);
    data.append('model_id', modelId);
    data.append('invn', invN);
    data.append('rost_from', rostFrom);
    data.append('rost_to', rostTo);

    data.append('from', dayFrom+' '+timeFrom+':00');
    data.append('to', dayTo+' '+timeTo+':00');
    data.append('fio', fio);
    data.append('phone', phone);
    data.append('info', info);

    let bodyTarget = form.querySelector('.k_second-step_container');


    fetch("/zvonok/kb", {
      method: 'POST',
      body: data,
    })
      .then((rez) => rez.json())
      .then((rezObj) => {
        //console.log(rezObj);
        bodyTarget.innerHTML = rezObj.message;
        // if (document.querySelector('.kb-btn-zayavka')) document.querySelector('.kb-btn-zayavka').addEventListener('click', kbZayavka);
        // if (document.querySelector('.kb_bron')) document.querySelector('.kb_bron').addEventListener('click', kbBron);

      });

  }
}

//new functionality for kbrons
function kbDateChange(e){
  let dateSelect = e.target;
  let lineDiv = dateSelect.closest('.time-line');
  let period = dateSelect.closest('.free-period');
  let timeSelect = lineDiv.querySelector('.time');


  if (dateSelect.value=="") {
    clearTime(timeSelect);
  }
  else {
    timeChange(timeSelect);
  }
}

function timeChange(timeSelect){
  let dateSelect = timeSelect.closest('.time-line').querySelector('.day');

  let period = dateSelect.closest('.free-period');
  let dateHtml = dateSelect.value;

  let scheduleInput = period.querySelector('.schedule[data-date="'+dateHtml+'"]')
  let periodFrom = new Date(period.dataset.from);
  let periodTo = new Date(period.dataset.to);
  let selectedDateFrom = new Date(dateHtml);
    selectedDateFrom.setHours(scheduleInput.dataset.openhour);
  let selectedDateTo = new Date(dateHtml);
    selectedDateTo.setHours(scheduleInput.dataset.closehour);

  if (selectedDateFrom < periodFrom) selectedDateFrom = periodFrom;
  if (selectedDateTo > periodTo) selectedDateTo = periodTo;

  fillInTime(timeSelect, selectedDateFrom.getHours(), selectedDateTo.getHours());

}

function fillInTime(container, fromH, toH){
  clearTime(container);
  fromH=fromH*1;
  toH=toH*1;

  while (fromH<=toH){
    let option = document.createElement('option');
    option.value=fromH;
    option.innerHTML = fromH+':00';
    container.append(option);
    fromH = fromH +1;
  }
}
function clearTime(container){
  container.innerHTML='<option value="">время</option>';
}

function freePeriodSelect(e){
  document.querySelectorAll('.free-period').forEach((el)=>{
    el.classList.remove('selected');
  });
  e.target.closest('.free-period').classList.add('selected');
}
