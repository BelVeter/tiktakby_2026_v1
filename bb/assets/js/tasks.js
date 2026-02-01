const currentUserId = document.querySelector('[name="current-user-id"]').value;
document.querySelectorAll('.comment-cell').forEach((el)=>{
  el.addEventListener('click', commentSellClick)
  el.querySelector('.btn-close').addEventListener('click', commentClose);
});

function commentSellClick(e){
  let elems = e.currentTarget.querySelectorAll('[data-edit="1"]');
  elems.forEach((el)=>{
    el.classList.remove('d-none');
  });
}

function commentClose(e){
  e.stopPropagation();
  let td = e.currentTarget.closest('td');
  let elems = td.querySelectorAll('[data-edit="1"]');
  elems.forEach((el)=>{
    el.classList.add('d-none');
  });
}

document.querySelectorAll('.owner-edit-btn').forEach((el)=>{
  el.addEventListener('click', ownerEditBtnClick);
});

function ownerEditBtnClick(e){
  let elements = e.target.closest('tr').querySelectorAll('[data-owner-edit="1"]');
  elements.forEach((el)=>{
    el.classList.remove('d-none');
  });
  e.currentTarget.classList.add('d-none');
  e.target.closest('tr').querySelector('.comment-cell').removeEventListener('click', commentSellClick);
}

document.querySelectorAll('[data-owner-cancel="1"]').forEach((el)=>{
  el.addEventListener('click', cancelOwnerEdit);
});

function cancelOwnerEdit(e){
  let row = e.target.closest('tr');
  let elements = row.querySelectorAll('[data-owner-edit="1"]');
  elements.forEach((el)=>{
    el.classList.add('d-none');
  });
  row.querySelector('.owner-edit-btn').classList.remove('d-none');
  row.querySelector('.comment-cell').addEventListener('click', commentSellClick);
}

//drag-and-drop management
let tasks = document.querySelectorAll('.drop-task');
  tasks.forEach((el)=>{
    el.addEventListener('dragstart', startDrag);
    el.addEventListener('contextmenu', taskRightClick);
    el.addEventListener('dblclick', taskRightClick);
  });

let dropDivs = document.querySelectorAll('.drop-div');
  dropDivs.forEach((el)=>{
    el.addEventListener('dragover', dragOver);
    el.addEventListener('dragleave', dragLeave);
    el.addEventListener('drop', drop);
  });

function startDrag(e){
  //console.log(e.target.id);
  e.dataTransfer.setData('id', e.target.dataset.id);
}

function dragOver(e){
  e.preventDefault();
  this.classList.add('border');
}

function dragLeave(e){
  this.classList.remove('border');
}

function drop(e){
  e.preventDefault();
  let id = e.dataTransfer.getData('id');
  let task = document.querySelector('#task-'+id);
  //console.log(task);
  e.currentTarget.appendChild(task);
  changeStatusTo(task, id, e.currentTarget.dataset.status);
  //console.log('dropped here '+this.dataset.status);
}

function changeStatusTo(task, id, status){
  let data = new FormData();
  data.append('a_action', status);
  data.append('id', id);

  fetch("/bb/task_management.php", {
    method: 'POST',
    body: data,
  })
    .then((rez) => rez.json())
    .then((rezObj) => {

      if (rezObj.result=='ok') {
        task.querySelector('#task-'+id+' .line1 .status').innerText=rezObj.newStatus;
        // console.log(rezObj.newComments);
        document.querySelector('#task-'+id+' .comments').innerHTML=rezObj.newComments;
        task.dataset.status=status;
      }

    });
}

//comments show
let commentStartBtns = document.querySelectorAll('.show-new-btn');
  commentStartBtns.forEach((el)=> {
    el.addEventListener('click', showNewComment);
  });

let commentCloseBtns = document.querySelectorAll('.new-comment .btns .btn-close');
  commentCloseBtns.forEach((el)=>{
    el.addEventListener('click', hideNewComment);
  });

let saveCommentBtns = document.querySelectorAll('.save-comment-btn');
  saveCommentBtns.forEach((el)=>{
    el.addEventListener('click', saveNewCommentAj);
  });

function showNewComment(e){
  e.target.closest('.new-comment').querySelector('textarea').classList.remove('hide');
  e.target.closest('.new-comment').querySelector('.btns').classList.remove('hide');
  e.target.closest('.new-comment').querySelector('.show-new-btn').classList.add('hide');
}

function hideNewComment(e){
  e.target.closest('.new-comment').querySelector('textarea').classList.add('hide');
  e.target.closest('.new-comment').querySelector('.btns').classList.add('hide');
  e.target.closest('.new-comment').querySelector('.show-new-btn').classList.remove('hide');
}

