<?php
namespace App;

use App\WikidataQuery;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class buildingsCommand extends Command {

    protected static $defaultName = 'buildings';

    protected function configure(): void {
        $this->setDescription('Get buildings data from Wikidata.');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $query = 'SELECT ?item ?itemLabel ?locatedOnStreet ?locatedOnStreetLabel ?streetNumber WHERE {
            ?item wdt:P31 wd:Q41176 .
            ?item wdt:P131 wd:Q1455046 .
            OPTIONAL { ?item wdt:P669 ?locatedOnStreet } .
            OPTIONAL { ?item p:P669/pq:P670 ?streetNumber } .
            SERVICE wikibase:label { bd:serviceParam wikibase:language "[AUTO_LANGUAGE],en" }
        }';
        $site = new Site(dirname(__DIR__));
        $query = new WikidataQuery($query);
        foreach ($query->fetch() as $result ) {
            if ( !isset($result['locatedOnStreetLabel']) || !isset($result['streetNumber'])) {
                $output->writeln("No address info for {$result['itemLabel']} {$result['item']}");
                continue;
            }
            $buildingName = $result['streetNumber'] . ' ' . $result['locatedOnStreetLabel'];
            $buildingId = str_replace(
                [' ', '–'],
                ['_', '-'],
                "/buildings/{$result['locatedOnStreetLabel']}/" . $buildingName
            );
            $page = new Page($site, $buildingId);
            $wikidataId = substr($result['item'], strlen('http://www.wikidata.org/entity/'));
            $meta = $page->getMetadata();
            if (isset($meta['wikidata']) && $meta['wikidata'] !== $wikidataId) {
                $output->writeln("Wikidata ID already exists but does not match for {$page->getId()}");
            }
            if (!isset($meta['wikidata'])) {
                $meta['template'] = 'building';
                $meta['wikidata'] = $wikidataId;
                $page->write($meta, $page->getContents());
            }
        }
        return Command::SUCCESS;
    }
}
