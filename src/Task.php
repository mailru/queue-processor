<?php

namespace MailRu\QueueProcessor;

class Task
{
    private $id;
    private $data;

    public function __construct($id, $data)
    {
        $this->id = $id;
        $this->data = $data;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getData()
    {
        return $this->data;
    }
}
