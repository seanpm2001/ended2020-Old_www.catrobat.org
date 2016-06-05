<?php
if(empty($_SERVER["HTTPS"]) || $_SERVER["HTTPS"] !== "on") {
	header("Location: https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"]);
	exit();
}

session_start();

function backup_tables($mysqli, $fname) {
	//get all of the tables
	$tables = array();
	$result = $mysqli->query('SHOW TABLES');
	while($row = $result->fetch_row())
	{
		$tables[] = $row[0];
	}
	//cycle through
	$return = "";
	foreach($tables as $table)
	{
		$result = $mysqli->query('SELECT * FROM '.$table);
		$num_fields = $result->field_count;
		
		$return.= 'DROP TABLE '.$table.';';
		$result2 = $mysqli->query('SHOW CREATE TABLE '.$table);
		$row2 = $result2->fetch_row();
		$return.= "\n\n".$row2[1].";\n\n";
		
		for ($i = 0; $i < $num_fields; $i++) 
		{
			while($row = $result->fetch_row())
			{
				$return.= 'INSERT INTO '.$table.' VALUES(';
				for($j=0; $j < $num_fields; $j++) 
				{
					$row[$j] = addslashes($row[$j]);
					$row[$j] = ereg_replace("\n","\\n",$row[$j]);
					if (isset($row[$j])) { $return.= '"'.$row[$j].'"' ; } else { $return.= '""'; }
					if ($j < ($num_fields-1)) { $return.= ','; }
				}
				$return.= ");\n";
			}
		}
		$return.="\n\n\n";
	}
	
	//save file
	$handle = fopen($fname,'w+');
	fwrite($handle,$return);
	fclose($handle);
}

$errors = 0;
$err_message = "";
$message = "";

$header = "";
$date = date("Y-m-d");
$text = "";
$image = "";
$link = "";
$today = true;

$mysqli = @new mysqli("localhost", "catrobat", "catrobat0815");

if ($mysqli->connect_errno) {
		$errors++;
		$err_message .= $mysqli->connect_error . "<br />";
} else {
	$mysqli->set_charset("utf8");
	$query = "CREATE DATABASE IF NOT EXISTS `catrobat`;
USE `catrobat`;
CREATE TABLE IF NOT EXISTS `news` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `headline` TEXT NOT NULL,
	`date` DATETIME,
  `text` TEXT NOT NULL,
  `image` TEXT,
  `link` TEXT,
  PRIMARY KEY (`id`)) DEFAULT CHARSET=utf8;
CREATE TABLE IF NOT EXISTS `credits` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `name_UNIQUE` (`name` ASC)) DEFAULT CHARSET=utf8;";
	if (!$mysqli->multi_query($query)) {
		$errors++;
		$err_message .= $mysqli->error . "<br />";
	}
	do {
		$mysqli->store_result();
	} while ($mysqli->next_result());
}

$backups = array();
$dir = opendir("backups/");
while (($file = readdir($dir)) !== false) {
	if (preg_match("/^([0-9]{4})-([0-9]{2})-([0-9]{2})-([0-9]{2})-([0-9]{2})-([0-9]{2})\.sql$/", $file, $matches)) {
		$backups[] = array("file" => $file, "name" => $matches[1]."-".$matches[2]."-".$matches[3]." ".$matches[4].":".$matches[5].":".$matches[6]);
	}
}
closedir($dir);
$backups = array_reverse($backups);

