<?php
require 'ipdeny.php';

try {
    $ipdeny = new IPdeny();
    
    /**
     * Enable console out
     */
    //$ipdeny->debug = true;
    
    /**
     * Reset iptables
     */
    //$ipdeny->resetIptables();
    
    /**
     * Clear all downloaded zones
     */
    //$ipdeny->clear()
    
    /**
     * Downloaded zone/s
     */
    // caches downloads
    //$ipdeny->download('gb');               - single
    //$ipdeny->download(['us', 'gb']);       - multiple
    //$ipdeny->download();                   - all
    
    // always use fresh
    //$ipdeny->download('gb', true);         - single
    //$ipdeny->download(['us', 'gb'], true); - multiple
    //$ipdeny->download(null, true);         - all

    /**
     * Apply zone/s
     */
    // slow mode
    //$ipdeny->apply('gb');                  - single
    //$ipdeny->apply(['us', 'gb']);          - multiple
    //$ipdeny->apply();                      - all set within download
    
    // fast mode
    //$ipdeny->apply('gb', true);            - single
    //$ipdeny->apply(['us', 'gb'], true);    - multiple
    //$ipdeny->apply(null, true);            - all set within download

    /**
     * Chaining
     */
    //$ipdeny->clear()->download(['us', 'gb'])->apply(); //- clear, download and apply
    //$ipdeny->download()->apply(['us', 'gb']);          //- download all and apply
} catch (\Exception $e) {
    exit(get_class($e).': '.$e->getMessage());
}
