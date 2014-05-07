<html>

<head>
    <title>throw new CryptoException(&quot;Hardeners off&quot;)</title>
</head>

<body>
    <div style="height:35%;">&nbsp;</div>
    <div style="width:500px; margin:auto auto; padding:20px; border:1px dashed #333; background-color:#eee;">
	<div style="text-align:center; color:#DD0000; font-size:25px; font-family:Arial; font-weight:bold;">Something went wrong!</div>
	<br>
	<div style="text-align:left; color:#333333; font-size:12px; font-family:Sans Courier; font-weight:bold;">Sometimes the <i>BRAVE Collective Core Service</i> is a bit picky about all the crypto going on. Sorry for that...</div>
	<br>
	<div style="text-align:right; font-size:12px; font-family:Sans Courier;"><a href="
	<?php
	    require('config.php');
	    print $cfg_url_login;
	?>
	">restart</a> <a href="javascript:location.reload()">reload</a></div>
    </div>
</body>

</html>