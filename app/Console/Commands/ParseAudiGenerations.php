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

    public function handle()
    {
        $client = new Client();
        $response = $client->request('GET', 'https://www.drom.ru/catalog/audi/');
        $html = $response->getBody()->getContents();
        $crawler = new Crawler($html);

        $modelLinks = $crawler->filter('a.e64vuai0');

        $modelLinks->each(function ($modelLink) use ($client) {
            $modelName = $modelLink->filterXPath('//text()[not(ancestor::svg)]')->text();
            $modelHref = $modelLink->attr('href');
            $existingModel = Models::where('name', $modelName)->first();

            if ($existingModel) {
                $modelPageResponse = $client->request('GET', $modelHref);
                $modelPageHtml = $modelPageResponse->getBody()->getContents();
                $modelPageCrawler = new Crawler($modelPageHtml);

                $modelPageCrawler->filter('.e1ei9t6a4')->each(function ($generationNode) use ($existingModel, $modelHref) {
                    $market = $generationNode->filter('.css-112idg0')->text();
                    $generationNode->filterXPath('.//div[contains(@class, "e1ei9t6a0")]')->each(function ($generationItem) use ($existingModel, $market, $modelHref) {
                        $modelNameElement = $generationItem->filter('.e1ei9t6a2')->first();
                        $periodElement = $generationItem->filter('.e1ei9t6a2')->nextAll()->first();
                        $modelNameText = $modelNameElement ? $modelNameElement->filterXPath('//text()[not(ancestor::svg)]')->text() : '';
                        $regex = '/^(?<modelName>.+?)\s((\d{2}\.\d{4})\s-\s((\d{2}\.\d{4})|(н\.в\.))|(\d{2}\.\d{4}\s-\sн\.в\.)|(\d{2}\.\d{4})|(н\.в\.))$/';
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

                        $this->info('Market: ' . $market);
                        $this->info('Model Name: ' . $modelName);
                        $this->info('Period: ' . $period);
                        $this->info('Generation: ' . $generation);
                        $this->info('Image Source: ' . $imageSrc);
                        $this->info('Tech Specs Link: ' . $modelHref. $techSpecsLink);
                        $this->line('----------------------');

                        $existingGeneration = Generation::where('imageSrc', $imageSrc)->first();

                        if (!$existingGeneration) {
                            Generation::create([
                                'market' => $market,
                                'modelName' => $modelName,
                                'period' => $period,
                                'generation' => $generation,
                                'imageSrc' => $imageSrc,
                                'techSpecsLink' => $modelHref.$techSpecsLink,
                            ]);
                        }
                    });
                });
            }
        });

        $this->info('Поколения сохранены в базе данных');
    }
}
