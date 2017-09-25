<?php

class DownloadException extends Exception
{
}
class InvalidIsoException extends Exception
{
}
class InvalidZoneException extends Exception
{
}
class InvalidZonesException extends Exception
{
}
class InvalidZoneTypeException extends Exception
{
}
class InvalidChecksumException extends Exception
{
}

/**
 * Requires
 * sudo apt-get install php7.0-zip
 */
class IPdeny
{
    private $ipdeny     = 'http://www.ipdeny.com/ipblocks/data/countries';
    private $workingDir = '';
    private $fresh      = false;
    private $zone       = null;
    private $zones      = [];
    private $checksums  = [];
    public $debug      = false;
    
    /**
     * @param string $workingDir
     */
    public function __construct($workingDir = './.ipblocks')
    {
        $this->workingDir = $workingDir;

        // check working directory
        if (!file_exists($this->workingDir)) {
            mkdir($this->workingDir, 0755, true);
        }
    }
    
    /**
     * @param string $url
     */
    private function httpGet($url = null)
    {
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_FAILONERROR, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    
            $data = curl_exec($ch);
            
            if (curl_errno($ch)) {
                throw new DownloadException(curl_error($ch));
            }
            curl_close($ch);
            
            return $data;
        } else {
            return file_get_contents($url);
        }
    }
    
    /**
     *
     */
    private function parseChecksums()
    {
        if (!empty($this->checksums) && is_array($this->checksums)) {
            echo $this->debug ? 'Checksums already populated, skipping.'.PHP_EOL : null;
            return $this->checksums;
        }
        
        echo $this->debug ? 'Fetching checksums: '.$this->ipdeny.'/MD5SUM'.PHP_EOL : null;
        $response = $this->httpGet($this->ipdeny.'/MD5SUM');
        $response = explode(PHP_EOL, trim($response));

        $checksums = [];
        array_walk($response, function ($val, $key) use (&$checksums) {
            $sum = explode(' ', $val);

            if (!in_array(trim($sum[2]), ['Copyrights.txt', 'MD5SUM'])) {
                list($iso, $zone) = explode('.', $sum[2], 2);
                $checksums[$iso] = [
                    'checksum' => $sum[0],
                    'zone' => $sum[2]
                ];
            }
        });

        return $this->checksums = $checksums;
    }
    
    /**
     *
     */
    private function downloadZone()
    {
        if (!array_key_exists($this->zone, $this->checksums)) {
            throw new InvalidIsoException($this->zone.' is not a supported country');
        }

        if (empty($this->checksums[$this->zone]['zone'])) {
            throw new InvalidZoneException($this->zone.' is not supported');
        }
        
        if ($this->fresh === false && file_exists($this->workingDir.'/'.$this->zone.'.zone')) {
            echo $this->debug ? 'Zone file ('.$this->zone.') already exists, skip fetch.'.PHP_EOL : null;
            return;
        }

        echo $this->debug ? 'Fetching zone: '.$this->ipdeny.'/'.$this->checksums[$this->zone]['zone'].PHP_EOL : null;
        $response = $this->httpGet($this->ipdeny.'/'.$this->checksums[$this->zone]['zone']);

        if (md5($response) != $this->checksums[$this->zone]['checksum']) {
            throw new InvalidChecksumException('Zone file checksum mismatch');
        }

        file_put_contents($this->workingDir.'/'.$this->checksums[$this->zone]['zone'], $response);
    }
    
    /**
     *
     */
    private function downloadAllZones()
    {
        if ($this->fresh === false && file_exists($this->workingDir.'/all-zones.tar.gz')) {
            echo $this->debug ? 'All zones file already exists, skip fetch.'.PHP_EOL : null;
            return;
        }
        
        echo $this->debug ? 'Fetching all zones: '.$this->ipdeny.'/all-zones.tar.gz'.PHP_EOL : null;
        $response = $this->httpGet($this->ipdeny.'/all-zones.tar.gz');

        if (md5($response) != $this->checksums['all-zones']['checksum']) {
            throw new InvalidChecksumException('All zone file checksum mismatch');
        }

        file_put_contents($this->workingDir.'/all-zones.tar.gz', $response);

        $p = new \PharData($this->workingDir.'/all-zones.tar.gz', RecursiveDirectoryIterator::SKIP_DOTS);
        $p->decompress();
        $p->convertToData(\Phar::ZIP);

        $zip = new \ZipArchive;
        $res = $zip->open($this->workingDir.'/all-zones.zip');
        if ($res === true) {
            $zip->extractTo($this->workingDir);
            $zip->close();
        }
        
        unlink($this->workingDir.'/all-zones.tar');
        unlink($this->workingDir.'/all-zones.zip');
        
        $it = new \FilesystemIterator($this->workingDir);
        foreach ($it as $zoneFile) {
            if (!in_array($zoneFile->getExtension(), ['zone', 'gz'])) {
                continue;
            }
            
            list($iso, $zone) = explode('.', $zoneFile->getFilename(), 2);
            
            if (md5_file($this->workingDir.'/'.$zoneFile->getFilename()) != $this->checksums[$iso]['checksum']) {
                throw new InvalidChecksumException($iso.'.zone file checksum mismatch');
            }
        }
    }
    
    /**
     * @param mixed $zone
     * @param bool  $fresh
     */
    public function download($zone = null, $fresh = false)
    {
        $this->parseChecksums();
        
        $this->fresh = $fresh;
        
        if (!is_null($zone)) {
            if (is_array($zone)) {
                foreach ($zone as $iso) {
                    $this->zone = $iso;
                    $this->zones[] = $iso;
                    $this->downloadZone();
                }
                return $this;
            }
            
            if (is_string($zone) && !is_numeric($zone)) {
                $this->zone = $zone;
                $this->zones[] = $zone;
                $this->downloadZone();
                return $this;
            }
            
            throw new InvalidZoneTypeException('Use only string or array');
        } else {
            $this->downloadAllZones();
        }

        return $this;
    }
        
    /**
     *
     */
    public function resetIptables()
    {
        echo $this->debug ? 'Backing up current iptables'.PHP_EOL : null;
        // backup iptables
        shell_exec("/sbin/iptables-save > {$this->workingDir}/iptables.".time().".bak");
        
        echo $this->debug ? 'Reseting iptables'.PHP_EOL : null;
        `/sbin/iptables -F`;
        `/sbin/iptables -X`;
        `/sbin/iptables -t nat -F`;
        `/sbin/iptables -t nat -X`;
        `/sbin/iptables -t mangle -F`;
        `/sbin/iptables -t mangle -X`;
        `/sbin/iptables -P INPUT ACCEPT`;
        `/sbin/iptables -P OUTPUT ACCEPT`;
        `/sbin/iptables -P FORWARD ACCEPT`;
        
        return $this;
    }
    
    /**
     * @param mixed $zone
     * @param bool  $fast
     */
    public function apply($zone = null, $fast = false)
    {
        if (!is_null($zone)) {
            if (is_array($zone)) {
                foreach ($zone as $iso) {
                    $this->zones[] = $iso;
                }
            } elseif (is_string($zone) && !is_numeric($zone)) {
                $this->zone = $zone;
                $this->zones[] = $zone;
            } else {
                throw new InvalidZoneTypeException('Use only string or array');
            }
        }
        
        if (empty($this->zones)) {
            throw new InvalidZonesException('Zones stack is not populated');
        }

        // reset current iptables
        $this->resetIptables();
    
        $ips = [];
        $whitelist = file_get_contents($this->workingDir.'/whitelist.zone');
        $whitelist = explode(PHP_EOL, trim($whitelist));
        foreach ($this->zones as $zone) {
            echo $this->debug ? 'Applying zone: '.$zone.PHP_EOL : null;
            $zone = file_get_contents($this->workingDir.'/'.$zone.'.zone');
            $ips = $ips+explode(PHP_EOL, trim($zone));
            // remove whitelisted
            $ips = array_diff($ips, $whitelist);
        }
        
        if ($fast) {
            echo $this->debug ? 'Fast mode enabled (tables is prebuilt)'.PHP_EOL : null;
            $iptables_save =
             '*mangle'.PHP_EOL
            .':PREROUTING ACCEPT [0:0]'.PHP_EOL
            .':INPUT ACCEPT [0:0]'.PHP_EOL
            .':FORWARD ACCEPT [0:0]'.PHP_EOL
            .':OUTPUT ACCEPT [0:0]'.PHP_EOL
            .':POSTROUTING ACCEPT [0:0]'.PHP_EOL
            .'COMMIT'.PHP_EOL
            .'#'.PHP_EOL
            .'*nat'.PHP_EOL
            .':PREROUTING ACCEPT [0]'.PHP_EOL
            .':INPUT ACCEPT [0:0]'.PHP_EOL
            .':OUTPUT ACCEPT [0:0]'.PHP_EOL
            .':POSTROUTING ACCEPT [0:0]'.PHP_EOL
            .'COMMIT'.PHP_EOL
            .'#'.PHP_EOL
            .'*filter'.PHP_EOL
            .':INPUT ACCEPT [951:1033081]'.PHP_EOL
            .':FORWARD ACCEPT [0:0]'.PHP_EOL
            .':OUTPUT ACCEPT [0:45517]';
        } else {
            echo $this->debug ? 'Fast mode disabled (rules individually added)'.PHP_EOL : null;
        }
        foreach ((array) $ips as $i => $ip) {
            echo 'Adding ('.($i+1).' of '.count($ips).'): '.$ip.PHP_EOL;
            if (!$fast) {
                `/sbin/iptables -A INPUT -s $ip -j DROP`;
                `/sbin/iptables -A OUTPUT -d $ip -j DROP`;
            } else {
                $iptables_save .= '-A INPUT -s '.$ip.' -j DROP'.PHP_EOL;
                $iptables_save .= '-A OUTPUT -d '.$ip.' -j DROP'.PHP_EOL;
            }
        }
        if ($fast) {
            $iptables_save .= 'COMMIT'.PHP_EOL;
            file_put_contents("{$this->workingDir}/iptables.bak", $iptables_save);
            // backup iptables
            shell_exec("/sbin/iptables-restore < {$this->workingDir}/iptables.bak");
            unlink("{$this->workingDir}/iptables.bak");
        }
        
        return true;
    }
    
    /**
     *
     */
    public function clear()
    {
        echo $this->debug ? 'Clearing downloaded zones'.PHP_EOL : null;
        $it = new \FilesystemIterator($this->workingDir);
        foreach ($it as $zoneFile) {
            if (in_array($zoneFile->getExtension(), ['bak']) || $zoneFile->getFilename() == 'whitelist.zone') {
                continue;
            }
            unlink($this->workingDir.'/'.$zoneFile->getFilename());
        }
        return $this;
    }
}
