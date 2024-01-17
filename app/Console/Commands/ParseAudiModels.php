<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Models;
use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

class ParseAudiModels extends Command
{
    protected $signature = 'parse:audi-models';

    protected $description = 'Парсинг моделей ауди';

    public function handle(): void
    {
        $client = new Client();
        $response = $client->request('GET', 'https://www.drom.ru/catalog/audi/');
        $html = $response->getBody()->getContents();

        $crawler = new Crawler($html);

        $modelLinks = $crawler->filter('a.e64vuai0');

        $modelNames = [];
        $modelLinks->each(function ($node) use (&$modelNames) {
            $modelName = $node->filterXPath('//text()[not(ancestor::svg)]')->text();
            $modelHref = $node->attr('href');
            $this->info('Model Name: ' . $modelName . ', Href: ' . $modelHref);
            $modelNames[] = [
                'name' => $modelName,
                'href' => $modelHref,
            ];
        });

        foreach ($modelNames as $modelData) {
            $existingModel = Models::where('name', $modelData['name'])->first();

            if (!$existingModel) {
                Models::create([
                    'name' => $modelData['name'],
                    'url' => $modelData['href'],
                ]);
            }
        }

        $this->info('Модели сохранены в базе данных');
    }
}
