<?php

/**
 * FB URL Debugger: A URL Scraper that mimics the old Facebook URL Debugger tool
 *
 * After facing disappointment when Facebook removed the old URL Debugger tool
 * and replaced it with the less useful (to me) Object Debugger tool, I decided to 
 * write my own implementation of the old URL Debugger to support my own code.
 *
 * @author Tyler Kivari ty.kivari@gmail.com
 * @copyright (c) 2011, Tyler Kivari
 * @license MIT license: http://www.opensource.org/licenses/mit-license.php
 * @TODO: Better error detection and data sanitization
 * @TODO: Add better reporting for audio and video data.
 *
 */

/**
 * The Scraper interface defines the structure of the URL Debugger
 */

interface Scraper {
    // This string will always be at the beginning of the name attribute of a Facebook OpenGraph meta tag.
    const FB_META_ID = "og:";
    
    // This string is the cURL user agent we will use.  Without a valid User Agent, any attempt to scrape a 
    // Facebook page will return an "Invalid Useragent" error.  Be careful though, some sites might ban you for doing this.
    const USER_AGENT = "Gecko/20050511 Firefox/1.0.4";
    
    // Improved media types:    
    const MEDIA_TYPE_WEBSITE = "website";
    const MEDIA_TYPE_ARTICLE = "article";
    const MEDIA_TYPE_VIDEO = "video";
    const MEDIA_TYPE_AUDIO = "audio";
    const MEDIA_TYPE_IMAGE = "image";
    const MEDIA_TYPE_SRC = "source_code";
    
    const FILE_EXT_IMAGE = "png|gif|bmp|jpg|jpeg";
    const FILE_EXT_FLASH = "swf";
    const FILE_EXT_SRC = "css|js";
    const FILE_EXT_VIDEO = "avi|mpg|mpeg|mp4|wmv|m2v|m4v|flv|fl4";
    const FILE_EXT_AUDIO = "mp3|ac3|aac|flac|wav|aiff|ra|rm|wav|ogg|wma";
    
    
    public function scrape();
    public static function file_get_contents_curl($url,$user_agent);
    public static function get_image_absolute_url($img,$lint_domain);
}

/**
 * The URL Debugger implements the Scraper interface to scrape the URL provided.
 * @TODO: Throw verbose exceptions on error 
 */

class URL_Debugger implements Scraper {
    
    // set the minimum width & height of images to be included in scraper results
    const IMAGE_MINIMUM_WIDTH = 5;
    const IMAGE_MINIMUM_HEIGHT = 5;

    // Setting save_tmp_image to true will attempt to save remote image files to the directory specified in $this->tmp_image_dir
    // This setting is useful if your web host has remote file access disabled for PHP functions like exif_imagetype() or getimagesize()
    public $save_tmp_image = false;
    
    // if save_tmp_image is set to true, web user must have read+write permissions for tmp_image_dir
    public $tmp_image_dir = "./tmp";
   
    // Setting $scrape_fb_tags_only to true will limit output to only report facebook's og:xxx meta tags in the document <head>.
    public $scrape_fb_tags_only = false;
    
    // Setting $get_extended_image_info to true will provide more detailed information for each image found.
    // This option is not recommended when a quick result is preferred, because
    // requesting the information for each image may take a considerable length of time.
    public $get_extended_image_info = false;
    
    protected $url;
    private $parsed_url;
    private $doc;
    private $html;
    
    protected $properties;
    protected $errors = array();
    
    /* 
     * Sets the URL to scrape and sanitizes it.
     * @param String $url : the URL to scrape
     */
    
    public function __construct($url) {
        $this->url = filter_var($url,FILTER_SANITIZE_URL);
        $this->parsed_url = parse_url($this->url);
    }
    
    public function __get($var) {
        return isset($this->{$var}) ? $this->{$var} : false;
    }
    
