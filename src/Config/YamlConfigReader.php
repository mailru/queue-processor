<?php

namespace MailRu\QueueProcessor\Config;

/**
 * @author Mougrim <rinat@mougrim.ru>
 */
class YamlConfigReader extends AbstractFileConfigReader
{
    public function getConfig()
    {
        if (!function_exists('yaml_parse_file')) {
            throw new \RuntimeException("yaml extension isn't loaded");
        }
        $config = yaml_parse_file($this->getConfigPath());
        if (!is_array($config)) {
            throw new \RuntimeException("Can't parse yaml or result isn't array: ".var_export($config, true));
        }

        return $config;
    }
}
