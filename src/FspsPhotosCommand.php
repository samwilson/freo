<?php
namespace App;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Process\Process;

class FspsPhotosCommand extends Command {

    protected static $defaultName = 'fsps:photos';

    protected function configure(): void {
        $this->setDescription('Create pages for each photo.');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $this->io = new SymfonyStyle($input, $output);

        $baseDir = dirname(__DIR__);
        $site = new Site($baseDir);
        foreach ($site->getPages() as $page) {
            $metadata = $page->getMetadata();
            if ($metadata['template'] !== 'fsps_group') {
                continue;
            }
            $this->io->section($page->getId());
            $groupId = basename($page->getId());

            $buildingFolder = false;

            foreach ($metadata['files'] as $photoNum => $fileInfo) {
                $photoId = '/fsps/photos/' . $groupId . '/' . ($photoNum + 1);
                $filename = $baseDir.'/content' . $photoId . '.md';

                $photoPage = new Page($site, $photoId);
                if (isset($photoPage->getMetadata()['buildings'][0]) && $photoPage->getMetadata()['buildings'][0]) {
                    $this->io->writeln('Already done: ' . $photoId . '  --  building: '.$photoPage->getMetadata()['buildings'][0].')');
                    continue;
                }

                if (!$buildingFolder) {
                    $buildingFolder = str_replace(' ', '_', $this->io->ask('Building folder name (without underscores):', $groupId));
                }

                preg_match('/.*(19[0-9]{2}).*/', $fileInfo['filename'], $yearMatches);
                $year = $yearMatches[1] ?? null;

                preg_match('/.*Nos?_([0-9-]+).*/i', $fileInfo['filename'], $streetNumMatches);
                $streetNum = $streetNumMatches[1] ?? '';

                $groupPage = new Page($site, '/fsps/groups/' . $groupId);
                $folder = 'Folder_' . str_pad($groupPage->getMetadata()['folder'], 2, '0');
                $displayUrl = "https://archive.org/download/FSPS1978/display/$folder/$groupId/". str_replace('.png', '_display.jpg', $fileInfo['filename']);

                $process = new Process(['firefox', $displayUrl]);
                $process->run();

                $possibleBuildingTitle = $streetNum . ' ' . str_replace('_', ' ', $buildingFolder);
                $buildingTitle = $this->io->ask( 'Building title from <info>' . $fileInfo['filename'] . '</info>:', $possibleBuildingTitle );
                $buildingIdPart = $buildingFolder . '/' . str_replace(' ', '_', $buildingTitle);

                $buildingMeta = [
                    'template' => 'building',
                    'title' => $buildingTitle,
                ];
                $photoMeta = [
                    'template' => 'fsps_photo',
                    'group' => $groupId,
                    'year' => $year,
                    'description' => null,
                    'buildings' => [
                        $buildingIdPart,
                    ],
                    'coordinates' => null,
                    'heading' => null,
                    'classification' => null,
                    'section' => null,
                    'cell' => null,
                    'film_roll' => null,
                    'files' => [
                        [
                            'filename' => $fileInfo['filename'],
                            'caption' => '',
                        ]
                    ],
                ];

                $this->writeFile($filename, $photoMeta);

                $buildingFilename = $baseDir.'/content/buildings/' . $buildingIdPart . '.md';
                $this->writeFile($buildingFilename, $buildingMeta);
            }
        }
        return Command::SUCCESS;
    }

    private function writeFile($filename, $metadata) {
        if (!is_dir(dirname($filename))) {
            mkdir(dirname($filename), 0755, true);
        }
        $yaml = Yaml::dump($metadata, 4, 4, Yaml::DUMP_NULL_AS_TILDE);
        file_put_contents($filename, "---\n$yaml---\n");
        $this->io->writeln($filename);
    }
}
