# PHP MapTiles, Simple Map Tiles Generator

Simple Map Tiles Generator allow to make a Map Tiles with PHP and Imagick. That allows to build a simple custom map.
It does not include any geographical calculations.

## Requirements

* PHP >= 5.3 version
* PHP Imagick extension >= 3.0 version
* A lot CPU time (for images with high resolution and for high zoom level)
* A lot free disc space
* Patience ;)

## Usage example

Make custom map, based on `my-image.jpg`.

Generate a tiles:
```php
// Setup
$tiler = new Fedik\MapTiles\MapTiles('/full/path/to/my-image.jpg', array(
  'tiles_path' => '/full/path/to/where-to-store-tiles/',
  'zoom_max' => 3
));

// Generate tiles
$tiler->process(true);
```

Display the result with [Leaflet.js](http://leafletjs.com)

```html
<html>
<head>
  <link rel="stylesheet" href="path/to/leaflet.css" />
  <script src="path/to/leaflet.js"></script>
    
  <script type="module">
      const tiles = L.tileLayer('tiles-path/{z}/{x}/{y}.jpg', {
          minZoom: 0,
          maxZoom: 5,
          tms: true
      });

      const map = L.map('map', {
          center: [0, 0],
          zoom: 1,
          layers:[tiles]
      });
  </script>
</head>
<body>
  <div id="map" style="height: 100vh"></div>  
</body>
</html>
```

## API

### Options

* `tile_size`  A tile size (def: 256);
* `store_structure`  A tile name, can contain `/` for split `zoom`, `x` , `y` by folder (def: `'%d/%d/%d'`);
* `force`  Force create new tile if it already exist (def: `false`);
* `tms`  Use TMS tile addressing, which is used in open-source projects like OpenLayers or TileCache (def: `true`);
* `fill_color`  Color for fill a free space if tile is not a square (def: `white`);
* `zoom_min`  Minimum zoom level for make tiles (def: `0`);
* `zoom_max`  Maximum zoom level for make tiles (def: `8`);
* `scaling_up`  Zoom level when scaling up still allowed, when a base image have less size than need for a requested zoom level (def: `0`);
* `format`  Image format (def: `jpeg`);
* `quality_jpeg`  Quality for jpeg format (def: `80`);
* `imagick_tmp`  Temporary folder for ImageMagick, useful if system /tmp folder have not enough free space (def: null);

### Publick Methods

* `__construct($image_path, $options = array())`  Class constructor.
* `setOptions($options)`  Set/change options.
* `getOptions()`  Get options array.
* `process($clean_up = false)`  Generate tiles, set `$clean_up=true` to remove a base zoom images.
* `prepareZoomBaseImages($min = null, $max = null)`  Prepare a base zoom images, `$min` - min zoom level, `$max` - max zoom level.
* `removeZoomBaseImages($min = null, $max = null)`  Remove the base zoom images.
* `tilesForZoom($zoom)`  Generate tiles for given `$zoom` level.

## Attention

The script do **NOT** related to **maptiler.com**, that is standalone project.
