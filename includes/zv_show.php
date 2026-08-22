<script language="javascript">

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



// Плашка со звонками висит поверх страницы в правом верхнем углу и может
// накрыть то, что там нарисовано. Класс на body позволяет странице подвинуть
// свои элементы ровно на то время, пока плашка на экране.
function zv_body_flag (on) {
	if (!document.body || !document.body.classList) return;
	if (on) document.body.classList.add('zv-alert');
	else document.body.classList.remove('zv-alert');
}

function zv_check () {

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
	    		 document.getElementById('zv_div').innerHTML='<div id="zvonki" style="position:absolute; right:0px; top:30px; background-color:#F03; width:150px;"> Заказ на обратный звонок: '+zv_num+' шт.!!!<br /><input type="button" value="обновить" onclick="zv_check()" /><br /><a href="/bb/zv_ch.php">перейти к звонкам</a></div>';
	    		 zv_body_flag(true);
	    	 }//if
	    	 else {
	    		 document.getElementById('zv_div').innerHTML='';
	    		 zv_body_flag(false);
	    	 }//else 
	    					 
			   }
	  		}
		}
}// end of zv_ch

zv_check();

var timer = setInterval(zv_check, 1000000);


</script>