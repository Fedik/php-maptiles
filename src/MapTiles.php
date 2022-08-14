<?php
/**
 * @package PHP MapTiles, Simple Map Tiles Generator
 * @author  Fedik getthesite at gmail.com
 * @link    http://www.getsite.org.ua
 * @license	GNU/GPL http://www.gnu.org/licenses/gpl.html
 */

namespace Fedik\MapTiles;

/**
 * MapTiles class
 */
class MapTiles
{
	/**
	 * Path to source image
	 * @var string
	 */
	protected $image_path = null;

	/**
	 * Path to tiles folder
	 * @var string
	 */
	protected $tiles_path = null;

	/**
	 * Tile size
	 * @var int
	 */
	protected $tile_size = 256;

	/**
	 * Store structure, examples: zoom/x/y, zoom/x-y
	 * file name format, can contain the path separator
	 * extension (eg: '.jpg') will add automatically depend on format option
	 *
	 * @var string for sprintf()
	 */
	protected $store_structure = '%d/%d/%d';

	/**
	 * Force tile generation, event if tile already exist
	 *
	 * @var bool
	 */
	protected $force = false;

	/**
	 * When true - tiles will be generated from top to bottom
	 * https://wiki.openstreetmap.org/wiki/TMS
	 * https://en.wikipedia.org/wiki/Tile_Map_Service
	 *
	 * @var bool
	 */
	protected $tms = true;

	/**
	 * A fill color. Can be "transparent" for png.
	 * @var string
	 */

	protected $fill_color = 'white';

	/**
	 * Zoom min
	 * @var int
	 */
	protected $zoom_min = 0;

	/**
	 * Zoom max
	 * @var int
	 */
	protected $zoom_max = 8;

	/**
	 * To prevent image scaling up when image size is less than needed for active zoom level
	 *
	 * @var int - max zoom level, when scaling up is allowed
	 */
	protected $scaling_up = 0;

	/**
	 * Imagick filter for resizing
	 * http://www.php.net/manual/en/imagick.constants.php
	 * Imagick::FILTER_POINT - fast with bad quality
	 * Imagick::FILTER_CATROM - good enough
	 */
	//protected $resize_filter = Imagick::FILTER_POINT;

	/**
	 * Image format to store the tiles: jpeg or png
	 * http://www.imagemagick.org/script/formats.php
	 *
	 * @var string
	 */
	protected $format = 'jpeg';

	/**
	 * Quality of the saved image in jpeg format
	 *
	 * @var int
	 */
	protected $quality_jpeg = 80;

	/**
	 * ImageMagick tmp folder,
	 * Can be changed in case if system /tmp have not enough free space.
	 *
	 * @var string
	 */
	protected $imagick_tmp = null;

	/**
	 * Profiler callback. Called by call_user_func_array
	 *
	 * @var callable
	 */
	protected $profiler_callback = null;

	/**
	 * Class constructor.
	 *
	 * @throws \RuntimeException
	 */
	public function __construct($image_path, $options = array())
	{
		// Verify that imagick support for PHP is available.
		if (!extension_loaded('imagick')){
			throw new \RuntimeException('The Imagick extension for PHP is not available.');
		}

		$this->image_path = $image_path;
		$this->setOptions($options);

		//if new tmp folder given
		if($this->imagick_tmp && is_dir($this->imagick_tmp)){
			putenv('MAGICK_TEMPORARY_PATH=' . $this->imagick_tmp);
		}
	}

	/**
	 * Set options
	 *
	 * @param array $options
	 */
	public function setOptions($options) {
		foreach($options as $k => $value){
			$this->{$k} = $value;
		}
	}

	/**
	 * Get options
	 *
	 * @return array $options
	 */
	public function getOptions() {
		return get_object_vars($this);
	}

	/**
	 * Run make tiles process
	 *
	 * @param bool $clean_up - Whether to remove a zoom base images
	 *
	 * @throws \ImagickException
	 */
	public function process($clean_up = false){
		$this->profiler('MapTiler: Process. Start');

		//prepare each zoom lvl base images
		$this->prepareZoomBaseImages();

		$this->profiler('MapTiler: Create images for each zoom level. End');

		//make tiles for each zoom lvl
		for($i = $this->zoom_min; $i <= $this->zoom_max; $i++){
			$this->tilesForZoom($i);
		}

		//clean up base images
		if($clean_up){
			$this->removeZoomBaseImages();
		}

		$this->profiler('MapTiler: Process. End');
	}

