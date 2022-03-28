<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Stopwatch\Stopwatch;
use GuzzleHttp\Client;
use RuntimeException;
use ZipArchive;

class GenerateCommand extends Command
{
    protected static $defaultName = 'generate';
    protected static $defaultDescription = 'Generates dat file with ip addresses';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $stopwatch = new Stopwatch();
        $downloadFileUrl =  $_ENV['DOWNLOAD_URL'];
        $zipDir = $_ENV['ZIP_DIR'];
        $zipFile = $_ENV['ZIP_FILE'];
        $unzipDir = $_ENV['UNZIP_DIR'];
        $unzipFile = $_ENV['UNZIP_FILE'];
        $filesDir = $_ENV['FILES_DIR'];
        $helper = $this->getHelper('question');

        if (file_exists($zipFile)) {
            $unzipFileDate = date("Y-m-d", filemtime($zipFile));
            $questionText = sprintf('Database modification date [%s] Download new?', $unzipFileDate);
        } else {
            $questionText = 'Database is missing. Download new?';
        }

        $output->writeln("");
        $question = new ChoiceQuestion($questionText, ['yes', 'no'], null);

        $answer = $helper->ask($input, $output, $question);
        if ('yes' === $answer) {
            $this->clearDir($zipDir);
            $client = new Client();
            $client->request('GET', $downloadFileUrl, ['sink' => $zipFile]);
            $unzipFileDate = date("Y-m-d", filemtime($zipFile));
            $output->writeln(sprintf('New Database loaded [%s]', $unzipFileDate));
        }
        if ('no' === $answer) {
            $unzipFileDate = date("Y-m-d", filemtime($zipFile));
            $output->writeln(sprintf('Used by Database [%s]', $unzipFileDate));
        }

        if (!file_exists($zipFile)) {
            throw new RuntimeException('Not found zip file');
        }

        $this->clearDir($unzipDir);

        $zip = new ZipArchive;
        $zip->open($zipFile);
        if (!$zip->extractTo($unzipDir)) {
            throw new RuntimeException('Unpacking error');
        }
        $zip->close();

        if (!file_exists($unzipFile)) {
            throw new RuntimeException('Not found unzip file');
        }

        $output->writeln("");
        $question = new ChoiceQuestion('Show list of available countries?', ['yes', 'no'], null);
        $answer = $helper->ask($input, $output, $question);
        if ('yes' === $answer) {

            if (false === ($readFile = fopen($unzipFile, "r"))) {
                throw new RuntimeException('Couldn\'t open unzip file');
            }

            $listCountries = [];
            while (false !== ($data = fgetcsv($readFile, 1000, ","))) {

                for ($c = 0; $c < count($data); $c++) {
                    $listCountries[$data[2]] = sprintf('[%s] - %s', $data[2], $data[3]);
                }
            }
            fclose($readFile);
            ksort($listCountries);
            foreach ($listCountries as $country) {
                $output->writeln($country);
            }
        }

        $output->writeln("");
        $question = new Question('Enter the desired countries separated by commas [AD,BG]: ', false);
        $answer = $helper->ask($input, $output, $question);
        if (!$answer) {
            throw new RuntimeException('You did not enter a list of countries');
        }

        $countriesInputArray = explode(",", $answer);
        foreach ($countriesInputArray as $k => $v) {
            $countriesInputArray[$k] = mb_strtoupper(trim($v));
        }

        $output->writeln("");
        $question = new ChoiceQuestion('Should these countries be included or excluded?', ['included', 'excluded'], null);
        $answer = $helper->ask($input, $output, $question);

        $this->clearDir($filesDir);

        if (false === ($readFile = fopen($unzipFile, "r"))) {
            throw new RuntimeException('Couldn\'t open unzip file');
        }


        if (false === ($writeFile = fopen($this->getOutputFile($answer, $countriesInputArray), 'w+b'))) {
            throw new RuntimeException('Couldn\'t open write file');
        }

        $countStr = 0;
        $stopwatch->start('execute');
        while (false !== ($data = fgetcsv($readFile, 1000, ","))) {

            $uniqData = [];
            foreach ($countriesInputArray as $country) {

                if ('included' === $answer) {
                    if ($data[2] === $country) {

                        $uniqData[$data[0]] = $data;
                        break;
                    }
                } elseif ('excluded' === $answer) {

                    if (in_array($data[2], $countriesInputArray)) {
                        break;
                    }

                    $uniqData[$data[0]] = $data;
                }
            }

            foreach ($uniqData as $uniq) {
                fwrite($writeFile, sprintf("%s-%s\n", long2ip($uniq[0]), long2ip($uniq[1])));
                $countStr++;
            }
        }

        fclose($writeFile);
        fclose($readFile);

        $output->writeln(sprintf('[%d] lines written to file', $countStr));
        $output->writeln("\nResult:");

        foreach ($this->listingDir($filesDir) as $file) {
            $output->writeln($file);
        }

        $stopwatch = $stopwatch->stop("execute");
        $output->writeln(
            sprintf(
                "\nDuration: [%4.3F] sec. Max.Memory: [%d] MB.",
                $stopwatch->getDuration() / 1000,
                $stopwatch->getMemory() / 1024 / 1024
            )
        );

        return Command::SUCCESS;
    }

    public function getOutputFile(string $answer, array $countries): string
    {

        return $_ENV['FILES_DIR'] . $answer . '-' . implode('-', $countries) . '.dat';
    }

    protected function clearDir($src): void
    {
        $dir = opendir($src);
        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                $full = $src . $file;
                if (is_dir($full)) {
                    $this->clearDir($full);
                } else {
                    unlink($full);
                }
            }
        }
        closedir($dir);
    }

    protected function listingDir($src): array
    {
        $out = [];
        $dir = opendir($src);
        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                $full = $src . $file;
                if (is_dir($full)) {
                    $this->clearDir($full);
                } else {
                    $out[] = $full;
                }
            }
        }
        closedir($dir);
        return $out;
    }
}
