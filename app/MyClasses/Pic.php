<?php

namespace App\MyClasses;

class Pic
{
    private $srcSmall;
    private $srcBig;
    private $alt;
    private $title;

    /**
     * @return mixed
     */
    public function getSrcSmall()
    {
        return $this->srcSmall;
    }

    /**
     * @param mixed $srcSmall
     */
    public function setSrcSmall($srcSmall): void
    {
        $this->srcSmall = $srcSmall;
    }

    /**
     * @return mixed
     */
    public function getSrcBig()
    {
        return $this->srcSmall;
    }

    /**
     * @param mixed $srcBig
     */
    public function setSrcBig($srcBig): void
    {
        $this->srcBig = $srcBig;
    }

    /**
     * @return mixed
     */
    public function getSrc()
    {
        return $this->getSrcBig();

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
