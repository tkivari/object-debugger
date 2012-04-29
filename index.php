<?php

    require_once('url_scraper.php');
    require_once('scraper_queue.php');
    
    echo '<pre>'; 
    
    // Try any of the following URLs yourself. If you stumble across a URL that doesn't work, please
    // Feel free to let me know!  Or better yet, fork the project, fix the problem, and share!
    // $url = "http://money.cnn.com/2011/10/15/markets/g20_europe_banks/index.htm?iid=Lead";
    // $url = "http://wagjag.com";
    // $url = "http://www.youtube.com/watch?v=3PDZTveY4uQ&feature=grec_index";
    // $url = "http://soundcloud.com/skrillex/narcissistic-cannibal-feat";
    $url = 'http://www.bbc.co.uk/news/world-europe-15325683';
    
    $scraper = new URL_Debugger($url);
    $scraper->get_extended_image_info = true;
    $scraper->save_tmp_image = true;
    $scraper->scrape();
    
    echo 'Scraping ' . $url . '...\n\n';
    
    print_r($scraper->properties); 
    print_r($scraper->errors);
    
    $url = 'http://www.youtube.com/watch?v=3PDZTveY4uQ&feature=grec_index';
    $scraper_fb = new URL_Debugger($url);
    
    $scraper_fb->scrape_fb_tags_only = true;
    $scraper_fb->save_tmp_image = true;
    
    $scraper_fb->scrape();
    
    echo 'Scraping ' . $url . ' for FB data...\n\n';
    
    print_r($scraper_fb->properties); 
    
    echo "<br/><br/>";
    
    $urls = array(
        'http://money.cnn.com/2011/10/15/markets/g20_europe_banks/index.htm?iid=Lead',
        'http://wagjag.com',
        'http://www.youtube.com/watch?v=3PDZTveY4uQ&feature=grec_index',
        'http://soundcloud.com/skrillex/narcissistic-cannibal-feat',
        'http://www.bbc.co.uk/news/world-europe-15325683'
    );
    
    $scraper_q = new Scraper_Queue($urls);
    
    $scraper_q->scrape_fb_tags_only = false;
    $scraper_q->save_tmp_image = true;
    
    $scraper_q->scrape_queue();
    
    echo 'Scraping URLs:<br/>' . implode('<br/>',$urls) . '<br/><br/>';
    
    print_r($scraper_q->arr_properties); 


?>
