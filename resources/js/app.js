require('./bootstrap');


var menuId;
var clickTimestamp;

function l2_calculate (m_id) {
    var tarif;
    var result;
    var num=+document.getElementById('tov2_num_'+m_id).value;

    if (num<1) {
        num=1;
        document.getElementById('tov2_num_'+m_id).value=num;
    }

    if (num>tars[m_id].length-1) {
        tarif=+tars[m_id][tars[m_id].length-1];
        result=tarif*num;
    }
    else {
        tarif=+tars[m_id][num];
        result=tarif*num;;
    }
    document.getElementById('nedel_'+m_id).innerHTML=tarif.toFixed(2)+' руб.';
    document.getElementById('total_'+m_id).innerHTML='('+result.toFixed(2)+' руб. ВСЕГО)';
}

// $("#l3-order-button").click(function(){
//     $("#order-form").toggle();
//     if ($('#l3-order-button').text()=='ЗАБРОНИРОВАТЬ'){
//         $('#l3-order-button').text('ОТМЕНА');
//     }
//     else{
//         $('#l3-order-button').text('ЗАБРОНИРОВАТЬ');
//     }
// });
//
// $(document).ready(function(){
//     $(".mlbtn").click(function() {
//         divid="#mldiv_"+$(this)[0].id;
//
//         if ($(divid).height()==100) {
//             $(divid).height($(divid)[0].scrollHeight+22);
//             $(this).text('Свернуть «');
//         }
//         else{
//             $(divid).height(100);
//             $(this).text('Развернуть »')
//         }
//     });
// });
//
//
// //top menu scripts
// $('#top-cat-menu1 > li').on('click', function(e){
//     n=$(this).next();
//     if(n.is(":visible")) {
//         n.hide();
//         //$("#shadow").hide();
//     }
//     else {
//         n.show();
//         //$("#shadow").show();
//     }
// });
//
// //clisk outside of my top-menu
// $(document).mouseup(function(e)
// {
//     var container = $(".cat-menu");
//     var but=$("#dropdownMenuButton");
//
//     // if the target of the click isn't the container nor a descendant of the container
//     if ((!container.is(e.target) && container.has(e.target).length === 0) && (!but.is(e.target) && but.has(e.target).length === 0))
//     {
//         container.hide();
//         $("#shadow").hide();
//     }
// });
//
//
// //left menu scripts
// $('.left-menu > ul > .level1').on('click', function(e){
//     //alert('click');
//     n=$(this).next();
//     cross=$(this).find('.lm_cross');
//     console.log(cross);
//     if(n.is(":visible")) {
//         n.hide();
//         cross.addClass('lm_o_c2');
//         cross.removeClass('lm_o_c');
//     }
//     else {
//         $('.left-menu').find('.level2:visible').hide();
//         $('.left-menu').find('.lm_o_c:visible').addClass('lm_o_c2');
//         $('.left-menu').find('.lm_o_c:visible').removeClass('lm_o_c');
//
//
//         // $('.left-menu').find('.lm_cross').addClass('lm_o_c2');
//         // $('.left-menu').find('.lm_cross').removeClass('lm_o_c');
//         n.show();
//         cross.addClass('lm_o_c');
//         cross.removeClass('lm_o_c2');
//     }
// });



