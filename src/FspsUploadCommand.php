<?php
namespace App;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use App\Site;

class FspsUploadCommand extends Command {

    protected static $defaultName = 'fsps:upload';

    /** @var SymfonyStyle */
    private $io;

    private $existingFiles = [];

    protected function configure(): void {
        $this->setDescription('Upload from the ArchivesWiki images directory to IA.');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $this->io = new SymfonyStyle($input, $output);

        // See what's already uploaded.
        $filesXml = 'https://archive.org/download/FSPS1978/FSPS1978_files.xml';
        $xmlData = file_get_contents($filesXml);
        if (!$xmlData) {
            $this->io->error('Unable to fetch list of existing files.');
            return Command::FAILURE;
        }
        // get existing files
        $xml = simplexml_load_string(file_get_contents($filesXml));
        foreach ($xml as $f) {
            $this->existingFiles['/' . $f['name']->__toString()] = $f->md5->__toString();
        }

        // Go through each group page and each file in each group.
        $site = new Site(dirname(__DIR__));
        foreach ($site->getPages() as $page) {
            $metadata = $page->getMetadata();
            if ($metadata['template'] !== 'fsps_group') {
                continue;
            }
            $this->io->section($page->getId());
            foreach ($metadata['files'] as $fileInfo) {
                $this->uploadFile($fileInfo['filename'], $metadata['folder'], $page->getId());
                $this->io->newLine();
            }
        }
        return Command::SUCCESS;
    }

    private function uploadFile($filename, $folderNum, $pageId) {
        $archivesWikiDir = '/path/to/archiveswiki/';
        $hash = md5($filename);
        $localFile = $archivesWikiDir . substr($hash, 0, 1) . '/' . substr($hash, 0, 2) .'/' . $filename;

        $folderName = 'Folder_' . str_pad($folderNum, 2, '0', STR_PAD_LEFT) . '/' . basename($pageId);
        $iaName = '/full/' . $folderName . '/' . $filename;
        $this->io->writeln('Local: ' . $localFile);
        $this->io->writeln('IA: ' . $iaName);

        // Full res.
        $this->upload($localFile, $iaName);

        $tmpDir = dirname(__DIR__) . '/tmp';

        // Thumbnail.
        $localThumbFilename = $tmpDir . '/thumb/' . substr($filename, 0, -4) . '_thumb.jpg';
        $iaThumbFilename = "/thumb/$folderName/" . basename($localThumbFilename);
        $this->makeThumb($localFile, $localThumbFilename, 300);
        $this->upload($localThumbFilename, $iaThumbFilename);

        // Display size.
        $localDisplayFilename = $tmpDir . '/display/' . substr($filename, 0, -4) . '_display.jpg';
        $iaDisplayFilename = "/display/$folderName/" . basename($localDisplayFilename);
        $this->makeThumb($localFile, $localDisplayFilename, 1200);
        $this->upload($localDisplayFilename, $iaDisplayFilename);
    }

    function makeThumb($localFile, $localThumbFilename, $width) {
        if (!file_exists($localThumbFilename)) {
            $displayProc = new Process(['convert', $localFile, '-resize', $width, $localThumbFilename]);
            $displayProc->mustRun();
        }
    }

    function upload($localFile, $iaName) {
        if (
            isset($this->existingFiles[$iaName])
            && $this->existingFiles[$iaName] === md5_file($localFile)
        ) {
            $this->io->writeln('Already uploaded: ' . $iaName);
            return;
        }
        $this->io->writeln('Uploading: ' . $iaName);
        $proc = new Process(['ia', 'upload', 'FSPS1978', $localFile, '--remote-name', $iaName, '--checksum']);
        $proc->run();
    }
}
