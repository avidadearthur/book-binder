<?php

namespace App\Tests;

use App\Controller\MeetupRequestsController;
use App\Entity\MeetupRequests;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Panther\PantherTestCase;
use App\Entity\MeetupList;
use App\Entity\MeetupRequestList;
use App\Form\MeetupRequestFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use App\Api\GoogleBooksApiClient;


class MeetupRequestsControllerTest extends PantherTestCase
{
    public function testLoadOverviewPage(): void
    {
        // Mock the Security object
        $security = $this->getMockBuilder(Security::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Mock the getUser() method of Security to return a user object
        $user = new User();
        $security->method('getUser')
            ->willReturn($user);

        // Mock the EntityManagerInterface object
        $entityManager = $this->getMockBuilder(EntityManagerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Create the Panther client and make the request
        $client = static::createPantherClient();
        $crawler = $client->request('GET', '/meetup/overview');

        // Check if page loaded successfully
        $statusCode = $client->getInternalResponse()->getStatusCode();
        $this->assertTrue($statusCode >= 200 && $statusCode < 300);
        $this->assertSelectorTextContains('h5', 'Upcoming meetups');
    }








    public function testAcceptMeetupRequest(): void
    {
        $client = static::createPantherClient();
        $crawler = $client->request('GET', '/meetup/request/host/accept/{meetupRequestId}');

        // Check if page loaded successfully
        $statusCode = $client->getInternalResponse()->getStatusCode();
        $this->assertTrue($statusCode >= 200 && $statusCode < 300);
    }

    public function testJoinMeetupRequest(): void
    {
        $client = static::createPantherClient();
        $crawler = $client->request('GET', '/meetup/requests/list/join/{userId}/{meetupRequestId}');

        // Check if page loaded successfully
        $statusCode = $client->getInternalResponse()->getStatusCode();
        $this->assertTrue($statusCode >= 200 && $statusCode < 300);
    }



}