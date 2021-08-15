<?php
namespace sgroup\sitescripts;

use sgroup\sitescripts\base\PluginTrait;

use Craft;

use sgroup\sitemodule\base\Module;

class SiteScripts extends Module
{
    // Traits
    // =========================================================================

    use PluginTrait;

    
    // Public Methods
    // =========================================================================

    public function init()
    {
        parent::init();

        self::$plugin = $this;
    }

}