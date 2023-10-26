<?php
namespace App;

use Symfony\Component\Console\Command\Command;
use Mediawiki\Api\FluentRequest;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Mediawiki\Api\MediawikiApi;
use Symfony\Component\Process\Process;

class FspsScrapeCommand extends Command {

    protected static $defaultName = 'fsps:scrape';

    protected function configure(): void {
        $this->setDescription('Download wikitext from ArchivesWiki.');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {

        // $files = array_filter(explode("\n", file_get_contents(__DIR__.'/../fsps_files.txt')));
        // //$archivesWikiDir = '/home/sam/backups/do-spaces/archiveswiki/';
        // $tot = count($files);
        // $i = 0;
        // foreach ($files as $title ) {
        //     $i++;
        //     $filename = str_replace(' ', '_', substr($title, strlen('File:')));
        //     $hash = md5($filename);
        //     $filePath = '/' . substr($hash, 0, 1) . '/' . substr($hash, 0, 2) .'/' . $filename;

        //     $output->writeln(sprintf('%4s/%s - %s', $i, $tot, $filePath));
        //     $proc = new Process(['rclone','copyto', "digitalocean-archiveswiki:archiveswiki$filePath", "digitalocean-freowiki:freowiki$filePath" ]);
        //     $proc->mustRun();
        //     // $output->writeln($proc->getOutput());
        //     // $output->writeln($proc->getErrorOutput());
        //     //exit();

        // }

        $api = MediawikiApi::newFromApiEndpoint('https://archives.org.au/api.php');
        $plg = new PageListGetter($api);
        $prefix = 'FSPS';
        //$output->writeln('Fetching prefixed pages.');
        $pages = $plg->getFromPrefix($prefix, 0);
        foreach ($pages->toArray() as $page) {
            $title = $page->getPageIdentifier()->getTitle()->getText();
            $output->writeln($title);
            // $filename = trim(substr($title, strlen($prefix)), '_ —');
            // if (empty($filename)) {
            //     $filename = 'index';
            // }
            // $filepath = '/home/sam/tmp/freowiki/'.str_replace(' ', '_', $title).'.txt';

            // $contents = $api->getRequest(FluentRequest::factory()->setAction('query')
            //     ->setParam('prop', 'revisions')
            //     ->setParam('titles', $title)
            //     ->setParam('rvslots', '*')
            //     ->setParam('rvprop', 'content')
            // );
            // $pageResp = reset($contents['query']['pages']);
            // $text = $pageResp['revisions'][0]['slots']['main']['*'];

            // file_put_contents($filepath, $text);
            // $output->writeln('Saved: '.$filepath."\n");
        }
        return Command::SUCCESS;
    }
}
