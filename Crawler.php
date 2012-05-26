<?php
include_once("simple_html_dom.php");
class Crawler {
  const REVIEW_AUTHOR = "Taran Adarsh";
  const INDEX_PAGE_PATTERN = "http://www.bollywoodhungama.com/movies/reviews/type/listing/sort/What's_New/page/";
  
  public static function start() {
    // self::processIndexPage(self::MAIN_INDEX_URL);
    
    for($i=1; $i<=5; $i++) {
      $indexPageUrl = self::INDEX_PAGE_PATTERN.$i;
      self::processIndexPage($indexPageUrl);
    }
    
    // self::processReviewPage("http://www.bollywoodhungama.com/moviemicro/criticreview/id/538351");
    
  }
  
  protected static function processIndexPage($url) {
    // Get page content
    $content = G87::makeRequest($url);
    $html = str_get_html($content);
    
    // Get list of review page URLs
    $pageLinks = $html->find(".movlstlititle a");
    foreach($pageLinks as $link) {
      $href = $link->href;
      $reviewUrl = Utils::getAbsoluteUrl($url, $href);
      self::processReviewPage($reviewUrl);
    }
    
  }
  
  protected static function processReviewPage($url) {
    // If the review already exists in database then no need to process
    if(self::reviewExistsForUrl($url)) return;
    
    Logger::info("processing review at $url");
    
    // Get page content
    $content = G87::makeRequest($url);
    Logger::info("Got response: ".strlen($content)." characters");
    $html = str_get_html($content);
    
    $root = $html->find("div[id=celeb_article_postview_tab]", 0);
    
    $movieName = $root->find("div.m0077", 0)->plaintext;
    Logger::info($movieName);
    
    $time_rating = $root->find(".m9090 .mfl");
    $timeData = Utils::trimSpaces($time_rating[0]->plaintext);
    
    $timeData = preg_replace("/&nbsp;/", " ", $timeData);
    
    preg_match("/By\s+([^,]+),/", $timeData, $matches);
    $author = $matches[1];
    
    $timeStr = preg_replace("/{$matches[0]}/", "", $timeData);
    $time = DateTime::createFromFormat("d M Y, H:i * T", trim($timeStr));
    Logger::info($time);
    
    $ratingData = $time_rating[1];
    $rating = $ratingData->find("img",0)->title;
    
    $moviePic = $html->find("img[src^=http://content.hungama.com/movie/display%20image/300x275%20jpeg]", 0)->src;
    
    $reviewText = $root->find(".mb_000", 0)->innertext;
    $reviewText = Utils::trimSpaces($reviewText);
    
    $review = new Review(
      $url,
      $movieName,
      $time,
      $rating,
      $moviePic,
      $reviewText,
      self::REVIEW_AUTHOR,
      $author);
    $review->pushToDb();
    
    self::publishReviewOnFacebook($review);
  }

  protected static function publishReviewOnFacebook($review) {
    include_once("facebook.php");
    try {
      $config = array(
        "appId" => "268756293222612",
        "section" => "a84ded2b43c866467d7adcec2e13f427");
      $fb = new Facebook($config);
      Facebook::$CURL_OPTS[CURLOPT_SSL_VERIFYPEER] = false;
      Facebook::$CURL_OPTS[CURLOPT_SSL_VERIFYHOST] = 2;
      
      $accessToken = "AAAD0brNjgNQBAA2HjFHcn4vEapENW56KzHuBYmmIDC4iFV1BLsQGW8VrX82snuZB8vjYpa1EcGqqxtKRDFu9TZBy8SUjQ8zikra61Df6g2b7Ez7Y19";
      // $accessToken = "AAAD0brNjgNQBALnsYHx7ZCvzVMUPZCF9nZC0T3YCsq5otZCLcYY4eu3gXDMf0JeSrEwcj3ZBdHUXGZBKbCYXTjfWzB8m18uT7xHGlgZBW1QjAZDZD";
      $pageId = "369781603070523";
      
      $pic = self::getPicForFacebook($review->pic);
      
      $params = array(
        "message" => "Got $review->author's review for $review->movie",
        "link" => $review->url,
        "picture" => $pic,
        "name" => "$review->movie",
        "caption" => "Rating: $review->rating | Source: Bollywood Hungama",
        "description" => substr($review->review, 0, 200)."...",
        "access_token" => $accessToken);
        
      Logger::info($params);
      
      $fb->api("$pageId/feed", "POST", $params);
    }
    catch(FacebookApiException $e) {
      $error = $e->getType().": ".$e->getMessage();
      Logger::error($error);
      Logger::error($e);
    }
  }
  
  protected static function getPicForFacebook1($pic) {
    
    $url = "http://test.goje87.com/downloader/download.php";
    Logger::info(G87::makeRequest($url));
    $hostedUrl = "http://test.goje87.com/downloader/files/$fileName";
    Logger::info($hostedUrl);
    return $hostedUrl;
  }

  protected static function getPicForFacebook($pic) {
    $url = "http://api.imgur.com/2/upload.json";
    $devKey = "82af2507e9380c562527b79921d98fd7";
    $config = (object) array(
      "type" => "POST",
      "params" => array(
        "key" => $devKey,
        "image" => $pic));
    $response = G87::makeRequest($url, $config);
    $response = json_decode($response);
    Logger::info($response);
    return $response->upload->links->original;
  }
  
  protected static function reviewExistsForUrl($url) {
    $query = "SELECT id FROM movieReviews WHERE sourceUrl = '$url'";
    $ret = DB::execQuery($query);
    
    return ($ret)?true:false;
  }
}
?>