function saveNewCommentAj(e){
  let id = e.target.closest('.drop-task').dataset.id;
  let message = e.target.closest('.new-comment').querySelector('textarea').value;
  let data = new FormData();
  data.append('a_action', 'add_comment');
  data.append('id', id);
  data.append('comments', message);

  fetch("/bb/task_management.php", {
    method: 'POST',
    body: data,
  })
    .then((rez) => rez.json())
    .then((rezObj) => {

      if (rezObj.result=='ok') {
        document.querySelector('#task-'+id+' .comments').innerHTML=rezObj.newComments;
        hideNewComment(e);
        e.target.closest('.new-comment').querySelector('textarea').value='';
      }

    });
}



//new new task
const newTaskBtn = document.querySelector('.new-task-btn');
const newTaskRow = document.querySelector('.new-task-row');
const newTaskSubmitBtn = document.querySelector('.new-task-submit-btn');

if (newTaskBtn) newTaskBtn.addEventListener('click', newTaskToggle);
if (newTaskSubmitBtn) newTaskSubmitBtn.addEventListener('click', newTaskFormCheck);

function newTaskToggle(){
  if (newTaskRow.classList.contains('d-none')){
    newTaskRow.classList.remove('d-none');
    newTaskBtn.querySelector('span').innerText='-';
  }
  else {
    newTaskRow.classList.add('d-none');
    newTaskBtn.querySelector('span').innerText='+';
  }
}

function newTaskFormCheck(){
  let deadline = new Date(document.querySelector('#deadline').value);
  let today = new Date();

  let rez = true;
  let message = [];

  if (newTaskRow.querySelector('textarea').value == ''){
    rez=false;
    message.push('Заполните текст задачи');
  }
  if (deadline<=today) {
    rez = false;
    message.push('Срок исполнения дложен быть позже текущей даты-времени');
  }


  if (!rez) {
    alert(message.join(', '));
  }
  else {
    let action = document.createElement('input');
      action.type='hidden';
      action.name='action';
      action.value='new_task';
      this.form.appendChild(action);
    this.form.submit();
  }

}

//right click menu

const rightMenu = document.querySelector('.context-menu');
rightMenu.querySelector('[data-action="approved"]').addEventListener('click', makeApproved);
rightMenu.querySelector('[data-action="back-to-in-process"]').addEventListener('click', rejectApprooval);
rightMenu.querySelector('[data-action="delete"]').addEventListener('click', deleteTask);

function taskRightClick(e){
  //if (e.currentTarget.dataset.status != 'done' && e.currentTarget.dataset.status != 'approved') return false;
  if (e.currentTarget.dataset.create_who*1 != currentUserId*1) return false;

  let taskX = e.currentTarget.getClientRects()[0].x + window.scrollX;
  let taskY = e.currentTarget.getClientRects()[0].y + window.scrollY;

  let targetLeft = e.pageX - taskX;
  let targetTop = e.pageY - taskY;

  if (e.type=='dblclick'){
    targetLeft = targetLeft-100;
  }

  e.preventDefault();
  rightMenu.dataset.id = e.currentTarget.dataset.id;
  e.currentTarget.appendChild(rightMenu);
  rightMenu.style.left = targetLeft+'px';
  rightMenu.style.top = targetTop+'px';
  // console.log(e.pageX, e.pageY);
  //console.log(taskX, taskY);
  rightMenu.classList.remove('d-none');
}

document.addEventListener('click', closeContextMenu);

function closeContextMenu(e){
  rightMenu.classList.add('d-none');
}

function makeApproved(e){

  let task = e.currentTarget.closest('.drop-task');
  let id = task.dataset.id;
  changeStatusTo(task, id, 'approved');

}

function rejectApprooval(e){
  let task = e.currentTarget.closest('.drop-task');
  let id = task.dataset.id;
  changeStatusTo(task, id, 'in-process');
  document.querySelector('.in-process').appendChild(task);
}

function deleteTask(e){
  let task = e.currentTarget.closest('.drop-task');
  let id = task.dataset.id;

  let q = confirm('Безвовзратно удалить задачу?');
  if (!q) return false;

  let data = new FormData();
  data.append('a_action', 'delete');
  data.append('id', task.dataset.id);

  fetch("/bb/task_management.php", {
    method: 'POST',
    body: data,
  })
    .then((rez) => rez.json())
    .then((rezObj) => {

      if (rezObj.result=='ok') {
        task.remove();
      }

    });
}
