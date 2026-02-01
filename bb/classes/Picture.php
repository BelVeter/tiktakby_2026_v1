<?php

namespace bb\classes;

class Picture
{
  private $src;
  private $alt;
  private $title;

  /**
   * @param $src
   * @param $alt
   * @param $title
   */
  public function __construct($src, $alt, $title)
  {
    $this->src = $src;
    $this->title = $title;
    $this->alt = $alt;
  }

  /**
   * @return mixed
   */
  public function getSrc()
  {
    return $this->src;
  }

  /**
   * @param mixed $src
   */
  public function setSrc($src): void
  {
    $this->src = $src;
  }

  /**
   * @return mixed
   */
  public function getAlt()
  {
    return $this->alt;
  }

  /**
   * @param mixed $alt
   */
  public function setAlt($alt): void
  {
    $this->alt = $alt;
  }

  /**
   * @return mixed
   */
  public function getTitle()
  {
    return $this->title;
  }

  /**
   * @param mixed $title
   */
  public function setTitle($title): void
  {
    $this->title = $title;
  }



}
