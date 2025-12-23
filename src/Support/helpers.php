<?php

if (!function_exists('metakit')) {
    /**
     * Get MetaKit manager instance.
     *
     * @return \TunaSahincomtr\MetaKit\Services\MetaKitManager
     */
    function metakit()
    {
        return app(\TunaSahincomtr\MetaKit\Services\MetaKitManager::class);
    }
}

