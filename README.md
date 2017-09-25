# Unofficial - IPdeny.com

A small PHP helper class which applies [IPdeny.com](http://www.ipdeny.com)'s country IP zones to iptables.

## ::Installation::

Requires `php5-zip / php7.0-zip` extension.

To install, git clone, download or copy & paste to your project. I may add as a composer lib if the need arises.


## ::Features::

 - It uses the [MD5SUM](http://www.ipdeny.com/ipblocks/data/countries/MD5SUM) file for reference and verifies all downloaded files
 - Downloads individual zones or all-zones.tar.gz based upon which zones you want to block
 - It checks all iso codes for a valid zone file
 - Uses `cURL` or fallbacks to `file_get_contents()`
 - Creates timestamped backups of iptables before applying
 - Whitelist.zone support
 - Fast or slow iptables rule apply modes
 - Console debug output

## ::Class Synopsis::

```
Methods
-------------

IPdeny {
    public __construct ( [ string $workingDir = './.ipblocks' ] )
    public IPdeny download ( [mixed $zone [, bool $fresh ]] )
    public IPdeny resetIptables ( void )
    public IPdeny clear ( void )
    public bool apply ( [mixed $zone [, bool $fast ]] )
}

Properties
-------------
IPdeny public $debug = false;
```

## ::Example Usage::

Below shows how you can use the class to apply country zones to your iptables.

```

try {
    $ipdeny = new IPdeny();
    
    /**
     * Enable debug output
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
    //$ipdeny->download('gb')               - single
    //$ipdeny->download(['us', 'gb'])       - multiple
    //$ipdeny->download()                   - all
    
    // always use fresh
    //$ipdeny->download('gb', true)         - single
    //$ipdeny->download(['us', 'gb'], true) - multiple
    //$ipdeny->download(null, true)         - all

    /**
     * Apply zone/s
     */
    // slow mode
    //$ipdeny->apply('gb')                  - single
    //$ipdeny->apply(['us', 'gb'])          - multiple
    //$ipdeny->apply()                      - all set within download
    
    // fast mode
    //$ipdeny->apply('gb', true)            - single
    //$ipdeny->apply(['us', 'gb'], true)    - multiple
    //$ipdeny->apply(null, true)            - all set within download

    /**
     * Chaining
     */
    //$ipdeny->clear()->download(['us', 'gb'])->apply() - clear, download and apply
    //$ipdeny->download()->apply(['us', 'gb'])          - download all and apply
    
} catch (\Exception $e) {
    exit(get_class($e).': '.$e->getMessage());
}

```


## ::Support::

Please [open an issue](https://github.com/lcherone/ipdeny/issues) for support.

## ::Contributing::

Please contribute using [Github Flow](https://guides.github.com/introduction/flow/). Create a branch, add commits, and [open a pull request](https://github.com/lcherone/ipdeny/compare/).

