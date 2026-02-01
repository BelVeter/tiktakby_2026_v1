let rashBtn = document.querySelector('.rash-btn');
let rashRows = document.querySelectorAll('.rash-row');

let dohBtn = document.querySelector('.doh-btn');
let dohRows = document.querySelectorAll('.doh-row');

let clickableCells = document.querySelectorAll('.details');

let year = document.querySelector('table').dataset.year;

rashBtn.addEventListener('click', rashBtnToggle);
dohBtn.addEventListener('click', dohBtnToggle);
clickableCells.forEach((el)=>{
  el.addEventListener('dblclick', cellClick);
});



function rashBtnToggle(e){
  rashRows.forEach((el)=>{
    el.classList.toggle('d-none');
  });
  if (rashBtn.value=='+') rashBtn.value='-';
  else rashBtn.value='+';
}
function dohBtnToggle(e){
  dohRows.forEach((el)=>{
    el.classList.toggle('d-none');
  });
  if (dohBtn.value=='+') dohBtn.value='-';
  else dohBtn.value='+';
}


function cellClick(e){
  let month = e.target.dataset.month;
  let row = e.target.closest('tr');
  let type2 = row.dataset.type2;
  let type1 = row.dataset.type1;

  let from = new Date();
    from.setFullYear(year, month,1);
  let toText = e.target.dataset.to;

  var form = document.createElement('form');
  form.action = '/bb/doh-rash.php'; // Replace with your actual endpoint URL
  form.method = 'POST';
  form.target = '_blank';

  // Add data to the form
  var data = {
    action: 'показать',
    i_from_date: from.getFullYear()+'-'+(from.getMonth()>9 ? from.getMonth() : '0'+from.getMonth())+'-'+(from.getDate() > 9 ? from.getDate() : '0'+from.getDate()),
    i_to_date: toText,
    item_place: 'all',
    kassa_s: 'all',
    type1_s: type1,
    type2_s: type2,
    zp_sel_s: 'all',
  };

  for (var key in data) {
    if (data.hasOwnProperty(key)) {
      var input = document.createElement('input');
      input.type = 'hidden';
      input.name = key;
      input.value = data[key];
      form.appendChild(input);
    }
  }

  // Append the form to the body
  document.body.appendChild(form);

  // Submit the form
  form.submit();

  // Remove the form from the DOM
  document.body.removeChild(form);


}
