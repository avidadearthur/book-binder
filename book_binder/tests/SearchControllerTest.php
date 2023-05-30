<?php

namespace App\Tests;

use App\Controller\SearchController;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Panther\PantherTestCase;
class SearchControllerTest extends PantherTestCase
{
    public function testBookPage(): void
    {
        $id = 'a';
        $client = static::createPantherClient();
        $crawler = $client->request('GET', "/book-page/$id");

        // Check if expected content is NOT present on the page
        $this->assertStringContainsString('Join Meetups:', $crawler->filter('h3')->text());
    }




    public function testInvalidBookPage(): void
    {
        $id = 'a';
        $client = static::createPantherClient();
        $crawler = $client->request('GET', "/book-page/$id");

        // Check if expected content is NOT present on the page
        $this->assertStringNotContainsString('Join Meetups:', $crawler->filter('h3')->text());
    }




}