if (isset($_POST["a"]) && !$errors) {
	if ($_POST["a"] == "login") {
		session_regenerate_id();
		$_SESSION["logged_in"] = $_POST["login_password"] == "abcd" && $_POST["login_user"] == "admin";
		if ($_SESSION["logged_in"]) {
			// Do database backup on login
			$fname = "backups/" . date("Y-m-d-H-m-s") . ".sql";
			backup_tables($mysqli, $fname);
			// Check diff between latest backup
			if (count($backups) && md5_file($fname) == md5_file("backups/" . $backups[0]["file"])
			&& filesize($fname) == filesize("backups/" . $backups[0]["file"])) {
				unlink($fname);
			}
			header("Location: http://" . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF']);
		} else {
			$errors++;
			$err_message .= "Invalid Login!<br />";
		}
	} else if (isset($_SESSION["logged_in"]) && $_SESSION["logged_in"]) {
		if ($_POST["a"] == "logout") {
			$_SESSION = array();
			session_destroy();
			header("Location: http://" . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF']);
		} else if ($_POST["a"] == "addnews") {
			$header = $mysqli->real_escape_string(trim(strip_tags($_POST["header"])));
			$date = $mysqli->real_escape_string(trim($_POST["date"]));
			$text = $mysqli->real_escape_string(trim($_POST["text"]));
			$image = $mysqli->real_escape_string(isset($_FILES["image"]["name"]) ? $_FILES["image"]["name"] : "");
			$link = $mysqli->real_escape_string(trim(strip_tags($_POST["link"])));
			if ($header == "") {
				$errors++;
				$err_message .= "Headline must not be empty!<br />";
			}
			if (!preg_match("/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/", $date)) {
				$errors++;
				$err_message .= "Invalid date format! Use <em>yyyy-mm-dd</em>!<br />";
			}
			if ($text == "") {
				$errors++;
				$err_message .= "Text must not be empty!<br />";
			}
			if (!isset($_FILES["image"]) || !is_uploaded_file($_FILES["image"]["tmp_name"])) {
				$errors++;
				if (!isset($_FILES["image"])) {
					$err_message .= "Image must not be empty!<br />";
				} else switch ($_FILES["image"]["error"]) {
					case 4:
					$err_message .= "Image must not be empty!<br />";
					break;
					
					case 1:
					case 2:
					$err_message .= "Image too big!<br />";
					break;
					
					default:
					$err_message .= "Image upload error: " . $_FILES["image"]["error"] . "<br />";
					break;
				}
			}
			if (!preg_match("/(ftp|http[s]?):\/\/(www.)?[^\.]+[\.]?[\.][a-z0-9]/i", $link) && $link != "") {
				$errors++;
				$err_message .= "\"" . $link . "\" is not a valid link!<br />";
			}
			if (!$errors) {
				if ($mysqli->query("INSERT INTO `news` (`headline`, `date`, `text`, `image`, `link`) VALUES ('$header', '$date 00:00:00', '$text', '$image', '$link');")) {
					move_uploaded_file($_FILES["image"]["tmp_name"], "../img/news/" .$image);
					$message .= "News post \"" . $header . "\" successfully added!<br />";
				} else {
					$errors++;
					$err_message .= $mysqli->error . "<br />";
				}
				$mysqli->store_result();
			}
			
			echo("ERRORS: " . $errors . "<br />" . $err_message . $message);
			$news = array();
			if ($result = $mysqli->query("SELECT * FROM `news` ORDER BY date DESC")) {
				while ($data = $result->fetch_array()) {
					$news[] = $data;
				}
			}
			die(json_encode($news));
			
		} else if ($_POST["a"] == "editnews") {
			if ($_POST["delete"] == 0) {
				$id = $_POST["select"];
				$header = $mysqli->real_escape_string(trim(strip_tags($_POST["header"])));
				$date = $mysqli->real_escape_string(trim($_POST["date"]));
				$text = $mysqli->real_escape_string(trim($_POST["text"]));
				$image = $mysqli->real_escape_string(isset($_FILES["image"]["name"]) ? $_FILES["image"]["name"] : "");
				$link = $mysqli->real_escape_string(trim(strip_tags($_POST["link"])));
				if ($header == "") {
					$errors++;
					$err_message .= "Headline must not be empty!<br />";
				}
				if (!preg_match("/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/", $date)) {
					$errors++;
					$err_message .= "Invalid date format! Use <em>yyyy-mm-dd</em>!<br />";
				}
				if ($text == "") {
					$errors++;
					$err_message .= "Text must not be empty!<br />";
				}
				if (isset($_FILES["image"]) && !is_uploaded_file($_FILES["image"]["tmp_name"])) {
					switch ($_FILES["image"]["error"]) {
						case 4:
						break;
						
						case 1:
						case 2:
						$errors++;
						$err_message .= "Image too big!<br />";
						break;
						
						default:
						$errors++;
						$err_message .= "Image upload error: " . $_FILES["image"]["error"] . "<br />";
						break;
					}
				}
				if (!preg_match("/(ftp|http[s]?):\/\/(www.)?[^\.]+[\.]?[\.][a-z0-9]/i", $link) && $link != "") {
					$errors++;
					$err_message .= "\"" . $link . "\" is not a valid link!<br />";
				}
				if (!$errors) {
					if ($mysqli->query("UPDATE `catrobat`.`news` SET `headline`='$header', `date`='$date 00:00:00', `text`='$text', " . (isset($_FILES["image"]) && is_uploaded_file($_FILES["image"]["tmp_name"]) ? "`image`='$image'," : "") . "`link`='$link' WHERE `id`='$id';")) {
						$message .= "News post \"" . $header . "\" successfully edited!<br />";
						if (isset($_FILES["image"]) && is_uploaded_file($_FILES["image"]["tmp_name"])) {
							move_uploaded_file($_FILES["image"]["tmp_name"], "../img/news/" .$image);
						}
					} else {
						$errors++;
						$err_message .= $mysqli->error . "<br />";
					}
					$mysqli->store_result();
				}
			} else if ($_POST["delete"] == 1) {
				$id = $mysqli->real_escape_string($_POST["select"]);
				$header = $mysqli->real_escape_string(trim($_POST["header"]));
				if ($mysqli->query("DELETE FROM `catrobat`.`news` WHERE `id`='$id';")) {
					$message .= "News post \"" . $header . "\" deleted!<br />";
				} else {
					$errors++;
					$err_message .= $mysqli->error . "<br />";
				}
				$mysqli->store_result();
			}
			
			echo("ERRORS: " . $errors . "<br />" . $err_message . $message);
			$news = array();
			if ($result = $mysqli->query("SELECT * FROM `news` ORDER BY date DESC")) {
				while ($data = $result->fetch_array()) {
					$news[] = $data;
				}
			}
			die(json_encode($news));
			
		} else if ($_POST["a"] == "backups") {
			$file = $_POST["select"];
			if (file_exists("backups/" . $file)) {
				if (!$mysqli->multi_query(file_get_contents("backups/" . $file))) {
					$errors++;
					$err_message .= $mysqli->error . "<br />";
				} else {
					$message .= "Restored database from backup file \"" . $file . "\"!<br />";
				}
				do {
					$mysqli->store_result();
				} while ($mysqli->next_result());
			} else {
				$errors++;
				$err_message .= "Backup file \"" . $file . "\" does not exist!<br />";
			}
			
			echo("ERRORS: " . $errors . "<br />" . $err_message . $message);
			$news = array();
			if ($result = $mysqli->query("SELECT * FROM `news` ORDER BY date DESC")) {
				while ($data = $result->fetch_array()) {
					$news[] = $data;
				}
			}
			echo(json_encode($news));
			echo("<br />");
			$credits = array();
			if ($result = $mysqli->query("SELECT * FROM `credits`")) {
				while ($data = $result->fetch_array()) {
					$credits[] = $data;
				}
			}
			die(json_encode($credits));
			
		} else if ($_POST["a"] == "addcredits") {
			$name = $mysqli->real_escape_string(trim(strip_tags($_POST["name"])));
			if ($name == "") {
				$errors++;
				$err_message .= "Name must not be empty!<br />";
			}
			if (!$errors) {
				if ($mysqli->query("INSERT INTO `credits` (`name`) VALUES ('$name');")) {
					$message .= "\"" . $name . "\" successfully added to the credits!<br />";
				} else {
					$errors++;
					$err_message .= $mysqli->error . "<br />";
				}
				$mysqli->store_result();
			}
			
			echo("ERRORS: " . $errors . "<br />" . $err_message . $message);
			$credits = array();
			if ($result = $mysqli->query("SELECT * FROM `credits`")) {
				while ($data = $result->fetch_array()) {
					$credits[] = $data;
				}
			}
			die(json_encode($credits));
			
		} else if ($_POST["a"] == "deletecredits") {
			$id = htmlspecialchars(trim(strip_tags($_POST["id"])));
			$name = htmlspecialchars(trim(strip_tags($_POST["name"])));

			if ($mysqli->query("DELETE FROM `catrobat`.`credits` WHERE `id`='$id';")) {
				$message .= "\"" . $name . "\" was removed from the credits!<br />";
			} else {
				$errors++;
				$err_message .= $mysqli->error . "<br />";
			}
			$mysqli->store_result();
			
			echo("ERRORS: " . $errors . "<br />" . $err_message . $message);
			$credits = array();
			if ($result = $mysqli->query("SELECT * FROM `credits`")) {
				while ($data = $result->fetch_array()) {
					$credits[] = $data;
				}
			}
			die(json_encode($credits));
		}
	}
}

$logged_in = isset($_SESSION["logged_in"]) ? $_SESSION["logged_in"] : false;

if ($logged_in) {
	$news = array();
	$credits = array();
	
	if ($result = $mysqli->query("SELECT * FROM `news` ORDER BY date DESC")) {
		while ($data = $result->fetch_array()) {
			$news[] = $data;
		}
	}
	
	if ($result = $mysqli->query("SELECT * FROM `credits`")) {
		while ($data = $result->fetch_array()) {
			$credits[] = $data;
		}
	}
}

if (!$mysqli->connect_errno) {
	$mysqli->close();
}
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Catrobat Admin</title>
<link rel="stylesheet" type="text/css" href="main.css" />
<link rel="shortcut icon" href="../img/favicon.ico" /> 

<script type="text/javascript">
news = {};
credits = {};

function setup() {
	updateNews(<?=json_encode($news)?>);
	updateCredits(<?=json_encode($credits)?>);
}

function sendRequest(params, callback, loadingmsg)
{
  if (window.XMLHttpRequest)
    request = new XMLHttpRequest(); // Mozilla, Safari, Opera
  else if (window.ActiveXObject) {
    try {
      request = new ActiveXObject('Msxml2.XMLHTTP'); // IE 5
    } catch (e) {
      try {
        request = new ActiveXObject('Microsoft.XMLHTTP'); // IE 6
      } catch (e) {}
    }
  }
  request.open("POST", "index.php", true);
  request.onreadystatechange = function () {
    if (request.readyState == 4) {
			dismiss();
			document.getElementById("loading").style.display = "none";
			var errnum = request.responseText.match(/ERRORS: ([0-9]+)<br \/>/)[1];
			
			var response = request.responseText.substring(request.responseText.indexOf("<br />") + 6);
			var message = "";
			for (var i = 0; i < errnum; i++) {
				message += response.substring(0, response.indexOf("<br />") + 6);
				response = response.substring(response.indexOf("<br />") + 6);
			}
			if (errnum == 0) {
				message = response.substring(0, response.indexOf("<br />") + 6);
				response = response.substring(response.indexOf("<br />") + 6);
			}

			if (errnum > 0) {
				var obj = document.getElementById("errors");
				obj.innerHTML = message;
				obj.style.opacity = "1";
			} else {
				var obj = document.getElementById("success");
				obj.innerHTML = message;
				obj.style.opacity = "1";
				callback(response);
			}
    }
  }
  request.send(params);
	document.getElementById("loading").getElementsByTagName("div")[0].getElementsByTagName("span")[0].innerHTML = (loadingmsg != undefined) ? loadingmsg :"Loading...";
	document.getElementById("loading").style.display = "flex";
}

function updateNews(obj) {
	news = obj;
	var sel = document.getElementById("news_edit_select");
	while (sel.options.length > 0) {
		sel.remove(0);
	}
	for (var i = 0; i < news.length; i++) {
		var option = document.createElement("option");
		option.text = news[i].headline;
		option.value = news[i].id;
		sel.add(option);
	}
	var header = document.getElementById("news_edit_header");
	var date = document.getElementById("news_edit_date");
	var text = document.getElementById("news_edit_text");
	var url = document.getElementById("news_edit_link");
	header.value = news[sel.selectedIndex].headline;
	date.value = news[sel.selectedIndex].date.match(/([0-9]+-[0-9]+-[0-9]+)/)[1];
	text.value = news[sel.selectedIndex].text;
	url.value = news[sel.selectedIndex].link;
}

function updateCredits(obj) {
	credits = obj;
	var cont = document.getElementById("credits-container");
	while (cont.hasChildNodes()) {
		cont.removeChild(cont.firstChild);
	}
	for (var i = 0; i < credits.length; i++) {
		var node = document.createElement("div");
		node.className = "credits";
		node.innerHTML = credits[i].name + " <a href=\"javascript:deleteCredits(" + credits[i].id + ", '" + credits[i].name + "');\">[X]</a>";
		cont.appendChild(node);
	}
	filterCredits();
}

function addNews() {
	var data = new FormData();
	var header = document.getElementById("news_add_header");
	var date = document.getElementById("news_add_date");
	var text = document.getElementById("news_add_text");
	var image = document.getElementById("news_add_image");
	var url = document.getElementById("news_add_link");
	data.append("a", "addnews");
	data.append("header", header.value);
	data.append("date", date.value);
	data.append("text", text.value);
	data.append("image", image.files[0]);
	data.append("link", url.value);
	sendRequest(data, function(objstring) {
		updateNews(JSON.parse(objstring));
		header.value = "";
		var d = new Date();
		date.value = d.getFullYear() + "-" + (d.getMonth() < 9 ? "0" : "") + (d.getMonth() + 1) + "-" + (d.getDate() < 10 ? "0" : "") + d.getDate();
		text.value = "";
    var newInput = document.createElement("input"); 
    newInput.type = image.type; 
    newInput.id = image.id; 
    newInput.name = image.name; 
    newInput.className = image.className; 
		newInput.accep = image.accept;
		image.parentNode.replaceChild(newInput, image);
		url.value = "";
	});
}

function editNews(del) {
	var data = new FormData();
	var sel = document.getElementById("news_edit_select");
	var header = document.getElementById("news_edit_header");
	var date = document.getElementById("news_edit_date");
	var text = document.getElementById("news_edit_text");
	var image = document.getElementById("news_edit_image");
	var url = document.getElementById("news_edit_link");
	var edit = document.getElementById("news_edit");
	data.append("a", "editnews");
	data.append("select", sel.options[sel.selectedIndex].value);
	data.append("header", header.value);
	data.append("date", date.value);
	data.append("text", text.value);
	data.append("image", image.files[0]);
	data.append("link", url.value);
	data.append("delete", del ? 1 : 0);
	if (del && confirm("Do you really want to delete the article \"" + header.value + "\"?")) {
		sendRequest(data, function(objstring){
			updateNews(JSON.parse(objstring));
		});
	} else if (!del) {
		sendRequest(data, function(){});
	}
}

function addCredits() {
	var data = new FormData();
	var name = document.getElementById("credits_name")
	data.append("a", "addcredits");
	data.append("name", name.value);
	sendRequest(data, function(objstring){
		updateCredits(JSON.parse(objstring));
	});
}

function deleteCredits(id, name) {
	if (confirm("Are you sure you wish to remove \"" + name + "\" from the credits?")) {
		var data = new FormData();
		data.append("a", "deletecredits");
		data.append("id", id);
		data.append("name", name);
		sendRequest(data, function(objstring){
			updateCredits(JSON.parse(objstring));
		});
	}
}

function restoreBackup() {
	var data = new FormData();
	var sel = document.getElementById("backup_select");
	data.append("a", "backups");
	data.append("select", sel.options[sel.selectedIndex].value);
	sendRequest(data, function(objstring){
		//alert(objstring.substring(0, objstring.indexOf("<br />")));
		updateNews(JSON.parse(objstring.substring(0, objstring.indexOf("<br />"))));
		//alert(objstring.substring(objstring.indexOf("<br />") + 6));
		updateCredits(JSON.parse(objstring.substring(objstring.indexOf("<br />") + 6)));
	}, "Restoring database, this may take a while...");
}

function updateEditForm() {
	var sel = document.getElementById("news_edit_select");
	var header = document.getElementById("news_edit_header");
	var date = document.getElementById("news_edit_date");
	var text = document.getElementById("news_edit_text");
	var url = document.getElementById("news_edit_link");
	header.value = news[sel.selectedIndex].headline;
	date.value = news[sel.selectedIndex].date.match(/([0-9]+-[0-9]+-[0-9]+)/)[1];
	text.value = news[sel.selectedIndex].text;
	url.value = news[sel.selectedIndex].link;
}

function changeDate() {
	var today = document.getElementById("news_add_date_today").checked;
	var date = document.getElementById("news_add_date");
	if (today) {
		var d = new Date();
		date.value = d.getFullYear() + "-" + (d.getMonth() < 9 ? "0" : "") + (d.getMonth() + 1) + "-" + (d.getDate() < 10 ? "0" : "") + d.getDate();
	}
	date.disabled = today;
}

function dismiss() {
	document.getElementById("success").style.opacity = "0";
	document.getElementById("errors").style.opacity = "0";
}

function filterCredits() {
	var str = document.getElementById("credits_filter").value.toLowerCase();
	var credits = document.getElementsByClassName("credits");
	for (var i = 0; i < credits.length; i++) {
		if (credits[i].innerHTML.toLowerCase().includes(str)) {
			credits[i].style.display = "inline-block";
		} else {
			credits[i].style.display = "none";
		}
	}
}
</script>
</head>

<body onload="javascript:setup()">

<div id="errors" onclick="dismiss()">
</div>

<div id="success" onclick="dismiss()">
</div>

<div id="loading">
	<div>
		<img src="spinner.gif" /><br /><span>Loading...</span>
  </div>
</div>

<?php

if ($logged_in == true) { ?>
<div id="logininfo">
<form action="" method="post" enctype="multipart/form-data" name="logout" id="logout">
<input name="a" type="hidden" value="logout" />
Logged in as <b>admin</b>&nbsp;&nbsp;|&nbsp;&nbsp;<a href="#" onclick="document.getElementById('logout').submit();">Logout</a>
</form>
</div>
<div class="expandable" id="1">
<h1>Add news</h1>
  <table>
    <tr>
      <td><label for="news_add_header">Headline</label></td>
      <td><input name="news_add_header" type="text" class="textbox" id="news_add_header" value="<?=$header?>" size="100" /></td>
    </tr>
    <tr>
      <td><label for="news_add_date">Date</label></td>
      <td><input name="news_add_date" type="text"  <?=($today ? "disabled=\"disabled\"" : "")?> class="textbox" id="news_add_date" value="<?=$date?>" />
        <input name="news_add_date_today" type="checkbox" id="news_add_date_today" onclick="javascript:changeDate()" <?=($today ? "checked=\"checked\"" : "")?> />
        <label for="news_add_date_today">Today</label></td>
    </tr>
    <tr>
      <td><label for="news_add_text">Text (HTML)</label></td>
      <td><textarea name="news_add_text" cols="100" rows="10" class="textbox" id="news_add_text"></textarea></td>
    </tr>
    <tr>
      <td><label for="news_add_image">Image (&lt; 1mb)<br />
      </label></td>
      <td><input type="hidden" name="MAX_FILE_SIZE" value="1048576" />
      <input type="file" name="news_add_image" id="news_add_image" accept=".jpeg,.jpg,.png" /></td>
    </tr>
    <tr>
      <td><label for="news_add_link">Link (optional)</label></td>
      <td><input name="news_add_link" class="textbox" id="news_add_link" value="" size="100" /></td>
    </tr>
    <tr>
      <td>&nbsp;</td>
      <td>&nbsp;</td>
    </tr>
    <tr>
      <td>&nbsp;</td>
      <td><input type="submit" name="news_add" id="news_add" value="Submit" onclick="addNews()"/></td>
    </tr>
  </table>
</div>
<div class="expandable" id="2">
<h1>Edit existing news</h1>
  <table>
    <tr>
      <td>&nbsp;</td>
      <td><select name="news_edit_select" class="textbox" id="news_edit_select" onchange="javascript:updateEditForm()">
      </select></td>
    </tr>
    <tr>
      <td><label for="news_edit_header">Headline</label></td>
      <td><input name="news_edit_header" type="text" class="textbox" id="news_edit_header" size="100" /></td>
    </tr>
    <tr>
      <td><label for="news_edit_date">Date</label></td>
      <td><input name="news_edit_date" type="text" class="textbox" id="news_edit_date"></td>
    </tr>
    <tr>
      <td><label for="news_edit_text">Text (HTML)</label></td>
      <td><textarea name="news_edit_text" cols="100" rows="10" class="textbox" id="news_edit_text"></textarea></td>
    </tr>
    <tr>
      <td><label for="news_edit_image">Image (&lt; 1mb)</label></td>
      <td><input type="hidden" name="MAX_FILE_SIZE" value="1048576" />
      <input type="file" name="news_edit_image" id="news_edit_image" accept=".jpeg,.jpg,.png" />
      (If selected, replaces previous image)</td>
    </tr>
    <tr>
      <td><label for="news_edit_link">Link (optional)</label></td>
      <td><input name="news_edit_link" class="textbox" id="news_edit_link" size="100" /></td>
    </tr>
    <tr>
      <td>&nbsp;</td>
      <td>&nbsp;</td>
    </tr>
    <tr>
      <td>&nbsp;</td>
      <td><input type="submit" name="news_edit" id="news_edit" value="Apply" onclick="editNews(false)" />&nbsp;<input type="submit" name="news_edit" id="news_edit" value="Delete" onclick="editNews(true)" /></td>
    </tr>
  </table>
</div>
<div class="expandable" id="3">
<h1>Credits</h1>
	<table>
    <tr>
      <td><label for="credits_name">Name</label></td>
      <td><input name="credits_name" type="text" class="textbox" id="credits_name" size="100" /></td>
    </tr>
    <tr>
      <td>&nbsp;</td>
      <td>&nbsp;</td>
    </tr>
    <tr>
      <td>&nbsp;</td>
      <td><input type="submit" name="credits_add" id="credits_add" value="Submit" onclick="addCredits()" /></td>
    </tr>
    <tr>
      <td>&nbsp;</td>
      <td>&nbsp;</td>
    </tr>
    <tr>
      <td><label for="credits_filter">Filter</label></td>
      <td><input name="credits_filter" id="credits_filter" type="text" class="textbox" onkeyup="filterCredits()"/></td>
    </tr>
    <tr>
      <td>&nbsp;</td>
      <td><div id="credits-container"></div></td>
    </tr>
  </table>
<br />
</div>
<div class="expandable" id="4">
<h1>Backups</h1>
	<input name="a" type="hidden" value="backups" />
  <select name="backup_select" class="textbox" id="backup_select">
  <?php
  foreach ($backups as $b) {
    echo("<option value=\"" . $b["file"] . "\">" . $b["name"] . "</option>");
  }
  ?>
  </select>
  <input type="submit" name="backup_restore" id="backup_restore" value="Restore" onclick="restoreBackup()"/>
<br />
<br />
</div>
<br />&nbsp;<br />
<?php } else { ?>
<div id="logininfo">Not logged in!</div>
<div class="expandable" id="3">
<h1>Login</h1>
<form id="login" name="login" method="post" action="">
	<input name="a" type="hidden" value="login" />
  <input name="login_user" type="text" class="textbox" id="login_user" placeholder="Username" />
  <input name="login_password" type="password" class="textbox" id="login_password" placeholder="Password" />
  <input type="submit" name="login_submit" id="login_submit" value="Login" />
</form>
<br />
</div>
<?php } ?>
</body>
</html>