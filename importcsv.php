<?php
/**
 *	@package W&Co import csv
 *	@author W&C
 *	@version 0.0.1
 */
/*
 Plugin Name: W&Co import csv
 Plugin URI: http://www.yann.com/
 Description: import google sheet on cpt/acf
 Version: 0.0.1
 Author: Yann Dubois
 Author URI: http://www.yann.com/
 License: GPL2
 */

include_once(dirname(__FILE__) . '/inc/main.php');

/** Controller Class **/
global $wac_importcsv_o;
$wac_importcsv_o = new wacimportcsv();