    /**
     * This function collects the scraped data into $this->properties as an array
     */
    public function scrape() {
        
        try {
            $this->html = $this->file_get_contents_curl($this->url,self::USER_AGENT);
        }
        catch (Exception $e) {
            $this->errors[] = $e->getMessage();
            return;
        }
            
        $this->doc = new DOMDocument();
        
        // using mb_convert_encoding here fixes the weird problem with UTF-8 encoding in PHP's DomDocument
        @$this->doc->loadHTML(mb_convert_encoding($this->html, 'HTML-ENTITIES', "UTF-8"));
        
        $meta_tags = $this->doc->getElementsByTagName('meta');
        $images = $this->doc->getElementsByTagName('img');

        $properties = $this->getMetaData($meta_tags);
        
        // Beyond this point is only used when we're scraping more than just what facebook provides
        // in their debug tool.
        if ($this->scrape_fb_tags_only !== true) {
            $properties['images'] = $this->getImages($images);
            $properties['title'] = (!isset($properties['title'])) ? $this->getTitle() : $properties['title'];
        }
        
        $this->properties = $properties;
    }
    
    
    /**
     * Using cURL instead of file_get_contents()gives us greater flexibility.
     * 
     * @param String $url : the URL to scrape (usually $this->url)
     * @param String $user_agent : the user agent to report (Usually self::USER_AGENT)
     * @return String $data : the HTML content retrieved via cURL. 
     */    
    public static function file_get_contents_curl($url,$user_agent) {
        $ch = curl_init($url);

        $curl_opts = array(
            // Don't use response header
            'CURLOPT_HEADER'            => false,
            // Return results as string
            'CURLOPT_RETURNTRANSFER'    => true,

            // Connection timeout, in seconds
            'CURLOPT_CONNECTTIMEOUT'    => 10,
            // Total timeout, in seconds
            'CURLOPT_TIMEOUT'           => 45,

            // Set a dummy useragent
            'CURLOPT_USERAGENT'         => $user_agent,

            // Follow Location: headers (HTTP 30x redirects)
            'CURLOPT_FOLLOWLOCATION'    => true,
            // Set a max redirect limit
            'CURLOPT_MAXREDIRS'         => 5,

            // Force connection close
            'CURLOPT_FORBID_REUSE'      => true,
            // Always use a new connection
            'CURLOPT_FRESH_CONNECT'     => true,

            // Turn off server and peer SSL verification.
            // Probably not the best solution to the SSL Errors.
            // @TODO:  Fix this.
            'CURLOPT_SSL_VERIFYPEER'    => false,
            'CURLOPT_SSL_VERIFYHOST'    => false,

            // Allow all encodings
            'CURLOPT_ENCODING'          => '*/*',
            'CURLOPT_AUTOREFERER'       => true
        );
        
        foreach($curl_opts as $name => $value) {
            curl_setopt($ch, constant($name), $value);
        }

        $data = curl_exec($ch);
        $curl_info = curl_getinfo($ch);
        $err = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        // If the URL was scraped and there were no errors
        if ($err == 0) {
            if ($curl_info['http_code'] == 200) {
                return $data;
            }
            else {
                throw new Exception($url . " returned HTTP response: " . $curl_info['http_code'], $err);
            }
        }
        else {
            // If we get here, there was an error:
            throw new Exception("Scraping " . $url . " failed: " . $error, $err);
        }

        return $data;
    }
    
    /**
     * Most img tags don't have an absolute URL, but for the purposes of downloading & storing, we need one.
     * This function constructs an absolute URL from a whatever is specified as the "src" attribute of an image tage
     * 
     * @param String $img : the src attribute of a <img> tag
     * @param String $lint_domain : A URL parsed with PHP's parse_url function
     * @return string : The absolute URL of the $img parameter.
     */
    public static function get_image_absolute_url($img,$lint_domain) {
        // if the URL of the image src attr is relative to the page path (instead of an absolute url that starts with http:// or https://)
    	// IMPORTANT:  Youtube likes to start img src attribute with // instead of http:// for some reason.  Way to use the same standards as everyone else, guys.
        $is_abs_path = false;
        if (strpos($img,"//")  !== 0 && strpos($img,"http")  !== 0) {
                // the url we're building needs to start with http://www.domain.com/
                $imgix = $lint_domain['scheme'] . "://" . $lint_domain['host'];
                // if the url is not relative to the document root on the server, it will be relative to the path of the page we're on.  
                // figure out where the image lives, and build the url path starting with /
                if (strpos($img,"/")  !== 0) {
                    $imgix = $imgix . $lint_domain['path'];
                } else $is_abs_path = true; // this is an absolute path already, we don't need the extra trailing slash.
                // add the training slash if needed.
                if (substr($imgix,(strlen($imgix)-1),1) != "/" && !$is_abs_path) $imgix .= "/";
                $img = $imgix . $img;
        }
        else if (strpos($img,"//") === 0) { $img = $lint_domain['scheme'] . ":" . $img; }

        return $img;
    }
    