// $(".l2-card_number-input").change(function (){
//     id=$(this).prop('id');
//     dd=$('.mid_'+id);
//     ch_num=$(this).val();
//
//     num=0;
//     val=0;
//
//     dd.each(function (index, element){
//         if (ch_num>=($(element).prop('name')*1)) {
//             num=$(element).prop('name')*1;
//             val=$(element).val()*1;
//         }
//     })
//
//     total=ch_num*val;
//     $('#steprent_'+id).html(val.toFixed(2));
//     $('#total_'+id).html(total.toFixed(2));
//     //console.log('итого:'+total)
// });
//
// $(".l2-card_number-input").keyup(function (){
//     id=$(this).prop('id');
//     dd=$('.mid_'+id);
//     ch_num=$(this).val();
//
//     num=0;
//     val=0;
//
//     dd.each(function (index, element){
//         if (ch_num>=($(element).prop('name')*1)) {
//             num=$(element).prop('name')*1;
//             val=$(element).val()*1;
//         }
//     })
//
//     total=ch_num*val;
//     $('#steprent_'+id).html(val.toFixed(2));
//     $('#total_'+id).html(total.toFixed(2));
//     //console.log('итого:'+total)
// });
//
// //l3 slider
// $('.carousel').on('slid.bs.carousel', function() {
//     $(".carousel-indicators2 li").removeClass("active");
//     indicator = $(".carousel-indicators li.active").data("slide-to");
//     a = $(".carousel-indicators2").find("[data-slide-to='" + indicator + "']").parent().addClass("active");
//     //console.log(indicator);
//
// })
//
// $('#exampleModalCenter').on('show.bs.modal', function (event) {
//     var img = $(event.relatedTarget) // Button that triggered the modal
//     //var recipient = button.data('whatever') // Extract info from data-* attributes
//     // If necessary, you could initiate an AJAX request here (and then do the updating in a callback).
//     // Update the modal's content. We'll use jQuery here, but you could use a data binding library or other methods instead.
//     var modal = $(this)
//     //modal.find('.modal-title').text('New message to ' + recipient)
//     modal.find('.modal-body input').val(img.attr('src'))
// })
//
// //tars calculate
// $(document).ready(function (){
//     let items = document.querySelectorAll('.tar-dates');
//     items.forEach((e) => {
//         e.addEventListener('change', tarCalculate, false);
//     });
// });

function pageTarifsActions(){
    let rez=tarCalculate('datepicker-from', 'datepicker-to');
    console.log('formula calculated rez='+rez);
    rez = rez.toFixed(2)*1;

    let eur = Math.floor(rez);
    let cents = rez - eur;
    cents = Math.floor(cents * 100);

    console.log('rez='+rez+'_eur='+eur+'_cents='+cents);

    if (cents.toString().length === 1) cents+='0';
    document.getElementById('tarif_eur').innerHTML=eur;
    document.getElementById('tarif_cents').innerHTML=cents;
}

// $(document).ready(function (){
//     let items = document.querySelectorAll('.date-unstyled');
//     items.forEach((e) => {
//         e.addEventListener('change', pageTarifsActions, false);
//     });
// });

function tarCalculate(from_id, to_id, tarif_class='tarifs-in-days'){
    let from = new Date(document.getElementById(from_id).value);
    let to = new Date(document.getElementById(to_id).value);

    if (!(from.getTime()>0 & to.getTime()>0)) {
        return false;
    }

    let secDiff = to.getTime() - from.getTime();
    if (secDiff<0) return 0;
    let dayDiff = secDiff / (1000*60*60*24); // divide by number of miliseconds in day
    let htmlTars = document.querySelectorAll('.'+tarif_class)
    let tars = new Map();
    let tarsKeys = [];
    htmlTars.forEach((element) => {
        tars.set(Number(element.name), Number(element.value));
        tarsKeys.push(Number(element.name));
    })
    tarsKeys.sort((a, b) => {
        return b*1 - a*1;
    });

    let key=0;
    let keyPrev=0;
    let i = 0;
    for (let e of tarsKeys) {
        i++;
        if (dayDiff >= e && i === 1) {
            key = e;
            //console.log('first condition. key:'+key+'_keyPrev='+keyPrev+'_e='+e);
            break;
        }
        else if (i !== 1) {
            if (dayDiff<=keyPrev && dayDiff > e){
                key=keyPrev;
                //console.log('second condition. key:'+key+'_keyPrev='+keyPrev+'_e='+e);
                break;
            }
            else {
                key = e;
            }
        }

        keyPrev = e;

        //console.log('no condition key:'+key+'___'+'keyprev:'+keyPrev);
    }

    let result = 0;

    if (i === 1) {
        dayTarif = tars.get(key) / key;
        result = dayDiff * dayTarif;
    }
    else {
        dayTarif='no';
        result = tars.get(key);
    }

    //console.log('Days='+dayDiff+' for '+dayTarif+' per day, total = '+result);

    return result;
}


//call back functionality
document.querySelectorAll('[data-targetinput]').forEach((el) => {
    el.addEventListener('keyup', krInputAjust);
});


