<?php
/**
 * Utsubot - APIKeyManager.php
 * Date: 01/05/2016
 */

declare(strict_types = 1);

namespace Utsubot\Web;
use Utsubot\{
    IRCBot,
    IRCMessage,
    Trigger
};
use function Utsubot\bold;


/**
 * Class APIKeyManagerException
 *
 * @package Utsubot\Web
 */
class APIKeysException extends WebModuleException {}

/**
 * Class APIKeys
 *
 * @package Utsubot\Web
 */
class APIKeys extends WebModule {

    private $interface;
    private $APIKeys;


    /**
     * APIKeyManager constructor.
     *
     * @param IRCBot $IRCBot
     */
    public function __construct(IRCBot $IRCBot) {
        parent::__construct($IRCBot);
        $this->interface = new APIKeysDatabaseInterface();

        $this->loadAPIKeys();

        $this->addTrigger(new Trigger("apikeys", array($this, "APIKeys")));
    }

    /**
     * @param IRCMessage $msg
     * @throws APIKeysException
     * @throws \Utsubot\Accounts\ModuleWithAccountsException
     * @throws \Utsubot\ModuleException
     *
     * @usage !apikeys add <service> <key>
     * @usage !apikeys remove <service>
     * @usage !apikeys reload
     */
    public function APIKeys(IRCMessage $msg) {
        $this->requireLevel($msg, 100);
        $this->requireParameters($msg, 1);

        $parameters = $msg->getCommandParameters();
        $mode = array_shift($parameters);

        switch ($mode) {
            case "add":
                $this->requireParameters($msg, 3);
                list($service, $key) = $parameters;
                $this->addAPIKey($service, $key);

                $return = sprintf(
                    "%s has been added as an API key for %s.",
                    bold($key),
                    bold($service)
                );
                break;

            case "remove":
                $this->requireParameters($msg, 2);
                $service = array_shift($parameters);
                $this->removeAPIKey($service);

                $return = sprintf(
                    "API key for %s has been removed.",
                    bold($service)
                );
                break;

            case "reload":
                $this->loadAPIKeys();
                
                $return = "API keys have been successfully reloaded.";
                break;
            
            default:
                throw new APIKeysException("Unknown mode '$mode'. Valid modes are 'add', 'remove', and 'reload'.");
                break;
        }
        
        if ($return)
            $this->respond($msg, $return);

    }

    /**
     * @param string $service
     * @param string $key
     * @throws APIKeysDatabaseInterfaceException
     */
    private function addAPIKey(string $service, string $key) {
        $this->interface->insertAPIKey($service, $key);
        $this->loadAPIKeys();
    }

    /**
     * @param string $service
     * @throws APIKeysDatabaseInterfaceException
     */
    private function removeAPIKey(string $service) {
        $this->interface->deleteAPIKey($service);
        $this->loadAPIKeys();
    }

    /**
     * Reload web service API keys from the database
     */
    private function loadAPIKeys() {
        $results = $this->interface->getAPIKeys();
        $this->APIKeys = [ ];
        
        foreach ($results as $row)
            $this->APIKeys[$row['service']] = $row['key'];
    }

    /**
     * Return an API key from the cache
     *
     * @param string $service
     * @return string
     * @throws APIKeysException Key not loaded
     */
    public function getAPIKey(string $service): string {
        if (!isset($this->APIKeys[$service]))
            throw new APIKeysException("No API key cached for '$service'.");
        
        return $this->APIKeys[$service];
    }
}