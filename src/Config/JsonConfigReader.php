<?php

namespace MailRu\QueueProcessor\Config;

use RuntimeException;

/**
 * @author Mougrim <rinat@mougrim.ru>
 */
class JsonConfigReader extends AbstractFileConfigReader
{
    public function getConfig()
    {
        $configPath = $this->getConfigPath();
        $configString = file_get_contents($configPath);
        if ($configString === false) {
            throw new RuntimeException("Can't get config from file {$configPath}");
        }
        $config = json_decode($configString, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Can't parse json: [".json_last_error().'] '.json_last_error_msg());
        }

        return $config;
    }
}
