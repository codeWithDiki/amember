<?php
/**
 * @title Wasabi Cloud Object Storage
 * @desc Wasabi Hot Cloud Storage is enterprise class, tier-free, instantly available and allows you to store an infinite amount of data affordably
 * @logo_url wasabi.png
 */

require_once __DIR__ . '/s3.php';

class Am_Storage_Wasabi extends Am_Storage_S3
{
    protected $_endpoints = [
        'us-east-1' => 's3.us-east-1.wasabisys.com',
        'us-east-2' => 's3.us-east-2.wasabisys.com',
        'us-central-1' => 's3.us-central-1.wasabisys.com',
        'us-west-1' => 's3.us-west-1.wasabisys.com',
        'eu-central-1' => 's3.eu-central-1.wasabisys.com',
    ];
    protected $_regions = [
        'us-east-1' => 'Wasabi US East 1 (N. Virginia)',
        'us-east-2' => 'Wasabi US East 2 (N. Virginia)',
        'us-central-1' => 'Wasabi US Central 1 (Texas)',
        'us-west-1' => 'Wasabi US West 1 (Oregon)',
        'eu-central-1' => 'Wasabi EU Central 1 (Amsterdam)',
    ];

    function getTitle()
    {
        return 'Wasabi Cloud Object Storage';
    }

    function _initSetupForm(Am_Form_Setup $form)
    {
        $form->addText('access_key', ['class' => 'am-el-wide'])
            ->setLabel('Wasabi Access Key
            Can be created at Wasabi Console->Access Keys')
            ->addRule('required')
            ->addRule('regex', 'must be alphanumeric', '/^[A-Z0-9]+$/');
        $form->addSecretText('secret_key', ['class' => 'am-el-wide'])
            ->setLabel('Wasabi Secret Key
            Can be created at Wasabi Console->Access Keys')
            ->addRule('required');

        $form->addSelect('region')
            ->loadOptions($this->_regions)
            ->setLabel('Region');
        $form->addText('expire', ['size' => 5])
            ->setLabel('Video link lifetime, min');
        $form->addAdvCheckbox('use_ssl')
            ->setLabel(___("Use SSL for Authenticated URLs\n" .
                "enable this option if you use https for your site"));

        $form->setDefault('expire', 15);



        $msg = ___('Your content  should not be public.
            Please restrict public access to your files
            and ensure you can not access it directly from Wasabi.
            aMember uses Access Key and Secret Key to generate links with
            authentication token for users to provide access them to your
            content on Wasabi.');

        $form->addProlog(<<<CUT
<div class="info"><strong>$msg</strong></div>
CUT
        );
    }

    public function getDescription()
    {
        return $this->isConfigured() ?
            ___("Files located on Wasabi Cloud Storage. (Warning: Your buckets should not contain letters in uppercase in its name)") :
            ___("Wasabi Cloud Storage is not configured");
    }

    protected function getConnector()
    {
        if (!$this->_connector) {
            $this->_connector = new S3($this->getConfig('access_key'), $this->getConfig('secret_key'), true,
                $this->getEndpoint(), $this->getConfig('region'));
            $this->_connector->setRequestClass('S3Request_HttpRequest4');
        }

        return $this->_connector;
    }
}
