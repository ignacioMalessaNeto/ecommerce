<?php 

use \Hcode\Page;
use \Hcode\Model\Product;

$app->get('/', function () {

	$page = new Page();
	
	$products = Product::listAll();
	
	$page->setTpl("index", [
		'products'=>Product::checkList($products)
	]);
});