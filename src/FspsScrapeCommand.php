<?php
namespace App;

use Symfony\Component\Console\Command\Command;
use Mediawiki\Api\FluentRequest;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Mediawiki\Api\MediawikiApi;

class FspsScrapeCommand extends Command {

    protected static $defaultName = 'fsps:scrape';

    protected function configure(): void {
        $this->setDescription('Download wikitext from ArchivesWiki.');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $api = MediawikiApi::newFromApiEndpoint('https://archives.org.au/api.php');
        $plg = new PageListGetter($api);
        $prefix = 'Fremantle Society Photographic Survey';
        $output->writeln('Fetching prefixed pages.');
        $pages = $plg->getFromPrefix($prefix);
        foreach ($pages->toArray() as $page) {
            $title = $page->getPageIdentifier()->getTitle()->getText();
            $output->writeln('Title: '.$title);
            $filename = trim(substr($title, strlen($prefix)), '_ —');
            if (empty($filename)) {
                $filename = 'index';
            }
            $filepath = dirname(__DIR__).'/data/fsps_groups/'.str_replace(' ', '_', $filename).'.txt';

            $contents = $api->getRequest(FluentRequest::factory()->setAction('query')
                ->setParam('prop', 'revisions')
                ->setParam('titles', $title)
                ->setParam('rvslots', '*')
                ->setParam('rvprop', 'content')
            );
            $pageResp = reset($contents['query']['pages']);
            $text = $pageResp['revisions'][0]['slots']['main']['*'];

            file_put_contents($filepath, $text);
            $output->writeln('Saved: '.$filepath."\n");
        }
        return Command::SUCCESS;
    }
}
