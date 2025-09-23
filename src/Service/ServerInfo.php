<?php
namespace App\Service;

use App\Entity\PhpBB\Forum;
use App\Repository\PhpBB\ForumRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use TurboLabIt\BaseCommand\Service\ProjectDir;
use TurboLabIt\BaseCommand\Traits\EnvTrait;


class ServerInfo
{
    const string DEFAULT = '--';
    const int BYTES_UNIT_CONVERSION = 1024;

    protected ?array $arrCpuInfo    = null;
    protected ?array $arrRamInfo    = null;
    protected ?array $arrPhpBBInfo  = null;

    use EnvTrait;

    public function __construct(
        protected ProjectDir $projectDir, protected EntityManagerInterface $em,
        protected ParameterBagInterface $parameters
    ) {}


    public function getServerInfo() : array
    {
        return [
            'Sistema operativo'             => $this->getOS(),
            'Kernel'                        => $this->getKernel(),
            'Uptime'                        => $this->getUptime(),
            'Versione PHP'                  => phpversion(),
            'Versione MySQL'                => $this->getDatabaseVersion(),
            'Server web'                    => $_SERVER['SERVER_SOFTWARE'] ?? static::DEFAULT,
            'CPU'                           => $this->getCpuModel(),
            'Numero core CPU'               => $this->getCpuNumCores(),
            'Carico CPU'                    => $this->getCpuLoad(),
            'RAM installata'                => $this->getRamIntalled(),
            'RAM libera'                    => $this->getRamFree(),
            'Swap'                          => $this->getSwap(),
            'Capacità disco'                => $this->getDiskCapacity(),
            'Spazio su disco disp.'         => $this->getDiskFreeSpace(),
            'Versione sito (git commit)'    => $this->getGitCommit(),
            'Ambiente'                      => $this->getEnv(),
            'Versione phpBB'                => $this->getPhpBBVersion(),
            'Versione Tapatalk'             => $this->getTapatalkVersion(),
        ];
    }


    //<editor-fold defaultstate="collapsed" desc="*** 🐧 OS version, uptime, ... ***">
    public function getOS(?string $default = null) : ?string
    {
        $lsbRelease = $this->getDumpedFile("lsb_release.txt");

        if( empty($lsbRelease) ) {
            return $default ?? static::DEFAULT;
        }

        $arrMatches = [];
        if( preg_match('/(?<=(Description:)).*/i', $lsbRelease, $arrMatches) ) {
            return trim($arrMatches[0]);
        }

        return $default ?? static::DEFAULT;
    }


    public function getKernel(?string $default = null) : ?string
        { return php_uname('s') . " " . php_uname('r') . ' (' . php_uname('m') . ')'; }


    protected function getUptime() : string
    {
        $str   = file_get_contents('/proc/uptime');
        $num   = floatval($str);
        $secs  = $num % 60;
        $num   = (int)($num / 60);
        $mins  = $num % 60;
        $num   = (int)($num / 60);
        $hours = $num % 24;
        $num   = (int)($num / 24);
        $days  = $num;

        return "$days giorni, $hours ore, $mins minuti e $secs secondi";
    }
    //</editor-fold>

    //<editor-fold defaultstate="collapsed" desc="*** 🗄️ Database ***">
    public function getDatabaseVersion(?string $default = null) : ?string
    {
        return
            $this->em
                ->getConnection()
                ->prepare('SELECT VERSION() AS v')
                ->executeQuery()
                ->fetchFirstColumn()[0];
    }
    //</editor-fold>

    //<editor-fold defaultstate="collapsed" desc="*** 🧠 CPU ***">
    public function getCpuModel(?string $default = null) : ?string
        { return$this->loadCpuInfo()['model'] ?? $default ?? static::DEFAULT; }

    public function getCpuNumCores(?string $default = null) : ?string
        { return $this->loadCpuInfo()['cores'] ?? $default ?? static::DEFAULT; }


    protected function loadCpuInfo() : array
    {
        if( $this->arrCpuInfo !== null ) {
            return $this->arrCpuInfo;
        }

        $this->arrCpuInfo = [];

        $arrCpuInfo = file('/proc/cpuinfo');
        foreach($arrCpuInfo as $line) {

            if(
                empty($this->arrCpuInfo["model"]) &&
                preg_match('/(?<=(^model name)).*/i', $line, $arrMatches)
            ) {
                $cpuModel = trim($arrMatches[0]);
                $cpuModel = trim($cpuModel, ':');
                $this->arrCpuInfo["model"] = trim($cpuModel);
            }

            if(
                empty($this->arrCpuInfo["cores"]) &&
                preg_match('/(?<=(^cpu cores)).*/i', $line, $arrMatches)
            ) {
                $cpuCores = trim($arrMatches[0]);
                $cpuCores = trim($cpuCores, ':');
                $this->arrCpuInfo["cores"] = trim($cpuCores);
            }

            if( count($this->arrCpuInfo) == 2 ) {
                break;
            }
        }

        return $this->arrCpuInfo;
    }


    public function getCpuLoad(?string $default = null) : ?string
    {
        $arrLoad    = sys_getloadavg();
        $cores      = (int)$this->getCpuNumCores();
        foreach($arrLoad as &$value) {

            $value /= $cores;
            $value  = round($value, 2) . '%';
        }

        return $arrLoad[0] . ' (1 min) | ' . $arrLoad[1] . ' (5 mins) | ' . $arrLoad[2] . ' (15 mins)';
    }
    //</editor-fold>

