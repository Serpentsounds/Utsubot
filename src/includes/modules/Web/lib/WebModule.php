<?php
/**
 * Utsubot - WebModule.php
 * Date: 01/05/2016
 */

declare(strict_types = 1);

namespace Utsubot\Web;
use Utsubot\ModuleException;
use Utsubot\Permission\ModuleWithPermission;


/**
 * Class WebModuleException
 *
 * @package Utsubot\Web
 */
abstract class WebModuleException extends ModuleException {}

/**
 * Class WebModule
 *
 * @package Utsubot\Web
 */
abstract class WebModule extends ModuleWithPermission {

    const separator = " \x02\x0304¦\x03\x02 ";

    /**
     * @return APIKeys
     * @throws \Utsubot\ModuleException
     */
    protected function getAPIKeys(): APIKeys {
        return $this->externalModule("Utsubot\\Web\\APIKeys");
    }

    /**
     * @param string $service
     * @return string
     * @throws APIKeysException
     */
    protected function getAPIKey(string $service): string {
        return $this->getAPIKeys()->getAPIKey($service);
    }    
    
}