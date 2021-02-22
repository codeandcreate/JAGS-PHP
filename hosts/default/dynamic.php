<?php
// $JAGSRequest contains all request informations
var_export($JAGSRequest);

echo "\n";
// $JAGSReturn contains all response data
// but you can still just "echo" your content :)
var_export($JAGSReturn);
	
// overwrite to get a nice automatic formating in Lagrange
$JAGSReturn['meta'] = 'text/x-php';