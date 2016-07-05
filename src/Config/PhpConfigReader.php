<?php

namespace MailRu\QueueProcessor\Config;

use RuntimeException;

/**
 * @author Mougrim <rinat@mougrim.ru>
 */
class PhpConfigReader implements ConfigReaderInterface
{
    private $configPath;

    /**
     * @return string
     *
     * @throws RuntimeException
     */
    public function getConfigPath()
    {
        if ($this->configPath === null) {
            throw new RuntimeException('You should specify configPath');
        }

        return $this->configPath;
    }

    /**
     * @param string $configPath
     */
    public function setConfigPath($configPath)
    {
        $this->configPath = (string) $configPath;
    }

    public function getConfig()
    {
        /** @noinspection PhpIncludeInspection */
        return require $this->getConfigPath();
    }
}
