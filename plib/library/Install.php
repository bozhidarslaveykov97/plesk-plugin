<?php

/*
 * https://docs.plesk.com/en-US/onyx/api-rpc/about-xml-api/reference/managing-databases/creating-database-users/creating-multiple-database-users.34472/#creating-a-database-user
 */

class Modules_Microweber_Install {

    protected $_appLatestVersionFolder = '/usr/share/microweber/latest';
    protected $_overwrite = true;
    protected $_domainId;
    protected $_type = 'default';
    protected $_databaseDriver = 'mysql';
    
    public function setDomainId($id) {
        $this->_domainId = $id;
    }

    public function setType($type) {
        $this->_type = $type;
    }
    
    public function setDatabaseDriver($driver) {
    	$this->_databaseDriver = $driver;
    }

    public function run() {
        
    	// $this->_domainId = 6;
    	
        $domain = pm_Domain::getByDomainId($this->_domainId);
        
        if (empty($domain->getName())) {
            throw new \Exception('Domain not found.');
        } 
        
        $dbPrefix = rand(111,999);
        $dbNameLength = 15;
        $dbName = $dbPrefix . str_replace('.', '', $domain->getName());
        $dbName = substr($dbName, 0, $dbNameLength);
        $dbUsername = $dbName;
        $dbPassword = $this->_getRandomPassword(12);

        if ($this->_databaseDriver == 'mysql') {
        	
	        $dbManager = new Modules_Microweber_DatabaseManager();
	        $dbManager->setDomainId($domain->getId());
	
	        $newDb = $dbManager->createDatabase($dbName);
	
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
	        	$newUser = $dbManager->createUser($dbId, $dbUsername, $dbPassword);
	        }
	
	        if (isset($newUser['database']['add-db-user']['result']['errtext'])) {
	            throw new \Exception($newUser['database']['add-db-user']['result']['errtext']);
	        }
        }

        $domainDocumentRoot = $domain->getDocumentRoot();
        $domainName = $domain->getName();
        $domainIsActive = $domain->isActive();
        $domainCreation = $domain->getProperty('cr_date');
        
        // Clear domain files if exists
        pm_ApiCli::callSbin('clear_domain_folder.sh', [$domainDocumentRoot]);
       
        
        if ($this->_type == 'symlink') {
        	
        	// First we will make a directories
        	foreach ($this->_getDirsToMake() as $dir) {
        		pm_ApiCli::callSbin('create_dir.sh', [$domainDocumentRoot . '/' . $dir]);
        	}
        	
        	foreach ($this->_getFilesForSymlinking() as $folder) {
        		
        		$scriptDirOrFile = $this->_appLatestVersionFolder . '/' . $folder;
        		$domainDirOrFile = $domainDocumentRoot .'/'. $folder;
        		
        		$result = pm_ApiCli::callSbin('create_symlink.sh', [$scriptDirOrFile, $domainDirOrFile], pm_ApiCli::RESULT_FULL);
        		
        	}
        	
        	// And then we will copy files
        	foreach ($this->_getFilesForCopy() as $file) {
        		pm_ApiCli::callSbin('copy_file.sh', [$this->_appLatestVersionFolder . '/' . $file, $domainDocumentRoot . '/' . $file]);
        	}
        } else {
        	pm_ApiCli::callSbin('rsync_two_dirs.sh', [$this->_appLatestVersionFolder . '/', $domainDocumentRoot]);
        }
        
        $adminEmail = '1';
        $adminPassword = '1';
        $adminUsername = '1';
        
        if ($this->_databaseDriver == 'mysql') {
	        $dbHost = '127.0.0.1';
	        $dbPort = '3306';
        } else {
        	$dbHost = 'localhost';
        	$dbPort = '';
        	$dbName = $domainDocumentRoot . '/storage/database1.sqlite';
        }
        
        $installArguments = array();
        
