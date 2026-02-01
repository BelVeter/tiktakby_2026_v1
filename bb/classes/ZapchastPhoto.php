<?php


namespace bb\classes;


class ZapchastPhoto
{
    private $storage_dir_url;

    public $id;
    public $model_id;
    public $url;

    public function __construct($storage_dir_url="/bb/zapchast_photos/")
    {
        $this->storage_dir_url=$storage_dir_url;
    }

}