    /**
     * Get an array of all images on the page, with their description (from alt attribute)
     * @param Array $images : an array of all the img tag nodes in the HTML returned by the file_get_contents_curl function
     * @return Array of images formatted properly for $this->properties 
     */
    private function getImages($images) {
        
        $arr_images = array();
        if (isset($this->properties['image'])) $arr_images[] = array($this->properties['image']);
        
        $i = count($arr_images);
        foreach ($images as $image) {
            $img_url = $this->get_image_absolute_url($this->getTagAttribute($image,'src'),$this->parsed_url);
            $arr_images[$i] = array(
                                'url' => $img_url,
                            );
            if ($this->getTagAttribute($image,'alt')) $arr_images[$i]['description'] = $this->getTagAttribute($image,'alt');
            if ($this->get_extended_image_info === true) {
                try {
                    $arr_info = $this->extended_image_info($img_url);
                }
                catch(Exception $e) {
                    $this->errors[] = $e->getMessage();
                }
            }
            else {
                // Just use the width & height info from the img tag in the html
                $arr_info = array(
                    'width'     => $this->getTagAttribute($image,'width') ? $this->getTagAttribute($image,'width') : "unknown",
                    'height'    => $this->getTagAttribute($image,'height') ? $this->getTagAttribute($image,'height') : "unknown"
                );
            }
            
            $arr_images[$i] = array_merge($arr_images[$i],$arr_info);
            
            // If the image does not meet mimimum height and width requirements, don't include it in results.
            if ((is_numeric($arr_images[$i]['width']) && $arr_images[$i]['width'] < self::IMAGE_MINIMUM_WIDTH) || (is_numeric($arr_images[$i]['height']) && $arr_images[$i]['height'] < self::IMAGE_MINIMUM_HEIGHT)) {
                unset($arr_images[$i]);
            }
            else {
                ++$i;
            }
        }
        
        return $arr_images;
    }
    
    
    /**
     * This function returns extended information about an image.
     * @param String $img : the URL of the image we're getting information about
     * @return Array $image_info : array containing extended image information width, height, mime type
     */
    private function extended_image_info($img) {
        $save_file = "";
        $reported_type = "";
        $actual_type = "";
        $mime_type = "";
        
        $pattern = "/(.*)\.(".self::FILE_EXT_IMAGE.")(\?.*)?/i";
        preg_match($pattern,$img,$image_type);
        $reported_type = $image_type[2];
        
        try {
            $image_file = $this->file_get_contents_curl($img,self::USER_AGENT);
        }
        catch(Exception $e) {
            throw $e;
            return;
        }
        
        if ($this->save_tmp_image == true) {
        
            if (!is_dir($this->tmp_image_dir)) {
                if (!mkdir($this->tmp_image_dir)) {
                    $this->errors[] = "Unable to save temp images to " . $this->tmp_image_dir . ".";
                    return array();
                }
            }
            $image_url = parse_url($img);
            $save_file = $this->tmp_image_dir . "/" . array_pop(explode("/",$image_url['path']));
            file_put_contents($save_file, $image_file);
            
            @list($width,$height,$actual_type) = getimagesize($save_file);
            unlink($save_file);
            
        } else {
            $image = imagecreatefromstring($image_file);
                $actual_type = exif_imagetype($image);
                $width = imagesx($image); // width
                $height = imagesy($image); // height
        }
        
        $mime_type = image_type_to_mime_type($actual_type);
        
        $image_info = array(
            'reported_type' => $reported_type,
            'actual_type'   => $actual_type,
            'mime_type'     => $mime_type,
            'width'         => $width,
            'height'        => $height
        );
        
        return $image_info;
    }
    
    /**
     * Get an attribute of the specified tag.
     * @param $tag : The HTML document node from which we want the attribute
     * @param $attr : The attribute whose contents we are reading
     * @return node's attribute content, or false if the node does not exist.
     */
    private function getTagAttribute($tag,$attr) {
        return ($tag->hasAttribute($attr)) ? $tag->getAttribute($attr) : false;
    }
    
    /**
     * Return the content of the document's <title> tag.
     * @return string : the title of the document, from the <title> tag in the document <head> 
     */
    private function getTitle() {
        $title_node = $this->doc->getElementsByTagName('title');
	return $title_node->item(0)->nodeValue;
    }
    
    /**
     *
     * @param type $metas : array of all <meta> tags from <head> of HTML document.
     * @return array of meta data formatted properly for $this->properties 
     */
    private function getMetaData($metas) {
        
        $meta_tag_arr = array();
        foreach ($metas as $meta) {
            if ($this->scrape_fb_tags_only === true) {
                if ($this->isOgTag($meta)) {
                    $tag_name = $this->getOgTagName($meta);
                    $content = $this->getTagAttribute($meta,'content');
                    $meta_tag_arr[$tag_name] = $content;
                }
            }
            else {
                $tag_name = ($this->isOgTag($meta)) ? $this->getOgTagName($meta) : $this->getMetaTagName($meta);
                $content = $this->getTagAttribute($meta,'content');
                if (!isset($meta_tag_arr[$tag_name]) && $tag_name != '' && isset($tag_name)) $meta_tag_arr[$tag_name] = $content;
            }
        }
        
        return $meta_tag_arr;
    }
    
    /**
     * Check whether a <meta> tag is an og:xxx facebook tag.
     * @param type $meta : the <meta> tag we are checking
     * @return boolean : true of the tag is og:xxx, false otherwise
     */
    private function isOgTag($meta) {
        return strpos($meta->getAttribute('property'),self::FB_META_ID) === 0;
    }
    
    /**
     * Gets the name of the og:xxx <meta> tag without the "og:" 
     * Any other colon (:) in the tag name is converted to "_"
     * @param $meta : the <meta> tag we are reading
     * @return string : the clean tag name. 
     */
    private function getOgTagName($meta) {
        return strtolower(str_replace(":","_",str_replace(self::FB_META_ID,"",$meta->getAttribute('property'))));
    }
    
    /**
     * Gets the meta tag name, replacing any colon (:) with "_"
     * @param $meta : the <meta> tag we are reading
     * @return string : the clean tag name 
     */
    private function getMetaTagName($meta) {
        $tag_name = ($meta->getAttribute('name') != "") ? strtolower($meta->getAttribute('name')) : strtolower($meta->getAttribute('property'));
        return str_replace(":","_",$tag_name);
    }
}
?>