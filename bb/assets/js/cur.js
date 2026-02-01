let curTotal=document.querySelector('.cur-total-taken');
let curFree=document.querySelector('.cur-total-free');

document.querySelectorAll('.cur-action').forEach((item) => {
    item.addEventListener('click', takeTovarToggle);
});


document.querySelectorAll('.tov-item > img').forEach((item) => {
   item.addEventListener('click', (e) => {
       e.target.classList.toggle('big');
       if (e.target.classList.contains('return')) e.target.classList.toggle('gray-transparent');
   })
});




async function takeTovarToggle(e){
    if (e.target.classList.contains('done') || e.target.classList.contains('fail')) return;
    if (e.target.classList.contains('message')) {
        e.target.classList.remove('show');
        window.setTimeout(()=> {
            e.target.remove();
        }, 500);
        return false;
    };

    let data = {};
        data.action = "tovarToggle";
        data.invN = e.target.dataset.inv;
        e.target.dataset.curid > 0 ? data.curId=e.target.dataset.curid : data.curId='0';
        data.delId=e.target.dataset.delid;
        data.currentStatus='';

        if (e.target.classList.contains('notmy')) data.currentStatus='notmy';
        else if (e.target.classList.contains('taken')) data.currentStatus='my';
        else data.currentStatus='free';

        //console.log(JSON.stringify(data));
    let url = 'cur_viezdy.php';

    let headers =  {
        'Content-type': 'application/json; charset=UTF-8'
    };

    let request = {
        method: 'POST',
        body: JSON.stringify(data),
        headers
    }

    let rez = await fetch(url, request);
    let rezJson = await rez.json();

    //console.log(rezJson);
    if (rezJson.success && rezJson.newStatus == 'my') {

        e.target.dataset.curid = rezJson.curId;
        e.target.classList.remove('notmy');
        e.target.classList.add('taken');

        curTotal.dataset.curtotal = curTotal.dataset.curtotal*1 + 1;
        curTotal.innerHTML = curTotal.dataset.curtotal;

        curFree.dataset.curfree = curFree.dataset.curfree*1 - 1;
        curFree.innerHTML = curFree.dataset.curfree;

    }
    else if (rezJson.success && rezJson.newStatus == 'free') {
        e.target.dataset.curid = rezJson.curId;
        e.target.dataset.curid = '';
        e.target.classList.remove('taken', 'notmy');

        curTotal.dataset.curtotal = curTotal.dataset.curtotal*1 - 1;
        curTotal.innerHTML = curTotal.dataset.curtotal;

        curFree.dataset.curfree = curFree.dataset.curfree*1 + 1;
        curFree.innerHTML = curFree.dataset.curfree;

    }
    else if (rezJson.success && rezJson.newStatus == '') {
        showMessage(data.delId, rezJson.message);
    }
}


function showMessage(delId, message){
    //alert(message);
    let target = document.querySelector('div[data-delid="'+delId+'"]');
    //console.log(target);
    let div = document.createElement('div');
    div.classList.add('message');
    div.innerText = message;

    target.appendChild(div);

    window.setTimeout(()=> {
        div.classList.add('show');
    }, 50);


    window.setTimeout(()=> {
        div.classList.remove('show');
    }, 3000);

    window.setTimeout(()=> {
        div.remove();
    }, 3500);

}

let form = document.querySelector('.filter_form');

//date menu show-hide
document.querySelectorAll('.header-date, .date-menu button').forEach((el) => {
    el.addEventListener('click', () => {
        document.querySelector('.date-menu').classList.toggle('show');
    });
});

document.querySelectorAll('.date-menu ul li').forEach((el) => {
    el.addEventListener('click', (e) => {
        form.elements.period.value = e.target.dataset.period;
        form.submit();
    })
});


//cur menu show-hide
document.querySelectorAll('.cur-flower, .cur-menu button').forEach((el) => {
    el.addEventListener('click', () => {
        document.querySelector('.cur-menu').classList.toggle('show');
    });
});

document.querySelectorAll('.cur-menu ul li').forEach((el) => {
    el.addEventListener('click', (e) => {
        form.elements.cur.value = e.target.dataset.cur;
        form.submit();
    })
});


//modal script
modal = document.querySelector('.modal');
document.querySelectorAll('.cur-client').forEach((e) => {
    e.addEventListener('click', openModal);
});

