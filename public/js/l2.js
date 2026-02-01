//level 2 price calculation scripts
document.querySelectorAll('.arrow-up, .arrow-down').forEach((el) => {
  el.addEventListener('click', (e) => {
    let input = e.currentTarget.closest('.l2-card_input-form').querySelector('input');
    //console.log(input);
    if (e.currentTarget.classList.contains('arrow-up')) {
      input.value = input.value * 1 + 1;
    } else {
      input.value = input.value * 1 - 1;
    }

    let ev = new Event("change");
    input.dispatchEvent(ev);

  });
});

document.querySelectorAll('.l2-card_number-input').forEach((el) => {
  el.addEventListener('change', makeCalculationForInput);
});

function makeCalculationForInput(e) {
  let days = e.target.value;
  let tarifs = [];
  // console.log(e.currentTarget.closest('.l2-card_input-form'));
  e.currentTarget.closest('.l2-card_input-form').querySelectorAll('.tarif').forEach((el) => {
    tarifs.push({
      days: el.dataset.days * 1,
      total: el.value * 1,
      perDay: (Math.round((el.value / el.dataset.days) * 100) / 100),
    });
  });

 let tarifsCopy = tarifs.map((x) => x);
  tarifsCopy.sort((a,b)=>{
    return -a.days + b.days;
  });

  let perDayPay = getDayTarifForDaysPeriod(days, tarifs);
  let theTarif = getTarifForDaysPeriod(days, tarifs);

  let totalPay = Math.round(days * perDayPay * 100) / 100;

  let ceilingAmount = getCeilingAmountForTarif(theTarif, tarifsCopy);
  if (ceilingAmount && totalPay>ceilingAmount) {
    totalPay = ceilingAmount;
    perDayPay = (ceilingAmount / days);
  }

  let parent = e.target.closest('.l2-card_line-3');
  //console.log(parent);
  parent.querySelector('.day-tarif-span').innerText = perDayPay.toFixed(2);
  parent.querySelector('.total-rent-span').innerText = totalPay.toFixed(2);

}

function getTarifForDaysPeriod(days, tarifs){
  if (days<1) return 0;

  let tarif=tarifs[0];

  tarifs.forEach((el)=>{
    if (days>=el.days) {
      tarif = el;
    }
  });

  return tarif;
}

function getDayTarifForDaysPeriod(days, tarifs) {
  if (tarifs.length < 1) return 0;

  if (days<1) return 0;

  let tarif=tarifs[0];

  tarifs.forEach((el)=>{
    if (days>=el.days) tarif = el;
  });

  return tarif.perDay;
}

function getCeilingAmountForTarif(tar, tarifsCopy){
  let tarif = false;
  tarifsCopy.forEach((el)=>{
    if (el.days>tar.days) tarif = el;
  });

  if (tarif) {
    return tarif.total*1;
  }
  else return false;

}

let filterBtn = document.querySelector('#filter-btn-l2');

if (filterBtn){
  filterBtn.addEventListener('click', l2FilterCheck);
}

function l2FilterCheck(e){
  e.preventDefault();
  console.log('ddd');

  let pol = filterBtn.closest('form').querySelector('[name="gender"]').value;
  let rost = filterBtn.closest('form').querySelector('[name="rost"]').value;
  let date = filterBtn.closest('form').querySelector('[name="date"]').value;

  // console.log(pol, rost, date);

  if (pol=='all' && (rost=='' || rost=='0') && date=='') {
    alert('Заполните хотя бы один параметр поиска');
  }
  else {
    filterBtn.closest('form').submit();
  }
}
// karn filter form