    //<editor-fold defaultstate="collapsed" desc="*** 📋 RAM ***">
    public function getRamIntalled(?string $default = null) : ?string
    {
        $ram = $this->loadRamInfo()['installed'] ?? null;

        if( !empty($ram) ) {
            $ram .= ' GB';
        }

        return $ram ?? $default ?? static::DEFAULT;
    }


    public function getRamFree(?string $default = null) : ?string
    {
        $ram = $this->loadRamInfo()['free'] ?? null;

        if( !empty($ram) ) {
            $ram .= ' GB';
        }

        return $ram ?? $default ?? static::DEFAULT;
    }


    public function getSwap(?string $default = null) : ?string
    {
        $swapTotal  = $this->loadRamInfo()['swapTotal'] ?? null;
        $swapFree   = $this->loadRamInfo()['swapFree'] ?? null;

        if( empty($swapTotal) || empty($swapFree) ) {
            return $default ?? static::DEFAULT;
        }

        $swap = $swapTotal - $swapFree;
        if( $swap > 1 ) {
            return "$swap GB";
        }

        $swap *= 1000;
        $swap = round($swap, 2);

        return "$swap MB";
    }


    protected function loadRamInfo() : array
    {
        if( $this->arrRamInfo !== null ) {
            return $this->arrRamInfo;
        }

        $this->arrRamInfo = [];

        $arrRamInfo = file('/proc/meminfo');
        $arrMap = [
            'installed'     => 'MemTotal',
            'free'          => 'MemAvailable',
            'swapTotal'     => 'SwapTotal',
            'swapFree'      => 'SwapFree'
        ];

        foreach($arrRamInfo as $line) {

            foreach($arrMap as $localValue => $meminfoNeedle) {

                if(
                    empty($this->arrRamInfo[$localValue]) &&
                    preg_match('/(?<=(^' . $meminfoNeedle  . ')).*/i', $line, $arrMatches)
                ) {
                    preg_match('/[0-9]+/', $arrMatches[0], $arrNumberOnly);
                    $ramValue = (float)$arrNumberOnly[0];
                    $this->arrRamInfo[$localValue] = round($ramValue / 1024 / 1024, 2);
                }
            }

            if( count($this->arrRamInfo) == count($arrMap) ) {
                break;
            }
        }

        return $this->arrRamInfo;
    }
    //</editor-fold>

    //<editor-fold defaultstate="collapsed" desc="*** 💾 Disk ***">
    public function getDiskCapacity(?string $default = null) : ?string
    {
        $disk = disk_total_space( $this->projectDir->getProjectDir() );
        return  $this->formatDiskSpace($disk);
    }


    public function getDiskFreeSpace(?string $default = null) : ?string
    {
        $disk = disk_free_space( $this->projectDir->getProjectDir() );
        return  $this->formatDiskSpace($disk);
    }


    protected function formatDiskSpace(float $disk) : string
    {
        $disk =
            $disk / static::BYTES_UNIT_CONVERSION / static::BYTES_UNIT_CONVERSION /
            static::BYTES_UNIT_CONVERSION / static::BYTES_UNIT_CONVERSION;

        $timesMultiplied = 0;
        while( $disk < 1 ) {

            $disk *= static::BYTES_UNIT_CONVERSION;
            $timesMultiplied++;
        }

        $disk = round($disk, 2);

        return
            match($timesMultiplied) {
                0       => "$disk TB",
                1       => "$disk GB",
                2       => "$disk MB",
                default => $disk
            };
    }
    //</editor-fold>


    //<editor-fold defaultstate="collapsed" desc="*** 🧾 TLI 2 version ***">
    public function getGitCommit(?string $default = null) : ?string
    {
        $gitCommit = $this->getDumpedFile("git-commit.txt");

        if( empty($gitCommit) ) {
            return $default ?? static::DEFAULT;
        }

        return $gitCommit;
    }
    //</editor-fold>

    //<editor-fold defaultstate="collapsed" desc="*** 🫂 phpBB version ***">
    public function getPhpBBVersion(?string $default = null) : ?string
        { return $this->loadPhpBBInfo()['version'] ?? $default ?? static::DEFAULT; }

    public function getTapatalkVersion(?string $default = null) : ?string
        { return $this->loadPhpBBInfo()['mobiquo_version'] ?? $default ?? static::DEFAULT; }


    protected function loadPhpBBInfo() : array
    {
        if( $this->arrPhpBBInfo !== null ) {
            return $this->arrPhpBBInfo;
        }

        /** @var ForumRepository $forumRepository */
        $forumRepository = $this->em->getRepository(Forum::class);

        return $this->arrPhpBBInfo = $forumRepository->getConfig();
    }
    //</editor-fold>


    //<editor-fold defaultstate="collapsed" desc="*** 💦 Internal functions ***">
    protected function getDumpedFile(string $filename) : ?string
    {
        $filePath = $this->projectDir->getVarDir('server-info-dump') . $filename;

        if( !file_exists($filePath) ) {
            return null;
        }

        return file_get_contents($filePath);
    }
    //</editor-fold>
}
