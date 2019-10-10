<?php

class Modules_Microweber_WhmcsConnector
{

    protected $_logger;
    protected $_domainName;

    public function __construct()
    {
        $this->_logger = new Modules_Microweber_Logger();
    }

    public function setDomainName($name)
    {
        $this->_domainName = $name;
    }

    public static function updateWhmcsConnector()
    {

        $whmcsJson = [];
        $whmcsJson['url'] = pm_Settings::get('whmcs_url');
        $whmcsJson['whmcs_url'] = pm_Settings::get('whmcs_url');

        $whmcsJson = json_encode($whmcsJson, JSON_PRETTY_PRINT);

        $whmFilePath = Modules_Microweber_Config::getAppLatestVersionFolder() . '/userfiles/modules/whmcs_connector/settings.json';
        $whmFilePathCache = pm_ProductInfo::getPrivateTempDir() . '/whmcs_connector_settings_cache.json';

        file_put_contents($whmFilePathCache, $whmcsJson);

        var_dump($whmFilePathCache);
        var_dump($whmFilePath);
        die();
        pm_ApiCli::callSbin('copy_file.sh', [$whmFilePathCache, $whmFilePath]);

    }

    public function getSelectedTemplate()
    {

        $this->_logger->write('Get selected template for domain: ' . $this->_domainName);

        $template = 'dream';

        $url = Modules_Microweber_Config::getWhmcsUrl() . '/index.php?m=microweber_addon&function=get_domain_template_config&domain=' . $this->_domainName;

        $json = $this->_getJsonFromUrl($url);

        $this->_logger->write('Recived json for domain: ' . $this->_domainName . print_r($json, true));

        if (isset($json['template'])) {
            $template = $json['template'];
        }

        return $template;
    }

    private function _getJsonFromUrl($url, $postfields = [])
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);

        if (!empty($postfields)) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
        }

        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $data = curl_exec($ch);

        curl_close($ch);

        return @json_decode($data, true);
    }

}