<?php

namespace Galantis\Typesense\Classes;

use Galantis\Typesense\Classes\Vite;

class LoadAssets
{
    public function admin()
    {
        Vite::enqueueScript('galantis-typesense-script-boot', 'admin/start.js', array('jquery'), GALANTIS_TYPESENSE_VERSION, true);
    }
  
}