	/**
	 * Prepare each zoom lvl base images
	 *
	 * @param int $min - min zoom lvl
	 * @param int $max - max zoom lvl
	 *
	 * @throws \RuntimeException
	 * @throws \ImagickException
	 */
	public function prepareZoomBaseImages($min = null, $max = null){
		//prepare zoom levels
		if($min){
			$max = !$max ? $min : $max;
			$this->zoom_min = $min;
			$this->zoom_max = $max;
		}

		//load main image
		if(!is_file($this->image_path) || !is_readable($this->image_path)){
			throw new \RuntimeException('Cannot read image '.$this->image_path);
		}
		$main_image = $this->loadImage($this->image_path);
		$main_image->setImageFormat($this->format);

		//get image size
		$main_size_w = $main_image->getimagewidth();
		$main_size_h = $main_image->getImageHeight();

		$this->profiler('MapTiler: Main Image loaded');

		//prepare each zoom lvl base images
		$ext = $this->getExtension();
		$start = true;
		$lvl_image = null;
		for($i = $this->zoom_max; $i >= $this->zoom_min; $i--){
			$lvl_file = $this->tiles_path.'/'.$i.'.'.$ext;

			//check if already exist
			if(!$this->force && is_file($lvl_file)){
				continue;
			}

			//prepare base images for each zoom lvl
			$img_size_w = pow(2, $i) * $this->tile_size;
			$img_size_h = $img_size_w;

			//prevent scaling up
			if((!$this->scaling_up || $i > $this->scaling_up  )
				&& $img_size_w > $main_size_w && $img_size_h > $main_size_h
			){
				//set real max zoom
				$this->zoom_max = $i-1;
				continue;
			}

			//fit main image to current zoom lvl
			$lvl_image = $start ? clone $main_image : $lvl_image;
			$lvl_image = $this->imageFit($lvl_image, $img_size_w, $img_size_h);

			//store
			$this->imageSave($lvl_image, $lvl_file);

			//clear
			if($start){
				$this->unloadImage($main_image);
			}
			$start = false;

			$this->profiler('MapTiler: Created Image for zoom level: '.$i);
		}

		//free resurce, destroy imagick object
		if($lvl_image) $this->unloadImage($lvl_image);
	}

	/**
	 * Remove zoom lvl base images
	 *
	 * @param int $min - min zoom lvl
	 * @param int $max - max zoom lvl
	 */
	public function removeZoomBaseImages($min = null, $max = null){
		//prepare zoom levels
		if($min){
			$max = !$max ? $min : $max;
			$this->zoom_min = $min;
			$this->zoom_max = $max;
		}
		//remove
		$ext = $this->getExtension();
		for($i = $this->zoom_min; $i <= $this->zoom_max; $i++){
			$lvl_file = $this->tiles_path.'/'.$i.'.'.$ext;
			if(is_file($lvl_file)){
				unlink($lvl_file);
			}
		}
	}

	/**
	 * Make tiles for given zoom level
	 *
	 * @param int $zoom
	 *
	 * @throws \RuntimeException
	 * @throws \ImagickException
	 */
	public function tilesForZoom($zoom) {
		$path = $this->tiles_path.'/'.$zoom;
		//base image
		$ext = $this->getExtension();
		$file = $this->tiles_path.'/'.$zoom.'.'.$ext;

		//load image
		if(!is_file($file) || !is_readable($file)){
			throw new \RuntimeException('Cannot read image '. $file . ', for zoom ' . $zoom);
		}

		$image = $this->loadImage($file);

		//get image size
		$image_w = $image->getimagewidth();
		$image_h = $image->getImageHeight();

		//count by x,y -hm, ceil or floor?
		$x = ceil($image_w / $this->tile_size);
		$y = ceil($image_h / $this->tile_size);

		//tile width, height
		$w = $this->tile_size;
		$h = $w;

		// Crop cursor
		$crop_x = 0;
		$crop_y = 0;

		//by x
		for($ix = 0; $ix < $x; $ix++){
			$crop_x = $ix * $w;

			//by y
			for($iy = 0; $iy < $y; $iy++){
				//full file path
				$lvl_file = $this->tiles_path.'/'.sprintf($this->store_structure, $zoom, $ix, $iy).'.'.$ext;

				//check if already exist
				if(!$this->force && is_file($lvl_file)){
					continue;
				}

				$crop_y = $this->tms? $image_h - ($iy + 1)* $h : $iy * $h;
				//@TODO: move non TMS tiles bottom too???
				//$crop_y = $this->tms ? $image_h - ($iy + 1) * $h : $image_h - ($y - $iy) * $h ;

				//crop
				$tile = clone $image;
				//$image->setImagePage($w, $h, $crop_x, $crop_y);
				$tile->cropImage($w, $h, $crop_x, $crop_y);

				//check if image smaller than we need
				if($tile->getImageWidth() < $w || $tile->getimageheight() < $h){
					$this->fillFreeSpace($tile, $w, $h, true);
				}

				//save
				$this->imageSave($tile, $lvl_file);
				$this->unloadImage($tile);
			}
		}

		//clear resurces
		$this->unloadImage($image);

		$this->profiler('MapTiler: Created Tiles for zoom level: '. $zoom);
	}

