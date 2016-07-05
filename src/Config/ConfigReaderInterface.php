<?php

namespace MailRu\QueueProcessor\Config;

/**
 * @author Mougrim <rinat@mougrim.ru>
 */
interface ConfigReaderInterface
{
    /**
     * @return array
     */
    public function getConfig();
}
