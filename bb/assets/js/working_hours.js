
const employeeSelect = document.querySelector('#employee');
const placeSelect = document.querySelector('#place');
const mainForm = document.querySelector('#new-data-form');
const scheduleContainer = document.querySelector('#week-schedule');
const monthShowBtn = document.querySelector('#month_view');

monthShowBtn.addEventListener('click', monthClick);


document.querySelectorAll('.shift-select').forEach((el)=>{
  el.addEventListener('change', getSchedule);
});

function getSchedule(){
  if (employeeSelect.value*1 === 0 || placeSelect.value*1 === 0) {
    return false;
  }
  let data = new FormData(mainForm);
  data.append('a_action', 'get-schedule');

  fetch("/bb/working_hours.php", {
    method: mainForm.method,
    body: data,
  })
    .then((rez) => rez.json())
    .then((rezObj) => {

      if (rezObj.result=='ok') {
        scheduleContainer.innerHTML=rezObj.options;

        let closeBtns = document.querySelectorAll('.day-cancel');
        closeBtns.forEach((el)=>{
          el.addEventListener('click', closeBtnClick);
        });
      }

    });
}

function closeBtnClick(e){
  let parent = e.target.closest('.sch-day');
  parent.querySelectorAll('select').forEach((el)=>{
    el.value=0;
  });
  console.log(e.target);
}


// function monthClick() {
//   const modal = document.getElementById("myModal");
//   const modalOverlay = document.getElementById("modalOverlay");
//   modal.style.display = "block";
//   modalOverlay.style.display = "block";
// }
//
// // Function to close the modal
// function closeModal() {
//   const modal = document.getElementById("myModal");
//   const modalOverlay = document.getElementById("modalOverlay");
//   modal.style.display = "none";
//   modalOverlay.style.display = "none";
// }

async function monthClick() {
  const dateInput = document.querySelector(".current_date").dataset.from;
  const modalContent = document.getElementById("modalContent");

  const form = new FormData();

  // Add a field with name 'inputField' and its value
  form.append('date', dateInput);



  try {
    const response = await fetch('shift_month_table.php', {
      method: 'POST',
      body: form
    });

    if (!response.ok) {
      throw new Error(`HTTP error! Status: ${response.status}`);
    }

    const data = await response.text();

    // Display the response in the modal
    modalContent.innerHTML = data;

    // Show the modal and overlay
    const modal = document.getElementById("myModal");
    const modalOverlay = document.getElementById("modalOverlay");
    modal.style.display = "block";
    modalOverlay.style.display = "block";
  } catch (error) {
    console.error("Error:", error);
  }
}

// Function to close the modal
function closeModal() {
  const modal = document.getElementById("myModal");
  const modalOverlay = document.getElementById("modalOverlay");
  modal.style.display = "none";
  modalOverlay.style.display = "none";
}
