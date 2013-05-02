<?php
/**
 * @package PHP MapTiler, Simple Map Tiles Generator
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
	 * http://www.maptiler.org/google-maps-coordinates-tile-bounds-projection/
	 * if true - tiles will generates from top to bottom
	 * @var bool
	 */
	protected $tms = false;

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
	protected $zoom_max = 18;

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
	 * run make tiles process
	 */
	public function process(){
		global $_PROFILER;
		$_PROFILER->mark('Make tiles Started');

		//prepare each zoom lvl base images
		$this->prepareZoomBaseImages();


		$_PROFILER->mark('PrepareEachZoomImage');


		//make tiles for each zoom lvl
		for($i = $this->zoom_min; $i <= $this->zoom_max; $i++){
			$this->tilesForZoom($i);

			$_PROFILER->mark('Make tiles for zoom ' .$i);
		}


		$_PROFILER->mark('Make tiles End');
	}

	/**
	 * prepare each zoom lvl base images
	 */
	public function prepareZoomBaseImages(){
		global $_PROFILER;
		//load main image
		if(!is_file($this->image_path) || !is_readable($this->image_path)){
			throw new RuntimeException('Cannot read image '.$this->image_path);
		}

		$main_image = $this->loadImage($this->image_path);
		$main_image->setImageFormat($this->format);
		//get image size
		$main_size_w = $main_image->getimagewidth();
		$main_size_h = $main_image->getImageHeight();

		$_PROFILER->mark('MainImageLoad');

		//prepare each zoom lvl base images
		for($i = $this->zoom_min; $i <= $this->zoom_max; $i++){
			$lvl_path = $this->tiles_path.'/'.$i;
			//prepare base images for each zoom lvl
			$img_size_w = ($i + 1) * $this->tile_size;
			$img_size_h = $img_size_w;
			//prevent scaling up
			if($img_size_w > $main_size_w &&  $img_size_h > $main_size_h){
				//set real max zoom
				$this->zoom_max = $i-1;
				break;
			}

			//fit main image to current zoom lvl
			$lvl_image = clone $main_image;
			$lvl_image = $this->imageFitTo($lvl_image, $img_size_w, $img_size_h);

			//store
			$ext = $this->format == 'jpeg'? 'jpg' : 'png';
			$lvl_file = $this->tiles_path.'/'.$i.'.'.$ext;
			$this->imageSave($lvl_image, $lvl_file);

			//clear
			$this->unloadImage($lvl_image);

		}

		//free resurce, destroy main image
		$this->unloadImage($main_image);
		$_PROFILER->mark('MainImageUnLoad');
	}

	/**
	 * make tiles for given zoom level
	 * @param int $zoom
	 */
	public function tilesForZoom($zoom) {
		$path = $this->tiles_path.'/'.$zoom;
		//base image
		$ext = $this->format == 'jpeg'? 'jpg' : 'png';
		$file = $this->tiles_path.'/'.$zoom.'.'.$ext;

		//load image
		if(!is_file($file) || !is_readable($file)){
			throw new RuntimeException('Cannot read image '. $file . ', for zoom ' . $zoom);
		}

		$image = $this->loadImage($file);

		//get image size
		$image_w = $image->getimagewidth();
		$image_h = $image->getImageHeight();

		//count by x,y
		$x = pow(2, $zoom);
		$y = $x;

		//tile width, height
		$w = $this->tile_size;
		$h = $w;

		//crop cursore
		$crop_x = 0;
		$crop_y = 0;

		//by x
		for($ix = 0; $ix < $x; $ix++){
			$path_x = $path .'/'.$ix;
			//$crop_x = $this->tms ? $image_w - $ix * $w : $ix * $w;
			$crop_x = $ix * $w;
			if($crop_x >= $image_w) break;
			//by y
			for($iy = 0; $iy < $y; $iy++){
				//file name
				$lvl_file = $this->tiles_path.'/'.sprintf($this->store_structure, $zoom, $ix, $iy).'.'.$ext;

				//just copy if zoom = 0
				if($zoom == 0){
					$this->imageSave($image, $lvl_file);
					continue;
				}

				//crop
				//$crop_y = $this->tms? $image_h - $iy * $h: $iy * $h;
				$crop_y = $iy * $h;
				if($crop_y >= $image_h) break;

				$tile = clone $image;

				//$image->setImagePage($w, $h, $crop_x, $crop_y);
				$tile->cropImage($w, $h, $crop_x, $crop_y);

				//fill free space
// 				if($tile->getimagewidth() < $w
// 					|| $tile->getimageheight() < $h
// 				){
// 					$tile->setImageBackgroundColor($this->fill_color);
// 					//$tile->setImageExtent($w, $h);
// 					$tile->extentImage($w, $h, 0, 0);
// 				}
				//save
				$this->imageSave($tile, $lvl_file);
				$this->unloadImage($tile);

			}
		}

		//clear resurces
		$this->unloadImage($image);

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
	 * @return resurce new imagick object
	 */
	protected function imageFitTo($image, $w, $h, $fill_free = true) {
		//resize
		//$image->resizeImage($w, $h, Imagick::FILTER_POINT, 1, true);
		//$image->resizeImage($w, $h, Imagick::FILTER_QUADRATIC, 1, true);
		$image->resizeImage($w, $h, Imagick::FILTER_CATROM, 1, true);

		//fill free space
		if($fill_free){
			$image->setImageBackgroundColor($this->fill_color);
			$image->extentImage($w, $h, 0, 0);
			//$image->setImageExtent($w, $h);
			//$image->setImageGravity(Imagick::GRAVITY_CENTER);
		}

		//return result
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
}
