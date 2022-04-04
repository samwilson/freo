<?php
namespace App;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

class FspsBuildCommand extends Command {

    protected static $defaultName = 'fsps:build';

    protected function configure(): void {
        $this->setDescription('Convert scraped wikitext to Markdown.');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $io = new SymfonyStyle($input, $output);

        foreach (glob(dirname(__DIR__).'/data/fsps_groups/*') as $file) {
            if (str_starts_with(basename($file), 'folder')
                || str_starts_with(basename($file), 'section')
                || basename($file) === 'todo.txt'
            ) {
                continue;
            }

            $text = file_get_contents($file);
            $io->writeln('Processing: '.$file);

            $metadata = ['template' => 'fsps_group'];
            $ingallery = false;
            $othertext = '';
            $files = [];
            foreach (explode("\n", $text) as $line) {
                if (substr($line, 0, 2) == '{{') {
                    $parts = array_filter(explode('|', trim($line, '{}')));
                    if ($parts[0] !== 'FSPS street') {
                        break;
                    }
                    if (!isset($parts[2])) {
                        dd($parts);
                    }
                    $metadata['title'] = $parts[1];
                    $metadata['photo_count'] = (int)$parts[2];
                    unset($parts[0], $parts[1], $parts[2]);
                    foreach ($parts as $part) {
                        list($param, $val) = explode('=', $part);
                        $param = trim($param);
                        if ($param === 'ident') {
                            continue;
                        }
                        $metadata[$param] = is_numeric($val) ? (int)$val : $val;
                    }
                } elseif (strpos($line, '<gallery') !== false) {
                        $ingallery = true;
                        continue;
                } elseif (!$ingallery) {
                    $othertext .= $line."\n";
                }
                if (strpos($line, '</gallery') !== false) {
                    $ingallery = false;
                    continue;
                }
                if ($ingallery) {
                    $pipePos = strpos($line, '|');
                    $imagefile = trim(($pipePos === false) ? $line : substr($line, 0, $pipePos));
                    if (empty($imagefile) || $imagefile === 'File:Empty.png' || $imagefile === 'empty.png' || $imagefile === 'X') {
                        continue;
                    }
                    if (substr($imagefile, 0, strlen('File:')) === 'File:') {
                        $imagefile = substr($imagefile, strlen('File:'));
                    }
                    $imagefile = str_replace(' ', '_', $imagefile);
                
                    $caption = ($pipePos !== false) ? trim(substr($line, $pipePos+1)) : null;
                    if (!empty($caption)) {
                        $caption = null;
                    }
                    $files[] = [
                        'filename' => $imagefile,
                        'caption' => $caption,
                    ];
                }
            }
            $metadata['description'] = trim($othertext) ? trim($othertext) : null;
            $metadata['files'] = $files;
            $yaml = Yaml::dump($metadata, 4, 4, Yaml::DUMP_NULL_AS_TILDE);
            $outfilepath = dirname(__DIR__).'/content/fsps/groups/'.pathinfo($file)['filename'].'.md';
            $output->writeln('Saving: '.$outfilepath);
            file_put_contents($outfilepath, "---\n$yaml---\n");
        }

        return Command::SUCCESS;
    }

}