backCallForm=document.querySelector('.back-coll');

backCallForm.querySelector('.close-cross').addEventListener('click', (e) => {
    e.target.parentElement.style.display='none';
});

document.querySelectorAll('[data-callback]').forEach((el) => {
    el.addEventListener('click', (e) => {
        e.preventDefault();
        backCallForm.style.display='flex';
    });
});

function krInputAjust(event){
    let len = event.target.value.length;
    let text = event.target.placeholder;
    let parent = event.target.parentElement;
    //console.log(text);
    if (len>0) {
        createSpanIfNotExists(parent, text);
    }
    else {
        if(parent.querySelector('span')) parent.querySelector('span').remove();
        if(parent.querySelector('[data-targetinput]')) parent.querySelector('[data-targetinput]').classList.remove('not-empty');
    }
}

function createSpanIfNotExists(parentElement, text){
    if(!parentElement.querySelector('span')){
        let span = document.createElement('span');
        span.innerText = text;
        parentElement.prepend(span);
        parentElement.querySelector('[data-targetinput]').classList.add('not-empty');
    }
}


//menu onhover show\hide + shadow + opacity for non-active top-menus

let back = document.querySelector('.backgound');
let menuItems = document.querySelectorAll('.item-a');


document.querySelectorAll('.top-nav-item').forEach((el) => {
    el.addEventListener('mouseenter', delayShow);

    el.addEventListener('mouseleave', hideMenuItem);
});

document.querySelector('.top-nav-row').addEventListener('mouseleave', menuMouseOut);

let menuEvent;
let delayStarted = false;
let firstDelayFinished = false;

function delayShow(e) {
    menuEvent=e;
    //console.log(delayStarted,firstDelayFinished);
    if(!delayStarted) {
        delayStarted=true;

        setTimeout(()=>{
            firstDelayFinished=true;
            showMenuItem();
        },400);
    }
    else {//delay started
        if (firstDelayFinished) {
            showMenuItem();
        }
    }

}

function menuMouseOut(){
    delayStarted = false;
}


function showMenuItem(/* e */){
    if (!delayStarted) return;
    e = menuEvent;

    if(localStorage.getItem('menu_id')) {
        menuId = localStorage.getItem('menu_id') * 1;
    }
    if(localStorage.getItem('click_timestamp')) {
        clickTimestamp = localStorage.getItem('click_timestamp')*1;
    }

    let navItem = e.target;
    if (navItem.dataset.id==menuId) {
        if (clickTimestamp != undefined && (Date.now()-clickTimestamp)<3000) {
            return false;
        }
    }
    else {
        unsetLocalStorage();
    }
    let currentA = e.target.querySelector('.item-a');
    e.target.classList.add('hover');
    back.classList.add('show');
    menuItems.forEach((el2) => {
        if(el2 != currentA) el2.classList.add('nonactive');
    });
}

function hideMenuItem(e){
    if(localStorage.getItem('menu_id')) {
        menuId = localStorage.getItem('menu_id') * 1;
    }
    if(localStorage.getItem('click_timestamp')) {
        clickTimestamp = localStorage.getItem('click_timestamp')*1;
    }

    let navItem = e.target;
    if (navItem.dataset.id==menuId) {
        unsetLocalStorage();
    }
    e.target.classList.remove('hover');
    back.classList.remove('show');
    menuItems.forEach((el2) => {
        if(el2 != e.target) el2.classList.remove('nonactive');
    });
}


menuItems.forEach((el) => {
    el.addEventListener('click', saveClickedInfo);
});

function saveClickedInfo(e){
    let navItem = e.target.closest('li');
    localStorage.setItem('menu_id', navItem.dataset.id);
    localStorage.setItem('click_timestamp', Date.now());
}


window.addEventListener('load', loadLocalStorageData);

function loadLocalStorageData(){
    if(localStorage.getItem('menu_id')) {
        menuId = localStorage.getItem('menu_id') * 1;
    }
    if(localStorage.getItem('click_timestamp')) {
        clickTimestamp = localStorage.getItem('click_timestamp')*1;
    }
}

