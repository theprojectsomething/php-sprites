<?php

// width/height per sprite
$size = 100;

// columns/rows per sheet
$mod = 20;

// path to data
$data_path = "sheets";

// path to root in filesystem (probably relative)
$root_path = "";

function generate () {
  global $root_path, $mod;

  $path = @$_GET["path"];
  $raw_data = get_data($path);

  if(!$path) respond($raw_data);

  // create the path if it doesn't exist
  $data = isset($raw_data[$path]) && !isset($_GET["refresh"]) ? $raw_data[$path] : new_path();

  // get all the images under the path
  $images = get_images($path);
  
  // find any new images
  $new_images = array();
  foreach ($images as $image => $details){
    $image = str_replace($root_path . $path, "", $image);
    if( !in_array($image, $data["images"]) ) $new_images[] = $image;
  }

  // if no new images return the sprites list
  if( !count($new_images) ) respond($data["sheets"], $path, count($data["images"]));

  // add new images
  add_images($new_images, $path, $data);

  // save the updated data
  $raw_data[$path] = $data;
  save_data($raw_data);

  respond($data["sheets"], $path, count($data["images"]), count($new_images));
}

function new_path () {
  return array(
    "images" => array(),
    "sheets" => array(
      array()
    )
  );
}

function get_data () {
  global $data_path, $size, $mod;
  $json = @file_get_contents("$data_path.json");
  return $json ? json_decode($json, true) : array();
}

function get_images ($path) {
  global $root_path;
  $pattern = '/^.+\.(jpg|png)$/i';
  if( !file_exists($root_path . $path) ) _die("Path not found");
  $dir = new RecursiveDirectoryIterator($root_path . $path);
  $i = new RecursiveIteratorIterator($dir);
  return new RegexIterator($i, $pattern, RecursiveRegexIterator::GET_MATCH);
}

function add_images ($new_images, $path, &$data) {
  global $root_path, $mod, $size;

  foreach ($new_images as $image) {
  
    // current sheet data
    $sheet_data = end($data["sheets"]);

    // current mod and sheet no
    $no = count($data["sheets"]);
    $i = count($sheet_data);
    
    // if current sheet is full create new sheet
    if( $i >= $mod * $mod ) {
      ++$no;
      $i = 0;
      if($no)
      $sheet_data = $data["sheets"][] = array();
    }

    $data["images"][] = $image;
    $data["sheets"][$no - 1][] = $image;

    // get current sheet as GD image
    $sheet_path = sheet_path($path, $no);
    if( !isset($sheet) ) $sheet = file_exists($sheet_path) ? imagecreatefromjpeg($sheet_path) : imagecreatetruecolor($size * $mod, $size * $mod);

    $image_path = $root_path . $path . $image;
    $image = substr($image_path, -3) === "jpg" ? imagecreatefromjpeg($image_path) : imagecreatefrompng($image_path);

    list($width, $height) = getimagesize($image_path);

    // cropped size
    $crop_size = min($width, $height);

    // offset x and y
    $dx = ($width - $crop_size)*0.5;
    $dy = ($height - $crop_size)*0.5;

    // current column and row
    $col = $i % $mod;
    $row = ($i - $col) / $mod;

    // crop and resample
    imagecopyresampled($sheet, $image, $col * $size, $row * $size, $dx, $dy, $size, $size, $crop_size, $crop_size);

    // if last image in sheet
    if( $i + 1 === $mod * $mod ) {
      imagejpeg($sheet, $sheet_path);
      unset($sheet);
    }
  }

  // if sheet hasn't been saved
  if( $sheet ) imagejpeg($sheet, $sheet_path);
}

function sheet_path ($path, $no) {
  global $data_path;
  return "{$data_path}/" . str_replace("/", "", $path) . "_{$no}.jpg";
}

function save_data ($data) {
  global $data_path;
  file_put_contents("$data_path.json", json_encode($data));
}

function empty_sheets () {
  global $data_path;
  $sheets = glob("$data_path/*");
  foreach($sheets as $sheet) {
    if( is_file($sheet) ) unlink($sheet);
  }
}

function respond ($data, $path="", $count=0, $new=0) {
  global $size, $mod;

  $sheet_paths = array();
  foreach($data as $i => $d){
    if($path) $sheet_paths[] = sheet_path($path, $i + 1);
    else {
      foreach($d["sheets"] as $ii => $dd) $sheet_paths[] = sheet_path($i, $ii + 1);
    }
  }

  _die(array(
    "size" => $size,
    "mod" => $mod,
    "path" => $path,
    "sheet_paths" => $sheet_paths,
    "count" => $count,
    "new" => $new,
    "sheets" => $data
  ), true);
}

function _die ($data, $success=false) {
  header('Content-Type: application/json');
  die(json_encode(array(
    "success" => $success,
    "data" => $data
  )));
}


generate();