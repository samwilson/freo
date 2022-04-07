<?php
namespace App;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

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
            foreach ($metadata['files'] as $photoNum => $fileInfo) {
                $groupId = basename($page->getId());
                $filename = $baseDir.'/content/fsps/photos/' . $groupId . '/' . ($photoNum + 1) . '.md';

                preg_match('/.*(19[0-9]{2}).*/', $fileInfo['filename'], $yearMatches);
                $year = $yearMatches[1] ?? null;

                $photoMeta = [
                    'template' => 'fsps_photo',
                    'group' => $groupId,
                    'year' => $year,
                    'description' => null,
                    'places' => [
                        ''
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
                if (!is_dir(dirname($filename))) {
                    mkdir(dirname($filename), 0755, true);
                }
                $yaml = Yaml::dump($photoMeta, 4, 4, Yaml::DUMP_NULL_AS_TILDE);
                file_put_contents($filename, "---\n$yaml---\n");
                $this->io->writeln($filename);
            }
        }
        return Command::SUCCESS;
    }
}
