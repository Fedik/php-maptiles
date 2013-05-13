<?php
/**
 * @package PHP MapTiler, Simple Map Tiles Generator
 * @version 1.1 (2013.05.13)
 * @author  Fedik getthesite at gmail.com
 * @link    http://www.getsite.org.ua
 * @license	GNU/GPL http://www.gnu.org/licenses/gpl.html
 */

class MapTiler
{
	/**
	 * image path
	 * @var string
	 */
	protected $image_path = null;

	/**
	 * tiles path
	 * @var string
	 */
	protected $tiles_path = null;

	/**
	 * tile size
	 * @var int
	 */
	protected $tile_size = 256;

	/**
	 * Store structure, examples: zoom/x/y, zoom/x-y
	 * file name format, can contain the path separator
	 * extension (eg: '.jpg') will add automaticaly depend of format option
	 * @var string for sprintf()
	 */
	protected $store_structure = '%d/%d/%d';

	/**
	 * force tile generation, event if tile already exist
	 * @var bool
	 */
	protected $force = false;

	/**
	 * http://www.maptiler.org/google-maps-coordinates-tile-bounds-projection/
	 * if true - tiles will generates from top to bottom
	 * @var bool
	 */
	protected $tms = true;

	/**
	 * fill color can be transparent for png
	 * @var string
	 */

	protected $fill_color = 'white';

	/**
	 * zoom min
	 * @var int
	 */

	protected $zoom_min = 0;
	/**
	 * zoom max
	 * @var int
	 */
	protected $zoom_max = 8;

	/**
	 * for prevent image scalling up
	 * if image size less than need for zoom level
	 * @var int - max zoom level, when scalling up is allowed
	 */
	protected $scaling_up = 0;

	/**
	 * Imagic filter for resizing
	 * http://www.php.net/manual/en/imagick.constants.php
	 * Imagick::FILTER_POINT - fast with bad quality
	 * Imagick::FILTER_CATROM - good enough
	 */
	//protected $resize_filter = Imagick::FILTER_POINT;

	/**
	 * image format used for store the tiles: jpeg or png
	 * http://www.imagemagick.org/script/formats.php
	 * @var string
	 */
	protected $format = 'jpeg';

	/**
	 * quality of the saved image in jpeg format
	 * @var int
	 */
	protected $quality_jpeg = 80;

	/**
	 * ImageMagick tmp folder,
	 * Can be changed in case if system /tmp have not enough free space
	 * @var string
	 */
	protected $imagick_tmp = null;

	/**
	 * array with profiler class and method for call_user_func_array
	 * @var array
	 */
	protected $profiler_callback = null;

	/**
	 * Class constructor.
	 */
	public function __construct($image_path, $options = array())
	{
		// Verify that imagick support for PHP is available.
		if (!extension_loaded('imagick')){
			throw new RuntimeException('The Imagick extension for PHP is not available.');
		}

		$this->image_path = $image_path;
		$this->setOptions($options);

		//if new tmp folder given
		if($this->imagick_tmp && is_dir($this->imagick_tmp)){
			putenv('MAGICK_TEMPORARY_PATH=' . $this->imagick_tmp);
		}
	}

	/**
	 * set options
	 * @param array $options
	 */
	public function setOptions($options) {
		foreach($options as $k => $value){
			$this->{$k} = $value;
		}
	}

	/**
	 * get options
	 * @return array $options
	 */
	public function getOptions() {
		return get_object_vars($this);
	}

	/**
	 * run make tiles process
	 * @param bool $clean_up - whether need to remove a zoom base images
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
	 * prepare each zoom lvl base images
	 * @param int $min - min zoom lvl
	 * @param int $max - max zoom lvl
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
			throw new RuntimeException('Cannot read image '.$this->image_path);
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
	 * remove zoom lvl base images
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
	 * make tiles for given zoom level
	 * @param int $zoom
	 */
	public function tilesForZoom($zoom) {
		$path = $this->tiles_path.'/'.$zoom;
		//base image
		$ext = $this->getExtension();
		$file = $this->tiles_path.'/'.$zoom.'.'.$ext;

		//load image
		if(!is_file($file) || !is_readable($file)){
			throw new RuntimeException('Cannot read image '. $file . ', for zoom ' . $zoom);
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

		//crop cursore
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
	 * load image and return imagic resurce
	 * @param string $path
	 * @return resource Imagick
	 */
	protected function loadImage($path = null) {
		return new Imagick($path);
	}

	/**
	 * Destroys the Imagick object
	 * @param resurce $image Imagick object
	 * @return bool
	 */
	protected function unloadImage($image) {
		$image->clear();
		return $image->destroy();
	}

	/**
	 * Fit image in to given size
	 * http://php.net/manual/en/imagick.resizeimage.php
	 * @param resurce $image Imagick object
	 * @param int $w width
	 * @param int $h height
	 *
	 * @return resurce imagick object
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
	 * @param resurce $image Imagick object
	 * @param int $w width
	 * @param int $h height
	 *
	 * @return resurce imagick object
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
	 * save image in to destination
	 * @param resurce $image
	 * @param string $name
	 * @param string $dest full path with file name
	 */
	protected function imageSave($image, $dest){
		//prepare folder
		$this->makeFolder(dirname($dest));

		//prepare to save
		if($this->format == 'jpeg') {
			$image->setCompression(Imagick::COMPRESSION_JPEG);
			$image->setCompressionQuality($this->quality_jpeg);
		}

		//save image
		if(!$image->writeImage($dest)){
			throw new RuntimeException('Cannot save image '.$dest);
		}

		return true;
	}

	/**
	 * create folder
	 * @param string $path folder path
	 */
	protected function makeFolder($path, $mode = 0755) {
		//check if already exist
		if(is_dir($path)){
			return true;
		}
		//make folder
		if(!mkdir($path, $mode, true)) {
			throw new RuntimeException('Cannot crate folder '.$path);
		}
		return true;
	}

	/**
	 * return file extension depend of given format
	 * @param string $format - output format used in Imagick
	 */
	public function getExtension($format = null){
		$format = $format ? $format : $this->format;
		$ext = '';

		switch (strtolower($format)) {
			case 'jpeg':
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
	 * profiler function
	 * @param string $note
	 */
	protected function profiler($note) {
		if($this->profiler_callback) {
			call_user_func_array($this->profiler_callback, array($note));
		}
	}
}
