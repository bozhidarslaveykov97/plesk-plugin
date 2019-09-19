<?php

/*
 * https://docs.plesk.com/en-US/onyx/api-rpc/about-xml-api/reference/managing-databases/creating-database-users/creating-multiple-database-users.34472/#creating-a-database-user
 */

class Modules_Credocart_Install {

    protected $_scriptFolder = '/usr/share/credocart/latest/';
    protected $_overwrite = true;
    protected $_domainId;
    protected $_version;
    protected $_type = 'default';

    public function setDomainId($id) {
        $this->_domainId = $id;
    }

    public function setVersion($version) {
        $this->_version = $version;
    }

    public function setType($type) {
        $this->_type = $type;
    }

    public function run() {
        
        $domain = pm_Domain::getByDomainId($this->_domainId);

        if (empty($domain->getName())) {
            throw new \Exception('Domain not found.');
        } 

        $time = rand(111,999);

        $dbName = 'db_x' . $time;
        $dbUsername = 'user_x' . $time;
        $dbPassword = 'hs45i4m4';

        $manager = new Modules_Credocart_DatabaseManager();
        $manager->setDomainId($domain->getId());

        $newDb = $manager->createDatabase($dbName);

        if (isset($newDb['database']['add-db']['result']['errtext'])) {
            throw new \Exception($newDb['database']['add-db']['result']['errtext']);
        }

        if (isset($newDb['database']['add-db']['result']['id'])) {
            $dbId = $newDb['database']['add-db']['result']['id'];
        }

        if (!$dbId) {
            throw new \Exception('Can\'t create database.');
        }

        if ($dbId) {
            $newUser = $manager->createUser($dbId, $dbUsername, $dbPassword);
        }

        if (isset($newUser['database']['add-db-user']['result']['errtext'])) {
            throw new \Exception($newUser['database']['add-db-user']['result']['errtext']);
        }

        $domainDocumentRoot = $domain->getDocumentRoot();
        $domainName = $domain->getName();
        $domainIsActive = $domain->isActive();
        $domainCreation = $domain->getProperty('cr_date');
        
        // Clear domain files if exists
        pm_ApiCli::callSbin('clear_domain_folder.sh', [$domainDocumentRoot]);
       
        // Copy only for installation
        $filesForCopy = array();
        $filesForCopy[] = 'assets';
        $filesForCopy[] = 'app';
        $filesForCopy[] = 'vendor';
        $filesForCopy[] = 'routes';
        $filesForCopy[] = 'resources';
        $filesForCopy[] = 'Modules';
        $filesForCopy[] = 'Themes';
        $filesForCopy[] = 'storage';
        $filesForCopy[] = 'bootstrap';
        $filesForCopy[] = 'index.php';
        $filesForCopy[] = '.env.example';
        $filesForCopy[] = 'config';
        $filesForCopy[] = '.htaccess';
        $filesForCopy[] = 'artisan';

        foreach ($filesForCopy as $file) {
            pm_ApiCli::callSbin('copy_file.sh', [$this->_scriptFolder . '/' . $file, $domainDocumentRoot . '/' . $file]);
        }
        
        $adminFirstName = '';
        $adminLastName = '';
        $adminEmail = '';
        $adminPassword = '';
        $storeName = '';
        $storeEmail = '';

        $installArguments = array();
        $installArguments[] = '--db_name=' . $dbName;
        $installArguments[] = '--db_host=' . $dbHost;
        $installArguments[] = '--db_port=' . $dbPort;
        $installArguments[] = '--db_username=' . $dbUsername;
        $installArguments[] = '--db_password=' . $dbPassword;

        $installArguments[] = '--admin_first_name=' . $adminFirstName;
        $installArguments[] = '--admin_last_name=' . $adminLastName;
        $installArguments[] = '--admin_email=' . $adminEmail;
        $installArguments[] = '--admin_password=' . $adminPassword;

        $installArguments[] = '--store_name=' . $storeName;
        $installArguments[] = '--store_email=' . $storeEmail;

        $installArguments = implode(' ', $installArguments);


        $command = $domainDocumentRoot . '/artisan credocart:install ' . $installArguments;
        var_dump(pm_ApiCli::callSbin('run_php.sh', [$command]));  

        var_dump(pm_ApiCli::callSbin('repar_domain_permissions.sh', [$domainName], pm_ApiCli::RESULT_FULL));
        
        // Create symlinks
        $symlinkFolders = array();
        $symlinkFolders[] = 'config';
        $symlinkFolders[] = 'app';
        $symlinkFolders[] = 'vendor';
        $symlinkFolders[] = 'routes';
        $symlinkFolders[] = 'resources';
        $symlinkFolders[] = '.htaccess';
        $symlinkFolders[] = 'Modules';
        $symlinkFolders[] = 'Themes';
        $symlinkFolders[] = 'assets';
        
        $fileManager = new pm_FileManager($domain->getId());

        foreach ($symlinkFolders as $folder) {
            
            $scriptDirOrFile = $this->_scriptFolder . '/' . $folder;
            $domainDirOrFile = $domainDocumentRoot .'/'. $folder;
			
            if ($fileManager->isDir($domainDirOrFile)) {
                $fileManager->removeDirectory($domainDirOrFile);
            } else {
                $fileManager->removeFile($domainDirOrFile);
            }
            
            $result = pm_ApiCli::callSbin('create_symlink.sh', [$scriptDirOrFile, $domainDirOrFile], pm_ApiCli::RESULT_FULL);
            
            var_dump($result);
            
        }
        
        
    }

}