	/**
	 * Load image and return Imagick resource
	 *
	 * @param string $path
	 *
	 * @return \Imagick Imagick
	 * @throws \ImagickException
	 */
	protected function loadImage($path = null) {
		return new \Imagick($path);
	}

	/**
	 * Destroys the Imagick object
	 *
	 * @param \Imagick $image Imagick object
	 *
	 * @return bool
	 */
	protected function unloadImage($image) {
		$image->clear();
		return $image->destroy();
	}

	/**
	 * Fit image in to given size
	 * http://php.net/manual/en/imagick.resizeimage.php
	 *
	 * @param \Imagick $image Imagick object
	 * @param int $w width
	 * @param int $h height
	 *
	 * @return \Imagick imagick object
	 */
	protected function imageFit($image, $w, $h) {
		//resize - works slower but have a better quality
		//$image->resizeImage($w, $h, $this->resize_filter, 1, true);

		//scale - work fast, but without any quality configuration
		$image->scaleImage($w, $h, true);

		return $image;
	}

	/**
	 * Put image in to rectangle and fill free space
	 *
	 * @param \Imagick $image Imagick object
	 * @param int $w width
	 * @param int $h height
	 *
	 * @return \Imagick imagick object
	 */
	protected function fillFreeSpace($image, $w, $h) {
		$image->setImageBackgroundColor($this->fill_color);
		$image->extentImage(
				$w, $h,
				0, ($this->tms ? $image->getImageHeight() - $h : 0) //count for move bottom-left
		);
		//$image->setImageExtent($w, $h);
		//$image->setImageGravity(Imagick::GRAVITY_CENTER);

		return $image;
	}

	/**
	 * Save image in to destination
	 *
	 * @param \Imagick $image
	 * @param string $name
	 *
	 * @param string $dest full path with file name
	 *
	 * @throws \RuntimeException
	 */
	protected function imageSave($image, $dest){
		//prepare folder
		$this->makeFolder(dirname($dest));

		//prepare to save
		if($this->format == 'jpeg') {
			$image->setCompression(\Imagick::COMPRESSION_JPEG);
			$image->setCompressionQuality($this->quality_jpeg);
		}

		//save image
		if(!$image->writeImage($dest)){
			throw new \RuntimeException('Cannot save image '.$dest);
		}

		return true;
	}

	/**
	 * Create folder
	 *
	 * @param string $path folder path
	 *
	 * @throws \RuntimeException
	 */
	protected function makeFolder($path, $mode = 0755) {
		//check if already exist
		if(is_dir($path)){
			return true;
		}
		//make folder
		if(!mkdir($path, $mode, true)) {
			throw new \RuntimeException('Cannot crate folder '.$path);
		}
		return true;
	}

	/**
	 * Return file extension depend on given format
	 *
	 * @param string $format - output format used in Imagick
	 */
	public function getExtension($format = null){
		$format = $format ?: $this->format;
		$ext = '';

		switch (strtolower($format)) {
			case 'jpeg':
			case 'jpg':
			case 'jp2':
			case 'jpc':
			case 'jxr':
				$ext = 'jpg';
				break;
			case 'png':
			case 'png00':
			case 'png8':
			case 'png24':
			case 'png32':
			case 'png64':
				$ext = 'png';
				break;
		}
		return $ext;
	}

	/**
	 * Profiler function
	 *
	 * @param string $note
	 */
	protected function profiler($note) {
		if ($this->profiler_callback) {
			call_user_func_array($this->profiler_callback, array($note));
		}
	}
}
