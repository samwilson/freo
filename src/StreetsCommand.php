<?php
namespace App;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class StreetsCommand extends Command {

    protected static $defaultName = 'streets';

    protected function configure(): void {
        $this->setDescription('Get streets data from OSM and Wikidata.');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $bbox = '-32.07675654031872, 115.73779106140137, -32.039876235544234, 115.7687759399414';
        $query = '
            [out:json][timeout:25];
            (
              relation["type"="route"]["route"="road"](' . $bbox . ');
              way["highway"]["name"](' . $bbox . ');
            );
            out body;
            >;
            out skel qt;
        ';
        $url = 'https://overpass-api.de/api/interpreter?data=' . urlencode($query);
        $json = file_get_contents($url);
        if (!$json) {
            return Command::FAILURE;
        }
        $data = json_decode($json, true);
        $streets = [];
        foreach ($data['elements'] as $element) {
            if (!isset($element['tags']['name'])) {
                continue;
            }
            $name = $element['tags']['name'];
            if (stripos($name, 'State Route') !== false
                || stripos($name, 'Arcade') !== false
                || stripos($name, 'Highway Exit') !== false
            ) {
                // Ignore state route relations and arcades.
                continue;
            }
            if (!isset($streets[$name])) {
                $streets[$name] = [
                    'template' => 'street',
                ];
            }
            $streets[$name]['title'] = $name;
            if (!isset($streets[$name]['wikidata']) && isset($element['tags']['wikidata'])) {
                $streets[$name]['wikidata'] = $element['tags']['wikidata'];
            }
        }
        $site = new Site(dirname(__DIR__));
        foreach ($streets as $street) {
            $yaml = Yaml::dump($street, 4, 4, Yaml::DUMP_NULL_AS_TILDE);
            $filename = str_replace([' ', "'"], ['_', '-'], $street['title']);
            $outfilepath = dirname(__DIR__).'/content/streets/'.$filename.'.md';
            $output->writeln('Saving: '.$outfilepath);
            file_put_contents($outfilepath, "---\n$yaml---\n");

            $groupFile = dirname(__DIR__).'/content/fsps/groups/'.$filename.'.md';
            if (file_exists($groupFile)) {
                $output->writeln('Adding to FSPS group');
                $groupPage = new Page($site, '/fsps/groups/'.$filename);
                $groupMeta = $groupPage->getMetadata();
                $groupMeta['streets'] = [
                    $filename,
                ];
                $yaml2 = Yaml::dump($groupMeta, 4, 4, Yaml::DUMP_NULL_AS_TILDE);
                $output->writeln('Saving: '.$groupFile);
                file_put_contents($groupFile, "---\n$yaml2---\n");
            }
        }
        return Command::SUCCESS;
    }
}