function unsetLocalStorage(){
    localStorage.setItem('menu_id', '');
    localStorage.setItem('click_timestamp', '');
    menuId='';
    clickTimestamp=0;
}

//mobile menu show

document.querySelector('.menu1-open').addEventListener('click', mobileMenuShow);
let topMenu1 = document.querySelector('.header-line1 ul');

function mobileMenuShow(){
    if (this.classList.contains('active')) {
        topMenu1Hide();
    }
    else {
        topMenu1Show();
    }
}

function topMenu1Show(){
    document.querySelector('.menu1-open').classList.add('active');
    topMenu1.classList.add('show');
    hideKatalogAll();
}

function topMenu1Hide(){
    topMenu1.classList.remove('show');
    document.querySelector('.menu1-open').classList.remove('active');
}



//mobile katalog
//show btn bind
let katalogRazdelUl = document.querySelector('ul[data-navlevel="razdel"]');
let wholeKatalogsDiv = document.querySelector('.mobile-topmenu-container');
let allSubrazdelsUls = wholeKatalogsDiv.querySelectorAll('ul[data-navlevel="subrazdel"]');
document.querySelector('.mobile-menu-line1 .col1').addEventListener('click', toggleKatalog);

function showRazdelMenu(){
    katalogRazdelUl.classList.remove('left');
    hideAllSubRazdels();
}
function hideRazdelMenu(){
    katalogRazdelUl.classList.add('left');
}

function hideAllSubRazdels(){
    allSubrazdelsUls.forEach((el)=>{
        el.classList.remove('left');
        el.classList.add('right');
    });
}

function getSubRazdelShown(){ // shown or false
    allSubrazdelsUls.forEach((el)=>{
        if (!el.classList.contains('right')) {
            return el;
        }
    });
    return false;
}

function hideKatalogAll(){
    hideRazdelMenu();
    hideAllSubRazdels();
}

function toggleKatalog(e){
    if (katalogRazdelUl.classList.contains('left')) {//if razdel hidden
        if (sr = getSubRazdelShown()) {//subrazel is shown
            hideKatalogAll();
        }
        else {
            showRazdelMenu(); //show razdel
            topMenu1Hide();
        }
    }
    else {//if razdel shown
        katalogRazdelUl.classList.add('left'); //hide razdel
    }
}


document.querySelectorAll('.nav-item-mobile.expand').forEach((el) => {
    el.addEventListener('click', katalogItemClick);
});

function katalogItemClick(e){
    let currentLi=e.target.closest('li');
    let currentUl=currentLi.closest('ul');
    let nextUl;

    if (currentLi.classList.contains('back')){
        nextUl = wholeKatalogsDiv.querySelector('ul[data-navlevel="razdel"]');
        currentUl.classList.add('right');
        nextUl.classList.remove('left');
    }
    else {
        nextUl = wholeKatalogsDiv.querySelector('ul[data-razdelid="'+currentLi.dataset.razdelid+'"]');
        currentUl.classList.add('left');
        nextUl.classList.remove('right');
    }
}


//favorite items main page
let itemsFList = document.querySelector('.top-card-container');
document.querySelectorAll('.top-card-arrow').forEach((el)=>{
    el.addEventListener('click', itemsScroll);
});

let scrollContainer = document.querySelector('.top-card-container');
let firstItem = document.querySelector('.top-card-container .top-card');
let cardsNumber = document.querySelectorAll('.top-card-container .top-card').length;

//window.addEventListener('resize', centreSliderScroll);

function centreSliderScroll(){
    let itemWidth = firstItem.clientWidth;
    let screenWidth = scrollContainer.clientWidth;

    let scrollToNumber = Math.ceil(cardsNumber/2);

    //console.log(scrollToNumber*itemWidth);

    scrollContainer.scrollTo({
        left: scrollToNumber*itemWidth,
        behavior: 'smooth' })
}


