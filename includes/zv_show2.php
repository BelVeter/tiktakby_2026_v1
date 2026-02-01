<script>

function getXmlHttp(){
	  var xmlhttp;
	  try {
	    xmlhttp = new ActiveXObject("Msxml2.XMLHTTP");
	  } catch (e) {
	    try {
	      xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
	    } catch (E) {
	      xmlhttp = false;
	    }
	  }
	  if (!xmlhttp && typeof XMLHttpRequest!='undefined') {
	    xmlhttp = new XMLHttpRequest();
	  }
	  return xmlhttp;
}//end of getXmlHttp



function zv_check () {

  let mainDiv=document.querySelector('#zv_div').closest('.row');
	//document.getElementById('post_div_').innerHTML='<img src="w.gif" />';

	var xmlhttp = getXmlHttp()
	xmlhttp.open("POST", '/bb/zv_ch.php', true)
	xmlhttp.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

	var params = '&action=' + encodeURIComponent('zv_check');

	xmlhttp.send(params);
	xmlhttp.onreadystatechange = function() {
	  if (xmlhttp.readyState == 4) {
	     if(xmlhttp.status == 200) {
	    	//alert (xmlhttp.responseText);
	    	 zv_num=(xmlhttp.responseText);

	    	 if (zv_num>=1) {
	    		 document.getElementById('zv_div').innerHTML='Заказ на обратный звонок: '+zv_num+' шт.!!!<a href="/bb/zv_ch.php" class="btn btn-lg btn-danger" style="margin-left: 30px">Перейти к звонкам</a></div>';
	    	 }//if
	    	 else {
	    		 document.getElementById('zv_div').innerHTML='';
	    	 }//else

			   }
	  		}
		}

  let data = new FormData();
  data.append('action', 'get_tasks_number');
  let rezDiv = document.querySelector('.task-zv-div');
  if (!rezDiv) {
    rezDiv = document.createElement('div');
    rezDiv.classList.add('col-12', 'alert-danger', 'h2', 'text-center', 'task-zv-div');
  }

    fetch("/bb/zv_ch.php", {
      method: 'POST',
      body: data,
    })
      .then(rez=>rez.json())
      .then((rezObj)=>{
        if (rezObj.count>0) rezDiv.innerHTML='Поступили новые задачи ('+rezObj.count+' шт). <a href="/bb/task_management.php" class="btn btn-lg btn-danger" style="margin-left: 30px">Перейти к задачам</a>';
        else rezDiv.innerText='';
        mainDiv.appendChild(rezDiv);
      });

}// end of zv_ch

zv_check();

var timer = setInterval(zv_check, 600000);


</script>
