<?php
/**
 * @title DigitalOcean Spaces
 * @logo_url digital-ocean.png
 */

require_once __DIR__ . '/s3.php';

class Am_Storage_DigitalOceanSpaces extends Am_Storage_S3
{
    protected $_endpoints = [
        'NYC3' => 'nyc3.digitaloceanspaces.com',
        'SFO2' => 'sfo2.digitaloceanspaces.com',
        'SPG1' => 'spg1.digitaloceanspaces.com',
        'FRA1' => 'fra1.digitaloceanspaces.com',
    ];
    protected $_regions = [
        'NYC3' => 'New York City, United States',
        'SFO2' => 'San Francisco, United States',
        'SPG1' => 'Singapore',
        'FRA1' => 'Frankfurt, Germany',
    ];

    function getTitle()
    {
        return 'Digital Ocean Spaces';
    }

    function _initSetupForm(Am_Form_Setup $form)
    {
        $form->addText('access_key', ['class' => 'am-el-wide'])
            ->setLabel('Spaces Access Key')
            ->addRule('required')
            ->addRule('regex', 'must be alphanumeric', '/^[A-Z0-9]+$/');
        $form->addSecretText('secret_key', ['class' => 'am-el-wide'])
            ->setLabel('Spaces  Secret Key')
            ->addRule('required');

        $form->addSelect('region')
            ->loadOptions($this->_regions)
            ->setLabel('DigitalOcean Spaces Region');
        $form->addText('expire', ['size' => 5])
            ->setLabel('Video link lifetime, min');
        $form->setDefault('expire', 15);
        $form->addAdvCheckbox('use_ssl')
            ->setLabel(___("Use SSL for Authenticated URLs\n" .
                "enable this option if you use https for your site"));

        $msg = ___('Your content  should not be public.
            Please restrict public access to your files
            and ensure you can not access it directly from DigitalOcean.
            aMember use Access Key and Secret Key to generate links with
            authentication token for users to provide access them to your
            content on Spaces.');

        $form->addProlog(<<<CUT
<div class="info"><strong>$msg</strong></div>
CUT
        );
    }

    public function getDescription()
    {
        return $this->isConfigured() ?
            ___("Files located on DigitalOcean Spaces storage. (Warning: Your buckets should not contain letters in uppercase in its name)") :
            ___("DigitalOcean Spaces storage is not configured");
    }

    protected function getConnector()
    {
        if (!$this->_connector)
        {
            $this->_connector = new S3($this->getConfig('access_key'), $this->getConfig('secret_key'), true, $this->getEndpoint(), $this->getConfig('region'));
            $this->_connector->setRequestClass('S3Request_HttpRequest4');
        }

        return $this->_connector;
    }
}