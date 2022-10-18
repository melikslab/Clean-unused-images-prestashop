<?php

$shop_root = $_SERVER['DOCUMENT_ROOT'].'/'; 
$image_folder = 'img/p/';
$scan_dir = $shop_root.$image_folder;

include('./config/config.inc.php');
include('./init.php');

#---------------------------------------------#
$last_id = (int)Db::getInstance()->getValue('
	SELECT count(id_image) FROM '._DB_PREFIX_.'image
');

$sql = 'SELECT DISTINCT
	i.id_image, i.id_product
FROM
	'._DB_PREFIX_.'image i
	LEFT JOIN '._DB_PREFIX_.'product_lang pl ON ( i.id_product = pl.id_product ) 
	AND pl.id_lang = 1
	LEFT JOIN '._DB_PREFIX_.'image_shop ish ON ( ish.id_image = i.id_image AND ish.id_shop = 1 )
	LEFT JOIN '._DB_PREFIX_.'image_lang il ON ( i.id_image = il.id_image AND il.id_lang = 1 ) 
	LEFT JOIN '._DB_PREFIX_.'product_attribute_image pai ON (i.id_image = pai.id_image )
WHERE
 ( ish.cover = 0 OR ish.cover IS NULL ) 
	AND ish.id_shop = 1
	AND pai.id_product_attribute is null';

$imagenes = Db::getInstance()->executeS($sql);


echo '<h3>There was '.$last_id.' images in database but '.count($imagenes).' is not used right now. Lets clean our storage!!.</h3><br>';


$removed_images = 0;

foreach ($imagenes as $key => $imagen) {
	//if($removed_images==50) break;// for testing
	if (!imageExistsInDB($imagen['id_image'])){
		$imageDir = str_split($imagen['id_image']);
		$imageDir = implode('/', $imageDir);
		$path = $scan_dir.$imageDir;
		deleteImagesFromPath($path);
	}
	else{
		echo 'Deleted image from prod '.$imagen['id_product'].'<br>';
		deleteSql($imagen['id_image']);
		$imageDir = str_split($imagen['id_image']);
		$imageDir = implode('/', $imageDir);
		$path = $scan_dir.$imageDir;
		deleteImagesFromPath($path);
	}
}

function deleteImagesFromPath($path) {
	global $removed_images;
	$images = glob($path . '/*.{jpg,png,gif,jpeg,webp}', GLOB_BRACE);
	if ($images){
		foreach ($images as $file) {
			if (is_file($file)) {
				unlink($file);
			}
		}
		$removed_images++;
		echo 'Deleted images from folder ' . $path . '/' ."<br/>";
	}
}

function imageExistsInDB($id_image){
	return Db::getInstance()->getValue('
	    SELECT id_image FROM '._DB_PREFIX_.'image WHERE id_image = '.(int)$id_image
	);
}

function deleteSql($id_image) {
	Db::getInstance()->executeS('DELETE FROM '._DB_PREFIX_.'image WHERE id_image='.$id_image);
	Db::getInstance()->executeS('DELETE FROM '._DB_PREFIX_.'image_shop WHERE id_image='.$id_image);
	Db::getInstance()->executeS('DELETE FROM '._DB_PREFIX_.'image_lang WHERE id_image='.$id_image);
}

echo '--------------------------------------<br>';
if  ($removed_images > 0)
	echo '<h3>We removed '.$removed_images.' product images!</h3>';
else
	echo 'Everything is ok with Your images. I did not removed any of them or I made it before. Good Job Presta!';







?>