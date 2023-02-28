<?php
# render-text.php
# Renders text in the Kindergarten-typeface. Images get concatenated via
# imgmagick. Variants are chosen based on a file $FONT.var and kerning is
# looked up in $FONT.krn.
header("Content-Type: text/html; charset=utf-8");

function getTime()
    {
    $a = explode (' ',microtime());
    return(double) $a[0] + $a[1];
    }
$t1 = getTime();

if ($_POST['dark'] == "on") {
	$dark = 1;
} else {
	$dark = 0;
}

$fdir = "fonts";
#$font = "test_small_PS";
$font = "release1_med";
#$font = "numbers";
#$font = "release1";
$kernadd = 10;
$debug = false;
#$debug = true;
$maxchars = 40;
$fontheight = 250;
#$demo = false;
$demo = true; # demo mode makes text appear randomly
              # as well as displaying the url and not the about
$useMySQL = false; # If set to true you need to convert the kerning tables to SQL first
$showTime = true;

function echod($text){
	global $debug;
	if($debug) echo "<div>$text</div>";
}

function uniord($u) {
    $k = mb_convert_encoding($u, 'UCS-2LE', 'UTF-8');
    $k1 = ord(substr($k, 0, 1));
    $k2 = ord(substr($k, 1, 1));
    return $k2 * 256 + $k1;
}

function str_split_unicode($str, $l = 0) {
    if ($l > 0) {
        $ret = array();
        $len = mb_strlen($str, "UTF-8");
        for ($i = 0; $i < $len; $i += $l) {
            $ret[] = mb_substr($str, $i, $l, "UTF-8");
        }
        return $ret;
    }
    return preg_split("//u", $str, -1, PREG_SPLIT_NO_EMPTY);
}

function getVariants($char, $vars) {
	switch ($char) {
		case '?':
			$char = '\?';
			break;
	}
	$pattern = "/.*$char ([0-9]*).*/";
	foreach ($vars as $i => $line) {
		if (preg_match($pattern, $vars[$i])) {
			$var = preg_replace($pattern, '$1', $vars[$i], 1);
			break;
		}
	}
	return (int)$var[0];
}

function getKerning($char, $var, $charL, $varL, $kerns) {
	$pattern = "/.*$charL$varL $char$var ([-0-9])*.*/";
	foreach ($kerns as $i => $line) {
		# CONTINUE STUFF HERE!!!!
		if (preg_match($pattern, $kerns[$i])) {
			$pattern = "/$charL$varL $char$var ([-0-9]*)/";
			$kerning = preg_replace($pattern, '$1', $kerns[$i]);
			echod("$charL$varL>$char$var: ".$kerning." (".$kerns[$i].")");
			break;
		}
	}
	#return "$charL$varL>$char$var: ".$kerning;
	return $kerning;
}

function getKerningMySQL($char1, $var1, $char2, $var2, $fontname) {
	// mysqli
	echod("GETTIN KERNIN VIA MYSQLIN!");
	$mysqli = new mysqli("localhost", "CHANGEME!!!USERNAME", "CHANGEME!!!PASSWORD", "CHANGEME!!!DATABASE");
	$query = "SELECT `kerning`
								FROM `kerning_$fontname`
								WHERE char1='$char1'
								AND    var1='$var1'
								AND   char2='$char2'
								AND    var2='$var2'
								LIMIT 1";
	echod($query);
	$result = $mysqli->query($query);
	$row = $result->fetch_assoc();
	echo htmlentities($row['_message']);
	return $row['kerning'];
}

function getFileName($ascii, $var) {
	global $fdir;
	global $font;
	$path = "$fdir/$font/$ascii-$var.png";
	return $path;
}

function computeText($text) {
	global $fdir;
	global $font;
	global $useMySQL;
	echod("Rendering '$text' with the '$font' font...");
	echod("text: $text | utf8-encode: ".utf8_encode($text)." | utf8-decode: ".utf8_decode($text)."");
	$text = str_split_unicode($text);
	
	$kernpath = "$fdir/$font.krn";
	$varpath = "$fdir/$font.var";
	$kernfile = file($kernpath);
	$varfile = file($varpath);
	
	$r = array();
	
	for($i=0; $i<count($text); $i++) {
		$charL = $char;
		$char = $text[$i];
		$ascii = uniord($text[$i]);
		$vars = getVariants($char, $varfile);
		$varL = $var;
		$var = rand(0,$vars);
		
		if ($i > 0)
			if ($useMySQL)
				$kerning = getKerningMySQL($charL, $varL, $char, $var, $font);
			else
				$kerning = getKerning($char, $var, $charL, $varL, $kernfile);
		
		#$r[] = array($char, $ascii, $var, $kerning);
		
		# DEBUG
		$r[] = array($char, $ascii, $var, $kerning, "$charL$varL > $char$var");
		echod("$charL$varL > $char$var = $kerning | $ascii");
		#echod(getFileName($ascii,$var));
		#echod("&nbsp;");
		#echo "<img src=\"".getFileName($ascii,$var)."\" alt=\"$char\" />";
	}
	return $r;
}

