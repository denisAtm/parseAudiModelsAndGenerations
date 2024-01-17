<?php

namespace App\Console\Commands;

use App\Models\Generation;
use App\Models\Models;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Symfony\Component\DomCrawler\Crawler;

class ParseAudiGenerations extends Command
{
    protected $signature = 'parse:audi-generations';
    protected $description = 'Парсинг поколений ауди';

    /** @noinspection UnknownColumnInspection */
    public function handle(): void
    {
        $client = new Client();

        $models = Models::all();

        foreach ($models as $model) {
            try {
                    $modelPageResponse = $client->request('GET', $model['url']);
                    $modelPageHtml = $modelPageResponse->getBody()->getContents();
                    $modelPageCrawler = new Crawler($modelPageHtml);

                    $modelPageCrawler->filter('.e1ei9t6a4')->each(function ($generationNode) use ($model) {
                        $market = $generationNode->filter('.css-112idg0')->text();
                        $generationNode->filterXPath('.//div[contains(@class, "e1ei9t6a0")]')->each(function ($generationItem) use ($market,$model) {
                            $modelNameElement = $generationItem->filter('.e1ei9t6a2')->first();
                            $modelNameText = $modelNameElement ? $modelNameElement->filterXPath('//text()[not(ancestor::svg)]')->text() : '';
                            $regex = '/^(?<modelName>.+?)\s((\d{2}\.\d{4})\s-\s((\d{2}\.\d{4})|(н\.в\.))|(\d{2}\.\d{4}\s-\sн\.в\.)|(\d{2}\.\d{4})|(н\.в\.))$/u';

                            if (preg_match($regex, $modelNameText, $matches)) {
                                $modelName = $matches['modelName'];
                                $startPeriod = $matches[3] ?? $matches[4] ?? '';
                                $endPeriod = $matches[4] ?? 'н.в.';
                                $period = $startPeriod ? "$startPeriod - $endPeriod" : '';
                            } else {
                                $modelName = $modelNameText;
                                $period = $modelNameText;
                            }

                            $generation = $generationItem->filter('[data-ftid="component_article_extended-info"] > div:first-child')->text();
                            $imageSrc = $generationItem->filter('.e1e9ee560 img')->attr('data-src');
                            $techSpecsLink = $generationItem->filter('.e1ei9t6a1')->attr('href');

                            $existingGeneration = Generation::where('imageSrc', $imageSrc)->first();

                            if (!$existingGeneration) {
                                $model->generations()->create([
                                    'market' => $market,
                                    'modelName' => $modelName,
                                    'period' => $period,
                                    'generation' => $generation,
                                    'imageSrc' => $imageSrc,
                                    'techSpecsLink' => $model['url'] . $techSpecsLink,
                                ]);
                            }
                        });
                    });
            } catch (\Exception $e) {
                $this->error('Error processing model ' . $model['name'] . ': ' . $e->getMessage());
            }
        }

        $this->info('Поколения сохранены в базе данных');
    }
}
