<?php
namespace App;

use GuzzleHttp\Client;
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
        // The area ID comes from the Overpass API (it's not the relation ID).
        $query = '
            [out:json][timeout:60];
            area(id:3611346296)->.freo;
            (
              relation["type"="route"]["route"="road"](area.freo);
              way["highway"]["name"](area.freo);
            );
            out body;
            >;
            out skel qt;
        ';
        $output->writeln("Fetching street data from OpenStreetMap");
        $url = 'https://overpass-api.de/api/interpreter?data=' . urlencode($query);
        $json = file_get_contents($url);
        if (!$json) {
            $output->write("Unable to decode: " . $json);
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
                || stripos($name, 'National Route') !== false
                || stripos($name, 'Arcade') !== false
                || stripos($name, 'Exit') !== false
                || stripos($name, 'PSP') !== false
            ) {
                // Ignore some types of streets/routes/etc.
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

            $filename = str_replace([' ', "'"], ['_', '-'], $street['title']);
            $outfilepath = dirname(__DIR__).'/content/streets/'.$filename.'.md';
            if (file_exists($outfilepath)) {
                $streetPage = new Page($site, '/streets/' . $filename);
                $metadata = array_merge($streetPage->getMetadata(), $street);
                $body = $streetPage->getBody();
            } else {
                $metadata = $street;
                $body = '';
            }
            $yaml = Yaml::dump($metadata, 4, 4, Yaml::DUMP_NULL_AS_TILDE);
            $output->writeln('Saving: '.$outfilepath);
            file_put_contents($outfilepath, "---\n$yaml---\n$body");

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
