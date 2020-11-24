<?php

namespace App\Commands;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DiscoverCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'netfind';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Discover network devices';

    protected static $requirements = [
        'nmcli', 'sed', 'ip', 'nmap'
    ];

    public function __construct()
    {
        parent::__construct();
        $this->addArgument('device',InputArgument::IS_ARRAY,
            "A space separated list of network devices to discover"
        );
        $this->addOption('delay','d',InputOption::VALUE_REQUIRED,
            "Time in milliseconds between discoveries",1000
        );
    }

    protected static function getMissingRequirements():?array {
        $missing = [];
        foreach (self::$requirements as $requirement) {
            $exists = `command -v $requirement`;
            if (!$exists) {
                $missing[] = $requirement;
            }
        }
        return empty($missing) ? null : $missing;
    }

    protected static function getExistingNetworks():array {

        $interfaces = explode("\n",trim(`nmcli device status | sed "1 d"`));

        array_walk($interfaces,function(&$item) {
            $item = explode("|",preg_replace("/\s{2,}/","|",trim($item)));
            if ($item[1] == 'loopback') {
                $item = null;
            }
        });

        return array_filter($interfaces);
    }

    protected function selectInterfaces():array {

        $interfaces = self::getExistingNetworks();

        if ($selected_interfaces = array_unique($this->argument('device'))) {
            foreach($selected_interfaces as $interface) {
                if (!in_array($interface,Arr::pluck($interfaces,0))) {
                    return [];
                }
            }
        } else {
            if ($this->input->isInteractive()) {
                $this->title("Select Networks");
            }
            $choices = array_map(function ($interface) {
                return preg_replace("/\s+/", " ", sprintf("%s %s (%s; %s)",
                    $interface[0],
                    $interface[3] !== '--'
                        ? " [{$interface[3]}]"
                        : "",
                    $interface[1],
                    $interface[2]
                ));
            }, $interfaces);
            $selected_interfaces = $this->choice(
                "Select one or more network interfaces, separated by comma",
                $choices,
                array_search('eth0', Arr::pluck($interfaces, 0)) ?: 0,
                10,
                true
            );
            array_walk($selected_interfaces, function (&$interface) use ($interfaces, $choices) {
                $interface = $interfaces[array_search($interface, $choices)][0];
            });
        }

        return $selected_interfaces;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $missingRequirements = self::getMissingRequirements();
        if ($missingRequirements) {
            $this->error(sprintf(
                "Netfind requires %s to be installed in order to run properly. Aborting.",
                $missingRequirements[0]
            ));
            return 1;
        }


        $interfaces = $this->selectInterfaces();
        if (empty($interfaces)) {
            $this->error(sprintf("There was no valid interface selected. Aborting."));
            return 1;
        }


        $this->info(sprintf("Selected interface(s): %s.", join(', ',$interfaces)));

        $devices = [];

        while (true) {

            foreach ($devices as &$device) {
                $device["status"] = sprintf("<error>down</error>");
            }

            if (trim(`whoami`) !== 'root') {
                $this->warn("To be able to collect mac address and manufacturer info this program must run as root!");
                sleep(10);
            }

            foreach ($interfaces as $interface) {
                $matches = null;
                if (preg_match('/inet ([0-9\.\/]+)/', trim(`ip address show dev $interface | grep inet`), $matches)) {
                    $results = explode("\n", trim(`nmap -P -sP {$matches[1]}`));
                    while (!str_starts_with(current($results), "Nmap done:")) {
                        if (empty(current($results))) {
                            exit;
                        }

                        if (str_starts_with(current($results), "Starting Nmap")) {
                            next($results);
                            continue;
                        }

                        preg_match("/Nmap scan report for" .
                            "\s?(.*) \(?([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{0,3}\.[0-9]{1,3})\)?/",
                            current($results), $matches);
                        $hostname = $matches[1];
                        $ip = $matches[2];
                        next($results);
                        preg_match("/Host is (\w+)/", current($results), $matches);
                        $status = $matches[1];
                        next($results);
                        if (preg_match('/MAC Address: ([0-9A-F:]+) \((.*)\)/', current($results), $matches)) {
                            $mac = $matches[1];
                            $manufacturer = $matches[2];
                            next($results);
                            $key = $mac;
                        } else {
                            $mac = "Unknown";
                            $manufacturer = "Unknown";
                            $key = $ip;
                        }

                        if (!array_key_exists($mac, $devices)) {
                            $devices[$key] = [
                                "mac" => $mac,
                                "discovered" => Carbon::now()->format('Y-m-d H:i:s'),
                                "status" => $status,
                                "interface" => $interface,
                                "ip" => $ip,
                                "hostname" => $hostname,
                                "manufacturer" => $manufacturer,
                            ];
                        } else {
                            $devices[$key]["status"] = $status == "up"
                                ? $status
                                : sprintf("<error>%s</error>",$status);
                            $devices[$key]["ip"] = $ip;
                            $devices[$key]["hostname"] = $hostname;
                        }
                    }
                }
            }
            system('clear');
            $this->info(Carbon::now()->format('Y-m-d H:i:s'),OutputInterface::VERBOSITY_VERBOSE);
            $this->table(["Mac Address","Discovered at","Status","Interface","Ip Address","Hostname","Manufacturer"],
                $devices);
            $this->info("Press CTRL+C to terminate execution");

            usleep($this->option('delay') * 1000);
        }
    }
}
