<?php
/**
 *	@package W&Co import csv
 *	@author W&C
 *	@version 0.0.1
 */
/*
 Plugin Name: CSV to WP
 Plugin URI: http://www.yann.com/
 Description: WP&Co CSV to WordPress content importer
 Version: 0.0.1
 Author: Yann Dubois
 Author URI: http://www.yann.com/
 License: GPL2
 */

include_once(dirname(__FILE__) . '/inc/main.php');

/** Controller Class **/
global $wac_importcsv_o;
$wac_importcsv_o = new wacimportcsv();
