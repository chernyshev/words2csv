<?php

namespace Words2CSV\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;

class PuzzleExportCommand extends Command
{
    private $config = null;
    private $client = null;
    private $limit = 50;

    const CSV_DELIMITER = ';';

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        $this->client = new \GuzzleHttp\Client(['cookies' => true]);
        $this->config = \Symfony\Component\Yaml\Yaml::parse(file_get_contents(__DIR__ . '/../../../config.yml'));
    }

    protected function configure()
    {
        $this
            ->setName('puzzle:export')
            ->setDescription('Экспотрирует слова из словаря puzzle-english.com');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln(['Логинимся']);

        $authForm = [
            'form_params' => [
                'email' => $this->config['puzzle_english']['login'],
                'password' => $this->config['puzzle_english']['password']
            ]
        ];

        $this->client->request('POST', 'https://puzzle-english.com/api/auth.php', $authForm);

        $output->writeln(['Загружаем данные']);

        $data = $this->getNewPageData(0);

        if (empty($data)) {
            $output->writeln("Ваш словарь пуст :(");
            return;
        }

        $totalCount = (int)$data[0]['total_count'];
        $totalPages = (int)$totalCount / $this->limit;

        $progress = new ProgressBar($output, $totalCount);

        $progress->start();

        $csvData = $csvRow = [];

        for ($i = 0; $i <= $totalPages; $i++) {

            if ($i != 0) {
                $data = $this->getNewPageData($i);
            }

            foreach ($data as $item) {

                $wordData = $this->getWordData($item['word']);

                $csvRow = [];
                $csvRow[0] = $item['word']; // front
                $csvRow[1] = $item['translation']; //back
                $csvRow[2] = $item['video_poster_url']; //image
                $csvRow[3] = $wordData['transcriptions'][$item['word']]['us'];

                $csvRow[4] = ''; //context
                $csvRow[5] = $item['speakers'][0]; //sound
                $csvRow[6] = ''; //text

                $csvData[] = implode(self::CSV_DELIMITER, $csvRow);
                $progress->advance();
            }
        }

        file_put_contents($this->config['puzzle_english']['csv_filename'], implode("\n", $csvData));
        $progress->finish();

        $output->writeln(["", "Готово!"]);
    }

    /**
     * @param $page
     * @return array
     */
    protected function getNewPageData($page)
    {
        $offset = $page * $this->limit;
        $response = $this->client->request('GET',
            "https://puzzle-english.com/api/dict/mywords.php?offset={$offset}&count={$this->limit}");
        $jsonResponse = $response->getBody()->getContents();

        $data = json_decode($jsonResponse, true);
        return $data;
    }

    protected function getWordData($word)
    {
        $response = $this->client->request('GET',
            "https://puzzle-english.com/api/dict/wordinfo.php?word={$word}");
        $jsonResponse = $response->getBody()->getContents();

        $data = json_decode($jsonResponse, true);
        return $data;
    }
}
