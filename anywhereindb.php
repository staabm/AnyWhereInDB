<?php
/***********************************************************************
* @name  AnyWhereInDB
* @abstract This project is to find out a part of string from anywhere in database
* @version 0.33 
* @package anywhereindb
*
*
*
*
*************************************************************************/



/******************************************************
 *	In this version, We have redistrubted the fontend and Backend code
 *	PHP/BACKEND [DB_Auth, Table loading, Search ]
 *  JS/FONTEND [showing/hiding, auth, tables, marking tables, highligthing]
 * *********************************************************
 * Work flow
 * 	# login Screen
 * 	User # login (Select DB)
 * 	
 * 		# Logged in Using AJAX
 * 		# backend sending the Tables, tables_fields to the Fontend (js_controled!)
 * 		
 *	# Empty search Screen (JS) 
 *	User # Do Search
 *	# populate tables ((js_controled!)) Single Table at a time.
 *
 *
 * ********************************************************************
 * 
 *  
 *****************************************************/


//@Session Start
session_start();


/*
//Setup the user name password of the users 
// ----

$_SESSION['server'] = 'localhost';
$_SESSION['dbuser'] = 'goba_anydb';
$_SESSION['pass'] = '!1mango@home';
$_SESSION['dbname']= 'goba_anywhereindb';
$_SESSION['loggedin'] = TRUE;


*/


$tables_to_search = array(); // keep it empty if you want to search in all the tables.
// $tables_to_search = array('table_name_1','table_name_2','table_name_3');

$fields_names = array();
//@Configation Shared Data will be here
$conf_data = array(
					'loggedin'=>false,
					'db_error'=>'',
					'no_load_tables' => 100,
					'url' => $_SERVER['PHP_SELF']
);



//Backend!


// Listners on GET / POST
if(is_requested('logout')) {
		logout();
}
;

if(is_requested('search_text')) {
	// ?search_text=Bangladesh&pg=0
	global $conf_data, $tables_to_search;
		must_login();
		//echo $link;
		load_tables();
		$len = $pg = (empty($_REQUEST['pg']))?0:$_REQUEST['pg'];
		
		$len += $conf_data['no_load_tables'];
		
		$tb_size = sizeof($tables_to_search);
		
		if($tb_size < $len) {
			$len = $tb_size;
		}
		
		//pre($tables_to_search);
		$result = array();
		$json = '[';
		for($j=0, $i = $pg; $i < $len; $i += 1) {
		
			$result = search_on_table($i, $_REQUEST['search_text']);


			if(!empty($result)) {
				if($j !== 0) {
					$json .= ', ';
				}	

				$json .= '{
							"table_no":'. $i .', 
							"result":'. json_encode($result).',
							"search_text": "'.$_REQUEST['search_text'].'"
						}';
				$j += 1;	
			}			
		}
		$json .= ']';	
		echo $json;
		finish();
		
		
}

if(is_requested('server')) {
	//echo 'ool';
		login();
		finish();
}
if(is_requested('init')) {
	
	
	must_login();
	init_search();
	finish();
}

//END of listeners 


//@__main__

//check login !!
must_login(TRUE);




//@Functions
function is_requested($key) {
// is_set replacement function.. 
	if(!empty($_REQUEST[$key]) || $_REQUEST[$key] === '0') {
		return true;
	}
	return false;
}

function init_search() {
	$tables =  load_tables();
	$len = sizeof($tables);
	for($i = 0; $i < $len; $i += 1) {
		table_fields($i);
	}
	update_js();
}

//@startof: login system 
function must_login($first_time = FALSE) {
		
	global $conf_data;
		
	if ($_SESSION['loggedin'] !== TRUE) {
		
		header('HTTP/1.1 401 Unauthorized');
		$conf_data['loggedin'] = FALSE;
		//session_destroy();
		if(!$first_time) {
			update_js();
			exit();
		}

	} else {
		$conf_data['loggedin'] = TRUE;
		db_con_start();
	}
	init_js();
}


