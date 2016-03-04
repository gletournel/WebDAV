WebDAV
======

A simple PHP WebDAV client and stream wrapper. 

## Timeout Value Big Int Support

Windows hosts PHP_INT_MAX value is 2147483647. For fix it you can use gmp extention.  

On Unix hosts big int support is better, you can use WebDAV AS IS. 
For run all tests run (it's skipped by default): 

	$sudo apt-get install php5-gmp
 