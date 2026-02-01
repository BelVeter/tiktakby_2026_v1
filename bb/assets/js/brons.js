const modal = document.querySelector('.modal');
const modalInvN = modal.querySelector('[name="invn-m"]');
const modalBrId = modal.querySelector('[name="brid-m"]');
let sellBtns = document.querySelectorAll('.sell-btn');
let modalSentBtn = modal.querySelector('.btn-sell');
let modalKassa = modal.querySelector('[name="kassa-m"]');


sellBtns.forEach((el)=>{
  el.addEventListener('click', sellBtnClick);
});

modalSentBtn.addEventListener('click', btnSellClick);

function sellBtnClick(e){
  let row = e.target.closest('tr.main-row');
  let invN = row.dataset.invn;
  let brId = row.dataset.brid;

  modalInvN.value=invN;
  modalBrId.value=brId;

  modal.classList.add('show');

  console.log(invN);
}

window.addEventListener('click', (event) => {
  if (event.target === modal) {
    modal.classList.remove('show');
  }
});

function btnSellClick(e){
  e.preventDefault();

  let rez = true;

  if (modalKassa.value==0) {
    rez=false;
    alert('Выберите кассу');
  }

  if (rez) {
    e.target.closest('form').submit();
  }

}



// JavaScript for the custom context menu
const contextMenu = document.getElementById("contextMenu");
const menuOptions = document.querySelectorAll(".context-menu li");
const targetElements = document.querySelectorAll('.tov_text');

// Function to show the context menu
function showContextMenu(event) {
  event.preventDefault();

  const mouseX = event.clientX;
  const mouseY = event.clientY;

  contextMenu.style.left = mouseX + "px";

  // Adjust the vertical position based on the scroll position
  const scrollY = window.scrollY || window.pageYOffset;
  contextMenu.style.top = mouseY + scrollY + "px";

  contextMenu.style.display = "block";

  let row = event.target.closest('tr.main-row');
  let brid = row.dataset.brid;
  contextMenu.dataset.brid=brid;
}

// Function to hide the context menu
function hideContextMenu() {
  contextMenu.style.display = "none";
  contextMenu.dataset.brid='';
}

// Attach the right-click event listener to the document
targetElements.forEach(el=>{
  el.addEventListener("contextmenu", showContextMenu);
})

// Attach click event listeners to menu options
contextMenu.querySelector('#menuOption1').addEventListener('click', sendToSell);

// Hide the context menu when clicking anywhere outside of it
document.addEventListener("click", hideContextMenu);

function sendToSell(){
  contextMenu.querySelector('[name="brid_context"]').value=contextMenu.dataset.brid;
  contextMenu.querySelector('form').submit();
}

