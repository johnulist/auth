<?php
/**
 * SocialConnect project
 * @author: Patsura Dmitry https://github.com/ovr <talk@dmtry.me>
 */

use Cache\Adapter\Doctrine\DoctrineCachePool;

error_reporting(-1);
ini_set('display_errors', 1);

include_once __DIR__ . '/../vendor/autoload.php';
include_once __DIR__ . '/vendor/autoload.php';
$configureProviders = include_once 'config.php';

/**
 * Why we need cache for Auth Providers?
 * Providers like OpenID & OpenIDConnect require as
 * to request OpenID specification (and JWK(s) for OpenIDConnect)
 *
 * It's not a good idea to request it every time, because it's unneeded round trip to the server
 * if you are using OpenID or OpenIDConnect we suggest you to use cache
 */
$cache = null;

// Wrap Doctrine's cache with the PSR-6 adapter, I would like to use it
$cache = new DoctrineCachePool(
    new \Doctrine\Common\Cache\FilesystemCache(
        __DIR__ . '/cache/'
    )
);

/**
 * It's a collection of providers, by default it's \SocialConnect\Auth\CollectionFactory
 */
$providerFactory = null;

$service = new \SocialConnect\Auth\Service(
    new \SocialConnect\Common\Http\Client\Curl(),
    new \SocialConnect\Provider\Session\Session(),
    $configureProviders,
    $cache,
    $providerFactory
);

$app = new \Slim\App(
    [
        'settings' => [
            'displayErrorDetails' => true
        ]
    ]
);

$app->any('/dump', function() {
    dump($_POST);
    dump($_GET);
    dump($_SERVER);
});

$app->get('/auth/cb/{provider}/', function (\Slim\Http\Request $request) use (&$configureProviders, $service) {
    $provider = strtolower($request->getAttribute('provider'));

    if (!$service->getFactory()->has($provider)) {
        throw new \Exception('Wrong $provider passed in url : ' . $provider);
    }

    $provider = $service->getProvider($provider);

    $accessToken = $provider->getAccessTokenByRequestParameters($_GET);
    dump($accessToken);

    dump($accessToken->getUserId());

    $user = $provider->getIdentity($accessToken);
    dump($user);
});

$app->get('/', function () {
    include_once 'page.php';
});

$app->post('/', function () use (&$configureProviders, $service) {
    try {
        if (!empty($_POST['provider'])) {
            $providerName = $_POST['provider'];
        } else {
            throw new \Exception('No provider passed in POST Request');
        }

        $provider = $service->getProvider($providerName);

        header('Location: ' . $provider->makeAuthUrl());
    } catch (\Exception $e) {
        echo $e->getMessage();
    }
    exit;
});

$app->run();