function randomColor() {
	$c = array(
			array(27, 211, 13), # green
			array(160, 219, 13), # light green
			array(248, 191, 5), # yellow
			array(242, 112, 15), # orange
			array(211, 13, 13), # red
			array(225, 50, 198), # pink
			array(102, 15, 242), # purple
			array(13, 84, 211), # blue
			array(13, 219, 219) # light blue
		);
	$r = rand(0,count($c)-1);
	return $c[$r];

}


function randomHue()
{
	$d = 24;
	$h = rand(0,255/$d)*$d;
	$s = 240;
	$v = 210;
	return hsv2rgb($h,$s,$v);
}

function hsv2rgb($h, $s, $v)
{
	$s /= 256.0;
	if ($s == 0.0) return array($v,$v,$v);
	$h /= (256.0 / 6.0);
	$i = floor($h);
	$f = $h - $i;
	$p = (integer)($v * (1.0 - $s));
	$q = (integer)($v * (1.0 - $s * $f));
	$t = (integer)($v * (1.0 - $s * (1.0 - $f)));
	switch($i) {
	case 0: return array($v,$t,$p);
	case 1: return array($q,$v,$p);
	case 2: return array($p,$v,$t);
	case 3: return array($p,$q,$v);
	case 4: return array($t,$p,$v);
	default: return array($v,$p,$q);
	}
}

function renderTextImg($arr, $colors=0) {
	global $kernadd;
	global $fontheight;
	global $dark;
	$file = "renders/output.png";
	$cmd = "";
	$x = 0;
	$y = $fontheight;
	if ($dark > 0)
		$bg = "black";
	else
		$bg = "white";
	
	for($i=0; $i<count($arr); $i++) {
		# random color (without being the same than the last time)
		$old_r = $r;
		$old_g = $g;
		$old_b = $b;
		#list($r,$g,$b) = randomHue();
		list($r,$g,$b) = randomColor();
		while ($r == $old_r && $g == $old_g && $b == $old_b ):
			#list($r,$g,$b) = randomHue();
			list($r,$g,$b) = randomColor();
		endwhile;
		echod("$r,$g,$b");
		# other phat stuff
		$char = $arr[$i][0];
		$ascii = $arr[$i][1];
		$var = $arr[$i][2];
		$kerning = $arr[$i][3];
		$image = getFileName($ascii,$var);
		echod("geometry: $xstr ($kerning + $w + $kernadd)");
		echod("<a href=\"$image\">$image</a>: $w/$h");
		if ($x >= 0) $x += $kerning + $w + $kernadd;
		if ($x >= 0) $xstr = "+".$x;
		else $xstr = (string)$x;
		#if ($colors==2) $cmd .= " \( $image -geometry $xstr+0 -level-colors 'rgb($r,$g,$b)' \) -composite "; # with kerning
		/*else*/ if ($colors==1)				$cmd .= " \( $image -geometry $xstr+0 +level-colors 'rgb($r,$g,$b)',$bg \) -composite "; # with kerning
		else if ($colors==0 && $dark==1)		$cmd .= " \( $image -geometry $xstr+0 +level-colors white,black \) -composite "; # with kerning
		else									$cmd .= " $image -geometry $xstr+0 -composite "; # with kerning
		#$cmd .= " $image -geometry $xstr+0 -composite "; # with kerning
		#$cmd .= " $image -geometry $xstr+0 +level-colors 'rgb($r,$g,$b)' -composite "; # with kerning

		list($w,$h) = getimagesize($image);
		
		# DEBUG
		#echod("$char ($ascii) @ $var/$vars = $kerning");
	}
	
	$x += $w+$kernadd;
	echod("x: $x | w: $w | kernadd: $kernadd");
	$cmd = "convert -gravity West -size ".$x."x$y xc:none $cmd $file";
	#echod($cmd);
	echod($cmd);
	$exec = exec($cmd, $out, $ret);
	if ($ret == 0)
		echo "<div><img src=\"$file?".rand(0,10000000000)."\" width=\"$x\" height=\"$y\" /></div>";
		#echo "<div><img src=\"$file?".rand(0,10000000000)."\" width=\"$x\" height=\"$y\" /></div>";
		#echod("imgmagick WORKED!");
	else
		echo "Irgendwas lief schief. :(";
		#echod("NO LUCK! ($ret)");
		#echod(print_r($out));
		#echo "<div><img src=\"$file?".rand(0,10000000000)."\" width=\"$x\" height=\"$y\" /></div>";
}

function escape($text) {
	global $maxchars;
	$text = substr($text, 0, $maxchars);
	#$pattern = "/([^a-zA-Z0-9<>!\"$%&\/()=?#+ -]*)/"; # match only letter which are in this list!
	$pattern = "|([^a-zA-Z0-9äÄöÖüÜß~<=>_,;:!?\'\"«»()§@$*&%+\\/# -]*)|"; # match only letter which are in this list!
	$text = preg_replace($pattern, '', $text);
	return $text;
}

