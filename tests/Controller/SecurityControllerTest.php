<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Security\LoginAuthenticator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Panther\PantherTestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityControllerTest extends PantherTestCase
{
    /**
     * @group PantherTestCase
     */
    public function testLogin(): void
    {
        $client = static::createPantherClient();

        // Simulate a GET request to the /logout page
        $crawler = $client->request('GET', '/logout');

        // Assert that the user is redirected to the home page
        $this->assertStringContainsString('/login', $client->getCurrentURL());

        $this->assertStringContainsString('Sign in', $client->getPageSource());

        // Fill in the login form
        $form = $crawler->filter('form.form-signin')->form();
        $form['email'] = 'test@test.com';
        $form['password'] = 'password123';

        // Submit the form
        $crawler = $client->submit($form);

        // Assert that the reading interest form submission was successful and redirected to the home page
        $this->assertStringContainsString('/', $client->getCurrentURL());

        // Assert that the home page is displayed
        $this->assertSelectorTextContains('h4', 'mystery');
    }

    /**
     * @group WebTestCase
     * @throws \Exception
     */
    public function testAuthenticate()
    {
        $client = static::createClient();
        $container = $client->getContainer();

        // Mock the necessary services
        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->expects($this->once())
            ->method('isTokenValid')
            ->willReturn(true);

        $authenticationUtils = $this->getMockBuilder(AuthenticationUtils::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Set the mock services in the container
        $container->set(CsrfTokenManagerInterface::class, $csrfTokenManager);
        $container->set(AuthenticationUtils::class, $authenticationUtils);

        // Create a request and set the necessary data
        $request = Request::create('/login', 'POST', [
            'email' => 'test@example.com',
            'password' => 'password123',
            '_csrf_token' => 'valid_csrf_token',
        ]);

        // Call the authenticate method
        $response = $client->getKernel()->handle($request);

        // Assertions
        $this->assertEquals(RedirectResponse::class, get_class($response));
    }

    /**
     * @group PantherTestCase
     */
    public function testOnAuthenticationSuccess()
    {
        // Create a Panther client to make the request
        $client = static::createPantherClient();

        // Mock the necessary services
        $token = $this->createMock(TokenInterface::class);
        $firewallName = 'main';
        $targetPath = '/login';
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);

        // Create an instance of the authenticator
        $authenticator = new LoginAuthenticator($urlGenerator);

        // Create a session and set the target path
        $session = new Session(new MockArraySessionStorage());
        $session->set('_security.'.$firewallName.'.target_path', $targetPath);

        // Create a request and set the session
        $request = Request::create('/dummy', 'GET');
        $request->setSession($session);

        // Call the onAuthenticationSuccess method
        $response = $authenticator->onAuthenticationSuccess($request, $token, $firewallName);

        // Assertions
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals($targetPath, $response->getTargetUrl());
    }

    /**
     * @group WebTestCase
     */
    public function testLoginPage()
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Welcome to BookBinder');
    }

    /**
     * @group WebTestCase
     */
    public function testLoginAction()
    {
        $client = static::createClient();

        $crawler = $client->request('POST', '/login', [
            'email' => 'test@test.com',
            'password' => 'password123',
        ]);

        $this->assertResponseRedirects();
    }

    /**
     * @group WebTestCase
     */
    public function testLogoutAction()
    {
        $client = static::createClient();
        $container = self::getContainer();
        $entityManager = $container->get('doctrine')->getManager();

        $userRepository = $entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => 'user1@example.com']);
        $this->assertInstanceOf(User::class, $user);
        $client->loginUser($user);

        $client->request('GET', '/logout');

        $this->assertResponseRedirects();
        // Add assertions for any other expected behavior after logout
    }

    /**
     * @group WebTestCase
     */
    public function testGoogleCheckAction()
    {
        $client = static::createClient();
        $container = self::getContainer();
        $entityManager = $container->get('doctrine')->getManager();

        // Delete the user if it exists
        $userRepository = $entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => 'shengzhe.lyu@gmail.com']);
        if ($user) {
            $entityManager->remove($user);
            $entityManager->flush();
        }

        $client->request('POST', '/login/google', [
            'credential' => 'eyJhbGciOiJSUzI1NiIsImtpZCI6IjJkOWE1ZWY1YjEyNjIzYzkxNjcxYTcwOTN
            jYjMyMzMzM2NkMDdkMDkiLCJ0eXAiOiJKV1QifQ.eyJpc3MiOiJodHRwczovL2FjY291bnRzLmdvb2ds
            ZS5jb20iLCJuYmYiOjE2ODU0NzA4MTEsImF1ZCI6Ijc3MzMxNTE5OTE4OC10MmJ1YWxuM2s2aWQxZ3Nv
            a3VzcXJ1ZXE1NWxrZzRlNC5hcHBzLmdvb2dsZXVzZXJjb250ZW50LmNvbSIsInN1YiI6IjEwMDAwNzIw
            NDI1MzQ4NjYyODc3NiIsImVtYWlsIjoic2hlbmd6aGUubHl1QGdtYWlsLmNvbSIsImVtYWlsX3Zlcmlm
            aWVkIjp0cnVlLCJhenAiOiI3NzMzMTUxOTkxODgtdDJidWFsbjNrNmlkMWdzb2t1c3FydWVxNTVsa2c0
            ZTQuYXBwcy5nb29nbGV1c2VyY29udGVudC5jb20iLCJuYW1lIjoiU2hlbmd6aGUgTHl1IiwicGljdHVy
            ZSI6Imh0dHBzOi8vbGgzLmdvb2dsZXVzZXJjb250ZW50LmNvbS9hL0FBY0hUdGROdV9NVmJoMXdUdXZJ
            QVUxMFA1UXRSdGdocGpISVhEYjJ0aFNYRmc9czk2LWMiLCJnaXZlbl9uYW1lIjoiU2hlbmd6aGUiLCJm
            YW1pbHlfbmFtZSI6Ikx5dSIsImlhdCI6MTY4NTQ3MTExMSwiZXhwIjoxNjg1NDc0NzExLCJqdGkiOiI4
            ZTk0ODM1N2Y1ZTk0YTM1M2I4MDM0ZTQxZDBlMDZiNmRmYzNhN2EzIn0.t0Yds-HU20pF8tPX2Zmg42kl
            e4TxCxUXMIrP2f2TGsDIgo7-boXunaxpdKhQYANHNBjlHEjuJ9s8YtbwjPV1JnknlkeVxa1NxgmXR6Ne
            3AGzaTQsdt1H5lWhMKIEvc0XVRkQyCz01ebDS-S7RCcr5X38n-jW9DWHiT5zed3da5TiQn7A5giKwJUG
            xfHjOfai-gX0UYELsatg4OzD0_ZBa9957oG8hH8ie7pipKkfmGxRbekTZDm8TutCM9NRyIafzk6pF6IU
            5MxSZcBZAb3InwDEeIR0-l0SfOpQAtyTyTI0sDEw_nGgBFKQTSVyqgVxo3VLS5kgL3NPeuWQsj4Wuw',
        ]);

        $this->assertNotSame(200, $client->getResponse()->getStatusCode());
    }
}
