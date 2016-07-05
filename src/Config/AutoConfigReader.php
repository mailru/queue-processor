<?php

namespace MailRu\QueueProcessor\Config;

use RuntimeException;

/**
 * @author Mougrim <rinat@mougrim.ru>
 */
class AutoConfigReader implements ConfigReaderInterface
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

    /** @var ConfigReaderInterface */
    private $configReader;

    protected function getConfigReader()
    {
        if ($this->configReader === null) {
            $extension = pathinfo($this->getConfigPath(), PATHINFO_EXTENSION);
            switch ($extension) {
                case 'php':
                    $this->configReader = new PhpConfigReader();
                    $this->configReader->setConfigPath($this->getConfigPath());
                    break;

                case 'json':
                    $this->configReader = new JsonConfigReader();
                    $this->configReader->setConfigPath($this->getConfigPath());
                    break;

                case 'yml':
                case 'yaml':
                    $this->configReader = new YamlConfigReader();
                    $this->configReader->setConfigPath($this->getConfigPath());
                    break;

                default:
                    throw new RuntimeException("Config format {$extension} isn't supported");
            }
        }

        return $this->configReader;
    }

    public function getConfig()
    {
        return $this->getConfigReader()->getConfig();
    }
}
