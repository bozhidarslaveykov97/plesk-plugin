<?php
/**
* Author: Bozhidar Slaveykov
* @email: info@credocart.com
* Plesk auto app installer
*/

class Modules_Credocart_EventListener implements EventListener
{
    public function handleEvent($objectType, $objectId, $action, $oldValue, $newValue)
    {
    	// https://github.com/plesk/ext-aps-autoprovision/blob/master/src/plib/library/EventListener.php
    	
    	if ($action == 'phys_hosting_create') {
    		
    		$newInstallation = new Modules_Credocart_Install();
    		$newInstallation->setDomainId($objectId);
    		$newInstallation->setType('default');
    		$newInstallation->run();
    		
    	}
    }
}

return new Modules_Credocart_EventListener();
