<?php

/*
 * https://docs.plesk.com/en-US/onyx/api-rpc/about-xml-api/reference/managing-databases/creating-database-users/creating-multiple-database-users.34472/#creating-a-database-user
 */

class Modules_Microweber_Install {

    protected $_appLatestVersionFolder = '/usr/share/microweber/latest';
    protected $_overwrite = true;
    protected $_domainId;
    protected $_type = 'default';

    public function setDomainId($id) {
        $this->_domainId = $id;
    }

    public function setType($type) {
        $this->_type = $type;
    }

    public function run() {
        
    	// $this->_domainId = 6;
    	
        $domain = pm_Domain::getByDomainId($this->_domainId);
        $domainFileManager = new pm_FileManager($domain->getId());
        
        if (empty($domain->getName())) {
            throw new \Exception('Domain not found.');
        } 
        
        $dbPrefix = rand(111,999);
        $dbNameLength = 15;
        $dbName = $dbPrefix . str_replace('.', '', $domain->getName());
        $dbName = substr($dbName, 0, $dbNameLength);
        $dbUsername = $dbName;
        $dbPassword = $this->_getRandomPassword(12);

        $manager = new Modules_Microweber_DatabaseManager();
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
       
        // First we will make a directories
        foreach ($this->_getDirsToMake() as $dir) {
        	pm_ApiCli::callSbin('create_dir.sh', [$domainDocumentRoot . '/' . $dir]);  
        }
        
        // And then we will copy files
        foreach ($this->_getFilesForCopy() as $file) {
        	pm_ApiCli::callSbin('copy_file.sh', [$this->_appLatestVersionFolder . '/' . $file, $domainDocumentRoot . '/' . $file]);
        }
        

        $adminEmail = '1';
        $adminPassword = '1';
        $adminUsername = '1';
        
        $dbDriver = 'mysql';
        $dbHost = '127.0.0.1';
        $dbPort = '3306';
        
        $installArguments = array();
        
        $installArguments[] =  $adminEmail;
        $installArguments[] =  $adminUsername;
        $installArguments[] =  $adminPassword;
        
        $installArguments[] = $dbHost;
        $installArguments[] = $dbName;
        $installArguments[] = $dbUsername;
        $installArguments[] = $dbPassword;
        $installArguments[] = $dbDriver;
        $installArguments[] = '-p mw_';
        $installArguments[] = '-t dream';
        $installArguments[] = '-d 1';
       // $installArguments[] = '-c 1';
        
        $installArguments = implode(' ', $installArguments);
		
        $command = $domainDocumentRoot . '/artisan microweber:install ' . $installArguments;
        
        pm_ApiCli::callSbin('run_php.sh', [$command]);  
      	
        if ($this->_type == 'symlink') {
	        
	        // Create symlinks
	        $symlinkFolders = array();
	        $symlinkFolders[] = 'src';
	        $symlinkFolders[] = 'vendor';
	        $symlinkFolders[] = 'resources';
	        
	        foreach ($symlinkFolders as $folder) {
	            
	        	$scriptDirOrFile = $this->_appLatestVersionFolder . '/' . $folder;
	            $domainDirOrFile = $domainDocumentRoot .'/'. $folder;
				
	            $result = pm_ApiCli::callSbin('create_symlink.sh', [$scriptDirOrFile, $domainDirOrFile], pm_ApiCli::RESULT_FULL);
	            
	        }
	        
	        $domainFileManager->filePutContents($domain->getDocumentRoot() . '/symlinked.txt', 'true');
        }
        
        $domainFileManager->filePutContents($domain->getDocumentRoot() . '/standalone.txt', 'true');
        
        pm_ApiCli::callSbin('repar_domain_permissions.sh', [$domainName], pm_ApiCli::RESULT_FULL); 
        
        return array('success'=>true, 'log'=> '');
        
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
    	//$dirs[] = 'userfiles';
    	//$dirs[] = 'userfiles/media';
    	//$dirs[] = 'userfiles/modules';
    	//$dirs[] = 'userfiles/templates';
    	
    	// Config dir
    	$dirs[] = 'config';
    	
    	return $dirs;
    }
    
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
    	
    	// App folders
    	$files[] = 'database';
    	$files[] = 'resources';
    	$files[] = 'src';
    	$files[] = 'tests';
    	$files[] = 'vendor';
    	$files[] = 'userfiles';
    	
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
