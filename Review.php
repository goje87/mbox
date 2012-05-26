<?php
class Review {
  public $url;
  public $movie;
  public $time;
  public $rating;
  public $pic;
  public $review;
  public $author;
  public $parsedAuthor;
  
  public function __construct($url, $movie, $time, 
                              $rating, $pic, $review, 
                              $author, $parsedAuthor) {
    $this->url = $url;
    $this->movie = $movie;
    $this->time = $time;
    $this->rating = $rating;
    $this->pic = $pic;
    $this->review = $review;
    $this->author = $author;
    $this->parsedAuthor = $parsedAuthor;
  }
                              
  public function pushToDb() {
    $data = array(
      "sourceUrl" => $this->url,
      "movieName" => $this->movie,
      "reviewTime" => $this->time->date,
      "rating" => $this->rating,
      "moviePic" => $this->pic,
      "reviewText" => $this->review,
      "author" => $this->author,
      "parsedAuthor" => $this->parsedAuthor);
      
    DB::insertInto("movieReviews", $data);
  }
}
?>