function login() {
	global $conf_data;
	
	
	// server, dbuser, pass, dbname
	if (!empty($_REQUEST['server'])
	    && !empty($_REQUEST['dbuser'])
		&& !empty($_REQUEST['pass'])
		&& !empty($_REQUEST['dbname'])
		&& empty($_SESSION['loggedin']) //funny thing it works false / not test both will send us the same restult
	   ) {
		
		$login = db_connect($_REQUEST['server'], $_REQUEST['dbuser'], $_REQUEST['pass'], $_REQUEST['dbname']);	
		if($login === TRUE) {
			$_SESSION['server'] = $_REQUEST['server'];
			$_SESSION['dbuser'] = $_REQUEST['dbuser'];
			$_SESSION['pass'] 	= $_REQUEST['pass'];
			$_SESSION['dbname'] = $_REQUEST['dbname'];
			$_SESSION['loggedin'] = TRUE;
			//$_REQUEST['server'], $_REQUEST['dbuser'], $_REQUEST['pass'], $_REQUEST['dbname']
			init_search();
		}else{
			header('HTTP/1.1 401 Unauthorized');
		}
	//server, dbuser, pass, dbname
		exit();
	}
	
	
	
}

function logout () {
	global $conf_data;
	$conf_data['loggedin'] = FALSE;
	
	session_destroy();
}

//@endof: login system 


//

//
function fetch_array($sql)
// @method    fetch_array
// @abstract taking the mySQL $resource id and fetch and return the result array
// @param   SQL 
// @return  array  
{
	$res = mysql_query($sql);
	$data = array();
	//echo 'sql: ', $sql, '<br>';
	//mysql_fetch_row()
	while ($row = mysql_fetch_row($res))
	{
		//pre($row);
		array_push($data, $row);
	}
	mysql_free_result($res);
	//pre($data);
	return $data;
} //@endof  function fetch_array

//
function load_tables () {
	global $tables_to_search;

	// if tables is not empty
	$dbname = $_SESSION['dbname'];
	if (empty($tables_to_search)) {

		if (!empty($_SESSION['tables_to_search'])) {
			// check session	
			$tables_to_search = $_SESSION['tables_to_search'];
		} else {	
			//@go for the tables 
			$sql= 'show tables';
			//@abstract  get all table information in row tables
			$tables = fetch_array($sql);
			//pre($tables);
			// process the table..
			for($i=0; $i < sizeof($tables); $i += 1) {
				// removing the empty tables from the list!
				$sql = 'select count(*) from '. $tables[$i][0];
				$number_rows = fetch_array($sql);
				if($number_rows[0][0] > 0) {
					array_push($tables_to_search, $tables[$i][0]);
				}
			}
			$_SESSION['tables_to_search'] = $tables_to_search ;
		}	
	}
	return $tables_to_search; 
}

function table_fields($tableno) {
	global $tables_to_search, $fields_names;
	$sql = 'desc ' . $tables_to_search[$tableno];
	
	$collum = fetch_array($sql);
	
	$data = array();
	
	for($i=0; $i < sizeof($collum); $i += 1) {
		array_push($data, $collum[$i][0]);
	}
	
	$fields_names[$tableno] = $data;
	
	//pre($fields_names);
	
	return $data;
	
}


function search_on_table($tableno, $search_text) {
	global $tables_to_search;
	
	
	
	// build the sql
	$search_sql = 'select * from '.$tables_to_search[$tableno].' where ';
	//pre($fields_names);
	$fields_on_this_table =  table_fields($tableno);
	$len = sizeof($fields_on_this_table);
	for($i=0; $i < $len; $i += 1) {
		if($i != 0) {
			$search_sql .= ' or ';
		}
		
		$search_sql .= '`' . $fields_on_this_table[$i] . '` like \'%' . $search_text . '%\' ';
		
	}
	
	
	//pre($data);
	return fetch_array($search_sql);
	// run SQL
	
	
	// build the data.. 

	
}




//DEBUG 
function pre($obj, $kill=false){
	echo '<pre>';
	print_r($obj);
	echo '</pre>';
	if($kill !== false) {
		die('<p class=error>'. $kill .' </p>');
	}
}




//select DBName