function itemsScroll(e){
    let showCapacity = Math.floor(scrollContainer.clientWidth / firstItem.clientWidth);
    let itemsHiddenFullLeft = Math.floor(scrollContainer.scrollLeft / firstItem.clientWidth);
    let itemsHiddenPartLeft = Math.ceil(scrollContainer.scrollLeft / firstItem.clientWidth);
    let itemsShownFullLeft = Math.floor((scrollContainer.scrollLeft + scrollContainer.clientWidth) / firstItem.clientWidth);
    let itemWidth = firstItem.clientWidth;
    let screenWidth = scrollContainer.clientWidth;

    //console.log(itemsHiddenFullLeft, itemsShownFullLeft, showCapacity, itemWidth,scrollContainer.clientWidth);

    if(e.currentTarget.classList.contains('left')) {
        if (itemsHiddenFullLeft<showCapacity) {
            //console.log('if close');
            itemsHiddenFullLeft = showCapacity;
        }
            //console.log((itemsHiddenFullLeft-showCapacity) * itemWidth - (screenWidth - showCapacity * itemWidth)/2);
        scrollContainer.scrollTo({
            left: (itemsHiddenPartLeft-showCapacity) * itemWidth - (screenWidth - showCapacity * itemWidth)/2,
            behavior: 'smooth' })
    }
    else {
        scrollContainer.scrollTo({
            left: itemsShownFullLeft*itemWidth - (screenWidth - showCapacity * itemWidth)/2,
            behavior: 'smooth' })
    }
}


//reviews

let reviewTransitionRunning = false;

const REVIEW_CONTAINER = document.querySelector('.review-slider-container');
if (REVIEW_CONTAINER) {
    REVIEW_CONTAINER.addEventListener('transitionend', transitionFinished);
}


const REVIEW_ITEMS = REVIEW_CONTAINER?.querySelectorAll('.review-item');


function transitionFinished(){
    let currentItem=REVIEW_CONTAINER.querySelector('.current');
    let nextItem=REVIEW_CONTAINER.querySelector('.next');

    currentItem.classList.remove('right');
    currentItem.classList.remove('left');
    currentItem.style.left='';
    currentItem.style.right='';
    currentItem.classList.remove('current');

    nextItem.classList.remove('right');
    nextItem.classList.remove('left');
    nextItem.style.right=''
    nextItem.style.left='';
    nextItem.style.width='';
    nextItem.classList.add('current');
    currentItem.classList.remove('next');


    reviewTransitionRunning = false;
}

function showNextReview(nextForse=-1){
    if (reviewTransitionRunning == true) return;
    reviewTransitionRunning = true;

    let currentItem=REVIEW_CONTAINER.querySelector('.current');
    let currentIndex = Array.prototype.indexOf.call(REVIEW_CONTAINER.children, currentItem);
    let currentItemRight = currentItem.parentElement.offsetWidth - currentItem.offsetWidth - currentItem.offsetLeft;
    let nextIndex;
    let nextItem;

    if (currentIndex<REVIEW_ITEMS.length-1) {
        nextIndex=currentIndex+1;
    }
    else nextIndex=0;

    if (nextForse>-1) nextIndex = nextForse;

    nextItem=REVIEW_ITEMS[nextIndex];

    nextItem.classList.add('next');
    //console.log(currentIndex, nextIndex);


    nextItem.classList.add('right');
    currentItem.classList.add('left');

    nextItem.style.width=currentItem.clientWidth+'px';

    setTimeout(()=>{
        nextItem.style.right=currentItemRight+'px';
        currentItem.style.left='-110%';
    }, 10);

    setPageActive(nextIndex);
    // console.log(currentItemRight);
}

function showPreviousReview(nextForse){
    if (reviewTransitionRunning == true) return;
    reviewTransitionRunning = true;

    let currentItem=REVIEW_CONTAINER.querySelector('.current');
    let currentIndex = Array.prototype.indexOf.call(REVIEW_CONTAINER.children, currentItem);
    let currentItemLeft = currentItem.offsetLeft;
    let nextIndex;
    let nextItem;

    if (currentIndex>0) {
        nextIndex = currentIndex-1;
    }
    else {
        nextIndex=REVIEW_ITEMS.length-1;
    }

    if (nextForse>-1) nextIndex = nextForse;

    nextItem=REVIEW_ITEMS[nextIndex];
    nextItem.classList.add('next');

    //console.log(currentIndex, nextIndex);

    nextItem.classList.add('left');
    currentItem.classList.add('right');

        nextItem.style.width=currentItem.clientWidth+'px';

    setTimeout(()=>{
        nextItem.style.left=currentItemLeft+'px';
        currentItem.style.right='-110%';
    }, 10);

    setPageActive(nextIndex);

    // console.log(currentItemRight);
}

