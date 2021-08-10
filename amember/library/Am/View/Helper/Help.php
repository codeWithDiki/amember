<?php


class Am_View_Helper_Help
{
    /**
     * Render inline HELP div for aMember admin area
     * by given help id string or for a plugin object
     * @param string|Am_Plugin_Base $id
     * @return string
     */
    function __invoke($id)
    {
        $di = Am_Di::getInstance();
        $params = [
            'root_url' => $di->config->get('root_url'),
            'root_surl' => $di->config->get('root_surl'),
            'root_dir' => ROOT_DIR,
        ];
        $readme = null;
        if (!is_string($id))
        {
            if ($id instanceof Am_Plugin_Base)
            {
                if ($id instanceof Am_Paysystem_Abstract)
                {
                    $type = "Payment";
                } elseif ($id instanceof Am_Protect_Abstract)
                {
                    $type = "Protect";
                } elseif ($id instanceof Am_Newsletter_Plugin)
                {
                    $type = "Newsletter";
                } elseif ($id instanceof Am_Storage)
                {
                    $type = "Storage";
                } elseif ($id instanceof Am_Softsale_Plugin_Abstract)
                {
                    $type = "Softsale";
                } elseif ($id instanceof Am_Invoice_Tax)
                {
                    $type = "Tax";
                } elseif ($id instanceof Am_Plugin)
                {
                    $type = "Misc";
                } else
                {
                    throw new \Exception("Unable to determine plugin type ".get_class($id));
                }

                $plId = preg_replace('#\__\d+$#', '', $id->getId());
                $plId = ucfirst(toCamelCase($plId));

                if (method_exists($id, 'getReadme'))
                {
                    if (($readme = $id->getReadme()) && strlen(trim($readme, " \t\n\r")))
                    {
                        $readme = str_replace(
                            [
                                '%root_url%',
                                '%root_surl%',
                                '%root_dir%',
                                'http://example.com/amember',
                                'https://example.com/amember',
                                '/var/www/html/amember',
                            ],
                            [
                                $root_url = Am_Di::getInstance()->config->get('root_url'),
                                $root_surl = Am_Di::getInstance()->config->get('root_surl'),
                                $root_dir = ROOT_DIR,
                                $root_url,
                                $root_surl,
                                $root_dir,
                            ],
                            $readme
                        );
                        $readme = "<div class=\"am-admin-help-readme\">$readme</div>";
                    }
                }

                $id = "PluginDocs/$type/$plId";
            } elseif ($id instanceof Am_Form_Setup ) {
                $data = $id->getData();
                if (empty($data['help-id']))
                    return null; // no readme
                $id = $data['help-id'];
            } else {
                throw new \Exception("Unknown class parent for help " . get_class($id));
            }
        }
        $url = self::helpUrl($id);
        $params = Am_Html::escape(json_encode($params));
        return <<<CUT
    <div class="am-admin-help-div" data-url="$url" data-params="$params">$readme</div>

CUT;
    }

    /**
     * Return Full HELP url based on topic passed
     * @param $topic
     * @return string
     */
    static function helpUrl($topic)
    {
        // transform old school anchrors to docusuarus
        // Setup/Global#Root_URL_and_License_Key => Setup/Global#root-url-and-license-key
        $topic = preg_replace_callback('/#.+$/', function($regs){
            return strtolower(str_replace('_', '-', $regs[0]));
        }, $topic);

        if (defined('AM_LOCAL_DEV_DOCS') && AM_LOCAL_DEV_DOCS)
            return 'http://localhost:3000/docs/' . $topic;
        else
            return 'https://docs.amember.com/docs/' . $topic . (AM_VERSION !== '6.3.6' ? '?v='.AM_VERSION : '');
    }

}