        $installArguments[] =  $adminEmail;
        $installArguments[] =  $adminUsername;
        $installArguments[] =  $adminPassword;
        
        $installArguments[] = $dbHost;
        $installArguments[] = $dbName;
        $installArguments[] = $dbUsername;
        $installArguments[] = $dbPassword;
        $installArguments[] = $this->_databaseDriver;
        $installArguments[] = '-p mw_';
        $installArguments[] = '-t dream';
        $installArguments[] = '-d 1';
       // $installArguments[] = '-c 1';
        
        $installArguments = implode(' ', $installArguments);
		
        $command = $domainDocumentRoot . '/artisan microweber:install ' . $installArguments;
        
        $artisan = pm_ApiCli::callSbin('run_php.sh', [$command]);  
      	
        // Repair domain permission
        pm_ApiCli::callSbin('repair_domain_permissions.sh', [$domainName], pm_ApiCli::RESULT_FULL);
        
        return array('success'=>true, 'log'=> $artisan['stdout']);
        
    }
    
    private function _getDirsToMake() {
    	
    	$dirs = array();
    	
    	// Storage dirs
    	$dirs[] = 'storage';
    	$dirs[] = 'storage/framework';
    	$dirs[] = 'storage/framework/sessions';
    	$dirs[] = 'storage/framework/views';
    	$dirs[] = 'storage/cache';
    	$dirs[] = 'storage/logs';
    	$dirs[] = 'storage/app';
    	
    	// Bootstrap dirs
    	$dirs[] = 'bootstrap';
    	$dirs[] = 'bootstrap/cache';
    	
    	// User files dirs
    	$dirs[] = 'userfiles';
    	$dirs[] = 'userfiles/media';
    	$dirs[] = 'userfiles/modules';
    	$dirs[] = 'userfiles/templates';
    	
    	// Config dir
    	$dirs[] = 'config';
    	
    	return $dirs;
    }
    
    private function _getFilesForSymlinking() {
    	
    	$files = array();
    	
    	$files[] = 'vendor';
    	$files[] = 'src';
    	$files[] = 'resources';
    	$files[] = 'database';
    	$files[] = 'userfiles/modules';
    	$files[] = 'userfiles/templates';
    	$files[] = 'userfiles/elements';
    	
    	return $files;
    }
    
    /**
     * This is the files when symlinking app.
     * @return string[]
     */
    private function _getFilesForCopy() {
    	
    	$files = array();
    	
    	// Index
    	$files[] = 'version.txt';
    	$files[] = 'index.php';
    	$files[] = '.htaccess';
    	$files[] = 'favicon.ico';
    	$files[] = 'composer.json';
    	$files[] = 'artisan';
    	
    	// Config folder
    	$files[] = 'config/.htaccess';
    	$files[] = 'config/database.php';
    	$files[] = 'config/app.php';
    	$files[] = 'config/auth.php';
    	$files[] = 'config/cache.php';
    	$files[] = 'config/compile.php';
    	$files[] = 'config/filesystems.php';
    	$files[] = 'config/queue.php';
    	$files[] = 'config/services.php';
    	$files[] = 'config/view.php';
    	$files[] = 'config/workbench.php';
    	$files[] = 'config/hashing.php';
    	$files[] = 'config/mail.php';
    	$files[] = 'config/session.php';
    	
    	// Bootstrap folder
    	$files[] = 'bootstrap/.htaccess';
    	$files[] = 'bootstrap/app.php';
    	$files[] = 'bootstrap/autoload.php';
    	
    	return $files;
    }
    
    private function _getRandomPassword($length = 16)
    {
    	$alphabet = 'ghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
    	$pass = array();
    	$alphaLength = strlen($alphabet) - 1;
    	for ($i = 0; $i < $length; $i++) {
    		$n = rand(0, $alphaLength);
    		$pass[] = $alphabet[$n];
    	}
    	return implode($pass);
    }
}
