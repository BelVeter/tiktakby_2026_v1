const zayavkaBtns = document.querySelectorAll('.zayavka_btn');
const modalBackground = document.getElementById('modalBackground');
const closeBtn = document.querySelector('.close-button');
const modal = document.querySelector('.modal-content');
const modalDate = document.querySelector('.validity-date');
const modalDayNum = document.querySelector('.days_num');


modalBackground.addEventListener('keydown', (event) => {
  // Check if the pressed key is the "Enter" key (key code 13)
  if (event.key === 'Enter') {
    event.preventDefault(); // Prevent the default form submission behavior
  }
});


if (zayavkaBtns) {
  zayavkaBtns.forEach((el)=>{
    el.addEventListener('click', zayavkaBtnClick);
  });
}

modalDayNum.addEventListener('input', daysChange);
modalDate.addEventListener('change', dateChange);

function dateChange(){
  const selectedDate = new Date(modalDate.value);
  const currentDate = new Date();
  const timeDifference = selectedDate - currentDate;
  const numberOfDays = Math.ceil(timeDifference / (1000 * 60 * 60 * 24));
  modalDayNum.value = numberOfDays;
}

function daysChange(){
  const currentDay = new Date();
  const numberOfDays = parseInt(modalDayNum.value);
  const newDate = new Date(currentDay);
  newDate.setDate(currentDay.getDate() + numberOfDays);
  const formattedDate = newDate.toISOString().split('T')[0];
  modalDate.value = formattedDate;
}



function zayavkaBtnClick(e){
  modalBackground.style.display = 'flex';
  let tr = e.target.closest('tr');
  modal.querySelector('.days_num').value=tr.dataset.validityDays;
  modal.querySelector('.model_id').value=tr.dataset.modelid;
  modal.querySelector('.zv_id').value=tr.dataset.zvId;
  modal.querySelector('.textarea-field').value=tr.querySelector('.dop_info').innerHTML;

  daysChange();
}


function closeModal(){
  modalBackground.style.display = 'none';
}


closeBtn.addEventListener('click', closeModal);

// Close the modal when clicking outside the content
modalBackground.addEventListener('click', (event) => {
  if (event.target === modalBackground) {
    closeModal();
  }
});