document.querySelector('.modal button').addEventListener('click', closeModal);

async function openModal(e) {
    if (e.target.classList.contains('formodal')) {
        showModal();
        let data = {};
        data.action = "modal";
        data.delId = e.target.parentNode.dataset.delid;

        let url = 'cur_viezdy.php';

        let headers = {
            'Content-type': 'application/json; charset=UTF-8'
        };

        let request = {
            method: 'POST',
            body: JSON.stringify(data),
            headers
        }

        let rez = await fetch(url, request);

        let rezJson = await rez.json();
        //console.log(rezJson);
        fillModal(rezJson.body);
    }
}


let modalEls = document.querySelectorAll('[data-modal]');

function fillModal(obj) {
    //console.log(modalEls);
    modalEls.forEach((item) => {
        //console.log(item);
        //console.log (item.dataset.modal);
        //console.log(obj[item.dataset.modal]);
        if (item.dataset.modal == 'address') item.href=obj['yandexurl'];
        if (item.dataset.modal == 'phone1') item.href=obj['phone1url'];
        if (item.dataset.modal == 'phone2') item.href=obj['phone2url'];
        if (item.dataset.modal == 'active_deal_id') item.value=obj['activeDealId'];
        if (item.dataset.modal == 'img') {
            item.src = obj[item.dataset.modal];
            return;
        }
        if (item.dataset.modal == 'delid') {
            item.value=obj['delid'];
            return;
        }
        if (item.dataset.modal == 'deliverystatus') {
            item.classList.add(obj['statusButClass']);
            return;
        }
        if (item.dataset.modal == 'deliveryfail') {
            //console.log('fail-'+obj['statusButFailClass']);
            if(obj['statusButFailClass']!='') item.classList.add('hide');
            return;
        }
        if (item.dataset.modal == "deliverydone") {
            //console.log('done-'+obj['statusButDoneClass']);
            if(obj['statusButDoneClass']!='') item.classList.add(obj['statusButDoneClass']);
            return;
        }


        item.innerText = obj[item.dataset.modal];
    });
}

function clearModal() {
    modalEls.forEach((item) => {
        //console.log (item.dataset.modal);
        //console.log(obj[item.dataset.modal]);
        if (item.dataset.modal == 'address') item.href='';
        if (item.dataset.modal == 'phone1') item.href='';
        if (item.dataset.modal == 'phone2') item.href='';
        if (item.dataset.modal == 'img') {
            item.src = '';
            return;
        }
        if (item.dataset.modal == 'deliverystatus') {
            //console.log('---');
            item.classList.remove('hide');
            return;
        }
        if (item.dataset.modal == 'deliveryfail') {
            item.classList.remove('hide');
            return;
        }
        if (item.dataset.modal == 'deliverydone') {
            item.classList.remove('hide');
            return;
        }
        item.innerText = '';
    });
}

allContainers=document.querySelectorAll('.container');

function showModal() {
    allContainers.forEach((el) => {
        el.classList.add('blur');
    });
    modal.classList.add('show-modal');
}
function closeModal(){
    clearModal();
    allContainers.forEach((el) => {
        el.classList.remove('blur');
    });
    modal.classList.remove('show-modal');
}


//delivery actions
document.querySelectorAll('[data-newstatus]').forEach((el) => {
    el.addEventListener('click', statusAction)
})

function statusAction2(e){
    console.log(e.target);
    closeModal();
}

async function statusAction(e) {

        let data = {};
        data.action = "newstatus";
        data.newStatus = e.target.dataset.newstatus;
        data.delId = document.querySelector('[data-modal="delid"]').value;

        let url = 'cur_viezdy.php';

        let headers = {
            'Content-type': 'application/json; charset=UTF-8'
        };

        let request = {
            method: 'POST',
            body: JSON.stringify(data),
            headers
        }

        let rez = await fetch(url, request);
        let rezJson = await rez.json();
        let heart = document.querySelector('.cur-action[data-delid="'+rezJson['delId']+'"]');

        if(rezJson['newStatus']=='done') {
            heart.classList.add('done');
            heart.classList.remove('fail');
        }
        else if (rezJson['newStatus']=='fail') {
            heart.classList.add('fail');
            heart.classList.remove('done');
        }
        else {
            heart.classList.remove('done');
            heart.classList.remove('fail');
        }

        closeModal();
}