function db_con_start() {
	//echo $_SESSION['server'], $_SESSION['dbuser'], $_SESSION['pass'], $_SESSION['dbname'];
	return db_connect($_SESSION['server'], $_SESSION['dbuser'], $_SESSION['pass'], $_SESSION['dbname']);
}
// ConnectDB!
function db_connect ($server, $dbuser, $pass, $dbname) {
	global $conf_data, $link;
	//echo 'db_connect ', $server, $dbuser, $pass, $dbname, '<br />';
	$link = @mysql_connect($server, $dbuser, $pass);
	if (!$link) {
		$conf_data['db_error'] += 'Server, Database User Name,  Password Missmatch <br/>';
		return FALSE;
	}
	
	if(!mysql_select_db($dbname, $link)) {
		$conf_data['db_error'] += 'No Database found in the name of "'. $dbname.'" <br/>';
		return false;
	};
	echo $conf_data['db_error'];
	return TRUE;
}

function finish($nokill = FALSE) {
	global $link;
	// kill the mysql_link
	if (!empty($link)) {
		mysql_close($link);
	}
	//
	if ($nokill == FALSE) {
		die();
	}
}

function init_js() {
    global $conf_data, $JS_PHP;
	$JS_PHP = 'var conf_data = '. json_encode($conf_data).';';
	
}

function update_js() {
    global $conf_data, $tables_to_search, $fields_names;
	echo '{"conf_data": ', json_encode($conf_data),',
			"tables_to_search" : ', json_encode($tables_to_search),',
			"fields_names" : ', json_encode($fields_names),' }';
}