function echoSpecialChars() {
	$chars = array('ä','ö','ü','Ä','Ö','Ü','ß','~',';',':','«','»','@');
	for($i=0; $i<count($chars); $i++) {
		echo "<a class=\"char\" href=\"javascript:insertChar('".$chars[$i]."')\">".$chars[$i]."</a>
		";
	}
}

function htmlEscape($text) {
	$pattern = "/(\"*)/";
	#$text = preg_replace($pattern, '&quot;', $text);
	return $text;
}

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
	<head>
		<title>Kindergarten Font</title>
		<link rel="stylesheet" type="text/css" href="common.css" />
		<script type="text/javascript">
			var idleTime = 30;
			var randomPhrases = [
				"Supersexy Käsebrot",
				"Lorem ipsum",
				"Release strogre",
				"All your base",
				"kontakt@felix-moeller,net",
				"23",
				"2+2=5",
				"Hamburgefonsiv",
				"Ringelpietz"
				];
			function selectInput() {
				var text_input = document.getElementById("textInput");
				text_input.focus();
				text_input.select();
			}
			function resetTimer() {
				if (timer > 0) {
					timer = idleTime;
				}

			}
			function startTimer() {
				timer = idleTime;
				timerInterval = setInterval("countDown()", 1000 );
			}
			function countDown() {
				if (timer > 0) {
					timer--;
				} else {
					clearInterval(timerInterval);
					randomText();
				}

			}
			function randomText() {
				document.getElementById("textInput").value = randomPhrases[Math.floor(Math.random()*randomPhrases.length)];
				document.getElementById('submitButton').click();
			}
			function initPage() {
				selectInput();
				startTimer();
				document.getElementById("submitButton").disabled = false;
			}
			function insertChar(c) {
				document.getElementById("textInput").value += c;
			}
			function toggleCharMenu() {
				var m = document.getElementById("charMenu");
				var b = document.getElementById("charButton");
				if (m.style.display == 'block') {
					m.style.display = 'none';
					b.innerHTML = "► Sonderzeichen";
				} else {
					m.style.display = 'block';
					b.innerHTML = "▼ Sonderzeichen";
				}
			}
			function showLoader() {
				document.getElementById("submitButton").disabled = true;
				document.getElementById("submitButton").style.display = 'none';
				document.getElementById("loader").style.display = 'block';
			}
		</script>
	</head>
	<body onload="initPage()" onmousemove="resetTimer()" onkeydown="resetTimer()" <?php if($dark==0) { echo "class=\"dark\""; } else { echo "class=\"light\""; } ?> >
		<table>
			<tr>
				<td vertical-align="middle">
					<div>
<?php

$text = escape($_POST['text']);
if ($_POST['colors'] == "on") {
	$colors = 1;
} else {
	$colors = 0;
}
if (isset($text) && $text != "") {
	renderTextImg(computeText($text), $colors);
} else {
	$colors = 1;
	$text = "Kindergarten";
	renderTextImg(computeText($text), $colors);
}
?>
							<div>
								<center>
									<?php if($_GET['about']) echo "About this project..." ?>
									<form method="POST" action="<?php echo $_SERVER[´PHP_SELF´] ?>" >
										<div class="formLeft">
											<div>
												<input id="textInput" name="text" type="text" maxlength="<?php echo $maxchars ?>" autocomplete="off" value="<?php echo htmlEscape($text) ?>" >
											</div>
											<div class="menuGap">
												<a class="right" href="javascript:toggleCharMenu()"><p id="charButton">► Sonderzeichen</p></a>
												<p class="right"><input type="checkbox" name="colors" <?php if($colors==1) echo "checked" ?> /><span class="left">Bunt</span></p>
												<p class="right"><input type="checkbox" name="dark" <?php if($dark==1) echo "checked" ?> /><span class="left">Dunkel</span></p>
											</div>
											<div class="border" id="charMenu">
												<?php echoSpecialChars() ?>
											</div>
										</div>
										<div class="formRight">
											<input id="submitButton" type="submit" value="Schreiben" onclick="showLoader()" >
											<div class="load" id="loader"><p class="loadimg">&nbsp;</p>Sekunde...</div>
										</div>
									</form>
								</center>
							</div>
					</div>
				</td>
			</tr>
		</table>
		<div id="credits" ><!--<div id="dino">&nbsp;</div>--><p>2012 ‌© Felix</p><p>Thanks to the little artists from the KiTa</p><p style="font-size:8px">&nbsp;</p><?php
if($demo) {
	echo '<p>Visit this page to write your own stuff at home</p><p class="underline">www.felix-moeller.net/kindergarten</p>';
} # else { echo '<p><a href="about">More about this project</a></p>'; }
$t2 = getTime();

if($showTime) {
echo "<p style=\"font-size:8px\">&nbsp;</p><p style=\"font-size:10px\">Computed within ".number_format(($t2 - $t1),2)."s.</p>";
}
		?></div>
	</body>
</html>
