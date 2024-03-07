<?php 

use \Hcode\Page;
use \Hcode\Model\Product;
use \Hcode\Model\Category;


$app->get('/', function () {

	$page = new Page();
	
	$products = Product::listAll();
	
	$page->setTpl("index", [
		'products'=>Product::checkList($products)
	]);
});


$app->get("/category/:idcategory",function($idcategory){
	
	$category = new Category();

	$category->get((int)$idcategory);

	$page = new Page();

	$page->setTpl("category", [
		'category'=>$category->getValues(),
		'products'=>Product::checkList($category->getProducts())
	]);

});