?>
<html><script><?php echo $JS_PHP;?></script>
<title>AnyWHereInDB :: Search anything from the DB </title>
<style>
#wlg{position:absolute; right: 10; top: 5;}
.m{min-height: 400px;}
h1{color:#233E99;font-size: 2em;font-weight:bold;}
h1,.title{text-shadow: #ccc 2px 2px 4px;}
.dn{display:none;}
#error{padding:10px; border-radius:10px;background-color:pink;color:red;}
#notice{padding:10px; border-radius:10px;background-color:#00B000;}
td,th{padding:5px;border-radius:2px;text-align:center;}

th{background-color:#ccf;padding:4px;}
tr:nth-child(odd){background-color:#eee;}
tr:nth-child(even){background-color:#f0fef0;}
a{text-decoration: none;}
.title{font-size:20px;font-weight:bold;}
table{clear:both; margin-bottom: 20px;}
.hg{background-color:yellow;padding:1px;border-radius:10px;}
.isql{font-size:small;}
.sql{background-color:#cfc;padding:10px;border-radius:10px;font:small italic;}
</style>
<body>
<div class=m>
	<h1>AnyWhereInDB</h1>
	<div id=notice class=dn > Notice: </div>
	<div id=error class=dn> Errors: </div>
	<div id=wlg> <a href="javascript:logout();">Disconnect/Change Database</a> </div>
	<div id=h1>
		
			<form id=login class=dn action="<?php echo $_SERVER['PHP_SELF'];?>" method="POST">
				<table>
						<tbody>
							<tr>
								<td><label for=server>Server Name </label></td>
								<td><input type=text name=server id=server value=localhost /></td>
							</tr>
							<tr>
								<td><label for=dbuser>User Name </label></td>
								<td><input type=text name=dbuser id=dbuser /></td>
							</tr>
							<tr>
								<td><label for=pass>Password </label></td>
								<td><input type=password name=pass id=pass  /></td>
							</tr>
							<tr>
								<td><label for=dbname>Database Name </label></td>
								<td><input type=text name=dbname id=dbname /></td>
							</tr>
							<tr>
								<td><input type=submit value="Login to your Database" /></td>
							</tr>
						</tbody>
					</table>
			</form>
			
			
	</div>
	
	<div id=wsearch>
		<form id=search action="<?php echo $_SERVER['PHP_SELF'];?>" method=POST>
				<input type=text id=search_text name=search_text <?php if(!empty($_POST['search_text'])) echo 'value="'.$_POST['search_text'].'"';  ?> />
				<input type=submit value=Search />		
		</form>
		<h4 id=re_text> </h4>
		<p id=result> </p>
		<div id=tbs> </div>
	</div>


</div>
<div>
<span  class="me">
"<a href="http://code.google.com/p/anywhereindb">AnyWhereInDB </a>" is a Open Source Project. 
<a href="https://twitter.com/intent/tweet?text=%23AnywhereInDB+">#AnyWhereInDB</a>
<a href="http://nafisahmad.com/">nafis</a>
	<br /> 


</span>
</div>
<script>
/*global window, console, conf_data */
"use strict";
(function(w){
    var dc = w.document, //lib
		attr = function (element, attrb, property) {
			if(typeof property !== 'undefined') {
				element.setAttribute(attrb, property);
			}
			return element.getAttribute(attrb);
		},
		el = function (tagname, classname, e_id) { //create new element by tagname

			var element = document.createElement(tagname);
			if(typeof classname !== 'undefined') {
				attr(element,'class', classname);
			}
			if(typeof e_id !== 'undefined') {
				attr(element,'id', e_id);
			}
			return element;
		},
        id = function (e) { //lib
            return dc.getElementById(e);
		},
		show = function (dom, not_show) { // 
			
			//console.log('show_loging!', conf_data.loggedin);
			not_show = (typeof not_show === 'undefined')?'block':'none';
			dom.style.display = not_show;
			
		},
		toggle = function(dom_element){
			if(dom_element.style.display === 'block') {
				dom_element.style.display = 'none';
				return 0;
			}
			dom_element.style.display = 'block';
			return 1;
		},
		val = function (key) {
			return id(key).value;
		},
		logout = function () {
			ajax("GET", conf_data.url, {logout:'do'},function () {
					show_login();
				});
			
			
		},
		show_search = function () {
			show(id('login'),'hide');
			show(id('wsearch'));
			show(id('wlg'));
		},
		show_login = function () {
			show(id('login'));
			show(id('wsearch'),'hide');
			show(id('wlg'),'hide');
		},
		ajax = function(method, url, data, callback) {
			
			var keys = Object.keys(data),
				//values = Object.value(data),
				len = keys.length, 
				st_data = '',
				i,
				xmlhttp = new XMLHttpRequest(),// assuming that the IE6-- user(s) is(are) dead!
				statuschange = function(){
					if(xmlhttp.readyState === 4 && xmlhttp.status === 200) {
						callback(xmlhttp.responseText);
					}
					console.info(xmlhttp.status);
					if(xmlhttp.status === 401) {
						console.log('not logged in!');
						show_login();
					}
				};
				xmlhttp.onreadystatechange = statuschange; 
				
			
			
				
				for (i = 0; i < len ;i+=1) {
			
					st_data += keys[i] + '=' + escape(data[keys[i]]) + "&";
					
				}
			
				//console.log(method, url);
				if(method === 'POST') {
					xmlhttp.open(method, url, true);
					xmlhttp.send(st_data);		
				}else{
					xmlhttp.open(method, url+'?'+st_data, true);
					xmlhttp.send();		
				}
				
				console.log(url+'?'+st_data);
			
			
		},
		text_processing = function(text){
			if (!text) return '';
			
			//
			var search_text_d =new RegExp('('+search_text+')', 'ig');
			// Remove_tags _! // Highlight the search_text
			text = text.replace(/>/g,"&gt;").replace(/</g,"&lt;").replace(search_text_d, "<span class=hg>\$1</span>");
			return text;
		},
		populate_table = function(result) {
			
			var len = result.length,
				i,
				fg = document.createDocumentFragment();
			function single_table(tableno, tb_result) {
				var div =  el('div', 'wd','d'+tableno),
				tb = el('table', 'tb', 't'+tableno),
				tr,
				td,
				a = el('a', 'title'),
				i, j,
				info = el('span', '');
				
				a.innerHTML = '<span id=ct'+ tableno +'>-</span>'+ tables_to_search[tableno] +' &nbsp;',
				a.href = 'javascript:tg('+tableno+');';
				div.appendChild(a);
				
				a = el('a','isql');
				a.innerHTML = 'SQL'
				a.href = 'javascript:show_sql('+tableno+');';
				info.innerHTML =  ' '+tb_result.length + ' rows ' ;
				info.appendChild(a);
				div.appendChild(info);
				div.appendChild(el('div', 'tb_h', 'hp'+tableno));
				tr = el('tr');
				for(j = 0; j<fields_names[tableno].length; j+=1) {
					td = el('th');
					td.innerHTML = fields_names[tableno][j];
					tr.appendChild(td);
				}
				tb.appendChild(tr);
				
				console.log(tb_result[0]);
				for(i = 0; i<tb_result.length; i+=1) {
					tr = el('tr');
					for(j = 0; j<tb_result[i].length; j+=1) {
						td = el('td');
						// lets highlight the question 
						td.innerHTML = text_processing(tb_result[i][j]);
						tr.appendChild(td);
					}
					tb.appendChild(tr);
				}
				
				tb.style.display = 'block';
				div.appendChild(tb);
				fg.appendChild(div);	
			}
			
			for(i=0; i < len; i+=1) {
				single_table(result[i].table_no, result[i].result);
			}
			//console.log('populate' );
			if(attr(id('tbs'),'data-last-item') !== search_text) {
				id('tbs').innerHTML ='';
				attr(id('tbs'),'data-last-item', search_text);
			}
			id('tbs').appendChild(fg);
			
		},
		toggle_table = function(table_no) {
			var plus = id('ct'+table_no);
			
			if(plus.innerHTML == '+') {
				plus.innerHTML = '-';

			}else{
				plus.innerHTML = '+';
			
			}
			toggle(id('t'+table_no));
//			console.log('hide table' + table_no);
		},
		show_sql = function(table_no) {
			var i,
				len =  fields_names[table_no].length,
				search_sql = 'select * from `'+ tables_to_search[table_no]+'` where ';
			for (i = 0; i < len; i+=1) {
				if (i !== 0) {
					search_sql += ' or ';
				}
				search_sql += ' `' + fields_names[table_no][i]+ '` like "%'+ search_text +'%" ';
			}
			id('hp'+table_no).innerHTML = '<div class=sql> ---Search SQL <br />' + search_sql + '</div>';
			
			toggle(id('hp'+table_no));
		},
		tables_to_search = [],
		fields_names = [],		 
		load_information = function (rs) {
		 // tables
		 // tables_fields
		 console.log(rs);
			var data = JSON.parse(rs);
			tables_to_search = data.tables_to_search;
			fields_names = data.fields_names;
			//console.log(data, tables_to_search, fields_names);
			console.log('field --', fields_names);
		},
		
		search_text = '';

	
	// show view login panel

	if (conf_data.db_error !== '') {
		id('error').innerHTML = conf_data.db_error;
		show(id('error'));
	}

	// Bind event listner ... 
	//__> element.addEventListener(evnent_type, fn, false);
	// asumming the the IE Users are not alive.. 
	id('login').addEventListener('submit', function(e) {
		var logininfo = {
				'server': val('server'),
				'dbuser': val('dbuser'),
				'pass': val('pass'),
				'dbname': val('dbname')
			};
		//console.log(logininfo);
		ajax("GET", conf_data.url, logininfo, function (rs) {
			console.log(rs);
			
			var data;
			
			
			if (rs !== '401') {
				// when you are logged in!
				show_search();
				load_information(rs);
			}else{
				show_login();
			}
			
		});
		e.preventDefault();
		//return false;
	}, false);	
	
	
	
	id('search').addEventListener('submit', function(e) {
		
		//console.log(logininfo);
		ajax("GET", conf_data.url, {'search_text': val('search_text')}, function (rs) {
			// console.log('result', rs);
			// console.log(tables_to_search, fields_names);
			var result = JSON.parse(rs), count = 0, i, j;
			console.log(result);
			search_text = val('search_text');
			
			if(result.length > 0) {
				
			
				for (i = result.length-1; i >= 0 ; i -= 1) {
					count += result[i].result.length;
				}
				populate_table(result);
				id('re_text').innerHTML = 'Showing search result for "' + search_text +'" found in ' + count + ' row(s). ';
			}else{
				id('re_text').innerHTML = 'No search result found for "' + search_text +'". ';
			}
			//console.log('result : ',result.length, result[0].result.length);
			
			
		});
		e.preventDefault();
		//return false;
	}, false);	
	
	
	// first login!
	if(conf_data.loggedin === false) {
		show_login();
		
	}else{
		
		ajax("GET", conf_data.url, {init:'do'}, function (rs) {
			load_information(rs);
			show_search();
			
			
		});
		
	}
	
	w.tg = toggle_table;
	w.show_sql = show_sql;
	w.logout = logout; //delete this


}(window))
    
    
</script>
</body>
</html><?php /* Yes, We keeped <?PHP open *///