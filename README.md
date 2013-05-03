#PHP MapTiler, Simple Map Tiles Generator

Simple Map Tiles Generator allow to make the Map Tiles using PHP. That allow to build simple custom map.
Here not exist any geographical calculations, because I have no idea how to :) 
Just fork/pull it if you know how to make it better ;)

##Requirements
* PHP >= 5.3 version
* PHP Imagic extension >= 3.0 version
* A lot CPU time (for images with high resolution and for high zoom level)
* A lot free disc space
* Patience ;)

##Usage example
Make custom map, based on my-image.jpg.

Generate the Tiles:
```php
//init 
$map_tiler = new MapTiler('/full/path/to/my-image.jpg', array(
  'tiles_path' => '/full/path/to/where-store-result/'
  'zoom_max' => 3,
));
//execute
try {
  $map_tiler->process(true);
} catch (Exception $e) {
  echo $e->getMessage();
  echo $e->getTraceAsString();
}
```

Display the result using [Leaflet.js](http://leafletjs.com)

```html
<html>
<head>
  <link rel="stylesheet" href="dist/leaflet.css" /> 
</head>
<body>
  <div id="map" style="width: 700px; height: 500px;"></div>
  <script src="dist/leaflet.js"></script>
  <script>
  
  var tiles = L.tileLayer('tiles-path/{z}/{x}/{y}.jpg', {
    minZoom: 0,
    maxZoom: 3,
    tms: true
  });
  
  var map = L.map('map', {
    center: [0, 0],
    zoom:1,
    minZoom: 0,
    maxZoom: 3,
    //crs: L.CRS.Simple, //available in dev version
    layers:[tiles]	
  });
  </script>
</body>
</html>
```

##API
###Options
* `tile_size` - the tile size (def: 256);
* `store_structure` - the tile name, can contain `/` for split `zoom`, `x` , `y` by folder (def: '%d/%d/%d');
* `force` - force create new tile if it already exist (def: false);
* `tms` - use TMS tile addressing, which is used in open-source projects like OpenLayers or TileCache (def: true);
* `fill_color` - color for fill free space if tile is not a square (def: 'white');
* `zoom_min` - minimum zoom level for make tiles (def: 0);
* `zoom_max` - maximum zoom level for make tiles (def: 8);
* `scaling_up` - whether allow scalle up the base image when it have less size than need for curent zoom level (def: false);
* `format` - image fomat (def: jpeg);
* `quality_jpeg` - quality for jpeg format (def: 80);
* `imagick_tmp` - temp folder for ImageMagick, useful if system /tmp folder have not enough free space (def: null);

###Publick Methods
* `__construct($image_path, $options = array())` - constructor
* `setOptions($options)` - can be used for set/change options
* `process($clean_up = false)` - run process for the tiles generation, set $clean_up=true to remove the base zoom images
* `prepareZoomBaseImages($min = null, $max = null)` - prepare the base zoom images, $min - min zoom level, $max - max zoom level
* `removeZoomBaseImages($min = null, $max = null)` - remove the base zoom images
* `tilesForZoom($zoom)` - generate the tiles for given $zoom level