const PAGES_CONTAINER = document.querySelector('.pages');

if(PAGES_CONTAINER) PAGES_CONTAINER.addEventListener('click', pageClick);

function pageClick(e){
    if(reviewTransitionRunning) return;
    if(!e.target.classList.contains('page')) return;
    let active = PAGES_CONTAINER.querySelector('.active');
    let currentIndex=Array.prototype.indexOf.call(PAGES_CONTAINER.children, e.target);
    let activeIndex = Array.prototype.indexOf.call(PAGES_CONTAINER.children, active);
    if (currentIndex==activeIndex) return;

    if (currentIndex > activeIndex) {
        showNextReview(currentIndex);
    }
    else {
        showPreviousReview(currentIndex);
    }
    active.classList.remove('active');
    e.target.classList.add('active');
}

function setPageActive(index){
    let active = PAGES_CONTAINER.querySelector('.active');
    active.classList.remove('active');
    PAGES_CONTAINER.children[index].classList.add('active');
}


window.addEventListener('load', swipeHandle);

function swipeHandle(){
    let touchDiv = document.querySelector('.review-slider-container');
    if(!touchDiv) return;

    let startX, startY, distX, distY;
    let threshold = 50; //required min distance traveled to be considered swipe
    let elapsedTime;
    let startTime;
    let touchOK;

    touchDiv.addEventListener('touchstart', function(e){
        let touchobj = e.changedTouches[0];
        //console.log(touchobj);
        distX = 0;
        distY = 0;
        startX = touchobj.pageX;
        startY = touchobj.pageY;
        startTime = new Date().getTime(); // record time when finger first makes contact with surface
        // e.preventDefault();
    }, false);

    touchDiv.addEventListener('touchmove', function(e){
        let touchobj = e.changedTouches[0];
        distX = touchobj.pageX - startX // get total dist traveled by finger while in contact with surface
        distY = touchobj.pageY - startY;
        if (Math.abs( distX) > Math.abs(distY)) e.preventDefault();
        //console.log('---');
        //e.preventDefault(); // prevent scrolling when inside DIV
    }, false);

    touchDiv.addEventListener('touchend', function(e){
        //console.log('111');
        let touchobj = e.changedTouches[0];
        distX = touchobj.pageX - startX // get total dist traveled by finger while in contact with surface
        distY = touchobj.pageY - startY;
        elapsedTime = new Date().getTime() - startTime; // get time elapsed
        // check that elapsed time is within specified, horizontal dist traveled >= threshold, and vertical dist traveled <= 100

        touchOK = Math.abs(distX) >= threshold && Math.abs(distY) <= 50;

        //console.log(distX, distY);
        if (touchOK) {
            swipeAction(distX);
        }
        // e.preventDefault()
    }, false)
}

function swipeAction(distX){
    if (distX<0) {
        showNextReview();
    }
    else {
        showPreviousReview();
    }
}


//language menus toggle
// document.querySelector('.lang-open').addEventListener('click', (e)=>{
//     let buttonImg = document.querySelector('.mobile-header button img');
//     let langMenu = document.querySelector('.mobile-header .lang-choice-container');
//
//     buttonImg.classList.toggle('show');
//     langMenu.classList.toggle('show');
// })
//
// document.querySelector('.lang-open-btn-desctop').addEventListener('click', (e)=>{
//     let buttonImg = e.currentTarget.querySelector('img');
//     let langMenu = e.currentTarget.querySelector('.lang-choice-container');
//
//     buttonImg.classList.toggle('show');
//     langMenu.classList.toggle('show');
// })


//left menu new

document.querySelectorAll('.nav-left_arrow-btn').forEach((el)=>{
    el.addEventListener('click', toggleNavLeftCats);
});

function toggleNavLeftCats(e) {
    e.currentTarget.classList.toggle('show');
    let parrent = e.target.closest('li');
    let target = parrent.querySelector('.cat-row');
    target.classList.toggle('show');
}



