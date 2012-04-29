<?php

    require_once('url_scraper.php');
    
    /**
     * Scraper Queue: A class to implement scraping multiple URLs
     * 
     * The Scraper_Queue adds the ability to pass multiple URLs to the URL_Debugger class at once
     * and returns the information about each URL in a single array.
     *
     * @author Tyler Kivari ty.kivari@gmail.com
     * @copyright (c) 2012, Tyler Kivari
     * @license MIT license: http://www.opensource.org/licenses/mit-license.php
     *
     */
    
    class Scraper_Queue extends URL_Debugger {
        
        public $arr_properties = array();
        private $arr_urls = array();
        
        /*
         * Accepts an array of URLs and scrapes them, storing results in a single arr_properties array.
         * @param Array $url_array An array of URLs to scrape
         */
        public function __construct($urls) {
            $this->arr_urls = $urls;
        }
        
        public function scrape_queue() {
            for($i=0;$i<count($this->arr_urls);++$i) {
                parent::__construct($this->arr_urls[$i]);
                $this->scrape();
                $this->arr_properties[$i]['url'] = $this->url;
                $this->arr_properties[$i]['properties'] = $this->properties;
            }
        }
        
    }
?>
